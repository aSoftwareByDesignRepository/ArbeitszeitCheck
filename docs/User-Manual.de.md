# ArbeitszeitCheck — Kurzanleitung (Deutsch)

Diese Kurzanleitung richtet sich an **Endnutzerinnen und Endnutzer** sowie an **Führungskräfte** und ergänzt die in der App sichtbare Oberfläche. Sie liegt im Ordner `docs/` und kann intern weitergegeben werden.

**Rechtliches / DSGVO:** [GDPR-Compliance-Guide.en.md](GDPR-Compliance-Guide.en.md) (englisch, inhaltlich für Betrieb relevant). **Technische ArbZG-Umsetzung:** [Compliance-Implementation.de.md](Compliance-Implementation.de.md).

---

## 1. Was die App leistet

ArbeitszeitCheck erfasst **Arbeitszeiten**, prüft konfigurierbare **ArbZG-Regeln** (Pausen, Ruhezeiten, Höchstzeiten), verwaltet **Abwesenheiten** (Urlaub, Krankheit, …) inkl. Genehmigungen nach betrieblicher Ausgestaltung und bietet **Berichte und Exporte**.

Alle Daten verbleiben in Ihrer **Nextcloud-Instanz**.

---

## 2. Kalender: Ansicht in der App vs. Nextcloud-Kalender

| Thema | Was Sie erwarten können |
|--------|---------------------------|
| **Kalenderansicht in ArbeitszeitCheck** | Die App hat eine eigene **Monatskalender-Ansicht** (Arbeitszeiten und Abwesenheiten). Das ist **nicht** die separate App „Kalender“. |
| **Nextcloud-Kalender-App** | ArbeitszeitCheck **synchronisiert keine** Abwesenheiten in die Nextcloud-**Kalender**-App (kein CalDAV-Abgleich durch diese App). |
| **Alte Kalender** | Falls früher Kalender in der Kalender-App angelegt wurden, **bleiben** diese dort, bis Sie sie dort **manuell löschen**. Die App entfernt sie nicht automatisch. |
| **E-Mail mit `.ics`-Anhang** | In manchen Abläufen kann die App **E-Mails** mit einer iCalendar-Datei senden, damit Empfänger einen Termin **manuell** importieren können. Das ist optionale E-Mail, **keine** dauerhafte Zwei-Wege-Synchronisation. |

---

> Betriebshinweis: Direkte Einträge/Synchronisation in die Nextcloud-Kalender-App sind bewusst nicht aktiv. Für Kalendereinträge bitte den optionalen `.ics`-E-Mail-Workflow nutzen.

---

## 3. Rollen (typisch)

- **Mitarbeitende**: Zeiten erfassen, Abwesenheiten beantragen, eigene Daten einsehen.
- **Vertretung** (falls genutzt): Eindeckung für Abwesenheiten bestätigen oder ablehnen.
- **Führungskraft / Team**: Abwesenheiten genehmigen, Teamübersichten je nach Rechten.
- **Administratorin / Administrator**: Globale Einstellungen, Nutzer, Feiertage, Compliance-Optionen, Exporte.
- **App-Administrator:in (optionale Einschränkung)**: Ihre Organisation kann die ArbeitszeitCheck-Administration auf ausgewählte Nextcloud-Admins begrenzen. Ist das gesetzt, bleiben andere Nextcloud-Admins zwar Plattform-Admins, erhalten in den ArbeitszeitCheck-Adminseiten aber „Zugriff verweigert“.

Die genauen Rechte hängen von Nextcloud-Gruppen und der App-Konfiguration ab.

### Für Administrator:innen: App-Administrator:innen konfigurieren

1. Öffnen Sie **ArbeitszeitCheck → Admin-Einstellungen**.
2. Wählen Sie im Bereich **App-Administratoren (ArbeitszeitCheck)** die Nextcloud-Admin-Konten aus, die diese App verwalten dürfen.
3. Speichern Sie die Einstellungen.
4. Lassen Sie die Liste leer, wenn das rückwärtskompatible Standardverhalten gelten soll (**alle** Nextcloud-Admins dürfen ArbeitszeitCheck verwalten).

