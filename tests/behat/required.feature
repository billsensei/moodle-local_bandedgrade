@local @local_bandedgrade
Feature: Questions that must be correct
  In order to make sure students know the essential questions
  As a teacher
  I need to choose questions that must be correct, so that missing one gives the lowest score

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

  # The question box is an autocomplete, which Behat can only fill in a real browser.
  @javascript
  Scenario: A student who misses a required question gets the lowest score
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | Grade by number correct         | 1                              |
      | Bands                           | My own bands (type them below) |
      | Band 1: from this many correct  | 0                              |
      | Band 1: score                   | 0                              |
      | Band 2: from this many correct  | 2                              |
      | Band 2: score                   | 1                              |
      | Questions that must be correct  | 1. TF1                         |
    And I press "Save and return to course"
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | False    |
      | 2    | True     |
      | 3    | True     |
      | 4    | True     |
    And I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    Then the following should exist in the "user-grades" table:
      | -1-         | -4-  |
      | Student One | 0.00 |
    And I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Number correct" in current page administration
    And I should see "3 (missed a required question)"
    And I am on the "Quiz 1" "quiz activity editing" page
    And I expand all fieldsets
    # The chosen question is shown as a tag in the autocomplete box.
    And I should see "1. TF1" in the "#fitem_id_bandedgrade_required .form-autocomplete-selection" "css_element"
