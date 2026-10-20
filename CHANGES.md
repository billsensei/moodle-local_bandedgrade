# Changes in the latest version

What changed or was added since the previous version of `local_bandedgrade`. The full history is in [changelog.md](changelog.md).

## 0.10.0, 2026-10-20

- New. The teacher can choose the colour of each band. In the quiz settings, tick **Choose the report colours myself** and pick a colour next to each band (or a colour for a pass and for a fail in the pass/fail scheme). A student's grade is then coloured with the colour of the band it falls in, on the Number correct page, in the grader report and in the quiz's Grades and Responses reports. The text colour (dark or white) is chosen so it stays readable, and the Number correct page lists the band colours instead of the percentage colours. Quizzes that do not tick the box keep the percentage colours as before. The colours are stored with the bands, so there is no database change and backup and restore keep them. The colour boxes start from a red-to-green suggestion.
