<?php

declare(strict_types=1);

/**
 * Structured callout banner: icon + body (title, text, actions).
 *
 * Expects:
 *   $calloutVariant (info|warning|danger|success|neutral)
 *   $calloutRole (region|alert|status|note)
 *   $calloutTitle (string, optional)
 *   $calloutText (string) OR $calloutTextHtml (sanitized HTML string)
 *   $calloutActions (optional list of ['href','label','id','class'])
 *   $calloutId, $calloutTitleId, $calloutIcon, $calloutExtraClass
 *   $calloutElement (aside|div, default aside)
 *   $calloutBanner (bool, default true — adds azc-callout--banner)
 *   $calloutHint (string, optional — secondary line below text, inside body)
 *   $calloutHidden, $calloutAriaLive, $calloutAriaLabel
 */

use OCA\ArbeitszeitCheck\Service\IconCatalog;

$calloutVariant = (string)($calloutVariant ?? 'info');
$calloutRole = (string)($calloutRole ?? 'status');
$calloutHint = (string)($calloutHint ?? '');
$calloutElement = (string)($calloutElement ?? 'aside');
$calloutBanner = ($calloutBanner ?? true) !== false;
$calloutId = isset($calloutId) ? (string)$calloutId : '';
$calloutTitle = (string)($calloutTitle ?? '');
$calloutTitleId = (string)($calloutTitleId ?? ($calloutId !== '' ? $calloutId . '-title' : ''));
$calloutText = (string)($calloutText ?? '');
$calloutTextHtml = $calloutTextHtml ?? null;
$calloutExtraClass = trim((string)($calloutExtraClass ?? ''));
$calloutHidden = !empty($calloutHidden);
$calloutAriaLive = isset($calloutAriaLive) ? (string)$calloutAriaLive : '';
$calloutAriaLabel = isset($calloutAriaLabel) ? (string)$calloutAriaLabel : '';
$calloutAriaLabelledby = (string)($calloutAriaLabelledby ?? ($calloutTitleId !== '' ? $calloutTitleId : ''));
$calloutActions = is_array($calloutActions ?? null) ? $calloutActions : [];

$variantIcons = [
	'warning' => 'alert-triangle',
	'info' => 'info',
	'danger' => 'circle-alert',
	'error' => 'circle-alert',
	'success' => 'check-circle',
	'neutral' => 'info',
];
$calloutIcon = (string)($calloutIcon ?? ($variantIcons[$calloutVariant] ?? 'info'));

$classes = trim('azc-callout azc-callout--' . $calloutVariant
	. ($calloutBanner ? ' azc-callout--banner' : '')
	. ($calloutExtraClass !== '' ? ' ' . $calloutExtraClass : ''));

$headingTag = ($calloutRole === 'alert' || $calloutRole === 'region') ? 'h2' : 'p';
?>
<<?php p($calloutElement); ?>
	<?php if ($calloutId !== '') { ?>id="<?php p($calloutId); ?>" <?php } ?>
	class="<?php p($classes); ?>"
	role="<?php p($calloutRole); ?>"
	<?php if ($calloutAriaLabel !== '') { ?>aria-label="<?php p($calloutAriaLabel); ?>"<?php } elseif ($calloutAriaLabelledby !== '') { ?>aria-labelledby="<?php p($calloutAriaLabelledby); ?>"<?php } ?>
	<?php if ($calloutAriaLive !== '') { ?>aria-live="<?php p($calloutAriaLive); ?>"<?php } ?>
	<?php if ($calloutHidden) { ?>hidden<?php } ?>
>
	<?php print_unescaped(IconCatalog::renderCalloutWell($calloutIcon, $calloutVariant)); ?>
	<div class="azc-callout__body">
		<?php if ($calloutTitle !== '' && $calloutTitleId !== '') { ?>
		<<?php print_unescaped($headingTag); ?> id="<?php p($calloutTitleId); ?>" class="azc-callout__title"><?php p($calloutTitle); ?></<?php print_unescaped($headingTag); ?>>
		<?php } elseif ($calloutTitle !== '') { ?>
		<<?php print_unescaped($headingTag); ?> class="azc-callout__title"><?php p($calloutTitle); ?></<?php print_unescaped($headingTag); ?>>
		<?php } ?>
		<?php if ($calloutTextHtml !== null && $calloutTextHtml !== '') { ?>
		<p class="azc-callout__text"><?php print_unescaped($calloutTextHtml); ?></p>
		<?php } elseif ($calloutText !== '') { ?>
		<p class="azc-callout__text"><?php p($calloutText); ?></p>
		<?php } ?>
		<?php if ($calloutHint !== '') { ?>
		<p class="azc-callout__hint"><?php p($calloutHint); ?></p>
		<?php } ?>
		<?php if ($calloutActions !== []) { ?>
		<div class="azc-callout__actions">
			<?php foreach ($calloutActions as $action) {
				$href = (string)($action['href'] ?? '');
				$label = (string)($action['label'] ?? '');
				if ($href === '' || $label === '') {
					continue;
				}
				$actionId = (string)($action['id'] ?? '');
				$btnClass = (string)($action['class'] ?? 'azc-btn azc-btn--secondary azc-btn--sm');
				?>
			<a<?php if ($actionId !== '') { ?> id="<?php p($actionId); ?>"<?php } ?>
				class="<?php p($btnClass); ?>"
				href="<?php p($href); ?>"
				<?php
				$actionTarget = (string)($action['target'] ?? '');
				$actionRel = (string)($action['rel'] ?? '');
				if ($actionTarget !== '') {
					?>target="<?php p($actionTarget); ?>" <?php
				}
				if ($actionRel !== '') {
					?>rel="<?php p($actionRel); ?>" <?php
				}
				?>><?php p($label); ?></a>
			<?php } ?>
		</div>
		<?php } ?>
	</div>
</<?php p($calloutElement); ?>>
