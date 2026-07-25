<?php
declare(strict_types=1);

/**
 * Admin notification settings template.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

/** @var array $_ */
/** @var \OCP\IL10N $l */

$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$settings = is_array($_['settings'] ?? null) ? $_['settings'] : [];
$absenceTypes = is_array($_['absenceTypes'] ?? null) ? $_['absenceTypes'] : [];
$eventTypes = is_array($_['eventTypes'] ?? null) ? $_['eventTypes'] : [];
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

        <div class="azc-page-stack">
        <div class="azc-admin-notifications-layout">
            <?php
            $jumpNavLayout = 'bar';
            $jumpNavAriaLabel = $l->t('Jump to notification sections');
            $jumpNavItems = [
                ['href' => '#section-absences-heading', 'label' => $l->t('Absences')],
                ['href' => '#section-absence-workflow-heading', 'label' => $l->t('Calendar & email')],
                ['href' => '#overtime-trafficlight-heading', 'label' => $l->t('Overtime alerts')],
                ['href' => '#overtime-bank-heading', 'label' => $l->t('Overtime bank')],
                ['href' => '#hr-notifications-heading', 'label' => $l->t('HR notifications')],
            ];
            include __DIR__ . '/common/azc-jump-nav.php';
            ?>
            <form id="admin-notifications-form" class="form admin-settings-form admin-notifications-form" novalidate>
                <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken'] ?? ''); ?>">

                <div class="azc-admin-notifications-form__sections">

                <section class="azc-card azc-admin-notifications-section admin-settings-section" aria-labelledby="section-absences-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-absences-heading" class="azc-card__title"><?php p($l->t('Absences and notifications')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Configure reminder behavior, vacation carryover rules, and substitution-related communication for absence workflows.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
					<h3 id="block-clock-reminders-heading" class="admin-settings-subsection__title"><?php p($l->t('Clock-in reminders')); ?></h3>
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
					<div class="azc-settings-subsection" role="group" aria-labelledby="vacation-carryover-expiry-heading">
						<h3 id="vacation-carryover-expiry-heading" class="admin-settings-subsection__title"><?php p($l->t('Vacation carryover expiry')); ?></h3>
						<p class="form-help form-help--block" id="vacation-carryover-expiry-intro">
							<?php p($l->t('This is the last calendar day in each year when carryover from the opening balance (Resturlaub) may still be used for vacation. You enter each person\'s opening balance per calendar year under Users. After this date, new vacation requests can only use the annual vacation entitlement from the working time model—not carryover. This applies to everyone.')); ?>
						</p>
						<p class="form-help form-help--block form-help--note" id="vacation-carryover-expiry-how">
							<?php p($l->t('Only approved vacation counts. For working days on or before this date, carryover is used before annual entitlement. Approved absences are applied in chronological order (by start date, then id).')); ?>
						</p>
						<div class="form-row form-row--inline" role="group" aria-labelledby="vacation-carryover-expiry-heading" aria-describedby="vacation-carryover-expiry-intro vacation-carryover-expiry-how vacation-carryover-expiry-help">
							<div class="form-group">
								<label for="vacationCarryoverExpiryMonth" class="form-label"><?php p($l->t('Month (1–12)')); ?></label>
								<input type="number" class="form-input" id="vacationCarryoverExpiryMonth" name="vacationCarryoverExpiryMonth"
									min="1" max="12" step="1" required
									value="<?php p((string)($settings['vacationCarryoverExpiryMonth'] ?? 3)); ?>"
									aria-describedby="vacation-carryover-expiry-intro vacation-carryover-expiry-how vacation-carryover-expiry-help">
							</div>
							<div class="form-group">
								<label for="vacationCarryoverExpiryDay" class="form-label"><?php p($l->t('Day (1–31)')); ?></label>
								<input type="number" class="form-input" id="vacationCarryoverExpiryDay" name="vacationCarryoverExpiryDay"
									min="1" max="31" step="1" required
									value="<?php p((string)($settings['vacationCarryoverExpiryDay'] ?? 31)); ?>"
									aria-describedby="vacation-carryover-expiry-intro vacation-carryover-expiry-how vacation-carryover-expiry-help">
							</div>
						</div>
						<p id="vacation-carryover-expiry-help" class="form-help">
							<?php p($l->t('Common example in Germany: 31 March (month 3, day 31). Austria and Switzerland may use a different date. If that day does not exist in a month (e.g. 31 February), the last day of that month is used automatically.')); ?>
						</p>
						<div class="form-group">
							<label for="vacationCarryoverMaxDays" class="form-label"><?php p($l->t('Maximum carryover days (optional)')); ?></label>
							<input type="text" class="form-input" id="vacationCarryoverMaxDays" name="vacationCarryoverMaxDays" inputmode="decimal"
								placeholder="<?php p($l->t('Empty = no limit')); ?>"
								value="<?php p((string)($settings['vacationCarryoverMaxDays'] ?? '')); ?>"
								aria-describedby="vacation-carryover-max-help">
							<p id="vacation-carryover-max-help" class="form-help">
								<?php p($l->t('If set, opening carryover per user cannot exceed this many days (Tarifvertrag / company policy). Leave empty for no cap. Imports and admin edits are clamped to this value.')); ?>
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
								<?php p($l->t('When enabled, a daily task may copy unused carryover (and optionally unused annual days, see below) into the next calendar year’s opening balance after the carryover deadline, unless a balance already exists for that year. Use the occ command for manual runs.')); ?>
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
								<?php p($l->t('Off by default. Only enable if your collective agreement allows transferring unused annual leave; consult HR / legal. When on, unused annual days for the year may be added to the next year’s carryover opening, subject to the maximum carryover cap above.')); ?>
							</p>
						</div>
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

				<section class="azc-card azc-admin-notifications-section admin-settings-section" aria-labelledby="section-absence-workflow-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="section-absence-workflow-heading" class="azc-card__title"><?php p($l->t('Calendar invites and workflow emails')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Control iCal attachments and substitution emails when absences are approved.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
					<div class="azc-settings-subsection" role="group" aria-labelledby="send-ical-heading">
						<h3 id="send-ical-heading" class="admin-settings-subsection__title"><?php p($l->t('Absences: Send iCal via email')); ?></h3>
						<p class="form-help form-help--block">
							<?php p($l->t('For approved absences, an email with an iCal attachment (.ics) can be sent automatically.')); ?>
						</p>
						<p class="form-help form-help--block form-help--note">
							<?php p($l->t('Important: This is best-effort email delivery, not a guaranteed real-time calendar sync. Delivery can be delayed or fail due to mail server/network issues. Source of truth remains ArbeitszeitCheck.')); ?>
						</p>
						<p class="form-help form-help--block form-help--note">
							<?php p($l->t('Privacy note: To reduce sensitive data exposure, iCal details for substitutes/managers intentionally avoid private absence reasons.')); ?>
						</p>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendIcalApprovedAbsences" name="sendIcalApprovedAbsences" value="1"
									<?php echo ($settings['sendIcalApprovedAbsences'] ?? true) ? 'checked' : ''; ?>
									aria-describedby="send-ical-heading">
								<label for="sendIcalApprovedAbsences" class="form-label">
									<?php p($l->t('Send iCal to the person with approved absence')); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendIcalToSubstitute" name="sendIcalToSubstitute" value="1"
									<?php echo ($settings['sendIcalToSubstitute'] ?? false) ? 'checked' : ''; ?>
									aria-describedby="send-ical-heading">
								<label for="sendIcalToSubstitute" class="form-label">
									<?php p($l->t('Also send iCal to substitute (if selected)')); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendIcalToManagers" name="sendIcalToManagers" value="1"
									<?php echo ($settings['sendIcalToManagers'] ?? false) ? 'checked' : ''; ?>
									aria-describedby="send-ical-heading">
								<label for="sendIcalToManagers" class="form-label">
									<?php p($l->t('Also send iCal to managers (team managers)')); ?>
								</label>
							</div>
						</div>
					</div>

					<div class="azc-settings-subsection" role="group" aria-labelledby="email-notifications-heading">
						<h3 id="email-notifications-heading" class="admin-settings-subsection__title"><?php p($l->t('Absences: Email notifications for substitution workflow')); ?></h3>
						<p class="form-help form-help--block">
							<?php p($l->t('When a substitute is selected, emails can be sent at each step of the approval process.')); ?>
						</p>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendEmailSubstitutionRequest" name="sendEmailSubstitutionRequest" value="1"
									<?php echo ($settings['sendEmailSubstitutionRequest'] ?? true) ? 'checked' : ''; ?>
									aria-describedby="email-notifications-heading">
								<label for="sendEmailSubstitutionRequest" class="form-label">
									<?php p($l->t('Email substitute when a substitution request is created')); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendEmailSubstituteApprovedToEmployee" name="sendEmailSubstituteApprovedToEmployee" value="1"
									<?php echo ($settings['sendEmailSubstituteApprovedToEmployee'] ?? true) ? 'checked' : ''; ?>
									aria-describedby="email-notifications-heading">
								<label for="sendEmailSubstituteApprovedToEmployee" class="form-label">
									<?php p($l->t('Email employee when substitute approves')); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendEmailSubstituteApprovedToManager" name="sendEmailSubstituteApprovedToManager" value="1"
									<?php echo ($settings['sendEmailSubstituteApprovedToManager'] ?? true) ? 'checked' : ''; ?>
									aria-describedby="email-notifications-heading">
								<label for="sendEmailSubstituteApprovedToManager" class="form-label">
									<?php p($l->t('Email managers when substitute approves (requires app teams)')); ?>
								</label>
							</div>
						</div>
					</div>
                    </div>
				</section>

				<section class="azc-card azc-admin-notifications-section admin-settings-section" aria-labelledby="overtime-trafficlight-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="overtime-trafficlight-heading" class="azc-card__title"><?php p($l->t('Overtime and undertime traffic light')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Configure thresholds and recipients for bidirectional balance alerts (overtime and undertime).')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
					<h3 id="block-trafficlight-recipients-heading" class="admin-settings-subsection__title"><?php p($l->t('Activation and recipients')); ?></h3>
					<div class="form-group">
						<div class="form-checkbox">
							<input type="checkbox"
								id="overtimeTrafficLightEnabled"
								name="overtimeTrafficLightEnabled"
								<?php echo ($settings['overtimeTrafficLightEnabled'] ?? false) ? 'checked' : ''; ?>
								aria-describedby="overtimeTrafficLightEnabled-help"
								aria-controls="overtime-trafficlight-settings">
							<label for="overtimeTrafficLightEnabled" class="form-label">
								<?php p($l->t('Enable overtime traffic light notifications')); ?>
							</label>
						</div>
						<p id="overtimeTrafficLightEnabled-help" class="form-help">
							<?php p($l->t('When enabled, transitions to yellow or red levels can trigger in-app and email notifications.')); ?>
						</p>
					</div>

					<div id="overtime-trafficlight-settings" class="admin-notifications-dependent-block">
					<p class="admin-settings-subsection__intro form-help form-help--note"><?php p($l->t('Define when overtime changes from green to yellow and yellow to red (hours).')); ?></p>
					<div class="form-row form-row--thresholds" role="group" aria-labelledby="block-trafficlight-recipients-heading">
						<div class="form-group">
							<label for="overtimeYellowOver" class="form-label"><?php p($l->t('Overtime yellow threshold (hours)')); ?></label>
							<input type="number" class="form-input" id="overtimeYellowOver" name="overtimeYellowOver" min="0" max="500" step="0.25" value="<?php p((string)($settings['overtimeYellowOver'] ?? 5)); ?>">
						</div>
						<div class="form-group">
							<label for="overtimeRedOver" class="form-label"><?php p($l->t('Overtime red threshold (hours)')); ?></label>
							<input type="number" class="form-input" id="overtimeRedOver" name="overtimeRedOver" min="0" max="500" step="0.25" value="<?php p((string)($settings['overtimeRedOver'] ?? 15)); ?>">
						</div>
					</div>

					<p class="admin-settings-subsection__intro form-help form-help--note"><?php p($l->t('Define equivalent thresholds for undertime (negative balance).')); ?></p>
					<div class="form-row form-row--thresholds" role="group" aria-label="<?php p($l->t('Undertime thresholds')); ?>">
						<div class="form-group">
							<label for="overtimeYellowUnder" class="form-label"><?php p($l->t('Undertime yellow threshold (hours)')); ?></label>
							<input type="number" class="form-input" id="overtimeYellowUnder" name="overtimeYellowUnder" min="0" max="500" step="0.25" value="<?php p((string)($settings['overtimeYellowUnder'] ?? 5)); ?>">
						</div>
						<div class="form-group">
							<label for="overtimeRedUnder" class="form-label"><?php p($l->t('Undertime red threshold (hours)')); ?></label>
							<input type="number" class="form-input" id="overtimeRedUnder" name="overtimeRedUnder" min="0" max="500" step="0.25" value="<?php p((string)($settings['overtimeRedUnder'] ?? 15)); ?>">
						</div>
					</div>

					<div class="form-group">
						<label for="overtimeRecipients" class="form-label"><?php p($l->t('Balance traffic light recipients (overtime + undertime, comma separated emails)')); ?></label>
						<textarea
							id="overtimeRecipients"
							name="overtimeRecipients"
							rows="3"
							class="form-input"
							placeholder="<?php p($l->t('lead@example.com, hr@example.com')); ?>"
							aria-describedby="overtimeRecipients-help"><?php p((string)($settings['overtimeRecipients'] ?? '')); ?></textarea>
						<p id="overtimeRecipients-help" class="form-help">
							<?php p($l->t('These recipients are used for both overtime and undertime alerts. Use valid email addresses separated by commas. Duplicates are removed automatically.')); ?>
						</p>
					</div>

					<h3 id="block-trafficlight-matrix-heading" class="admin-settings-subsection__title"><?php p($l->t('Notification matrix')); ?></h3>
					<p class="form-help form-help--block">
						<?php p($l->t('Choose which severity levels should trigger notifications for overtime and undertime.')); ?>
					</p>
					<div class="table-container azc-table-wrap admin-notifications-matrix-wrap">
						<table class="grid-table admin-notifications-matrix azc-table--matrix" role="table" aria-labelledby="block-trafficlight-matrix-heading">
							<caption class="sr-only"><?php p($l->t('Severity levels that trigger notifications for overtime and undertime')); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php p($l->t('Direction')); ?></th>
									<th scope="col"><?php p($l->t('Yellow notifications')); ?></th>
									<th scope="col"><?php p($l->t('Red notifications')); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<th scope="row"><?php p($l->t('Overtime')); ?></th>
									<td>
										<div class="form-checkbox form-checkbox--center">
											<input type="checkbox" name="overtimeMatrix[over][yellow]" <?php echo !empty($settings['overtimeMatrix']['over']['yellow']) ? 'checked' : ''; ?> aria-label="<?php p($l->t('Notify on overtime yellow')); ?>">
										</div>
									</td>
									<td>
										<div class="form-checkbox form-checkbox--center">
											<input type="checkbox" name="overtimeMatrix[over][red]" <?php echo !empty($settings['overtimeMatrix']['over']['red']) ? 'checked' : ''; ?> aria-label="<?php p($l->t('Notify on overtime red')); ?>">
										</div>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php p($l->t('Undertime')); ?></th>
									<td>
										<div class="form-checkbox form-checkbox--center">
											<input type="checkbox" name="overtimeMatrix[under][yellow]" <?php echo !empty($settings['overtimeMatrix']['under']['yellow']) ? 'checked' : ''; ?> aria-label="<?php p($l->t('Notify on undertime yellow')); ?>">
										</div>
									</td>
									<td>
										<div class="form-checkbox form-checkbox--center">
											<input type="checkbox" name="overtimeMatrix[under][red]" <?php echo !empty($settings['overtimeMatrix']['under']['red']) ? 'checked' : ''; ?> aria-label="<?php p($l->t('Notify on undertime red')); ?>">
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					</div>
                    </div>
				</section>

				<section class="azc-card azc-admin-notifications-section admin-settings-section" aria-labelledby="overtime-bank-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="overtime-bank-heading" class="azc-card__title"><?php p($l->t('Overtime bank and payouts')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Employees can accumulate overtime up to a maximum (bank). Hours above the cap can be paid out at month end via Admin → Overtime payouts.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
					<div class="form-group">
						<div class="form-checkbox">
							<input type="checkbox"
								id="overtimeBankEnabled"
								name="overtimeBankEnabled"
								<?php echo ($settings['overtimeBankEnabled'] ?? false) ? 'checked' : ''; ?>
								aria-describedby="overtimeBankEnabled-help"
								aria-controls="overtime-bank-settings">
							<label for="overtimeBankEnabled" class="form-label">
								<?php p($l->t('Enable overtime bank (cap + month-end payout)')); ?>
							</label>
						</div>
						<p id="overtimeBankEnabled-help" class="form-help">
							<?php p($l->t('When enabled, the dashboard shows banked hours and payroll can record payouts above the cap.')); ?>
						</p>
					</div>
					<div id="overtime-bank-settings" class="admin-notifications-dependent-block">
					<div class="form-row form-row--thresholds">
						<div class="form-group">
							<label for="overtimeBankMaxHours" class="form-label"><?php p($l->t('Maximum banked overtime (hours)')); ?></label>
							<input type="number" class="form-input" id="overtimeBankMaxHours" name="overtimeBankMaxHours" min="1" max="500" step="0.25" value="<?php p((string)($settings['overtimeBankMaxHours'] ?? 100)); ?>">
						</div>
						<div class="form-group">
							<label for="overtimeBankYellowPercent" class="form-label"><?php p($l->t('Bank fill yellow from (%%)')); ?></label>
							<input type="number" class="form-input" id="overtimeBankYellowPercent" name="overtimeBankYellowPercent" min="0" max="100" step="1" value="<?php p((string)($settings['overtimeBankYellowPercent'] ?? 80)); ?>">
						</div>
						<div class="form-group">
							<label for="overtimeBankRedPercent" class="form-label"><?php p($l->t('Bank fill red from (%%)')); ?></label>
							<input type="number" class="form-input" id="overtimeBankRedPercent" name="overtimeBankRedPercent" min="0" max="100" step="1" value="<?php p((string)($settings['overtimeBankRedPercent'] ?? 95)); ?>">
						</div>
					</div>
					<h3 class="admin-settings-subsection__title"><?php p($l->t('After payout')); ?></h3>
					<div class="form-group">
						<div class="form-checkbox">
							<input type="checkbox" id="overtimePayoutNotifyInApp" name="overtimePayoutNotifyInApp" value="1"
								<?php echo ($settings['overtimePayoutNotifyInApp'] ?? true) ? 'checked' : ''; ?>>
							<label for="overtimePayoutNotifyInApp" class="form-label">
								<?php p($l->t('Notify employee in the app when payout is recorded')); ?>
							</label>
						</div>
					</div>
					<div class="form-group">
						<div class="form-checkbox">
							<input type="checkbox" id="overtimePayoutNotifyEmail" name="overtimePayoutNotifyEmail" value="1"
								<?php echo ($settings['overtimePayoutNotifyEmail'] ?? true) ? 'checked' : ''; ?>>
							<label for="overtimePayoutNotifyEmail" class="form-label">
								<?php p($l->t('Email employee when payout is recorded (requires valid email address)')); ?>
							</label>
						</div>
					</div>
					<nav class="admin-overtime-quicklinks" aria-label="<?php p($l->t('Overtime payroll shortcuts')); ?>">
						<p class="admin-overtime-quicklinks__label"><?php p($l->t('Payroll actions')); ?></p>
						<a class="azc-btn azc-btn--secondary azc-btn--sm" href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.index')); ?>">
							<?php p($l->t('Process payouts')); ?>
						</a>
						<a class="azc-btn azc-btn--secondary azc-btn--sm" href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.auditIndex')); ?>">
							<?php p($l->t('Payout audit')); ?>
						</a>
					</nav>
					<div class="form-group">
						<div class="form-checkbox">
							<input type="checkbox"
								id="overtimeBlockMonthClosurePendingPayout"
								name="overtimeBlockMonthClosurePendingPayout"
								value="1"
								<?php echo ($settings['overtimeBlockMonthClosurePendingPayout'] ?? false) ? 'checked' : ''; ?>
								aria-describedby="overtimeBlockMonthClosurePendingPayout-help">
							<label for="overtimeBlockMonthClosurePendingPayout" class="form-label">
								<?php p($l->t('Block month finalization until overtime payout is recorded')); ?>
							</label>
						</div>
						<p id="overtimeBlockMonthClosurePendingPayout-help" class="form-help">
							<?php p($l->t('When enabled, employees cannot seal a month while hours above the bank cap are still unpaid for that month.')); ?>
						</p>
					</div>
					</div>
                    </div>
				</section>

				<section class="azc-card azc-admin-notifications-section admin-settings-section" aria-labelledby="hr-notifications-heading">
                    <header class="azc-card__header">
                        <div class="azc-card__header-text">
                            <h2 id="hr-notifications-heading" class="azc-card__title"><?php p($l->t('HR office notifications')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('These settings define if and when HR receives email updates for absence workflows.')); ?></p>
                        </div>
                    </header>
                    <div class="azc-card__body">
					<h3 id="block-hr-setup-heading" class="admin-settings-subsection__title"><?php p($l->t('General HR notification setup')); ?></h3>
					<div class="form-group">
						<div class="form-checkbox">
							<input type="checkbox"
								id="hrNotificationsEnabled"
								name="hrNotificationsEnabled"
								<?php echo ($settings['enabled'] ?? false) ? 'checked' : ''; ?>
								aria-describedby="hrNotificationsEnabled-help"
								aria-controls="hr-notification-settings">
							<label for="hrNotificationsEnabled" class="form-label">
								<?php p($l->t('Enable HR office email notifications')); ?>
							</label>
						</div>
						<p id="hrNotificationsEnabled-help" class="form-help">
							<?php p($l->t('When enabled, selected workflow events send email updates to the configured HR recipients.')); ?>
						</p>
					</div>

					<div id="hr-notification-settings" class="admin-notifications-dependent-block">
					<div class="form-group">
						<label for="hrRecipients" class="form-label"><?php p($l->t('HR office recipients (comma separated emails)')); ?></label>
						<textarea
							id="hrRecipients"
							name="hrRecipients"
							rows="3"
							class="form-input"
							placeholder="<?php p($l->t('hr@example.com, office@example.com')); ?>"
							aria-describedby="hrRecipients-help"><?php p((string)($settings['recipients'] ?? '')); ?></textarea>
						<p id="hrRecipients-help" class="form-help">
							<?php p($l->t('Use valid email addresses separated by commas. Duplicates are removed automatically.')); ?>
						</p>
					</div>

					<h3 id="notification-matrix-heading" class="admin-settings-subsection__title"><?php p($l->t('Rules by absence type and event')); ?></h3>
					<p class="form-help form-help--block">
						<?php p($l->t('Activate exactly which event should trigger an HR email for each absence type. Disabled cells mean no email is sent for that combination.')); ?>
					</p>
					<div class="table-container azc-table-wrap admin-notifications-matrix-wrap">
						<table class="grid-table admin-notifications-matrix azc-table--matrix" role="table" aria-labelledby="notification-matrix-heading">
							<caption class="sr-only"><?php p($l->t('Notification rules by absence type and event')); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php p($l->t('Absence type')); ?></th>
									<?php foreach ($eventTypes as $event): ?>
										<th scope="col"><?php p($event['label'] ?? (string)$event['key']); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($absenceTypes as $type): ?>
									<?php $typeKey = (string)($type['key'] ?? ''); ?>
									<tr>
										<th scope="row"><?php p($type['label'] ?? $typeKey); ?></th>
										<?php foreach ($eventTypes as $event): ?>
											<?php
											$eventKey = (string)($event['key'] ?? '');
											$enabled = !empty($settings['matrix'][$typeKey][$eventKey]);
											$inputId = 'rule_' . $typeKey . '_' . $eventKey;
											?>
											<td>
												<div class="form-checkbox form-checkbox--center">
													<input type="checkbox"
														id="<?php p($inputId); ?>"
														name="matrix[<?php p($typeKey); ?>][<?php p($eventKey); ?>]"
														<?php echo $enabled ? 'checked' : ''; ?>
														aria-label="<?php p($l->t('%1$s -> %2$s', [$type['label'] ?? $typeKey, $event['label'] ?? $eventKey])); ?>">
												</div>
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					</div>
                    </div>
				</section>

                </div><!-- /.azc-admin-notifications-form__sections -->

                <div class="azc-admin-notifications-form__actions" role="group" aria-labelledby="admin-notifications-actions-heading">
                    <h2 id="admin-notifications-actions-heading" class="visually-hidden"><?php p($l->t('Save and leave')); ?></h2>
                    <div id="admin-notifications-live" class="admin-notifications-live azc-admin-notifications-live" role="status" aria-live="polite" aria-atomic="true"></div>
                    <div class="azc-admin-notifications-form__footer">
                        <button type="submit"
                            class="azc-btn azc-btn--primary"
                            id="admin-notifications-save"
                            aria-label="<?php p($l->t('Save notification settings')); ?>"
                            title="<?php p($l->t('Save changes to notification rules, overtime alerts, and HR emails')); ?>">
                            <?php p($l->t('Save notification settings')); ?>
                        </button>
                        <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.settings')); ?>"
                            class="azc-btn azc-btn--secondary"
                            aria-label="<?php p($l->t('Back to global settings without saving')); ?>"
                            title="<?php p($l->t('Open global admin settings')); ?>">
                            <?php p($l->t('Back to global settings')); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.adminNotificationsApiUrl = <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.admin.updateNotificationSettings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.adminNotificationSettings = <?php echo json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.notificationMatrixMeta = {
	absenceTypes: <?php echo json_encode($absenceTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
	eventTypes: <?php echo json_encode($eventTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};
window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
window.ArbeitszeitCheck.l10n.notificationsSaved = <?php echo json_encode($l->t('Notification settings updated successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.invalidRecipients = <?php echo json_encode($l->t('Please enter at least one valid recipient email address.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.invalidBalanceTrafficLightRecipients = <?php echo json_encode($l->t('Please enter at least one valid balance traffic light recipient email address (overtime/undertime).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.invalidThresholdValues = <?php echo json_encode($l->t('Threshold values must be valid numbers.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.invalidThresholdOrder = <?php echo json_encode($l->t('Yellow thresholds must be less than or equal to red thresholds.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.invalidBankFillOrder = <?php echo json_encode($l->t('Bank fill yellow percent must be less than or equal to red percent.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.invalidCarryoverMaxDays = <?php echo json_encode($l->t('Maximum carryover days must be empty (unlimited) or between 0 and 366'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ArbeitszeitCheck.l10n.failedToSaveNotifications = <?php echo json_encode($l->t('Failed to save notification settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

</div><!-- /.azc-page-stack -->
<?php include __DIR__ . '/common/page-end.php'; ?>
