@mod @mod_peerreview
Feature: Reviews are only visible to teachers who may see the reviewee's group
  In order to respect separate groups
  As a teacher without access to all groups
  I can open reviews of students in my groups but not of students in other groups

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tia       | Teacher  | teacher1@example.com |
      | teacher2 | Ed        | Editor   | teacher2@example.com |
      | student1 | Sam       | Ahn      | student1@example.com |
      | student2 | Bea       | Lee      | student2@example.com |
      | student3 | Cal       | Wong     | student3@example.com |
      | student4 | Dee       | Park     | student4@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | teacher        |
      | teacher2 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
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
      | student4 | GB    |
    And the following "activities" exist:
      | activity   | name        | course | idnumber | groupmode |
      | peerreview | Talk review | C1     | pr1      | 1         |
    And the following peer review allocations exist:
      | activity    | reviewer | reviewee |
      | Talk review | student1 | student2 |
      | Talk review | student3 | student4 |

  Scenario: A teacher restricted to Group A opens a Group A review
    When I am on the "Talk review: student1 reviews student2" "mod_peerreview > Review" page logged in as "teacher1"
    Then I should see "Review of Bea Lee"
    And I should see "Written by Sam Ahn"

  Scenario: A teacher restricted to Group A cannot open a Group B review, even by guessing the link
    Given I log in as "teacher1"
    Then I should be refused the "Talk review: student3 reviews student4" peer review

  Scenario: A teacher who can access all groups opens reviews of every group
    When I am on the "Talk review: student1 reviews student2" "mod_peerreview > Review" page logged in as "teacher2"
    Then I should see "Review of Bea Lee"
    And I am on the "Talk review: student3 reviews student4" "mod_peerreview > Review" page
    And I should see "Review of Dee Park"

  Scenario: A student can open their own review but not someone else's
    When I am on the "Talk review: student3 reviews student4" "mod_peerreview > Review" page logged in as "student3"
    Then I should see "Review of Dee Park"
    And I should be refused the "Talk review: student1 reviews student2" peer review
