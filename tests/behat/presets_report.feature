@local @local_bandedgrade
Feature: Site presets, the "Number correct" report and the unreachable band warning
  In order to set up banded grading quickly and check the results
  As an administrator and a teacher
  I need site-wide sets of bands, a report of the number correct, and a warning when a band cannot be reached

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

  # Real browser: without JavaScript Behat drops the hidden "0" that Moodle posts for an unticked admin checkbox.
  @javascript
  Scenario: An administrator's sets of bands are checked and offered to teachers
    Given I log in as "admin"
    And I navigate to "Plugins > Local plugins > Grade by number correct" in site administration
    When I set the field "Sets of bands" to "Weekly check | 1=1, 3=2"
    And I press "Save changes"
    Then I should see "Line 1: The first band must start at 0 correct."
    And I set the field "Sets of bands" to "Weekly check | 0=0, 2=1, 4=2"
    And I set the field "Offer the ready-made bands" to "0"
    And I press "Save changes"
    And I should see "Changes saved"
    And I log out
    And I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I set the field "Grade by number correct" to "1"
    And the "Bands" select box should contain "Weekly check"
    And the "Bands" select box should not contain "Pass or fail: 6 or more correct passes (score 1)"
    And I set the field "Bands" to "Weekly check"
    And I should see "2 to 3 correct → score 1" in the "#local_bandedgrade_preview" "css_element"
    And I press "Save and display"
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And the field "Bands" matches value "Weekly check"
    And I should see "2 to 3 correct → score 1"
    And I should see "4 correct → score 2"

  Scenario: The teacher sees the number correct next to each student's score
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
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
    When I am on the "Quiz 1" "quiz activity" page
    And I navigate to "Number correct" in current page administration
    Then I should see "Number correct in \"Quiz 1\""
    And I should see "This quiz has 4 questions that can be marked right or wrong. Grading method: Highest grade."
    And the following should exist in the "local_bandedgrade_report" table:
      | Student     | Correct in each attempt | Number correct used for the score | Score in the gradebook |
      | Student One | 3                       | 3                                 | 1                      |

  Scenario: The teacher is warned when a band can never be reached
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    And I set the following fields to these values:
      | Grade by number correct         | 1                              |
      | Bands                           | My own bands (type them below) |
      | Band 1: from this many correct  | 0                              |
      | Band 1: score                   | 0                              |
      | Band 2: from this many correct  | 2                              |
      | Band 2: score                   | 1                              |
      | Band 3: from this many correct  | 5                              |
      | Band 3: score                   | 2                              |
    When I press "Save and display"
    Then I should see "this quiz now has 4 questions that can be marked right or wrong, so no student can reach the band that starts at 5 correct."
    And I click on "Change the bands in the quiz settings" "link"
    And I should see "Grade by number correct"
    And I am on the "Quiz 1" "quiz activity" page logged in as "student1"
    And I should not see "no student can reach the band"
