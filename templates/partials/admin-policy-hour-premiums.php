<?php
declare(strict_types=1);
/**
 * Partial: admin-policy-hour-premiums.php
 * Expects: $l, $settings, $urlGenerator (where needed), $absenceTypes/$eventTypes for HR, $premiumNightPreset for premiums.
 * @license AGPL-3.0-or-later
 */
/** @var \OCP\IL10N $l */
/** @var array $settings */
?>
				<?php
				$premiumEnabled = (bool)($settings['premiumSurchargesEnabled'] ?? false);
				$premiumPolicy = is_array($settings['premiumPolicy'] ?? null) ? $settings['premiumPolicy'] : [];
				$premiumCats = is_array($premiumPolicy['categories'] ?? null) ? $premiumPolicy['categories'] : [];
				$datevPremiumMap = is_array($settings['datevLohnartPremiumMap'] ?? null) ? $settings['datevLohnartPremiumMap'] : [];
				$catById = [];
				foreach ($premiumCats as $c) {
					if (is_array($c) && isset($c['id'])) {
						$catById[(string)$c['id']] = $c;
					}
				}
				$ratePct = static function (string $id) use ($catById): string {
					$r = (float)($catById[$id]['rate'] ?? 0);
					return (string)(int)round($r * 100);
				};
				$catOn = static function (string $id) use ($catById): bool {
					if (!isset($catById[$id])) {
						return false;
					}
					return !array_key_exists('enabled', $catById[$id]) || !empty($catById[$id]['enabled']);
				};
				$datevCode = static function (string $id) use ($datevPremiumMap): string {
					return isset($datevPremiumMap[$id]) ? (string)$datevPremiumMap[$id] : '';
				};
				$nightStart = (string)($catById['night']['window_start'] ?? ($premiumNightPreset === 'de' ? '23:00' : '22:00'));
				$nightEnd = (string)($catById['night']['window_end'] ?? ($premiumNightPreset === 'de' ? '06:00' : '05:00'));
				$holidayPolicy = (string)($premiumPolicy['holiday_policy'] ?? 'treat_as_sunday');
				if ($holidayPolicy !== 'treat_as_sunday' && $holidayPolicy !== 'ignore') {
					$holidayPolicy = 'treat_as_sunday';
				}
				$stackingMode = (string)($premiumPolicy['stacking'] ?? 'max_single_rate');
				if (!in_array($stackingMode, ['max_single_rate', 'additive_rates', 'tagged_multi'], true)) {
					$stackingMode = 'max_single_rate';
				}
				?>
				<section class="azc-card azc-admin-policy-section admin-settings-section" aria-labelledby="premium-surcharges-heading" id="premium-surcharges-section">
					<header class="azc-card__header">
						<div class="azc-card__header-text">
							<h2 id="premium-surcharges-heading" class="azc-card__title"><?php p($l->t('Hour premiums')); ?></h2>
							<p class="azc-card__lead"><?php p($l->t('Sunday/night/Saturday/overtime percentages for reports — not salary, not Saldo. Off by default.')); ?></p>
						</div>
					</header>
					<div class="azc-card__body">
						<div class="form-group">
							<div class="form-checkbox">
								<input type="checkbox"
									id="premiumSurchargesEnabled"
									name="premiumSurchargesEnabled"
									value="1"
									<?php echo $premiumEnabled ? 'checked' : ''; ?>
									aria-describedby="premiumSurchargesEnabled-help"
									aria-controls="premium-surcharges-panel">
								<label for="premiumSurchargesEnabled" class="form-label">
									<?php p($l->t('Turn on hour premiums')); ?>
								</label>
							</div>
							<p id="premiumSurchargesEnabled-help" class="form-help">
								<?php p($l->t('When on, completed work is classified into premium hour buckets (Sunday, night, Saturday, overtime above daily target). This does not change Saldo or Auszahlung.')); ?>
							</p>
						</div>
						<div id="premium-surcharges-panel" class="admin-notifications-dependent-block" <?php echo $premiumEnabled ? '' : 'hidden'; ?>
							data-premium-example-off="<?php p($l->t('Example: turn on Sunday to see a sample.')); ?>"
							data-premium-example-on="<?php p($l->t('Example: Sunday 2 h → 2.0 h @ __PCT__%%')); ?>">
							<p class="form-help form-help--block" id="premium-presets-help"><?php p($l->t('Pick Simple to start. Templates and custom edits are optional.')); ?></p>
							<div class="premium-presets" role="radiogroup" aria-labelledby="premium-presets-help" id="premium-mode-chips">
								<button type="button"
									class="azc-settings-nav__link premium-mode-chip"
									role="radio"
									aria-checked="false"
									data-premium-mode="simple"
									id="premium-mode-simple">
									<?php p($l->t('Simple')); ?>
								</button>
								<button type="button"
									class="azc-settings-nav__link premium-mode-chip"
									role="radio"
									aria-checked="false"
									data-premium-mode="template"
									id="premium-mode-template"
									aria-controls="premium-template-picker">
									<?php p($l->t('From template')); ?>
								</button>
								<button type="button"
									class="azc-settings-nav__link premium-mode-chip"
									role="radio"
									aria-checked="false"
									data-premium-mode="custom"
									id="premium-mode-custom">
									<?php p($l->t('Custom')); ?>
								</button>
							</div>
							<div id="premium-template-picker" class="premium-template-picker" hidden role="group" aria-label="<?php p($l->t('Country starter templates')); ?>">
								<button type="button" class="azc-settings-nav__link" data-premium-preset="at"><?php p($l->t('AT Tarif/KV example')); ?></button>
								<button type="button" class="azc-settings-nav__link" data-premium-preset="de"><?php p($l->t('DE Tarif example (non-binding)')); ?></button>
							</div>
							<div class="table-container premium-categories-wrap" role="region" aria-label="<?php p($l->t('Premium categories')); ?>">
								<table class="table premium-categories-table" id="premium-categories-table">
									<thead>
										<tr>
											<th scope="col" class="premium-categories-table__on"><?php p($l->t('On')); ?></th>
											<th scope="col" class="premium-categories-table__cat"><?php p($l->t('Category')); ?></th>
											<th scope="col" class="premium-categories-table__pct"><?php p($l->t('Percent')); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr data-premium-id="overtime_base">
											<td class="premium-categories-table__on">
												<div class="form-checkbox premium-cat-toggle">
													<input type="checkbox" class="premium-cat-on" id="premium-cat-ot-on" <?php echo $catOn('overtime_base') ? 'checked' : ''; ?>
														aria-labelledby="premium-cat-ot-name">
												</div>
											</td>
											<td class="premium-categories-table__cat">
												<label id="premium-cat-ot-name" class="premium-cat-name" for="premium-cat-ot-on"><?php p($l->t('Overtime above daily target')); ?></label>
											</td>
											<td class="premium-categories-table__pct">
												<div class="premium-cat-rate-wrap">
													<label for="premium-cat-ot-rate" class="visually-hidden"><?php p($l->t('Overtime percent')); ?></label>
													<input type="number" class="form-input premium-cat-rate azc-input--touch" id="premium-cat-ot-rate" min="0" max="300" step="1" inputmode="numeric"
														value="<?php p($ratePct('overtime_base') !== '0' || $catOn('overtime_base') ? $ratePct('overtime_base') : '50'); ?>"
														aria-describedby="premium-ot-hint">
													<span class="premium-cat-rate-suffix" aria-hidden="true">%</span>
												</div>
											</td>
										</tr>
										<tr data-premium-id="sunday">
											<td class="premium-categories-table__on">
												<div class="form-checkbox premium-cat-toggle">
													<input type="checkbox" class="premium-cat-on" id="premium-cat-sun-on" <?php echo $catOn('sunday') ? 'checked' : ''; ?>
														aria-labelledby="premium-cat-sun-name">
												</div>
											</td>
											<td class="premium-categories-table__cat">
												<label id="premium-cat-sun-name" class="premium-cat-name" for="premium-cat-sun-on"><?php p($l->t('Sunday')); ?></label>
											</td>
											<td class="premium-categories-table__pct">
												<div class="premium-cat-rate-wrap">
													<label for="premium-cat-sun-rate" class="visually-hidden"><?php p($l->t('Sunday percent')); ?></label>
													<input type="number" class="form-input premium-cat-rate azc-input--touch" id="premium-cat-sun-rate" min="0" max="300" step="1" inputmode="numeric"
														value="<?php p($ratePct('sunday') !== '0' || $catOn('sunday') ? $ratePct('sunday') : '100'); ?>">
													<span class="premium-cat-rate-suffix" aria-hidden="true">%</span>
												</div>
											</td>
										</tr>
										<tr data-premium-id="saturday">
											<td class="premium-categories-table__on">
												<div class="form-checkbox premium-cat-toggle">
													<input type="checkbox" class="premium-cat-on" id="premium-cat-sat-on" <?php echo $catOn('saturday') ? 'checked' : ''; ?>
														aria-labelledby="premium-cat-sat-name">
												</div>
											</td>
											<td class="premium-categories-table__cat">
												<label id="premium-cat-sat-name" class="premium-cat-name" for="premium-cat-sat-on"><?php p($l->t('Saturday')); ?></label>
											</td>
											<td class="premium-categories-table__pct">
												<div class="premium-cat-rate-wrap">
													<label for="premium-cat-sat-rate" class="visually-hidden"><?php p($l->t('Saturday percent')); ?></label>
													<input type="number" class="form-input premium-cat-rate azc-input--touch" id="premium-cat-sat-rate" min="0" max="300" step="1" inputmode="numeric"
														value="<?php p($ratePct('saturday') !== '0' || $catOn('saturday') ? $ratePct('saturday') : '50'); ?>">
													<span class="premium-cat-rate-suffix" aria-hidden="true">%</span>
												</div>
											</td>
										</tr>
										<tr data-premium-id="night">
											<td class="premium-categories-table__on">
												<div class="form-checkbox premium-cat-toggle">
													<input type="checkbox" class="premium-cat-on" id="premium-cat-night-on" <?php echo $catOn('night') ? 'checked' : ''; ?>
														aria-labelledby="premium-cat-night-name">
												</div>
											</td>
											<td class="premium-categories-table__cat">
												<label id="premium-cat-night-name" class="premium-cat-name" for="premium-cat-night-on"><?php p($l->t('Night')); ?></label>
											</td>
											<td class="premium-categories-table__pct">
												<div class="premium-cat-rate-wrap">
													<label for="premium-cat-night-rate" class="visually-hidden"><?php p($l->t('Night percent')); ?></label>
													<input type="number" class="form-input premium-cat-rate azc-input--touch" id="premium-cat-night-rate" min="0" max="300" step="1" inputmode="numeric"
														value="<?php p($ratePct('night') !== '0' || $catOn('night') ? $ratePct('night') : '50'); ?>">
													<span class="premium-cat-rate-suffix" aria-hidden="true">%</span>
												</div>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
							<p id="premium-example" class="azc-callout azc-callout--info premium-live-example" role="status" aria-live="polite">
								<?php p($l->t('Example: Sunday 2 h → 2.0 h @ 100%%')); ?>
							</p>
							<details class="azc-settings-more premium-more-options" id="premium-more-options">
								<summary><?php p($l->t('More options')); ?></summary>
								<p id="premium-ot-hint" class="form-help"><?php p($l->t('Default overlap: highest single percentage. Night window is editable. Templates are starters — not a legal guarantee.')); ?></p>
								<p class="form-help form-help--note"><?php p($l->t('Overtime premium = hours above the daily contract target — separate from Saldo.')); ?></p>
								<div class="form-row form-row--inline premium-night-window" role="group" aria-labelledby="premium-night-window-heading">
									<h3 id="premium-night-window-heading" class="admin-settings-subsection__title"><?php p($l->t('Night window')); ?></h3>
									<div class="form-group">
										<label for="premium-night-start" class="form-label"><?php p($l->t('Starts')); ?></label>
										<input type="time" class="form-input azc-input--touch" id="premium-night-start" name="premiumNightStart" value="<?php p($nightStart); ?>" aria-describedby="premium-ot-hint">
									</div>
									<div class="form-group">
										<label for="premium-night-end" class="form-label"><?php p($l->t('Ends')); ?></label>
										<input type="time" class="form-input azc-input--touch" id="premium-night-end" name="premiumNightEnd" value="<?php p($nightEnd); ?>">
									</div>
								</div>
								<div class="form-row form-row--inline" role="group" aria-labelledby="premium-rules-heading">
									<h3 id="premium-rules-heading" class="admin-settings-subsection__title"><?php p($l->t('Overlap rules')); ?></h3>
									<div class="form-group">
										<label for="premium-stacking" class="form-label"><?php p($l->t('When rules overlap')); ?></label>
										<select id="premium-stacking" name="premiumStacking" class="form-input azc-input--touch" aria-describedby="premium-ot-hint">
											<option value="max_single_rate" <?php echo $stackingMode === 'max_single_rate' ? 'selected' : ''; ?>><?php p($l->t('Highest single percentage (recommended)')); ?></option>
											<option value="additive_rates" <?php echo $stackingMode === 'additive_rates' ? 'selected' : ''; ?>><?php p($l->t('Add percentages (advanced)')); ?></option>
											<option value="tagged_multi" <?php echo $stackingMode === 'tagged_multi' ? 'selected' : ''; ?>><?php p($l->t('Tag multiple categories (advanced)')); ?></option>
										</select>
									</div>
									<div class="form-group">
										<label for="premium-holiday-policy" class="form-label"><?php p($l->t('Public holidays')); ?></label>
										<select id="premium-holiday-policy" name="premiumHolidayPolicy" class="form-input azc-input--touch">
											<option value="treat_as_sunday" <?php echo $holidayPolicy === 'treat_as_sunday' ? 'selected' : ''; ?>><?php p($l->t('Treat like Sunday')); ?></option>
											<option value="ignore" <?php echo $holidayPolicy === 'ignore' ? 'selected' : ''; ?>><?php p($l->t('Ignore holiday (weekday rules only)')); ?></option>
										</select>
									</div>
								</div>
								<details class="premium-datev-details" id="premium-datev-details">
									<summary><?php p($l->t('DATEV Lohnart codes')); ?></summary>
									<p id="premium-datev-hint" class="form-help"><?php p($l->t('Optional. Leave empty to skip that premium in DATEV export.')); ?></p>
									<div class="form-row form-row--inline" role="group" aria-labelledby="premium-datev-hint">
										<div class="form-group">
											<label for="premium-cat-ot-datev" class="form-label"><?php p($l->t('Overtime')); ?></label>
											<input type="text" class="form-input premium-cat-datev" id="premium-cat-ot-datev" inputmode="numeric" pattern="[1-9][0-9]{0,3}" maxlength="4" value="<?php p($datevCode('overtime_base')); ?>" aria-label="<?php p($l->t('DATEV Lohnart for overtime premium')); ?>" placeholder="—">
										</div>
										<div class="form-group">
											<label for="premium-cat-sun-datev" class="form-label"><?php p($l->t('Sunday')); ?></label>
											<input type="text" class="form-input premium-cat-datev" id="premium-cat-sun-datev" inputmode="numeric" pattern="[1-9][0-9]{0,3}" maxlength="4" value="<?php p($datevCode('sunday')); ?>" aria-label="<?php p($l->t('DATEV Lohnart for Sunday premium')); ?>" placeholder="—">
										</div>
										<div class="form-group">
											<label for="premium-cat-sat-datev" class="form-label"><?php p($l->t('Saturday')); ?></label>
											<input type="text" class="form-input premium-cat-datev" id="premium-cat-sat-datev" inputmode="numeric" pattern="[1-9][0-9]{0,3}" maxlength="4" value="<?php p($datevCode('saturday')); ?>" aria-label="<?php p($l->t('DATEV Lohnart for Saturday premium')); ?>" placeholder="—">
										</div>
										<div class="form-group">
											<label for="premium-cat-night-datev" class="form-label"><?php p($l->t('Night')); ?></label>
											<input type="text" class="form-input premium-cat-datev" id="premium-cat-night-datev" inputmode="numeric" pattern="[1-9][0-9]{0,3}" maxlength="4" value="<?php p($datevCode('night')); ?>" aria-label="<?php p($l->t('DATEV Lohnart for night premium')); ?>" placeholder="—">
										</div>
									</div>
								</details>
							</details>
						</div>
					</div>
				</section>
