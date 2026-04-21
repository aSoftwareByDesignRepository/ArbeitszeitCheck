## [Unreleased]

## 1.2.1 – 2026-04-21

### Behoben

- **Wiederzugriff auf pausierte Einträge**: Pausierte Einträge sind im Bearbeiten-/Löschen-Workflow wieder erreichbar und werden beim Speichern mit Endzeit konsistent als `completed` finalisiert.
- **Fortsetzen statt Duplikat bei gleichem Tag**: `Clock In` setzt einen pausierten Tages-Eintrag fort, statt einen neuen automatischen Eintrag zu erzeugen; die Pausenlücke wird korrekt als Break-Historie archiviert.
- **Historische Restfälle bei `paused`**: Neue Migration `Version1020Date20260421000000` repariert verbleibende verwaiste `paused`-Datensätze (auch Fälle außerhalb der früheren Einmal-Migration).

### Hinzugefügt

- **Auto-Fallback mit Nachvollziehbarkeit**: Zeiteinträge speichern jetzt `ended_reason` und `policy_applied` (z. B. `manual_clock_out` oder `auto_break_fallback`) für klare Audit-/Export-Nachweise.
- **Einmalige Nutzerinfo nach Auto-Ausstempeln**: Beim nächsten Statusabruf wird eine neutrale, konkrete Meldung mit Uhrzeit und Regel eingeblendet.
- **Urlaubsanspruch-Policy-Engine**: Neue berechnungslogikbasierte Anspruchsermittlung mit Modi `manual_fixed`, `model_based_simple`, `tariff_rule_based` und `manual_exception` inkl. Simulations-Endpunkt für Admins.
- **Tarifregel-Datenmodell und APIs**: Versionierte Tarif-Regelwerke/Module sowie Admin-Endpunkte zum Erstellen, Aktualisieren, Aktivieren, Stilllegen und Zuweisen von Urlaubs-Policies.
- **Snapshots der Anspruchsberechnung**: Persistente Snapshots (`at_entitlement_snapshots`) mit Berechnungstrace/Policy-Fingerprint für Nachvollziehbarkeit und Diagnose.
- **Neue Admin-Seite „Benachrichtigungen“**: Eigene Oberfläche für HR-Empfänger und Ereignis-Matrix inkl. dedizierter Notifications-API.

### Geändert

- **Fallback-Logik differenziert nach Einsatzart**: Für Schichtarbeit gilt standardmäßig eine strikte Fallback-Regel; für Nicht-Schichtmodelle eine flexible Regel mit tagsüber konfigurierbarem Ruhefenster (z. B. Familien-/Mittagsunterbrechung ohne Auto-Ausstempeln).
- **Export-Transparenz**: CSV/JSON-Zeilen enthalten jetzt `ended_reason` und `policy_applied`, damit automatische Beendigungen in Reports eindeutig erkennbar sind.
- **Urlaubsallokation integriert**: Jahresallokation nutzt nun die neue `VacationEntitlementEngine` und liefert Quelle/Regelwerk/Trace in der Ergebnisstruktur zurück.
- **Migrations-Kompatibilität**: Bestehende Urlaubswerte aus Nutzer-Modellzuweisungen werden in Policy-Zuordnungen überführt (`Version1018Date20260420123000`), damit Bestandsinstallationen konsistent weiterlaufen.
- **Admin-Einstellungsfluss für Abwesenheiten**: Carryover-/Rollover-, Vertretungs- und E-Mail-Schalter sind zentral über die neue Notifications-Seite/API steuerbar.
- **Schema Arbeitszeitmodelle**: `at_models` enthält jetzt `work_days_per_week` (`Version1019Date20260420150000`) als Grundlage für Formeln.

### Behoben

- **Aufräumen bei Nutzerlöschung**: Beim Entfernen eines Nutzers werden jetzt auch Urlaubs-Policy-Zuordnungen und Entitlement-Snapshots gelöscht (keine verwaisten Policy-/Berechnungsdaten).

## 1.2.0 – 2026-04-15

### Behoben

