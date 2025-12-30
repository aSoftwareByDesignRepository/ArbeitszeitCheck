# CRITICAL: Webpack DefinePlugin Fix für @nextcloud/vue

## Problem erkannt

`@nextcloud/vue` v8 erwartet `appName` und `appVersion` als **globale Variablen zur BUILD-ZEIT**, nicht zur Laufzeit!

Aus dem Source-Code von `@nextcloud/vue`:
```javascript
let realAppName = "missing-app-name";
try {
  realAppName = appName;  // <-- Erwartet globale Variable!
} catch {
  logger.error("The `@nextcloud/vue` library was used without setting / replacing the `appName`.");
}
```

## Lösung implementiert

✅ **`webpack.config.js` wurde aktualisiert** mit:
```javascript
new webpack.DefinePlugin({
  __VUE_OPTIONS_API__: JSON.stringify(true),
  __VUE_PROD_DEVTOOLS__: JSON.stringify(false),
  __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: JSON.stringify(false),
  // @nextcloud/vue v8 requires these to be defined at build time
  appName: JSON.stringify('arbeitszeitcheck'),
  appVersion: JSON.stringify('1.0.0')
})
```

## WICHTIG: Build erforderlich!

**Die JS-Datei MUSS neu gebaut werden**, damit die Änderungen wirksam werden!

### Option 1: Build lokal (empfohlen)
```bash
cd apps/arbeitszeitcheck
npm install  # Falls noch nicht gemacht
npm run build:dev
```

### Option 2: Build im Container (falls npm installiert)
```bash
docker-compose exec nextcloud bash
cd /var/www/html/custom_apps/arbeitszeitcheck
npm run build:dev
```

### Option 3: Build auf Host-Maschine
```bash
cd apps/arbeitszeitcheck
npm run build:dev
# Dann Dateien zurückkopieren falls nötig
```

## Verifizierung nach Build

Nach dem Build sollte die kompilierte Datei `arbeitszeitcheck-main.js` enthalten:
- `"arbeitszeitcheck"` statt `"missing-app-name"`
- `"1.0.0"` statt leerer String

## Warum das funktioniert

Webpack's `DefinePlugin` ersetzt zur **Build-Zeit** alle Vorkommen von `appName` und `appVersion` im Code mit den definierten Werten. Das ist genau das, was `@nextcloud/vue` v8 erwartet.

## Status

✅ **Webpack Config korrigiert**
⚠️ **Build erforderlich - Datei muss neu kompiliert werden**
