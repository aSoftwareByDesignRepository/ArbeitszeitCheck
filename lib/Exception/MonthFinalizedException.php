<?php

declare(strict_types=1);

/**
 * Thrown when a mutation would change data in a finalized calendar month.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Exception;

class MonthFinalizedException extends \Exception
{
}