- **Zeitzonen-Konsistenz (Europe/Berlin)**: Server-/PHP-Zeitzone für ArbeitszeitCheck auf Deutschland ausgerichtet; neue Migration `Version1015Date20260415120000` konvertiert bestehende UTC-DATETIME-Werte in App-Tabellen nach `Europe/Berlin` und setzt `app_timezone` explizit.
- **Ausstempeln-Semantik korrigiert**: `clockOut()` finalisiert Einträge nun zuverlässig mit `end_time` und `status=completed` (statt `paused` ohne Endzeit). Dadurch sind Exporte/Reports wieder vollständig und konsistent.
- **Historische Pausiert-Einträge repariert**: Migration schließt verwaiste Einträge mit `status=paused` und `end_time IS NULL` automatisch über `end_time = updated_at` und Statuswechsel auf `completed`.
- **Mehrfach-Pausen ohne Datenverlust**: Beim Start einer weiteren Pause wird die zuvor abgeschlossene Pause zuerst in `breaks` (JSON) archiviert; Break-Dauern bleiben vollständig für ArbZG-Prüfungen erhalten.
- **Break-Status-Berechnung korrigiert**: `getBreakStatus()` zählt aktive Sitzungszeit nicht mehr doppelt; Warnstufen und Restpausen-Hinweise sind wieder korrekt.
- **Export-Spalten korrigiert**: `duration_hours` liefert jetzt Brutto-Dauer (Wall-Clock), `working_hours` Netto-Arbeitszeit (abzgl. Pausen). Vorher waren beide Spalten identisch.

### Geändert

- **Export-Transparenz**: CSV/JSON-Exporte enthalten jetzt explizite Zeitzonen-Metadaten (`timezone`, `exported_at`), damit nachgelagerte Systeme die Uhrzeiten eindeutig interpretieren.
- **UI-Klarheit**: Dashboard zeigt sichtbaren Zeitzonen-Hinweis (`Europe/Berlin (MEZ/MESZ)`), Export-Hinweis auf der Zeiteintragsseite nennt die verwendete Zeitzone.
- **Bediensicherheit**: Vor `Clock Out` erscheint eine Bestätigungsabfrage mit klarer Abgrenzung zwischen „Pause starten“ und „Ausstempeln“.
- **Admin-Transparenz**: In den Admin-Einstellungen wird die konfigurierte Zeitzone sichtbar angezeigt.

## 1.1.14 – 2026-04-14

### Behoben

- **Genehmigungs-Deadlock (App-Teams)**: Abwesenheiten und Zeiteintrags-Korrekturen behandeln „hat Kolleg:innen“ nicht mehr wie „hat eine:n Vorgesetzte:n“. Auto-Genehmigung, wenn **kein zuweisbarer Genehmiger** existiert, folgt `TeamResolverService::hasAssignableManagerForEmployee()` (explizite Team-Manager bei App-Teams; Legacy-Gruppenmodus weiterhin Kollegen-Proxy). Verhindert Anträge, die dauerhaft auf Managerfreigabe warten, obwohl niemand freigeben darf.
- **Zeiteintrags-Korrekturen**: Gleiche Zuweisbarkeitsregel wie bei Abwesenheiten (zuvor nur Kollegen-IDs).
- **Admin-Users API auf `/index.php`-Instanzen**: Refresh/Edit/History/Update nutzen nun zuverlässig aufgelöste App-URLs; fehlerhafte Requests wie `search=[object PointerEvent]` treten nicht mehr auf.
- **Admin-Teams und Settings auf Rewrite-losen Setups**: Zentrale URL-Auflösung enthält jetzt einen robusten `/index.php`-Fallback, wenn `OC.generateUrl()` im Seitenkontext fehlt oder unvollständig ist.

### Hinzugefügt

- **Repair-Schritt** `ReleaseStuckPendingAbsences`: setzt nach Migration verbliebene `pending`-Abwesenheiten unter derselben Bedingung automatisch auf genehmigt (idempotent).
- **Frontend-URL-Sicherheitsleitplanken**: Die gemeinsame AJAX-Schicht blockiert externe Cross-Origin-Calls standardmäßig (explizit `allowExternal: true` nötig); Unit-Tests decken URL-Normalisierung und External-Handling ab.
- **Lint-Leitplanken**: ESLint-Regeln verhindern neue rohe `fetch('/apps/arbeitszeitcheck/...')`-Aufrufe und implizite externe `fetch(...)`-Nutzung außerhalb der vorgesehenen Abstraktionen.

