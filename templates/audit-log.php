<?php

declare(strict_types=1);

/**
 * Audit log template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCA\ArbeitszeitCheck\Constants;

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');

$logs = $_['logs'] ?? [];
$total = (int)($_['total'] ?? 0);
$limit = (int)($_['limit'] ?? 50);
$offset = (int)($_['offset'] ?? 0);
$startDate = $_['startDate'] ?? '';
$endDate = $_['endDate'] ?? '';
$actionCategoryOptions = is_array($_['actionCategoryOptions'] ?? null) ? $_['actionCategoryOptions'] : [];
$entityTypeOptions = is_array($_['entityTypeOptions'] ?? null) ? $_['entityTypeOptions'] : [];
$maxDateRangeDays = (int)($_['maxDateRangeDays'] ?? Constants::MAX_EXPORT_DATE_RANGE_DAYS);
$shownCount = count($logs);
$rangeStart = min($total, $offset + 1);
$rangeEnd = min($total, $offset + $shownCount);
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack">
	<div class="audit-log-page">

		<section class="azc-card audit-log-page__filters" aria-labelledby="audit-filters-heading">
			<header class="azc-card__header">
				<div class="azc-card__header-text">
					<h2 id="audit-filters-heading" class="azc-card__title"><?php p($l->t('Search activity')); ?></h2>
					<p id="audit-log-filter-help" class="azc-card__lead">
						<?php p($l->t('Choose a date range and optional filters, then click Show. Export downloads everything that matches your filters.')); ?>
					</p>
				</div>
			</header>
			<div class="azc-card__body">
				<div class="audit-log-page__toolbar" role="search" aria-label="<?php p($l->t('Filter activity log')); ?>">
					<form
						id="audit-log-filter-form"
						class="audit-log-page__filter-form"
						novalidate
						aria-describedby="audit-log-filter-error"
					>
						<div class="audit-log-page__filter-grid audit-log-scope-filter" role="group" aria-label="<?php p($l->t('Filter options')); ?>">
							<div class="azc-filter-field azc-filter-field--user">
								<label for="user-filter" class="azc-filter-field__label"><?php p($l->t('User account')); ?></label>
								<div class="azc-filter-field__control">
									<input
										type="search"
										id="user-filter"
										name="user_id"
										class="form-input"
										placeholder="<?php p($l->t('Nextcloud user ID…')); ?>"
										autocomplete="off"
										autocapitalize="none"
										spellcheck="false"
										inputmode="text"
										maxlength="200"
										aria-describedby="audit-log-filter-footnote"
										aria-label="<?php p($l->t('User account')); ?>"
									/>
								</div>
							</div>

							<div class="azc-filter-field azc-filter-field--action">
								<label for="action-category-filter" class="azc-filter-field__label"><?php p($l->t('Action')); ?></label>
								<div class="azc-filter-field__control">
									<select id="action-category-filter" name="action_category" class="form-select" aria-label="<?php p($l->t('Action')); ?>">
										<?php foreach ($actionCategoryOptions as $value => $label): ?>
											<option value="<?php p((string)$value); ?>"><?php p($label); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>

							<div class="azc-filter-field azc-filter-field--entity">
								<label for="entity-type-filter" class="azc-filter-field__label"><?php p($l->t('What changed')); ?></label>
								<div class="azc-filter-field__control">
									<select id="entity-type-filter" name="entity_type" class="form-select" aria-label="<?php p($l->t('What changed')); ?>">
										<?php foreach ($entityTypeOptions as $value => $label): ?>
											<option value="<?php p((string)$value); ?>"><?php p($label); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>

							<div class="azc-filter-field azc-filter-field--actions">
								<span class="azc-filter-field__label visually-hidden"><?php p($l->t('Actions')); ?></span>
								<div class="azc-filter-field__control azc-filter-actions">
									<button type="submit" id="apply-filters" class="azc-btn azc-btn--primary">
										<?php p($l->t('Show')); ?>
									</button>
									<button type="button" id="reset-filters" class="azc-btn azc-btn--secondary">
										<?php p($l->t('Reset')); ?>
									</button>
									<button type="button" id="export-logs" class="azc-btn azc-btn--secondary">
										<?php p($l->t('Export CSV')); ?>
									</button>
								</div>
							</div>

							<div
								class="azc-filter-field azc-filter-field--dates"
								role="group"
								aria-labelledby="audit-log-date-range-label"
							>
								<span id="audit-log-date-range-label" class="azc-filter-field__label"><?php p($l->t('Date range')); ?></span>
								<div class="azc-filter-field__control">
									<div class="azc-date-range">
										<div class="azc-date-range__part">
											<label for="start-date" class="azc-date-range__sublabel visually-hidden"><?php p($l->t('From')); ?></label>
											<input
												type="text"
												id="start-date"
												name="start_date"
												class="form-input datepicker-input"
												placeholder="<?php p($l->t('dd.mm.yyyy')); ?>"
												pattern="\d{2}\.\d{2}\.\d{4}"
												maxlength="10"
												readonly
												required
												autocomplete="off"
												aria-required="true"
												value="<?php p($startDate); ?>"
												aria-label="<?php p($l->t('From')); ?>"
												aria-describedby="audit-log-filter-footnote"
											/>
										</div>
										<span class="azc-date-range__sep" aria-hidden="true"><?php p($l->t('to')); ?></span>
										<div class="azc-date-range__part">
											<label for="end-date" class="azc-date-range__sublabel visually-hidden"><?php p($l->t('To')); ?></label>
											<input
												type="text"
												id="end-date"
												name="end_date"
												class="form-input datepicker-input"
												placeholder="<?php p($l->t('dd.mm.yyyy')); ?>"
												pattern="\d{2}\.\d{2}\.\d{4}"
												maxlength="10"
												readonly
												required
												autocomplete="off"
												aria-required="true"
												value="<?php p($endDate); ?>"
												aria-label="<?php p($l->t('To')); ?>"
												aria-describedby="audit-log-filter-footnote"
											/>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div id="audit-log-filter-error" class="azc-callout azc-callout--danger audit-log-page__toolbar-feedback" role="alert" hidden>
							<span class="azc-callout__text"></span>
						</div>
					</form>
					<p id="audit-log-filter-footnote" class="audit-log-page__toolbar-footnote" role="note">
						<?php p($l->t('Optional: filter by Nextcloud user ID. Maximum date range: %d days. Times use your personal timezone.', [$maxDateRangeDays])); ?>
					</p>
				</div>
			</div>
		</section>

		<section class="azc-card audit-log-page__results" aria-labelledby="audit-table-heading" aria-busy="false">
			<header class="azc-card__header">
				<div class="azc-card__header-text">
					<h2 id="audit-table-heading" class="azc-card__title"><?php p($l->t('Activity log')); ?></h2>
					<p class="azc-card__lead"><?php p($l->t('Each row is one recorded change. Newest entries appear first.')); ?></p>
				</div>
				<p id="audit-log-count" class="azc-badge azc-badge--neutral" role="status" aria-live="polite">
					<?php if ($total > 0): ?>
						<?php p($l->t('%1$d–%2$d of %3$d entries', [$rangeStart, $rangeEnd, $total])); ?>
					<?php else: ?>
						<?php p($l->t('0 entries')); ?>
					<?php endif; ?>
				</p>
			</header>
			<div class="azc-card__body">
				<div class="table-container audit-log-page__table-wrap" role="region" aria-label="<?php p($l->t('Activity log')); ?>">
					<table class="table table--hover azc-table--responsive audit-log-table" id="audit-log-table" aria-label="<?php p($l->t('Activity log')); ?>">
						<thead>
							<tr>
								<th scope="col"><?php p($l->t('Date and time')); ?></th>
								<th scope="col"><?php p($l->t('Employee')); ?></th>
								<th scope="col"><?php p($l->t('Action')); ?></th>
								<th scope="col"><?php p($l->t('What was changed')); ?></th>
								<th scope="col"><?php p($l->t('Who did it')); ?></th>
							</tr>
						</thead>
						<tbody id="audit-log-tbody">
							<?php if (empty($logs)): ?>
								<tr>
									<td colspan="5" class="text-center audit-log-empty">
										<div class="empty-state">
											<h3 class="empty-state__title"><?php p($l->t('No activities found')); ?></h3>
											<p class="empty-state__description">
												<?php p($l->t('No activities were logged for the selected period.')); ?>
											</p>
										</div>
									</td>
								</tr>
							<?php else: ?>
								<?php foreach ($logs as $log): ?>
								<tr>
									<td data-label="<?php p($l->t('Date and time')); ?>"><?php p($log['createdAt'] ?? '-'); ?></td>
									<td data-label="<?php p($l->t('Employee')); ?>"><?php p($log['userDisplayName'] ?? $log['userId']); ?></td>
									<td data-label="<?php p($l->t('Action')); ?>"><?php p($log['action']); ?></td>
									<td data-label="<?php p($l->t('What was changed')); ?>"><?php p($log['entityType']); ?></td>
									<td data-label="<?php p($l->t('Who did it')); ?>"><?php p($log['performedByDisplayName'] ?? $log['performedBy'] ?? '-'); ?></td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<nav id="audit-log-pagination" class="audit-log-page__pagination" aria-label="<?php p($l->t('Activity log pages')); ?>" <?php if ($total <= $limit): ?>hidden<?php endif; ?>>
					<button type="button" id="audit-log-prev" class="azc-btn azc-btn--secondary" <?php if ($offset <= 0): ?>disabled aria-disabled="true"<?php endif; ?>>
						<?php p($l->t('Previous')); ?>
					</button>
					<p id="audit-log-pagination-text" class="audit-log-page__pagination-text" aria-live="polite">
						<?php p($l->t('Page %1$d of %2$d', [max(1, (int)floor($offset / max(1, $limit)) + 1), max(1, (int)ceil($total / max(1, $limit)))])); ?>
					</p>
					<button type="button" id="audit-log-next" class="azc-btn azc-btn--secondary" <?php if ($offset + $shownCount >= $total): ?>disabled aria-disabled="true"<?php endif; ?>>
						<?php p($l->t('Next')); ?>
					</button>
				</nav>
			</div>
		</section>

	</div>
</div>

<?php
// Placeholder strings (%d etc.) must go through TemplateL10n: a bare $l->t() without
// arguments crashes vsprintf on PHP 8, and the JS substitutes the placeholders itself.
$auditLogViewerL10n = \OCA\ArbeitszeitCheck\Util\TemplateL10n::mapFromMessageIds($l, [
	'Loading…',
	'Error loading audit logs',
	'Failed to load audit logs. Please try again.',
	'No audit log entries found',
	'Date and time',
	'Employee',
	'Action',
	'What was changed',
	'Who did it',
	'0 entries',
	'%1$d–%2$d of %3$d entries',
	'Page %1$d of %2$d',
	'Previous',
	'Next',
	'Start date must be before or equal to end date',
	'Please enter valid dates in dd.mm.yyyy format.',
	'Date range must not exceed %d days. Please narrow the range.',
	'User filter is too long.',
]);
?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.auditLogViewerL10n = <?php echo json_encode($auditLogViewerL10n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
window.ArbeitszeitCheck.auditLogViewerConfig = <?php echo json_encode([
	'limit' => $limit,
	'offset' => $offset,
	'total' => $total,
	'maxDateRangeDays' => $maxDateRangeDays,
	'defaultStartDate' => $startDate,
	'defaultEndDate' => $endDate,
	'exportUrl' => \OCP\Server::get(\OCP\IURLGenerator::class)->linkToRoute('arbeitszeitcheck.admin.exportAuditLogs'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
</script>

<?php include __DIR__ . '/common/page-end.php'; ?>
