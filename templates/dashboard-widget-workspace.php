<?php

declare(strict_types=1);

use OCP\Util;

/** @var array $_ */
/** @var \OCP\IL10N $l */

Util::addTranslations('arbeitszeitcheck');
Util::addStyle('arbeitszeitcheck', 'common/colors');
Util::addStyle('arbeitszeitcheck', 'common/base');
Util::addStyle('arbeitszeitcheck', 'common/components');
Util::addStyle('arbeitszeitcheck', 'dashboard-widgets');
Util::addScript('arbeitszeitcheck', 'dashboard-widgets');

$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$isManager    = (bool)($_['isManager'] ?? false);
$isAdmin      = (bool)($_['isAdmin'] ?? false);
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
	<div id="app-content-wrapper" class="dz-workspace">

		<!-- ── Page header ──────────────────────────────────────────── -->
		<section class="section dz-header-section" aria-label="<?php p($l->t('Dashboard quick actions')); ?>">
			<div class="dz-header">
				<h2 class="dz-header__title"><?php p($l->t('Dashboard quick actions')); ?></h2>
				<p class="dz-header__desc"><?php p($l->t('Use quick actions and role-based status sections in one place.')); ?></p>
			</div>
		</section>

		<!-- ── My status ─────────────────────────────────────────────── -->
		<section class="section dz-section" id="dz-status-section" aria-labelledby="dz-employee-heading" aria-busy="true">
			<div class="dz-section__header">
				<h3 id="dz-employee-heading" class="dz-section__title"><?php p($l->t('My status')); ?></h3>
			</div>

			<div class="dz-status-card" data-status="clocked_out" id="dz-status-card">
				<div class="dz-status-card__header">
					<div class="dz-status-title-wrap">
						<span class="dz-status-icon" id="dz-status-icon" aria-hidden="true">○</span>
						<div class="dz-status-headings">
							<p class="dz-status-eyebrow"><?php p($l->t('Current status')); ?></p>
							<p id="dz-status-text" class="dz-status-text"><?php p($l->t('Clocked Out')); ?></p>
						</div>
					</div>
					<span id="dz-status-badge" class="dz-status-badge" data-status="clocked_out"> <?php p($l->t('Clocked Out')); ?> </span>
				</div>

				<div class="dz-status-metrics" aria-label="<?php p($l->t('Today summary')); ?>">
					<div class="dz-metric">
						<p class="dz-metric__label"><?php p($l->t('Worked today')); ?></p>
						<p id="dz-worked-today" class="dz-metric__value">0.00 h</p>
					</div>
					<div class="dz-metric">
						<p class="dz-metric__label"><?php p($l->t('Current session')); ?></p>
						<p id="dz-session-duration" class="dz-metric__value">00:00</p>
					</div>
				</div>

				<!-- Event-driven live region to avoid noisy poll announcements -->
				<p id="dz-live-status" class="dz-sr-only" role="status" aria-live="polite" aria-atomic="true"></p>

				<p id="dz-feedback" class="dz-feedback" hidden></p>
				<p id="dz-last-updated" class="dz-last-updated" aria-live="off"></p>
			</div>

			<p id="dz-error" class="dz-error" role="alert" hidden></p>

			<div class="dz-button-row"
				 role="group"
				 aria-label="<?php p($l->t('Time tracking quick actions')); ?>">
				<button id="dz-clock-in"
						class="btn btn--primary"
						type="button"
						disabled
						aria-disabled="true"
						aria-label="<?php p($l->t('Clock In')); ?>">
					<?php p($l->t('Clock In')); ?>
				</button>
				<button id="dz-start-break"
						class="btn btn--secondary"
						type="button"
						disabled
						aria-disabled="true"
						aria-label="<?php p($l->t('Pause')); ?>">
					<?php p($l->t('Pause')); ?>
				</button>
				<button id="dz-end-break"
						class="btn btn--secondary"
						type="button"
						disabled
						aria-disabled="true"
						aria-label="<?php p($l->t('Continue')); ?>">
					<?php p($l->t('Continue')); ?>
				</button>
				<button id="dz-clock-out"
						class="btn btn--danger"
						type="button"
						disabled
						aria-disabled="true"
						aria-label="<?php p($l->t('Clock Out')); ?>">
					<?php p($l->t('Clock Out')); ?>
				</button>
			</div>

			<div class="dz-link-row">
				<a class="btn btn--secondary"
				   href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.timeEntries')); ?>">
					<?php p($l->t('Open time entries')); ?>
				</a>
			</div>
		</section>

		<!-- ── Team overview (manager only) ─────────────────────────── -->
		<?php if ($isManager): ?>
		<section class="section dz-section" aria-labelledby="dz-manager-heading">
			<div class="dz-section__header">
				<h3 id="dz-manager-heading" class="dz-section__title"><?php p($l->t('Team overview')); ?></h3>
			</div>
			<div id="dz-manager-list" class="dz-people-list" aria-live="polite"></div>
		</section>
		<?php endif; ?>

		<!-- ── Company overview (admin only) ────────────────────────── -->
		<?php if ($isAdmin): ?>
		<section class="section dz-section" aria-labelledby="dz-admin-heading">
			<div class="dz-section__header">
				<h3 id="dz-admin-heading" class="dz-section__title"><?php p($l->t('Company overview')); ?></h3>
			</div>
			<div id="dz-admin-list" class="dz-people-list" aria-live="polite"></div>
		</section>
		<?php endif; ?>

	</div><!-- /#app-content-wrapper -->
</div><!-- /#app-content -->

<!--
	Config block: type="application/json" is never executed by the browser,
	so no CSP nonce is required. JSON_HEX_TAG prevents </script> injection.
-->
<script type="application/json" id="dz-config"><?php echo json_encode([
	'employeeDataUrl' => $urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.employeeData'),
	'managerDataUrl'  => $urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.managerData'),
	'adminDataUrl'    => $urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.adminData'),
	'clockInUrl'      => $urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.clockIn'),
	'startBreakUrl'   => $urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.startBreak'),
	'endBreakUrl'     => $urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.endBreak'),
	'clockOutUrl'     => $urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.clockOut'),
	'isManager'       => $isManager,
	'isAdmin'         => $isAdmin,
	'l10n'            => [
		'working'         => $l->t('Working'),
		'onBreak'         => $l->t('On Break'),
		'paused'          => $l->t('Paused'),
		'continueLabel'   => $l->t('Continue'),
		'pauseLabel'      => $l->t('Pause'),
		'clockedOut'      => $l->t('Clocked Out'),
		'workedTodayLabel'=> $l->t('Worked today'),
		'sessionLabel'    => $l->t('Current session'),
		'statusLine'      => $l->t('Status: %1$s'),
		'peopleRow'       => $l->t('%1$s: %2$s (%3$s h)'),
		'noEntriesFound'  => $l->t('No entries found.'),
		'noTeamMembers'   => $l->t('No team members found.'),
		'noUsersFound'    => $l->t('No users found.'),
		'actionFailed'    => $l->t('Action failed'),
		'networkError'    => $l->t('Could not load status. Please check your connection.'),
		'sessionExpired'  => $l->t('Your session has expired. Please refresh the page and try again.'),
		'errorTitle'      => $l->t('ArbeitszeitCheck'),
		'lastUpdated'     => $l->t('Last updated: %1$s'),
		'actionDone'      => $l->t('%1$s successful'),
		'loading'         => $l->t('Loading…'),
	],
], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
