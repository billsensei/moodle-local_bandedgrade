<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for local_bandedgrade.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['arrow'] = 'or more correct → score';
$string['bandedgrade:recalculate'] = 'Recalculate "Grade by number correct" scores';
$string['bandrow'] = 'Band {$a}';
$string['builtinpresets'] = 'Offer the ready-made bands';
$string['builtinpresets_desc'] = 'When this is ticked, teachers can also pick the two ready-made sets of bands that come with the plugin ("Scores 0 to 3" and "Pass or fail"). Untick it to offer only the bands above.';
$string['enabled'] = 'Grade by number correct';
$string['enabled_help'] = 'Turn this on to give each student a score based on how many questions they got fully right.

Questions with part marks do not count as right. A question answered right on a later try does count.

The score appears in the gradebook as a separate column named after the quiz.';
$string['error_duplicate'] = 'Another band already starts at {$a}. Each band needs a different number.';
$string['error_from'] = 'Type a whole number of correct answers, like 0, 1 or 5.';
$string['error_frommax'] = 'Use a number of correct answers up to {$a}.';
$string['error_nozero'] = 'The first band must start at 0 correct. Right now it starts at {$a}, so students with fewer correct answers would get no score.';
$string['error_presetcount'] = 'There can be at most {$a} sets of bands. Remove a line.';
$string['error_presetformat'] = 'Write the name, then a | sign, then the bands. For example: Scores 0 to 3 | 0=0, 1=1, 5=2, 9=3';
$string['error_presetline'] = 'Line {$a->line}: {$a->error}';
$string['error_presetname'] = 'Use a name of at most {$a} characters.';
$string['error_presetpair'] = '"{$a}" is not a band. Write each band as "from this many correct = score", like 5=2. Use a dot in scores, like 2.5.';
$string['error_presetrows'] = 'Use at most {$a} bands in one set.';
$string['error_score'] = 'Type a score of 0 or more, like 0, 1 or 2.5 (at most 5 decimal places).';
$string['error_scoremax'] = 'Use a score up to {$a}.';
$string['error_toofew'] = 'Fill in at least two bands, for example "0 → 0" and "5 → 1".';
$string['error_topzero'] = 'At least one band needs a score above 0.';
$string['eventreportviewed'] = 'Number-correct report viewed';
$string['eventreportviewed_desc'] = 'The user with id \'{$a->userid}\' viewed the number-correct report of the quiz with course module id \'{$a->cmid}\'.';
$string['eventscoresrecalculated'] = 'Number-correct scores recalculated';
$string['eventscoresrecalculated_keep'] = 'The user with id \'{$a->userid}\' asked to recalculate the number-correct scores of the quiz with course module id \'{$a->cmid}\', keeping scores changed by hand.';
$string['eventscoresrecalculated_overwrite'] = 'The user with id \'{$a->userid}\' asked to recalculate the number-correct scores of the quiz with course module id \'{$a->cmid}\', replacing scores changed by hand.';
$string['formheader'] = 'Grade by number correct';
$string['fromlabel'] = 'Band {$a}: from this many correct';
$string['iteminfo'] = 'Score based on the number of fully correct questions. Filled in automatically by "Grade by number correct".';
$string['itemname'] = '{$a} – score';
$string['locktimeout'] = 'Another recalculation of this quiz is still running. It will be tried again shortly.';
$string['modekeep'] = 'Recalculate, but keep the scores I changed by hand in the gradebook';
$string['modeoverwrite'] = 'Recalculate everything, and replace the scores I changed by hand (number of students: {$a})';
$string['nochanges'] = 'No scores have been changed by hand.';
$string['nogrademanage'] = 'Only teachers who can set up the gradebook can change this.';
$string['overwriteconfirm'] = 'This replaces the scores you changed by hand for these students ({$a->count}): {$a->names}. Their scores will come from their quiz answers again. You cannot undo this. Continue?';
$string['pluginname'] = 'Grade by number correct';
$string['preset'] = 'Bands';
$string['preset_custom'] = 'My own bands (type them below)';
$string['preset_help'] = 'Pick a ready-made set of bands, or choose "My own bands" and type them in the boxes below.

