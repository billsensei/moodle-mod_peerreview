@mod @mod_peerreview @javascript
Feature: A wide rubric fits on a phone
  In order to review on a phone
  As a student
  I need a rubric with four levels and three criteria to fit the screen, for the review form and for the received feedback

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

  Scenario: The review form and the received feedback do not scroll sideways with a four-level rubric
    Given I log in as "teacher1"
    And I add a "peerreview" activity to course "Course 1" section "1" and I fill the form with:
      | Peer review name | Talk review |
      | Grading method   | Rubric      |
    And I go to "Talk review" advanced grading definition page
    And I set the following fields to these values:
      | Name | Wide rubric |
    And I define the following rubric:
      | Content  | Off topic | 1 | Some gaps      | 2 | Solid    | 3 | Excellent | 4 |
      | Delivery | Hard to follow | 1 | Uneven         | 2 | Clear    | 3 | Engaging  | 4 |
      | Slides   | Missing   | 1 | Cluttered      | 2 | Readable | 3 | Polished  | 4 |
    And I press "Save rubric and make it ready"
    And the following peer review allocations exist:
      | activity    | reviewer | reviewee |
      | Talk review | student1 | student2 |
    And I log out
    When I change viewport size to "mobile"
    And I am on the "Talk review: student1 reviews student2" "mod_peerreview > Review" page logged in as "student1"
    Then the peer review page should not scroll horizontally
    And I fill in the peer review rubric with:
      | Content  | 4 | Very well researched |
      | Delivery | 3 | Clear                |
      | Slides   | 2 | Too much text        |
    And I press "Submit review"
    And I should see "Review submitted."
    And I log out
    And I change viewport size to "large"
    And I am on the "Talk review" "mod_peerreview > Report" page logged in as "teacher1"
    And I press "Release feedback"
    And I log out
    And I change viewport size to "mobile"
    And I am on the "Talk review" "mod_peerreview > View" page logged in as "student2"
    And I click on "View details" "link" in the "section[aria-labelledby='mod_peerreview-received-heading']" "css_element"
    And I should see "Very well researched"
    And the peer review page should not scroll horizontally
