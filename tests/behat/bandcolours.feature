@local @local_bandedgrade @javascript
Feature: Colours chosen for each band
  In order to see at a glance which band each student reached
  As a teacher
  I need to pick the colour of each band and see it in the reports

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

  Scenario: Colours chosen for the bands are used in the reports
    Given I am on the "Quiz 1" "quiz activity editing" page
    And I expand all fieldsets
    And I set the field "Choose the report colours myself" to "1"
    And I set the field "Band 1: colour" to "#ff0000"
    And I set the field "Band 2: colour" to "#ffff00"
    And I set the field "Band 3: colour" to "#0b3d91"
    And I press "Save and display"
    When I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Number correct" in current page administration
    Then "span.local_bandedgrade-custom[style*='ffff00']" "css_element" should exist in the "Student One" "table_row"
    And "span.local_bandedgrade-custom[style*='0b3d91']" "css_element" should exist in the "Student Two" "table_row"
    And ".local_bandedgrade-lightblue" "css_element" should not exist
    And I should see "From 4 correct"
    And I am on the "Course 1" "grades > Grader report > View" page
    And "td.local_bandedgrade-custom[style*='rgb(255, 255, 0)']" "css_element" should exist in the "Student One" "table_row"
    And "td.local_bandedgrade-custom[style*='rgb(11, 61, 145)']" "css_element" should exist in the "Student Two" "table_row"
    And I am on the "Quiz 1" "mod_quiz > Grades report" page
    And ".local_bandedgrade-custom[style*='rgb(11, 61, 145)']" "css_element" should exist in the "Student Two" "table_row"

  Scenario: The colours stay saved and can be turned off again
    Given I am on the "Quiz 1" "quiz activity editing" page
    And I expand all fieldsets
    And I set the field "Choose the report colours myself" to "1"
    And I set the field "Band 3: colour" to "#0b3d91"
    And I press "Save and display"
    And I am on the "Quiz 1" "quiz activity editing" page
    And I expand all fieldsets
    And the field "Choose the report colours myself" matches value "1"
    And the field "Band 3: colour" matches value "#0b3d91"
    And I set the field "Choose the report colours myself" to ""
    And I press "Save and display"
    When I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Number correct" in current page administration
    Then ".local_bandedgrade-lightblue" "css_element" should exist in the "Student One" "table_row"
    And ".local_bandedgrade-custom" "css_element" should not exist

  Scenario: Colours work with a ready-made preset and with pass or fail
    Given I am on the "Quiz 1" "quiz activity editing" page
    And I expand all fieldsets
    And I set the field "Choose the report colours myself" to "1"
    And I set the following fields to these values:
      | How to give scores  | Pass or fail (one pass mark) |
      | Pass mark           | 3            |
      | Colour for a fail   | #ff0000      |
      | Colour for a pass   | #00ff00      |
    And I press "Save and display"
    When I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Number correct" in current page administration
    Then "span.local_bandedgrade-custom[style*='00ff00']" "css_element" should exist in the "Student One" "table_row"
    And "span.local_bandedgrade-custom[style*='00ff00']" "css_element" should exist in the "Student Two" "table_row"
