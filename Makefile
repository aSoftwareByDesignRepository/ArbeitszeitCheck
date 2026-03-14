# Makefile for ArbeitszeitCheck app release

app_name = arbeitszeitcheck
build_dir = build
release_dir = $(build_dir)/release
version = $(shell grep '^\s*<version>' appinfo/info.xml | sed 's/.*<version>\([0-9.]*\)<\/version>.*/\1/' | head -1)

.PHONY: release clean

release:
	@echo "Building $(app_name) v$(version)..."
	@mkdir -p $(release_dir)
	@staging=$$(mktemp -d) && \
		mkdir -p "$$staging/$(app_name)" && \
		rsync -a --exclude='.git' --exclude='$(build_dir)' --exclude='.github' \
			--exclude='node_modules' --exclude='tests' --exclude='.phpunit.result.cache' \
			./ "$$staging/$(app_name)/" && \
		tar -czf $(release_dir)/$(app_name).tar.gz -C "$$staging" $(app_name) && \
		rm -rf "$$staging"
	@echo "Created $(release_dir)/$(app_name).tar.gz"

clean:
	rm -rf $(build_dir)

# Generate tarball signature for App Store upload (single-line base64, no line breaks)
# Paste the output into the App Store upload form's "Signature" field
sign-tarball:
	@test -f ~/.nextcloud/certificates/$(app_name).key || (echo "Error: Missing ~/.nextcloud/certificates/$(app_name).key"; exit 1)
	@test -f $(release_dir)/$(app_name).tar.gz || (echo "Error: Run 'make release' first"; exit 1)
	@openssl dgst -sha512 -sign $$HOME/.nextcloud/certificates/$(app_name).key $(release_dir)/$(app_name).tar.gz 2>/dev/null | base64 | tr -d '\n'; echo

# Sign the app for Nextcloud App Store (requires certificate)
# Generate cert: openssl req -nodes -newkey rsa:4096 -keyout ~/.nextcloud/certificates/arbeitszeitcheck.key -out ~/.nextcloud/certificates/arbeitszeitcheck.csr -subj "/CN=arbeitszeitcheck"
# Store signed cert as ~/.nextcloud/certificates/arbeitszeitcheck.crt
sign:
	@test -f ~/.nextcloud/certificates/$(app_name).key || (echo "Error: Run 'make release' first, then obtain certificate from https://github.com/nextcloud/app-certificate-requests"; exit 1)
	@test -f ~/.nextcloud/certificates/$(app_name).crt || (echo "Error: Store signed certificate at ~/.nextcloud/certificates/$(app_name).crt"; exit 1)
	php ../../occ integrity:sign-app \
		--privateKey=$$HOME/.nextcloud/certificates/$(app_name).key \
		--certificate=$$HOME/.nextcloud/certificates/$(app_name).crt \
		--path=$$(pwd)
	@echo "App signed. Commit appinfo/signature.json before release."
