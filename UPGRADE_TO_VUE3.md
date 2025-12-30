# Upgrade auf @nextcloud/vue v9+ (Vue 3 Support)

## Problem

`@nextcloud/vue` v8 ist für Vue 2 und verursacht den `extend`-Fehler, da Vue 3 keine `Vue.extend` API hat.

## Lösung: Upgrade auf v9+

✅ **`package.json` aktualisiert** - `@nextcloud/vue: ^9.0.0`
✅ **`main.js` aktualisiert** - Verwendet jetzt `setAppName()` und `setAppVersion()`
✅ **`webpack.config.js` aktualisiert** - Entfernt `appName`/`appVersion` aus DefinePlugin (nicht mehr nötig)

## Nächste Schritte

1. **Dependencies installieren:**
   ```bash
   cd apps/arbeitszeitcheck
   npm install
   ```

2. **Neu bauen:**
   ```bash
   npm run build:dev
   ```

3. **Testen:**
   - `appName`/`appVersion` Fehler sollten verschwunden sein
   - `extend`-Fehler sollte verschwunden sein
   - App sollte funktionieren

## Breaking Changes (möglicherweise)

`@nextcloud/vue` v9+ könnte einige API-Änderungen haben. Prüfe:
- Komponenten-Imports funktionieren noch
- Props/Events sind kompatibel
- Styling funktioniert

## Falls Probleme auftreten

Falls es Breaking Changes gibt, prüfe:
- [@nextcloud/vue Changelog](https://github.com/nextcloud/nextcloud-vue/releases)
- Migration Guide falls vorhanden
