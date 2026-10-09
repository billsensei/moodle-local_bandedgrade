@local @local_bandedgrade
Feature: Give scores as the words of a gradebook scale
  In order to show results as words such as Fail, Pass or Merit
  As a teacher
  I need to pick a scale in the quiz settings and give each band a word of it

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
    And the following "scales" exist:
      | name   | scale                        | course |
      | Result | Fail, Pass, Merit, Excellent | C1     |
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
  Scenario: Bands give words of the scale, with a live preview
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | Grade by number correct         | 1                                            |
      | Scores are                      | Result (Fail, Pass, Merit, Excellent)        |
      | Bands                           | My own bands (type them below)               |
      | Band 1: from this many correct  | 0                                            |
      | Band 1: score                   | Fail                                         |
      | Band 2: from this many correct  | 2                                            |
      | Band 2: score                   | pass                                         |
      | Band 3: from this many correct  | 4                                            |
      | Band 3: score                   | Excellent                                    |
    Then I should see "0 to 1 correct → score Fail"
    And I should see "2 to 3 correct → score Pass"
    And I should see "4 correct → score Excellent"
    And I press "Save and return to course"
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | True     |
      | 3    | True     |
      | 4    | False    |
    And I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Number correct" in current page administration
    And I should see "Pass" in the "Student One" "table_row"

  Scenario: A word that is not in the scale is refused in plain language
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | Grade by number correct         | 1                              |
      | Scores are                      | Result (Fail, Pass, Merit, Excellent) |
      | Bands                           | My own bands (type them below) |
      | Band 1: from this many correct  | 0                              |
      | Band 1: score                   | Fail                           |
      | Band 2: from this many correct  | 2                              |
      | Band 2: score                   | Great                          |
    And I press "Save and display"
    Then I should see "Type one of the words of the scale (Fail, Pass, Merit, Excellent), or its number (1 to 4)."
