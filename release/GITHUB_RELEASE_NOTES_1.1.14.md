## Highlights

- **Approval workflow deadlock fix (app teams)**: Absences and time-entry corrections now correctly check for an assignable manager instead of treating "has colleagues" as "has manager".
- **Routing hardening for `/index.php` installations**: Admin users, admin teams, and settings calls now resolve app URLs consistently, including defensive fallback behavior when `OC.generateUrl()` is not available in context.
- **Frontend request guardrails**: Shared AJAX utilities now enforce centralized URL resolution, CSRF token handling, and cross-origin blocking by default.

## UX and Accessibility

- **Mobile consistency pass (WCAG 2.1 AA focused)**: Improved iPhone safe-area spacing, touch targets, and section rhythm across user and manager views for clearer mobile operation.

## Documentation

- Updated changelogs, README, developer documentation, and user manuals to reflect URL/security guardrails and mobile behavior.

## Install

Use the attached `arbeitszeitcheck-1.1.14.tar.gz` or install from the [Nextcloud App Store](https://apps.nextcloud.com/apps/arbeitszeitcheck).

Full changelog: see `CHANGELOG.md` in the app package.
