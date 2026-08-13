<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\DatevPremiumLohnartMap;
use PHPUnit\Framework\TestCase;

class DatevPremiumLohnartMapTest extends TestCase
{
	public function testNormalizeDropsEmptyAndKeepsValidCodes(): void
	{
		$map = DatevPremiumLohnartMap::normalize([
			'sunday' => '3100',
			'night' => '',
			'saturday' => ' 320 ',
			'overtime_base' => '1',
		]);
		$this->assertSame([
			'sunday' => '3100',
			'saturday' => '320',
			'overtime_base' => '1',
		], $map);
	}

	public function testValidateRejectsZeroPaddedAndNonDigits(): void
	{
		$this->assertContains(
			DatevPremiumLohnartMap::ERR_INVALID_CODE,
			DatevPremiumLohnartMap::validate(['sunday' => '03100'])
		);
		$this->assertContains(
			DatevPremiumLohnartMap::ERR_INVALID_CODE,
			DatevPremiumLohnartMap::validate(['sunday' => 'abc'])
		);
		$this->assertContains(
			DatevPremiumLohnartMap::ERR_INVALID_ID,
			DatevPremiumLohnartMap::validate(['' => '3100'])
		);
		$this->assertSame([], DatevPremiumLohnartMap::validate(['sunday' => '3100', 'night' => '']));
	}

	public function testFromJsonRoundTrip(): void
	{
		$json = DatevPremiumLohnartMap::toJson(['sunday' => '3100', 'night' => '']);
		$this->assertSame('{"sunday":"3100"}', $json);
		$this->assertSame(['sunday' => '3100'], DatevPremiumLohnartMap::fromJson($json));
		$this->assertSame([], DatevPremiumLohnartMap::fromJson('{not-json'));
		$this->assertSame([], DatevPremiumLohnartMap::fromJson(''));
	}

	public function testNormalizeRejectsInvalidPayload(): void
	{
		$this->assertSame([], DatevPremiumLohnartMap::normalize('oops'));
		$this->assertSame([], DatevPremiumLohnartMap::normalize(['sunday' => '0']));
	}
}
