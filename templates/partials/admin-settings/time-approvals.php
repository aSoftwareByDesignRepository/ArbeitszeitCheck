<?php
declare(strict_types=1);

/**
 * Admin global settings — time entry approval gates (one topic).
 *
 * @var array $_
 * @var \OCP\IL10N $l
 * @var array $settings
 */

/** @var \OCP\IL10N $l */
/** @var array $settings */
$settings = is_array($settings ?? null) ? $settings : (is_array($_['settings'] ?? null) ? $_['settings'] : []);
$azcSettingsShowCardChrome = !empty($azcSettingsShowCardChrome) || !empty($renderAll);
?>
                <section class="azc-card admin-settings-section" aria-labelledby="section-time-approval-heading">
                    <header class="azc-card__header<?php echo empty($azcSettingsShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
                        <div class="azc-card__header-text">
                            <?php if (!empty($azcSettingsShowCardChrome)): ?>
                            <h2 id="section-time-approval-heading" class="azc-card__title"><?php p($l->t('Time entries and approval')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Both options are off by default (legacy behaviour). Enable only when your organisation requires four-eyes approval.')); ?></p>
                            <?php else: ?>
                            <h2 id="section-time-approval-heading" class="azc-card__title visually-hidden"><?php p($l->t('Time entries and approval')); ?></h2>
                            <?php endif; ?>
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
