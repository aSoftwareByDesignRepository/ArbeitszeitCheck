<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\BreakCountable;
use OCA\ArbeitszeitCheck\Support\LaborLawProfileFactory;
use PHPUnit\Framework\TestCase;

class BreakCountableTest extends TestCase
{
	public function testDefaultsToFifteenMinutes(): void
	{
		self::assertSame(15, BreakCountable::minMinutes(null));
		self::assertSame(15, BreakCountable::minMinutes(0));
		self::assertSame(15, BreakCountable::minMinutes(-3));
		self::assertSame(900, BreakCountable::minSeconds(null));
	}

	public function testAcceptsAustrianTenMinuteFloor(): void
	{
		self::assertSame(10, BreakCountable::minMinutes(10));
		self::assertSame(600, BreakCountable::minSeconds(10));
	}

	public function testAustrianFactoryProfileUsesTen(): void
	{
		$profile = LaborLawProfileFactory::profileForCountry('AT');
		self::assertSame(10, $profile->minBreakMinutes);
		self::assertSame(600, BreakCountable::minSeconds($profile->minBreakMinutes));
		self::assertNotNull($profile->allowedBreakSplitPatterns);
	}
}
