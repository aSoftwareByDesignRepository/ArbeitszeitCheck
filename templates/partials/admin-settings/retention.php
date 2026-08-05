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
                <section class="azc-card admin-settings-section" aria-labelledby="section-retention-heading">
                                        <header class="azc-card__header<?php echo empty($azcSettingsShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
                        <div class="azc-card__header-text">
                            <?php if (!empty($azcSettingsShowCardChrome)): ?>
                            <h2 id="section-retention-heading" class="azc-card__title"><?php p($l->t('Data retention')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Control how long time-tracking records are kept before automated cleanup.')); ?></p>
                            <?php else: ?>
                            <h2 id="section-retention-heading" class="azc-card__title visually-hidden"><?php p($l->t('Data retention')); ?></h2>
                            <?php endif; ?>
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

