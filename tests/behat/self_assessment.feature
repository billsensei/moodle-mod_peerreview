@mod @mod_peerreview @javascript
Feature: Self-assessment
  In order to let students compare their own view with their classmates' view
  As a teacher
  I need to give every student a review of themselves

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tia       | Teacher  | teacher1@example.com |
      | student1 | Sam       | Ahn      | student1@example.com |
      | student2 | Bea       | Lee      | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |

  Scenario: A teacher gives every student a self-assessment
    Given the following "activities" exist:
      | activity   | name        | course | idnumber | allowselfreview |
      | peerreview | Talk review | C1     | pr1      | 1               |
    And the following peer review allocations exist:
      | activity    | reviewer | reviewee |
      | Talk review | student1 | student1 |
    When I am on the "Talk review" "mod_peerreview > Allocate self" page logged in as "teacher1"
    And I press "Preview"
    Then I should see "This will create 1 new allocations"
    And I press "Confirm and save"
    And I should see "1 allocations created, 0 removed."

  Scenario: The self-assessment method needs "Allow self-review"
    Given the following "activities" exist:
      | activity   | name        | course | idnumber |
      | peerreview | Talk review | C1     | pr1      |
    When I am on the "Talk review" "mod_peerreview > Allocate self" page logged in as "teacher1"
    And I press "Preview"
    Then I should see "Self-review is not allowed in this activity, so nothing was proposed"
