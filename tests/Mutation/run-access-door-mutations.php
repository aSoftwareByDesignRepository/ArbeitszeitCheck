<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for ArbeitszeitCheck portfolio access door.
 * Run: php tests/Mutation/run-access-door-mutations.php
 */

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\Generator\Generator;
use Psr\Log\LoggerInterface;

$failures = 0;

function kill(string $label, callable $assert): void
{
	global $failures;
	try {
		$assert();
		fwrite(STDOUT, "KILL  {$label}\n");
	} catch (Throwable $e) {
		$failures++;
		fwrite(STDOUT, "SURVIVE {$label}: {$e->getMessage()}\n");
	}
}

function makeService(array $configMap, ?callable $inGroup = null): PermissionService
{
	$gen = new Generator();
	$groupManager = $gen->testDouble(IGroupManager::class, true, [], [], '', false);
	$groupManager->method('isAdmin')->willReturn(false);
	$groupManager->method('isInGroup')->willReturnCallback($inGroup ?? static fn (): bool => false);

	$config = $gen->testDouble(IConfig::class, true, [], [], '', false);
	$config->method('getAppValue')->willReturnCallback(
		static function (string $app, string $key, string $default = '') use ($configMap): string {
			return $configMap[$key] ?? $default;
		}
	);

	return new PermissionService(
		$groupManager,
		$gen->testDouble(IAppManager::class, true, [], [], '', false),
		$config,
		$gen->testDouble(IUserManager::class, true, [], [], '', false),
		$gen->testDouble(TeamResolverService::class, true, [], [], '', false),
		$gen->testDouble(LoggerInterface::class, true, [], [], '', false),
	);
}

// Mutant: collapse Open into Restricted fail-closed
kill('open-mode-allows-stranger', static function (): void {
	$svc = makeService([
		Constants::CONFIG_ACCESS_RESTRICTION_ENABLED => '0',
		Constants::CONFIG_ACCESS_ALLOWED_USER_IDS => '[]',
		Constants::CONFIG_ACCESS_ALLOWED_GROUP_IDS => '[]',
		Constants::CONFIG_APP_ADMIN_USER_IDS => '[]',
	]);
	if (!$svc->isUserAllowedByAccessGroups('stranger')) {
		throw new RuntimeException('Open mode must admit logged-in users');
	}
});

// Mutant: Restricted empty allowlists fall open
kill('restricted-empty-fail-closed', static function (): void {
	$svc = makeService([
		Constants::CONFIG_ACCESS_RESTRICTION_ENABLED => '1',
		Constants::CONFIG_ACCESS_ALLOWED_USER_IDS => '[]',
		Constants::CONFIG_ACCESS_ALLOWED_GROUP_IDS => '[]',
		Constants::CONFIG_APP_ADMIN_USER_IDS => '[]',
	]);
	if ($svc->isUserAllowedByAccessGroups('stranger')) {
		throw new RuntimeException('Restricted empty lists must deny');
	}
});

// Mutant: invert user allowlist check
kill('restricted-user-allowlist', static function (): void {
	$svc = makeService([
		Constants::CONFIG_ACCESS_RESTRICTION_ENABLED => '1',
		Constants::CONFIG_ACCESS_ALLOWED_USER_IDS => '["alice"]',
		Constants::CONFIG_ACCESS_ALLOWED_GROUP_IDS => '[]',
		Constants::CONFIG_APP_ADMIN_USER_IDS => '[]',
	]);
	if (!$svc->isUserAllowedByAccessGroups('alice') || $svc->isUserAllowedByAccessGroups('bob')) {
		throw new RuntimeException('User allowlist broken');
	}
});

fwrite(STDOUT, $failures === 0 ? "All mutants killed.\n" : "{$failures} mutant(s) survived.\n");
exit($failures === 0 ? 0 : 1);
