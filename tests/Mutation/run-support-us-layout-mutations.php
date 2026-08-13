<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for Support & Us layout + absences past-record inline badges.
 *
 * Proves that contract/render/table tests catch regressions that drop the
 * dedicated Support Us admin page wiring or restack type/past-record badges.
 *
 * Usage (Docker):
 *   docker compose exec -T nextcloud bash -lc \
 *     'cd /var/www/html/custom_apps/arbeitszeitcheck && php -d opcache.enable_cli=0 tests/Mutation/run-support-us-layout-mutations.php'
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

$appRoot = dirname(__DIR__, 2);
$phpunit = $appRoot . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
	$phpunit = 'phpunit';
}

/**
 * @param list<string> $filters
 */
function run_filters(string $appRoot, string $phpunit, array $filters): int {
	$filter = implode('|', $filters);
	$cmd = escapeshellarg('php')
		. ' -d opcache.enable_cli=0 -d opcache.enable=0 '
		. escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
		. ' --filter ' . escapeshellarg($filter);
	passthru($cmd, $code);
	return (int)$code;
}

function restore_file(string $path, string $backup): void {
	if (is_file($backup)) {
		rename($backup, $path);
	}
}

/**
 * @param array{file:string,from:string,to:string,filters:list<string>} $mutation
 */
function apply_and_expect_fail(string $appRoot, string $phpunit, string $name, array $mutation): bool {
	$path = $appRoot . '/' . $mutation['file'];
	$backup = $path . '.mutation-bak';
	$original = file_get_contents($path);
	if ($original === false) {
		fwrite(STDERR, "Cannot read {$path}\n");
		return false;
	}
	if (!str_contains($original, $mutation['from'])) {
		fwrite(STDERR, "Mutation anchor not found for {$name}\n");
		return false;
	}
	file_put_contents($backup, $original);
	$mutated = str_replace($mutation['from'], $mutation['to'], $original);
	if ($mutated === $original) {
		fwrite(STDERR, "Mutation replace had no effect for {$name}\n");
		restore_file($path, $backup);
		return false;
	}
	file_put_contents($path, $mutated);
	echo "\n== mutation: {$name} ==\n";
	$code = run_filters($appRoot, $phpunit, $mutation['filters']);
	restore_file($path, $backup);
	if ($code === 0) {
		echo "MUTATION SURVIVED: {$name}\n";
		return false;
	}
	echo "killed {$name}\n";
	return true;
}