### Geändert

- **UX**: Abwesenheiten zeigen einen Hinweis, wenn App-Teams aktiv sind und kein Genehmiger zugeordnet ist; in der Detailansicht erscheint bei veralteten hängenden Anträgen ein Warnhinweis (bis Repair/Admin die Teamkonfiguration korrigiert).
- **Frontend-Architektur**: `ArbeitszeitCheckUtils` stellt nun zentral `getRequestToken()`, `resolveUrl()` und `isExternalUrl()` bereit; genutzt u. a. in `admin-users`, `reports`, `settings` und `validation`.
- **Mobile UX Konsistenz (WCAG 2.1 AA)**: iPhone-Safe-Area-konforme Abstände, bessere Touch-Targets, klarere Abschnittsstruktur und visuelle Hierarchie für Nutzerseiten (`dashboard`, `time-entries`, `absences`) sowie Managerseiten (`manager-dashboard`, `manager-time-entries`, Mitarbeiter-Abwesenheiten).

### Dokumentation

- Nutzerhandbücher (EN/DE), `tests/WORKFLOW_ROLE_MATRIX.md` und Entwicklerdokumentation zur Semantik „zuweisbarer Manager“ und zum Repair-Schritt ergänzt.
- README und Entwicklerdokumentation um zentrale Frontend-URL-Policy, striktes External-Call-Verhalten und Mobile/iOS-Layout-Hinweise ergänzt.

## 1.1.13 – 2026-04-13

### Hinzugefügt

- **Monatsabschluss: Karenz und Auto-Finalisierung**: Admin-Einstellung `month_closure_grace_days_after_eom` (0–90, Standard 0). Nach Monatsende haben Mitarbeitende so viele Kalendertage zur manuellen Finalisierung; ist der Monat danach noch offen, finalisiert ein täglicher Hintergrundauftrag automatisch (gleicher Snapshot wie manuell). Ausstehende Zeiteintragsfreigaben und offene Abwesenheits-Workflows blockieren die Auto-Finalisierung. Wiederöffnen bleibt Administrator:innen vorbehalten.
- **App-Admin-Whitelist**: Neue Admin-Einstellung `app_admin_user_ids`, um die Administration von ArbeitszeitCheck auf eine ausgewählte Teilmenge der Nextcloud-Admins zu begrenzen. Leere Auswahl bleibt rückwärtskompatibel (alle Nextcloud-Admins dürfen die App verwalten).
- **Docker-Testziel für Security-Role-Gating**: Verdrahtung von `scripts/test-security-role-gating-docker.sh` über `make test-security-role-gating-docker` und `composer test:security-role-gating:docker` für schnelle Autorisierungs-Regressionstests im Container-Setup.

### Geändert

- **Monatsabschluss UX/API**: Klarere Karten-UI, sichtbares Erfolgs-/Fehlerfeedback (WCAG), serverseitiges `canFinalize` mit lokalisierten Sperrgründen; manuelle Finalisierung lehnt zukünftige Kalendermonate ab; Abwesenheits-Workflow (`pending`, `substitute_pending`, `substitute_declined`) zusätzlich zu ausstehenden Zeiteintragskorrekturen; API 401 bei fehlender Anmeldung wo passend; Admin: eigener Abschnitt „Monatsabschluss“; Karenzfeld bleibt editierbar mit Hinweis, dass der Wert gespeichert wird und bei aktivierter Funktion gilt; Wiederöffnen mit durchsuchbarer Mitarbeitenden-Auswahl und klarerer Rollenbeschreibung; Validierungsfehler mit höherem Kontrast über Themes hinweg. Auto-Finalize protokolliert Einzelfehler.
- **Release-/Signatur-Workflow für Integritätsprüfung gehärtet**: `make release-signed` signiert jetzt den entpackten Release-Archivinhalt (nicht den lokalen Entwicklungs-Checkout), prüft verbotene Entwicklungs-Pfade und packt das signierte Archiv für Deployment/App-Store neu.
- **Admin-Autorisierung zentral erzwungen**: Zugriffe auf `AdminController`-Routen werden jetzt per Middleware auf App-Admin-Rechte geprüft; nicht berechtigte angemeldete Nutzer erhalten eine konsistente 403-Seite.

### Dokumentation

