<?php
declare(strict_types=1);

/**
 * Admin global settings section partial.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 * @var array $settings
 */

/** @var \OCP\IL10N $l */
/** @var array $settings */
$settings = is_array($settings ?? null) ? $settings : (is_array($_['settings'] ?? null) ? $_['settings'] : []);
$availableGroups = is_array($availableGroups ?? null) ? $availableGroups : (is_array($_['availableGroups'] ?? null) ? $_['availableGroups'] : []);
$availableAppAdmins = is_array($availableAppAdmins ?? null) ? $availableAppAdmins : (is_array($_['availableAppAdmins'] ?? null) ? $_['availableAppAdmins'] : []);
$availableAccessUsers = is_array($availableAccessUsers ?? null) ? $availableAccessUsers : (is_array($_['availableAccessUsers'] ?? null) ? $_['availableAccessUsers'] : []);
$azcSettingsShowCardChrome = !empty($azcSettingsShowCardChrome) || !empty($renderAll);
?>
                <section class="azc-card admin-settings-section" aria-labelledby="section-time-capture-heading">
                                        <header class="azc-card__header<?php echo empty($azcSettingsShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
                        <div class="azc-card__header-text">
                            <?php if (!empty($azcSettingsShowCardChrome)): ?>
                            <h2 id="section-time-capture-heading" class="azc-card__title"><?php p($l->t('Time recording methods')); ?></h2>
                            <?php else: ?>
                            <h2 id="section-time-capture-heading" class="azc-card__title visually-hidden"><?php p($l->t('Time recording methods')); ?></h2>
                            <?php endif; ?>
                        </div>
                    </header>
                    <div class="azc-card__body">
                    <p id="section-time-capture-intro" class="<?php echo !empty($azcSettingsShowCardChrome) ? 'azc-card__lead' : 'visually-hidden'; ?>">
                        <?php p($l->t('Choose how the organisation records working time. At least one method must stay on.')); ?>
                    </p>
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

