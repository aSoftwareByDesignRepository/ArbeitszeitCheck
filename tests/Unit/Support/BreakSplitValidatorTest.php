<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\BreakSplitValidator;
use PHPUnit\Framework\TestCase;

class BreakSplitValidatorTest extends TestCase
{
	public function testSumOnlyAcceptsTotalWithoutPattern(): void
	{
		$this->assertTrue(BreakSplitValidator::meetsRequirement([20, 10], 30, null));
		$this->assertFalse(BreakSplitValidator::meetsRequirement([20, 5], 30, null));
	}

	public function testAustrianContinuousThirty(): void
	{
		$patterns = [[15, 15], [10, 10, 10]];
		$this->assertTrue(BreakSplitValidator::meetsRequirement([30], 30, $patterns));
		$this->assertTrue(BreakSplitValidator::meetsRequirement([45], 30, $patterns));
	}

	public function testAustrianTwoTimesFifteen(): void
	{
		$patterns = [[15, 15], [10, 10, 10]];
		$this->assertTrue(BreakSplitValidator::meetsRequirement([15, 15], 30, $patterns));
		$this->assertTrue(BreakSplitValidator::meetsRequirement([20, 15], 30, $patterns));
		$this->assertFalse(BreakSplitValidator::meetsRequirement([20, 10], 30, $patterns));
	}

	public function testAustrianThreeTimesTen(): void
	{
		$patterns = [[15, 15], [10, 10, 10]];
		$this->assertTrue(BreakSplitValidator::meetsRequirement([10, 10, 10], 30, $patterns));
		$this->assertTrue(BreakSplitValidator::meetsRequirement([12, 10, 10], 30, $patterns));
		$this->assertFalse(BreakSplitValidator::meetsRequirement([10, 10, 5], 30, $patterns));
		$this->assertFalse(BreakSplitValidator::meetsRequirement([10, 10, 10, 10], 30, $patterns));
	}

	public function testRejectsInsufficientTotalEvenWithPatternShape(): void
	{
		$patterns = [[15, 15], [10, 10, 10]];
		$this->assertFalse(BreakSplitValidator::meetsRequirement([15, 14], 30, $patterns));
	}

	public function testZeroRequirementAlwaysPasses(): void
	{
		$this->assertTrue(BreakSplitValidator::meetsRequirement([], 0, [[15, 15]]));
		$this->assertTrue(BreakSplitValidator::meetsRequirement([], -5, null));
	}

	public function testEmptyPatternsFailClosedWhenSplitsRequired(): void
	{
		// Patterns present but empty / garbage → continuous-only path; short portions fail.
		$this->assertFalse(BreakSplitValidator::meetsRequirement([10, 10, 10], 30, []));
		$this->assertFalse(BreakSplitValidator::meetsRequirement([15, 15], 30, [[]]));
		$this->assertTrue(BreakSplitValidator::meetsRequirement([30], 30, []));
	}

	public function testNegativePortionsDoNotInflateTotal(): void
	{
		// Negatives are clamped to 0 — they must never reduce the required total
		// by "cancelling" positive minutes in a raw sum.
		$this->assertFalse(BreakSplitValidator::meetsRequirement([-10, 20], 30, null));
		$this->assertTrue(BreakSplitValidator::meetsRequirement([-10, 30], 30, null));
		// Without the clamp, 30 + (-1) = 29 would incorrectly fail the sum gate.
		$this->assertTrue(BreakSplitValidator::meetsRequirement([30, -1], 30, null));
		$this->assertTrue(BreakSplitValidator::meetsRequirement([-10, 30], 30, [[15, 15]]));
	}

	public function testOrderIndependentPatternMatching(): void
	{
		$patterns = [[15, 15], [10, 10, 10]];
		$this->assertTrue(BreakSplitValidator::meetsRequirement([10, 15, 10], 30, $patterns));
	}
}
