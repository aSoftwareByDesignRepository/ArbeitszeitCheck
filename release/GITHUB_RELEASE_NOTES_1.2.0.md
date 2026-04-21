## Highlights

- Introduced a policy-driven vacation entitlement engine with support for `manual_fixed`, `model_based_simple`, `tariff_rule_based`, and `manual_exception`.
- Added versioned tariff rule-set APIs, entitlement snapshots, and a dedicated admin notifications page with matrix-based controls.
- Integrated entitlement source/rule-set trace data into vacation allocation responses and added cleanup of policy/snapshot data on user deletion.

## Included changes

- Added: entitlement policy engine, tariff rule modules/APIs, entitlement snapshots, admin notifications UI/API.
- Changed: allocation now uses `VacationEntitlementEngine`, migration backfills legacy model-based values, admin absence-notification controls centralized.
- Fixed: user deletion now removes vacation policy assignments and entitlement snapshots.
