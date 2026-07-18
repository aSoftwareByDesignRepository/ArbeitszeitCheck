<?php
/**
 * ArbeitszeitCheck unified page chrome (open).
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCA\ArbeitszeitCheck\Service\IconCatalog;

$pageId = isset($_['pageId']) ? (string)$_['pageId'] : 'dashboard';
$shellWidth = (string)($_['shellWidth'] ?? 'standard');
$shellWidthClass = match ($shellWidth) {
	'wide' => ' azc-shell--wide',
	'constrained' => ' azc-shell--constrained',
	'minimal' => ' azc-shell--minimal',
	default => '',
};
$pageTitle = (string)($_['pageTitle'] ?? '');
$pageHelp = (string)($_['pageHelp'] ?? '');
$breadcrumbSection = (string)($_['breadcrumbSection'] ?? '');
$roleLabel = (string)($_['roleLabel'] ?? $l->t('Employee'));
$urls = $_['urls'] ?? [];
$clientHints = $_['clientHints'] ?? ['locale' => 'en-US', 'htmlLang' => 'en-US', 'timezone' => 'UTC'];
$azcHtmlLang = (string)($clientHints['htmlLang'] ?? $clientHints['locale'] ?? 'en-US');
$timezone = (string)($clientHints['timezone'] ?? 'UTC');
$pendingCorrectionCount = (int)($_['pendingCorrectionCount'] ?? 0);

$pageIcons = [
	'dashboard' => 'layout-grid',
	'time-entries' => 'clock',
	'absences' => 'calendar-off',
	'calendar' => 'calendar',
	'timeline' => 'activity',
	'compliance' => 'shield-check',
	'compliance-violations' => 'alert-triangle',
	'compliance-reports' => 'file-analytics',
	'settings' => 'settings',
	'reports' => 'file-text',
	'substitution-requests' => 'user-check',
	'manager-dashboard' => 'users',
	'manager-time-entries' => 'clock',
	'manager-absences' => 'calendar-off',
	'manager-month-closures' => 'file-down',
	'admin-dashboard' => 'layout-grid',
	'admin-notifications' => 'bell',
	'admin-overtime-payouts' => 'coins',
	'admin-overtime-payout-audit' => 'clipboard-list',
	'admin-users' => 'users',
	'admin-user-detail' => 'user-check',
	'admin-working-time-models' => 'briefcase',
	'admin-tariff-rules' => 'layers',
	'admin-holidays' => 'calendar-heart',
	'admin-teams' => 'building-2',
	'admin-vacation-layers' => 'layers',
	'admin-audit-log' => 'scroll-text',
	'admin-license' => 'key',
	'admin-kiosk' => 'tablet',
	'admin-settings' => 'shield',
	'access-denied' => 'lock',
];
$headerIconName = $pageIcons[$pageId] ?? 'layout-grid';

$urlsJson = htmlspecialchars(json_encode($urls, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$roleSlug = (string)($_['roleSlug'] ?? 'employee');
?>
<?php include __DIR__ . '/navigation.php'; ?>
<div id="app-content" class="azc-app azc-app--<?php p($pageId); ?>"
	lang="<?php p($azcHtmlLang); ?>"
	data-azc-locale="<?php p((string)($clientHints['locale'] ?? '')); ?>"
	data-azc-html-lang="<?php p($azcHtmlLang); ?>"
	data-azc-timezone="<?php p($timezone); ?>"
	data-azc-page="<?php p($pageId); ?>"
	data-azc-role="<?php p($roleSlug); ?>"
	data-azc-urls="<?php print_unescaped($urlsJson); ?>">
	<a class="azc-skip-link" href="#azc-main-content"><?php p($l->t('Skip to main content')); ?></a>
	<div id="azc-live-region" class="azc-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
	<div id="azc-alert-region" class="azc-sr-only" role="alert" aria-live="assertive" aria-atomic="true"></div>
	<div id="app-content-wrapper" class="azc-shell<?php p($shellWidthClass); ?>">
		<header class="azc-page-header" aria-labelledby="azc-page-title">
			<nav class="azc-breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
				<ol class="azc-breadcrumb__list">
					<li class="azc-breadcrumb__item">
						<a class="azc-breadcrumb__link" href="<?php p((string)($urls['dashboard'] ?? '#')); ?>">
							<?php p($l->t('ArbeitszeitCheck')); ?>
						</a>
					</li>
					<?php if ($breadcrumbSection !== ''): ?>
						<li class="azc-breadcrumb__item">
							<span class="azc-breadcrumb__text"><?php p($breadcrumbSection); ?></span>
						</li>
					<?php endif; ?>
					<li class="azc-breadcrumb__item azc-breadcrumb__item--current" aria-current="page">
						<span class="azc-breadcrumb__current"><?php p($pageTitle); ?></span>
					</li>
				</ol>
			</nav>
			<div class="azc-page-header__main">
				<div class="azc-page-header__icon" aria-hidden="true">
					<?php print_unescaped(IconCatalog::render($headerIconName, 'azc-page-header__icon-svg')); ?>
				</div>
				<div class="azc-page-header__text">
					<h1 id="azc-page-title"><?php p($pageTitle); ?></h1>
					<?php if ($pageHelp !== ''): ?>
						<p class="azc-page-header__lead"><?php p($pageHelp); ?></p>
					<?php endif; ?>
				</div>
				<div id="azc-page-actions" class="azc-page-header__actions" aria-live="polite"></div>
			</div>
			<div class="azc-scope-strip" aria-label="<?php p($l->t('Active session context')); ?>">
				<span class="azc-scope-strip__label"><?php p($l->t('Role')); ?></span>
				<span class="azc-badge azc-badge--neutral azc-scope-strip__badge"><?php p($roleLabel); ?></span>
				<span class="azc-scope-strip__sep" aria-hidden="true">·</span>
				<span class="azc-scope-strip__label"><?php p($l->t('Timezone')); ?></span>
				<span class="azc-scope-strip__value"><?php p($timezone); ?></span>
				<?php if ($pendingCorrectionCount > 0): ?>
					<span class="azc-scope-strip__sep" aria-hidden="true">·</span>
					<a class="azc-scope-strip__chip azc-badge azc-badge--warning"
						href="<?php p((string)($urls['timeEntries'] ?? '#')); ?>">
						<?php p($l->n(
							'%n pending correction',
							'%n pending corrections',
							$pendingCorrectionCount
						)); ?>
					</a>
				<?php endif; ?>
			</div>
		</header>
		<?php include __DIR__ . '/schema-upgrade-callout.php'; ?>
		<main id="azc-main-content" class="azc-main" tabindex="-1" aria-labelledby="azc-page-title">
