<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

use OCA\ArbeitszeitCheck\BusinessRuleCode;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;

/**
 * Maps time-tracking business rules to stable kiosk API error codes.
 *
 * Human detail stays on the exception message (already translated server-side).
 */
final class KioskBusinessRuleMapper
{
	public function toKioskException(BusinessRuleException $e): KioskException
	{
		$code = match ($e->getReasonCode()) {
			BusinessRuleCode::ALREADY_CLOCKED_IN => 'KIOSK_ALREADY_CLOCKED_IN',
			BusinessRuleCode::ON_BREAK_END_FIRST => 'KIOSK_ON_BREAK_END_FIRST',
			BusinessRuleCode::NOT_CLOCKED_IN => 'KIOSK_NOT_CLOCKED_IN',
			BusinessRuleCode::BREAK_ALREADY_STARTED => 'KIOSK_BREAK_ALREADY_STARTED',
			BusinessRuleCode::NOT_ON_BREAK => 'KIOSK_NOT_ON_BREAK',
			BusinessRuleCode::DAILY_HOURS_LIMIT => 'KIOSK_DAILY_HOURS_LIMIT',
			BusinessRuleCode::REST_PERIOD_REQUIRED => 'KIOSK_REST_PERIOD_REQUIRED',
			default => 'KIOSK_ACTION_REJECTED',
		};

		return new KioskException($code, $e->getMessage());
	}
}