Im Auswahldialog erscheinen nur Konten aus der Nextcloud-`admin`-Gruppe.

### Für Administrator:innen: Benachrichtigungen und Urlaubs-Policy-Modell

- Öffnen Sie **ArbeitszeitCheck → Admin-Benachrichtigungen**, um abwesenheitsbezogene Mail-Regeln zentral zu pflegen.
- **HR-Office-Benachrichtigungen** lassen sich mit Empfängerliste plus Matrix je Abwesenheitstyp/Ereignis steuern (z. B. Antrag erstellt, Vertretung genehmigt, Manager genehmigt/abgelehnt, Mitarbeitende storniert/gekürzt).
- Auf derselben Seite liegen außerdem praxisrelevante Schalter für Resturlaubs-Ablaufdatum, optionales Carryover-Limit, Rollover-Verhalten, Vertretungspflicht je Abwesenheitstyp sowie iCal-/Workflow-Mailoptionen.
- Der Urlaubsanspruch kann pro Nutzer:in über Policy-Modi zugewiesen werden (manuell, modellbasiert, tarifregelbasiert, manuelle Ausnahme). Bei tariflicher Abbildung können Regelwerk-Versionen und Aktivierungsfenster über die Admin-APIs/Integrationsprozesse verwaltet werden.

---

## 4. Alltägliche Aufgaben

- **Kommen/Gehen und Pausen** über die Zeiterfassung; Korrekturen und Begründungen nach internen Regeln.
- **Abwesenheiten** beantragen und ggf. auf Freigabe warten. **Resturlaub** und Überträge werden angezeigt, wenn die Administration das gepflegt hat.
  - **App-Teams (empfohlene Einrichtung):** Wenn Ihre Organisation **App-Teams** nutzt und in der App **kein:e Vorgesetzte:r** für Ihr Team hinterlegt ist, werden Anträge **ohne** Vertretung beim Absenden **automatisch genehmigt**—es gäbe sonst niemanden mit Managerfreigabe. Mit **Vertretung** läuft zuerst der Vertretungs-Schritt. Die Oberfläche kann dazu einen kurzen Hinweis anzeigen.
  - **Älteres Gruppenmodell:** Verhalten folgt dem früheren „gleiche Gruppe“-Modell; die Administration sollte sicherstellen, dass Genehmigungen für Ihre Organisation weiterhin sinnvoll möglich sind.
- **Manager-Dashboard** (als Führungskraft): Unter **Ausstehende Genehmigungen** erscheint der **Abwesenheitstyp in Ihrer Sprache** (z. B. Urlaub, Krankheit), nicht technische Kurzbezeichnungen. Wo freigeschaltet, bietet **Abwesenheiten der Mitarbeitenden** eine eigene Listen-/Filteransicht.
- **Mobile Nutzung (Smartphone/Tablet):** Die Oberfläche ist responsiv optimiert (klarere Abschnittsabstände, größere Touch-Ziele, iPhone-Safe-Area-Berücksichtigung). Auf sehr kleinen Displays funktionieren Formulare meist am besten im Hochformat, breite Tabellen/Berichte im Querformat.
- **Berichte** für Zeiträume erstellen und erlaubte Exporte nutzen (CSV, DATEV, …).
- **Compliance-Hinweise** (z. B. fehlende Pausen) nach Vorgabe des Arbeitgebers bearbeiten.

---

## 5. Monatsnachweis (revisionssicher)

