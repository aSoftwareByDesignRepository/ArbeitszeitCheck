<?php

declare(strict_types=1);

/**
 * Admin global settings: enable/disable ProjectCheck integration for all users.
 *
 * Expects:
 *   - $projectCheckAvailable (bool)
 *   - $settings['projectCheckIntegrationEnabled'] (bool)
 *   - $l
 *
 * @var \OCP\IL10N $l
 */

$pcAvailable = !empty($projectCheckAvailable);
$pcEnabled = !empty($settings['projectCheckIntegrationEnabled']);
?>
<section class="azc-card admin-settings-section azc-projectcheck-admin-settings" aria-labelledby="section-projectcheck-heading">
	<header class="azc-card__header">
		<div class="azc-card__header-text">
			<h2 id="section-projectcheck-heading" class="azc-card__title"><?php p($l->t('ProjectCheck connection')); ?></h2>
			<p class="azc-card__lead"><?php p($l->t('Optionally let employees link working time to ProjectCheck projects.')); ?></p>
		</div>
	</header>
	<div class="azc-card__body">
	<?php if (!$pcAvailable): ?>
		<?php
		$calloutVariant = 'warning';
		$calloutRole = 'status';
		$calloutTitle = $l->t('ProjectCheck app required');
		$calloutText = $l->t('Install and enable the ProjectCheck app on this server before you can connect ArbeitszeitCheck to it.');
		$calloutExtraClass = 'azc-projectcheck-admin-settings__missing';
		$calloutActions = [];
		$calloutElement = 'div';
		include __DIR__ . '/../common/alert-callout.php';
		?>
	<?php else: ?>
		<p class="form-help admin-settings-section__intro">
			<?php p($l->t('One switch for your whole organisation. When it is on, every employee who uses ArbeitszeitCheck can optionally link their hours to a ProjectCheck project when they clock in or add a time entry. When it is off, no one sees a project picker and new links are blocked. The connection is off by default — turn it on here when you want to link working time to customer projects. Existing installs that already have linked time entries keep the connection on automatically after upgrade.')); ?>
		</p>

		<div class="azc-projectcheck-connection" data-projectcheck-admin-connection>
			<div class="azc-projectcheck-connection__status-row">
				<span class="azc-projectcheck-connection__badge<?php echo $pcEnabled ? ' azc-projectcheck-connection__badge--on' : ' azc-projectcheck-connection__badge--off'; ?>"
					id="projectcheck-admin-status-badge"
					aria-hidden="true">
					<?php p($pcEnabled ? $l->t('Connection on') : $l->t('Connection off')); ?>
				</span>
				<p class="azc-projectcheck-connection__status-text" id="projectcheck-admin-status-text" role="status">
					<?php p($pcEnabled
						? $l->t('Employees can link time to customer projects.')
						: $l->t('Project linking is disabled for everyone until you turn this on.')); ?>
				</p>
			</div>

			<div class="azc-switch-field">
				<input type="checkbox"
					class="azc-switch-field__input"
					id="projectCheckIntegrationEnabled"
					name="projectCheckIntegrationEnabled"
					value="1"
					role="switch"
					<?php if ($pcEnabled) {
						p('checked');
					} ?>
					aria-checked="<?php p($pcEnabled ? 'true' : 'false'); ?>"
					aria-describedby="projectcheck-admin-integration-help">
				<label for="projectCheckIntegrationEnabled" class="azc-switch-field__label">
					<span class="azc-switch-field__track" aria-hidden="true"></span>
					<span class="azc-switch-field__text"><?php p($l->t('Connect ArbeitszeitCheck to ProjectCheck')); ?></span>
				</label>
			</div>

			<p id="projectcheck-admin-integration-help" class="form-help">
				<?php p($l->t('Saved with “Save all settings” below. Existing links on old time entries stay as they are; turning this off only stops new links and hides the project picker. Projects with per-person pricing still appear only for users on the project team.')); ?>
			</p>
		</div>
	<?php endif; ?>
	</div><!-- /.azc-card__body -->
</section>
