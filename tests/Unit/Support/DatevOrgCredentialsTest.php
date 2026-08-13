<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\DatevOrgCredentials;
use PHPUnit\Framework\TestCase;

class DatevOrgCredentialsTest extends TestCase
{
	public function testValidatePairAcceptsBothEmptyOrBothSet(): void
	{
		$this->assertSame([], DatevOrgCredentials::validatePair('', ''));
		$this->assertSame([], DatevOrgCredentials::validatePair('1234567', '12345'));
		$this->assertSame([], DatevOrgCredentials::validatePair(' 12 3 ', '45'));
	}

	public function testValidatePairRejectsPartialAndInvalid(): void
	{
		$this->assertContains(DatevOrgCredentials::ERR_PAIR, DatevOrgCredentials::validatePair('123', ''));
		$this->assertContains(DatevOrgCredentials::ERR_PAIR, DatevOrgCredentials::validatePair('', '123'));
		$this->assertContains(DatevOrgCredentials::ERR_BERATER, DatevOrgCredentials::validatePair('12345678', '1'));
		$this->assertContains(DatevOrgCredentials::ERR_MANDANT, DatevOrgCredentials::validatePair('1', '123456'));
		$this->assertContains(DatevOrgCredentials::ERR_BERATER, DatevOrgCredentials::validatePair('12a', '1'));
	}

	public function testValidateLohnartAndPersonalnummer(): void
	{
		$this->assertSame([], DatevOrgCredentials::validateLohnart('1000'));
		$this->assertSame([], DatevOrgCredentials::validateLohnart('', true));
		$this->assertContains(DatevOrgCredentials::ERR_LOHNART, DatevOrgCredentials::validateLohnart('0100'));
		$this->assertContains(DatevOrgCredentials::ERR_LOHNART, DatevOrgCredentials::validateLohnart('', false));
		$this->assertSame([], DatevOrgCredentials::validatePersonalnummer('12345678'));
		$this->assertContains(DatevOrgCredentials::ERR_PERSONAL, DatevOrgCredentials::validatePersonalnummer('123456789'));
		$this->assertContains(DatevOrgCredentials::ERR_PERSONAL, DatevOrgCredentials::validatePersonalnummer('12ab'));
	}
}
