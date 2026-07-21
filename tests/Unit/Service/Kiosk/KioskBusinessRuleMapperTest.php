<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\BusinessRuleCode;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskBusinessRuleMapper;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskException;
use PHPUnit\Framework\TestCase;

final class KioskBusinessRuleMapperTest extends TestCase
{
	private KioskBusinessRuleMapper $mapper;

	protected function setUp(): void
	{
		parent::setUp();
		$this->mapper = new KioskBusinessRuleMapper();
	}

	public function testMapsKnownReasonCodes(): void
	{
		$cases = [
			[BusinessRuleCode::ALREADY_CLOCKED_IN, 'KIOSK_ALREADY_CLOCKED_IN'],
			[BusinessRuleCode::ON_BREAK_END_FIRST, 'KIOSK_ON_BREAK_END_FIRST'],
			[BusinessRuleCode::NOT_CLOCKED_IN, 'KIOSK_NOT_CLOCKED_IN'],
			[BusinessRuleCode::BREAK_ALREADY_STARTED, 'KIOSK_BREAK_ALREADY_STARTED'],
			[BusinessRuleCode::NOT_ON_BREAK, 'KIOSK_NOT_ON_BREAK'],
			[BusinessRuleCode::DAILY_HOURS_LIMIT, 'KIOSK_DAILY_HOURS_LIMIT'],
			[BusinessRuleCode::REST_PERIOD_REQUIRED, 'KIOSK_REST_PERIOD_REQUIRED'],
		];

		foreach ($cases as [$reason, $expectedCode]) {
			$e = new BusinessRuleException('detail for ' . $reason, $reason);
			$mapped = $this->mapper->toKioskException($e);
			self::assertInstanceOf(KioskException::class, $mapped);
			self::assertSame($expectedCode, $mapped->getErrorCode());
			self::assertSame('detail for ' . $reason, $mapped->getMessage());
		}
	}

	public function testUnknownReasonFallsBackToActionRejected(): void
	{
		$mapped = $this->mapper->toKioskException(new BusinessRuleException('Something else', null));
		self::assertSame('KIOSK_ACTION_REJECTED', $mapped->getErrorCode());
		self::assertSame('Something else', $mapped->getMessage());
	}
}
