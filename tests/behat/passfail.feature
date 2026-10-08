@local @local_bandedgrade
Feature: Pass or fail with one pass mark
  In order to give a simple pass or fail
  As a teacher
  I need to type only the pass mark in the quiz settings and see pass and fail in the gradebook

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext |
      | Test questions   | truefalse | TF1  | Question 1   |
      | Test questions   | truefalse | TF2  | Question 2   |
      | Test questions   | truefalse | TF3  | Question 3   |
      | Test questions   | truefalse | TF4  | Question 4   |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 1    |
      | TF3      | 1    |
      | TF4      | 1    |

  @javascript
  Scenario: A pass mark gives pass and fail scores, with a live preview
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | Grade by number correct | 1                             |
      | How to give scores      | Pass or fail (one pass mark)  |
      | Pass mark               | 3                             |
      | Score for a pass        | 10                            |
      | Score for a fail        | 2                             |
    Then I should see "0 to 2 correct → score 2"
    And I should see "3 to 4 correct → score 10"
    And I should not see "Band 1: from this many correct"
    And I press "Save and return to course"
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | True     |
      | 3    | True     |
      | 4    | False    |
    And I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    And the following should exist in the "user-grades" table:
      | -1-         | -4-   |
      | Student One | 10.00 |
    And I am on the "Quiz 1" "quiz activity editing" page
    And the field "How to give scores" matches value "Pass or fail (one pass mark)"
    And the field "Pass mark" matches value "3"

  Scenario: A pass mark of zero is refused in plain language
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | Grade by number correct | 1                             |
      | How to give scores      | Pass or fail (one pass mark)  |
      | Pass mark               | 0                             |
    And I press "Save and display"
    Then I should see "The pass mark must be at least 1 correct answer."
