<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Exception;

use OCP\IRequest;

final class OutlookIcalSubscriptionAuthException extends OutlookIcalSubscriptionException
{
	public const ERROR_UNAUTHORIZED = 'UNAUTHORIZED';
	public const ERROR_FORBIDDEN = 'FORBIDDEN';

	public readonly int $httpStatus;

	public function __construct(string $errorCode, int $httpStatus)
	{
		parent::__construct($errorCode);
		$this->httpStatus = $httpStatus;
	}
}

