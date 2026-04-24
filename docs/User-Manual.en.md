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
- **App administrator (optional restriction)**: Your organization can limit ArbeitszeitCheck administration to selected Nextcloud admins only. If this is configured, other Nextcloud admins may still be platform admins but will see access denied for ArbeitszeitCheck admin pages.

Exact permissions depend on your Nextcloud groups and app configuration.

### For administrators: how to configure app administrators

1. Open **ArbeitszeitCheck → Admin settings**.
2. In **App administrators (ArbeitszeitCheck)**, search/select the Nextcloud admin accounts that should manage this app.
3. Save settings.
4. Leave the list empty if you want the backward-compatible default (**all** Nextcloud admins can administer ArbeitszeitCheck).

Only users in Nextcloud's `admin` group are eligible in this picker.

### For administrators: notifications and vacation policy model

- Open **ArbeitszeitCheck → Admin notifications** to configure absence-related mail behavior in one place.
- **HR office notifications** can be enabled with recipient list plus a matrix per absence type/event (for example: request created, substitute approved, manager approved/rejected, employee cancelled/shortened).
- **Overtime/undertime traffic light notifications** can be enabled with separate yellow/red thresholds for both directions, a dedicated recipient list, and a direction-level matrix (`over|under` x `yellow|red`).
- The same page contains practical controls for carryover expiry, optional carryover cap, rollover behavior, substitute-required absence types, and iCal/substitution e-mail toggles.
- Vacation entitlement can now be assigned per user with policy modes (manual, model-based, tariff-rule based, manual exception). If your organization uses tariff rules, administrators can manage rule-set versions and activation windows via admin APIs/integration tooling.

---

## 4. Everyday tasks

- **Clock in / out** and **breaks**: Use the time tracking UI; follow your organization’s rules for corrections and comments.
- **Dashboard quick actions workspace**:
  - The dashboard includes a dedicated quick-actions area with **My status**, **Current session**, and direct buttons for **Clock In**, **Pause**, **Continue**, and **Clock Out**.
  - Depending on your role, the same page can also show **Team overview** (manager) and **Company overview** (admin) with compact status lists.
  - If a request fails because your Nextcloud session expired, refresh the page and retry.
- **Paused entry (resume or fix):**
  - If an entry is in **Paused** state, the dashboard shows **Resume after break** on clock-in and continues the same-day entry instead of creating a duplicate.
  - Paused entries are editable/deletable again within the normal 14-day edit window (as long as they are not already approved).
  - When saved with an end time, a paused entry is finalized automatically as **Completed**.
- **Absences**: Create requests; wait for approval if your workflow requires it. Vacation balances and carryover (**Resturlaub**) may be shown if your admin configured them.
  - **App teams (recommended setup):** If your organization uses **app-managed teams** and **no manager is assigned** to your team in the app, requests you submit **without** a substitute are **approved automatically** when you send them—there is nobody who could approve them in the manager workflow. If you **do** pick a substitute, the substitute step still runs first. The UI may show a short explanation when this applies.
  - **Legacy group-based setup:** Behavior follows the older “same group” model; your admin should ensure approvals remain workable for your organization.
- **Manager dashboard** (if you are a team lead): Under **Pending approvals**, you can switch between **Absences** and **Time entry corrections** tabs. Absence requests list each person with the **absence type in your language** (e.g. vacation vs sick leave), not raw internal codes. Where enabled, **Employee absences** provides a dedicated list/filter view of team absences.
- **Overtime balance traffic light**: On the dashboard, your balance can appear as green/yellow/red and distinguishes overtime vs undertime warnings. This is an orientation signal; your organization defines policy actions.
- **Mobile use (phones/tablets):** The UI is optimized for responsive use with clearer section spacing, larger touch targets, and iPhone safe-area aware layout. If you use a very small screen, prefer portrait mode for forms and landscape for wide tables/reports.
- **Reports**: Generate period reports or exports your admin allows (CSV, DATEV, etc.).
- **Compliance**: The app may flag violations (e.g. missing breaks); your employer defines how those are handled.

---

## 5. Monthly record (revision-safe)

**Legal note (not legal advice):** German law requires employers to **record and retain** working time (ArbZG / case law). It does **not** prescribe that an employee must “click finalize” in software, nor a specific **deadline** for that action—those are **organizational** choices (works agreement, company policy, payroll process). This app’s optional monthly seal is a **technical audit aid** (snapshot + hash), not a statutory formality by itself.

If your administrator enabled **revision-safe month finalization**, the **Time entries** page can show a **Monthly record** section.

- Choose the **calendar month** (month and year in one list, shown with a readable month name). The list includes **only months that have ended and contain at least one time entry** (so empty months are not shown). Review your times until that month is complete (including resolving **pending correction** requests—finalization is blocked while any correction is still pending).
- **Finalize month** stores a **fixed snapshot** of that calendar month (working time and related report totals as implemented by the app), a **cryptographic hash**, and allows downloading a **PDF** for your records.
- **Grace period (if configured):** Administrators may set **calendar days after month-end** during which you are expected to finalize manually; the UI may show that deadline. If the month is **still open** after the grace period ends, a **daily background job** may seal it automatically using the **same snapshot** as a manual finalize. **Pending** time-entry correction requests or **open absence workflow** steps (e.g. approval or substitute steps) **block** automatic sealing until resolved.
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

*Document version: aligned with the current app release. For the exact shipped version, see `appinfo/info.xml`.*
