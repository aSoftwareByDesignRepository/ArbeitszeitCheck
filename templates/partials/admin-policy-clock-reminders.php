<?php
declare(strict_types=1);
/**
 * Partial: admin-policy-clock-reminders.php
 * Expects: $l, $settings
 * @license AGPL-3.0-or-later
 */
/** @var \OCP\IL10N $l */
/** @var array $settings */
?>
				<section class="azc-card azc-admin-policy-section admin-settings-section" aria-labelledby="block-clock-reminders-heading">
					<header class="azc-card__header">
						<div class="azc-card__header-text">
							<h2 id="block-clock-reminders-heading" class="azc-card__title"><?php p($l->t('Clock-in reminders')); ?></h2>
							<p class="azc-card__lead"><?php p($l->t('Remind people who forgot to clock in on expected workdays.')); ?></p>
						</div>
					</header>
					<div class="azc-card__body">
					<div class="form-group">
						<div class="form-checkbox">
							<input type="checkbox" id="missingClockInRemindersEnabled" name="missingClockInRemindersEnabled"
								<?php echo ($settings['missingClockInRemindersEnabled'] ?? true) ? 'checked' : ''; ?>
								aria-describedby="missingClockInRemindersEnabled-help">
							<label for="missingClockInRemindersEnabled" class="form-label">
								<?php p($l->t('Enable missing clock-in reminders globally')); ?>
							</label>
						</div>
						<p id="missingClockInRemindersEnabled-help" class="form-help">
							<?php p($l->t('If enabled, users can still turn this reminder off in their personal settings. Reminders are sent only for expected workdays (not weekends, holidays, or approved absences).')); ?>
						</p>
					</div>
					</div>
				</section>
