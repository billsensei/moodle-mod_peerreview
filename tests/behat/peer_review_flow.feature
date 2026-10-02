@mod @mod_peerreview @javascript
Feature: A whole peer review, from a rubric to the gradebook
  In order to run a peer assessment in class
  As a teacher
  I need to set up a rubric, allocate reviewers, let students review on their phones, release feedback and grade

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

  Scenario: Teacher sets up a rubric review, students review on phones, feedback is anonymous and grades reach the gradebook
    # The teacher creates the activity with a rubric.
    Given I log in as "teacher1"
    And I add a "peerreview" activity to course "Course 1" section "1" and I fill the form with:
      | Peer review name                     | Talk review |
      | Reviewer identity shown to reviewees | Anonymous   |
      | Grading method                       | Rubric      |
    And I go to "Talk review" advanced grading definition page
    And I set the following fields to these values:
      | Name | Talk rubric |
    # No 0-point levels: the core step drops "0" cells. With core's default "lockzeropoints" the grade is score / 6.
    And I define the following rubric:
      | Content  | Weak | 1 | Good | 2 | Excellent | 3 |
      | Delivery | Weak | 1 | Good | 2 | Excellent | 3 |
    And I press "Save rubric and make it ready"
    And I should see "Ready for use"

    # Random allocation, one review each: with two students each reviews the other.
    When I am on the "Talk review" "mod_peerreview > Allocate random" page
    And I set the field "Reviews per student" to "1"
    And I press "Preview"
    Then I should see "This will create 2 new allocations"
    And I press "Confirm and save"
    And I should see "2 allocations created, 0 removed."
    And I log out

    # Student 1 reviews on a phone-sized screen.
    Given I change viewport size to "mobile"
    And I am on the "Talk review" "mod_peerreview > View" page logged in as "student1"
    And I should see "0 of 1 reviews done"
    And the peer review page should not scroll horizontally
    When I click on "Bea Lee" "link" in the "region-main" "region"
    And I fill in the peer review rubric with:
      | Content  | 3 | Well researched |
      | Delivery | 2 | A little fast   |
    And I set the field "Overall comment" to "Clear slides, good examples"
    And I press "Submit review"
    Then I should see "Review submitted."
    And I should see "1 of 1 reviews done"
    And the peer review page should not scroll horizontally
    And I log out

    # Student 2 does the same.
    Given I am on the "Talk review" "mod_peerreview > View" page logged in as "student2"
    When I click on "Sam Ahn" "link" in the "region-main" "region"
    And the peer review page should not scroll horizontally
    And I fill in the peer review rubric with:
      | Content  | 2 | Some gaps        |
      | Delivery | 2 | Good eye contact |
    And I press "Submit review"
    Then I should see "Review submitted."
    # Feedback is not released yet.
    And I should not see "Feedback I received"
    And I log out

    # The teacher sees the progress, releases feedback and sends the grades.
    Given I change viewport size to "large"
    And I am on the "Talk review" "mod_peerreview > Report" page logged in as "teacher1"
    # Reviews given, reviews received and the aggregated received grade per student.
    And I should see "1 / 1" in the "Sam Ahn" "table_row"
    And I should see "66.67" in the "Sam Ahn" "table_row"
    And I should see "1 / 1" in the "Bea Lee" "table_row"
    And I should see "83.33" in the "Bea Lee" "table_row"
    # The class average per rubric criterion (3 and 2 on Content, 2 and 2 on Delivery, out of 3).
    And I should see "Average by criterion"
    And I should see "2.50" in the "Content" "table_row"
    And I should see "2.00" in the "Delivery" "table_row"
    When I press "Release feedback"
    Then I should see "Feedback is now visible to students."
    And I press "Push grades to gradebook"
    And I should see "Grades were sent to the gradebook."
    And I log out

    # Student 2 sees anonymous feedback and the grade, also in the gradebook.
    Given I change viewport size to "mobile"
    And I am on the "Talk review" "mod_peerreview > View" page logged in as "student2"
    Then I should see "Feedback I received"
    And I should see "Anonymous"
    And I should see "83.33 / 100"
    And I should see "Clear slides, good examples"
    # Bea reviewed Sam, so his name is on her own "Reviews to do" card, but never next to the feedback she received.
    And I should not see "Sam Ahn" in the "section[aria-labelledby='mod_peerreview-received-heading']" "css_element"
    And the peer review page should not scroll horizontally
    And I click on "View details" "link" in the "section[aria-labelledby='mod_peerreview-received-heading']" "css_element"
    And I should see "Well researched"
    And I should not see "Sam Ahn" in the "region-main" "region"
    And the peer review page should not scroll horizontally
    And I change viewport size to "large"
    And I am on the "Course 1" "grades > User report > View" page
    And I should see "83.33" in the "Talk review" "table_row"
