<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

/**
 * Thrown when a cookie-session mutating request fails the CSRF check.
 */
final class SessionCsrfRequiredException extends \Exception
{
}
