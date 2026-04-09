<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\MonthClosureCanonical;
use PHPUnit\Framework\TestCase;

class MonthClosureCanonicalTest extends TestCase
{
	public function testEncodeIsStableForKeyOrder(): void
	{
		$a = ['z' => 1, 'a' => ['b' => 2, 'a' => 3]];
		$b = ['a' => ['a' => 3, 'b' => 2], 'z' => 1];
		$this->assertSame(MonthClosureCanonical::encode($a), MonthClosureCanonical::encode($b));
	}

	public function testHashChainChangesWhenPayloadChanges(): void
	{
		$json1 = MonthClosureCanonical::encode(['x' => 1]);
		$json2 = MonthClosureCanonical::encode(['x' => 2]);
		$h1 = MonthClosureCanonical::hashChain('', 'user1', 2024, 3, 1, $json1);
		$h2 = MonthClosureCanonical::hashChain('', 'user1', 2024, 3, 1, $json2);
		$this->assertNotSame($h1, $h2);
		$this->assertSame(64, strlen($h1));
	}

	public function testHashChainChainsPrevious(): void
	{
		$json = MonthClosureCanonical::encode(['k' => 'v']);
		$h0 = MonthClosureCanonical::hashChain('prevhash', 'u', 2025, 1, 2, $json);
		$h1 = MonthClosureCanonical::hashChain('otherprev', 'u', 2025, 1, 2, $json);
		$this->assertNotSame($h0, $h1);
	}
}
