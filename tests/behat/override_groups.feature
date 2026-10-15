@mod @mod_peerreview @javascript
Feature: Grade overrides and the report drill-down respect separate groups
  In order to keep groups apart
  As a teacher who may override grades but cannot access all groups
  I can revert overrides and see partners only in my own groups

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tia       | Teacher  | teacher1@example.com |
      | student1 | Sam       | Ahn      | student1@example.com |
      | student2 | Bea       | Lee      | student2@example.com |
      | student3 | Cal       | Wong     | student3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | student3 | C1     | student |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | GA       |
      | Group B | C1     | GB       |
    And the following "group members" exist:
      | user     | group |
      | teacher1 | GA    |
      | student1 | GA    |
      | student2 | GA    |
      | student3 | GB    |
    And the following "activities" exist:
      | activity   | name        | course | idnumber | groupmode |
      | peerreview | Talk review | C1     | pr1      | 1         |
    # The non-editing teacher may override grades, but still cannot access all groups.
    And the following "permission overrides" exist:
      | capability                     | permission | role    | contextlevel    | reference |
      | mod/peerreview:overridegrade   | Allow      | teacher | Activity module | pr1       |
    And the following peer review allocations exist:
      | activity    | reviewer | reviewee |
      | Talk review | student1 | student2 |
      | Talk review | student1 | student3 |
    And the following peer review overrides exist:
      | activity    | user     | grade |
      | Talk review | student2 | 80    |
      | Talk review | student3 | 70    |

  Scenario: The teacher can revert an override in their own group but not in another group
    Given I log in as "teacher1"
    Then reverting the peer review override of "student3" in "Talk review" should be "refused"
    And the peer review override of "student3" in "Talk review" should "exist"
    And reverting the peer review override of "student2" in "Talk review" should be "accepted"
    And the peer review override of "student2" in "Talk review" should "not exist"

  Scenario: The report drill-down lists only partners from the teacher's own groups
    When I am on the "Talk review" "mod_peerreview > Report" page logged in as "teacher1"
    And I click on "Sam Ahn" "link" in the "region-main" "region"
    Then I should see "Bea Lee"
    And I should not see "Cal Wong"
