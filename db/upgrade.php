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
 * Upgrade steps for mod_peerreview.
 *
 * @package    mod_peerreview
 * @category   upgrade
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the mod_peerreview database structure and data.
 *
 * Add each new step below with its own upgrade_mod_savepoint() call.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Always true.
 */
function xmldb_peerreview_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026100204) {
        // Automatic reminders before the close date (release 0.12.0).
        $table = new xmldb_table('peerreview');
        $field = new xmldb_field('reminderlead', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeclose');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('remindersentfor', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'reminderlead');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026100204, 'peerreview');
    }

    if ($oldversion < 2026100301) {
        peerreview_upgrade_reminder_table($dbman);
        upgrade_mod_savepoint(true, 2026100301, 'peerreview');
    }

    return true;
}

/**
 * Several automatic reminders per activity: one row each in peerreview_reminder instead of the two columns reminderlead
 * and remindersentfor of peerreview, which hold at most one. Each activity's reminder is kept, with whether it was sent.
 *
 * @param database_manager $dbman The database manager.
 */
function peerreview_upgrade_reminder_table(database_manager $dbman): void {
    global $DB;

    $table = new xmldb_table('peerreview_reminder');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('peerreviewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('leadtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sentfor', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('peerreviewid', XMLDB_KEY_FOREIGN, ['peerreviewid'], 'peerreview', ['id']);
        $table->add_index('peerreview_leadtime', XMLDB_INDEX_UNIQUE, ['peerreviewid', 'leadtime']);
        $dbman->create_table($table);
    }

    $peerreview = new xmldb_table('peerreview');
    $leadfield = new xmldb_field('reminderlead');
    $sentfield = new xmldb_field('remindersentfor');
    if ($dbman->field_exists($peerreview, $leadfield)) {
        $activities = $DB->get_recordset_select(
            'peerreview',
            'reminderlead > 0',
            null,
            'id',
            'id, reminderlead, remindersentfor'
        );
        foreach ($activities as $activity) {
            $row = ['peerreviewid' => $activity->id, 'leadtime' => $activity->reminderlead];
            if (!$DB->record_exists('peerreview_reminder', $row)) {
                $DB->insert_record('peerreview_reminder', (object) ($row + ['sentfor' => $activity->remindersentfor]));
            }
        }
        $activities->close();
        $dbman->drop_field($peerreview, $leadfield);
    }
    if ($dbman->field_exists($peerreview, $sentfield)) {
        $dbman->drop_field($peerreview, $sentfield);
    }
}
