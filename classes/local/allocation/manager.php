<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database-backed allocation service: who can be allocated, saving, deleting, CSV.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

use core_user\fields;

/**
 * Allocation service for one peer review activity.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int Allocation not started. */
    public const STATUS_NEW = 0;

    /** @var int Review saved as a draft. */
    public const STATUS_DRAFT = 1;

    /** @var int Review submitted. */
    public const STATUS_SUBMITTED = 2;

    /** @var \stdClass[]|null Eligible students, userid => user record. */
    private ?array $students = null;

    /** @var int[]|null userid => lowest group id of the user (0 for none). */
    private ?array $primarygroup = null;

    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \stdClass $cm Course module record (needs id, course, groupmode, groupingid).
     * @param \context_module $context Module context.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \stdClass Course module record. */
        private readonly \stdClass $cm,
        /** @var \context_module Module context. */
        private readonly \context_module $context
    ) {
    }

    /**
     * Students who can review and be reviewed: active enrolments holding mod/peerreview:review.
     *
     * @return \stdClass[] userid => user record (picture fields, username, email), ordered by name.
     */
    public function get_students(): array {
        if ($this->students === null) {
            $fields = implode(',', array_map(
                static fn($field) => 'u.' . $field,
                array_unique(array_merge(fields::get_picture_fields(), ['username']))
            ));
            $this->students = get_enrolled_users(
                $this->context,
                'mod/peerreview:review',
                0,
                $fields,
                'u.lastname, u.firstname, u.id',
                0,
                0,
                true
            );
        }
        return $this->students;
    }

    /**
     * Group ids of the course (restricted to the activity's grouping) with their student members.
     *
     * @return int[][] Group id => student user ids. Students in no group are not listed.
     */
    public function get_group_members(): array {
        $students = $this->get_students();
        $groups = groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid, 'g.id, g.name', true);
        $result = [];
        foreach ($groups as $group) {
            $members = array_values(array_intersect(array_keys($group->members), array_keys($students)));
            if ($members) {
                $result[$group->id] = $members;
            }
        }
        return $result;
    }

    /**
     * Pools for the random method.
     *
     * With no group mode, or with cross-group allowed, everybody is in one pool. Otherwise there is one pool per
     * group (a student in several groups goes to the lowest group id) and one pool (id 0) for students in no group.
     *
     * @param bool $crossgroup Allow reviewing across groups.
     * @return int[][] Pool id => user ids.
     */
    public function get_pools(bool $crossgroup): array {
        $students = array_keys($this->get_students());
        if ($crossgroup || (int) $this->cm->groupmode === NOGROUPS) {
            return $students ? [0 => $students] : [];
        }
        $pools = [];
        foreach ($students as $userid) {
            $pools[$this->get_primary_group($userid)][] = $userid;
        }
        ksort($pools);
        return $pools;
    }

    /**
     * The lowest-numbered group of a student, or 0 when group mode is off or the student is in no group.
     *
     * @param int $userid
     * @return int
     */
    public function get_primary_group(int $userid): int {
        if ($this->primarygroup === null) {
            $this->primarygroup = [];
            if ((int) $this->cm->groupmode !== NOGROUPS) {
                $members = $this->get_group_members();
                ksort($members);
                foreach (array_reverse($members, true) as $groupid => $userids) {
                    foreach ($userids as $memberid) {
                        $this->primarygroup[$memberid] = $groupid;
                    }
                }
            }
        }
        return $this->primarygroup[$userid] ?? 0;
    }

    /**
     * All allocation records of this activity.
     *
     * @return \stdClass[] id => record.
     */
    public function get_allocations(): array {
        global $DB;
        return $DB->get_records('peerreview_alloc', ['peerreviewid' => $this->peerreview->id], 'id');
    }

    /**
     * Existing pairs as [reviewerid, revieweeid] lists.
     *
     * @param \stdClass[]|null $allocations Allocation records, defaults to all.
     * @return int[][]
     */
    public function get_pairs(?array $allocations = null): array {
        $pairs = [];
        foreach ($allocations ?? $this->get_allocations() as $alloc) {
            $pairs[] = [(int) $alloc->reviewerid, (int) $alloc->revieweeid];
        }
        return $pairs;
    }

    /**
     * Ordered list of students for the rotation method.
     *
     * @param string $sortby lastname, firstname, username or random.
     * @param int $seed Seed for random.
     * @param string $usernames Optional explicit order, one username per line (overrides $sortby).
     * @return array [int[] ordered user ids, string[] unknown usernames]
     */
    public function get_rotation_order(string $sortby, int $seed, string $usernames = ''): array {
        $students = $this->get_students();
        $usernames = array_filter(array_map('trim', preg_split('/\R/', $usernames)));
        if ($usernames) {
            $byusername = [];
            foreach ($students as $student) {
                $byusername[\core_text::strtolower($student->username)] = (int) $student->id;
            }
            $order = [];
            $unknown = [];
            foreach ($usernames as $username) {
                $key = \core_text::strtolower($username);
                if (isset($byusername[$key])) {
                    $order[] = $byusername[$key];
                } else {
                    $unknown[] = $username;
                }
            }
            return [$order, $unknown];
        }

        $list = array_values($students);
        if ($sortby === 'random') {
            $ids = random_allocator::seeded($seed)->shuffleArray(array_map(static fn($s) => (int) $s->id, $list));
            return [$ids, []];
        }
        $field = in_array($sortby, ['firstname', 'username'], true) ? $sortby : 'lastname';
        usort($list, static fn($a, $b) => \core_text::strtolower($a->$field) <=> \core_text::strtolower($b->$field)
            ?: $a->id <=> $b->id);
        return [array_map(static fn($s) => (int) $s->id, $list), []];
    }

    /**
     * Allocations that a "replace" run would remove: not started and inside the pools involved.
     *
     * @param int[][] $pools Pool id => user ids in scope.
     * @return \stdClass[] Allocation records (status new only).
     */
    public function get_replaceable(array $pools): array {
        $inscope = [];
        foreach ($pools as $members) {
            foreach ($members as $userid) {
                $inscope[$userid] = true;
            }
        }
        return array_filter(
            $this->get_allocations(),
            static fn($alloc) => (int) $alloc->status === self::STATUS_NEW
                && isset($inscope[$alloc->reviewerid], $inscope[$alloc->revieweeid])
        );
    }

    /**
     * Count of allocations in scope that have a saved or submitted review (they are always kept by "replace").
     *
     * @param int[][] $pools Pool id => user ids in scope.
     * @return int
     */
    public function count_started_in_scope(array $pools): int {
        $inscope = [];
        foreach ($pools as $members) {
            foreach ($members as $userid) {
                $inscope[$userid] = true;
            }
        }
        return count(array_filter(
            $this->get_allocations(),
            static fn($alloc) => (int) $alloc->status > self::STATUS_NEW
                && isset($inscope[$alloc->reviewerid], $inscope[$alloc->revieweeid])
        ));
    }

    /**
     * Work out what an automatic method would do, without changing anything.
     *
     * Params by method: random (n, crossgroup, replace), group (groupid, replace),
     * rotation (shift, sortby, usernames, replace). With "replace", not-started allocations in scope are removed
     * first; started ones are always kept.
     *
     * @param string $method random, group or rotation.
     * @param array $params Method parameters.
     * @param int $seed Seed making the result reproducible.
     * @return \stdClass With proposal, deleteids, keptstarted and the resulting per-user totals.
     */
    public function plan(string $method, array $params, int $seed): \stdClass {
        $groupallocator = new group_allocator();
        $unknown = [];
        switch ($method) {
            case 'random':
                $pools = $this->get_pools(!empty($params['crossgroup']));
                break;
            case 'group':
                $pools = $this->get_group_members();
                if (!empty($params['groupid'])) {
                    $pools = array_intersect_key($pools, [(int) $params['groupid'] => true]);
                }
                break;
            case 'rotation':
                [$order, $unknown] = $this->get_rotation_order(
                    $params['sortby'] ?? 'lastname',
                    $seed,
                    $params['usernames'] ?? ''
                );
                $pools = [0 => $order];
                break;
            default:
                throw new \coding_exception('Unknown allocation method ' . $method);
        }

        $allocations = $this->get_allocations();
        $deleteids = [];
        if (!empty($params['replace'])) {
            $deleteids = array_keys($this->get_replaceable($pools));
            $allocations = array_diff_key($allocations, array_flip($deleteids));
        }
        $existing = $this->get_pairs($allocations);

        switch ($method) {
            case 'random':
                $proposal = (new random_allocator(random_allocator::seeded($seed)))
                    ->generate($pools, max(1, (int) $params['n']), $existing);
                break;
            case 'group':
                $proposal = $groupallocator->all_to_all($pools, $existing);
                if (!$pools) {
                    $proposal->warn('errornogroups');
                }
                break;
            default:
                $proposal = $groupallocator->rotation($pools[0], (int) $params['shift'], $existing);
        }
        foreach ($unknown as $username) {
            $proposal->warn('warnunknownusername', $username);
        }

        $result = new \stdClass();
        $result->proposal = $proposal;
        $result->deleteids = $deleteids;
        $result->keptstarted = $this->count_started_in_scope($pools);
        [$result->given, $result->received] = $proposal->totals($existing);
        $result->existingpairs = $existing;
        return $result;
    }

    /**
     * Insert the pairs of a proposal, skipping any that already exist, and optionally delete unstarted allocations.
     *
     * @param proposal $proposal
     * @param int $byuserid User doing the allocation.
     * @param int[] $deleteids Ids of not-started allocations to delete first ("replace").
     * @return int Number of pairs created.
     */
    public function save(proposal $proposal, int $byuserid, array $deleteids = []): int {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        if ($deleteids) {
            $this->delete($deleteids, false);
        }
        $created = 0;
        foreach ($proposal->pairs as [$reviewerid, $revieweeid]) {
            if ($this->add_pair($reviewerid, $revieweeid, $byuserid)) {
                $created++;
            }
        }
        $transaction->allow_commit();
        // Reviewers who just received new work are no longer "complete".
        \mod_peerreview\local\completion::update($this->peerreview, array_column($proposal->pairs, 0));
        return $created;
    }

    /**
     * Manual allocation: one reviewer, several reviewees.
     *
     * @param int $reviewerid
     * @param int[] $revieweeids
     * @param int $byuserid
     * @return array [int created, string[] error lang identifiers keyed by reviewee id]
     */
    public function add_manual(int $reviewerid, array $revieweeids, int $byuserid): array {
        $students = $this->get_students();
        $proposal = new proposal();
        $errors = [];
        if (!isset($students[$reviewerid])) {
            return [0, [$reviewerid => 'errornotstudent']];
        }
        foreach ($revieweeids as $revieweeid) {
            $revieweeid = (int) $revieweeid;
            if (!isset($students[$revieweeid])) {
                $errors[$revieweeid] = 'errornotstudent';
            } else if ($revieweeid === $reviewerid && empty($this->peerreview->allowselfreview)) {
                $errors[$revieweeid] = 'errorselfnotallowed';
            } else {
                $proposal->add($reviewerid, $revieweeid);
            }
        }
        return [$this->save($proposal, $byuserid), $errors];
    }

    /**
     * Create one allocation unless it already exists.
     *
     * @param int $reviewerid
     * @param int $revieweeid
     * @param int $byuserid
     * @return bool True if a row was created.
     */
    private function add_pair(int $reviewerid, int $revieweeid, int $byuserid): bool {
        global $DB;

        $conditions = [
            'peerreviewid' => $this->peerreview->id,
            'reviewerid' => $reviewerid,
            'revieweeid' => $revieweeid,
        ];
        if ($DB->record_exists('peerreview_alloc', $conditions)) {
            return false;
        }
        $now = time();
        $record = (object) ($conditions + [
            'groupid' => $this->get_primary_group($reviewerid),
            'status' => self::STATUS_NEW,
            'feedbackformat' => FORMAT_HTML,
            'allocatedby' => $byuserid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $record->id = $DB->insert_record('peerreview_alloc', $record);
        \mod_peerreview\event\allocation_created::create([
            'objectid' => $record->id,
            'context' => $this->context,
            'relateduserid' => $revieweeid,
            'other' => ['reviewerid' => $reviewerid],
        ])->trigger();
        return true;
    }

    /**
     * How many of the given allocations have a saved or submitted review.
     *
     * @param int[] $ids Allocation ids.
     * @return int
     */
    public function count_started(array $ids): int {
        global $DB;

        if (!$ids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        return $DB->count_records_select(
            'peerreview_alloc',
            "peerreviewid = :pr AND status > :new AND id $insql",
            $params + ['pr' => $this->peerreview->id, 'new' => self::STATUS_NEW]
        );
    }

    /**
     * Delete allocations. Started reviews are never deleted unless the caller says it has confirmed.
     *
     * @param int[] $ids Allocation ids (ids of other activities are ignored).
     * @param bool $confirmedstarted The teacher confirmed deleting saved or submitted reviews.
     * @return int Number deleted.
     * @throws \moodle_exception If started reviews are included and not confirmed.
     */
    public function delete(array $ids, bool $confirmedstarted): int {
        global $DB;

        if (!$ids) {
            return 0;
        }
        if (!$confirmedstarted && $this->count_started($ids) > 0) {
            throw new \moodle_exception('errorstartedreviews', 'mod_peerreview');
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            'peerreview_alloc',
            "peerreviewid = :pr AND id $insql",
            $params + ['pr' => $this->peerreview->id]
        );
        if ($records) {
            // Remove the advanced grading instances (rubric/guide fillings) of these allocations.
            \core_grading\privacy\provider::delete_data_for_instances($this->context, array_keys($records));
        }
        foreach ($records as $record) {
            $DB->delete_records('peerreview_alloc', ['id' => $record->id]);
            \mod_peerreview\event\allocation_deleted::create([
                'objectid' => $record->id,
                'context' => $this->context,
                'relateduserid' => $record->revieweeid,
                'other' => ['reviewerid' => $record->reviewerid],
            ])->trigger();
        }
        // A reviewer whose remaining reviews are all submitted may now be complete (or has no work left at all).
        \mod_peerreview\local\completion::update($this->peerreview, array_column((array) $records, 'reviewerid'));
        return count($records);
    }

    /**
     * Resolve a CSV cell (username or email) to an eligible student id.
     *
     * @param string $value
     * @return int|null
     */
    private function find_student(string $value): ?int {
        $value = \core_text::strtolower(trim($value));
        foreach ($this->get_students() as $student) {
            if (\core_text::strtolower($student->username) === $value || \core_text::strtolower($student->email) === $value) {
                return (int) $student->id;
            }
        }
        return null;
    }

    /**
     * Validate CSV rows (reviewer, reviewee) and turn the good ones into a proposal.
     *
     * @param string[][] $rows Each [reviewer, reviewee] cell values, keyed by line number.
     * @return array [proposal $proposal, array[] $errors each [line, lang identifier, $a], int $duplicates]
     */
    public function build_csv_proposal(array $rows): array {
        $proposal = new proposal();
        $errors = [];
        $duplicates = 0;
        $seen = [];
        foreach ($this->get_pairs() as [$reviewer, $reviewee]) {
            $seen[$reviewer . '_' . $reviewee] = true;
        }
        foreach ($rows as $line => [$reviewercell, $revieweecell]) {
            $reviewerid = $this->find_student($reviewercell);
            $revieweeid = $this->find_student($revieweecell);
            if ($reviewerid === null) {
                $errors[] = [$line, 'csverrorunknown', $reviewercell];
            } else if ($revieweeid === null) {
                $errors[] = [$line, 'csverrorunknown', $revieweecell];
            } else if ($reviewerid === $revieweeid && empty($this->peerreview->allowselfreview)) {
                $errors[] = [$line, 'errorselfnotallowed', $reviewercell];
            } else if (isset($seen[$reviewerid . '_' . $revieweeid])) {
                $duplicates++;
            } else {
                $seen[$reviewerid . '_' . $revieweeid] = true;
                $proposal->add($reviewerid, $revieweeid);
            }
        }
        return [$proposal, $errors, $duplicates];
    }

    /**
     * Rows for the CSV export.
     *
     * @return \Generator Objects with reviewer, reviewee, status.
     */
    public function export_rows(): \Generator {
        global $DB;

        $sql = "SELECT a.id, a.status, r.username AS reviewer, e.username AS reviewee
                  FROM {peerreview_alloc} a
                  JOIN {user} r ON r.id = a.reviewerid
                  JOIN {user} e ON e.id = a.revieweeid
                 WHERE a.peerreviewid = :pr
              ORDER BY r.lastname, r.firstname, e.lastname, e.firstname, a.id";
        $statuses = [
            self::STATUS_NEW => get_string('statusnew', 'mod_peerreview'),
            self::STATUS_DRAFT => get_string('statusdraft', 'mod_peerreview'),
            self::STATUS_SUBMITTED => get_string('statussubmitted', 'mod_peerreview'),
        ];
        $rs = $DB->get_recordset_sql($sql, ['pr' => $this->peerreview->id]);
        foreach ($rs as $row) {
            yield (object) [
                'reviewer' => $row->reviewer,
                'reviewee' => $row->reviewee,
                'status' => $statuses[$row->status],
            ];
        }
        $rs->close();
    }
}
