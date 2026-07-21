<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck;

/**
 * Stable machine reason codes for {@see Exception\BusinessRuleException}.
 *
 * Locale-independent — kiosk and web clients map these to their own copy.
 */
final class BusinessRuleCode
{
	public const ALREADY_CLOCKED_IN = 'already_clocked_in';
	public const ON_BREAK_END_FIRST = 'on_break_end_first';
	public const NOT_CLOCKED_IN = 'not_clocked_in';
	public const BREAK_ALREADY_STARTED = 'break_already_started';
	public const NOT_ON_BREAK = 'not_on_break';
	public const DAILY_HOURS_LIMIT = 'daily_hours_limit';
	public const REST_PERIOD_REQUIRED = 'rest_period_required';
}
