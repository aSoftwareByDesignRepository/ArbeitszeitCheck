<?php

declare(strict_types=1);

use OCA\ArbeitszeitCheck\Service\EmployeeSettingsSectionCatalog;
use OCA\ArbeitszeitCheck\Service\FrontEndAssetService;

/**
 * Personal settings template for arbeitszeitcheck app.
 *
 * Rendered inside Nextcloud's user-settings shell via {@see \OCA\ArbeitszeitCheck\Settings\PersonalSettings}.
 * Detailed personal preferences (notifications, breaks, ...) live in the in-app settings pages;
 * this panel only provides a clear pointer plus a short overview of GDPR data-rights actions.
 *
 * @copyright Copyright (c) 2024-2026 Software by Design / Alexander Mäule
 * @license AGPL-3.0-or-later
 */

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
/** @var \OCP\IURLGenerator $urlGenerator */
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);

$catalog = new EmployeeSettingsSectionCatalog();
$inAppSettingsUrl = $catalog->url($urlGenerator, EmployeeSettingsSectionCatalog::DEFAULT_SECTION);
$inAppPrivacyUrl = $catalog->url($urlGenerator, EmployeeSettingsSectionCatalog::SECTION_DATA_PRIVACY);
$inAppLandingUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.page.index');

FrontEndAssetService::registerCore();
?>

<section id="arbeitszeitcheck-personal-settings"
	class="section azc-nc-settings-panel"
	aria-labelledby="azc-personal-settings-title">
	<h2 id="azc-personal-settings-title"><?php p($l->t('ArbeitszeitCheck')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Personal preferences for time tracking, notifications and break reminders are managed inside the ArbeitszeitCheck app itself, alongside your dashboard and time entries.')); ?>
	</p>

	<p class="settings-hint">
		<a class="azc-btn azc-btn--primary"
			href="<?php p($inAppSettingsUrl); ?>"
			aria-label="<?php p($l->t('Open ArbeitszeitCheck personal settings page')); ?>">
			<?php p($l->t('Open personal settings in app')); ?>
		</a>
		<a class="azc-btn azc-btn--secondary"
			href="<?php p($inAppLandingUrl); ?>"
			aria-label="<?php p($l->t('Open the ArbeitszeitCheck dashboard')); ?>">
			<?php p($l->t('Open ArbeitszeitCheck')); ?>
		</a>
	</p>

	<h3 class="settings-subheading"><?php p($l->t('Your data rights')); ?></h3>
	<p class="settings-hint">
		<?php p($l->t('Under the GDPR you can request a copy of your data, correction of inaccuracies or deletion of your records. ArbeitszeitCheck provides a self-service data export and deletion request inside the app under "My settings" → "Data and privacy".')); ?>
	</p>
	<p class="settings-hint">
		<a class="azc-btn azc-btn--secondary"
			href="<?php p($inAppPrivacyUrl); ?>"
			aria-label="<?php p($l->t('Open Data and privacy in ArbeitszeitCheck')); ?>">
			<?php p($l->t('Open Data & privacy')); ?>
		</a>
	</p>
</section>
