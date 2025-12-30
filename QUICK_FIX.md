# Quick Fix: App zeigt nichts an

## Problem
Die App zeigt nichts an, weil die Frontend-Assets nicht gebaut wurden.

## Lösung mit dev-setup.sh

### Option 1: Automatisches Build-Skript (empfohlen)

```bash
# Im Projekt-Root ausführen
cd apps/arbeitszeitcheck
./build-docker.sh
```

### Option 2: Manuell mit docker-compose

```bash
# 1. Dependencies installieren
docker-compose exec nextcloud bash -c "cd /var/www/html/apps/arbeitszeitcheck && npm install"

# 2. Frontend bauen
docker-compose exec nextcloud bash -c "cd /var/www/html/apps/arbeitszeitcheck && npm run build:dev"

# 3. Cache leeren
./dev-setup.sh occ files:scan --all
```

### Option 3: Manuell im Container

```bash
# 1. In Container wechseln
docker-compose exec nextcloud bash

# 2. In App-Verzeichnis wechseln
cd /var/www/html/apps/arbeitszeitcheck

# 3. Dependencies installieren
npm install

# 4. Frontend bauen
npm run build:dev

# 5. Cache leeren
php occ files:scan --all

# 6. Container verlassen
exit
```

## Nach dem Build

1. Browser-Cache leeren (Ctrl+Shift+R)
2. App öffnen: http://localhost:8081/apps/arbeitszeitcheck/
3. Sollte jetzt funktionieren!

## Falls es immer noch nicht funktioniert

1. Browser-Konsole öffnen (F12)
2. Nach Fehlern suchen
3. Network-Tab prüfen auf 404-Fehler
4. Nextcloud-Logs prüfen: `docker exec -it <container> tail -f /var/www/html/data/nextcloud.log`
