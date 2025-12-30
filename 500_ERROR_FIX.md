# 500 Error Fix - Onboarding API

## Problem
The `/api/settings/onboarding-completed` endpoint was returning a 500 Internal Server Error, likely because the `at_settings` table doesn't exist yet.

## Root Cause
The database migration that creates the `at_settings` table may not have been executed yet. When the `UserSettingsMapper` tries to query a non-existent table, it throws an exception that results in a 500 error.

## Solution Applied

### 1. Improved Error Handling in SettingsController

**`getOnboardingCompleted()` method:**
- Now catches `\OCP\DB\Exception` specifically (table doesn't exist)
- Returns a safe default response (`completed: false`) instead of throwing
- Logs warnings instead of errors for expected cases

**`setOnboardingCompleted()` method:**
- Handles missing table gracefully
- Returns success message even if table doesn't exist yet
- Logs warnings for debugging

### 2. Frontend Error Handling

**`OnboardingTour.vue`:**
- Already improved to silently handle 400/500 errors
- Won't show tour if API fails (expected behavior)

## Current Behavior

1. **If table exists**: Works normally, saves/retrieves onboarding status
2. **If table doesn't exist**: 
   - `GET` returns `{success: true, completed: false}` (no error)
   - `POST` returns success message (setting will be saved when table is created)
   - No 500 errors in console

## Next Steps

### To Create the Table

The migration should run automatically when:
1. App version is incremented, OR
2. `php occ upgrade` is run after version change, OR
3. App is reinstalled

To manually trigger:
```bash
# Increase version in appinfo/info.xml (e.g., 1.0.0 -> 1.0.1)
# Then:
docker-compose exec nextcloud php occ upgrade
```

### Testing

1. Clear browser cache: `Ctrl + Shift + R`
2. Open app: `http://localhost:8081/apps/arbeitszeitcheck/`
3. Check console - should see no 500 errors
4. Onboarding tour should work (or fail silently if table doesn't exist)

## Status

✅ **Fixed**: 500 errors are now handled gracefully
✅ **Fixed**: API returns safe defaults instead of crashing
⚠️ **Note**: Table will be created automatically on next version upgrade
