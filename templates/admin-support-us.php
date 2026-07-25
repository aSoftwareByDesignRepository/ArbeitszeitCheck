<?php

declare(strict_types=1);

/**
 * Dedicated Administration page: Support & us (partner / invoiceable care).
 *
 * Informational CTAs only — never gates AGPL use of the app.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 * @var \OCP\IURLGenerator $urlGenerator
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
$urlGenerator = $_['urlGenerator'] ?? null;
$appDisplayName = 'ArbeitszeitCheck';
$licensePageUrl = $urlGenerator instanceof \OCP\IURLGenerator
	? $urlGenerator->linkToRoute('arbeitszeitcheck.license_admin.index')
	: '';
$vendorLogoUrl = $urlGenerator instanceof \OCP\IURLGenerator
	? $urlGenerator->imagePath('arbeitszeitcheck', 'vendor-logo-sbd.svg')
	: '';
$vendorSiteUrl = 'https://nextcloud.software-by-design.de/';

include __DIR__ . '/common/page-start.php';
?>

<div class="azc-support-us-page" data-azc-support-us-page="1">
	<section class="azc-support-us-page__hero" aria-labelledby="azc-support-us-hero-title">
		<?php if ($vendorLogoUrl !== ''): ?>
		<div class="azc-support-us-page__brand">
			<a
				class="azc-support-us-page__brand-link"
				href="<?php p($vendorSiteUrl); ?>"
				target="_blank"
				rel="noopener noreferrer"
				aria-label="<?php p($l->t('Software by Design — open website in a new tab')); ?>"
			>
				<img
					class="azc-support-us-page__logo"
					src="<?php p($vendorLogoUrl); ?>"
					width="280"
					height="91"
					alt="<?php p($l->t('Software by Design')); ?>"
					decoding="async"
					data-azc-vendor-logo="1"
				>
			</a>
		</div>
		<?php endif; ?>
		<p class="azc-support-us-page__kicker"><?php p($l->t('Software by Design · Check apps')); ?></p>
		<h2 id="azc-support-us-hero-title" class="azc-support-us-page__headline">
			<?php p($l->t('Bookable help for your organisation — while the app stays free')); ?>
		</h2>
		<p class="azc-support-us-page__lead">
			<?php p($l->t('%s is AGPL software on your Nextcloud. Bug reports and ideas on GitHub stay welcome. When you need priority email, hour packs, workshops, or a commissioned feature, we invoice only after you accept a quote.', [$appDisplayName])); ?>
		</p>
		<ul class="azc-support-us-page__trust" aria-label="<?php p($l->t('What stays free')); ?>">
			<li><?php p($l->t('Free to run under AGPL')); ?></li>
			<li><?php p($l->t('Open-source care on GitHub stays free')); ?></li>
			<li><?php p($l->t('Partner and project work are optional and invoiceable')); ?></li>
		</ul>
	</section>

	<?php
	$supportUsLanguageCode = method_exists($l, 'getLanguageCode') ? (string)$l->getLanguageCode() : 'en';
	$supportUsCssPrefix = 'azc';
	$supportUsBtnPrimaryClass = 'azc-btn azc-btn--primary';
	$supportUsBtnSecondaryClass = 'azc-btn azc-btn--secondary';
	$supportUsPresentation = 'page';
	$supportUsLinks = new \OCA\ArbeitszeitCheck\Support\SupportUsLinks(
		$appDisplayName,
		true,
		$licensePageUrl
	);
	include __DIR__ . '/parts/support-us-section.php';
	?>
</div>

<?php include __DIR__ . '/common/page-end.php'; ?>
