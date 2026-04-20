<?php

declare(strict_types=1);

/**
 * Application constants for arbeitszeitcheck
 *
 * Named constants for business rules, limits, and magic numbers.
 * Use these instead of hardcoded values for maintainability and clarity.
 *
 * @copyright Copyright (c) 2024-2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck;

final class Constants
{
	/**
	 * Number of days within which time entries can be edited (compliance / data integrity).
	 */
	public const EDIT_WINDOW_DAYS = 14;

	/**
	 * Default number of items per page for list endpoints (time entries, absences, violations, etc.).
	 */
	public const DEFAULT_LIST_LIMIT = 25;

	/**
	 * Maximum number of items per request (DoS protection).
	 */
	public const MAX_LIST_LIMIT = 500;

	/**
	 * Default vacation days per year when no user setting exists (German standard).
	 */
	public const DEFAULT_VACATION_DAYS_PER_YEAR = 25;

	public const VACATION_MODE_MANUAL_FIXED = 'manual_fixed';
	public const VACATION_MODE_MODEL_BASED_SIMPLE = 'model_based_simple';
	public const VACATION_MODE_TARIFF_RULE_BASED = 'tariff_rule_based';
	public const VACATION_MODE_MANUAL_EXCEPTION = 'manual_exception';

	public const TARIFF_RULE_SET_STATUS_DRAFT = 'draft';
	public const TARIFF_RULE_SET_STATUS_ACTIVE = 'active';
	public const TARIFF_RULE_SET_STATUS_RETIRED = 'retired';

	/** App config: month (1–12) when carryover from the previous year expires (default March). */
	public const CONFIG_VACATION_CARRYOVER_EXPIRY_MONTH = 'vacation_carryover_expiry_month';

	/** App config: day of month for carryover expiry (default 31). */
	public const CONFIG_VACATION_CARRYOVER_EXPIRY_DAY = 'vacation_carryover_expiry_day';

	/**
	 * Optional max opening carryover days (empty = no cap). Tarifvertrag-specific; not legal advice.
	 */
	public const CONFIG_VACATION_CARRYOVER_MAX_DAYS = 'vacation_carryover_max_days';

	/** When "1", background job may write next year opening from unused carryover remainder (see docs). */
	public const CONFIG_VACATION_ROLLOVER_ENABLED = 'vacation_rollover_enabled';

	/**
	 * When "1" and rollover enabled, also roll unused annual entitlement (off by default; Tarifvertrag-specific).
	 */
	public const CONFIG_VACATION_ROLLOVER_INCLUDE_UNUSED_ANNUAL = 'vacation_rollover_include_unused_annual';

	/**
	 * Maximum duration in days for absence requests (validation).
	 */
	public const MAX_ABSENCE_DAYS = 365;

	/**
	 * Sick leave: maximum days in the past for start date (German law allows up to 3 days backdating; 7 is a safe buffer).
	 */
	public const SICK_LEAVE_MAX_PAST_DAYS = 7;

	/**
	 * Maximum date range in days for exports (audit, users, etc.).
	 */
	public const MAX_EXPORT_DATE_RANGE_DAYS = 365;

	/**
	 * Batch size for chunked DB operations (e.g. recursive team queries).
	 */
	public const BATCH_CHUNK_SIZE = 500;

	/** App config: when "1", employees may finalize months (revision-safe snapshot + lock). Default off. */
	public const CONFIG_MONTH_CLOSURE_ENABLED = 'month_closure_enabled';

	/**
	 * App config: JSON array of user IDs that are allowed to administer this app.
	 * Empty means all Nextcloud admins are app-admins (backward compatible default).
	 */
	public const CONFIG_APP_ADMIN_USER_IDS = 'app_admin_user_ids';

	/**
	 * Days after the last day of a calendar month until automatic finalization runs (daily job).
	 * "0" = no automatic finalization (employees must finalize manually, or admin reopens).
	 */
	public const CONFIG_MONTH_CLOSURE_GRACE_DAYS_AFTER_EOM = 'month_closure_grace_days_after_eom';

	/** App config: when "1", HR absence email notifications are enabled globally. */
	public const CONFIG_HR_NOTIFICATIONS_ENABLED = 'hr_notifications_enabled';
	/** App config: comma-separated HR recipient email list (legacy readable). */
	public const CONFIG_HR_NOTIFICATION_RECIPIENTS = 'hr_notification_recipients';
	/** App config: versioned JSON matrix of absence_type => event => bool. */
	public const CONFIG_HR_NOTIFICATION_MATRIX_V1 = 'hr_notification_matrix_v1';

	/** @var list<string> */
	public const HR_NOTIFICATION_EVENTS = [
		'request_created',
		'substitute_approved',
		'substitute_declined',
		'manager_approved',
		'manager_rejected',
		'employee_cancelled',
		'employee_shortened',
	];

	/** @var list<string> */
	public const ABSENCE_TYPES = [
		'vacation',
		'sick_leave',
		'personal_leave',
		'parental_leave',
		'special_leave',
		'unpaid_leave',
		'home_office',
		'business_trip',
	];

	/**
	 * Compliance score weights (critical, warning, info).
	 */
	public const COMPLIANCE_SCORE_CRITICAL_WEIGHT = 25;
	public const COMPLIANCE_SCORE_WARNING_WEIGHT = 10;
	public const COMPLIANCE_SCORE_INFO_WEIGHT = 5;
	public const COMPLIANCE_SCORE_MAX_DEDUCTION = 100;


	private function __construct()
	{
	}
}
