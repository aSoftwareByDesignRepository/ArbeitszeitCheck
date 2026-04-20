## Highlights

- **Revision-safe month finalization (optional)** — Admin toggle for per-employee calendar-month sealing: canonical JSON snapshot, SHA-256 hash chain, append-only revisions, audit trail, and downloadable PDF summary. Finalized months are read-only via normal app APIs; admins can reopen with a mandatory audited reason.

## Documentation

- User manuals (EN/DE), developer documentation, and compliance context updated for month closure (retention, limits: in-app tamper evidence, not QES).
- Follow-up: README development/test instructions, grace-period and auto-finalize behavior in user guides, developer guide corrections (no misleading `npm run watch`), `docs/README` index, `package.json` version aligned with `info.xml`.

## Install

Use the attached `arbeitszeitcheck-1.1.12.tar.gz` or install from the [Nextcloud App Store](https://apps.nextcloud.com/apps/arbeitszeitcheck). For self-hosted installs, prefer deploying from a verified release archive; see `release/deploy-from-release.sh` in the repository.

Full changelog: see `CHANGELOG.md` in the app package.
