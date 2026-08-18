<?php

declare(strict_types=1);

/**
 * Get the App — ArbeitszeitCheck Mobile + Terminal (published on Google Play).
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCA\ArbeitszeitCheck\Service\IconCatalog;
use OCA\ArbeitszeitCheck\Support\MobileAppLinks;

$urls = is_array($_['urls'] ?? null) ? $_['urls'] : [];
$playStore = (string)($urls['playStore'] ?? MobileAppLinks::PLAY_STORE_URL);
if (!str_starts_with($playStore, 'https://play.google.com/')) {
	$playStore = MobileAppLinks::PLAY_STORE_URL;
}
$kioskPlay = (string)($urls['kioskPlayStore'] ?? MobileAppLinks::KIOSK_PLAY_STORE_URL);
if (!str_starts_with($kioskPlay, 'https://play.google.com/')) {
	$kioskPlay = MobileAppLinks::KIOSK_PLAY_STORE_URL;
}
$productPage = (string)($urls['mobileProductPage'] ?? '');
$privacyPage = (string)($urls['mobilePrivacyPage'] ?? '');
$kioskPrivacy = (string)($urls['kioskPrivacyPage'] ?? '');
if ($productPage !== '' && !str_starts_with($productPage, 'https://')) {
	$productPage = '';
}
if ($privacyPage !== '' && !str_starts_with($privacyPage, 'https://')) {
	$privacyPage = '';
}
if ($kioskPrivacy !== '' && !str_starts_with($kioskPrivacy, 'https://')) {
	$kioskPrivacy = '';
}

$features = [
	[
		'icon' => 'clock',
		'title' => $l->t('Clock in from your phone'),
		'hint' => $l->t('Start and stop work, and see today’s hours, without opening a laptop.'),
	],
	[
		'icon' => 'calendar-off',
		'title' => $l->t('Request leave on the go'),
		'hint' => $l->t('Submit absences and follow their status from the official mobile app.'),
	],
	[
		'icon' => 'tablet',
		'title' => $l->t('Foyer terminal for shared tablets'),
		'hint' => $l->t('The Terminal app is for reception or shop-floor tablets. IT licenses each device.'),
	],
	[
		'icon' => 'lock',
		'title' => $l->t('Sign in safely'),
		'hint' => $l->t('Uses Nextcloud Login Flow — your main password is never stored in the app.'),
	],
];

include __DIR__ . '/common/page-start.php';
?>
<div class="azc-get-app-page azc-page-stack">
	<section class="azc-get-app__hero" aria-labelledby="azc-get-app-intro-title">
		<p class="azc-get-app__eyebrow"><?php p($l->t('Official Android apps')); ?></p>
		<h2 id="azc-get-app-intro-title" class="azc-get-app__title"><?php p($l->t('Phone and foyer terminal')); ?></h2>
		<p class="azc-get-app__lead">
			<?php p($l->t('Clock in, request leave, and see your hours from your phone. Foyer tablets use the Terminal app. Both connect to this Nextcloud. Your organisation licenses seats — the web app stays free.')); ?>
		</p>
		<div class="azc-get-app__cta">
			<a class="azc-btn azc-btn--primary azc-get-app__play" href="<?php p($playStore); ?>" target="_blank" rel="noopener noreferrer">
				<span class="azc-get-app__play-icon" aria-hidden="true"><?php print_unescaped(IconCatalog::render('smartphone')); ?></span>
				<span class="azc-get-app__play-label"><?php p($l->t('ArbeitszeitCheck Mobile on Google Play')); ?></span>
				<span class="visually-hidden"><?php p($l->t('(opens in a new tab)')); ?></span>
			</a>
			<a class="azc-btn azc-btn--primary azc-get-app__play" href="<?php p($kioskPlay); ?>" target="_blank" rel="noopener noreferrer">
				<span class="azc-get-app__play-icon" aria-hidden="true"><?php print_unescaped(IconCatalog::render('tablet')); ?></span>
				<span class="azc-get-app__play-label"><?php p($l->t('Terminal app on Google Play')); ?></span>
				<span class="visually-hidden"><?php p($l->t('(opens in a new tab)')); ?></span>
			</a>
			<p class="azc-get-app__price-hint">
				<?php p($l->t('Free to download. Your IT assigns official mobile and terminal seats on the License page.')); ?>
			</p>
		</div>
	</section>

	<section class="azc-get-app__features-block" aria-labelledby="azc-get-app-features-title">
		<h2 id="azc-get-app-features-title" class="azc-get-app__section-title"><?php p($l->t('What you can do')); ?></h2>
		<ul class="azc-get-app__features">
			<?php foreach ($features as $feature): ?>
				<li class="azc-get-app__feature">
					<span class="azc-get-app__icon-well azc-get-app__icon-well--feature" aria-hidden="true">
						<?php print_unescaped(IconCatalog::render((string)$feature['icon'])); ?>
					</span>
					<div class="azc-get-app__feature-copy">
						<span class="azc-get-app__feature-title"><?php p((string)$feature['title']); ?></span>
						<span class="azc-get-app__feature-hint"><?php p((string)$feature['hint']); ?></span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php
	$actionRows = [];
	if ($productPage !== '') {
		$actionRows[] = ['href' => $productPage, 'label' => $l->t('Product page')];
	}
	if ($privacyPage !== '') {
		$actionRows[] = ['href' => $privacyPage, 'label' => $l->t('Privacy policy for the mobile app')];
	}
	if ($kioskPrivacy !== '') {
		$actionRows[] = ['href' => $kioskPrivacy, 'label' => $l->t('Privacy policy for the terminal app')];
	}
	?>
	<?php if ($actionRows !== []): ?>
		<nav class="azc-get-app__actions" aria-label="<?php p($l->t('More information')); ?>">
			<?php foreach ($actionRows as $row): ?>
				<a class="azc-get-app__action" href="<?php p((string)$row['href']); ?>" target="_blank" rel="noopener noreferrer">
					<span class="azc-get-app__action-label"><?php p((string)$row['label']); ?></span>
					<span class="visually-hidden"><?php p($l->t('(opens in a new tab)')); ?></span>
					<span class="azc-get-app__action-external" aria-hidden="true">↗</span>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</div>
<?php include __DIR__ . '/common/page-end.php'; ?>
