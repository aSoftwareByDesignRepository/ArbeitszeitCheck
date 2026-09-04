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
                <section class="azc-card admin-settings-section" aria-labelledby="section-export-heading">
                                        <header class="azc-card__header<?php echo empty($azcSettingsShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
                        <div class="azc-card__header-text">
                            <?php if (!empty($azcSettingsShowCardChrome)): ?>
                            <h2 id="section-export-heading" class="azc-card__title"><?php p($l->t('Exports and reporting')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Midnight split for CSV/JSON, and DATEV payroll numbers for your accountant.')); ?></p>
                            <?php else: ?>
                            <h2 id="section-export-heading" class="azc-card__title visually-hidden"><?php p($l->t('Exports and reporting')); ?></h2>
                            <?php endif; ?>
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

                    <fieldset class="form-fieldset" aria-labelledby="datev-org-legend" aria-describedby="datev-org-intro">
                        <legend id="datev-org-legend" class="form-legend"><?php p($l->t('DATEV organisation numbers')); ?></legend>
                        <p id="datev-org-intro" class="form-help form-help--block">
                            <?php p($l->t('Needed only if you export DATEV files. Leave both empty if you do not use DATEV. Set both numbers together. Premium Lohnarten are configured under Hour premiums.')); ?>
                        </p>
                        <div class="form-row form-row--2">
                            <div class="form-group">
                                <label for="datevBeraternummer" class="form-label"><?php p($l->t('Beraternummer')); ?></label>
                                <input type="text"
                                       class="form-input"
                                       id="datevBeraternummer"
                                       name="datevBeraternummer"
                                       inputmode="numeric"
                                       pattern="[0-9]{0,7}"
                                       maxlength="7"
                                       autocomplete="off"
                                       value="<?php p((string)($settings['datevBeraternummer'] ?? '')); ?>"
                                       aria-describedby="datevBeraternummer-help datev-org-intro">
                                <p id="datevBeraternummer-help" class="form-help"><?php p($l->t('Up to 7 digits. Saved with Save on this page.')); ?></p>
                            </div>
                            <div class="form-group">
                                <label for="datevMandantennummer" class="form-label"><?php p($l->t('Mandantennummer')); ?></label>
                                <input type="text"
                                       class="form-input"
                                       id="datevMandantennummer"
                                       name="datevMandantennummer"
                                       inputmode="numeric"
                                       pattern="[0-9]{0,5}"
                                       maxlength="5"
                                       autocomplete="off"
                                       value="<?php p((string)($settings['datevMandantennummer'] ?? '')); ?>"
                                       aria-describedby="datevMandantennummer-help datev-org-intro">
                                <p id="datevMandantennummer-help" class="form-help"><?php p($l->t('Up to 5 digits.')); ?></p>
                            </div>
                        </div>
                        <div class="form-row form-row--2">
                            <div class="form-group">
                                <label for="datevLohnartNormal" class="form-label"><?php p($l->t('Lohnart normal hours')); ?></label>
                                <input type="text"
                                       class="form-input"
                                       id="datevLohnartNormal"
                                       name="datevLohnartNormal"
                                       inputmode="numeric"
                                       pattern="[1-9][0-9]{0,3}"
                                       maxlength="4"
                                       autocomplete="off"
                                       value="<?php p((string)($settings['datevLohnartNormal'] ?? '1000')); ?>"
                                       aria-describedby="datevLohnartNormal-help">
                                <p id="datevLohnartNormal-help" class="form-help"><?php p($l->t('Wage type for regular working hours (default 1000).')); ?></p>
                            </div>
                            <div class="form-group">
                                <label for="datevLohnartUeberstunden" class="form-label"><?php p($l->t('Lohnart overtime (reserved)')); ?></label>
                                <input type="text"
                                       class="form-input"
                                       id="datevLohnartUeberstunden"
                                       name="datevLohnartUeberstunden"
                                       inputmode="numeric"
                                       pattern="[1-9][0-9]{0,3}"
                                       maxlength="4"
                                       autocomplete="off"
                                       value="<?php p((string)($settings['datevLohnartUeberstunden'] ?? '2000')); ?>"
                                       aria-describedby="datevLohnartUeberstunden-help">
                                <p id="datevLohnartUeberstunden-help" class="form-help"><?php p($l->t('Reserved for future Saldo/Auszahlung mapping. Premium hours use the Lohnart column under Hour premiums.')); ?></p>
                            </div>
                        </div>
                    </fieldset>
                    </div><!-- /.azc-card__body -->
                </section>

                <?php $monthClosureOn = !empty($settings['monthClosureEnabled']); ?>
