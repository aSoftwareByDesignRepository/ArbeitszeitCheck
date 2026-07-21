<?php

declare(strict_types=1);

/**
 * Thrown when a service rejects a user request because of a known
 * business rule (e.g. "User is already clocked in"). The message is
 * already translated for the current user, so callers must not run
 * it through IL10N::t() again. HTTP transports map this exception to
 * a 400 Bad Request response.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Exception;

class BusinessRuleException extends \RuntimeException
{
	public function __construct(
		string $message,
		private readonly ?string $reasonCode = null,
	) {
		parent::__construct($message);
	}

	public function getReasonCode(): ?string
	{
		return $this->reasonCode;
	}
}
