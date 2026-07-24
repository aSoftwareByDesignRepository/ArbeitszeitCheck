<?php

declare(strict_types=1);

/**
 * Support & Us — admin settings surface (family standard).
 *
 * Expected variables (set by the including settings template):
 * @var \OCP\IL10N $l
 * @var \OCA\ArbeitszeitCheck\Support\SupportUsLinks $supportUsLinks
 * @var string $supportUsCssPrefix CSS BEM prefix without trailing dash (e.g. azc, bc, dc)
 * @var string $supportUsBtnPrimaryClass
 * @var string $supportUsBtnSecondaryClass
 * @var string|null $supportUsLanguageCode optional override; defaults to $l->getLanguageCode()
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

if (!isset($supportUsLinks) || !$supportUsLinks instanceof \OCA\ArbeitszeitCheck\Support\SupportUsLinks) {
	return;
}

$l = $l ?? (\OCP\Util::getL10N('arbeitszeitcheck'));
$lang = isset($supportUsLanguageCode) && is_string($supportUsLanguageCode) && $supportUsLanguageCode !== ''
	? $supportUsLanguageCode
	: (method_exists($l, 'getLanguageCode') ? (string)$l->getLanguageCode() : 'en');
$links = $supportUsLinks->forLocale($lang);
$prefix = isset($supportUsCssPrefix) && is_string($supportUsCssPrefix) && $supportUsCssPrefix !== ''
	? preg_replace('/[^a-z0-9\-]/i', '', $supportUsCssPrefix)
	: 'azc';
$btnPrimary = isset($supportUsBtnPrimaryClass) && is_string($supportUsBtnPrimaryClass) && $supportUsBtnPrimaryClass !== ''
	? $supportUsBtnPrimaryClass
	: 'button primary';
$btnSecondary = isset($supportUsBtnSecondaryClass) && is_string($supportUsBtnSecondaryClass) && $supportUsBtnSecondaryClass !== ''
	? $supportUsBtnSecondaryClass
	: 'button';
$appName = (string)$links['appDisplayName'];
$sectionId = $prefix . '-support-us';
$titleId = $prefix . '-support-us-title';
$introId = $prefix . '-support-us-intro';
?>
<section
	class="<?php p($prefix); ?>-card <?php p($prefix); ?>-section <?php p($prefix); ?>-support-us"
	id="<?php p($sectionId); ?>"
	aria-labelledby="<?php p($titleId); ?>"
	aria-describedby="<?php p($introId); ?>"
	data-support-us="1"
>
	<header class="<?php p($prefix); ?>-section__header <?php p($prefix); ?>-support-us__header">
		<div>
			<h2 id="<?php p($titleId); ?>" class="<?php p($prefix); ?>-card__title <?php p($prefix); ?>-support-us__title">
				<?php p($l->t('Support & us')); ?>
			</h2>
			<p id="<?php p($introId); ?>" class="<?php p($prefix); ?>-section__sub <?php p($prefix); ?>-support-us__intro">
				<?php p($l->t(
					'%s stays free (AGPL) on your Nextcloud. GitHub issues for bugs and ideas remain welcome. For bookable help on an invoice — setup, hour packs, commissioned work — or the official mobile app:',
					[$appName]
				)); ?>
			</p>
		</div>
	</header>

	<div class="<?php p($prefix); ?>-support-us__body">
		<div class="<?php p($prefix); ?>-support-us__primary">
			<a
				class="<?php p($btnPrimary); ?> <?php p($prefix); ?>-support-us__cta <?php p($prefix); ?>-support-us__cta--primary"
				href="<?php p($links['partnerMailto']); ?>"
			>
				<?php p($l->t('Ask for a partner offer')); ?>
			</a>
			<p class="<?php p($prefix); ?>-support-us__hint">
				<?php p($l->t('Annual hour pack + priority response — details in the offer / on our site.')); ?>
				<a
					href="<?php p($links['supportPageUrl']); ?>"
					target="_blank"
					rel="noopener noreferrer"
				><?php p($l->t('Open support page')); ?></a>
			</p>
		</div>

		<div class="<?php p($prefix); ?>-support-us__secondary" role="group" aria-label="<?php p($l->t('Additional support options')); ?>">
			<a
				class="<?php p($btnSecondary); ?> <?php p($prefix); ?>-support-us__cta"
				href="<?php p($links['onboardingMailto']); ?>"
			>
				<?php p($l->t('Ask about setup or training')); ?>
			</a>
			<a
				class="<?php p($btnSecondary); ?> <?php p($prefix); ?>-support-us__cta"
				href="<?php p($links['featureMailto']); ?>"
			>
				<?php p($l->t('Request a commissioned feature')); ?>
			</a>
			<?php if (!empty($links['hasOfficialMobileLicenses']) && !empty($links['licensePageUrl'])): ?>
				<a
					class="<?php p($btnSecondary); ?> <?php p($prefix); ?>-support-us__cta"
					href="<?php p($links['licensePageUrl']); ?>"
				>
					<?php p($l->t('Official mobile & terminal licenses')); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="<?php p($prefix); ?>-support-us__tertiary">
			<p class="<?php p($prefix); ?>-support-us__more">
				<a
					href="<?php p($links['appsPageUrl']); ?>"
					target="_blank"
					rel="noopener noreferrer"
				><?php p($l->t('More Check apps')); ?></a>
				<span aria-hidden="true"> · </span>
				<a
					href="<?php p($links['sponsorsUrl']); ?>"
					target="_blank"
					rel="noopener noreferrer"
				><?php p($l->t('GitHub Sponsors (voluntary, no invoice SLA)')); ?></a>
			</p>
			<p class="<?php p($prefix); ?>-support-us__contact">
				<a href="<?php p($links['contactMailto']); ?>"><?php p($links['contactEmail']); ?></a>
				<span aria-hidden="true"> · </span>
				<span><?php p($links['vendorName']); ?></span>
			</p>
		</div>
	</div>
</section>
