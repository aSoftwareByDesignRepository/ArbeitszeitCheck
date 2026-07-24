<?php

declare(strict_types=1);

/**
 * Lightweight mutation gauntlet for SupportUsLinks (no Infection dependency required).
 *
 * Runs the real unit tests, then applies known-bad source mutations and asserts
 * that SupportUsLinksTest fails — proving the suite catches broken link logic.
 *
 * Usage (from app root, inside Docker when applicable):
 *   php tests/Mutation/run-support-us-links-mutations.php
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

$appRoot = dirname(__DIR__, 2);
$source = $appRoot . '/lib/Support/SupportUsLinks.php';
$backup = $source . '.mutation-bak';
$phpunit = $appRoot . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
	$phpunit = 'phpunit';
}

function run_unit_tests(string $appRoot, string $phpunit): int {
	$cmd = escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
		. ' --filter SupportUsLinksTest';
	passthru($cmd, $code);
	return (int)$code;
}

function restore(string $source, string $backup): void {
	if (is_file($backup)) {
		rename($backup, $source);
	}
}

if (!is_file($source)) {
	fwrite(STDERR, "Missing SupportUsLinks.php\n");
	exit(1);
}

echo "== baseline SupportUsLinksTest ==\n";
$baseline = run_unit_tests($appRoot, $phpunit);
if ($baseline !== 0) {
	fwrite(STDERR, "Baseline tests must pass before mutation run\n");
	exit(1);
}

$mutations = [
	'break_sponsors_host' => [
		'from' => "public const SPONSORS_URL = 'https://github.com/sponsors/aSoftwareByDesignRepository';",
		'to' => "public const SPONSORS_URL = 'https://evil.example/phish';",
	],
	'drop_subject_encoding' => [
		'from' => "return 'mailto:' . self::CONTACT_EMAIL . '?subject=' . rawurlencode(\$subject);",
		'to' => "return 'mailto:' . self::CONTACT_EMAIL . '?subject=' . \$subject;",
	],
	'force_english_locale' => [
		'from' => "return str_starts_with(\$lang, 'de');",
		'to' => "return false;",
	],
	'allow_empty_display_name' => [
		'from' => "if (\$normalized === '' || !\$this->isSafeDisplayName(\$normalized)) {",
		'to' => "if (false && (\$normalized === '' || !\$this->isSafeDisplayName(\$normalized))) {",
	],
];

$failedToKill = [];
foreach ($mutations as $name => $pair) {
	echo "\n== mutation: {$name} ==\n";
	$original = file_get_contents($source);
	if ($original === false) {
		fwrite(STDERR, "Cannot read source\n");
		exit(1);
	}
	if (!str_contains($original, $pair['from'])) {
		fwrite(STDERR, "Mutation anchor not found for {$name}\n");
		$failedToKill[] = $name . ' (anchor missing)';
		continue;
	}
	file_put_contents($backup, $original);
	file_put_contents($source, str_replace($pair['from'], $pair['to'], $original));
	$code = run_unit_tests($appRoot, $phpunit);
	restore($source, $backup);
	if ($code === 0) {
		$failedToKill[] = $name;
		echo "MUTATION SURVIVED: {$name}\n";
	} else {
		echo "killed {$name}\n";
	}
}

restore($source, $backup);

if ($failedToKill !== []) {
	fwrite(STDERR, "Mutations not killed: " . implode(', ', $failedToKill) . "\n");
	exit(1);
}

echo "\nAll SupportUsLinks mutations killed.\n";
exit(0);
