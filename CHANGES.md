# Changes in the latest version

What changed or was added since the previous version of `local_bandedgrade`. The full history is in [changelog.md](changelog.md).

## Unreleased

Marketplace summary: Release workflow now sends a one-line summary, because the Marketplace keeps only 128 characters of the notes.

- Release. The Moodle Marketplace keeps only the first 128 characters of the release notes, so v0.10.3 showed a cut-off sentence. `moodle-release.yml` now sends the one line of `CHANGES.md` that starts with `Marketplace summary: ` (at most 128 characters) and stops with an error if it is missing or too long. Add that line with every version.

## 0.10.3, 2026-11-03

- Fix. A student's score is no longer delayed when many students submit a quiz at once (the wait for the quiz lock was 5 seconds; now the background task takes over at once). Found by the MDL Shield review.
- Fix. The score column on the Number correct page is shown only to people who also have `moodle/grade:viewall`. Default roles are unaffected. Found by the MDL Shield review.
- Release. A new GitHub workflow, `moodle-release.yml`, sends each pushed `v*` tag to the Moodle plugins directory. It needs the repository secret `MARKETPLACE_TOKEN`.

## 0.10.2, 2026-10-31

- Fix. The "Changed by hand" badge on the Number correct page is now white on dark teal. It was dark text on a dark background and hard to read.
- Fix. In the quiz settings, on the Pass or fail view, the two colour pickers now have the normal form layout (label on the left) and sit below the "Choose the report colours myself" checkbox. The colour box of an empty band row is hidden.
