<?php

declare(strict_types=1);

/**
 * Lightweight mutation gauntlet for SupportUsLinks (no Infection dependency required).
 *
 * Usage (host, from repo nextcloud/):
 *   php tests/Mutation/run-support-us-links-mutations.php
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

$appRoot = dirname(__DIR__, 2);
$appId = basename($appRoot);
$workspaceRoot = dirname($appRoot, 2);
$source = $appRoot . '/lib/Support/SupportUsLinks.php';
$backup = $source . '.mutation-bak';
$orig = (string) file_get_contents($source);

function run_unit_tests(string $appRoot, string $workspaceRoot, string $appId): int
{
	$filter = 'SupportUsLinksTest';
	$inside = is_file('/var/www/html/lib/base.php');
	if ($inside) {
		$phpunit = is_file($appRoot . '/vendor/bin/phpunit')
			? $appRoot . '/vendor/bin/phpunit'
			: 'phpunit';
		$cmd = 'php -d opcache.enable_cli=0 -d opcache.enable=0 '
			. escapeshellarg($phpunit)
			. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
			. ' --do-not-cache-result'
			. ' --filter ' . escapeshellarg($filter);
	} else {
		$cmd = 'docker compose -f ' . escapeshellarg($workspaceRoot . '/docker-compose.yml')
			. ' exec -u www-data -T nextcloud php -d opcache.enable_cli=0 -d opcache.enable=0 '
			. '/var/www/html/custom_apps/' . $appId . '/vendor/bin/phpunit '
			. '-c /var/www/html/custom_apps/' . $appId . '/phpunit.xml '
			. '--do-not-cache-result '
			. '--filter ' . escapeshellarg($filter);
	}
	passthru($cmd, $code);

	return (int)$code;
}

function restore(string $source, string $backup): void
{
	if (is_file($backup)) {
		file_put_contents($source, (string) file_get_contents($backup));
		unlink($backup);
	}
}

if (!is_file($source)) {
	fwrite(STDERR, "Missing SupportUsLinks.php\n");
	exit(1);
}

echo "== baseline SupportUsLinksTest ==\n";
$run = static fn (): int => run_unit_tests($appRoot, $workspaceRoot, $appId);
if ($run() !== 0) {
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
		'from' => "return \$lang === 'de' || str_starts_with(\$lang, 'de-');",
		'to' => "return false;",
	],
	'allow_empty_display_name' => [
		'from' => "if (\$normalized === '' || !\$this->isSafeDisplayName(\$normalized)) {",
		'to' => "if (false && (\$normalized === '' || !\$this->isSafeDisplayName(\$normalized))) {",
	],
	'allow_at_in_relative_license_url' => [
		'from' => "&& !str_contains(\$url, '\\\\')\n\t\t\t\t&& !str_contains(\$url, '@');",
		'to' => "&& !str_contains(\$url, '\\\\');",
	],
	'allow_protocol_relative_license_url' => [
		'from' => "return !str_starts_with(\$url, '//')\n\t\t\t\t&& !preg_match('/[\\x00-\\x1F\\x7F\\s]/', \$url)",
		'to' => "return !preg_match('/[\\x00-\\x1F\\x7F\\s]/', \$url)",
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
	$mutated = str_replace($pair['from'], $pair['to'], $original);
	if ($mutated === $original) {
		fwrite(STDERR, "Mutation replace had no effect for {$name}\n");
		$failedToKill[] = $name . ' (no effect)';
		restore($source, $backup);
		continue;
	}
	if (file_put_contents($source, $mutated) === false) {
		fwrite(STDERR, "Cannot write mutated source for {$name}\n");
		$failedToKill[] = $name . ' (write failed)';
		restore($source, $backup);
		continue;
	}
	$code = $run();
	restore($source, $backup);
	if ($code === 0) {
		$failedToKill[] = $name;
		echo "MUTATION SURVIVED: {$name}\n";
	} else {
		echo "killed {$name}\n";
	}
}

restore($source, $backup);
if ((string) file_get_contents($source) !== $orig) {
	file_put_contents($source, $orig);
}

if ($failedToKill !== []) {
	fwrite(STDERR, "Mutations not killed: " . implode(', ', $failedToKill) . "\n");
	exit(1);
}

echo "\nAll SupportUsLinks mutations killed.\n";
exit(0);
