# Nextcloud App Store — release workflow (ArbeitszeitCheck)

End-to-end steps to produce the **archive**, **checksums**, and **code signature** you need at [apps.nextcloud.com](https://apps.nextcloud.com) (developer account → your app → new version).

Replace `X.Y.Z` with the real version (e.g. `1.1.6`).

---

## 0. Prerequisites

- Registered app and **developer certificate** from Nextcloud (private key on your machine).
- Export local key/cert paths in your shell before signing (for example `APP_CERT_KEY_PATH` and `APP_CERT_CRT_PATH`).
- This monorepo: build the tarball from **`apps/`** so the archive root is `arbeitszeitcheck/`.

---

## 1. Version and changelog

1. Bump **`appinfo/info.xml`**: `<version>X.Y.Z</version>` and any required `<dependencies>` / `<nextcloud min-version="…" max-version="…"/>`.
2. Update **`CHANGELOG.md`** / **`CHANGELOG.de.md`** for `X.Y.Z`.
3. Optionally add **`release/GITHUB_RELEASE_NOTES_X.Y.Z.md`** for GitHub.

---

## 2. Build the installable `.tar.gz`

Preferred (uses this app's guarded Makefile workflow):

```bash
cd apps/arbeitszeitcheck
make release-signed
```

This produces `build/release/arbeitszeitcheck-X.Y.Z.tar.gz`, verifies the archive does not contain development paths (for example `.git`, `node_modules`, `tests`, `build`, `scripts`), signs the extracted archive payload via `occ integrity:sign-app`, validates that `appinfo/signature.json` does not reference forbidden development paths, and repacks the signed tarball.

If `make release-signed` fails on the host because `occ` cannot run (for example missing PDO driver), use the Docker signing fallback below.

### Docker signing fallback (recommended when using the local Nextcloud container)

Use this after creating `release/arbeitszeitcheck-X.Y.Z.tar.gz`:

```bash
VERSION=X.Y.Z
APPID=arbeitszeitcheck
CONTAINER=nextcloud-app

# 1) Copy key material into container tmp
#    Set APP_CERT_KEY_PATH and APP_CERT_CRT_PATH in your shell first.
docker cp "${APP_CERT_KEY_PATH}" "${CONTAINER}:/tmp/${APPID}.key"
docker cp "${APP_CERT_CRT_PATH}" "${CONTAINER}:/tmp/${APPID}.crt"
docker exec "${CONTAINER}" sh -lc "chown www-data:www-data /tmp/${APPID}.key /tmp/${APPID}.crt && chmod 600 /tmp/${APPID}.key && chmod 644 /tmp/${APPID}.crt"

# 2) Sign extracted archive payload with occ (as www-data), repack to /tmp
docker exec -u www-data "${CONTAINER}" sh -lc "
  set -e
  ARCHIVE=/var/www/html/custom_apps/${APPID}/release/${APPID}-${VERSION}.tar.gz
  STAGING=\$(mktemp -d)
  tar -xzf \"\$ARCHIVE\" -C \"\$STAGING\"
  php /var/www/html/occ integrity:sign-app \
    --privateKey=/tmp/${APPID}.key \
    --certificate=/tmp/${APPID}.crt \
    --path=\"\$STAGING/${APPID}\"
  tar -czf /tmp/${APPID}-signed-${VERSION}.tar.gz -C \"\$STAGING\" \"${APPID}\"
  rm -rf \"\$STAGING\"
"

# 3) Copy signed archive back and clean temporary secrets
docker cp "${CONTAINER}:/tmp/${APPID}-signed-${VERSION}.tar.gz" "apps/${APPID}/release/${APPID}-${VERSION}.tar.gz"
docker exec "${CONTAINER}" sh -lc "rm -f /tmp/${APPID}.key /tmp/${APPID}.crt /tmp/${APPID}-signed-${VERSION}.tar.gz"
```

Validate the result before continuing:

```bash
cd apps/arbeitszeitcheck/release
tar -tzf "arbeitszeitcheck-${VERSION}.tar.gz" | grep "appinfo/signature.json"
```

Manual fallback (advanced, use only if you cannot run `make release-signed` or Docker signing):

From the repo root that contains `apps/arbeitszeitcheck` (here: `nextcloud-development/apps/`; local folder name may differ):

```bash
cd apps
VERSION=X.Y.Z
tar --exclude='arbeitszeitcheck/node_modules' \
    --exclude='arbeitszeitcheck/node_modules.broken-*' \
    --exclude='arbeitszeitcheck/test-results' \
    --exclude='arbeitszeitcheck/.git' \
    --exclude='arbeitszeitcheck/.github' \
    --exclude='arbeitszeitcheck/tests' \
    --exclude='arbeitszeitcheck/scripts' \
    --exclude='arbeitszeitcheck/build' \
    --exclude='arbeitszeitcheck/vendor/**/.github' \
    --exclude='arbeitszeitcheck/vendor/**/.phpunit.result.cache' \
    --exclude='arbeitszeitcheck/release/arbeitszeitcheck-*.tar.gz' \
    -czf "arbeitszeitcheck/release/arbeitszeitcheck-${VERSION}.tar.gz" arbeitszeitcheck
```

If you use the manual fallback, you **must** re-sign the extracted archive tree with `occ integrity:sign-app` and validate that `appinfo/signature.json` contains no `.git/`, `node_modules/`, `.github/`, `tests/`, `build/`, or `scripts/` entries before upload.

If `make release-signed` fails due to `build/` permission issues (for example root-owned from an older run), either fix ownership first or use this manual tarball + Docker signing path.

**Do not commit** the tarball (see `.gitignore`).

### Critical deployment rule (prevents integrity errors)

Deploy **only** from the signed release tarball.
Do **not** copy/sync a development checkout (`git`, `rsync`, IDE upload) into production when `appinfo/signature.json` exists.

If production ever shows many `FILE_MISSING` entries under `.git/*` or a huge list under `node_modules/*`, that is a strong indicator the app was signed from a development tree and then deployed in a different file layout.

### Safe production deployment helper

```bash
cd apps/arbeitszeitcheck/release
./deploy-from-release.sh \
  --archive ../build/release/arbeitszeitcheck-X.Y.Z.tar.gz \
  --target-apps-dir /var/www/html/custom_apps \
  --occ /var/www/html/occ
```

This helper validates archive integrity prerequisites before replacing app files.
By default, it now requires `--occ` so integrity checks cannot be silently skipped.

---

## 3. SHA-256 / SHA-512 (app store + checksum file)

```bash
cd apps/arbeitszeitcheck/release
sha256sum "arbeitszeitcheck-${VERSION}.tar.gz"
sha512sum "arbeitszeitcheck-${VERSION}.tar.gz"
```

- The app store form usually asks for **SHA-256** of the uploaded archive.
- Copy the hashes into **`release/CHECKSUMS-X.Y.Z.txt`** (template: see existing `CHECKSUMS-*.txt`). Only commit the checksums file if you want them in git for traceability; the tarball itself stays **ignored**.

---

## 4. Code signature (base64) for the app store

The store expects a **base64-encoded** RSA signature over the **exact** `.tar.gz` bytes (SHA-512 digest signed with your app certificate key).

**One line** (copy output into the store’s signature field):

```bash
openssl dgst -sha512 -sign "${APP_CERT_KEY_PATH}" \
  "arbeitszeitcheck-${VERSION}.tar.gz" | openssl base64 | tr -d '\n'
```

If you prefer wrapped output, omit `| tr -d '\n'`.

**Important:** If you change the tarball or rebuild, **regenerate** the signature. Any byte change invalidates it.

**Do not commit** the private key or ad-hoc signature dump files (see `.gitignore`).

---

## 5. Optional: detached GPG sign the archive

Not required by the app store; useful for mirrors or GitHub releases.

```bash
gpg --detach-sign --armor "arbeitszeitcheck-${VERSION}.tar.gz"
```

Produces `arbeitszeitcheck-X.Y.Z.tar.gz.asc` — **ignored** by git.

---

## 6. GitHub Release (**required**) — **`nextcloud-arbeitszeitcheck`**

You **must** create a **GitHub Release** tagged **`vX.Y.Z`** and attach **`arbeitszeitcheck-X.Y.Z.tar.gz`** before you treat the App Store upload as complete. The SHA-256, OpenSSL signature, and store upload **must** be the **same byte-identical** file as the release asset. User-facing downloads and tags belong on the **public** app repo, not on the private monorepo.

| Repository | Role |
|------------|------|
| **This workspace** (`nextcloud-development`, …) | Development; tarball is built under `apps/arbeitszeitcheck/build/release/`. |
| **`aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck`** | **Public** — source sync + **GitHub Releases** + `.tar.gz` asset. |

**Canonical repo:** https://github.com/aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck  

Always pass **`--repo aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck`** to `gh` (or `export GH_REPO=...`) so you never target the monorepo by mistake.

**1. Push sources** (from monorepo root):

```bash
./scripts/push-public-app-subtree.sh arbeitszeitcheck aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck
```

**2. Create the release** from the built tarball (paths relative to `apps/arbeitszeitcheck`):

```bash
VERSION=X.Y.Z
cd apps/arbeitszeitcheck

gh release create "v${VERSION}" \
  --repo aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck \
  --title "v${VERSION}" \
  --notes-file "release/GITHUB_RELEASE_NOTES_${VERSION}.md" \
  "build/release/arbeitszeitcheck-${VERSION}.tar.gz"
```

If the release **already exists** and you only need to **replace the asset**:

```bash
gh release upload "v${VERSION}" "build/release/arbeitszeitcheck-${VERSION}.tar.gz" \
  --repo aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck \
  --clobber
```

Publishing the **tarball** does not push git history; subtree sync is separate. The release command only attaches the archive to **`nextcloud-arbeitszeitcheck`**.

---

## 7. Upload at apps.nextcloud.com

Upload the **same** `build/release/arbeitszeitcheck-X.Y.Z.tar.gz` you attached to the GitHub Release.

| Field | Source |
|--------|--------|
| **Archive** | `build/release/arbeitszeitcheck-X.Y.Z.tar.gz` (same bytes as GitHub asset) |
| **SHA-256** | From `sha256sum` / `CHECKSUMS-X.Y.Z.txt` |
| **Signature** | Output of the `openssl dgst … \| openssl base64` command |
| **Changelog** | Paste from `CHANGELOG.md` (or shortened) |

Submit; fix any validation errors (wrong checksum/signature almost always means a wrong file or stale copy).

---

## 8. Required chat handoff (every release)

After release creation and before closing the task, always paste these two items in chat:

1. **App Store signature** — the single-line base64 value from `SIGNATURE-X.Y.Z.txt` (or direct command output).
2. **Direct GitHub tarball URL** — `https://github.com/aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck/releases/download/vX.Y.Z/arbeitszeitcheck-X.Y.Z.tar.gz`.

This handoff is mandatory for every release so upload data can be copied without re-running commands.

---


## What is committed vs ignored

| Artifact | Committed? |
|----------|------------|
| `README.md`, `APPSTORE-RELEASE.md`, `GITHUB_RELEASE_NOTES_*.md` | Yes (workflow + notes) |
| `CHECKSUMS-X.Y.Z.txt` | Optional (recommended for your team) |
| `*.tar.gz`, `*.tar.gz.asc` | **No** (gitignored) |
| `SIGNATURE-*.txt` or local signature dumps | **No** (gitignored) |
| `appinfo/signature.json` in working tree | **No** (generated during signing, not source-controlled) |
| Private key `*.key` | **Never** in the repo |

---

## Quick checklist

- [ ] `info.xml` version = `X.Y.Z`
- [ ] Changelog updated
- [ ] Tarball built (and signed) with `appinfo/signature.json` inside archive
- [ ] SHA-256 + SHA-512 recorded; store gets **SHA-256**
- [ ] OpenSSL base64 signature **from the same tarball file**
- [ ] Nothing uploaded to git except docs/checksums (no `.tar.gz`, no keys)
- [ ] **GitHub Release** on **`nextcloud-arbeitszeitcheck`**: tag `vX.Y.Z`, attach **`build/release/arbeitszeitcheck-X.Y.Z.tar.gz`**, `gh --repo aSoftwareByDesignRepository/nextcloud-arbeitszeitcheck` (**required**)
- [ ] App Store upload uses **that same** tarball bytes
- [ ] Chat handoff posted: App Store signature + direct GitHub tarball URL
