<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Exception;

use OCA\ArbeitszeitCheck\BusinessRuleCode;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;
use PHPUnit\Framework\TestCase;

class BusinessRuleExceptionTest extends TestCase
{
	public function testHttpPayloadIncludesStableCodeAndScalarDetails(): void
	{
		$e = new BusinessRuleException(
			'Minimum 11-hour rest period required between shifts (ArbZG §5).',
			BusinessRuleCode::REST_PERIOD_REQUIRED,
			[
				'min_rest_hours' => 11,
				'law_label' => 'ArbZG §5',
				'hours_remaining' => 10.8,
				'nested' => ['drop' => true],
				'' => 'empty-key',
				0 => 'numeric-key',
			],
		);

		$payload = $e->toHttpPayload();
		$this->assertFalse($payload['success']);
		$this->assertSame($payload['error'], $payload['message']);
		$this->assertSame(BusinessRuleCode::REST_PERIOD_REQUIRED, $payload['error_code']);
		$this->assertSame(11, $payload['error_details']['min_rest_hours']);
		$this->assertSame('ArbZG §5', $payload['error_details']['law_label']);
		$this->assertSame(10.8, $payload['error_details']['hours_remaining']);
		$this->assertArrayNotHasKey('nested', $payload['error_details']);
		$this->assertArrayNotHasKey('', $payload['error_details']);
	}

	public function testHttpPayloadOmitsEmptyCodeAndDetails(): void
	{
		$e = new BusinessRuleException('plain rule');
		$payload = $e->toHttpPayload();
		$this->assertSame('plain rule', $payload['error']);
		$this->assertArrayNotHasKey('error_code', $payload);
		$this->assertArrayNotHasKey('error_details', $payload);
	}
}