- **Deployment-Hinweise ergänzt**: Die Release-Dokumentation fordert nun explizit das Deployment aus dem signierten Tarball und beschreibt das typische Fehlerbild (`.git/*` / `node_modules/*`) bei versehentlicher Signierung eines Dev-Trees.
- **Deployment-Helferskript**: `release/deploy-from-release.sh` hinzugefügt für Deployment aus signierten Release-Archiven mit Sicherheitsprüfungen (verbotene Pfade, erforderliche `signature.json`, optionales Disable/Enable und `occ integrity:check-app`).
- **Admin-Betrieb**: Nutzer-/Entwicklerdokumentation ergänzt um Einrichtung der App-Admin-Whitelist, Rückfallverhalten bei leerer Auswahl und Verifikation des Role-Gatings im Docker-Testlauf.

## 1.1.12 – 2026-04-09

### Hinzugefügt

- **Revisionssichere Monatsfinalisierung (optional)**: Admin-Schalter `month_closure_enabled` (Standard aus). Mitarbeitende können einen vollen Kalendermonat finalisieren; die App speichert kanonischen JSON-Snapshot, SHA-256-Hashkette, Anhänge-Revisionen, Audit-Ereignisse und ein schlankes PDF. Finalisierte Monate sind über normale App-APIs nicht mehr änderbar; Administrator:innen können einen Monat mit Pflichtbegründung wieder öffnen (Audit). Monatsberichte für finalisierte Monate lesen den gespeicherten Snapshot. Datenbank: `at_month_closure`, `at_month_closure_revision` (Migration `Version1014Date20260409120000`).

### Dokumentation

- Nutzerhandbücher (DE/EN), Entwicklerdokumentation und Compliance-Hinweise zu Monatsabschluss, Aufbewahrung und Grenzen (Nachweis in der App, keine QES) ergänzt.

## 1.1.11 – 2026-04-09

### Hinzugefügt

- **Manager-Ansicht „Mitarbeiter-Abwesenheiten“**: Neue In-App-Seite und API für Manager/Admins zur Einsicht von Abwesenheiten mit sicherer Bereichsfilterung, Pagination und lokalisierten Statusbezeichnungen.
- **Kopierfunktion für Arbeitszeitmodelle**: Neue Kopieraktion mit Modal-UX, eindeutiger Namensvorschlag-Logik und Schutz gegen Doppelklicks.

### Geändert

- **Manager-Navigation / Sidebar**: Struktur in klarere Manager-/Admin-Untermenüs überführt; Berichte in den Manager-Kontext verschoben; Compliance-Link zur besseren Übersicht umgruppiert.
- **UX Mitarbeiter-Zeiteinträge (Manager)**: Standard-Datumswerte sowie Datums-/Übersetzungsdarstellung im Filterfluss verbessert.
- **Kalender-Verhalten (Rollback-Bereinigung)**: Angefangene Funktionalität für direkte Kalendereinträge sowie zugehörige Admin-Optionen/Status/Test-Endpunkte wurde entfernt. Das unterstützte Verhalten bleibt unverändert: keine Synchronisation mit der Nextcloud-Kalender-App; optionale `.ics`-Anhänge werden weiterhin per E-Mail in den konfigurierten Abwesenheits-Workflows versendet.

### Behoben

- **Arbeitszeitmodell-Modaldialoge**: Interaktionsprobleme im Kopier-Modal, Darstellung des Quellmodells sowie Lokalisierung/Formatierung im Lösch-Dialog korrigiert.
- **Abwesenheits-iCal-Härtung**: Strengere Status-/Datumsprüfungen, Empfänger-Deduplizierung und datenschutzärmere Beschreibungen für Vertretung/Manager ergänzt.

### Dokumentation

- Nutzerhandbücher und Changelogs an das finale Kalenderverhalten (optionale `.ics`-Mail, keine direkte Kalender-App-Synchronisation) sowie die aktuelle Manager/Admin-UX-Struktur angepasst.

## 1.1.10 – 2026-04-07

### Hinzugefügt

- **Urlaubsübertrag / Rollover**: `VacationRolloverService`, Hintergrundauftrag, `occ arbeitszeitcheck:vacation-rollover`, Migration `Version1013Date20260407120000` mit `at_vacation_rollover_log`; Unit-Tests.

### Geändert

