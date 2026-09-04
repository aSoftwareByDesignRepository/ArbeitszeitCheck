<?php

declare(strict_types=1);

$azcSettingsShowCardChrome = !empty($azcSettingsShowCardChrome) || !empty($renderAll);

/**
 * Admin global settings: enable/disable ProjectCheck integration for all users.
 *
 * Expects:
 *   - $projectCheckAvailable (bool) — ProjectCheck enabled on this Nextcloud
 *   - $projectCheckEnabledForCurrentUser (bool) — current admin may open ProjectCheck
 *   - $projectCheckAppsUrl (string) — Nextcloud Apps page
 *   - $settings['projectCheckIntegrationEnabled'] (bool)
 *   - $l
 *
 * @var \OCP\IL10N $l
 */

$pcAvailable = !empty($projectCheckAvailable);
$pcEnabledForMe = !empty($projectCheckEnabledForCurrentUser);
$pcEnabled = !empty($settings['projectCheckIntegrationEnabled']);
$pcAppsUrl = isset($projectCheckAppsUrl) ? (string)$projectCheckAppsUrl : '';
?>
<section class="azc-card admin-settings-section azc-projectcheck-admin-settings" aria-labelledby="section-projectcheck-heading">
	                    <header class="azc-card__header<?php echo empty($azcSettingsShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
                        <div class="azc-card__header-text">
                            <?php if (!empty($azcSettingsShowCardChrome)): ?>
                            <h2 id="section-projectcheck-heading" class="azc-card__title"><?php p($l->t('ProjectCheck connection')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('Optionally let employees link working time to ProjectCheck projects.')); ?></p>
                            <?php else: ?>
                            <h2 id="section-projectcheck-heading" class="azc-card__title visually-hidden"><?php p($l->t('ProjectCheck connection')); ?></h2>
                            <?php endif; ?>
                        </div>
                    </header>
	<div class="azc-card__body">
	<?php if (!$pcAvailable): ?>
		<?php
		$calloutVariant = 'warning';
		$calloutRole = 'status';
		$calloutId = 'azc-projectcheck-app-required';
		$calloutTitle = $l->t('ProjectCheck app required');
		$calloutText = $l->t('Install and enable the ProjectCheck app on this server before you can connect ArbeitszeitCheck to it.');
		$calloutHint = $l->t('Find ProjectCheck in Nextcloud Apps and click Enable. Then return here and turn on the connection.');
		$calloutExtraClass = 'azc-projectcheck-admin-settings__missing';
		$calloutActions = [];
		if ($pcAppsUrl !== '') {
			$calloutActions[] = [
				'href' => $pcAppsUrl,
				'label' => $l->t('Open Apps'),
				'class' => 'azc-btn azc-btn--primary azc-btn--touch',
			];
		}
		$calloutElement = 'div';
		include __DIR__ . '/../common/alert-callout.php';
		?>
	<?php else: ?>
		<p class="form-help admin-settings-section__intro">
			<?php p($l->t('One switch for your whole organisation. When it is on, every employee who uses ArbeitszeitCheck can optionally link their hours to a ProjectCheck project when they clock in or add a time entry. When it is off, no one sees a project picker and new links are blocked. The connection is off by default — turn it on here when you want to link working time to customer projects. Existing installs that already have linked time entries keep the connection on automatically after upgrade.')); ?>
		</p>

		<?php if (!$pcEnabledForMe): ?>
		<?php
		$calloutVariant = 'info';
		$calloutRole = 'status';
		$calloutId = 'azc-projectcheck-group-limited';
		$calloutTitle = $l->t('ProjectCheck is limited to some groups');
		$calloutText = $l->t('You can still turn this connection on. Only people who are allowed to use ProjectCheck will see a project picker.');
		$calloutHint = '';
		$calloutExtraClass = 'azc-projectcheck-admin-settings__group-limited';
		$calloutActions = [];
		$calloutElement = 'div';
		include __DIR__ . '/../common/alert-callout.php';
		?>
		<?php endif; ?>

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
				<?php p($l->t('Saved with Save on this page. Existing links on old time entries stay as they are; turning this off only stops new links and hides the project picker. Projects with per-person pricing still appear only for users on the project team.')); ?>
			</p>
		</div>
	<?php endif; ?>
	</div><!-- /.azc-card__body -->
</section>
