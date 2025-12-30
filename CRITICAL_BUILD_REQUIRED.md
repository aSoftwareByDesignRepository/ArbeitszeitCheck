# ⚠️ CRITICAL: Frontend Build Required

## Current Status

The app is **partially working** but has these issues that require a frontend rebuild:

1. ❌ **Vue Feature Flags Warning** - The compiled JS file doesn't include the feature flags
2. ⚠️ **Component Missing Template Warning** - May be resolved after rebuild
3. ✅ **500 Error Fixed** - Backend now handles missing table gracefully

## Why Build is Required

The `webpack.config.js` has been updated with Vue feature flags, but the compiled JavaScript file (`js/arbeitszeitcheck-main.js`) was built **before** these changes. The build process was "Killed" due to memory constraints in Docker.

## Solution: Build on Host Machine

Since Docker doesn't have enough memory, build on your host machine:

```bash
cd /home/alex/Development/nextcloud-dev/apps/arbeitszeitcheck

# Install dependencies (if not done)
npm install

# Build in development mode (faster, less memory)
npm run build:dev
```

The built files will be in `js/arbeitszeitcheck-main.js` and will be available in Docker if the directory is mounted.

## After Building

1. **Clear Nextcloud cache**:
   ```bash
   docker-compose exec nextcloud php occ files:scan --all
   ```

2. **Clear browser cache**: `Ctrl + Shift + R`

3. **Test the app**: `http://localhost:8081/apps/arbeitszeitcheck/`

## Expected Results After Build

✅ No Vue Feature Flags warning
✅ No "Component missing template" warning (if it was a build issue)
✅ No 500 errors (already fixed)
✅ App loads and displays correctly

## Alternative: Increase Docker Memory

If you prefer to build in Docker:
1. Docker Desktop → Settings → Resources → Memory
2. Increase to at least 4GB
3. Run: `docker-compose exec nextcloud bash -c "cd /var/www/html/custom_apps/arbeitszeitcheck && npm run build:dev"`

## Current Workarounds

The app **will work** even without rebuilding, but you'll see:
- Vue Feature Flags warning (cosmetic, doesn't break functionality)
- Possible component warnings (may affect some routes)

The 500 error is **already fixed** and won't appear anymore.
