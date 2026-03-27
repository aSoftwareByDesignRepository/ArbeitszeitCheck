# Release artifacts

- `arbeitszeitcheck-1.1.6.tar.gz` — installable app archive (generated; not committed).
- `arbeitszeitcheck-1.1.6.tar.gz.asc` — detached GPG signature (optional; not committed).
- `CHECKSUMS-1.1.6.txt` — SHA-256 / SHA-512 for the tarball (committed).

Regenerate the tarball from the repository root that **contains** the `arbeitszeitcheck` folder (e.g. `apps/` in this monorepo):

```bash
cd apps
tar --exclude='arbeitszeitcheck/node_modules' \
    --exclude='arbeitszeitcheck/node_modules.broken-1774623876' \
    --exclude='arbeitszeitcheck/test-results' \
    -czf arbeitszeitcheck/release/arbeitszeitcheck-1.1.6.tar.gz arbeitszeitcheck
cd arbeitszeitcheck/release
sha256sum arbeitszeitcheck-1.1.6.tar.gz
sha512sum arbeitszeitcheck-1.1.6.tar.gz
gpg --detach-sign --armor arbeitszeitcheck-1.1.6.tar.gz
```

Upload the `.tar.gz` to the Nextcloud app store and paste the **SHA-256** (or SHA-512 if the form asks for it) from `CHECKSUMS-1.1.6.txt`.
