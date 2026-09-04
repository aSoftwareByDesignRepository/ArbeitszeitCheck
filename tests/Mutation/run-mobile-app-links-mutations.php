<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for MobileAppLinks / Get the App Play Store URL.
 *
 * Usage (host):
 *   php tests/Mutation/run-mobile-app-links-mutations.php
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

$appRoot = dirname(__DIR__, 2);
$appId = basename($appRoot);
$workspaceRoot = dirname($appRoot, 2);
$source = $appRoot . '/lib/Support/MobileAppLinks.php';
$orig = (string) file_get_contents($source);

function run_tests(string $appRoot, string $workspaceRoot, string $appId): int {
	$filter = 'MobileAppLinksTest|GetTheAppPageContractTest';
	$inside = is_file('/var/www/html/lib/base.php');
	if ($inside) {
		$phpunit = is_file($appRoot . '/vendor/bin/phpunit')
			? $appRoot . '/vendor/bin/phpunit'
			: 'phpunit';
		$cmd = 'php -d opcache.enable_cli=0 -d opcache.enable=0 '
			. escapeshellarg($phpunit)
			. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
			. ' --filter ' . escapeshellarg($filter);
	} else {
		$cmd = 'docker compose -f ' . escapeshellarg($workspaceRoot . '/docker-compose.yml')
			. ' exec -u www-data -T nextcloud php -d opcache.enable_cli=0 -d opcache.enable=0 '
			. '/var/www/html/custom_apps/' . $appId . '/vendor/bin/phpunit '
			. '-c /var/www/html/custom_apps/' . $appId . '/phpunit.xml '
			. '--filter ' . escapeshellarg($filter);
	}
	passthru($cmd, $code);
	return (int) $code;
}

function expect_fail(string $label, callable $mutate, callable $restore, callable $run): void {
	echo "== mutate: {$label} ==\n";
	$mutate();
	try {
		$code = $run();
		if ($code === 0) {
			fwrite(STDERR, "Mutation '{$label}' was NOT caught\n");
			$restore();
			exit(1);
		}
		echo "caught OK (exit {$code})\n";
	} finally {
		$restore();
	}
}

$playNeedle = null;
if (preg_match('/https:\\/\\/play\\.google\\.com\\/store\\/apps\\/details\\?id=de\\.softwarebydesign\\.[a-z.]+/', $orig, $m) === 1) {
	$playNeedle = $m[0];
}
if ($playNeedle === null) {
	fwrite(STDERR, "Could not find Play Store URL in MobileAppLinks.php\n");
	exit(1);
}

echo "== baseline ==\n";
$run = static fn (): int => run_tests($appRoot, $workspaceRoot, $appId);
if ($run() !== 0) {
	fwrite(STDERR, "Baseline must pass\n");
	exit(1);
}

expect_fail(
	'evil_play_store_host',
	static function () use ($source, $orig, $playNeedle): void {
		$broken = str_replace($playNeedle, 'https://evil.example/phish', $orig, $count);
		if ($count < 1) {
			fwrite(STDERR, "Failed to mutate Play Store URL\n");
			exit(1);
		}
		file_put_contents($source, $broken);
	},
	static function () use ($source, $orig): void {
		file_put_contents($source, $orig);
	},
	$run,
);

$fromOneLine = '$path = $this->isGermanLocale($languageCode) ? self::PRODUCT_PAGE_PATH_DE : self::PRODUCT_PAGE_PATH;';
$fromMulti = "\$path = \$this->isGermanLocale(\$languageCode)\n"
	. "\t\t\t? self::PRODUCT_PAGE_PATH_DE\n"
	. "\t\t\t: self::PRODUCT_PAGE_PATH;";
$from = str_contains($orig, $fromOneLine) ? $fromOneLine : $fromMulti;

expect_fail(
	'english_only_product_path',
	static function () use ($source, $orig, $from): void {
		$broken = str_replace($from, '$path = self::PRODUCT_PAGE_PATH;', $orig, $count);
		if ($count !== 1) {
			fwrite(STDERR, "Failed to mutate product page locale branch (count={$count})\n");
			exit(1);
		}
		file_put_contents($source, $broken);
	},
	static function () use ($source, $orig): void {
		file_put_contents($source, $orig);
	},
	$run,
);

echo "\nAll MobileAppLinks mutations killed.\n";
