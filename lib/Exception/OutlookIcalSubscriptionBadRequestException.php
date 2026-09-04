<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Exception;

class OutlookIcalSubscriptionBadRequestException extends OutlookIcalSubscriptionException
{
	public const ERROR_INVALID_DATE_RANGE = 'INVALID_DATE_RANGE';
	public const ERROR_RANGE_TOO_LARGE = 'RANGE_TOO_LARGE';
	public const ERROR_EVENT_COUNT_TOO_LARGE = 'EVENT_COUNT_TOO_LARGE';
	public const ERROR_MISSING_PARAMETERS = 'MISSING_PARAMETERS';
	public const ERROR_INVALID_TEAM_SCOPE = 'INVALID_TEAM_SCOPE';
	public const ERROR_MANAGER_UNAVAILABLE = 'MANAGER_UNAVAILABLE';
	public const ERROR_INVALID_FEED_LANGUAGE = 'INVALID_FEED_LANGUAGE';
	public const ERROR_SUBSCRIPTION_ALREADY_EXISTS = 'SUBSCRIPTION_ALREADY_EXISTS';
	public const ERROR_SUBSCRIPTION_NOT_FOUND = 'SUBSCRIPTION_NOT_FOUND';

	public function __construct(string $errorCode)
	{
		parent::__construct($errorCode);
	}
}

