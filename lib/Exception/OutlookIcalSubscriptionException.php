<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Exception;

/**
 * Base exception for Outlook iCal subscription flows.
 *
 * The controller maps these to HTTP status codes with an empty body on failure
 * to avoid leaking sensitive state (privacy + token side-channel resistance).
 */
abstract class OutlookIcalSubscriptionException extends \RuntimeException
{
	/**
	 * Stable machine-readable error code (for logs / optional client mapping).
	 */
	public readonly string $errorCode;

	public function __construct(string $errorCode, string $message = '')
	{
		parent::__construct($message);
		$this->errorCode = $errorCode;
	}
}

