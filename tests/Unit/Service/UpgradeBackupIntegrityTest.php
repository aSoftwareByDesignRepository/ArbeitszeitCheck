<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Exception\UpgradeBackupException;
use OCA\ArbeitszeitCheck\Migration\ArbeitszeitCheckTableCatalog;
use OCA\ArbeitszeitCheck\Service\UpgradeBackupCatalog;
use OCA\ArbeitszeitCheck\Service\UpgradeBackupIntegrity;
use PHPUnit\Framework\TestCase;

final class UpgradeBackupIntegrityTest extends TestCase
{
	public function testAssertSnapshotIdRejectsTraversal(): void
	{
		$this->expectException(UpgradeBackupException::class);
		UpgradeBackupIntegrity::assertSnapshotId('../etc/passwd');
	}

	public function testAssertSnapshotIdAcceptsValidId(): void
	{
		UpgradeBackupIntegrity::assertSnapshotId('20260624T120000Z-deadbeef');
		$this->addToAssertionCount(1);
	}

	public function testValidateManifestRejectsIncompleteSnapshot(): void
	{
		$this->expectException(UpgradeBackupException::class);
		UpgradeBackupIntegrity::validateManifest(
			[
				'format' => UpgradeBackupCatalog::FORMAT_VERSION,
				'appId' => UpgradeBackupCatalog::APP_ID,
				'id' => '20260624T120000Z-deadbeef',
				'complete' => false,
				'integrity' => 'abc',
				'tables' => ['pc_projects' => ['checksum' => 'abc', 'rowCount' => 0]],
			],
			'20260624T120000Z-deadbeef',
			['pc_projects' => ['checksum' => 'abc', 'rowCount' => 0]],
		);
	}

	public function testValidateManifestRejectsTamperedIntegrityHash(): void
	{
		$tables = ['pc_projects' => ['checksum' => 'abc', 'rowCount' => 0]];
		$this->expectException(UpgradeBackupException::class);
		UpgradeBackupIntegrity::validateManifest(
			[
				'format' => UpgradeBackupCatalog::FORMAT_VERSION,
				'appId' => UpgradeBackupCatalog::APP_ID,
				'id' => '20260624T120000Z-deadbeef',
				'complete' => true,
				'integrity' => 'tampered',
				'tables' => $tables,
			],
			'20260624T120000Z-deadbeef',
			$tables,
		);
	}

	public function testValidateManifestRequiresIntegrityHash(): void
	{
		$tables = ['pc_projects' => ['checksum' => 'abc', 'rowCount' => 0]];
		$this->expectException(UpgradeBackupException::class);
		UpgradeBackupIntegrity::validateManifest(
			[
				'format' => UpgradeBackupCatalog::FORMAT_VERSION,
				'appId' => UpgradeBackupCatalog::APP_ID,
				'id' => '20260624T120000Z-deadbeef',
				'complete' => true,
				'tables' => $tables,
			],
			'20260624T120000Z-deadbeef',
			$tables,
		);
	}

	public function testAssertTablePayloadDetectsChecksumMismatch(): void
	{
		$content = json_encode([['id' => 1]], JSON_THROW_ON_ERROR);
		$this->expectException(UpgradeBackupException::class);
		UpgradeBackupIntegrity::assertTablePayload('pc_projects', $content, [
			'checksum' => 'deadbeef',
			'rowCount' => 1,
		]);
	}

	public function testIsAllowedColumnRejectsInvalidNames(): void
	{
		self::assertFalse(UpgradeBackupIntegrity::isAllowedColumn('id;drop'));
		self::assertTrue(UpgradeBackupIntegrity::isAllowedColumn('project_id'));
	}

	public function testIsAllowedConfigKeyRejectsInvalidKeys(): void
	{
		self::assertFalse(UpgradeBackupIntegrity::isAllowedConfigKey('../evil'));
		self::assertTrue(UpgradeBackupIntegrity::isAllowedConfigKey('installed_version'));
		self::assertTrue(UpgradeBackupIntegrity::isAllowedConfigKey('rate_limit:category_write:admin'));
	}

	public function testIsAllowedAppDataNameRejectsTraversal(): void
	{
		self::assertFalse(UpgradeBackupIntegrity::isAllowedAppDataName('../evil'));
		self::assertFalse(UpgradeBackupIntegrity::isAllowedAppDataName('..'));
		self::assertTrue(UpgradeBackupIntegrity::isAllowedAppDataName('project_files'));
	}

	public function testIsAllowedTableNameRejectsInvalidNames(): void
	{
		self::assertFalse(UpgradeBackupIntegrity::isAllowedTableName('pc;drop'));
		self::assertTrue(UpgradeBackupIntegrity::isAllowedTableName('pc_projects'));
	}

	public function testAssertAppDataFolderNameRejectsInvalid(): void
	{
		$this->expectException(UpgradeBackupException::class);
		UpgradeBackupIntegrity::assertAppDataFolderName('../snapshots');
	}
}
