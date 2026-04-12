# Release folder

This directory holds **release documentation** and optional **checksum list files** for published versions.

## Full workflow (Nextcloud App Store)

See **[APPSTORE-RELEASE.md](./APPSTORE-RELEASE.md)** — build tarball, SHA-256/512, OpenSSL signature, **required GitHub Release** on the public app repo (`aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck`, not the private monorepo — see `ready2publish/REPOSITORY-LAYOUT.md`), App Store upload (same `.tar.gz` bytes), and a **gitignore checklist** (what must not be committed).

## Files in this folder

| File | Purpose |
|------|---------|
| `APPSTORE-RELEASE.md` | Step-by-step app store upload workflow |
| `CHECKSUMS-X.Y.Z.txt` | Optional: SHA-256 / SHA-512 for the matching tarball |
| `GITHUB_RELEASE_NOTES_*.md` | Optional: copy-paste for GitHub releases |

**Generated** (not committed — see root `.gitignore`):

- `arbeitszeitcheck-X.Y.Z.tar.gz`
- `arbeitszeitcheck-X.Y.Z.tar.gz.asc` (optional GPG)
- `SIGNATURE-*.txt` / `APPSTORE-SIGNATURE*.txt` / `*.b64` if you save signature output locally

## One-liner: build signed tarball

```bash
cd apps/arbeitszeitcheck
make release-signed
```

Output archive: `build/release/arbeitszeitcheck-X.Y.Z.tar.gz`.

Deploy from this signed tarball only. Do not rsync/copy a development checkout into production.

## Production deploy helper

Use `deploy-from-release.sh` to deploy the signed tarball safely:

```bash
cd apps/arbeitszeitcheck/release
./deploy-from-release.sh \
  --archive ../build/release/arbeitszeitcheck-X.Y.Z.tar.gz \
  --target-apps-dir /var/www/html/custom_apps \
  --occ /var/www/html/occ
```

The script validates archive layout, checks for forbidden development paths, requires `appinfo/signature.json`, deploys to `custom_apps/arbeitszeitcheck`, and runs `occ integrity:check-app`.

Details and signing commands: **`APPSTORE-RELEASE.md`**.