- **Frontend-L10n**: Gemeinsame Partials `templates/common/main-ui-l10n.php` und `teams-l10n.php`, damit Übersetzungen früh verfügbar sind; zugehörige Template- und JS-Anpassungen.

### Behoben

- **Manager-Dashboard — ausstehende Abwesenheiten**: Die API liefert `summary.typeLabel` (serverseitig übersetzter Abwesenheitstyp); die Oberfläche nutzt das bevorzugt, damit Karten lokalisierte Bezeichnungen zeigen (z. B. *Urlaub*) statt Rohcodes wie `vacation`.

### Dokumentation

- `docs/Developer-Documentation.en.md`: API-Hinweis zu `typeLabel` bei Pending Approvals; Nutzerhandbücher EN/DE: Hinweis zu lokalisierten Abwesenheitstypen bei ausstehenden Genehmigungen.

## 1.1.9 – 2026-04-05

### Entfernt

- **Nextcloud-Kalender-App (CalDAV)**: Synchronisation von Abwesenheiten in die Kalender-App ist entfernt; Migration `Version1012Date20260406120000` entfernt die Tabelle `at_absence_calendar`. Bereits angelegte Kalender in der Kalender-App bleiben bestehen, bis Nutzer sie dort löschen.

### Geändert

- **Feiertage / Kalenderlogik**: In der Klasse `HolidayService` gebündelt.

### Behoben

- **AdminController**: Doppelte `use`-Anweisung für `HolidayService` führte zu einem PHP-Fatal (u. a. beim Laden durch PHPUnit).

### Dokumentation

- Nutzerhandbücher EN/DE (`docs/User-Manual.*`), README- und Entwicklerdokumentation aktualisiert; Hilfsskript `docker/run-app-phpunit.sh` für PHPUnit im Container.

## 1.1.7 – 2026-04-05

### Hinzugefügt

- **Resturlaub / Urlaubsübertrag**: Pro Nutzer und Kalenderjahr Eröffnungsbestand `carryover_days` in `at_vacation_year_balance`; globale Admin-Einstellung für Ablauf des Vorjahresurlaubs (Monat/Tag, Standard 31.03.). `VacationAllocationService` wendet FIFO auf genehmigten Urlaub an (nach `start_date`, dann `id`) und teilt Arbeitstage vor/nach Ablauf, sodass Resturlaub zuerst verbraucht wird, solange er gültig ist.
- **Validierung & Freigaben**: Urlaubsanträge werden bei Manager-Freigabe (und bei Auto-Approve) erneut geprüft, damit parallele Anträge nach Genehmigung das Kontingent nicht überziehen.
- **API & UI**: `AbsenceController::stats` liefert Anspruch, Übertrag, Summen und ablaufbezogene Felder; Dashboard und Abwesenheiten zeigen eine klare Urlaubsübersicht; Admin-Einstellungen enthalten Ablauffelder.
- **DSGVO**: `UserDeletedListener` löscht Urlaubs-Jahresbestände bei Kontolöschung.
- **Migration / Massenpflege**: `occ arbeitszeitcheck:import-vacation-balance` importiert CSV `user_id,year,carryover_days` mit `--dry-run`.

### Tests

- Unit-Tests für `VacationAllocationService`; erweiterte Tests für `AbsenceService` und zugehörige Controller.

## 1.1.6 – 2026-03-27

### Hinzugefügt

- **Entwicklung**: CLI `occ arbeitszeitcheck:generate-test-data` für deterministische Demo-Daten (Zeiteinträge, Abwesenheiten, optional Verstöße, Demo-App-Team) zum Testen von UI, Berichten und Workflows.
- **Exporte**: `TimeEntryExportTransformer` bündelt Feldzuordnung und CSV-Aufbereitung für Zeiteintrags-Exporte; `ExportController` delegiert daran für eine einheitliche, testbare Pipeline.

### Behoben

- **Berichte-UI**: Berichtstyp-Karten werden bei teambezogenem Scope nicht mehr fälschlich deaktiviert.
- **Berichte (Tests)**: CSV-Download-Test nutzt `DataDownloadResponse::render()` für den Dateiinhalt.
- **Team-Berichte**: Nutzer-IDs werden vor Berechtigungsprüfung und Aggregation dedupliziert (keine Doppelzählung bei Mehrfach-Teams).
- **Abwesenheits-Badges**: Besser lesbare, theme-sichere Kontraste für Urlaub / Krank / Homeoffice / Sonstiges.