Each band says: "from this many correct answers, give this score". A band lasts until the next band starts. The first band must start at 0.';
$string['preset_passfail10'] = 'Pass or fail: 6 or more correct passes (score 1)';
$string['preset_zerotothree10'] = 'Scores 0 to 3: 0 correct → 0, 1–4 → 1, 5–8 → 2, 9 or more → 3';
$string['presetsheading'] = 'Bands teachers can pick';
$string['presetsheading_desc'] = 'Set up the bands your teachers use most. They appear in the <strong>Bands</strong> list in the quiz settings. A quiz keeps a copy of its bands, so changing a set here does not change quizzes that already use it.';
$string['preview'] = 'What students will get';
$string['previewempty'] = 'Fill in the bands to see what students will get.';
$string['previewopen'] = '{$a->from} or more correct → score {$a->score}';
$string['previewrange'] = '{$a->from} to {$a->to} correct → score {$a->score}';
$string['previewsingle'] = '{$a->from} correct → score {$a->score}';
$string['previewunreachable'] = 'The band starting at {$a->from} can never be reached: this quiz has only {$a->total} questions.';
$string['privacy:metadata:attempt'] = 'The number of fully correct questions in each quiz attempt.';
$string['privacy:metadata:attempt:attemptid'] = 'The quiz attempt.';
$string['privacy:metadata:attempt:correctcount'] = 'How many questions were fully correct.';
$string['privacy:metadata:attempt:pending'] = 'Whether some questions were still waiting to be marked.';
$string['privacy:metadata:attempt:timemodified'] = 'When the count was made.';
$string['privacy:metadata:attempt:userid'] = 'The student.';
$string['privacy:metadata:core_grades'] = 'The score is stored in the gradebook.';
$string['privacy:metadata:written'] = 'The last score the plugin put in the gradebook, used to notice changes made by hand.';
$string['privacy:metadata:written:score'] = 'The score.';
$string['privacy:metadata:written:timemodified'] = 'When the score was written.';
$string['privacy:metadata:written:userid'] = 'The student.';
$string['questiontotal'] = 'This quiz has {$a} questions that can be marked right or wrong.';
$string['questiontotalnew'] = 'Add the questions after saving the quiz. The preview then shows the number of questions.';
$string['recalculate'] = 'Recalculate scores';
$string['recalculatebutton'] = 'Recalculate';
$string['recalculatechoose'] = 'What should happen to scores changed by hand?';
$string['recalculateheading'] = 'Recalculate scores for "{$a}"';
$string['recalculateintro'] = 'This works out every student\'s score again from their quiz answers, using the current bands.';
$string['recalculatelink'] = 'Recalculate scores now';
$string['recalculatenotenabled'] = '"Grade by number correct" is not turned on for this quiz. Turn it on in the quiz settings first.';
$string['recalculatequeued'] = 'Done. The scores will update in a minute or two.';
$string['report'] = 'Number correct';
$string['reportattempts'] = 'Correct in each attempt';
$string['reportbyhand'] = 'Changed by hand';
$string['reportempty'] = 'No student has finished this quiz yet.';
$string['reportheading'] = 'Number correct in "{$a}"';
$string['reportintro'] = 'This quiz has {$a->total} questions that can be marked right or wrong. Grading method: {$a->method}. The score comes from the number correct in the third column.';
$string['reportlink'] = 'See the number correct for each student';
$string['reportscore'] = 'Score in the gradebook';
$string['reportstudent'] = 'Student';
$string['reportused'] = 'Number correct used for the score';
$string['reportwaiting'] = 'waiting for marking';
$string['scorelabel'] = 'Band {$a}: score';
$string['sitepresets'] = 'Sets of bands';
$string['sitepresets_desc'] = 'One set per line: a name, a | sign, then the bands. Each band is "from this many correct = score", with commas between bands. The first band must start at 0. For example:<br><code>Scores 0 to 3 | 0=0, 1=1, 5=2, 9=3</code><br><code>Pass or fail (6 of 10) | 0=0, 6=1</code>';
$string['statusoff'] = 'Off.';
$string['statuson'] = 'On.';
$string['taskrescore'] = 'Recalculate "Grade by number correct" scores for a quiz';
$string['unreachablewarning'] = '<strong>Grade by number correct:</strong> this quiz now has {$a->total} questions that can be marked right or wrong, so no student can reach the band that starts at {$a->from} correct. <a href="{$a->url}">Change the bands in the quiz settings</a>, or add questions.';
$string['weightunsupported'] = 'Both the quiz grade and the "number correct" score count towards the course total. The way your gradebook adds up grades does not let this plugin change that. Ask your Moodle administrator for help if only the score should count.';
$string['zeroweight'] = 'Count only this score in the course total';
$string['zeroweight_help'] = 'When this is on, the quiz\'s usual grade still shows in the gradebook, but only the "number correct" score counts towards the course total.';
