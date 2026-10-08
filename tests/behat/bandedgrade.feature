@local @local_bandedgrade
Feature: Grade a quiz by the number of fully correct questions
  In order to give a simple score such as 0 to 3
  As a teacher
  I need to set bands of "number correct" in the quiz settings and see the score in the gradebook

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
      | question | page | maxmark |
      | TF1      | 1    | 1       |
      | TF2      | 1    | 3       |
      | TF3      | 1    | 1       |
      | TF4      | 1    | 1       |
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
    And I press "Save and return to course"

  Scenario: The student's score comes from the number of fully correct questions
    When user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
      | 3    | True     |
      | 4    | True     |
    And I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    # Columns: name, email, Quiz 1, Quiz 1 – score, course total. The quiz's own grade (50 of 100) has weight 0.
    Then the following should exist in the "user-grades" table:
      | -1-         | -3-   | -4-  | -5-  |
      | Student One | 50.00 | 1.00 | 1.00 |
    And I am on the "Quiz 1" "quiz activity editing" page
    And I should see "0 to 1 correct → score 0"
    And I should see "2 to 3 correct → score 1"
    And I should see "4 correct → score 2"
    And I should see "This quiz has 4 questions that can be marked right or wrong."

  Scenario: Bands with a gap at the start are refused in plain language
    When I am on the "Quiz 1" "quiz activity editing" page
    And I set the field "Band 1: from this many correct" to "1"
    And I press "Save and display"
    Then I should see "The first band must start at 0 correct."

  Scenario: A score changed by hand is kept or replaced on the Recalculate page
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | True     |
      | 3    | True     |
      | 4    | True     |
    And I am on the "Course 1" "grades > Grader report > View" page
    And I turn editing mode on
    And I give the grade "0.50" to the user "Student One" for the grade item "Quiz 1 – score"
    And I press "Save changes"
    And I turn editing mode off
    # Keep.
    When I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Recalculate scores" in current page administration
    Then I should see "Student One"
    And I press "Recalculate"
    And I should see "The scores will update in a minute or two."
    And I run all adhoc tasks
    And I am on the "Course 1" "grades > Grader report > View" page
    And the following should exist in the "user-grades" table:
      | -1-         | -3-   | -4-  |
      | Student One | 100.00 | 0.50 |
    # Overwrite, after a confirmation.
    And I am on the "Quiz 1" "local_bandedgrade > Recalculate" page
    And I set the field "Recalculate everything, and replace the scores I changed by hand (number of students: 1)" to "1"
    And I press "Recalculate"
    And I should see "This replaces the scores you changed by hand for these students (1): Student One."
    And I press "Continue"
    And I run all adhoc tasks
    And I am on the "Course 1" "grades > Grader report > View" page
    And the following should exist in the "user-grades" table:
      | -1-         | -3-   | -4-  |
      | Student One | 100.00 | 2.00 |

  Scenario: Students cannot use the Recalculate page
    When I am on the "Quiz 1" "quiz activity" page logged in as "student1"
    Then I should not see "Recalculate scores"
    And I should be refused the recalculate page of "Quiz 1"

  @javascript
  Scenario: The preview follows what the teacher types and the presets fill in the bands
    When I am on the "Quiz 1" "quiz activity editing" page
    And I expand all fieldsets
    And I set the field "Band 3: from this many correct" to "3"
    Then I should see "2 correct → score 1" in the "#local_bandedgrade_preview" "css_element"
    And I should see "3 to 4 correct → score 2" in the "#local_bandedgrade_preview" "css_element"
    And I set the field "Bands" to "Pass or fail: 6 or more correct passes (score 1)"
    And the field "Band 2: from this many correct" matches value "6"
    And the field "Band 3: from this many correct" matches value ""
    And I should see "The band starting at 6 can never be reached: this quiz has only 4 questions." in the "#local_bandedgrade_preview" "css_element"

  Scenario: Bands can be based on the percentage of correct questions
    Given I am on the "Quiz 1" "quiz activity editing" page
    And I set the following fields to these values:
      | Bands are based on             | The percentage of questions answered correctly |
      | Band 1: from this many correct | 0                                              |
      | Band 1: score                  | 0                                              |
      | Band 2: from this many correct | 50                                             |
      | Band 2: score                  | 1                                              |
      | Band 3: from this many correct | 75                                             |
      | Band 3: score                  | 2                                              |
    And I press "Save and return to course"
    When user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
      | 3    | True     |
      | 4    | True     |
    And I run all adhoc tasks
    And I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    # 3 of 4 questions are fully right: 75%, which meets the band starting at 75 (with number bands 3 correct gave 1).
    Then the following should exist in the "user-grades" table:
      | -1-         | -3-   | -4-  |
      | Student One | 50.00 | 2.00 |
    And I am on the "Quiz 1" "quiz activity editing" page
    And I should see "75% or more correct (3 of 4 questions or more) → score 2"
    And I should see "50% or more correct (2 of 4 questions or more) → score 1"

  Scenario: A percentage above 100 is refused in plain language
    When I am on the "Quiz 1" "quiz activity editing" page
    And I set the following fields to these values:
      | Bands are based on             | The percentage of questions answered correctly |
      | Band 3: from this many correct | 101                                            |
    And I press "Save and display"
    Then I should see "Type a percentage from 0 to 100"

  @javascript
  Scenario: A percentage set fills in the bands, switches the type and shows what the percentages mean
    When I am on the "Quiz 1" "quiz activity editing" page
    And I expand all fieldsets
    And I set the field "Bands" to "Scores 0 to 3 by percentage: under 10% → 0, 10% → 1, 50% → 2, 90% or more → 3"
    Then the field "Bands are based on" matches value "The percentage of questions answered correctly"
    And the field "Band 3: from this many correct" matches value "50"
    And I should see "50% or more correct (2 of 4 questions or more) → score 2" in the "#local_bandedgrade_preview" "css_element"
    And I should see "90% or more correct (4 of 4 questions or more) → score 3" in the "#local_bandedgrade_preview" "css_element"
    And I should see "% or more correct → score" in the "#fgroup_id_bandedgrade_row0" "css_element"
