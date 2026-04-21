## ArbeitszeitCheck v1.2.1

### Fixed

- Restored reliable access to paused time entries in edit/delete workflows.
- Hardened paused-entry lifecycle so edited paused entries are finalized consistently as `completed`.
- Clock-in now resumes same-day paused sessions instead of creating duplicate automatic entries.
- Added migration `Version1020Date20260421000000` to repair all remaining orphaned `paused` records.

### Operational Notes

- Break auto-fallback behavior from settings remains unchanged.
- Existing databases are auto-repaired on app upgrade to this version.
