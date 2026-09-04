<?php

declare(strict_types=1);

/**
 * Bootstrap for tests
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

// Support multiple layouts for running tests:
// 1. Standalone app with NEXTCLOUD_ROOT env (CI or local):
//    NEXTCLOUD_ROOT=/path/to/nextcloud (must contain lib/base.php)
// 2. App inside a Nextcloud checkout (this repo layout):
//    nextcloud-dev/
//      ├─ lib/
//      └─ apps/arbeitszeitcheck/

$candidates = [];

$nextcloudRoot = getenv('NEXTCLOUD_ROOT') ?: '';
if ($nextcloudRoot !== '') {
	$candidates[] = rtrim($nextcloudRoot, '/\\') . '/lib/base.php';
}

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
	require_once $vendorAutoload;
} else {
	// Docker/dev trees often omit composer vendor; keep PSR-4 so unit tests still load.
	spl_autoload_register(static function (string $class): void {
		$prefixes = [
			'OCA\\ArbeitszeitCheck\\Tests\\' => __DIR__ . '/',
			'OCA\\ArbeitszeitCheck\\' => dirname(__DIR__) . '/lib/',
		];
		foreach ($prefixes as $prefix => $baseDir) {
			if (!str_starts_with($class, $prefix)) {
				continue;
			}
			$relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			$file = $baseDir . $relative;
			if (is_file($file)) {
				require_once $file;
				return;
			}
		}
	});
}

// Monorepo / this dev setup: lib/ next to apps/
$candidates[] = __DIR__ . '/../../lib/base.php';

// Fallback: traditional Nextcloud layout (apps/ + lib/ siblings)
$candidates[] = __DIR__ . '/../../../lib/base.php';

$base = null;
foreach ($candidates as $candidate) {
	if (is_file($candidate)) {
		$base = $candidate;
		break;
	}
}

if ($base === null) {
	throw new RuntimeException(
		"Could not locate Nextcloud lib/base.php for tests.\n" .
		"Set NEXTCLOUD_ROOT to your Nextcloud server root or run the tests from within a Nextcloud checkout " .
		"where 'lib/base.php' exists next to 'apps/'."
	);
}

require_once $base;

$integrationBootstrap = dirname(__DIR__, 3) . '/scripts/phpunit-integration-bootstrap.php';
if (is_file($integrationBootstrap)) {
	require_once $integrationBootstrap;
}

// Some environments (notably containerized Nextcloud images) don't ship the core test classes.
// Load our minimal shim so unit tests can still execute.
if (!class_exists(\Test\TestCase::class)) {
	$shim = __DIR__ . '/shim/TestCase.php';
	if (is_file($shim)) {
		require_once $shim;
	}
}

if (!defined('PHPUNIT_RUNNING')) {
	define('PHPUNIT_RUNNING', true);
}

// Production default is the real vendor key; PHPUnit fixtures use the deterministic test key.
putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . \OCA\ArbeitszeitCheck\Config\VendorPublicKey::TEST_PUBLIC_KEY_B64);
