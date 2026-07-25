<?php

declare(strict_types=1);

/**
 * Admin employee detail page — replaces the former edit-user modal.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');

/** @var \OCP\IURLGenerator|null $urlGenerator */
$urlGenerator = $_['urlGenerator'] ?? null;
$employeesListUrl = (string)($_['employeesListUrl'] ?? ($urlGenerator ? $urlGenerator->linkToRoute('arbeitszeitcheck.admin.users') : '#'));
$detailUserId = (string)($_['detailUserId'] ?? '');
$detailUserFound = !empty($_['detailUserFound']);
$detailDisplayName = (string)($_['detailDisplayName'] ?? $detailUserId);
$detailEmail = (string)($_['detailEmail'] ?? '');
$detailEnabled = !empty($_['detailEnabled']);
$kioskCredentialsUrl = (string)($_['kioskCredentialsUrl'] ?? ($urlGenerator
	? $urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.index') . '#azc-kiosk-creds-heading'
	: '#'));
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack admin-user-detail">

	<nav class="admin-user-detail__back" aria-label="<?php p($l->t('Breadcrumb')); ?>">
		<a class="admin-user-detail__back-link" href="<?php p($employeesListUrl); ?>">
			<span aria-hidden="true">←</span>
			<?php p($l->t('Back to employees')); ?>
		</a>
	</nav>

	<?php if (!$detailUserFound): ?>
		<section class="section admin-user-detail__missing" aria-labelledby="admin-user-detail-missing-title">
			<div class="section-content">
				<h2 id="admin-user-detail-missing-title" class="admin-user-detail__missing-title">
					<?php p($l->t('Employee not found')); ?>
				</h2>
				<p class="form-help form-help--block">
					<?php p($l->t('There is no Nextcloud account with this user ID. It may have been deleted or the link is outdated.')); ?>
				</p>
				<?php if ($detailUserId !== ''): ?>
					<p class="admin-user-detail__missing-id">
						<span class="form-label"><?php p($l->t('User ID')); ?></span>
						<code><?php p($detailUserId); ?></code>
					</p>
				<?php endif; ?>
				<p>
					<a class="azc-btn azc-btn--primary" href="<?php p($employeesListUrl); ?>">
						<?php p($l->t('Back to employees')); ?>
					</a>
				</p>
			</div>
		</section>
	<?php else: ?>
		<header class="admin-user-detail__identity" aria-label="<?php p($l->t('Employee profile')); ?>">
			<div class="admin-user-detail__identity-main">
				<dl class="admin-user-detail__meta">
					<div class="admin-user-detail__meta-item">
						<dt><?php p($l->t('User ID')); ?></dt>
						<dd><code><?php p($detailUserId); ?></code></dd>
					</div>
					<?php if ($detailEmail !== ''): ?>
						<div class="admin-user-detail__meta-item">
							<dt><?php p($l->t('Email')); ?></dt>
							<dd><?php p($detailEmail); ?></dd>
						</div>
					<?php endif; ?>
					<div class="admin-user-detail__meta-item">
						<dt><?php p($l->t('Status')); ?></dt>
						<dd>
							<?php if ($detailEnabled): ?>
								<span class="badge badge--success"><?php p($l->t('Enabled')); ?></span>
							<?php else: ?>
								<span class="badge badge--error"><?php p($l->t('Disabled')); ?></span>
							<?php endif; ?>
						</dd>
					</div>
				</dl>
			</div>
		</header>

		<details class="admin-user-detail__howto">
			<summary class="admin-user-detail__howto-summary">
				<?php p($l->t('How to edit this employee')); ?>
			</summary>
			<div class="admin-user-detail__howto-body">
				<p><?php p($l->t('Go through each section below. Open a section heading for a short explanation. When you are done, press Save at the bottom.')); ?></p>
				<ol class="admin-user-detail__howto-list">
					<li><?php p($l->t('Choose work schedule and state for holidays')); ?></li>
					<li><?php p($l->t('Choose vacation calculation mode')); ?></li>
					<li><?php p($l->t('Check preview, then save')); ?></li>
				</ol>
			</div>
		</details>

		<nav class="admin-user-detail__toc" aria-label="<?php p($l->t('Jump to section')); ?>">
			<a class="admin-user-detail__toc-link" href="#user-edit-assignment"><?php p($l->t('Work schedule')); ?></a>
			<a class="admin-user-detail__toc-link" href="#user-edit-capture"><?php p($l->t('Time recording')); ?></a>
			<a class="admin-user-detail__toc-link" href="#user-edit-vacation"><?php p($l->t('Vacation days')); ?></a>
			<a class="admin-user-detail__toc-link" href="#user-edit-overtime"><?php p($l->t('Overtime balance')); ?></a>
			<a class="admin-user-detail__toc-link" href="#user-edit-validity"><?php p($l->t('Valid from')); ?></a>
			<a class="admin-user-detail__toc-link" href="#user-edit-employment"><?php p($l->t('Employment period (for pro-rata vacation)')); ?></a>
			<a class="admin-user-detail__toc-link" href="#assignment-history"><?php p($l->t('Work schedule history')); ?></a>
			<a class="admin-user-detail__toc-link" href="#user-kiosk-credentials"><?php p($l->t('Badges & PIN')); ?></a>
		</nav>

		<div id="admin-user-detail-status" class="admin-user-detail__status" role="status" aria-live="polite" aria-atomic="true">
			<p class="admin-user-detail__status-text"><?php p($l->t('Loading…')); ?></p>
		</div>

		<div id="admin-user-detail-root" class="admin-user-detail__root" hidden></div>

		<aside class="admin-user-detail__kiosk-xref" id="user-kiosk-credentials" aria-labelledby="user-kiosk-credentials-heading">
			<h2 id="user-kiosk-credentials-heading" class="admin-user-detail__kiosk-xref-title">
				<?php p($l->t('Badges & PIN (kiosk)')); ?>
			</h2>
			<p class="admin-user-detail__kiosk-xref-text">
				<?php p($l->t('Kiosk badges (RFID/NFC) and PINs are managed in Kiosk administration, together with the foyer tablets. Open that page to allow kiosk access, generate a PIN, or scan a badge for this employee.')); ?>
			</p>
			<p class="admin-user-detail__kiosk-xref-actions">
				<a class="azc-btn azc-btn--secondary" href="<?php p($kioskCredentialsUrl); ?>">
					<?php p($l->t('Manage badges & PIN for this employee')); ?>
				</a>
			</p>
		</aside>
	<?php endif; ?>

</div><!-- /.azc-page-stack -->

<?php if ($detailUserFound): ?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
<?php include __DIR__ . '/partials/admin-user-edit-l10n.php'; ?>
	window.ArbeitszeitCheck.adminUserDetailConfig = <?php echo json_encode([
		'userId' => $detailUserId,
		'displayName' => $detailDisplayName,
		'email' => $detailEmail,
		'enabled' => $detailEnabled,
		'employeesListUrl' => $employeesListUrl,
		'organizationTimeCapture' => $_['organizationTimeCapture'] ?? ['clockStampingEnabled' => true, 'manualTimeEntryEnabled' => true],
	], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<?php endif; ?>

<?php include __DIR__ . '/common/page-end.php'; ?>