### Geändert

- **Kompatibilität (Dev)**: Lokale Entwicklungsumgebungen an Nextcloud 33.x ausgerichtet (z. B. offizielles `nextcloud`-Docker-Image).
- **Berichte-Layout**: Zu aggressive Vollbreiten-Regel für das Parameterformular zurückgenommen (verbessert Scroll/Layout).
- **Berichte-UI**: Anpassungen an Templates, JavaScript und Styles auf der Berichtsseite; Admin-Einstellungen mit zugehörigem Hook.
- **Reporting**: Anpassungen in `ReportController` und `ReportingService` passend zum Export-Refactoring.

### Tests

- Unit-Tests für `TimeEntryExportTransformer`; erweiterte `ReportController`-Tests; `ExportController`-Tests an neue Verdrahtung angepasst.

## 1.1.4 – 2026-03-25

### Behoben
- **Routing/Kompatibilität**: `indexApi()`-Kompatibilitätsaliases für Legacy-Endpunkte ergänzt, um 500-Fehler in den Nextcloud-Logs zu verhindern.
- **PHP-Fatals**: Konstruktor-Signaturprobleme in `AbsenceService` und `ComplianceService` behoben (konnte die App beim Laden von Services oder beim Speichern von Einstellungen zum Absturz bringen).
- **Reports-Sicherheit**: Vorschau-Endpunkte gehärtet (`start <= end` Validierung + maximale Zeitraumbegrenzung) um DoS-Risiken durch untrusted Parameter zu reduzieren.
- **Admin-“Gesamte Organisation”**: Admin-Organisation-Scope korrekt verarbeitet (`userId=""` = alle aktivierten Nutzer) inklusive passender Zugriffsprüfung, damit Preview/Download konsistent bleiben.
- **Reports-Rendering**: Preview-Darstellung für **Abwesenheiten** und **Compliance** verbessert, sodass sie zur tatsächlichen Ergebnisstruktur passt.

### Geändert
- **Reports-UI-Semantik**: Team-Scope auf Team-Overview-/Export-Semantik eingeschränkt (verhindert irreführende Preview/Downloads).
- **Organisation-Download Hinweis**: UI-Hinweis ergänzt, dass Organisation-Download erst vollständig unterstützt ist, sobald dedizierte Organization-Export-Endpunkte verfügbar sind.

## 1.1.3 – 2025-03-14
### Behoben
- **ArbZG-Compliance**: Pausenprüfung korrigiert (9h/45min-Zweig erreichbar; Prüfung ≥9h vor ≥6h)
- **Manager-Logik**: `employeeHasManager()` nutzt nun `getManagerIdsForEmployee()` statt `getColleagueIds()`
- **Berichte**: `getTeamHoursSummary()` berücksichtigt Periodenparameter (Woche/Monat)
- **Admin-Benutzer**: `hasTimeEntriesToday` pro Benutzer statt systemweit
- **UserSettingsMapper**: Falsy-Null/Leerstring-Behandlung in getIntegerSetting, getFloatSetting, getStringSetting
- **Routing**: exportUsers-Route vor getUser verschoben (Shadowing behoben)
- **Version1009-Migration**: MySQL-Backticks durch portablen QueryBuilder ersetzt; OCP\DB\Types
- **Doppelte Notifier-Registrierung**: Aus Application.php boot() entfernt
- **API-Sicherheit**: Generische Fehlermeldungen statt roher Exceptions (SubstituteController, GdprController)
- **PDF-Export**: HTTP 422 mit klarer Meldung statt stillem CSV-Fallback
- **LIKE-Injection**: WorkingTimeModelMapper::searchByName() verwendet escapeLikeParameter()
- **XSS**: Modal-Titel in components.js escaped; compliance-violations.js innerHTML escaped
- **Admin-Einstellungen**: CSRF-requesttoken ergänzt
- **AbsenceService DI**: Konstruktorargument-Reihenfolge (IDBConnection) korrigiert
- Admin-Feiertage und -Einstellungen: englische Quellstrings für l10n
- UserDeletedListener: TeamMemberMapper und TeamManagerMapper per Injection
- XSS: Team-Namen in admin-teams.js bereinigt

