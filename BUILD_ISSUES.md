# Build Issues and Solutions

## Problem: Build Gets "Killed"

The `npm run build:dev` command is being killed due to memory constraints in the Docker container.

### Solutions

#### Option 1: Build on Host (Recommended)
Build the frontend assets on your host machine where you have more memory:

```bash
cd apps/arbeitszeitcheck
npm install
npm run build:dev
```

Then copy the built files to the Docker container or mount the directory.

#### Option 2: Increase Docker Memory
If using Docker Desktop, increase the memory limit:
- Docker Desktop → Settings → Resources → Memory
- Increase to at least 4GB

#### Option 3: Use Build Script
We have a `build-docker.sh` script that handles this:

```bash
./build-docker.sh
```

## Current Status

- ✅ Vue Feature Flags are defined in `webpack.config.js`
- ✅ All Vue components have templates
- ⚠️ Build is being killed due to memory constraints
- ⚠️ Onboarding API returns 400 (table may not exist yet)

## Next Steps

1. **Build on host** (if possible) or increase Docker memory
2. **Run migrations** to create the `at_settings` table:
   ```bash
   docker-compose exec nextcloud php occ upgrade
   ```
3. **Test the app** after rebuilding

## Feature Flags

The Vue feature flags are now properly configured in `webpack.config.js`:
- `__VUE_OPTIONS_API__: true`
- `__VUE_PROD_DEVTOOLS__: false`
- `__VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false`

These will be included in the next successful build.
