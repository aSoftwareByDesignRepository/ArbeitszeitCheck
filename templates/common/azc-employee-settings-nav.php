<?php

declare(strict_types=1);

/**
 * In-page topic switcher for employee My settings multipage.
 *
 * Hierarchy (Bachus / granny test):
 *   Sidebar → one “My settings” item
 *   Page → grouped topic chips (the only section switcher)
 *   H1 → current topic
 *
 * Expects: $l, $settingsPages from EmployeeSettingsSectionCatalog::chipBarPayload()
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
/** @var array{
 *   current?: string,
 *   labels?: array<string, string>,
 *   urls?: array<string, string>,
 *   groups?: list<array{id?: string, label?: string, sections?: list<string>}>,
 *   navAriaLabel?: string,
 *   navTitle?: string
 * } $settingsPages */

$settingsPages = is_array($settingsPages ?? null) ? $settingsPages : [];
$labels = is_array($settingsPages['labels'] ?? null) ? $settingsPages['labels'] : [];
$urls = is_array($settingsPages['urls'] ?? null) ? $settingsPages['urls'] : [];
$groups = is_array($settingsPages['groups'] ?? null) ? $settingsPages['groups'] : [];
$current = (string)($settingsPages['current'] ?? '');
$navAriaLabel = (string)($settingsPages['navAriaLabel'] ?? '');
$navTitle = (string)($settingsPages['navTitle'] ?? '');
if ($labels === []) {
	return;
}
if ($navAriaLabel === '') {
	$navAriaLabel = $l->t('My settings topics');
}
if ($navTitle === '') {
	$navTitle = $l->t('Choose a topic');
}
if ($groups === []) {
	$groups = [[
		'id' => 'all',
		'label' => '',
		'sections' => array_keys($labels),
	]];
}
?>
<nav class="azc-settings-nav azc-settings-nav--grouped" id="azc-employee-settings-pages" aria-label="<?php p($navAriaLabel); ?>">
	<p class="azc-settings-nav__title" id="azc-employee-settings-topics-title"><?php p($navTitle); ?></p>
	<?php foreach ($groups as $group):
		$groupId = (string)($group['id'] ?? '');
		$groupLabel = (string)($group['label'] ?? '');
		$sectionIds = is_array($group['sections'] ?? null) ? $group['sections'] : [];
		if ($sectionIds === []) {
			continue;
		}
		$groupTitleId = $groupId !== '' ? 'azc-employee-settings-group-' . $groupId : '';
		?>
		<div class="azc-settings-nav__group"<?php if ($groupTitleId !== '' && $groupLabel !== ''): ?> role="group" aria-labelledby="<?php p($groupTitleId); ?>"<?php endif; ?>>
			<?php if ($groupLabel !== ''): ?>
				<p class="azc-settings-nav__group-label"<?php if ($groupTitleId !== ''): ?> id="<?php p($groupTitleId); ?>"<?php endif; ?>><?php p($groupLabel); ?></p>
			<?php endif; ?>
			<div class="azc-settings-nav__chips">
				<?php foreach ($sectionIds as $sectionId):
					$sectionId = (string)$sectionId;
					$href = (string)($urls[$sectionId] ?? '');
					$sectionLabel = (string)($labels[$sectionId] ?? '');
					if ($href === '' || $href === '#' || $sectionLabel === '') {
						continue;
					}
					$active = $current !== '' && $current === $sectionId;
					?>
					<a class="azc-settings-nav__link<?php echo $active ? ' is-active' : ''; ?>"
						href="<?php p($href); ?>"
						<?php if ($active): ?>aria-current="page"<?php endif; ?>>
						<?php p($sectionLabel); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</nav>