$mutations = [
	'drop_dedicated_support_us_page_include' => [
		'file' => 'templates/admin-support-us.php',
		'from' => "include __DIR__ . '/parts/support-us-section.php';",
		'to' => "/* mutated: support-us-section include removed */",
		'filters' => [
			'SupportUsSectionContractTest::testSupportUsLivesOnDedicatedAdminPageNotSettingsEmbed',
		],
	],
	'drop_vendor_logo_from_support_us_page' => [
		'file' => 'templates/admin-support-us.php',
		'from' => "vendor-logo-mark.png",
		'to' => "vendor-logo-missing.png",
		'filters' => [
			'SupportUsSectionContractTest::testSupportUsLivesOnDedicatedAdminPageNotSettingsEmbed',
		],
	],
	'reindent_support_us_trust_chips' => [
		'file' => 'css/admin-support-us.css',
		'from' => "#app-content.azc-app--admin-support-us ul.azc-support-us-page__trust",
		'to' => ".azc-support-us-page__trust",
		'filters' => [
			'SupportUsSectionContractTest::testCssContractHasFocusAndReducedMotion',
		],
	],
	'reconstrain_support_us_page_root' => [
		'file' => 'css/admin-support-us.css',
		'from' => "#app-content.azc-app--admin-support-us .azc-support-us-page,\n.azc-support-us-page {\n\tdisplay: flex;\n\tflex-direction: column;\n\tgap: var(--azc-space-6, 2rem);\n\twidth: 100%;\n\tmax-width: none;",
		'to' => "#app-content.azc-app--admin-support-us .azc-support-us-page,\n.azc-support-us-page {\n\tdisplay: flex;\n\tflex-direction: column;\n\tgap: var(--azc-space-6, 2rem);\n\twidth: 100%;\n\tmax-width: 56rem;",
		'filters' => [
			'SupportUsSectionContractTest::testCssContractHasFocusAndReducedMotion',
			'PageShellLayoutTest::testAdminLicenseAndSupportUsPageRootsAreFullWidth',
		],
	],
	'reconstrain_partner_spotlight' => [
		'file' => 'css/admin-support-us.css',
		'from' => ".azc-support-us--page .azc-support-us__primary {\n\tmax-width: none;",
		'to' => ".azc-support-us--page .azc-support-us__primary {\n\tmax-width: 48rem;",
		'filters' => [
			'SupportUsSectionContractTest::testCssContractHasFocusAndReducedMotion',
		],
	],
	'drop_option_titles' => [
		'file' => 'templates/parts/support-us-section.php',
		'from' => "support-us__option-title",
		'to' => "support-us__option-label",
		'filters' => [
			'SupportUsSectionContractTest::testAccessibilityHooksPresent',
		],
	],
	'reallow_azc_btn_link_cascade' => [
		'file' => 'css/common/accessibility.css',
		'from' => 'a:not(.btn):not(.azc-btn)',
		'to' => 'a:not(.btn)',
		'filters' => [
			'AzcBtnCascadeContractTest::testLinkResetsExcludeAzcBtn',
		],
	],
	'reembed_support_us_into_settings' => [
		'file' => 'templates/admin-settings.php',
		'from' => "['href' => '#section-projectcheck-heading', 'label' => \$l->t('ProjectCheck connection')],",
		'to' => "['href' => '#section-projectcheck-heading', 'label' => \$l->t('ProjectCheck connection')],\n                    ['href' => '#azc-support-us-title', 'label' => \$l->t('Support & us')],",
		'filters' => [
			'SupportUsSectionContractTest::testSupportUsLivesOnDedicatedAdminPageNotSettingsEmbed',
		],
	],
	'restack_past_record_margin' => [
		'file' => 'css/absences.css',
		'from' => ".badge--past-record,\n.absence-past-record-badge {\n\tmargin-top: 0;\n}",
		'to' => ".badge--past-record,\n.absence-past-record-badge {\n\tmargin-top: var(--space-2, 0.5rem);\n}",
		'filters' => [
			'TableConventionTest::testDenseListTablesUseResponsiveCardReflow',
		],
	],
	'drop_support_us_card_header' => [
		'file' => 'templates/parts/support-us-section.php',
		'from' => '-card__header',
		'to' => '-section__header',
		'filters' => [
			'SupportUsSectionContractTest::testAccessibilityHooksPresent',
			'SupportUsSectionRenderTest::testRenderEscapesDisplayNameAndOmitsMobileWithoutFlag',
		],
	],
	'drop_type_badges_wrapper_class' => [
		'file' => 'templates/absences.php',
		'from' => 'class="absence-type-badges"',
		'to' => 'class="absence-badges-stack"',
		'filters' => [
			'TableConventionTest::testDenseListTablesUseResponsiveCardReflow',
		],
	],
	'strip_noopener_from_support_page' => [
		'file' => 'templates/parts/support-us-section.php',
		'from' => "\t\t\t\t\t\trel=\"noopener noreferrer\"\n\t\t\t\t\t><?php p(\$l->t('Open support page')); ?></a>",
		'to' => "\t\t\t\t\t\trel=\"noopener\"\n\t\t\t\t\t><?php p(\$l->t('Open support page')); ?></a>",
		'filters' => [
			'SupportUsSectionContractTest::testExternalLinksUseNoopenerNoreferrer',
		],
	],
];

echo "== baseline layout contracts ==\n";
$baseline = run_filters($appRoot, $phpunit, [
	'SupportUsSectionContractTest',
	'SupportUsSectionRenderTest',
	'AzcBtnCascadeContractTest',
	'TableConventionTest::testDenseListTablesUseResponsiveCardReflow',
]);
if ($baseline !== 0) {
	fwrite(STDERR, "Baseline tests must pass before mutation run\n");
	exit(1);
}

$failed = [];
foreach ($mutations as $name => $mutation) {
	if (!apply_and_expect_fail($appRoot, $phpunit, $name, $mutation)) {
		$failed[] = $name;
	}
}

// Ensure no leftover backups
foreach ($mutations as $mutation) {
	$bak = $appRoot . '/' . $mutation['file'] . '.mutation-bak';
	if (is_file($bak)) {
		restore_file($appRoot . '/' . $mutation['file'], $bak);
	}
}

if ($failed !== []) {
	fwrite(STDERR, 'Mutations not killed: ' . implode(', ', $failed) . "\n");
	exit(1);
}

echo "\nAll Support Us / past-record layout mutations killed.\n";
exit(0);
