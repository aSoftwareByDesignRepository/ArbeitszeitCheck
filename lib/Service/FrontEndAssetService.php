<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCP\Util;

/**
 * Central front-end asset registration for ArbeitszeitCheck pages.
 */
final class FrontEndAssetService
{
	private static bool $coreRegistered = false;
	private static bool $translationsRegistered = false;

	/**
	 * Register l10n boot helper before locale JS (idempotent per request).
	 */
	public static function registerTranslations(): void
	{
		if (self::$translationsRegistered) {
			return;
		}
		self::$translationsRegistered = true;

		Util::addScript(Application::APP_ID, 'common/l10n-boot');
		Util::addTranslations(Application::APP_ID);
	}

	/**
	 * Register shared stylesheet + JS stack (idempotent per request).
	 */
	public static function registerCore(): void
	{
		if (self::$coreRegistered) {
			return;
		}
		self::$coreRegistered = true;

		self::registerTranslations();
		Util::addStyle(Application::APP_ID, 'app');
		Util::addScript(Application::APP_ID, 'common/api');
		Util::addScript(Application::APP_ID, 'common/catalog');
		Util::addScript(Application::APP_ID, 'common/utils');
		Util::addScript(Application::APP_ID, 'common/time');
		Util::addScript(Application::APP_ID, 'common/components');
		Util::addScript(Application::APP_ID, 'common/messaging');
		Util::addScript(Application::APP_ID, 'common/app-feedback');
		Util::addScript(Application::APP_ID, 'common/navigation');
		Util::addScript(Application::APP_ID, 'common/navigation-icons');
	}

	/**
	 * Register core assets plus optional page-specific CSS/JS.
	 *
	 * @param list<string> $extraStyles
	 * @param list<string> $extraScripts Loaded after core and before the page script (dependencies).
	 */
	public static function registerPage(string $pageScript, ?string $pageStyle = null, array $extraStyles = [], array $extraScripts = []): void
	{
		self::registerCore();
		foreach ($extraStyles as $style) {
			if ($style !== '') {
				Util::addStyle(Application::APP_ID, $style);
			}
		}
		if ($pageStyle !== null && $pageStyle !== '') {
			Util::addStyle(Application::APP_ID, $pageStyle);
		}
		foreach ($extraScripts as $script) {
			if ($script !== '') {
				Util::addScript(Application::APP_ID, $script);
			}
		}
		if ($pageScript !== '') {
			Util::addScript(Application::APP_ID, $pageScript);
		}
	}
}
