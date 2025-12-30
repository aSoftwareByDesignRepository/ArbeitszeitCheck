# Vue.extend Fix Applied

## Problem

`@nextcloud/vue` v8 verwendet `Vue.extend()`, das in Vue 3 nicht existiert. Die kompilierte Datei enthält noch v8 Code, da der Build wegen Speichermangels fehlschlägt.

## Lösung angewendet

### 1. ✅ Webpack Require Shim
Ein Shim wurde hinzugefügt, der `__webpack_require__` patcht, um Vue-Modulen automatisch eine `extend`-Methode hinzuzufügen, die `defineComponent` verwendet.

### 2. ✅ Direkte extend-Aufrufe gepatcht
Alle `.extend({` Aufrufe wurden zu `.defineComponent ? .defineComponent({ : .extend({` geändert, um Vue 3 Kompatibilität zu gewährleisten.

## Status

✅ **Shim hinzugefügt** - Patched `__webpack_require__` für Vue-Module
✅ **extend-Aufrufe gepatcht** - Alle Aufrufe verwenden jetzt `defineComponent` falls verfügbar
⚠️ **Build erforderlich** - Für permanente Lösung muss der Build mit v9+ erfolgreich sein

## Nächste Schritte

1. **Testen** - Prüfe ob der `extend`-Fehler verschwunden ist
2. **Build mit mehr Speicher** - Führe `NODE_OPTIONS="--max-old-space-size=4096" npm run build:dev` aus
3. **Oder Production Build** - `npm run build` (oft effizienter)

## Hinweis

Diese Patches sind Workarounds. Die dauerhafte Lösung ist ein erfolgreicher Build mit `@nextcloud/vue` v9+, das nativ Vue 3 unterstützt.
