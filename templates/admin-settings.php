<?php
declare(strict_types=1);

/**
 * Admin global settings — SETTINGS-PAGES-STANDARD multipage shell.
 *
 * Dispatches to templates/partials/admin-settings/<section>.php via a literal
 * slug → file map (never concatenate the request into an include path).
 * Nextcloud Administration uses settingsSection=all (full form parity).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');

$settings = $_['settings'] ?? [];
$availableGroups = is_array($_['availableGroups'] ?? null) ? $_['availableGroups'] : [];
$availableAppAdmins = is_array($_['availableAppAdmins'] ?? null) ? $_['availableAppAdmins'] : [];
$availableAccessUsers = is_array($_['availableAccessUsers'] ?? null) ? $_['availableAccessUsers'] : [];
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$apiSettingsUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.admin.updateAdminSettings');
$monthClosureReopenUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.month_closure.reopen');
$adminUsersListUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.admin.getUsers');
$settingsShell = (string)($_['settingsShell'] ?? 'app');
$isNcAdminShell = $settingsShell === 'nextcloud';
$settingsSection = (string)($_['settingsSection'] ?? \OCA\ArbeitszeitCheck\Service\AdminSettingsSectionCatalog::DEFAULT_SECTION);
$settingsPages = is_array($_['settingsPages'] ?? null) ? $_['settingsPages'] : [];
$inAppAdminSettingsUrl = (string)($_['inAppAdminSettingsUrl'] ?? $urlGenerator->linkToRoute(
	'arbeitszeitcheck.admin.settingsSection',
	['section' => \OCA\ArbeitszeitCheck\Service\AdminSettingsSectionCatalog::DEFAULT_SECTION]
));
$sectionFiles = [
	'access' => 'access.php',
	'compliance' => 'compliance.php',
	'time-recording' => 'time-recording.php',
	'time-approvals' => 'time-approvals.php',
	'exports' => 'exports.php',
	'month-closure' => 'month-closure.php',
	'hours' => 'hours.php',
	'regional' => 'regional.php',
	'retention' => 'retention.php',
];

$renderAll = $isNcAdminShell || $settingsSection === \OCA\ArbeitszeitCheck\Service\AdminSettingsSectionCatalog::SECTION_ALL;
$formScope = $renderAll
	? \OCA\ArbeitszeitCheck\Service\AdminSettingsSectionCatalog::SECTION_ALL
	: $settingsSection;
// Single-topic pages use the shell H1/lead — hide duplicate card titles (DS §14 / granny test).
// NC mega-form keeps all titles for scanability in one scroll.
$azcSettingsShowCardChrome = $renderAll;
?>

<?php if (!$isNcAdminShell): ?>
<?php include __DIR__ . '/common/page-start.php'; ?>
        <div class="azc-page-stack">
<?php else: ?>
<div class="azc-nc-admin-settings" id="arbeitszeitcheck-nc-admin-settings">
	<div id="azc-live-region" class="azc-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
	<div id="azc-alert-region" class="azc-sr-only" role="alert" aria-live="assertive" aria-atomic="true"></div>
	<?php
	$calloutVariant = 'info';
	$calloutRole = 'note';
	$calloutTitleId = 'azc-nc-admin-settings-note-title';
	$calloutTitle = $l->t('Nextcloud administration view');
	$calloutText = $l->t('This panel keeps every setting for save parity. Prefer the focused pages in the app — use the chips below to open one topic at a time.');
	$calloutExtraClass = 'azc-nc-admin-settings__banner';
	$calloutActions = [[
		'href' => $inAppAdminSettingsUrl,
		'label' => $l->t('Open Access in app'),
		'class' => 'azc-btn azc-btn--primary',
	]];
	include __DIR__ . '/common/alert-callout.php';
	?>
<?php endif; ?>

        <div class="section<?php echo $isNcAdminShell ? ' azc-nc-admin-settings__form' : ''; ?>">
<?php if (isset($_['error']) && !empty($_['error'])): ?>
                <?php
                $error = (string)$_['error'];
                $calloutVariant = 'danger';
                $calloutRole = 'alert';
                $calloutBanner = false;
                $calloutIcon = 'circle-alert';
                $calloutTitle = $l->t('An error occurred');
                if (strpos($error, 'Exception') !== false || strpos($error, 'Error') !== false || strpos($error, 'SQL') !== false) {
                    $calloutText = $l->t('Please try again. If the problem persists, contact your administrator.');
                } else {
                    $calloutText = $error;
                }
                include __DIR__ . '/common/alert-callout.php';
                ?>
            <?php endif; ?>

            <div class="azc-settings-layout azc-admin-settings-layout<?php echo $renderAll ? ' azc-admin-settings-layout--all' : ' azc-admin-settings-layout--section'; ?>">
				<?php if ($settingsPages !== []): ?>
					<?php include __DIR__ . '/common/azc-admin-settings-nav.php'; ?>
				<?php endif; ?>
            <div class="azc-settings-layout__main">
            <form id="admin-settings-form" class="form admin-settings-form" method="post" action="#" novalidate
                  data-settings-section="<?php p($formScope); ?>"
                  data-initial-country="<?php p($settings['country'] ?? \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_DE); ?>">
                <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken'] ?? ''); ?>">
                <input type="hidden" name="settings_section" value="<?php p($formScope); ?>">

				<?php
				$includeSection = static function (string $slug) use (
					$sectionFiles,
					$settings,
					$availableGroups,
					$availableAppAdmins,
					$availableAccessUsers,
					$l,
					$_,
					$urlGenerator,
					$azcSettingsShowCardChrome,
					$renderAll
				): void {
					if ($slug === 'projectcheck') {
						include __DIR__ . '/partials/projectcheck-admin-settings-section.php';
						return;
					}
					if (!isset($sectionFiles[$slug])) {
						throw new \RuntimeException('ArbeitszeitCheck admin settings: unknown section in dispatcher.');
					}
					include __DIR__ . '/partials/admin-settings/' . $sectionFiles[$slug];
				};

				if ($renderAll) {
					foreach (array_keys($sectionFiles) as $slug) {
						$includeSection($slug);
					}
					$includeSection('projectcheck');
				} else {
					if (!isset($sectionFiles[$settingsSection]) && $settingsSection !== 'projectcheck') {
						throw new \RuntimeException('ArbeitszeitCheck admin settings: unknown section reached the template dispatcher.');
					}
					$includeSection($settingsSection);
				}
				?>

                <div class="azc-admin-settings-form__actions azc-admin-settings-form__actions--sticky" role="group" aria-labelledby="admin-settings-actions-heading">
                    <h2 id="admin-settings-actions-heading" class="visually-hidden"><?php p($l->t('Save')); ?></h2>
                    <div id="admin-settings-live" class="admin-settings-live" role="status" aria-live="polite" aria-atomic="true"></div>
                    <div class="azc-admin-settings-form__footer">
                        <button type="submit"
                            class="azc-btn azc-btn--primary azc-btn--touch"
                            id="admin-settings-save"
                            aria-label="<?php p($l->t('Save this page')); ?>"
                            title="<?php p($l->t('Save changes on this page')); ?>">
                            <?php p($l->t('Save')); ?>
                        </button>
						<?php if ($isNcAdminShell): ?>
                        <a href="<?php p($inAppAdminSettingsUrl); ?>"
                            class="azc-btn azc-btn--secondary azc-btn--touch"
                            aria-label="<?php p($l->t('Open full settings in the app without saving')); ?>"
                            title="<?php p($l->t('Open the full ArbeitszeitCheck admin settings in the app')); ?>">
                            <?php p($l->t('Open in app')); ?>
                        </a>
						<?php endif; ?>
                    </div>
                </div>
            </form>
            </div><!-- /.azc-settings-layout__main -->
            </div><!-- /.azc-settings-layout -->
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.adminSettingsApiUrl = <?php echo json_encode($apiSettingsUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.monthClosureReopenUrl = <?php echo json_encode($monthClosureReopenUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.adminUsersListUrl = <?php echo json_encode($adminUsersListUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.adminSettingsPages = <?php echo json_encode($settingsPages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
window.ArbeitszeitCheck.l10n.settingsSavedSuccessfully = <?php echo json_encode($l->t('Settings saved successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.failedToSaveSettings = <?php echo json_encode($l->t('Failed to save settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.errorSavingSettings = <?php echo json_encode($l->t('An error occurred while saving settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.maxDailyHoursRange = <?php echo json_encode($l->t('Maximum daily hours must be between 1 and 24'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.minRestPeriodRange = <?php echo json_encode($l->t('Minimum rest period must be between 1 and 24 hours'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.defaultWorkingHoursRange = <?php echo json_encode($l->t('Default working hours must be between 1 and 24'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.retentionPeriodRange = <?php echo json_encode($l->t('Retention period must be between 1 and 10 years'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.carryoverMonthRange = <?php echo json_encode($l->t('Carryover expiry month must be between 1 and 12'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.carryoverDayRange = <?php echo json_encode($l->t('Carryover expiry day must be between 1 and 31'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.maxCarryoverDaysRange = <?php echo json_encode($l->t('Maximum carryover days must be empty (unlimited) or between 0 and 366'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.valueBetweenMinMax = <?php echo json_encode($l->t('Value must be between {min} and {max}'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.monthReopenFillAll = <?php echo json_encode($l->t('Please select an employee, and enter year, month, and a reason.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.loadingEllipsis = <?php echo json_encode($l->t('Loading…'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.loading = <?php echo json_encode($l->t('Loading…'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.noUsersFound = <?php echo json_encode($l->t('No matching users found'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.typeToSearch = <?php echo json_encode($l->t('Type at least 2 characters to search for a person.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.searchError = <?php echo json_encode($l->t('User search failed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.employeeSelected = <?php echo json_encode($l->t('Selected: %s', ['%s']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.resultsCount = <?php echo json_encode($l->t('%n results', ['%n']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.monthReopenConfirm = <?php echo json_encode($l->t('Reopen this finalized month? The employee will be able to edit times again until the month is finalized once more.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.monthReopenSuccess = <?php echo json_encode($l->t('Month reopened.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.accessGroupsSelected = <?php echo json_encode($l->t('%s group(s) selected', ['%s']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.accessGroupsNone = <?php echo json_encode($l->t('No groups selected.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.accessUsersSelected = <?php echo json_encode($l->t('%s user(s) selected', ['%s']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.accessUsersNone = <?php echo json_encode($l->t('No individual users selected.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.appAdminsSelected = <?php echo json_encode($l->t('%s app admin(s) selected', ['%s']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.appAdminsAllAdmins = <?php echo json_encode($l->t('No app admins selected (all Nextcloud admins are allowed).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.timeCaptureAtLeastOneOrg = <?php echo json_encode($l->t('Enable clock in/out or manual time entries — at least one method is required for the organisation.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<?php if (!$isNcAdminShell): ?>
        </div><!-- /.azc-page-stack -->
<?php include __DIR__ . '/common/page-end.php'; ?>
<?php else: ?>
</div>
<?php endif; ?>
