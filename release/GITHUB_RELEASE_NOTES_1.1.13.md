## Highlights

- **Month closure grace period and auto-finalization**: Admins can configure a grace period after month end before automatic finalization runs. Pending approvals and open absence workflows still block finalization.
- **App-admin allowlist and middleware enforcement**: App administration can be restricted to selected Nextcloud admins with consistent 403 handling for authenticated non-app-admin users.
- **Release pipeline hardening**: Signing now targets the extracted release archive payload, reducing integrity-check drift between development trees and deployed archives.

## Documentation

- Deployment guidance now explicitly requires deploying from the signed tarball.
- Added Docker-first security regression test commands for role-gating verification.
- User and developer docs updated for app-admin operations and month-closure behavior.

## Install

Use the attached `arbeitszeitcheck-1.1.13.tar.gz` or install from the [Nextcloud App Store](https://apps.nextcloud.com/apps/arbeitszeitcheck).

Full changelog: see `CHANGELOG.md` in the app package.
