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
- **Or type your own:** pick **My own bands (type them below)**. Then, for each band, type the number correct it starts at and the score.
  The first band must start at **0**. You can leave the extra rows empty.

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

- **ZIP upload:** *Site administration → Plugins → Install plugins*, upload `local_bandedgrade_0.2.1.zip`, and follow the steps.
- **Manual copy:** unzip into `local/bandedgrade` under your Moodle folder (on Moodle 5.1 or later, `public/local/bandedgrade`), then run
  `php admin/cli/upgrade.php`. The output should end with
  `Command line upgrade from ... completed successfully.`

Moodle's cron must be running. The Recalculate page and changes to bands or questions use a background task.

### Capability

`local/bandedgrade:recalculate` lets someone use the Recalculate page. By default, editing teachers and managers have it.
It is marked as a data-loss risk because "replace" overwrites scores changed by hand.
Turning the feature on and setting the bands needs both the right to edit the quiz settings and
`moodle/grade:manage` (set up the gradebook) in the course.

### Uninstall

*Site administration → Plugins → Plugins overview*, find **Grade by number correct**, click **Uninstall**. Uninstalling deletes the score columns
and gives every quiz its own weight in the course total back.

### Privacy

The plugin stores, per quiz attempt, the number of fully correct questions, and per student the last score it wrote.
Both are covered by Moodle's privacy export and delete tools. The scores themselves are gradebook data.

### Compatibility

Moodle 5.0 (tested on 5.0.10+), PHP 8.2 or later.

### Changelog

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
