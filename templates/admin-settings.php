<?php

declare(strict_types=1);

/**
 * Admin settings template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */


/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');

$settings = $_['settings'] ?? [];
$availableGroups = is_array($_['availableGroups'] ?? null) ? $_['availableGroups'] : [];
$availableAppAdmins = is_array($_['availableAppAdmins'] ?? null) ? $_['availableAppAdmins'] : [];
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$apiSettingsUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.admin.updateAdminSettings');
$monthClosureReopenUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.month_closure.reopen');
$adminUsersListUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.admin.getUsers');
$settingsShell = (string)($_['settingsShell'] ?? 'app');
$isNcAdminShell = $settingsShell === 'nextcloud';
$inAppAdminSettingsUrl = (string)($_['inAppAdminSettingsUrl'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.admin.settings'));
$cancelUrl = $isNcAdminShell
	? $inAppAdminSettingsUrl
	: $urlGenerator->linkToRoute('arbeitszeitcheck.page.index');
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
	$calloutText = $l->t('These settings are the same as in the ArbeitszeitCheck app. Use the full app view for navigation to all admin tools (employees, holidays, audit log, and more).');
	$calloutExtraClass = 'azc-nc-admin-settings__banner';
	$calloutActions = [[
		'href' => $inAppAdminSettingsUrl,
		'label' => $l->t('Open full settings in app'),
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

            <div class="azc-settings-layout">
                <?php
                $jumpNavAriaLabel = $l->t('Jump to settings sections');
                $projectCheckAvailable = !empty($_['projectCheckAvailable']);
                $jumpNavItems = [
                    ['href' => '#section-access-heading', 'label' => $l->t('Access control')],
                    ['href' => '#section-compliance-heading', 'label' => $l->t('Compliance and working time rules')],
                    ['href' => '#section-time-capture-heading', 'label' => $l->t('Time recording methods')],
                    ['href' => '#section-time-approval-heading', 'label' => $l->t('Time entries and approval')],
                    ['href' => '#section-export-heading', 'label' => $l->t('Exports and reporting')],
                    ['href' => '#section-month-closure-heading', 'label' => $l->t('Month closure (revision-safe)')],
                    ['href' => '#section-hours-heading', 'label' => $l->t('Daily hours and rest periods')],
                    ['href' => '#section-regional-heading', 'label' => $l->t('Region and holidays')],
                    ['href' => '#section-retention-heading', 'label' => $l->t('Data retention')],
                    ['href' => '#section-projectcheck-heading', 'label' => $l->t('ProjectCheck connection')],
                    ['href' => '#azc-support-us-title', 'label' => $l->t('Support & us')],
                ];
                include __DIR__ . '/common/azc-jump-nav.php';
                ?>
            <form id="admin-settings-form" class="form admin-settings-form" method="post" action="#" novalidate>
                <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken'] ?? ''); ?>">
                <section class="azc-card admin-settings-section" aria-labelledby="section-access-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-access-heading" class="azc-card__title"><?php p($l->t('Access control')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Choose who may administer ArbeitszeitCheck and which Nextcloud groups may use the app.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                    <div class="form-group">
                        <?php $selectedAppAdmins = is_array($settings['appAdminUserIds'] ?? null) ? $settings['appAdminUserIds'] : []; ?>
                        <label for="appAdminUsersSearch" class="form-label"><?php p($l->t('ArbeitszeitCheck app administrators')); ?></label>
                        <input type="text"
                               id="appAdminUsersSearch"
                               class="form-input"
                               autocomplete="off"
                               spellcheck="false"
                               placeholder="<?php p($l->t('Search administrators...')); ?>"
                               aria-describedby="appAdminUsers-help appAdminUsers-note appAdminUsersCount">
                        <p id="appAdminUsersCount" class="form-help form-help--note" aria-live="polite">
                            <?php
                            $selectedAdminCount = count($selectedAppAdmins);
                            p($selectedAdminCount > 0
                                ? $l->t('%d app admin(s) selected', [$selectedAdminCount])
                                : $l->t('No app admins selected (all Nextcloud admins are allowed).'));
                            ?>
                        </p>
                        <div id="appAdminUsersList" class="access-groups-list" role="group" aria-label="<?php p($l->t('App administrator selection')); ?>">
                            <?php foreach ($availableAppAdmins as $adminOption): ?>
                                <?php
                                $adminId = (string)($adminOption['id'] ?? '');
                                if ($adminId === '') {
                                    continue;
                                }
                                $adminDisplayName = (string)($adminOption['displayName'] ?? $adminId);
                                $isSelectedAdmin = in_array($adminId, $selectedAppAdmins, true);
                                ?>
                                <label class="access-groups-item" data-app-admin-search="<?php p(strtolower($adminDisplayName . ' ' . $adminId)); ?>">
                                    <input type="checkbox"
                                           name="appAdminUserIds[]"
                                           value="<?php p($adminId); ?>"
                                           <?php echo $isSelectedAdmin ? 'checked' : ''; ?>>
                                    <span class="access-groups-item__label"><?php p($adminDisplayName); ?></span>
                                    <span class="access-groups-item__meta"><?php p($adminId); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p id="appAdminUsersEmpty" class="form-help form-help--note" hidden>
                            <?php p($l->t('No matching administrators found for your search.')); ?>
                        </p>
                        <p id="appAdminUsers-help" class="form-help">
                            <?php p($l->t('Select who can administer ArbeitszeitCheck. If empty, every Nextcloud admin can administer the app (backward compatible default).')); ?>
                        </p>
                        <p id="appAdminUsers-note" class="form-help form-help--note">
                            <?php p($l->t('Only users in the Nextcloud admin group are listed. Changes take effect immediately after saving.')); ?>
                        </p>
                    </div>
                    <div class="form-group">
                        <?php $selectedAccessGroups = is_array($settings['accessAllowedGroups'] ?? null) ? $settings['accessAllowedGroups'] : []; ?>
                        <label for="accessAllowedGroupsSearch" class="form-label"><?php p($l->t('Allowed Nextcloud groups')); ?></label>
                        <input type="text"
                               id="accessAllowedGroupsSearch"
                               class="form-input"
                               autocomplete="off"
                               spellcheck="false"
                               placeholder="<?php p($l->t('Search groups...')); ?>"
                               aria-describedby="accessAllowedGroups-help accessAllowedGroups-note accessAllowedGroupsCount">
                        <p id="accessAllowedGroupsCount" class="form-help form-help--note" aria-live="polite">
                            <?php
                            $selectedCount = count($selectedAccessGroups);
                            p($selectedCount > 0
                                ? $l->t('%d group(s) selected', [$selectedCount])
                                : $l->t('No groups selected (all users are allowed).'));
                            ?>
                        </p>
                        <div id="accessAllowedGroupsList" class="access-groups-list" role="group" aria-label="<?php p($l->t('Group selection')); ?>">
                            <?php foreach ($availableGroups as $groupOption): ?>
                                <?php
                                $groupId = (string)($groupOption['id'] ?? '');
                                if ($groupId === '') {
                                    continue;
                                }
                                $groupDisplayName = (string)($groupOption['displayName'] ?? $groupId);
                                $isSelected = in_array($groupId, $selectedAccessGroups, true);
                                ?>
                                <label class="access-groups-item" data-access-group-search="<?php p(strtolower($groupDisplayName . ' ' . $groupId)); ?>">
                                    <input type="checkbox"
                                           name="accessAllowedGroups[]"
                                           value="<?php p($groupId); ?>"
                                           <?php echo $isSelected ? 'checked' : ''; ?>>
                                    <span class="access-groups-item__label"><?php p($groupDisplayName); ?></span>
                                    <span class="access-groups-item__meta"><?php p($groupId); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p id="accessAllowedGroupsEmpty" class="form-help form-help--note" hidden>
                            <?php p($l->t('No matching groups found for your search.')); ?>
                        </p>
                        <p id="accessAllowedGroups-help" class="form-help">
                            <?php p($l->t('Leave empty to allow all users (default behavior). If one or more groups are selected, only members of these groups can use this app. Administrators are always allowed.')); ?>
                        </p>
                        <p id="accessAllowedGroups-note" class="form-help form-help--note">
                            <?php p($l->t('Select one or more groups. The rule applies immediately after saving settings.')); ?>
                        </p>
                    </div>
                    </div><!-- /.azc-card__body -->
                </section>
                <section class="azc-card admin-settings-section" aria-labelledby="section-compliance-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-compliance-heading" class="azc-card__title"><?php p($l->t('Compliance and working time rules')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Define how ArbeitszeitCheck validates bookings, handles break edge cases, and enforces legal limits.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                    <h3 class="admin-settings-subsection__title"><?php p($l->t('Compliance checks')); ?></h3>
                <div class="form-group">
                    <label class="form-label"><?php p($l->t('Configured timezone')); ?></label>
                    <p class="form-help">
                        <strong><?php p(\OCP\Server::get(\OCP\IConfig::class)->getAppValue('arbeitszeitcheck', 'app_timezone', 'Europe/Berlin')); ?></strong>
                        — <?php p($l->t('All clock-in/out timestamps and exports use this timezone and should match the server PHP timezone setting.')); ?>
                    </p>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="autoComplianceCheck" name="autoComplianceCheck"
                            <?php echo ($settings['autoComplianceCheck'] ?? true) ? 'checked' : ''; ?>
                            aria-describedby="autoComplianceCheck-help">
                        <label for="autoComplianceCheck" class="form-label">
                            <?php p($l->t('Check working time rules automatically')); ?>
                        </label>
                    </div>
                    <p id="autoComplianceCheck-help" class="form-help">
                        <?php p($l->t('The system will automatically check if working hours follow the configured country labour law (ArbZG or AZG). For example, it will warn when required breaks are missing.')); ?>
                    </p>
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="realtimeComplianceCheck" name="realtimeComplianceCheck"
                            <?php echo ($settings['realtimeComplianceCheck'] ?? true) ? 'checked' : ''; ?>
                            aria-describedby="realtimeComplianceCheck-help">
                        <label for="realtimeComplianceCheck" class="form-label">
                            <?php p($l->t('Real-time compliance check when recording')); ?>
                        </label>
                    </div>
                    <p id="realtimeComplianceCheck-help" class="form-help">
                        <?php p($l->t('Checks working times immediately when saving or editing. Disable only if you run compliance checks exclusively via batch processing.')); ?>
                    </p>
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="complianceStrictMode" name="complianceStrictMode"
                            <?php echo ($settings['complianceStrictMode'] ?? false) ? 'checked' : ''; ?>
                            aria-describedby="complianceStrictMode-help">
                        <label for="complianceStrictMode" class="form-label">
                            <?php p($l->t('Strict mode: Violations block saving')); ?>
                        </label>
                    </div>
                    <p id="complianceStrictMode-help" class="form-help">
                        <?php p($l->t('In default mode, violations are shown but saving is still possible. In strict mode, violations prevent saving the time entry.')); ?>
                    </p>
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="enableViolationNotifications" name="enableViolationNotifications"
                            <?php echo ($settings['enableViolationNotifications'] ?? true) ? 'checked' : ''; ?>>
                        <label for="enableViolationNotifications" class="form-label">
                            <?php p($l->t('Send alerts when working time rules are broken')); ?>
                        </label>
                    </div>
                    <p class="form-help">
                        <?php p($l->t('When someone works too many hours or doesn\'t take required breaks, the system will send a notification to managers and the employee.')); ?>
                    </p>
                </div>
                <h3 class="admin-settings-subsection__title"><?php p($l->t('Absence workflow rules')); ?></h3>
                <p class="form-help form-help--block">
                    <?php p($l->t('Define for which absence types a substitute must be chosen before a request can be submitted.')); ?>
                </p>
                <fieldset class="form-fieldset" aria-labelledby="require-substitute-legend">
                    <legend id="require-substitute-legend" class="form-legend"><?php p($l->t('Absences: Substitute required')); ?></legend>
                    <p class="form-help form-help--block">
                        <?php p($l->t('For the selected absence types, a substitute must be designated.')); ?>
                    </p>
                    <?php
                    $requireTypes = $settings['requireSubstituteTypes'] ?? [];
                    $absenceTypesForSubstitute = [
                        'vacation' => $l->t('Vacation'),
                        'sick_leave' => $l->t('Sick leave'),
                        'personal_leave' => $l->t('Personal reasons'),
                        'parental_leave' => $l->t('Parental leave'),
                        'special_leave' => $l->t('Special leave'),
                        'unpaid_leave' => $l->t('Unpaid leave'),
                        'home_office' => $l->t('Home office'),
                        'business_trip' => $l->t('Business trip'),
                    ];
                    foreach ($absenceTypesForSubstitute as $typeKey => $typeLabel):
                        $checked = in_array($typeKey, $requireTypes, true);
                    ?>
                        <div class="form-group form-group--inline">
                            <div class="form-checkbox">
                                <input type="checkbox" id="requireSubstitute_<?php p($typeKey); ?>" name="requireSubstituteTypes[]" value="<?php p($typeKey); ?>"
                                    <?php echo $checked ? 'checked' : ''; ?>
                                    aria-describedby="require-substitute-legend">
                                <label for="requireSubstitute_<?php p($typeKey); ?>" class="form-label"><?php p($typeLabel); ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <h3 class="admin-settings-subsection__title"><?php p($l->t('Break fallback behavior')); ?></h3>
                <p class="form-help form-help--block">
                    <?php p($l->t('Use these settings to prevent users from staying in an open break state for many hours by accident.')); ?>
                </p>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="breakAutoFallbackEnabled" name="breakAutoFallbackEnabled"
                            <?php echo ($settings['breakAutoFallbackEnabled'] ?? true) ? 'checked' : ''; ?>
                            aria-describedby="breakAutoFallbackEnabled-help breakAutoFallbackMinutes-help">
                        <label for="breakAutoFallbackEnabled" class="form-label">
                            <?php p($l->t('Automatic fallback for very long breaks')); ?>
                        </label>
                    </div>
                    <p id="breakAutoFallbackEnabled-help" class="form-help">
                        <?php p($l->t('If a break is left open for too long, the system automatically clocks out to prevent permanent pause status.')); ?>
                    </p>
                </div>
                <div class="form-group">
                    <label for="breakAutoFallbackMinutes" class="form-label"><?php p($l->t('Auto clock-out after break (minutes)')); ?></label>
                    <input type="number"
                        class="form-input"
                        id="breakAutoFallbackMinutes"
                        name="breakAutoFallbackMinutes"
                        min="15"
                        max="720"
                        step="1"
                        value="<?php p((string)($settings['breakAutoFallbackMinutes'] ?? 180)); ?>"
                        aria-describedby="breakAutoFallbackMinutes-help">
                    <p id="breakAutoFallbackMinutes-help" class="form-help">
                        <?php p($l->t('Recommended: 120 to 240 minutes. After this threshold, an open break is automatically finalized by clocking out.')); ?>
                    </p>
                </div>
                <div class="form-row form-row--inline" role="group" aria-labelledby="breakAutoFallbackEnabled">
                    <div class="form-group">
                        <label for="breakAutoFallbackFlexWindowStart" class="form-label"><?php p($l->t('Flex policy quiet window start hour')); ?></label>
                        <input type="number"
                            class="form-input"
                            id="breakAutoFallbackFlexWindowStart"
                            name="breakAutoFallbackFlexWindowStart"
                            min="0"
                            max="23"
                            step="1"
                            value="<?php p((string)($settings['breakAutoFallbackFlexWindowStart'] ?? 11)); ?>">
                    </div>
                    <div class="form-group">
                        <label for="breakAutoFallbackFlexWindowEnd" class="form-label"><?php p($l->t('Flex policy quiet window end hour')); ?></label>
                        <input type="number"
                            class="form-input"
                            id="breakAutoFallbackFlexWindowEnd"
                            name="breakAutoFallbackFlexWindowEnd"
                            min="1"
                            max="24"
                            step="1"
                            value="<?php p((string)($settings['breakAutoFallbackFlexWindowEnd'] ?? 16)); ?>">
                    </div>
                </div>
                <p class="form-help form-help--note">
                    <?php p($l->t('For non-shift models (flex policy), automatic clock-out is suppressed inside this daytime window. Shift work remains strict.')); ?>
                </p>
                </div><!-- /.azc-card__body -->
                </section>

                <section class="azc-card admin-settings-section" aria-labelledby="section-time-capture-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-time-capture-heading" class="azc-card__title"><?php p($l->t('Time recording methods')); ?></h2>
                            <p id="section-time-capture-intro" class="azc-card__lead">
                                <?php p($l->t('Choose which ways employees may record working time across your organisation. These are administrator settings — employees cannot change them. You can restrict individual employees further under Employees.')); ?>
                            </p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                    <?php
                    $orgClockStampingEnabled = (bool)($settings['clockStampingEnabled'] ?? true);
                    $orgManualTimeEntryEnabled = (bool)($settings['manualTimeEntryEnabled'] ?? true);
                    $employeesUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.admin.users');
                    if ($orgClockStampingEnabled && $orgManualTimeEntryEnabled) {
                        $orgCaptureStatusBadge = $l->t('Both methods active');
                        $orgCaptureStatusText = $l->t('Employees can clock in/out on the dashboard and add manual time entries.');
                        $orgCaptureStatusVariant = 'info';
                    } elseif ($orgClockStampingEnabled) {
                        $orgCaptureStatusBadge = $l->t('Stamping only');
                        $orgCaptureStatusText = $l->t('Employees must use the punch clock. Manual time entries are hidden for everyone.');
                        $orgCaptureStatusVariant = 'success';
                    } else {
                        $orgCaptureStatusBadge = $l->t('Manual entries only');
                        $orgCaptureStatusText = $l->t('Employees add completed work blocks by date and time. The punch clock is hidden for everyone.');
                        $orgCaptureStatusVariant = 'warning';
                    }
                    ?>
                    <div class="admin-time-capture__status" id="admin-time-capture-status" role="status" aria-live="polite" aria-atomic="true">
                        <span class="azc-badge azc-badge--<?php p($orgCaptureStatusVariant); ?>" id="admin-time-capture-status-badge"><?php p($orgCaptureStatusBadge); ?></span>
                        <p id="admin-time-capture-status-text" class="admin-time-capture__status-text"><?php p($orgCaptureStatusText); ?></p>
                    </div>
                    <fieldset class="form-fieldset admin-time-capture-fieldset" aria-labelledby="section-time-capture-heading" aria-describedby="section-time-capture-intro admin-time-capture-status admin-time-capture-error admin-time-capture-employees-note">
                        <legend class="visually-hidden"><?php p($l->t('Organisation-wide time recording methods')); ?></legend>
                        <div class="admin-time-capture__grid" role="group">
                            <label class="admin-time-capture__card">
                                <input type="checkbox"
                                       id="clockStampingEnabled"
                                       name="clockStampingEnabled"
                                       value="1"
                                       class="admin-time-capture__checkbox"
                                       <?php echo $orgClockStampingEnabled ? 'checked' : ''; ?>
                                       aria-describedby="clockStampingEnabled-help">
                                <span class="admin-time-capture__card-body">
                                    <span class="admin-time-capture__card-title"><?php p($l->t('Clock in / out (stamping)')); ?></span>
                                    <span class="admin-time-capture__card-text"><?php p($l->t('Live punch clock on the dashboard and in the mobile app.')); ?></span>
                                </span>
                            </label>
                            <label class="admin-time-capture__card">
                                <input type="checkbox"
                                       id="manualTimeEntryEnabled"
                                       name="manualTimeEntryEnabled"
                                       value="1"
                                       class="admin-time-capture__checkbox"
                                       <?php echo $orgManualTimeEntryEnabled ? 'checked' : ''; ?>
                                       aria-describedby="manualTimeEntryEnabled-help">
                                <span class="admin-time-capture__card-body">
                                    <span class="admin-time-capture__card-title"><?php p($l->t('Manual time entries')); ?></span>
                                    <span class="admin-time-capture__card-text"><?php p($l->t('Add completed work blocks by date and time in the web app.')); ?></span>
                                </span>
                            </label>
                        </div>
                        <p id="clockStampingEnabled-help" class="form-help">
                            <?php p($l->t('Turn off stamping if your organisation records hours only via manual entries — the punch clock disappears for all employees.')); ?>
                        </p>
                        <p id="manualTimeEntryEnabled-help" class="form-help">
                            <?php p($l->t('Turn off manual entries if everyone must use the punch clock only.')); ?>
                        </p>
                        <p id="admin-time-capture-error" class="form-error admin-time-capture__error" role="alert" hidden></p>
                        <p id="admin-time-capture-employees-note" class="form-help form-help--note">
                            <?php
                            print_unescaped($l->t(
                                'Need different rules for one person? Open <a href="%s">Employees</a>, edit the person, and adjust the Time recording section.',
                                [$employeesUrl]
                            ));
                            ?>
                        </p>
                    </fieldset>
                    </div><!-- /.azc-card__body -->
                </section>

                <section class="azc-card admin-settings-section" aria-labelledby="section-time-approval-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-time-approval-heading" class="azc-card__title"><?php p($l->t('Time entries and approval')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Both options are off by default (legacy behaviour). Enable only when your organisation requires four-eyes approval.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                    <fieldset class="form-fieldset" aria-labelledby="time-changes-legend">
                        <legend id="time-changes-legend" class="form-legend"><?php p($l->t('Changes to existing entries')); ?></legend>
                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" id="timeEntryChangesRequireApproval" name="timeEntryChangesRequireApproval"
                                    <?php echo !empty($settings['timeEntryChangesRequireApproval']) ? 'checked' : ''; ?>
                                    aria-describedby="timeEntryChangesRequireApproval-help">
                                <label for="timeEntryChangesRequireApproval" class="form-label"><?php p($l->t('Require manager approval for edits to completed time entries')); ?></label>
                            </div>
                            <p id="timeEntryChangesRequireApproval-help" class="form-help"><?php p($l->t('When enabled, employees must use the correction request workflow instead of direct edits.')); ?></p>
                        </div>
                    </fieldset>
                    <fieldset class="form-fieldset" aria-labelledby="manual-entries-legend">
                        <legend id="manual-entries-legend" class="form-legend"><?php p($l->t('New manual entries')); ?></legend>
                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" id="manualTimeEntriesRequireApproval" name="manualTimeEntriesRequireApproval"
                                    <?php echo !empty($settings['manualTimeEntriesRequireApproval']) ? 'checked' : ''; ?>
                                    aria-describedby="manualTimeEntriesRequireApproval-help">
                                <label for="manualTimeEntriesRequireApproval" class="form-label"><?php p($l->t('Require manager approval for new manual time entries')); ?></label>
                            </div>
                            <p id="manualTimeEntriesRequireApproval-help" class="form-help"><?php p($l->t('When enabled, manual entries stay pending until a manager approves them (excluded from overtime until completed).')); ?></p>
                        </div>
                    </fieldset>
                    </div><!-- /.azc-card__body -->
                </section>

                <section class="azc-card admin-settings-section" aria-labelledby="section-export-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-export-heading" class="azc-card__title"><?php p($l->t('Exports and reporting')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Control how entries that run across midnight appear in CSV and JSON exports.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                    <div class="form-group">
                        <div class="form-checkbox">
                            <input type="checkbox"
                                   id="exportMidnightSplitEnabled"
                                   name="exportMidnightSplitEnabled"
                                   <?php echo ($settings['exportMidnightSplitEnabled'] ?? true) ? 'checked' : ''; ?>
                                   aria-describedby="exportMidnightSplitEnabled-help">
                            <label for="exportMidnightSplitEnabled" class="form-label">
                                <?php p($l->t('Split overnight entries at midnight in CSV/JSON export')); ?>
                            </label>
                        </div>
                        <p id="exportMidnightSplitEnabled-help" class="form-help">
                            <?php p($l->t('When enabled, entries that run across midnight (for example 22:00–06:00) are shown as two lines in the export (before and after 00:00). This is only a visual/export split – all internal working time and labour-law compliance checks continue to use the original, unsplit entry.')); ?>
                        </p>
                        <p id="exportMidnightSplitEnabled-example" class="form-help form-help--note">
                            <?php p($l->t('Example for CSV/JSON long layout: row 1 has date = first calendar day, start_time 22:00:00, end_time 23:59:59; row 2 has date = next day, start_time 00:00:00, end_time 06:00:00. Column working_hours is the work time share per segment (the segments sum to the full entry). This is not an extra "break" row — rest breaks remain tied to the original booking; split rows may show empty break columns.')); ?>
                        </p>
                        <p class="form-help form-help--note" id="exportDatevMidnight-note">
                            <?php p($l->t('DATEV export always uses full, unsplit time entries as required by the DATEV payroll format. CSV and JSON exports respect the midnight split setting above when it is enabled.')); ?>
                        </p>
                    </div>
                    </div><!-- /.azc-card__body -->
                </section>

                <?php $monthClosureOn = !empty($settings['monthClosureEnabled']); ?>
                <section class="azc-card admin-settings-section" aria-labelledby="section-month-closure-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-month-closure-heading" class="azc-card__title"><?php p($l->t('Month closure (revision-safe)')); ?></h2>
                            <p class="azc-card__lead" id="month-closure-section-intro">
                                <?php p($l->t('Employees seal a calendar month when work is complete. Administrators can reopen a sealed month if corrections are needed.')); ?>
                            </p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                    <div class="form-group">
                        <div class="form-checkbox">
                            <input type="checkbox"
                                   id="monthClosureEnabled"
                                   name="monthClosureEnabled"
                                   <?php echo $monthClosureOn ? 'checked' : ''; ?>
                                   aria-describedby="month-closure-section-intro monthClosureEnabled-help">
                            <label for="monthClosureEnabled" class="form-label">
                                <?php p($l->t('Enable revision-safe month finalization')); ?>
                            </label>
                        </div>
                        <p id="monthClosureEnabled-help" class="form-help">
                            <?php p($l->t('When enabled, employees can finalize a calendar month to create a tamper-evident snapshot (hash) and PDF. Finalized months stay locked even if this option is turned off later. Reopening a month is limited to administrators.')); ?>
                        </p>
                    </div>
                    <div class="form-group">
                        <label for="monthClosureGraceDaysAfterEom" class="form-label"><?php p($l->t('Grace days after month end')); ?></label>
                        <input type="number"
                            class="form-input"
                            id="monthClosureGraceDaysAfterEom"
                            name="monthClosureGraceDaysAfterEom"
                            min="0"
                            max="90"
                            step="1"
                            value="<?php p((string)($settings['monthClosureGraceDaysAfterEom'] ?? 0)); ?>"
                            aria-describedby="month-closure-section-intro monthClosureGraceDaysAfterEom-help monthClosureGraceDaysAfterEom-editable-note">
                        <p id="monthClosureGraceDaysAfterEom-help" class="form-help">
                            <?php p($l->t('Number of calendar days after the last day of each month for employees to finalize manually. If the month is still open after that, a daily job seals it automatically (same snapshot as manual finalize). Pending time entry or absence approvals block auto-finalization. Use 0 to disable automatic sealing.')); ?>
                        </p>
                        <p id="monthClosureGraceDaysAfterEom-editable-note" class="form-help form-help--note">
                            <?php p($l->t('You can set this even while month finalization is disabled; the value is saved with “Save all settings” and applies when you enable month finalization above.')); ?>
                        </p>
                    </div>

                    <fieldset class="form-fieldset" aria-labelledby="month-closure-reopen-legend" aria-describedby="month-closure-reopen-intro month-closure-reopen-separate-notice">
                        <legend id="month-closure-reopen-legend" class="form-legend"><?php p($l->t('Reopen a finalized month (admin)')); ?></legend>
                        <p class="form-help form-help--block" id="month-closure-reopen-intro">
                            <?php p($l->t('If a calendar month was finalized by mistake or a correction is required, you can reopen it here as an administrator for the employee whose month should be opened again. Use the search field to select that person (their Nextcloud account). You must enter a reason; the audit log records your administrator action, the reason, and who the change applies to. Previous snapshot rows remain in the database for traceability.')); ?>
                        </p>
                        <p class="form-help form-help--block form-help--note" id="month-closure-reopen-separate-notice">
                            <?php p($l->t('The "Reopen month" button runs immediately and only performs this reopening step. It is not saved with "Save all settings" at the bottom of the page.')); ?>
                        </p>
                        <div class="form-group month-reopen-user-picker">
                            <label for="monthClosureReopenUserSearch" class="form-label">
                                <?php p($l->t('Employee')); ?>
                                <span class="required-star" aria-hidden="true">*</span>
                            </label>
                            <input type="hidden" id="monthClosureReopenUserId" name="monthClosureReopenUserId" value="" required>
                            <div class="user-picker" id="month-reopen-picker">
                                <input type="search"
                                    id="monthClosureReopenUserSearch"
                                    class="form-input user-picker__search"
                                    autocomplete="off"
                                    autocapitalize="none"
                                    spellcheck="false"
                                    placeholder="<?php p($l->t('Search by name or user ID…')); ?>"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="monthClosureReopenUserListbox"
                                    aria-describedby="monthClosureReopenUserSearch-help monthClosureReopenUserStatus"
                                    aria-required="true">
                                <div id="monthClosureReopenUserListbox"
                                    class="user-picker__list"
                                    role="listbox"
                                    hidden
                                    aria-label="<?php p($l->t('Matching users')); ?>"></div>
                                <p id="monthClosureReopenUserStatus" class="azc-sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
                            </div>
                            <p id="monthClosureReopenUserSearch-help" class="form-help">
                                <?php p($l->t('Type at least 2 characters, then select the employee whose finalized month you are reopening.')); ?>
                            </p>
                        </div>
                        <div class="form-row form-row--inline" role="group" aria-labelledby="month-closure-reopen-legend" aria-describedby="month-closure-reopen-intro month-closure-reopen-separate-notice">
                            <div class="form-group">
                                <label for="monthClosureReopenYear" class="form-label"><?php p($l->t('Year')); ?></label>
                                <input type="number" id="monthClosureReopenYear" class="form-input" min="1970" max="2100" step="1" aria-required="true">
                            </div>
                            <div class="form-group">
                                <label for="monthClosureReopenMonth" class="form-label"><?php p($l->t('Month')); ?></label>
                                <input type="number" id="monthClosureReopenMonth" class="form-input" min="1" max="12" step="1" aria-required="true">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="monthClosureReopenReason" class="form-label"><?php p($l->t('Reason (required)')); ?></label>
                            <textarea id="monthClosureReopenReason" class="form-input" rows="3" aria-required="true" aria-describedby="month-closure-reopen-intro"></textarea>
                        </div>
                        <div class="card-actions card-actions--inline">
                            <button type="button" id="monthClosureReopenBtn" class="btn btn--secondary">
                                <?php p($l->t('Reopen month')); ?>
                            </button>
                        </div>
                        <div id="monthClosureReopenLive" class="form-help" role="status" aria-live="polite" aria-atomic="true"></div>
                    </fieldset>
                    </div><!-- /.azc-card__body -->
                </section>

                <section class="azc-card admin-settings-section" aria-labelledby="section-hours-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-hours-heading" class="azc-card__title"><?php p($l->t('Daily hours and rest periods')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Set baseline working-time limits used for legal checks and default employee setup.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                <div class="form-group">
                    <label for="maxDailyHours" class="form-label">
                        <?php p($l->t('Maximum working hours per day (in hours)')); ?>
                        <span class="form-required" aria-label="<?php p($l->t('required')); ?>">*</span>
                    </label>
                    <input type="number" 
                           id="maxDailyHours" 
                           name="maxDailyHours" 
                           class="form-input <?php echo isset($_['errors']['maxDailyHours']) ? 'form-input--error' : ''; ?>"
                           value="<?php p($settings['maxDailyHours'] ?? 10); ?>" 
                           min="1" 
                           max="24" 
                           step="0.1" 
                           required
                           aria-describedby="maxDailyHours-help <?php echo isset($_['errors']['maxDailyHours']) ? 'maxDailyHours-error' : ''; ?>"
                           aria-invalid="<?php echo isset($_['errors']['maxDailyHours']) ? 'true' : 'false'; ?>">
                    <p id="maxDailyHours-help" class="form-help">
                        <?php p($l->t('Upper limit of daily working time in hours. The suggested default comes from the selected country profile (Germany ArbZG: up to 10; Austria AZG: up to 12). Changing the country does not overwrite a value you already set.')); ?>
                    </p>
                    <?php if (isset($_['errors']['maxDailyHours'])): ?>
                        <?php 
                        $fieldName = 'maxDailyHours';
                        $errorMessage = is_array($_['errors']['maxDailyHours']) ? $_['errors']['maxDailyHours'][0] : $_['errors']['maxDailyHours'];
                        include __DIR__ . '/common/form-error.php';
                        ?>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="minRestPeriod" class="form-label">
                        <?php p($l->t('Minimum rest period between work days (in hours)')); ?>
                        <span class="form-required" aria-label="<?php p($l->t('required')); ?>">*</span>
                    </label>
                    <input type="number" 
                           id="minRestPeriod" 
                           name="minRestPeriod" 
                           class="form-input"
                           value="<?php p($settings['minRestPeriod'] ?? 11); ?>" 
                           min="1" 
                           max="24" 
                           step="0.1" 
                           required
                           aria-describedby="minRestPeriod-help">
                    <p id="minRestPeriod-help" class="form-help">
                        <?php p($l->t('Hours of rest between end of work and next start. German law requires at least 11 hours.')); ?>
                    </p>
                </div>

                <div class="form-group">
                    <label for="defaultWorkingHours" class="form-label">
                        <?php p($l->t('Standard working hours per day')); ?>
                        <span class="form-required" aria-label="<?php p($l->t('required')); ?>">*</span>
                    </label>
                    <input type="number" 
                           id="defaultWorkingHours" 
                           name="defaultWorkingHours" 
                           class="form-input"
                           value="<?php p($settings['defaultWorkingHours'] ?? 8); ?>" 
                           min="1" 
                           max="24" 
                           step="0.01" 
                           required
                           aria-describedby="defaultWorkingHours-help">
                    <p id="defaultWorkingHours-help" class="form-help">
                        <?php p($l->t('Default daily working hours. Used for new employees until individual models are set. Decimal hours are allowed (e.g. 7.74).')); ?>
                    </p>
                </div>
                </div><!-- /.azc-card__body -->
                </section>

                <section class="azc-card admin-settings-section" aria-labelledby="section-regional-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-regional-heading" class="azc-card__title"><?php p($l->t('Country and region')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('First pick the country whose working time law applies, then the default region for public holidays.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                <?php
                $azcCurrentCountry = $settings['country'] ?? \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_DE;
                $azcCurrentState = $settings['germanState']
                    ?? \OCA\ArbeitszeitCheck\Support\RegionRegistry::defaultRegionForCountry($azcCurrentCountry);
                $azcCountryCards = [
                    \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_DE => [
                        'title' => $l->t('Germany'),
                        'text' => $l->t('Working time rules follow the German Working Time Act (ArbZG).'),
                    ],
                    \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_AT => [
                        'title' => $l->t('Austria'),
                        'text' => $l->t('Working time rules follow the Austrian Working Time Act (AZG) and Rest Act (ARG).'),
                    ],
                    \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_CH => [
                        'title' => $l->t('Switzerland'),
                        'text' => $l->t('Working time rules follow the Swiss Labour Act (ArG). Public holidays follow the selected canton.'),
                    ],
                ];
                // Region data for all supported countries so the region list can
                // be rebuilt client-side when the country changes (no auto-save).
                $azcRegionData = ['defaultRegionByCountry' => [], 'regionsByCountry' => []];
                foreach (\OCA\ArbeitszeitCheck\Support\RegionRegistry::supportedCountries() as $azcCountryCode) {
                    $azcRegionData['defaultRegionByCountry'][$azcCountryCode] =
                        \OCA\ArbeitszeitCheck\Support\RegionRegistry::defaultRegionForCountry($azcCountryCode);
                    $azcRegions = [];
                    foreach (\OCA\ArbeitszeitCheck\Support\RegionRegistry::regionsForCountry($azcCountryCode) as $azcRegionCode => $azcRegionMsgid) {
                        $azcRegions[] = ['code' => $azcRegionCode, 'label' => $l->t($azcRegionMsgid)];
                    }
                    $azcRegionData['regionsByCountry'][$azcCountryCode] = $azcRegions;
                }
                ?>
                <script type="application/json" id="azc-region-data"><?php print_unescaped(json_encode($azcRegionData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?></script>
                <fieldset class="form-fieldset" aria-labelledby="country-legend" aria-describedby="country-help">
                    <legend id="country-legend" class="form-legend"><?php p($l->t('In which country does your organisation work?')); ?></legend>
                    <div class="azc-country-grid" role="radiogroup" aria-labelledby="country-legend">
                        <?php foreach ($azcCountryCards as $azcCountryCode => $azcCard): ?>
                        <label class="azc-country-card" for="country-<?php p(strtolower($azcCountryCode)); ?>">
                            <input type="radio"
                                   id="country-<?php p(strtolower($azcCountryCode)); ?>"
                                   name="country"
                                   value="<?php p($azcCountryCode); ?>"
                                   class="azc-country-card__radio"
                                   <?php echo ($azcCurrentCountry === $azcCountryCode) ? 'checked' : ''; ?>
                                   aria-describedby="country-help">
                            <span class="azc-country-card__body">
                                <span class="azc-country-card__title"><?php p($azcCard['title']); ?></span>
                                <span class="azc-country-card__text"><?php p($azcCard['text']); ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p id="country-help" class="form-help">
                        <?php p($l->t('Changing the country does not change the limit values under “Daily hours and rest periods” below — review them after switching. Nothing is saved until you select “Save all settings”.')); ?>
                    </p>
                </fieldset>
                <div id="country-region-live" class="form-help" role="status" aria-live="polite" aria-atomic="true"></div>
                <div class="form-group">
                    <label for="germanState" class="form-label">
                        <?php p($l->t('Default region for public holidays')); ?>
                        <span class="form-required" aria-label="<?php p($l->t('required')); ?>">*</span>
                    </label>
                    <select id="germanState" 
                            name="germanState" 
                            class="form-select" 
                            required
                            aria-describedby="germanState-help">
                        <?php
                        foreach (\OCA\ArbeitszeitCheck\Support\RegionRegistry::regionsForCountry($azcCurrentCountry) as $code => $name) {
                            $selected = ($azcCurrentState === $code) ? ' selected' : '';
                            $label = $l->t($name);
                            echo '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' .
                                htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
                                '</option>';
                        }
                        ?>
                    </select>
                    <p id="germanState-help" class="form-help">
                        <?php p($l->t('Used for statutory holidays and compliance when no specific region is configured for employees or teams.')); ?>
                    </p>
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" 
                               id="statutoryAutoReseed" 
                               name="statutoryAutoReseed" 
                               value="1"
                               <?php echo ($settings['statutoryAutoReseed'] ?? true) ? 'checked' : ''; ?>
                               aria-describedby="statutoryAutoReseed-help">
                        <label for="statutoryAutoReseed" class="form-label">
                            <?php p($l->t('Auto-restore statutory holidays when viewing calendar')); ?>
                        </label>
                    </div>
                    <p id="statutoryAutoReseed-help" class="form-help">
                        <?php p($l->t('When enabled, missing statutory holidays are added when the calendar is viewed. Disable if you want deleted holidays to stay removed.')); ?>
                    </p>
                </div>
                </div><!-- /.azc-card__body -->
                </section>

                <section class="azc-card admin-settings-section" aria-labelledby="section-retention-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-retention-heading" class="azc-card__title"><?php p($l->t('Data retention')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Control how long time-tracking records are kept before automated cleanup.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
                <div class="form-group">
                    <label for="retentionPeriod" class="form-label">
                        <?php p($l->t('Data retention period for time records (in years)')); ?>
                        <span class="form-required" aria-label="<?php p($l->t('required')); ?>">*</span>
                    </label>
                    <input type="number" 
                           id="retentionPeriod" 
                           name="retentionPeriod" 
                           class="form-input"
                           value="<?php p($settings['retentionPeriod'] ?? 2); ?>" 
                           min="1" 
                           max="10" 
                           required
                           aria-describedby="retentionPeriod-help">
                    <p id="retentionPeriod-help" class="form-help">
                        <?php p($l->t('Number of years to keep time tracking data before automatic deletion (typically at least 2 years).')); ?>
                    </p>
                </div>
                </div><!-- /.azc-card__body -->
                </section>

                <?php include __DIR__ . '/partials/projectcheck-admin-settings-section.php'; ?>

                <div class="azc-admin-settings-form__actions" role="group" aria-labelledby="admin-settings-actions-heading">
                    <h2 id="admin-settings-actions-heading" class="visually-hidden"><?php p($l->t('Save and leave')); ?></h2>
                    <div id="admin-settings-live" class="admin-settings-live" role="status" aria-live="polite" aria-atomic="true"></div>
                    <div class="azc-admin-settings-form__footer">
                        <button type="submit"
                            class="azc-btn azc-btn--primary"
                            id="admin-settings-save"
                            aria-label="<?php p($l->t('Save all settings')); ?>"
                            title="<?php p($l->t('Save changes and apply to all users')); ?>">
                            <?php p($l->t('Save all settings')); ?>
                        </button>
                        <a href="<?php p($cancelUrl); ?>"
                            class="azc-btn azc-btn--secondary"
                            aria-label="<?php p($isNcAdminShell ? $l->t('Open full settings in the app without saving') : $l->t('Cancel and return to overview')); ?>"
                            title="<?php p($isNcAdminShell ? $l->t('Open the full ArbeitszeitCheck admin settings in the app') : $l->t('Go back without saving changes')); ?>">
                            <?php p($isNcAdminShell ? $l->t('Open in app') : $l->t('Cancel')); ?>
                        </a>
                    </div>
                </div>
            </form>
                <?php
                // Support & Us — informational CTAs only; never gates AGPL use.
                $supportUsLanguageCode = method_exists($l, 'getLanguageCode') ? (string)$l->getLanguageCode() : 'en';
                $supportUsCssPrefix = 'azc';
                $supportUsBtnPrimaryClass = 'azc-btn azc-btn--primary';
                $supportUsBtnSecondaryClass = 'azc-btn azc-btn--secondary';
                $supportUsLinks = new \OCA\ArbeitszeitCheck\Support\SupportUsLinks(
                	'ArbeitszeitCheck',
                	true,
                	$urlGenerator->linkToRoute('arbeitszeitcheck.license_admin.index')
                );
                include __DIR__ . '/parts/support-us-section.php';
                ?>
            </div>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.adminSettingsApiUrl = <?php echo json_encode($apiSettingsUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.monthClosureReopenUrl = <?php echo json_encode($monthClosureReopenUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.adminUsersListUrl = <?php echo json_encode($adminUsersListUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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
window.ArbeitszeitCheck.l10n.accessGroupsAllUsers = <?php echo json_encode($l->t('No groups selected (all users are allowed).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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
