<?php
declare(strict_types=1);
/**
 * Partial: admin-policy-vacation.php
 * Expects: $l, $settings, $urlGenerator (where needed), $absenceTypes/$eventTypes for HR, $premiumNightPreset for premiums.
 * @license AGPL-3.0-or-later
 */
/** @var \OCP\IL10N $l */
/** @var array $settings */
?>
                <section class="azc-card azc-admin-policy-section admin-settings-section" aria-labelledby="section-absences-heading">
                    <header class="azc-card__header<?php echo empty($azcPolicyShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
                        <div class="azc-card__header-text">
							<?php if (!empty($azcPolicyShowCardChrome)): ?>
                            <h2 id="section-absences-heading" class="azc-card__title"><?php p($l->t('Vacation rules')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Vacation year, carryover, day/hour unit, and pro-rata rules for the whole organisation.')); ?></p>
							<?php else: ?>
                            <h2 id="section-absences-heading" class="azc-card__title visually-hidden"><?php p($l->t('Vacation rules')); ?></h2>
							<?php endif; ?>
                        </div>
                    </header>
                    <div class="azc-card__body">
					<?php $vacationYearMode = (string)($settings['vacationYearMode'] ?? 'calendar'); ?>
					<?php
					$missingHireCount = (int)($settings['vacationYearMissingHireCount'] ?? 0);
					$employeesAdminUrl = (string)($settings['employeesAdminUrl'] ?? '');
					?>
					<div class="azc-settings-subsection" role="group" aria-labelledby="vacation-year-mode-heading">
						<h3 id="vacation-year-mode-heading" class="admin-settings-subsection__title"><?php p($l->t('Vacation year')); ?></h3>
						<p class="form-help form-help--block" id="vacation-year-mode-intro">
							<?php p($l->t('Leave Calendar year unless your company counts vacation from each person’s hire date.')); ?>
						</p>
						<fieldset class="wtm-vacation-year-mode" aria-describedby="vacation-year-mode-intro vacation-year-mode-help vacation-year-mode-rollover-note vacation-year-mode-recompute-note">
							<legend class="form-label"><?php p($l->t('Vacation year mode')); ?></legend>
							<div class="azc-choice-cards">
							<div class="form-radio azc-choice-card">
								<input type="radio" id="vacationYearMode-calendar" name="vacationYearMode" value="calendar"
									<?php echo $vacationYearMode === 'anniversary' ? '' : 'checked'; ?>>
								<label for="vacationYearMode-calendar">
									<span class="azc-choice-card__title"><?php p($l->t('Calendar year')); ?></span>
									<span class="azc-choice-card__hint"><?php p($l->t('1 Jan – 31 Dec (default)')); ?></span>
								</label>
							</div>
							<div class="form-radio azc-choice-card">
								<input type="radio" id="vacationYearMode-anniversary" name="vacationYearMode" value="anniversary"
									<?php echo $vacationYearMode === 'anniversary' ? 'checked' : ''; ?>>
								<label for="vacationYearMode-anniversary">
									<span class="azc-choice-card__title"><?php p($l->t('Hire anniversary')); ?></span>
									<span class="azc-choice-card__hint"><?php p($l->t('From employment start')); ?></span>
								</label>
							</div>
							</div>
						</fieldset>
						<details class="azc-settings-more" id="vacation-year-mode-more">
							<summary><?php p($l->t('What changes if I switch?')); ?></summary>
							<p id="vacation-year-mode-help" class="form-help">
								<?php p($l->t('Anniversary mode needs an employment start date on each employee (Employees → person). People without a start date get no vacation entitlement until it is set. Carryover expiry below switches to “months after anniversary” automatically.')); ?>
							</p>
							<p class="form-help form-help--note" id="vacation-year-mode-rollover-note">
								<?php p($l->t('Automatic rollover still runs in anniversary mode: each person’s unused Resturlaub rolls after their own carryover deadline into the next anniversary year.')); ?>
							</p>
						</details>
						<p class="form-help form-help--note" id="vacation-year-mode-recompute-note">
							<?php p($l->t('Saving a mode change refreshes open vacation balances for everyone with app access.')); ?>
						</p>
						<p id="vacation-year-missing-hire" class="azc-callout azc-callout--warning" role="status"
							data-missing-count="<?php p((string)$missingHireCount); ?>"
							<?php echo ($vacationYearMode === 'anniversary' && $missingHireCount > 0) ? '' : 'hidden'; ?>>
							<?php
							if ($missingHireCount === 1) {
								p($l->t('1 person is missing a hire date and will get no vacation until it is set.'));
							} else {
								p($l->t('%s people are missing a hire date and will get no vacation until it is set.', [(string)$missingHireCount]));
							}
							?>
							<?php if ($employeesAdminUrl !== '') { ?>
								<a href="<?php p($employeesAdminUrl); ?>"><?php p($l->t('Open Employees')); ?></a>
							<?php } ?>
						</p>
						<div id="vacation-year-missing-hire-ack-wrap" class="form-checkbox"
							<?php echo ($missingHireCount > 0) ? '' : 'hidden'; ?>>
							<input type="checkbox" id="vacationYearMissingHireAcknowledged" name="vacationYearMissingHireAcknowledged" value="1"
								aria-describedby="vacation-year-missing-hire vacation-year-missing-hire-ack-help">
							<label for="vacationYearMissingHireAcknowledged" class="form-label">
								<?php p($l->t('I understand people without a hire date get no vacation until a start date is set')); ?>
							</label>
							<p id="vacation-year-missing-hire-ack-help" class="form-help">
								<?php p($l->t('Required when switching to anniversary mode while hire dates are missing. Prefer setting employment start dates first.')); ?>
							</p>
						</div>
					</div>
					<div class="azc-settings-subsection" role="group" aria-labelledby="vacation-carryover-expiry-heading" data-vacation-year-mode="<?php p($vacationYearMode); ?>">
						<h3 id="vacation-carryover-expiry-heading" class="admin-settings-subsection__title"><?php p($l->t('Vacation carryover expiry')); ?></h3>
						<p class="form-help form-help--block" id="vacation-carryover-expiry-intro" data-mode-calendar <?php echo $vacationYearMode === 'anniversary' ? 'hidden' : ''; ?>>
							<?php p($l->t('Last day leftover vacation (Resturlaub) can still be used each year.')); ?>
						</p>
						<p class="form-help form-help--block" id="vacation-carryover-expiry-intro-anniversary" data-mode-anniversary <?php echo $vacationYearMode === 'anniversary' ? '' : 'hidden'; ?>>
							<?php p($l->t('How many months after each hire anniversary leftover vacation stays usable.')); ?>
						</p>
						<details class="azc-settings-more" id="vacation-carryover-how-more">
							<summary><?php p($l->t('How carryover works')); ?></summary>
							<p class="form-help form-help--block form-help--note" id="vacation-carryover-expiry-how">
								<?php p($l->t('Only approved vacation counts. For working days on or before this date, carryover is used before annual entitlement. Approved absences are applied in chronological order (by start date, then id).')); ?>
							</p>
						</details>
						<div class="form-row form-row--inline" role="group" aria-labelledby="vacation-carryover-expiry-heading" aria-describedby="vacation-carryover-expiry-intro vacation-carryover-expiry-how vacation-carryover-expiry-help">
							<div class="form-group">
								<label for="vacationCarryoverExpiryMonth" class="form-label" id="vacationCarryoverExpiryMonth-label" data-label-calendar="<?php p($l->t('Month (1–12)')); ?>" data-label-anniversary="<?php p($l->t('Months after anniversary (1–12)')); ?>"><?php
									echo $vacationYearMode === 'anniversary'
										? $l->t('Months after anniversary (1–12)')
										: $l->t('Month (1–12)');
								?></label>
								<input type="number" class="form-input azc-input--touch" id="vacationCarryoverExpiryMonth" name="vacationCarryoverExpiryMonth"
									min="1" max="12" step="1" required
									value="<?php p((string)($settings['vacationCarryoverExpiryMonth'] ?? 3)); ?>"
									aria-describedby="vacation-carryover-expiry-intro vacation-carryover-expiry-how vacation-carryover-expiry-help">
							</div>
							<div class="form-group" id="vacationCarryoverExpiryDay-wrap" <?php echo $vacationYearMode === 'anniversary' ? 'hidden' : ''; ?>>
								<label for="vacationCarryoverExpiryDay" class="form-label"><?php p($l->t('Day (1–31)')); ?></label>
								<input type="number" class="form-input azc-input--touch" id="vacationCarryoverExpiryDay" name="vacationCarryoverExpiryDay"
									min="1" max="31" step="1"
									<?php echo $vacationYearMode === 'anniversary' ? '' : 'required'; ?>
									value="<?php p((string)($settings['vacationCarryoverExpiryDay'] ?? 31)); ?>"
									aria-describedby="vacation-carryover-expiry-intro vacation-carryover-expiry-how vacation-carryover-expiry-help">
							</div>
						</div>
						<p id="vacation-carryover-expiry-help" class="form-help" data-mode-calendar <?php echo $vacationYearMode === 'anniversary' ? 'hidden' : ''; ?>>
							<?php p($l->t('Common example: 31 March.')); ?>
						</p>
						<p id="vacation-carryover-expiry-help-anniversary" class="form-help" data-mode-anniversary <?php echo $vacationYearMode === 'anniversary' ? '' : 'hidden'; ?>>
							<?php p($l->t('Example: hire 1 July + 3 months → usable until 30 September.')); ?>
						</p>
						<div class="form-group">
							<?php
							$carryoverUnitIsHours = ((string)($settings['vacationUnit'] ?? 'days')) === 'hours';
							?>
							<label for="vacationCarryoverMaxDays" class="form-label"><?php
								echo $carryoverUnitIsHours
									? $l->t('Maximum carryover hours (optional)')
									: $l->t('Maximum carryover days (optional)');
							?></label>
							<input type="text" class="form-input" id="vacationCarryoverMaxDays" name="vacationCarryoverMaxDays" inputmode="decimal"
								placeholder="<?php p($l->t('Empty = no limit')); ?>"
								value="<?php p((string)($settings['vacationCarryoverMaxDays'] ?? '')); ?>"
								data-unit="<?php p($carryoverUnitIsHours ? 'hours' : 'days'); ?>"
								aria-describedby="vacation-carryover-max-help">
							<p id="vacation-carryover-max-help" class="form-help">
								<?php
								echo $carryoverUnitIsHours
									? $l->t('If set, opening carryover per user cannot exceed this many hours (Tarifvertrag / company policy). Leave empty for no cap. Imports and admin edits are clamped to this value.')
									: $l->t('If set, opening carryover per user cannot exceed this many days (Tarifvertrag / company policy). Leave empty for no cap. Imports and admin edits are clamped to this value.');
								?>
							</p>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="vacationRolloverEnabled" name="vacationRolloverEnabled" value="1"
									<?php echo ($settings['vacationRolloverEnabled'] ?? true) ? 'checked' : ''; ?>
									aria-describedby="vacation-rollover-enabled-help">
								<label for="vacationRolloverEnabled" class="form-label"><?php p($l->t('Automatic vacation rollover job')); ?></label>
							</div>
						<p id="vacation-rollover-enabled-help" class="form-help">
							<?php p($l->t('When enabled, a daily task may copy unused carryover (and optionally unused annual entitlement) into the next year’s opening balance after the carryover deadline, unless a balance already exists for that year. In anniversary mode the deadline follows each person’s hire anniversary. Use the occ command for manual runs.')); ?>
						</p>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="vacationRolloverIncludeUnusedAnnual" name="vacationRolloverIncludeUnusedAnnual" value="1"
									<?php echo ($settings['vacationRolloverIncludeUnusedAnnual'] ?? false) ? 'checked' : ''; ?>
									aria-describedby="vacation-rollover-annual-help">
								<label for="vacationRolloverIncludeUnusedAnnual" class="form-label"><?php p($l->t('Include unused annual entitlement in rollover (advanced)')); ?></label>
							</div>
							<p id="vacation-rollover-annual-help" class="form-help form-help--note">
								<?php
								echo $carryoverUnitIsHours
									? $l->t('Off by default. Only enable if your collective agreement allows transferring unused annual leave; consult HR / legal. When on, unused annual hours for the year may be added to the next year’s carryover opening, subject to the maximum carryover cap above.')
									: $l->t('Off by default. Only enable if your collective agreement allows transferring unused annual leave; consult HR / legal. When on, unused annual days for the year may be added to the next year’s carryover opening, subject to the maximum carryover cap above.');
								?>
							</p>
						</div>
					</div>
					<div class="azc-settings-subsection" role="group" aria-labelledby="vacation-unit-heading">
						<h3 id="vacation-unit-heading" class="admin-settings-subsection__title"><?php p($l->t('Vacation unit')); ?></h3>
						<p id="vacation-unit-intro" class="form-help form-help--block">
							<?php p($l->t('Most organisations use days. Switch to hours only if you book vacation as an hour budget.')); ?>
						</p>
						<details class="azc-settings-more" id="vacation-unit-more-help">
							<summary><?php p($l->t('What happens when I switch?')); ?></summary>
							<p class="form-help form-help--block">
								<?php p($l->t('Switching runs a one-time conversion of open balances — it is not a simple label change. Existing customers who stay on days are unchanged.')); ?>
							</p>
						</details>
						<?php
						$vacationUnit = (string)($settings['vacationUnit'] ?? 'days');
						$vacationHoursPerDay = (string)($settings['vacationHoursPerDay'] ?? '8');
						$vacationUnitClientConfirmed = !empty($settings['vacationUnitClientConfirmed']);
						?>
						<p id="vacation-unit-status" class="form-help" role="status" data-current-unit="<?php p($vacationUnit); ?>">
							<?php
							echo $vacationUnit === 'hours'
								? $l->t('Current unit: hours')
								: $l->t('Current unit: days (default)');
							?>
						</p>
						<fieldset class="form-group vacation-unit-choice" id="vacation-unit-choice">
							<legend class="form-label"><?php p($l->t('Organisation unit')); ?></legend>
							<div class="vacation-unit-radios azc-choice-cards">
								<label class="form-radio vacation-unit-radio azc-choice-card" for="vacation-unit-days">
									<input type="radio" id="vacation-unit-days" name="vacationUnitChoice" value="days"
										<?php echo $vacationUnit === 'days' ? 'checked' : ''; ?>>
									<span class="azc-choice-card__title"><?php p($l->t('Days')); ?></span>
								</label>
								<label class="form-radio vacation-unit-radio azc-choice-card" for="vacation-unit-hours">
									<input type="radio" id="vacation-unit-hours" name="vacationUnitChoice" value="hours"
										<?php echo $vacationUnit === 'hours' ? 'checked' : ''; ?>>
									<span class="azc-choice-card__title"><?php p($l->t('Hours')); ?></span>
								</label>
							</div>
						</fieldset>
						<div class="form-group">
							<label for="vacationHoursPerDay" class="form-label"><?php p($l->t('Hours per day (for conversion)')); ?></label>
							<input type="number" id="vacationHoursPerDay" name="vacationHoursPerDay"
								class="form-input azc-input--touch" min="0.25" max="24" step="0.25"
								value="<?php p($vacationHoursPerDay); ?>"
								aria-describedby="vacation-hours-per-day-help vacation-hours-banss-callout">
							<p id="vacation-hours-per-day-help" class="form-help">
								<?php p($l->t('Used only when converting days ↔ hours. Day-to-day booking still follows each person’s work schedule.')); ?>
							</p>
							<div class="azc-callout azc-callout--info" id="vacation-hours-banss-callout" role="note">
								<p class="azc-callout__title"><?php p($l->t('38.5 h week tip')); ?></p>
								<p class="azc-callout__body">
									<?php p($l->t('Use 7.7 (= 38.5 ÷ 5) so open day balances convert fairly.')); ?>
								</p>
								<p class="azc-callout__actions">
									<button type="button" id="btn-vacation-hours-use-banss"
										class="azc-btn azc-btn--secondary"
										data-hours="<?php p((string)\OCA\ArbeitszeitCheck\Constants::BANSS_RECOMMENDED_VACATION_HOURS_PER_DAY); ?>">
										<?php p($l->t('Use 7.7')); ?>
									</button>
								</p>
							</div>
						</div>
						<div class="form-group">
							<label class="form-checkbox" for="vacationUnitClientConfirmed">
								<input type="checkbox" id="vacationUnitClientConfirmed" name="vacationUnitClientConfirmed" value="1"
									<?php echo $vacationUnitClientConfirmed ? 'checked' : ''; ?>
									aria-describedby="vacation-unit-client-help">
								<span><?php p($l->t('Employee apps are updated and show hours correctly')); ?></span>
							</label>
							<p id="vacation-unit-client-help" class="form-help form-help--note">
								<?php p($l->t('Required before enabling hours. Old apps still say “days”.')); ?>
							</p>
						</div>
						<div class="reports-form__actions" style="margin-top: 0.75rem;">
							<button type="button" id="btn-vacation-unit-apply" class="azc-btn azc-btn--primary"
								aria-describedby="vacation-unit-apply-help">
								<?php p($l->t('Apply unit change')); ?>
							</button>
						</div>
						<p id="vacation-unit-apply-help" class="form-help">
							<?php p($l->t('Select Days or Hours, then Apply. Hours needs the app confirmation above.')); ?>
						</p>
						<p id="vacation-unit-migrate-status" class="form-help" role="status" aria-live="polite"></p>
						<p id="vacation-unit-migrate-error" class="form-help form-help--note" role="alert" hidden></p>
					</div>
					<div class="azc-settings-subsection" role="group" aria-labelledby="vacation-proration-heading">
						<h3 id="vacation-proration-heading" class="admin-settings-subsection__title"><?php p($l->t('Pro-rata vacation for partial years')); ?></h3>
						<p class="form-help form-help--block" id="vacation-proration-intro">
							<?php p($l->t('When an employee joins or leaves during the year, the annual vacation entitlement is reduced to the part of the year actually worked. This only applies to employees who have an employment start and/or end date set under Employees. Choose how the reduction is calculated.')); ?>
						</p>
						<div class="form-group">
							<label for="vacationProrationMethod" class="form-label"><?php p($l->t('Proration method')); ?></label>
							<?php $prorationMethod = (string)($settings['vacationProrationMethod'] ?? 'twelfths'); ?>
							<select class="form-select" id="vacationProrationMethod" name="vacationProrationMethod" aria-describedby="vacation-proration-help">
								<option value="twelfths" <?php echo $prorationMethod === 'daily' ? '' : 'selected'; ?>><?php p($l->t('Full months (Zwölftelung, common DE default)')); ?></option>
								<option value="daily" <?php echo $prorationMethod === 'daily' ? 'selected' : ''; ?>><?php p($l->t('Exact days')); ?></option>
							</select>
							<p id="vacation-proration-help" class="form-help">
								<?php p($l->t('Full months: each calendar month touched by the employment counts as 1/12 of the annual entitlement; a fraction of half a day or more is rounded up to a full day (BUrlG §5). Exact days: annual entitlement times worked days divided by days in the year. This is not legal advice — consult HR for your collective agreement.')); ?>
							</p>
						</div>
					</div>
                    </div>
				</section>
