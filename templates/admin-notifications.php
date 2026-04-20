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

Util::addTranslations('arbeitszeitcheck');
Util::addStyle('arbeitszeitcheck', 'common/colors');
Util::addStyle('arbeitszeitcheck', 'common/typography');
Util::addStyle('arbeitszeitcheck', 'common/base');
Util::addStyle('arbeitszeitcheck', 'common/components');
Util::addStyle('arbeitszeitcheck', 'common/layout');
Util::addStyle('arbeitszeitcheck', 'common/app-layout');
Util::addStyle('arbeitszeitcheck', 'common/utilities');
Util::addStyle('arbeitszeitcheck', 'common/responsive');
Util::addStyle('arbeitszeitcheck', 'common/accessibility');
Util::addStyle('arbeitszeitcheck', 'navigation');
Util::addStyle('arbeitszeitcheck', 'admin-settings');
Util::addStyle('arbeitszeitcheck', 'admin-notifications');
Util::addScript('arbeitszeitcheck', 'common/utils');
Util::addScript('arbeitszeitcheck', 'common/messaging');
Util::addScript('arbeitszeitcheck', 'admin-notifications');

$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$settings = is_array($_['settings'] ?? null) ? $_['settings'] : [];
$absenceTypes = is_array($_['absenceTypes'] ?? null) ? $_['absenceTypes'] : [];
$eventTypes = is_array($_['eventTypes'] ?? null) ? $_['eventTypes'] : [];
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
	<div id="app-content-wrapper" role="main">
		<div class="section">
			<div class="section-header">
				<h2><?php p($l->t('Notification settings')); ?></h2>
				<p><?php p($l->t('Configure HR office email notifications by absence type and workflow event.')); ?></p>
			</div>

			<form id="admin-notifications-form" class="form admin-notifications-form" novalidate>
				<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken'] ?? ''); ?>">

				<section class="admin-settings-section" aria-labelledby="section-absences-heading">
					<h3 id="section-absences-heading" class="admin-settings-section__title"><?php p($l->t('Absences and notifications')); ?></h3>
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
					<fieldset class="form-fieldset" aria-labelledby="vacation-carryover-expiry-legend">
						<legend id="vacation-carryover-expiry-legend" class="form-legend"><?php p($l->t('Vacation carryover expiry')); ?></legend>
						<p class="form-help form-help--block" id="vacation-carryover-expiry-intro">
							<?php p($l->t('This is the last calendar day in each year when carryover from the opening balance (Resturlaub) may still be used for vacation. You enter each person\'s opening balance per calendar year under Users. After this date, new vacation requests can only use the annual vacation entitlement from the working time model—not carryover. This applies to everyone.')); ?>
						</p>
						<p class="form-help form-help--block form-help--note" id="vacation-carryover-expiry-how">
							<?php p($l->t('Only approved vacation counts. For working days on or before this date, carryover is used before annual entitlement. Approved absences are applied in chronological order (by start date, then id).')); ?>
						</p>
						<div class="form-row form-row--inline" role="group" aria-labelledby="vacation-carryover-expiry-legend" aria-describedby="vacation-carryover-expiry-intro vacation-carryover-expiry-how vacation-carryover-expiry-help">
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
							<?php p($l->t('Typical value in Germany: 31 March (month 3, day 31). If that day does not exist in a month (e.g. 31 February), the last day of that month is used automatically.')); ?>
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
					</fieldset>
					<fieldset class="form-fieldset" aria-labelledby="send-ical-legend">
						<legend id="send-ical-legend" class="form-legend"><?php p($l->t('Absences: Send iCal via email')); ?></legend>
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
									aria-describedby="send-ical-legend">
								<label for="sendIcalApprovedAbsences" class="form-label">
									<?php p($l->t('Send iCal to the person with approved absence')); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendIcalToSubstitute" name="sendIcalToSubstitute" value="1"
									<?php echo ($settings['sendIcalToSubstitute'] ?? false) ? 'checked' : ''; ?>
									aria-describedby="send-ical-legend">
								<label for="sendIcalToSubstitute" class="form-label">
									<?php p($l->t('Also send iCal to substitute (if selected)')); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendIcalToManagers" name="sendIcalToManagers" value="1"
									<?php echo ($settings['sendIcalToManagers'] ?? false) ? 'checked' : ''; ?>
									aria-describedby="send-ical-legend">
								<label for="sendIcalToManagers" class="form-label">
									<?php p($l->t('Also send iCal to managers (team managers)')); ?>
								</label>
							</div>
						</div>
					</fieldset>

					<fieldset class="form-fieldset" aria-labelledby="email-notifications-legend">
						<legend id="email-notifications-legend" class="form-legend"><?php p($l->t('Absences: Email notifications for substitution workflow')); ?></legend>
						<p class="form-help form-help--block">
							<?php p($l->t('When a substitute is selected, emails can be sent at each step of the approval process.')); ?>
						</p>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendEmailSubstitutionRequest" name="sendEmailSubstitutionRequest" value="1"
									<?php echo ($settings['sendEmailSubstitutionRequest'] ?? true) ? 'checked' : ''; ?>
									aria-describedby="email-notifications-legend">
								<label for="sendEmailSubstitutionRequest" class="form-label">
									<?php p($l->t('Email substitute when a substitution request is created')); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendEmailSubstituteApprovedToEmployee" name="sendEmailSubstituteApprovedToEmployee" value="1"
									<?php echo ($settings['sendEmailSubstituteApprovedToEmployee'] ?? true) ? 'checked' : ''; ?>
									aria-describedby="email-notifications-legend">
								<label for="sendEmailSubstituteApprovedToEmployee" class="form-label">
									<?php p($l->t('Email employee when substitute approves')); ?>
								</label>
							</div>
						</div>
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox" id="sendEmailSubstituteApprovedToManager" name="sendEmailSubstituteApprovedToManager" value="1"
									<?php echo ($settings['sendEmailSubstituteApprovedToManager'] ?? true) ? 'checked' : ''; ?>
									aria-describedby="email-notifications-legend">
								<label for="sendEmailSubstituteApprovedToManager" class="form-label">
									<?php p($l->t('Email managers when substitute approves (requires app teams)')); ?>
								</label>
							</div>
						</div>
					</fieldset>

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
				</section>

				<section class="admin-settings-section" aria-labelledby="hr-notifications-heading">
					<h3 id="hr-notifications-heading" class="admin-settings-section__title"><?php p($l->t('HR office notifications')); ?></h3>
					<p class="form-help form-help--block">
						<?php p($l->t('These settings define if and when HR receives email updates for absence workflows.')); ?>
					</p>
					<div class="form-group">
						<div class="form-checkbox">
							<input type="checkbox"
								id="hrNotificationsEnabled"
								name="hrNotificationsEnabled"
								<?php echo ($settings['enabled'] ?? false) ? 'checked' : ''; ?>
								aria-describedby="hrNotificationsEnabled-help">
							<label for="hrNotificationsEnabled" class="form-label">
								<?php p($l->t('Enable HR office email notifications')); ?>
							</label>
						</div>
						<p id="hrNotificationsEnabled-help" class="form-help">
							<?php p($l->t('When enabled, selected workflow events send email updates to the configured HR recipients.')); ?>
						</p>
					</div>

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

					<h4 id="notification-matrix-heading" class="admin-settings-section__title"><?php p($l->t('Rules by absence type and event')); ?></h4>
					<p class="form-help form-help--block">
						<?php p($l->t('Activate exactly which event should trigger an HR email for each absence type.')); ?>
					</p>
					<div class="table-responsive">
						<table class="grid-table admin-notifications-matrix">
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
				</section>

				<div class="card-actions">
					<button type="submit" class="btn btn--primary" id="admin-notifications-save">
						<?php p($l->t('Save notification settings')); ?>
					</button>
					<a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.settings')); ?>" class="btn btn--secondary">
						<?php p($l->t('Back to global settings')); ?>
					</a>
				</div>
				<div id="admin-notifications-live" class="form-help" role="status" aria-live="polite" aria-atomic="true"></div>
			</form>
		</div>
	</div>
</div>
</div><!-- /#arbeitszeitcheck-app -->

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
window.ArbeitszeitCheck.l10n.failedToSaveNotifications = <?php echo json_encode($l->t('Failed to save notification settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
