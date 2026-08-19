<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Exception;

/**
 * Feed limit violations (date range too large or too many events).
 *
 * These map to HTTP 400 and intentionally have empty response bodies to
 * avoid leaking dataset size and to keep error handling side-effect-free.
 */
final class OutlookIcalSubscriptionFeedLimitException extends OutlookIcalSubscriptionBadRequestException
{
	public const ERROR_EVENT_COUNT_TOO_LARGE = 'EVENT_COUNT_TOO_LARGE';

	public function __construct(string $errorCode)
	{
		parent::__construct($errorCode);
	}
}

