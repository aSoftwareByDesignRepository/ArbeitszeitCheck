<?php

declare(strict_types=1);

/**
 * Nav footer: single "Help & Feedback" button that opens a dropdown with
 * Report a problem / Suggest an improvement / GitHub Issues.
 *
 * Expected variables (set by the including template):
 * @var \OCP\IL10N $l
 * @var \OCA\ArbeitszeitCheck\Support\AppFeedbackLinks $appFeedbackLinks optional; constructed when omitted
 * @var string $appFeedbackCssPrefix CSS BEM prefix (e.g. azc, dc, crm)
 * @var string|null $appFeedbackLanguageCode
 * @var string|null $appFeedbackVersion
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

use OCA\ArbeitszeitCheck\Support\AppFeedbackLinks;
use OCA\ArbeitszeitCheck\Service\IconCatalog;

$l = $l ?? (\OCP\Util::getL10N('arbeitszeitcheck'));
$prefix = isset($appFeedbackCssPrefix) && is_string($appFeedbackCssPrefix) && $appFeedbackCssPrefix !== ''
	? preg_replace('/[^a-z0-9\-]/i', '', $appFeedbackCssPrefix)
	: 'azc';
$lang = isset($appFeedbackLanguageCode) && is_string($appFeedbackLanguageCode) && $appFeedbackLanguageCode !== ''
	? $appFeedbackLanguageCode
	: (method_exists($l, 'getLanguageCode') ? (string)$l->getLanguageCode() : 'en');
$version = isset($appFeedbackVersion) && is_string($appFeedbackVersion) ? $appFeedbackVersion : '';
if (!isset($appFeedbackLinks) || !$appFeedbackLinks instanceof AppFeedbackLinks) {
	$appFeedbackLinks = new AppFeedbackLinks('arbeitszeitcheck', 'ArbeitszeitCheck', $version);
}
$pageUrl = '';
if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
	$pageUrl = $appFeedbackLinks->sanitizePageUrl((string)$_SERVER['REQUEST_URI']);
}
$ncVersion = '';
if (class_exists(\OCP\Server::class)) {
	try {
		$config = \OCP\Server::get(\OCP\IConfig::class);
		$ncVersion = (string)$config->getSystemValue('version', '');
	} catch (\Throwable) {
		$ncVersion = '';
	}
}
$ctx = [
	'pageUrl' => $pageUrl,
	'locale' => $lang,
	'ncVersion' => $ncVersion,
];
$links = $appFeedbackLinks->forLocale($lang, $ctx);
$github = (string)($links['githubIssuesUrl'] ?? '');
$footerId = $prefix . '-nav-footer';
$menuId = $prefix . '-feedback-menu';
$newTab = $l->t('(opens in a new tab)');
?>
<div
	class="<?php p($prefix); ?>-nav-footer"
	id="<?php p($footerId); ?>"
	data-app-feedback="1"
	data-app-feedback-app="<?php p((string)$links['appId']); ?>"
>
	<div class="<?php p($prefix); ?>-nav-footer__popover">
		<button
			type="button"
			class="<?php p($prefix); ?>-nav-footer__trigger"
			aria-expanded="false"
			aria-controls="<?php p($menuId); ?>"
			aria-haspopup="true"
		>
			<span class="azc-nav-footer__trigger-icon" aria-hidden="true"><?php
				print_unescaped(IconCatalog::render('info'));
			?></span>
			<span class="azc-nav-footer__trigger-label"><?php p($l->t('Help & Feedback')); ?></span>
		</button>
		<ul
			class="<?php p($prefix); ?>-nav-footer__menu"
			id="<?php p($menuId); ?>"
			role="menu"
			hidden
		>
			<li role="none">
				<a
					class="<?php p($prefix); ?>-nav-footer__menu-item"
					role="menuitem"
					id="<?php p($prefix); ?>-feedback-problem"
					href="<?php p((string)$links['problemMailto']); ?>"
					data-app-feedback-kind="problem"
				>
					<span class="azc-nav-footer__menu-icon" aria-hidden="true"><?php
						print_unescaped(IconCatalog::render('circle-alert'));
					?></span>
					<?php p($l->t('Report a problem')); ?>
				</a>
			</li>
			<li role="none">
				<a
					class="<?php p($prefix); ?>-nav-footer__menu-item"
					role="menuitem"
					id="<?php p($prefix); ?>-feedback-idea"
					href="<?php p((string)$links['ideaMailto']); ?>"
					data-app-feedback-kind="idea"
				>
					<span class="azc-nav-footer__menu-icon" aria-hidden="true"><?php
						print_unescaped(IconCatalog::render('edit'));
					?></span>
					<?php p($l->t('Suggest an improvement')); ?>
				</a>
			</li>
			<?php if ($github !== ''): ?>
			<li role="none">
				<a
					class="<?php p($prefix); ?>-nav-footer__menu-item"
					role="menuitem"
					id="<?php p($prefix); ?>-feedback-github"
					href="<?php p($github); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					<span class="azc-nav-footer__menu-icon" aria-hidden="true"><?php
						print_unescaped(IconCatalog::render('file-text'));
					?></span>
					<?php p($l->t('GitHub Issues')); ?>
					<span class="<?php p($prefix); ?>-nav-footer__new-tab"><?php p($newTab); ?></span>
				</a>
			</li>
			<?php endif; ?>
		</ul>
	</div>
	<script type="application/json" id="<?php p($prefix); ?>-app-feedback-config"><?php
		print_unescaped(json_encode([
			'appId' => $links['appId'],
			'appDisplayName' => $links['appDisplayName'],
			'appVersion' => $links['appVersion'],
			'feedbackEmail' => $links['feedbackEmail'],
			'githubIssuesUrl' => $github,
			'problemMailto' => $links['problemMailto'],
			'ideaMailto' => $links['ideaMailto'],
			'cssPrefix' => $prefix,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
	?></script>
</div>
