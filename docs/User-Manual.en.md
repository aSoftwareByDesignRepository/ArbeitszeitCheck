# ArbeitszeitCheck — User guide (English)

This guide describes features from an **end-user and team lead** perspective. It is shipped with the app in `docs/` for administrators who want a short reference to share internally.

For **legal / GDPR** topics, see [GDPR-Compliance-Guide.en.md](GDPR-Compliance-Guide.en.md). For **technical / ArbZG implementation** details, see [Compliance-Implementation.en.md](Compliance-Implementation.en.md).

---

## 1. What this app does

ArbeitszeitCheck records **working time**, checks **German working time law (ArbZG)** rules (breaks, rest periods, limits where configured), manages **absences** (leave, sick leave, etc.) with approvals where your organization uses them, and offers **reports and exports**.

All data stays in your **Nextcloud** instance.

---

## 2. Calendars: in-app view vs Nextcloud Calendar

| Topic | What to expect |
|--------|------------------|
| **Calendar page inside ArbeitszeitCheck** | The app has its own **month calendar** view (working time and absences). This is **not** the separate “Calendar” app. |
| **Nextcloud Calendar app** | ArbeitszeitCheck **does not** sync absences into the Nextcloud **Calendar** app (no CalDAV feed from this app). |
| **Old calendars** | If calendars were created in the past when a different integration existed, they **remain** in the Calendar app until you delete them there. The app does not remove them automatically. |
| **Email with `.ics` attachment** | In some workflows, the app can send **email** with an iCalendar file so people can **import an event manually** into any calendar client. That is optional mail, not live two-way sync. |

---

> Operational note: Direct write/sync into the Nextcloud Calendar app is intentionally not active. If you need calendar entries, use the optional `.ics` email flow.

---

## 3. Roles (typical setup)

- **Employee**: Record time, request absences, view own data.
- **Substitute** (if used): Approve or reject coverage for an absence.
- **Manager / team lead**: Approve absences for team members, see team views where permitted.
- **Administrator**: Global settings, users, holidays, compliance options, exports.

Exact permissions depend on your Nextcloud groups and app configuration.

---

## 4. Everyday tasks

- **Clock in / out** and **breaks**: Use the time tracking UI; follow your organization’s rules for corrections and comments.
- **Absences**: Create requests; wait for approval if your workflow requires it. Vacation balances and carryover (**Resturlaub**) may be shown if your admin configured them.
- **Manager dashboard** (if you are a team lead): Under **Pending approvals**, absence requests list each person with the **absence type in your language** (e.g. vacation vs sick leave), not raw internal codes.
- **Reports**: Generate period reports or exports your admin allows (CSV, DATEV, etc.).
- **Compliance**: The app may flag violations (e.g. missing breaks); your employer defines how those are handled.

---

## 5. Monthly record (revision-safe)

**Legal note (not legal advice):** German law requires employers to **record and retain** working time (ArbZG / case law). It does **not** prescribe that an employee must “click finalize” in software, nor a specific **deadline** for that action—those are **organizational** choices (works agreement, company policy, payroll process). This app’s optional monthly seal is a **technical audit aid** (snapshot + hash), not a statutory formality by itself.

If your administrator enabled **revision-safe month finalization**, the **Time entries** page can show a **Monthly record** section.

- Choose the **calendar month** (month and year in one list, shown with a readable month name). The list includes **only months that have ended and contain at least one time entry** (so empty months are not shown). Review your times until that month is complete (including resolving **pending correction** requests—finalization is blocked while any correction is still pending).
- **Finalize month** stores a **fixed snapshot** of that calendar month (working time and related report totals as implemented by the app), a **cryptographic hash**, and allows downloading a **PDF** for your records.
- After finalization, you **cannot change** time entries or absences that fall in that month through the normal app—your organization’s **administrator** can **reopen** a month only with a **documented reason** (audited).

Turning the feature **off** later does **not** unlock months that were already finalized.

**Administrator reopen:** An administrator can reopen a finalized month **in the app** (admin settings area) with a **mandatory reason** (audited), not only via API.

This is an **in-app integrity** feature (hash + audit). It is **not** a qualified electronic signature. Direct database access by server operators is outside what the app can prevent—your organization’s IT and retention policies apply.

---

## 6. Holidays (Germany)

Statutory and optional holidays depend on the **federal state (Bundesland)** and settings your **administrator** maintains under the holidays / admin area. The app uses this data for working-day calculations and checks—not for pushing events into the Nextcloud Calendar app.

---

## 7. Privacy and data

- Personal data is processed for **time recording and HR-related processes** as configured by your organization.
- Use **GDPR export / deletion** features only as allowed by policy and retention rules (see the GDPR guide linked above).

---

## 8. Getting help

- **Issues with the product**: Contact your internal IT or the person who runs Nextcloud.
- **Bugs in the app**: Your administrator can report issues via the repository linked in the app metadata on the App Store page.

---

*Document version: aligned with app 1.1.x. For the exact shipped version, see `appinfo/info.xml`.*
