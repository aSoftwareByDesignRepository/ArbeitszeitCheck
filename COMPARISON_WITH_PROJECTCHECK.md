# Vergleich: ArbeitszeitCheck vs. ProjectCheck

## Hauptunterschiede

### ProjectCheck (Einfacher)
- ✅ **KEIN Vue.js** - verwendet nur Vanilla JavaScript
- ✅ **Eigene Webpack-Config** - vollständige Kontrolle
- ✅ **Keine Vue-Komponenten** - nur JavaScript-Dateien
- ✅ **Kein Vue Router** - einfache Navigation
- ✅ **Keine Vue Feature Flags** - keine Probleme damit
- ✅ **Einfachere Struktur** - weniger Abhängigkeiten

### ArbeitszeitCheck (Komplexer)
- ❌ **Vue.js 3** - zusätzliche Komplexität
- ❌ **@nextcloud/webpack-vue-config** - weniger Kontrolle
- ❌ **Vue Single File Components (.vue)** - müssen kompiliert werden
- ❌ **Vue Router** - zusätzliche Abhängigkeit
- ❌ **Vue Feature Flags** - müssen definiert werden
- ❌ **Mehr Abhängigkeiten** - vue, vue-router, @nextcloud/vue

## Warum ist ArbeitszeitCheck komplizierter?

### 1. **Vue.js Framework**
ProjectCheck verwendet **Vanilla JavaScript**, während ArbeitszeitCheck **Vue.js 3** verwendet. Das bedeutet:
- Vue-Komponenten müssen kompiliert werden
- Vue Router muss konfiguriert werden
- Vue Feature Flags müssen definiert werden
- Mehr Build-Komplexität

### 2. **Webpack-Config**
- **ProjectCheck**: Eigene, vollständig kontrollierte Webpack-Config
- **ArbeitszeitCheck**: Verwendet `@nextcloud/webpack-vue-config`, was weniger Kontrolle gibt

### 3. **Build-Prozess**
- **ProjectCheck**: Einfacher Babel-Transform, keine Vue-Kompilierung
- **ArbeitszeitCheck**: Vue-Templates müssen kompiliert werden → mehr Speicherbedarf

### 4. **Feature Flags**
- **ProjectCheck**: Keine Feature Flags nötig
- **ArbeitszeitCheck**: Vue Feature Flags müssen definiert werden, sonst Warnungen

## Könnte ArbeitszeitCheck einfacher sein?

### Option 1: Auf Vanilla JavaScript umstellen (wie ProjectCheck)
**Vorteile:**
- ✅ Keine Vue-Komplexität
- ✅ Einfacherer Build
- ✅ Weniger Abhängigkeiten
- ✅ Keine Feature Flags Probleme

**Nachteile:**
- ❌ Müssen alle Vue-Komponenten neu schreiben
- ❌ Mehr Code für State Management
- ❌ Weniger moderne Entwicklung

### Option 2: Vue.js beibehalten, aber Webpack-Config vereinfachen
**Vorteile:**
- ✅ Behält Vue.js Vorteile
- ✅ Mehr Kontrolle über Build
- ✅ Kann Feature Flags direkt definieren

**Nachteile:**
- ❌ Müssen eigene Webpack-Config schreiben
- ❌ Mehr Wartungsaufwand

### Option 3: Aktuelle Lösung optimieren
**Was wir tun können:**
- ✅ Feature Flags richtig definieren (bereits gemacht)
- ✅ Build-Prozess optimieren
- ✅ Speicher-Bedarf reduzieren

## Empfehlung

**Für jetzt:** Behalte Vue.js, aber optimiere die Webpack-Config:
1. Eigene Webpack-Config schreiben (wie ProjectCheck)
2. Vue Feature Flags direkt definieren
3. Build-Prozess vereinfachen

**Für später:** Wenn die App stabil ist, könnten wir überlegen, ob Vue.js wirklich nötig ist oder ob Vanilla JavaScript ausreicht.

## Aktuelle Probleme

Die Hauptprobleme kommen von:
1. **@nextcloud/webpack-vue-config** - gibt nicht genug Kontrolle
2. **Vue Feature Flags** - müssen richtig definiert werden
3. **Build-Speicher** - Vue-Kompilierung braucht viel RAM

## Lösung

Die Webpack-Config sollte ähnlich wie ProjectCheck sein, aber mit Vue-Support:
- Eigene Config statt @nextcloud/webpack-vue-config
- Direkte Feature Flags Definition
- Bessere Kontrolle über den Build-Prozess
