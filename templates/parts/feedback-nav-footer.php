<?php

declare(strict_types=1);

/**
 * Nav footer: Report a problem / Suggest an improvement / Open GitHub Issues.
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
$titleId = $prefix . '-nav-footer-title';
$newTab = $l->t('(opens in a new tab)');
?>
<nav
	class="<?php p($prefix); ?>-nav-footer"
	id="<?php p($footerId); ?>"
	data-app-feedback="1"
	data-app-feedback-app="<?php p((string)$links['appId']); ?>"
	aria-labelledby="<?php p($titleId); ?>"
>
	<p class="<?php p($prefix); ?>-nav-footer__title" id="<?php p($titleId); ?>">
		<?php p($l->t('Help')); ?>
	</p>
	<ul class="<?php p($prefix); ?>-nav-footer__list">
		<li>
			<a
				class="<?php p($prefix); ?>-nav-footer__link"
				id="<?php p($prefix); ?>-feedback-problem"
				href="<?php p((string)$links['problemMailto']); ?>"
				data-app-feedback-kind="problem"
			><?php p($l->t('Report a problem')); ?></a>
		</li>
		<li>
			<a
				class="<?php p($prefix); ?>-nav-footer__link"
				id="<?php p($prefix); ?>-feedback-idea"
				href="<?php p((string)$links['ideaMailto']); ?>"
				data-app-feedback-kind="idea"
			><?php p($l->t('Suggest an improvement')); ?></a>
		</li>
		<?php if ($github !== ''): ?>
		<li>
			<a
				class="<?php p($prefix); ?>-nav-footer__link"
				id="<?php p($prefix); ?>-feedback-github"
				href="<?php p($github); ?>"
				target="_blank"
				rel="noopener noreferrer"
			><?php p($l->t('Open GitHub Issues')); ?><span class="<?php p($prefix); ?>-nav-footer__new-tab"><?php p($newTab); ?></span></a>
		</li>
		<?php endif; ?>
	</ul>
	<p class="<?php p($prefix); ?>-nav-footer__hint">
		<?php p($l->t('Email is best-effort — no reply SLA. Need booked help? Use Support & us.')); ?>
	</p>
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
</nav>
