# Docker Setup Guide for ArbeitszeitCheck

## Problem
When accessing `http://localhost:8081/apps/arbeitszeitcheck/` in Docker:
- Nothing shows (blank page)
- App icon not displayed correctly

## Solution

### Step 1: Build Frontend Assets

The frontend **must be built** before the app works. In Docker:

```bash
# Enter your Nextcloud Docker container
docker exec -it <your-nextcloud-container> bash

# Navigate to app directory
cd /var/www/html/apps/arbeitszeitcheck

# Install npm dependencies (first time only)
npm install

# Build frontend assets
npm run build

# Exit container
exit
```

**Or if volumes are mounted on host:**
```bash
cd /path/to/nextcloud-dev/apps/arbeitszeitcheck
npm install
npm run build
```

### Step 2: Verify Build Output

After building, check these files exist:
```bash
ls -la js/arbeitszeitcheck-main.js    # Should exist
ls -la css/arbeitszeitcheck-main.css # Should exist (if CSS extracted)
```

### Step 3: Clear Nextcloud Cache

After building, clear Nextcloud cache:
```bash
docker exec -it <your-nextcloud-container> php occ files:scan --all
docker exec -it <your-nextcloud-container> php occ maintenance:mode --off
```

### Step 4: Verify App Icon

Check app icon exists:
```bash
ls -la apps/arbeitszeitcheck/app.svg  # Should exist
```

If icon is missing or broken:
1. Verify file exists and is valid SVG
2. Clear browser cache (Ctrl+Shift+R)
3. Check Nextcloud logs: `docker exec -it <container> tail -f /var/www/html/data/nextcloud.log`

### Step 5: Check Browser Console

Open browser developer tools (F12) and check:
- **Console tab**: Look for JavaScript errors
- **Network tab**: Check for 404 errors (missing JS/CSS files)
- **Application tab**: Check for CSP violations

## Common Issues & Solutions

### Issue 1: Blank Page / Nothing Shows

**Cause**: Frontend not built  
**Solution**: Run `npm run build` inside Docker container

### Issue 2: 404 Errors for JS/CSS Files

**Cause**: Files not found or cache issue  
**Solution**: 
1. Verify files exist: `ls -la js/ css/`
2. Clear Nextcloud cache: `php occ files:scan --all`
3. Hard refresh browser: Ctrl+Shift+R

### Issue 3: App Icon Missing

**Cause**: File not found or path incorrect  
**Solution**:
1. Check `app.svg` exists in app root
2. Verify `info.xml` has correct icon path: `<icon>app.svg</icon>`
3. Clear browser cache

### Issue 4: JavaScript Errors in Console

**Cause**: Build errors or missing dependencies  
**Solution**:
1. Check build output for errors
2. Reinstall dependencies: `rm -rf node_modules && npm install`
3. Rebuild: `npm run build`

## Development Workflow

For active development with auto-rebuild:

```bash
# In Docker container
cd /var/www/html/apps/arbeitszeitcheck
npm run dev  # Watch mode - rebuilds on file changes
```

## Production Build

For production deployment:

```bash
cd /path/to/nextcloud-dev/apps/arbeitszeitcheck
npm run build  # Production build with minification
```

## Quick Test

After building, test the app:
1. Open: `http://localhost:8081/apps/arbeitszeitcheck/`
2. Should see the dashboard (not blank page)
3. App icon should appear in navigation
4. No console errors in browser

If still not working, check Nextcloud logs:
```bash
docker exec -it <container> tail -f /var/www/html/data/nextcloud.log
```
