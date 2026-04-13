# Makefile for ArbeitszeitCheck app release

app_name = arbeitszeitcheck
build_dir = build
release_dir = $(build_dir)/release
version = $(shell grep '^\s*<version>' appinfo/info.xml | sed 's/.*<version>\([0-9.]*\)<\/version>.*/\1/' | head -1)
archive_name = $(app_name)-$(version).tar.gz
archive_path = $(release_dir)/$(archive_name)
occ = ../../occ

.PHONY: release verify-release verify-signature-manifest sign-release release-signed clean test-security-role-gating-docker

release:
	@echo "Building $(app_name) v$(version)..."
	@mkdir -p $(release_dir)
	@staging=$$(mktemp -d) && \
		mkdir -p "$$staging/$(app_name)" && \
		rsync -a --exclude='.git' --exclude='$(build_dir)' --exclude='.github' \
			--exclude='node_modules' --exclude='tests' --exclude='.phpunit.result.cache' \
			--exclude='scripts' --exclude='release/*.tar.gz' --exclude='release/*.asc' \
			--exclude='appinfo/signature.json' \
			./ "$$staging/$(app_name)/" && \
		tar -czf $(archive_path) -C "$$staging" $(app_name) && \
		rm -rf "$$staging"
	@echo "Created $(archive_path)"

verify-release:
	@test -f $(archive_path) || (echo "Error: Run 'make release' first"; exit 1)
	@if tar -tzf $(archive_path) | grep -Eq '/(\.git/|node_modules/|build/|tests/|scripts/)'; then \
		echo "Error: release archive contains forbidden development paths"; \
		tar -tzf $(archive_path) | grep -E '/(\.git/|node_modules/|build/|tests/|scripts/)' || true; \
		exit 1; \
	fi
	@echo "Release archive layout looks clean."

verify-signature-manifest:
	@test -f $(archive_path) || (echo "Error: Run 'make release-signed' first"; exit 1)
	@tmpdir=$$(mktemp -d) && \
		trap 'rm -rf "$$tmpdir"' EXIT && \
		tar -xzf $(archive_path) -C "$$tmpdir" "$(app_name)/appinfo/signature.json" && \
		sig="$$tmpdir/$(app_name)/appinfo/signature.json" && \
		if ! test -f "$$sig"; then \
			echo "Error: signature.json missing from signed archive"; \
			exit 1; \
		fi && \
		if grep -Eq '"([^"]*/)?(\.git|node_modules|build|tests|scripts)\\/' "$$sig"; then \
			echo "Error: signature.json references forbidden development paths"; \
			grep -E '"([^"]*/)?(\.git|node_modules|build|tests|scripts)\\/' "$$sig" || true; \
			exit 1; \
		fi
	@echo "Signature manifest sanity check passed."

clean:
	rm -rf $(build_dir)

# Generate tarball signature for App Store upload (single-line base64, no line breaks)
# Paste the output into the App Store upload form's "Signature" field
sign-tarball:
	@test -f ~/.nextcloud/certificates/$(app_name).key || (echo "Error: Missing ~/.nextcloud/certificates/$(app_name).key"; exit 1)
	@test -f $(archive_path) || (echo "Error: Run 'make release' first"; exit 1)
	@openssl dgst -sha512 -sign $$HOME/.nextcloud/certificates/$(app_name).key $(archive_path) 2>/dev/null | base64 | tr -d '\n'; echo

# Sign the release archive payload with Nextcloud app signature
# This signs the extracted archive tree (not your local dev checkout), then repacks it.
# Generate cert: openssl req -nodes -newkey rsa:4096 -keyout ~/.nextcloud/certificates/arbeitszeitcheck.key -out ~/.nextcloud/certificates/arbeitszeitcheck.csr -subj "/CN=arbeitszeitcheck"
# Store signed cert as ~/.nextcloud/certificates/arbeitszeitcheck.crt
sign-release: verify-release
	@test -f ~/.nextcloud/certificates/$(app_name).key || (echo "Error: Missing ~/.nextcloud/certificates/$(app_name).key (see https://github.com/nextcloud/app-certificate-requests)"; exit 1)
	@test -f ~/.nextcloud/certificates/$(app_name).crt || (echo "Error: Store signed certificate at ~/.nextcloud/certificates/$(app_name).crt"; exit 1)
	@test -f $(occ) || (echo "Error: occ not found at $(occ). Override with 'make sign-release occ=/path/to/occ'"; exit 1)
	@staging=$$(mktemp -d) && \
		trap 'rm -rf "$$staging"' EXIT && \
		tar -xzf $(archive_path) -C "$$staging" && \
		php $(occ) integrity:sign-app \
			--privateKey=$$HOME/.nextcloud/certificates/$(app_name).key \
			--certificate=$$HOME/.nextcloud/certificates/$(app_name).crt \
			--path="$$staging/$(app_name)" && \
		tar -czf $(archive_path) -C "$$staging" $(app_name)
	@echo "Signed archive updated at $(archive_path)"

release-signed: release sign-release verify-signature-manifest
	@echo "Release build + Nextcloud signature complete."

test-security-role-gating-docker:
	@bash scripts/test-security-role-gating-docker.sh
