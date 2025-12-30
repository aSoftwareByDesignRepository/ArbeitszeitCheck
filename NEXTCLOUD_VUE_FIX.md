# @nextcloud/vue Initialization Fix

## Problem

Die App zeigt folgende Fehler:
1. `[ERROR] @nextcloud/vue: The @nextcloud/vue library was used without setting / replacing the appName.`
2. `[ERROR] @nextcloud/vue: The @nextcloud/vue library was used without setting / replacing the appVersion.`
3. `Uncaught TypeError: Cannot read properties of undefined (reading 'extend')`

## Ursache

- Wir verwenden **Vue 3** (`vue: ^3.3.0`)
- Aber **@nextcloud/vue v8** (`@nextcloud/vue: ^8.0.0`) ist für **Vue 2** gedacht
- `@nextcloud/vue` v8 hat keine `setAppName()`/`setAppVersion()` Funktionen
- Die Komponenten versuchen Vue 2 APIs zu verwenden (`Vue.extend`), die in Vue 3 nicht existieren

## Lösung

### Kurzfristig (aktuell implementiert)

Wir setzen die App-Informationen direkt auf `window`, bevor Komponenten geladen werden:

```javascript
// In main.js, VOR allen Imports von @nextcloud/vue Komponenten
if (typeof window !== 'undefined') {
	window.__NC_APP_NAME__ = 'arbeitszeitcheck'
	window.__NC_APP_VERSION__ = '1.0.0'
}
```

### Langfristig (empfohlen)

**Option 1: Upgrade auf @nextcloud/vue v9+ (für Vue 3)**
```json
{
  "dependencies": {
    "@nextcloud/vue": "^9.0.0"
  }
}
```

Dann können wir `setAppName()` und `setAppVersion()` verwenden:
```javascript
import { setAppName, setAppVersion } from '@nextcloud/vue'
setAppName('arbeitszeitcheck')
setAppVersion('1.0.0')
```

**Option 2: Downgrade auf Vue 2**
- Nicht empfohlen, da Vue 2 EOL ist
- Würde erfordern, alle Komponenten zu migrieren

## Status

✅ **Kurzfristige Lösung implementiert**
⚠️ **Langfristig: Upgrade auf @nextcloud/vue v9+ empfohlen**

## Nächste Schritte

1. Testen ob die Warnungen verschwinden
2. Wenn nicht: Prüfen ob `@nextcloud/vue` v8 mit Vue 3 überhaupt kompatibel ist
3. Falls nicht kompatibel: Upgrade auf v9+ oder Wechsel zu Vue 2

## Referenzen

- [@nextcloud/vue GitHub](https://github.com/nextcloud/nextcloud-vue)
- [Vue 3 Migration Guide](https://v3-migration.vuejs.org/)
- [Nextcloud Vue 3 Support Discussion](https://help.nextcloud.com/t/desperately-missing-vue-3-support/215118)
