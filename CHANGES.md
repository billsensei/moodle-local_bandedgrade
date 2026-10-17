# Changes in the latest version

What changed or was added since the previous version of `local_bandedgrade`. The full history is in [changelog.md](changelog.md).

## 0.9.4, 2026-10-17

- Fix. Restoring a backup file that was edited by hand and lacks some of the plugin's settings (bands, enabled, weight, score column) no longer gives PHP warnings. Without bands the quiz is restored with banded grading off. No database change. New file `CHANGES.md` lists what changed in each version.
