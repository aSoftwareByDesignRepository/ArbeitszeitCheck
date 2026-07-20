<?php
declare(strict_types=1);

/**
 * Settings template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/** @var array $_ */
/** @var \OCP\IL10N $l */

// Assets registered by PageController::registerFrontEndAssets

$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$urls = $_['urls'] ?? [];
$appVersion = \OCP\Server::get(\OCP\App\IAppManager::class)->getAppVersion('arbeitszeitcheck');
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack settings-container" aria-label="<?php p($l->t('Settings options')); ?>">

	<section class="settings-section azc-card" aria-labelledby="settings-sections-heading">
		<header class="azc-card__header">
			<div class="azc-card__header-text">
				<h2 id="settings-sections-heading" class="azc-card__title"><?php p($l->t('Working Time Preferences')); ?></h2>
				<p class="azc-card__lead"><?php p($l->t('Control how the app handles your breaks.')); ?></p>
			</div>
		</header>
		<div class="azc-card__body">
			<form id="working-time-settings-form" class="settings-form">
				<div class="settings-form__group">
					<div class="settings-form__checkbox">
						<input type="checkbox"
							id="auto-break-calculation"
							name="auto_break_calculation"
							checked
							aria-describedby="auto-break-calculation-help">
						<label for="auto-break-calculation" class="form-label">
							<?php p($l->t('Calculate breaks automatically')); ?>
						</label>
					</div>
					<p id="auto-break-calculation-help" class="settings-form__help">
						<?php p($l->t('The system will automatically calculate when you need to take breaks according to German labor law. For example, if you work more than 6 hours, you must take at least a 30-minute break.')); ?>
					</p>
				</div>
				<div class="settings-form__actions">
					<button type="submit"
						class="azc-btn azc-btn--primary"
						aria-label="<?php p($l->t('Save your preferences')); ?>">
						<?php p($l->t('Save Settings')); ?>
					</button>
					<a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"
						class="azc-btn azc-btn--secondary"
						aria-label="<?php p($l->t('Cancel and go back to dashboard')); ?>">
						<?php p($l->t('Cancel')); ?>
					</a>
				</div>
			</form>
		</div>
	</section>

	<section class="settings-section azc-card" aria-labelledby="settings-notifications-heading">
		<header class="azc-card__header">
			<div class="azc-card__header-text">
				<h2 id="settings-notifications-heading" class="azc-card__title"><?php p($l->t('Notifications')); ?></h2>
				<p class="azc-card__lead"><?php p($l->t('Choose which reminders you want to receive.')); ?></p>
			</div>
		</header>
		<div class="azc-card__body">
			<form id="notification-settings-form" class="settings-form">
				<div class="settings-form__group">
					<div class="settings-form__checkbox">
						<input type="checkbox"
							id="notifications-enabled"
							name="notifications_enabled"
							checked>
						<label for="notifications-enabled" class="form-label">
							<?php p($l->t('Enable Notifications')); ?>
						</label>
					</div>
				</div>
				<div class="settings-form__group">
					<div class="settings-form__checkbox">
						<input type="checkbox"
							id="break-reminders"
							name="break_reminders_enabled"
							checked
							aria-describedby="break-reminders-help">
						<label for="break-reminders" class="form-label">
							<?php p($l->t('Remind me to take breaks')); ?>
						</label>
					</div>
					<p id="break-reminders-help" class="settings-form__help">
						<?php p($l->t('Get a notification when it\'s time to take a required break. For example, if you work more than 6 hours, you\'ll get a reminder to take at least a 30-minute break.')); ?>
					</p>
				</div>
				<div class="settings-form__group">
					<div class="settings-form__checkbox">
						<input type="checkbox"
							id="missing-clock-in-reminders"
							name="missing_clock_in_reminders_enabled"
							checked
							aria-describedby="missing-clock-in-reminders-help">
						<label for="missing-clock-in-reminders" class="form-label">
							<?php p($l->t('Remind me when I forgot to clock in (for expected workdays)')); ?>
						</label>
					</div>
					<p id="missing-clock-in-reminders-help" class="settings-form__help">
						<?php p($l->t('You receive this reminder only on regular working days. No reminder is sent on weekends, holidays, or approved absences.')); ?>
					</p>
				</div>
				<div class="settings-form__actions">
					<button type="submit"
						class="azc-btn azc-btn--primary"
						aria-label="<?php p($l->t('Save your working time settings')); ?>">
						<?php p($l->t('Save Settings')); ?>
					</button>
					<a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"
						class="azc-btn azc-btn--secondary"
						aria-label="<?php p($l->t('Cancel and go back to dashboard')); ?>">
						<?php p($l->t('Cancel')); ?>
					</a>
				</div>
			</form>
		</div>
	</section>

	<section class="settings-section azc-card" aria-labelledby="settings-model-heading">
		<header class="azc-card__header">
			<div class="azc-card__header-text">
				<h2 id="settings-model-heading" class="azc-card__title"><?php p($l->t('Working Time Model')); ?></h2>
				<p class="azc-card__lead"><?php p($l->t('Your assigned schedule and vacation rules (read-only).')); ?></p>
			</div>
		</header>
		<div class="azc-card__body">
			<div id="working-time-model-info" class="azc-callout azc-callout--neutral" role="note">
				<p class="azc-callout__text"><?php p($l->t('Your working time model, vacation days, and working hours are assigned by your administrator. Contact your administrator if you have questions or need changes.')); ?></p>
			</div>
		</div>
	</section>

	<section class="settings-section azc-card" aria-labelledby="settings-compliance-heading">
		<header class="azc-card__header">
			<div class="azc-card__header-text">
				<h2 id="settings-compliance-heading" class="azc-card__title"><?php p($l->t('Compliance Information')); ?></h2>
				<p class="azc-card__lead"><?php p($l->t('Key rules from German working time law that this app helps you follow.')); ?></p>
			</div>
		</header>
		<div class="azc-card__body">
			<div class="azc-callout azc-callout--neutral" role="note">
				<p class="azc-callout__text"><strong><?php p($l->t('German Labor Law (Arbeitszeitgesetz - ArbZG)')); ?></strong></p>
				<ul class="settings-callout-list">
					<li><?php p($l->t('Maximum working time: 8 hours per day (can be extended to 10 hours)')); ?></li>
					<li><?php p($l->t('Minimum rest period: 11 hours between working days')); ?></li>
					<li><?php p($l->t('Mandatory breaks: 30 min after 6 hours, 45 min after 9 hours')); ?></li>
					<li><?php p($l->t('Sunday work is generally prohibited with exceptions')); ?></li>
				</ul>
			</div>
		</div>
	</section>

	<section class="settings-section azc-card" id="settings-data-privacy" aria-labelledby="settings-privacy-heading">
		<header class="azc-card__header">
			<div class="azc-card__header-text">
				<h2 id="settings-privacy-heading" class="azc-card__title"><?php p($l->t('Data and privacy')); ?></h2>
				<p class="azc-card__lead"><?php p($l->t('Export or permanently delete your personal ArbeitszeitCheck data in accordance with GDPR.')); ?></p>
			</div>
		</header>
		<div class="azc-card__body">
			<div class="settings-privacy-actions">
				<a href="<?php print_unescaped((string)($urls['gdprExport'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.gdpr.export'))); ?>"
					class="azc-btn azc-btn--secondary"
					download>
					<?php p($l->t('Export My Data')); ?>
				</a>
				<button type="button"
					id="btn-gdpr-delete"
					class="azc-btn azc-btn--danger"
					data-delete-url="<?php p((string)($urls['gdprDelete'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.gdpr.delete'))); ?>"
					aria-describedby="gdpr-delete-help">
					<?php p($l->t('Delete my ArbeitszeitCheck data')); ?>
				</button>
			</div>
			<p class="form-help" id="gdpr-delete-help">
				<?php p($l->t('Deleting your data permanently removes time entries, absences, and settings stored by this app. This cannot be undone.')); ?>
			</p>
		</div>
	</section>

	<section class="settings-section azc-card" aria-labelledby="settings-version-heading">
		<header class="azc-card__header">
			<div class="azc-card__header-text">
				<h2 id="settings-version-heading" class="azc-card__title"><?php p($l->t('Version Information')); ?></h2>
				<p class="azc-card__lead"><?php p($l->t('Installed app version on this Nextcloud.')); ?></p>
			</div>
		</header>
		<div class="azc-card__body">
			<p class="settings-version-line">
				<strong><?php p($l->t('ArbeitszeitCheck')); ?></strong>
				<?php p($l->t('Version:')); ?> <?php p($appVersion); ?>
			</p>
			<p class="settings-version-line"><?php p($l->t('German labor law compliant time tracking for Nextcloud')); ?></p>
		</div>
	</section>
</div>

<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
	window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
	window.ArbeitszeitCheck.page = 'settings';

	window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
	window.ArbeitszeitCheck.l10n.settingsSaved = <?php echo json_encode($l->t('Settings saved successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	window.ArbeitszeitCheck.l10n.error = <?php echo json_encode($l->t('An error occurred'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	window.ArbeitszeitCheck.l10n.saving = <?php echo json_encode($l->t('Saving...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	window.ArbeitszeitCheck.l10n.failedToSaveSettings = <?php echo json_encode($l->t('Failed to save settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

	window.ArbeitszeitCheck.apiUrl = {
		updateSettings: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.settings.update'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		gdprDelete: <?php echo json_encode((string)($urls['gdprDelete'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.gdpr.delete')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
	};
</script>
<?php include __DIR__ . '/common/page-end.php'; ?>
