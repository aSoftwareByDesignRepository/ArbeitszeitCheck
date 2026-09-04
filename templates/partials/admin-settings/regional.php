<?php
declare(strict_types=1);

/**
 * Admin global settings section partial.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 * @var array $settings
 */

/** @var \OCP\IL10N $l */
/** @var array $settings */
$settings = is_array($settings ?? null) ? $settings : (is_array($_['settings'] ?? null) ? $_['settings'] : []);
$availableGroups = is_array($availableGroups ?? null) ? $availableGroups : (is_array($_['availableGroups'] ?? null) ? $_['availableGroups'] : []);
$availableAppAdmins = is_array($availableAppAdmins ?? null) ? $availableAppAdmins : (is_array($_['availableAppAdmins'] ?? null) ? $_['availableAppAdmins'] : []);
$availableAccessUsers = is_array($availableAccessUsers ?? null) ? $availableAccessUsers : (is_array($_['availableAccessUsers'] ?? null) ? $_['availableAccessUsers'] : []);
$azcSettingsShowCardChrome = !empty($azcSettingsShowCardChrome) || !empty($renderAll);
?>
                <section class="azc-card admin-settings-section" aria-labelledby="section-regional-heading">
                                        <header class="azc-card__header<?php echo empty($azcSettingsShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
                        <div class="azc-card__header-text">
                            <?php if (!empty($azcSettingsShowCardChrome)): ?>
                            <h2 id="section-regional-heading" class="azc-card__title"><?php p($l->t('Country and region')); ?></h2>
                            <p class="azc-card__lead"><?php p($l->t('First pick the country whose working time law applies, then the default region for public holidays.')); ?></p>
                            <?php else: ?>
                            <h2 id="section-regional-heading" class="azc-card__title visually-hidden"><?php p($l->t('Country and region')); ?></h2>
                            <?php endif; ?>
                        </div>
                    </header>
                    <div class="azc-card__body">
                <?php
                $azcCurrentCountry = $settings['country'] ?? \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_DE;
                $azcCurrentState = $settings['germanState']
                    ?? \OCA\ArbeitszeitCheck\Support\RegionRegistry::defaultRegionForCountry($azcCurrentCountry);
                $azcCountryCards = [
                    \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_DE => [
                        'title' => $l->t('Germany'),
                        'text' => $l->t('Working time rules follow the German Working Time Act (ArbZG).'),
                    ],
                    \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_AT => [
                        'title' => $l->t('Austria'),
                        'text' => $l->t('Working time rules follow the Austrian Working Time Act (AZG) and Rest Act (ARG).'),
                    ],
                    \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_CH => [
                        'title' => $l->t('Switzerland'),
                        'text' => $l->t('Working time rules follow the Swiss Labour Act (ArG). Public holidays follow the selected canton.'),
                    ],
                ];
                // Region data for all supported countries so the region list can
                // be rebuilt client-side when the country changes (no auto-save).
                $azcRegionData = ['defaultRegionByCountry' => [], 'regionsByCountry' => []];
                foreach (\OCA\ArbeitszeitCheck\Support\RegionRegistry::supportedCountries() as $azcCountryCode) {
                    $azcRegionData['defaultRegionByCountry'][$azcCountryCode] =
                        \OCA\ArbeitszeitCheck\Support\RegionRegistry::defaultRegionForCountry($azcCountryCode);
                    $azcRegions = [];
                    foreach (\OCA\ArbeitszeitCheck\Support\RegionRegistry::regionsForCountry($azcCountryCode) as $azcRegionCode => $azcRegionMsgid) {
                        $azcRegions[] = ['code' => $azcRegionCode, 'label' => $l->t($azcRegionMsgid)];
                    }
                    $azcRegionData['regionsByCountry'][$azcCountryCode] = $azcRegions;
                }
                ?>
                <script type="application/json" id="azc-region-data"><?php print_unescaped(json_encode($azcRegionData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?></script>
                <fieldset class="form-fieldset" aria-labelledby="country-legend" aria-describedby="country-help">
                    <legend id="country-legend" class="form-legend"><?php p($l->t('In which country does your organisation work?')); ?></legend>
                    <div class="azc-country-grid">
                        <?php foreach ($azcCountryCards as $azcCountryCode => $azcCard): ?>
                        <label class="azc-country-card" for="country-<?php p(strtolower($azcCountryCode)); ?>">
                            <input type="radio"
                                   id="country-<?php p(strtolower($azcCountryCode)); ?>"
                                   name="country"
                                   value="<?php p($azcCountryCode); ?>"
                                   class="azc-country-card__radio"
                                   <?php echo ($azcCurrentCountry === $azcCountryCode) ? 'checked' : ''; ?>
                                   aria-describedby="country-help">
                            <span class="azc-country-card__body">
                                <span class="azc-country-card__title"><?php p($azcCard['title']); ?></span>
                                <span class="azc-country-card__text"><?php p($azcCard['text']); ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p id="country-help" class="form-help">
                        <?php p($l->t('Changing the country does not change Hours & rest values — open that page after switching if you want to review limits. Nothing is saved until you select Save.')); ?>
                    </p>
                </fieldset>
                <div id="country-region-live" class="form-help" role="status" aria-live="polite" aria-atomic="true"></div>
                <div class="form-group">
                    <label for="germanState" class="form-label">
                        <?php p($l->t('Default region for public holidays')); ?>
                        <span class="form-required" aria-label="<?php p($l->t('required')); ?>">*</span>
                    </label>
                    <select id="germanState" 
                            name="germanState" 
                            class="form-select" 
                            required
                            aria-describedby="germanState-help">
                        <?php
                        foreach (\OCA\ArbeitszeitCheck\Support\RegionRegistry::regionsForCountry($azcCurrentCountry) as $code => $name) {
                            $selected = ($azcCurrentState === $code) ? ' selected' : '';
                            $label = $l->t($name);
                            echo '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' .
                                htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
                                '</option>';
                        }
                        ?>
                    </select>
                    <p id="germanState-help" class="form-help">
                        <?php p($l->t('Used for statutory holidays and compliance when no specific region is configured for employees.')); ?>
                    </p>
                </div>

                <?php
                $azcWeeklyAbs = (int)($settings['weeklyAbsoluteMaxHours'] ?? 45);
                if ($azcWeeklyAbs !== 50) {
                	$azcWeeklyAbs = 45;
                }
                $azcShowWeeklyAbs = $azcCurrentCountry === \OCA\ArbeitszeitCheck\Support\RegionRegistry::COUNTRY_CH;
                $azcVacationSuggestion = (int)($settings['vacationDaysSuggestion']
                    ?? \OCA\ArbeitszeitCheck\Support\LaborLawProfileFactory::profileForCountry($azcCurrentCountry)->vacationDaysSuggestion);
                ?>
                <div class="form-group" id="weekly-absolute-max-group" <?php echo $azcShowWeeklyAbs ? '' : 'hidden'; ?>
                     data-show-for-country="CH">
                    <label for="weeklyAbsoluteMaxHours" class="form-label">
                        <?php p($l->t('Weekly working time maximum (Switzerland)')); ?>
                    </label>
                    <select id="weeklyAbsoluteMaxHours"
                            name="weeklyAbsoluteMaxHours"
                            class="form-select"
                            aria-describedby="weeklyAbsoluteMaxHours-help"
                            <?php echo $azcShowWeeklyAbs ? '' : 'disabled'; ?>>
                        <option value="45" <?php echo $azcWeeklyAbs === 45 ? 'selected' : ''; ?>><?php p($l->t('45 hours (general ArG Art. 9)')); ?></option>
                        <option value="50" <?php echo $azcWeeklyAbs === 50 ? 'selected' : ''; ?>><?php p($l->t('50 hours (sector exception under ArG Art. 9)')); ?></option>
                    </select>
                    <p id="weeklyAbsoluteMaxHours-help" class="form-help">
                        <?php p($l->t('Swiss labour law allows 45 hours as the general weekly maximum, or 50 hours for certain sectors. Pick the rule that applies to your organisation.')); ?>
                    </p>
                </div>

                <div class="azc-callout azc-callout--info" id="vacation-days-suggestion-callout" role="note"
                     data-suggestion-de="25" data-suggestion-at="25" data-suggestion-ch="20"
                     data-current-suggestion="<?php p((string)$azcVacationSuggestion); ?>">
                    <p class="azc-callout__title"><?php p($l->t('Suggested annual vacation days')); ?></p>
                    <p class="azc-callout__body" id="vacation-days-suggestion-text">
                        <?php
                        p($l->t(
                            'When assigning working time models, the suggested default is %1$d vacation days per year for the selected country (Germany/Austria typically 25; Switzerland typically 20). Existing assignments are never changed automatically.',
                            [$azcVacationSuggestion]
                        ));
                        ?>
                    </p>
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" 
                               id="statutoryAutoReseed" 
                               name="statutoryAutoReseed" 
                               value="1"
                               <?php echo ($settings['statutoryAutoReseed'] ?? true) ? 'checked' : ''; ?>
                               aria-describedby="statutoryAutoReseed-help">
                        <label for="statutoryAutoReseed" class="form-label">
                            <?php p($l->t('Auto-restore statutory holidays when viewing calendar')); ?>
                        </label>
                    </div>
                    <p id="statutoryAutoReseed-help" class="form-help">
                        <?php p($l->t('When enabled, missing statutory holidays are added when the calendar is viewed. Disable if you want deleted holidays to stay removed.')); ?>
                    </p>
                </div>
                </div><!-- /.azc-card__body -->
                </section>

