# Changes in the latest version

What changed or was added since the previous version of `local_bandedgrade`. The full history is in [changelog.md](changelog.md).

## Unreleased

- Release. A new GitHub workflow, `moodle-release.yml`, sends each pushed `v*` tag to the Moodle plugins directory. It needs the repository secret `MARKETPLACE_TOKEN`.

## 0.10.2, 2026-10-31

- Fix. The "Changed by hand" badge on the Number correct page is now white on dark teal. It was dark text on a dark background and hard to read.
- Fix. In the quiz settings, on the Pass or fail view, the two colour pickers now have the normal form layout (label on the left) and sit below the "Choose the report colours myself" checkbox. The colour box of an empty band row is hidden.
