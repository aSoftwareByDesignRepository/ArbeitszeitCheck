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
                <section class="azc-card admin-settings-section" aria-labelledby="section-compliance-heading">
                                        <header class="azc-card__header<?php echo empty($azcSettingsShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
                        <div class="azc-card__header-text">
                            <?php if (!empty($azcSettingsShowCardChrome)): ?>
                            <h2 id="section-compliance-heading" class="azc-card__title"><?php p($l->t('Compliance and working time rules')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Define how ArbeitszeitCheck validates bookings, handles break edge cases, and enforces legal limits.')); ?></p>
                            <?php else: ?>
                            <h2 id="section-compliance-heading" class="azc-card__title visually-hidden"><?php p($l->t('Compliance and working time rules')); ?></h2>
                            <?php endif; ?>
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
                        <?php p($l->t('The system will automatically check if working hours follow the configured country labour law (ArbZG, AZG/ARG or ArG). For example, it will warn when required breaks are missing.')); ?>
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

