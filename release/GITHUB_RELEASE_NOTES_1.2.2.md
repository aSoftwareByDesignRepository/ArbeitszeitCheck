## ArbeitszeitCheck v1.2.2

This patch release hardens localized decimal-hour handling across settings and time-entry compatibility endpoints.

### Fixed
- Admin settings now correctly accept localized decimal values like `7,74` without truncation.
- Legacy hours request payloads in time-entry APIs now parse comma and dot decimals consistently.

### Changed
- Updated relevant hour input precision from one decimal to two decimals where needed (`step="0.01"`), including clearer help text for 38.7-hour week scenarios.

### Upgrade notes
- No manual migration steps required.
- Existing data remains compatible; this release focuses on safer parsing and input precision.
