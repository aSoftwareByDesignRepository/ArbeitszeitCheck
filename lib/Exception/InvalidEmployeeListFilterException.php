<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Exception;

/**
 * Thrown when GET /api/admin/users (or export) receives an unknown access filter value.
 */
final class InvalidEmployeeListFilterException extends BusinessRuleException
{
	public const CODE = 'INVALID_EMPLOYEE_LIST_FILTER';

	public function __construct(string $message)
	{
		parent::__construct($message, self::CODE);
	}
}
