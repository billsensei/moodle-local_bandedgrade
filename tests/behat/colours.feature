@local @local_bandedgrade @javascript
Feature: Colour codes for the percentage of questions answered correctly
  In order to see at a glance how well students did
  As a teacher
  I need the report and the grader report coloured by the percentage of questions correct

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
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
    And I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    And I set the following fields to these values:
      | Grade by number correct         | 1                              |
      | Bands                           | My own bands (type them below) |
      | Band 1: from this many correct  | 0                              |
      | Band 1: score                   | 0                              |
      | Band 2: from this many correct  | 2                              |
      | Band 2: score                   | 1                              |
      | Band 3: from this many correct  | 4                              |
      | Band 3: score                   | 2                              |
    And I press "Save and display"
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
      | 3    | True     |
      | 4    | True     |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | True     |
      | 3    | True     |
      | 4    | True     |

  Scenario: The number correct report shows the percentage in colour
    When I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Number correct" in current page administration
    Then ".local_bandedgrade-lightblue" "css_element" should exist in the "Student One" "table_row"
    And I should see "75%" in the "Student One" "table_row"
    And ".local_bandedgrade-green" "css_element" should exist in the "Student Two" "table_row"
    And I should see "100%" in the "Student Two" "table_row"
    And I should see "Red: 0% to 59.9%"
    And I should see "Green: 90% to 100%"

  Scenario: The grader report colours the banded score
    When I am on the "Course 1" "grades > Grader report > View" page
    Then ".local_bandedgrade-lightblue" "css_element" should exist in the "Student One" "table_row"
    And ".local_bandedgrade-green" "css_element" should exist in the "Student Two" "table_row"

  Scenario: The Grades report of the quiz colours the grade of each attempt
    When I am on the "Quiz 1" "mod_quiz > Grades report" page
    Then ".local_bandedgrade-lightblue" "css_element" should exist in the "Student One" "table_row"
    And ".local_bandedgrade-green" "css_element" should exist in the "Student Two" "table_row"

  Scenario: The Responses report of the quiz colours the grade of each attempt
    When I am on the "Quiz 1" "mod_quiz > Responses report" page
    Then ".local_bandedgrade-lightblue" "css_element" should exist in the "Student One" "table_row"
    And ".local_bandedgrade-green" "css_element" should exist in the "Student Two" "table_row"
