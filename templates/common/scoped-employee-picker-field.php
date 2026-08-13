<?php

declare(strict_types=1);

/**
 * Searchable manager-scope employee combobox (filter or required field).
 *
 * Expects: $scopePickerId, $scopePickerName, $scopePickerAllowAll (bool), $scopePickerRequired (bool),
 *           $scopePickerCompact (bool), $l (IL10N)
 */
$scopePickerId = $scopePickerId ?? 'scoped-employee-picker';
$scopePickerName = $scopePickerName ?? 'employee_id';
$scopePickerAllowAll = $scopePickerAllowAll ?? true;
$scopePickerRequired = $scopePickerRequired ?? false;
$scopePickerCompact = $scopePickerCompact ?? false;
$searchId = $scopePickerId . '-search';
$listId = $scopePickerId . '-listbox';
$hiddenId = $scopePickerId . '-id';
$wrapId = $scopePickerId . '-wrap';
$statusId = $scopePickerId . '-status';
$helpId = $scopePickerId . '-help';
$clearId = $scopePickerId . '-clear';
?>
<input type="hidden"
	id="<?php p($hiddenId); ?>"
	name="<?php p($scopePickerName); ?>"
	value=""
	<?php if ($scopePickerRequired) { ?>required aria-required="true"<?php } ?>>
<div class="user-picker<?php if ($scopePickerCompact) { ?> scoped-employee-picker--compact<?php } ?>" id="<?php p($wrapId); ?>">
	<div class="user-picker__control">
		<input type="search"
			id="<?php p($searchId); ?>"
			class="form-input user-picker__search"
			autocomplete="off"
			autocapitalize="none"
			spellcheck="false"
			placeholder="<?php p($scopePickerAllowAll
				? $l->t('All in my scope — type to search…')
				: $l->t('Search by name or login…')); ?>"
			role="combobox"
			aria-autocomplete="list"
			aria-expanded="false"
			aria-controls="<?php p($listId); ?>"
			aria-describedby="<?php p($helpId); ?> <?php p($statusId); ?>"
			<?php if ($scopePickerRequired) { ?>aria-required="true"<?php } ?>>
		<?php if ($scopePickerAllowAll) { ?>
		<button type="button"
			class="user-picker__clear"
			id="<?php p($clearId); ?>"
			hidden
			aria-label="<?php p($l->t('Clear employee filter')); ?>">
			<span aria-hidden="true">&times;</span>
		</button>
		<?php } ?>
	</div>
	<div id="<?php p($listId); ?>"
		class="user-picker__list"
		role="listbox"
		hidden
		aria-label="<?php p($l->t('Matching employees')); ?>"></div>
	<p id="<?php p($statusId); ?>" class="azc-sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
</div>
<p id="<?php p($helpId); ?>" class="form-help<?php if ($scopePickerCompact) { ?> azc-sr-only<?php } ?>">
	<?php if ($scopePickerAllowAll) {
		p($l->t('Type at least 2 characters, then choose an employee. Leave empty to include everyone in your scope.'));
	} else {
		p($l->t('Type at least 2 characters, then choose the employee.'));
	} ?>
</p>
