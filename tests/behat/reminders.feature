@mod @mod_peerreview @javascript
Feature: Reminders for students with reviews left to do
  In order to get every review finished during the lesson
  As a teacher
  I need to remind the students who have not finished their reviews

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
    And the following "activities" exist:
      | activity   | name        | course | idnumber |
      | peerreview | Talk review | C1     | pr1      |
    And the following peer review allocations exist:
      | activity    | reviewer | reviewee |
      | Talk review | student1 | student2 |

  Scenario: A teacher reminds the students who still have reviews to do
    Given I am on the "Talk review" "mod_peerreview > Report" page logged in as "teacher1"
    When I press "Remind students with reviews to do"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    Then I should see "1 students were sent a reminder."

  Scenario: A teacher turns on the automatic reminder, which needs a close date
    Given I am on the "Talk review" "peerreview activity editing" page logged in as "teacher1"
    Then the "reminderlead[number]" "field" should be disabled
    When I set the following fields to these values:
      | timeclose[enabled]     | 1    |
      | timeclose[year]        | 2035 |
      | reminderlead[enabled]  | 1    |
      | reminderlead[number]   | 2    |
      | reminderlead[timeunit] | days |
    And I press "Save and display"
    And I am on the "Talk review" "peerreview activity editing" page
    Then the field "reminderlead[enabled]" matches value "1"
    And the field "reminderlead[number]" matches value "2"
    And the field "reminderlead[timeunit]" matches value "days"

  Scenario: The reminder can be any length of time, not only whole days
    Given I am on the "Talk review" "peerreview activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | timeclose[enabled]     | 1     |
      | timeclose[year]        | 2035  |
      | reminderlead[enabled]  | 1     |
      | reminderlead[number]   | 36    |
      | reminderlead[timeunit] | hours |
    And I press "Save and display"
    And I am on the "Talk review" "peerreview activity editing" page
    Then the field "reminderlead[number]" matches value "36"
    And the field "reminderlead[timeunit]" matches value "hours"
    When I set the following fields to these values:
      | reminderlead[number]   | 3     |
      | reminderlead[timeunit] | weeks |
    And I press "Save and display"
    And I am on the "Talk review" "peerreview activity editing" page
    Then the field "reminderlead[number]" matches value "3"
    And the field "reminderlead[timeunit]" matches value "weeks"

  Scenario: A reminder shorter than the hourly check is refused
    Given I am on the "Talk review" "peerreview activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | timeclose[enabled]     | 1     |
      | timeclose[year]        | 2035  |
      | reminderlead[enabled]  | 1     |
      | reminderlead[number]   | 0.5   |
      | reminderlead[timeunit] | hours |
    And I press "Save and display"
    Then I should see "The reminder must be at least 1 hour before the close date"