**Rechtlicher Hinweis (keine Rechtsberatung):** Nach deutschem Recht hat der Arbeitgeber Zeiten **aufzuzeichnen und aufzubewahren** (ArbZG / Rechtsprechung). Es gibt **keine** gesetzliche Vorgabe, dass eine Software einen bestimmten „Finalisieren“-Klick oder eine feste **Frist** dafür vorsieht—das ist **betrieblich** (Betriebsvereinbarung, Unternehmensregel, Lohnabrechnung). Die optionale Monats-Siegelung in dieser App ist ein **technischer Prüfnachweis** (Snapshot + Hash), keine gesetzliche Form selbst.

Wenn Ihre Administration die **revisionssichere Monatsfinalisierung** aktiviert hat, kann die Seite **Zeiteinträge** einen Bereich **Monatsnachweis** zeigen.

- Wählen Sie den **Kalendermonat** (Monat und Jahr in einer Liste, mit ausgeschriebenem Monatsnamen). Angezeigt werden **nur abgeschlossene Monate, in denen mindestens ein Zeiteintrag liegt** (leere Monate erscheinen nicht). Stellen Sie sicher, dass der Monat vollständig ist (inkl. Klärung **ausstehender Korrekturanträge**—eine Finalisierung ist blockiert, solange noch ein Antrag aussteht).
- **Monat finalisieren** legt einen **festen Snapshot** dieses Kalendermonats ab (Arbeitszeit und zugehörige Report-Summen gemäß App-Logik), einen **kryptografischen Hash** und ermöglicht den Download eines **PDFs** zur Ablage.
- **Karenz (falls konfiguriert):** Die Administration kann **Kalendertage nach Monatsende** festlegen, in denen der Monat noch **manuell** finalisiert werden soll; die Oberfläche kann eine Frist anzeigen. Bleibt der Monat nach Ende dieser Karenz **noch offen**, kann ein **täglicher Hintergrundauftrag** ihn automatisch versiegeln (**gleicher Snapshot** wie bei manueller Finalisierung). **Ausstehende** Korrekturanträge zu Zeiteinträgen oder **offene Abwesenheits-Workflows** (z. B. Genehmigung oder Vertretung) **verhindern** die automatische Versiegelung, bis sie geklärt sind.
- Nach der Finalisierung können Sie Zeiteinträge und Abwesenheiten in diesem Monat **nicht mehr** über die normale App ändern—eine **Administratorin / ein Administrator** kann einen Monat nur mit **dokumentierter Begründung** wieder öffnen (auditierbar).

Wird die Funktion später **deaktiviert**, bleiben **bereits finalisierte Monate gesperrt**.

**Administrator:innen – Wieder öffnen:** Eine Administratorin / ein Administrator kann einen finalisierten Monat **in der App** (Admin-Einstellungen) mit **Pflichtbegründung** (auditierbar) wieder öffnen, nicht nur über eine API.

Das ist ein **Integritätsnachweis in der App** (Hash + Audit), **keine** qualifizierte elektronische Signatur. Direkter Datenbankzugriff durch Server-Betrieb liegt außerhalb der App-Kontrolle—es gelten IT- und Aufbewahrungsvorgaben Ihrer Organisation.

---

## 6. Feiertage (Deutschland)

Gesetzliche und betriebliche Feiertage hängen vom **Bundesland** und den **Administrator-Einstellungen** ab. Die App nutzt diese Daten für Arbeitstags- und Prüflogik—**nicht** zum automatischen Befüllen der Nextcloud-Kalender-App.

---

## 7. Datenschutz

Personenbezogene Daten werden für **Zeiterfassung und damit verbundene HR-Prozesse** verarbeitet, wie von Ihrer Organisation festgelegt. **DSGVO-Export und -Löschung** nur im Rahmen von Vorgaben und Aufbewahrungsfristen nutzen (siehe verlinkte Dokumente).

---

## 8. Hilfe

- **Interne Fragen**: IT oder Nextcloud-Administration Ihrer Organisation.
- **Fehler in der App**: Meldung über die im App-Store genannte Projektvorgabe möglich.

---

*Stand: zur App-Version 1.1.x passend; exakte Versionsnummer: `appinfo/info.xml`.*
