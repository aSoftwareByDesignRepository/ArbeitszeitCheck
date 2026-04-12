<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
$error = $_['error'] ?? null;
$availableMonthsUrl = $_['revisionPdfAvailableMonthsUrl'] ?? '';
$usersForMonthUrl = $_['revisionPdfUsersForMonthUrl'] ?? '';
$pdfUrlBase = $_['pdfUrlBase'] ?? '';

$l10nKeys = [
	'Loading…',
	'Choose month…',
	'Could not load months.',
	'No finalized months are available for your access yet.',
	'Select a month to see who you can download for.',
	'Could not load people for this month.',
	'No one has a finalized revision for this month in your scope.',
	'Download PDF',
	'Download revision PDF for {name}',
	'Could not initialize the month list. Please reload the page.',
	// Calendar month names (same msgids as calendar / datepicker)
	'January',
	'February',
	'March',
	'April',
	'May',
	'June',
	'July',
	'August',
	'September',
	'October',
	'November',
	'December',
];
$l10nMap = [];
foreach ($l10nKeys as $key) {
	$l10nMap[$key] = $l->t($key);
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content"
	class="manager-month-closures-page"
	<?php if (!$error): ?>
	data-revision-pdf-available-months-url="<?php p($availableMonthsUrl); ?>"
	data-revision-pdf-users-for-month-url="<?php p($usersForMonthUrl); ?>"
	data-pdf-url-base="<?php p($pdfUrlBase); ?>"
	<?php endif; ?>>
	<?php if (!$error): ?>
	<script type="application/json" id="manager-mc-l10n-json" nonce="<?php p($_['cspNonce'] ?? ''); ?>">
	<?php echo json_encode($l10nMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>

	</script>
	<?php endif; ?>
	<div id="app-content-wrapper">
		<div class="section manager-month-closures-page__content">
			<header class="manager-mc-header">
				<h1 class="manager-mc-header__title"><?php p($l->t('Revision PDFs (month closure)')); ?></h1>
				<p class="manager-mc-header__intro" id="manager-month-closures-intro">
					<?php p($l->t('Pick a month that already has sealed data, then download the same revision-secure PDF for each person you are allowed to access.')); ?>
				</p>
			</header>

			<?php if ($error): ?>
				<p class="form-help form-help--error" role="alert"><?php p($error); ?></p>
			<?php else: ?>

			<div class="manager-mc-flow" role="region" aria-labelledby="manager-mc-flow-title">
				<h2 id="manager-mc-flow-title" class="visually-hidden"><?php p($l->t('Download revision PDF')); ?></h2>

				<fieldset class="manager-mc-panel">
					<legend class="manager-mc-panel__legend">
						<span class="manager-mc-step" aria-hidden="true">1</span>
						<span class="manager-mc-panel__legend-text"><?php p($l->t('Choose month')); ?></span>
					</legend>
					<p class="manager-mc-panel__hint" id="manager-mc-month-hint">
						<?php p($l->t('Only months with finalized (sealed) data you can act on are listed.')); ?>
					</p>
					<div class="manager-mc-month-row">
						<label class="manager-mc-label" for="manager-mc-month-select"><?php p($l->t('Calendar month')); ?></label>
						<select id="manager-mc-month-select"
							class="form-input form-select manager-mc-select"
							aria-busy="true"
							aria-describedby="manager-mc-month-hint manager-mc-month-load-status manager-month-closures-intro">
							<option value=""><?php p($l->t('Loading…')); ?></option>
						</select>
					</div>
					<p id="manager-mc-month-load-status" class="manager-mc-month-status" role="status" aria-live="polite"></p>
				</fieldset>

				<fieldset class="manager-mc-panel">
					<legend class="manager-mc-panel__legend">
						<span class="manager-mc-step" aria-hidden="true">2</span>
						<span class="manager-mc-panel__legend-text"><?php p($l->t('Download for each person')); ?></span>
					</legend>
					<p class="manager-mc-panel__hint" id="manager-mc-people-hint">
						<?php p($l->t('Everyone listed has a finalized month for your selection and matches your permissions.')); ?>
					</p>
					<div id="manager-mc-people-region"
						class="manager-mc-people-region"
						role="region"
						aria-labelledby="manager-mc-people-title"
						aria-describedby="manager-mc-people-hint">
						<h3 id="manager-mc-people-title" class="visually-hidden"><?php p($l->t('People')); ?></h3>
						<div id="manager-mc-people-empty" class="manager-mc-empty" hidden></div>
						<ul id="manager-mc-people-list" class="manager-mc-people-list" hidden></ul>
					</div>
					<p id="manager-mc-people-status" class="manager-mc-month-status" role="status" aria-live="polite"></p>
				</fieldset>

				<p id="manager-mc-page-error" class="form-help form-help--error manager-mc-page-error" role="alert" aria-live="assertive" hidden></p>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>
