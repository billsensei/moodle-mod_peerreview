@mod @mod_peerreview @javascript
Feature: A peer review graded with a scale
  In order to give word grades instead of points
  As a teacher
  I need to choose a scale for the activity, so reviewers pick items and the report and gradebook show item names

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
    And the following "scales" exist:
      | name       | scale                       |
      | Talk marks | Poor, Fair, Good, Excellent |

  Scenario: Reviewers pick a scale item and the teacher sees and overrides item names
    Given I log in as "teacher1"
    And I add a "peerreview" activity to course "Course 1" section "1" and I fill the form with:
      | Peer review name | Talk review |
      | Type             | Scale       |
      | Scale            | Talk marks  |
    And the following peer review allocations exist:
      | activity    | reviewer | reviewee |
      | Talk review | student1 | student2 |
    And I log out

    # The reviewer chooses an item from a list; there is no number to type.
    Given I am on the "Talk review" "mod_peerreview > View" page logged in as "student1"
    When I click on "Bea Lee" "link" in the "region-main" "region"
    And I set the field "Score" to "Good"
    And I press "Submit review"
    Then I should see "Review submitted."
    And I log out

    # The report shows the item name; the override form offers the items.
    Given I am on the "Talk review" "mod_peerreview > Report" page logged in as "teacher1"
    Then I should see "Good" in the "Bea Lee" "table_row"
    When I click on "Bea Lee" "link" in the "region-main" "region"
    And I set the field "Grade" to "Excellent"
    And I set the field "Note (why)" to "Outstanding questions"
    And I press "Save override"
    Then I should see "Override saved"
    And I am on the "Talk review" "mod_peerreview > Report" page
    And I should see "Excellent" in the "Bea Lee" "table_row"
    And I press "Release feedback"
    And I should see "Feedback is now visible to students."
    And I press "Push grades to gradebook"
    And I should see "Grades were sent to the gradebook."
    And I log out

    # The student sees the item name, also in the gradebook.
    Given I am on the "Talk review" "mod_peerreview > View" page logged in as "student2"
    Then I should see "Excellent"
    And I am on the "Course 1" "grades > User report > View" page
    And I should see "Excellent" in the "Talk review" "table_row"
