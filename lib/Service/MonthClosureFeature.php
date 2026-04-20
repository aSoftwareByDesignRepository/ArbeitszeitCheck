<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;

final class MonthClosureFeature
{
	public static function isEnabledFromIConfig(IConfig $config): bool
	{
		return $config->getAppValue('arbeitszeitcheck', Constants::CONFIG_MONTH_CLOSURE_ENABLED, '0') === '1';
	}

	public static function isEnabledFromAppConfig(IAppConfig $appConfig): bool
	{
		return $appConfig->getAppValueString(Constants::CONFIG_MONTH_CLOSURE_ENABLED, '0') === '1';
	}
}
