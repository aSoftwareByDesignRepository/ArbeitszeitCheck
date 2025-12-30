# ⚠️ CRITICAL: Build erforderlich!

## Problem

Die Datei `arbeitszeitcheck-main.js` wurde **NICHT neu gebaut**. Die Webpack-Konfiguration mit `appName` und `appVersion` im `DefinePlugin` wurde zwar hinzugefügt, aber die Datei muss neu kompiliert werden, damit die Änderungen wirksam werden.

## Aktueller Status

✅ **Webpack Config korrigiert** - `appName` und `appVersion` sind im `DefinePlugin` definiert
✅ **Workaround angewendet** - Direkte Ersetzung in der kompilierten Datei
⚠️ **Build erforderlich** - Für permanente Lösung

## Sofortige Lösung

**Die Datei MUSS neu gebaut werden:**

```bash
cd apps/arbeitszeitcheck
npm install  # Falls noch nicht gemacht
npm run build:dev
```

## Warum der Build fehlschlägt

Der Build im Docker-Container schlägt fehl, weil:
- `@nextcloud/webpack-vue-config` nicht installiert ist
- Oder npm/node nicht verfügbar ist

## Alternative: Build auf Host-Maschine

```bash
# Auf deiner lokalen Maschine (nicht im Container)
cd /home/alex/Development/nextcloud-dev/apps/arbeitszeitcheck
npm install
npm run build:dev

# Die Datei wird dann in apps/arbeitszeitcheck/js/arbeitszeitcheck-main.js erstellt
# Diese wird automatisch vom Container verwendet (wenn gemountet)
```

## Verifizierung nach Build

Nach dem Build sollte die Datei enthalten:
- `realAppName = "arbeitszeitcheck"` (nicht `realAppName = appName`)
- `realAppVersion = "1.0.0"` (nicht `realAppVersion = appVersion`)

## Hinweis zum `extend`-Fehler

Der `extend`-Fehler ist ein **separates Problem**:
- `@nextcloud/vue` v8 ist für Vue 2
- Wir verwenden Vue 3
- Die Komponenten verwenden Vue 2 APIs (`Vue.extend`), die in Vue 3 nicht existieren

**Lösung**: Upgrade auf `@nextcloud/vue` v9+ (für Vue 3) oder Wechsel zu Vue 2.
