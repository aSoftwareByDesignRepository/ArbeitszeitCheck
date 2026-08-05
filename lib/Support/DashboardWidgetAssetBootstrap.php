<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCP\Util;

/**
 * Idempotent script/style registration for the NC home dashboard desklet.
 *
 * Nextcloud may inject widget assets more than once when several desklets load;
 * PHP dedupe is per-request only, so companion JS modules must also be safe to
 * re-execute. This class avoids duplicate Util::addScript calls in one request.
 */
final class DashboardWidgetAssetBootstrap {
	private static bool $deskletAssetsRegistered = false;

	public static function registerDeskletAssets(): void {
		if (self::$deskletAssetsRegistered) {
			return;
		}
		self::$deskletAssetsRegistered = true;

		Util::addScript(Application::APP_ID, 'common/catalog');
		Util::addScript(Application::APP_ID, 'common/api');
		Util::addScript(Application::APP_ID, 'common/desklet-actions');
		Util::addScript(Application::APP_ID, 'dashboard-widgets');
		Util::addStyle(Application::APP_ID, 'dashboard-widgets');
	}
}
