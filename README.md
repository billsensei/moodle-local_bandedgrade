# Grade by number correct (local_bandedgrade)

Give each student a simple score based on **how many questions they got fully right** in a quiz.
For example, with 10 questions: 0 correct → 0, 1–4 correct → 1, 5–8 correct → 2, 9 or 10 correct → 3.

The score appears in the gradebook as its own column, next to the quiz. You set it up in the quiz settings. There are no formulas to write.

---

## For teachers

### 1. Turn it on

1. Open your quiz and click **Settings**.
2. Scroll down to the section **Grade by number correct** and click it to open it.
3. Tick **Grade by number correct**.

### 2. Choose your bands

A *band* says: "from this many correct answers, give this score". A band lasts until the next band starts.

- **Use a ready-made set:** in the **Bands** list, pick one, for example
  *Scores 0 to 3: 0 correct → 0, 1–4 → 1, 5–8 → 2, 9 or more → 3*. The boxes below fill in by themselves.
  Your Moodle administrator may have added your school's own sets to this list.
- **Or type your own:** pick **My own bands (type them below)**. Then, for each band, type the number correct it starts at and the score.
  The first band must start at **0**. You can leave the extra rows empty.

- **Bands by percentage:** in **Bands are based on**, choose *The percentage of questions answered correctly*
  (or pick a ready-made set that says "by percentage"). Each band then starts at a percentage, for example
  *50%*, instead of a number of questions. Use this when quizzes of different lengths should share the same
  bands. The percentage is worked out from the questions the quiz has **now**, so adding or removing a question
  changes it for everyone (the same as the quiz's own grade). A student with 2 of 3 correct has 66.67%, so a band
  starting at 66.67 includes them. With the *average* grading method the average percentage is used.

- **Pass or fail:** if you only need a pass or a fail, set **How to give scores** to *Pass or fail (one pass mark)*.
  Type the **pass mark** (a number of correct questions, or a percentage if you change **The pass mark is**) and the
  score for a pass (1 unless you change it) and for a fail (0). Students at or above the pass mark pass; the others
  fail. The pass score is also set as the **grade to pass** of the gradebook column, so the gradebook shows pass and
  fail and the activity can require a passing grade. Going back to *A list of bands* takes that grade to pass back.

- **Questions that must be correct:** under the bands, choose the questions every student has to get fully right.
  A student who misses any of them gets the **lowest score** (the first band, or the fail score), however many other
  questions are right. The **Number correct** page still shows the real number, marked "missed a required question".
  With several attempts, each attempt is checked first and then the quiz's grading method picks the score, so a later
  attempt that gets every required question right can make up for an earlier one. Leave the box empty to require none.
  Only questions with a mark above 0 can be chosen. A question that is deleted from the quiz stops being required.

Under the bands, **What students will get** shows the result as you type, for example:

> 0 correct → score 0
> 1 to 4 correct → score 1
> 5 to 8 correct → score 2
> 9 to 10 correct → score 3

If a band can never be reached (for example, it starts at 12 but the quiz has 10 questions), the preview tells you.

**Count only this score in the course total** is ticked at first. The quiz's usual grade still shows in the gradebook, but only the new score counts towards the course total. If your gradebook adds grades up in a way that does not allow this, a message tells you so. Your gradebook is not changed in that case.

Click **Save and return to course**.

### 3. What students see

Nothing changes in the quiz itself. After a student finishes the quiz, their score appears in **Grades**, in the column
**"*quiz name* – score"**.

### 4. Where you find the scores

**Grades → Grader report**: the column **"*quiz name* – score"**, just after the quiz.

To see **how many questions** each student got right, open the quiz and click **More → Number correct**
(or use the link in the quiz settings). For each student, the page shows:

- the number correct in each attempt (*waiting for marking* while an essay is not marked yet),
- the number correct used for the score, which follows the quiz's grading method,
- the score in the gradebook, marked **Changed by hand** if you changed it.

In a quiz with separate groups, you see only the students in your groups, as in the quiz's own reports.

### If you remove questions

If the quiz has fewer questions than a band needs, for example a band starts at 9 correct but the quiz now has 8 questions,
no student can reach that band. The quiz page and the **Questions** page then show a warning with a link to the settings.
Change the bands, or add questions. Only teachers who can edit the quiz see the warning.

### What counts as "correct"

- A question counts only when it is **fully right**. Part marks do not count, whatever the question is worth.
- A question with several parts (for example, "Embedded answers") counts only if **every part** is right.
- A question answered right **on a later try** (in "Interactive with multiple tries") **does count**, even though the quiz takes marks off.
- **Essay questions** wait until you mark them. Until then the score stays **blank**. When you give full marks, the essay counts as correct.
  *Careful:* if you re-mark, by hand, a question that was right on a later try and keep the lower mark, it counts as "partly right" and stops counting.
- Description items and questions with a mark of 0 are not counted.

### Several attempts

The score follows the quiz's **Grading method** setting:

| Grading method | Which attempt counts |
|---|---|
| Highest grade | The attempt with the most correct answers |
| Average grade | The average number correct. If the average falls between two bands, the **lower** band is used (4.5 correct with a band starting at 5 → the band below). |
| First attempt | The first attempt |
| Last attempt | The last attempt |

### Changing a score by hand

You can change the score in the gradebook as usual. **Your change is kept**: the plugin never replaces a score you changed,
even after another attempt or a change of bands. Locked grades are never changed either.

### The Recalculate page

Open the quiz, click **More → Recalculate scores**, or use the **Recalculate scores now** link in the quiz settings.

- **Recalculate, but keep the scores I changed by hand**: works out every score again, but leaves your own changes alone.
- **Recalculate everything, and replace the scores I changed by hand**: the page lists the students affected and asks you to confirm.
  This cannot be undone.

Scores update a minute or two later.

You do not normally need this page. Scores update by themselves when a student finishes, when you mark an essay, regrade, delete an attempt,
change the bands, or add or remove questions.

### Backups, course copies and duplicated quizzes

The settings are saved with the quiz in backups.

- **Restoring a whole course** (or copying it): the bands and the score column come back. Scores, and changes you made by hand,
  are kept when the backup includes student data.
- **Duplicating a quiz**, or importing or restoring just one quiz: the copy keeps the bands and gets its **own new** score column.
  It fills in as students take the copy.

- **Restoring a course into a course that already has its own gradebook categories:** Moodle does not restore the gradebook
  in that case. The quiz keeps its bands and gets a new score column, filled in from the students' quiz answers.
  Scores you had changed by hand are **not** carried over, so check them afterwards.

Scores are checked again in the background shortly after a restore.

### Turning it off

Untick **Grade by number correct** and save. The score column and its scores stay in the gradebook (delete the column there if you
no longer want it), and the quiz's own grade counts again.

---

## For administrators

### Install

- **ZIP upload:** *Site administration → Plugins → Install plugins*, upload `local_bandedgrade_0.6.0.zip`, and follow the steps.
- **Manual copy:** unzip into `local/bandedgrade` under your Moodle folder (on Moodle 5.1 or later, `public/local/bandedgrade`), then run
  `php admin/cli/upgrade.php`. The output should end with
  `Command line upgrade from ... completed successfully.`

Moodle's cron must be running. The Recalculate page and changes to bands or questions use a background task.

### Your school's own bands

*Site administration → Plugins → Local plugins → Grade by number correct.*

- **Sets of bands:** one set per line: a name, a `|` sign, then the bands, each written as *from this many correct = score*:

  ```
  Scores 0 to 3 | 0=0, 1=1, 5=2, 9=3
  Pass or fail (6 of 10) | 0=0, 6=1
  ```

  The same rules apply as in the quiz settings: the first band starts at 0, at most 10 bands, and at least one score above 0.
  At most 20 sets. If a line is wrong, the page says which line and why, and nothing is saved.
  To base a set on the percentage correct, write `%` after **every** band start: `Scores by percentage | 0%=0, 10%=1, 50%=2, 90%=3`.
  Percentages go from 0 to 100, with up to 2 decimals.
- **Offer the ready-made bands:** untick it to offer only your own sets.

Each quiz keeps a copy of its bands. Changing or removing a set here does not change quizzes that already use it.

### Capability

`local/bandedgrade:recalculate` lets someone use the Recalculate page. By default, editing teachers and managers have it.
It is marked as a data-loss risk because "replace" overwrites scores changed by hand.
The **Number correct** page uses the quiz's own `mod/quiz:viewreports` capability (teachers, non-editing teachers and managers).
Turning the feature on and setting the bands needs both the right to edit the quiz settings and
`moodle/grade:manage` (set up the gradebook) in the course.

### Uninstall

*Site administration → Plugins → Plugins overview*, find **Grade by number correct**, click **Uninstall**. Uninstalling deletes the score columns
and gives every quiz its own weight in the course total back.

### Privacy

The plugin stores, per quiz attempt, the number of fully correct questions, and per student the last score it wrote.
Both are covered by Moodle's privacy export and delete tools. The scores themselves are gradebook data.

### Status

Version 0.6.0 is **stable**: it has been tried on a Moodle site and passes the automated tests on every supported
Moodle version and database.

### Compatibility

Moodle 5.0, 5.1 and 5.2, PHP 8.2 or later (5.2 needs PHP 8.3). Automated tests run on all three with MariaDB and PostgreSQL.

### Changelog

- **0.6.0** (2026-10-09): choose **questions that must be correct**: a student who misses one gets the lowest score.
  Works with bands, percentages and pass/fail. Needs a database upgrade (one new setting per quiz and one per counted
  attempt); quizzes that already use the plugin are not changed.
- **0.5.1** (2026-10-09): the plugin is now marked stable. No change to how it works.
- **0.5.0** (2026-10-09): new **Pass or fail (one pass mark)** way to give scores: type the pass mark and the two
  scores. It sets the gradebook column's grade to pass. Needs a database upgrade (one new setting per quiz); quizzes
  that already use bands are not changed.
- **0.4.1** (2026-10-08): now supports Moodle 5.1 and 5.2 (tested with automated checks on 5.0, 5.1 and 5.2). No change
  to how the plugin works.
- **0.4.0** (2026-10-08): bands can start at a percentage of correct questions instead of a number. Two new ready-made
  sets by percentage. Administrators write a percentage set by putting % after each band start
  (`Name | 0%=0, 50%=1`). The Number correct page also shows the percentage.
- **0.3.0** (2026-10-08): administrators can add their own sets of bands, and turn off the ready-made ones. New
  **Number correct** page for each quiz. A warning on the quiz page when the quiz has too few questions to reach a band.
- **0.2.1** (2026-10-08): hardening. The score column is hidden whenever the quiz grade is hidden (quiz hidden, or marks
  not yet reviewable). Changing the settings needs gradebook rights. The Recalculate page lists only students the teacher
  can see, and "replace" changes only the students it listed. Limits on scores and band numbers. A score cleared by hand
  is kept. Two rescores of one quiz no longer run at the same time. Course reset: resetting attempts blanks the scores,
  resetting gradebook items makes a new column right away with the weight applied again. Uninstall removes the columns
  and restores the quiz weights.
- **0.2.0** (2026-10-08): settings are included in backups, course copies, imports and duplicated quizzes.
  Fix: two quizzes with the same name in one course shared one score column; each now gets its own.
- **0.1.0** (2026-10-08): first release. Bands set in quiz settings with presets and a live preview; scores follow the quiz's
  grading method; changes made by hand are kept; Recalculate page; privacy support.
# moodle-local_bandedgrade
