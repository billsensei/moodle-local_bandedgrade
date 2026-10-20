# Changelog

All notable changes to `local_bandedgrade`, newest first. Add an entry here with every new version. `CHANGES.md` holds only the latest version.
See also the [releases on GitHub](https://github.com/billsensei/moodle-local_bandedgrade/releases).

## 0.10.0, 2026-10-20

- New. The teacher can choose the colour of each band. In the quiz settings, tick **Choose the report colours myself** and pick a colour next to each band (or a colour for a pass and for a fail in the pass/fail scheme). A student's grade is then coloured with the colour of the band it falls in, on the Number correct page, in the grader report and in the quiz's Grades and Responses reports. The text colour (dark or white) is chosen so it stays readable, and the Number correct page lists the band colours instead of the percentage colours. Quizzes that do not tick the box keep the percentage colours as before. The colours are stored with the bands, so there is no database change and backup and restore keep them. The colour boxes start from a red-to-green suggestion.

## 0.9.5, 2026-10-19

- Added `composer.json` (package `billsensei/moodle-local_bandedgrade`, type `moodle-local`) so the plugin can be installed with Composer, and a README note on it, including the `public/` path needed on Moodle 5.1 and later. No code changes.

## 0.9.4, 2026-10-17

- Fix. Restoring a backup file that was edited by hand and lacks some of the plugin's settings (bands, enabled, weight, score column) no longer gives PHP warnings. Without bands the quiz is restored with banded grading off. No database change. New files: `CHANGES.md` lists what changed in the latest version and `changelog.md` lists what changed in every version.

## 0.9.3, 2026-10-17

- Fix. When the quiz's score column is hidden in the gradebook, the **Number correct** report leaves out the "Score in the gradebook" column for teachers who may not see hidden grades (as the colours in the grader report already did).

## 0.9.2, 2026-10-17

- Now supports Moodle 5.3 (tested with automated checks on 5.0, 5.1, 5.2 and 5.3). No change to how the plugin works.

## 0.9.1, 2026-10-17

- Fix. The light blue band (70% to 79.9%) is a little darker, so it still shows on the pale blue rows of the quiz's **Results** reports. Purge caches after upgrading so the new stylesheet loads.

## 0.9.0, 2026-10-17

- New. The grade of each attempt is colour coded in the quiz's own **Results** reports (**Grades** and **Responses**), by the percentage of questions that attempt got fully right, with the same colours as the grader report. Hover over a cell to see the percentage. Only people who may view the quiz reports see the colours, and in a quiz with separate groups only the students in their groups. No database change; purge caches after upgrading so the new stylesheet loads.

## 0.8.3, 2026-10-17

- Fix. The colours in the **grader report** now show. The Boost theme paints every cell of that report itself and that rule was stronger than the plugin's colour, so the cells stayed white. The Number correct page was not affected. No database change; purge caches after upgrading so the new stylesheet loads.

## 0.8.2, 2026-10-16

- Security fix. In a course set to separate groups, the grader-report colours no longer include students from groups the teacher cannot access, even when the quiz itself has no groups. Upgrade from 0.8.0 and 0.8.1 is recommended. No database change.

## 0.8.1, 2026-10-16

- The colours in the grader report are shown only to people who may view the quiz's reports (`mod/quiz:viewreports`). Recalculating a very large quiz now works through the students in groups of 200, so it uses less memory. No database change.

## 0.8.0, 2026-10-15

- Scores are **colour coded** by the percentage of questions answered correctly (red below 60%, yellow 60%, light blue 70%, blue 80%, green 90% and up), on the Number correct page and in the grader report. No database change; purge caches after upgrading so the new stylesheet loads.

## 0.7.1, 2026-10-09

- Adds the `LICENSE` file (GNU GPL v3). No change to how the plugin works.

## 0.7.0, 2026-10-09

- Give scores as the words of a **gradebook scale** (for example Fail / Pass / Merit) instead of numbers. Works with bands and pass/fail. Needs a database upgrade (one new setting per quiz); quizzes that already use the plugin are not changed.

## 0.6.0, 2026-10-09

- Choose **questions that must be correct**: a student who misses one gets the lowest score. Works with bands, percentages and pass/fail. Needs a database upgrade (one new setting per quiz and one per counted attempt); quizzes that already use the plugin are not changed.

## 0.5.1, 2026-10-09

- The plugin is now marked stable. No change to how it works.

## 0.5.0, 2026-10-09

- New **Pass or fail (one pass mark)** way to give scores: type the pass mark and the two scores. It sets the gradebook column's grade to pass. Needs a database upgrade (one new setting per quiz); quizzes that already use bands are not changed.

## 0.4.1, 2026-10-08

- Now supports Moodle 5.1 and 5.2 (tested with automated checks on 5.0, 5.1 and 5.2). No change to how the plugin works.

## 0.4.0, 2026-10-08

- Bands can start at a percentage of correct questions instead of a number. Two new ready-made sets by percentage. Administrators write a percentage set by putting % after each band start (`Name | 0%=0, 50%=1`). The Number correct page also shows the percentage.

## 0.3.0, 2026-10-08

- Administrators can add their own sets of bands, and turn off the ready-made ones. New **Number correct** page for each quiz. A warning on the quiz page when the quiz has too few questions to reach a band.

## 0.2.1, 2026-10-08

- Hardening. The score column is hidden whenever the quiz grade is hidden (quiz hidden, or marks not yet reviewable). Changing the settings needs gradebook rights. The Recalculate page lists only students the teacher can see, and "replace" changes only the students it listed. Limits on scores and band numbers. A score cleared by hand is kept. Two rescores of one quiz no longer run at the same time. Course reset: resetting attempts blanks the scores, resetting gradebook items makes a new column right away with the weight applied again. Uninstall removes the columns and restores the quiz weights.

## 0.2.0, 2026-10-08

- Settings are included in backups, course copies, imports and duplicated quizzes. Fix: two quizzes with the same name in one course shared one score column; each now gets its own.

## 0.1.0, 2026-10-08

- First release. Bands set in quiz settings with presets and a live preview; scores follow the quiz's grading method; changes made by hand are kept; Recalculate page; privacy support.
# moodle-local_bandedgrade