### Geändert
- **CSS**: Shadow-Light-Variable, scopierte Resets, Dark-Mode color-mix, semantische Farben, Navigationshöhe/z-index
- **Uhr-Buttons**: Doppel-Submit-Guard (deaktiviert während API-Aufrufen)
- **initTimeline()**: Max-Retry (20) gegen Endlosschleife
- **Barrierefreiheit**: aria-label auf Header-Buttons, Label für Admin-Suche, aria-modal im Willkommens-Dialog, englische l10n-Keys in Navigation
- **Dokumentation**: Interne Docs entfernt; docs/README ergänzt; Repo-URLs korrigiert
- **Manager-Dashboard**: l10n von PHP an JS übergeben für Übersetzungen
- Constants.php; benutzerfreundliche Fehlermeldungen
- **Zeiteintrags-Export**: Optional (per Admin-Einstellung) können Einträge, die über Mitternacht laufen, im CSV/JSON-Export rein darstellungsbezogen in zwei Kalendertage segmentiert werden (vor/nach 00:00). Die zugrundeliegende Arbeitszeit- und ArbZG-Compliance-Berechnung bleibt unverändert auf Basis des originalen, unsplitteten Zeiteintrags.

### Hinzugefügt
- **Version1010-Migration**: Zusammengesetzte Indizes auf at_entries, at_violations, at_holidays, at_absences

## 1.1.2 – 2025-03-07
### Geändert
- Langfristiges Refactoring: Ersetzung aller `\OC::$server`-Verwendungen durch OCP-APIs und Konstruktor-Injection
- CSPService: ContentSecurityPolicyNonceManager per Konstruktor injiziert
- Controller: manuelles cspNonce entfernt (configureCSP übernimmt dies); IURLGenerator und IConfig injiziert, wo nötig
- PageController: IURLGenerator und IConfig injiziert; übergibt urlGenerator an Templates
- HealthController: IDBConnection für Datenbank-Check injiziert
- ProjectCheckIntegrationService: LoggerInterface statt OC::$server->getLogger() injiziert
- Templates: `\OC::$server` durch `\OCP\Server::get()` (öffentliche OCP-API) ersetzt
- GitHub-Actions-Release-Workflow hinzugefügt (`.github/workflows/release.yml`)
- PageControllerTest mit vollständigen Konstruktor-Mocks aktualisiert

## 1.1.1 – 2025-01-07
### Behoben
- Doppelte Routen-Namen in der Abwesenheits-API behoben (absence#store, absence#show, absence#update, absence#delete)
- Klassen-Namen der Settings in info.xml korrigiert, um den vollständigen OCA-Namespace zu verwenden
- `declare(strict_types=1)` zu routes.php hinzugefügt

### Geändert
- Nicht vorhandene Screenshot-Referenzen aus info.xml entfernt, bis echte Screenshots verfügbar sind

## 1.1.0 – 2025-01-04
### Hinzugefügt
- ProjectCheck-Integration für Projektzeiterfassung
- Zusätzliche Migrationen für Schema-Updates

## 1.0.3 – 2025-01-03
### Hinzugefügt
- Weitere Verfeinerungen des Datenbankschemas

## 1.0.2 – 2025-01-02
### Hinzugefügt
- Arbeitszeitmodelle
- Zuweisung von Arbeitszeitmodellen zu Nutzern

## 1.0.1 – 2025-01-01
### Hinzugefügt
- Abwesenheitsverwaltung
- Audit-Logging
- Benutzer-Einstellungen
- Tracking von Compliance-Verstößen

## 1.0.0 – 2024-12-29
### Hinzugefügt
- Erste Veröffentlichung
- Arbeitszeiterfassung gemäß deutschem Arbeitszeitgesetz (ArbZG)
- Kommen-/Gehen- und Pausen-Erfassung
- Verwaltung von Zeiteinträgen (Erstellen, Bearbeiten, Löschen, manuelle Einträge)
- Grundlegende Compliance-Prüfungen (max. 8h/Tag, Pausenanforderungen)
- DSGVO-konforme Datenverarbeitung
- Deutsche und englische Übersetzungen
- WCAG-2.1-AAA-Accessibility-Compliance

