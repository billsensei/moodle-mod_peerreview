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
    Then the "reminderlead[0][number]" "field" should be disabled
    When I set the following fields to these values:
      | timeclose[enabled]        | 1    |
      | timeclose[year]           | 2035 |
      | reminderlead[0][enabled]  | 1    |
      | reminderlead[0][number]   | 2    |
      | reminderlead[0][timeunit] | days |
    And I press "Save and display"
    And I am on the "Talk review" "peerreview activity editing" page
    Then the field "reminderlead[0][enabled]" matches value "1"
    And the field "reminderlead[0][number]" matches value "2"
    And the field "reminderlead[0][timeunit]" matches value "days"

  Scenario: The reminder can be any length of time, not only whole days
    Given I am on the "Talk review" "peerreview activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | timeclose[enabled]        | 1     |
      | timeclose[year]           | 2035  |
      | reminderlead[0][enabled]  | 1     |
      | reminderlead[0][number]   | 36    |
      | reminderlead[0][timeunit] | hours |
    And I press "Save and display"
    And I am on the "Talk review" "peerreview activity editing" page
    Then the field "reminderlead[0][number]" matches value "36"
    And the field "reminderlead[0][timeunit]" matches value "hours"
    When I set the following fields to these values:
      | reminderlead[0][number]   | 3     |
      | reminderlead[0][timeunit] | weeks |
    And I press "Save and display"
    And I am on the "Talk review" "peerreview activity editing" page
    Then the field "reminderlead[0][number]" matches value "3"
    And the field "reminderlead[0][timeunit]" matches value "weeks"

  Scenario: An activity can have several reminders
    Given I am on the "Talk review" "peerreview activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | timeclose[enabled]        | 1     |
      | timeclose[year]           | 2035  |
      | reminderlead[0][enabled]  | 1     |
      | reminderlead[0][number]   | 1     |
      | reminderlead[0][timeunit] | days  |
    And I press "Add another reminder"
    And I set the following fields to these values:
      | reminderlead[1][enabled]  | 1     |
      | reminderlead[1][number]   | 1     |
      | reminderlead[1][timeunit] | weeks |
    And I press "Save and display"
    And I am on the "Talk review" "peerreview activity editing" page
    Then the field "reminderlead[0][number]" matches value "1"
    And the field "reminderlead[0][timeunit]" matches value "weeks"
    And the field "reminderlead[1][number]" matches value "1"
    And the field "reminderlead[1][timeunit]" matches value "days"

  Scenario: A reminder shorter than the hourly check is refused
    Given I am on the "Talk review" "peerreview activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | timeclose[enabled]        | 1     |
      | timeclose[year]           | 2035  |
      | reminderlead[0][enabled]  | 1     |
      | reminderlead[0][number]   | 0.5   |
      | reminderlead[0][timeunit] | hours |
    And I press "Save and display"
    Then I should see "The reminder must be at least 1 hour before the close date"

  Scenario: The same reminder twice is refused
    Given I am on the "Talk review" "peerreview activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | timeclose[enabled]        | 1     |
      | timeclose[year]           | 2035  |
      | reminderlead[0][enabled]  | 1     |
      | reminderlead[0][number]   | 2     |
      | reminderlead[0][timeunit] | days  |
    And I press "Add another reminder"
    And I set the following fields to these values:
      | reminderlead[1][enabled]  | 1     |
      | reminderlead[1][number]   | 48    |
      | reminderlead[1][timeunit] | hours |
    And I press "Save and display"
    Then I should see "There is already a reminder at this time."
