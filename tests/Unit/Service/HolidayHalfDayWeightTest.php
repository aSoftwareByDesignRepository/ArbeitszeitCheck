<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\Holiday;
use OCA\ArbeitszeitCheck\Db\HolidayMapper;
use OCA\ArbeitszeitCheck\Db\HolidaySuppressionMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * E-6: Swiss half-day statutory holidays must keep weight 0.5 in working-day math.
 */
class HolidayHalfDayWeightTest extends TestCase
{
	/** @var HolidayMapper|MockObject */
	private $holidayMapper;

	/** @var HolidayService */
	private $service;

	protected function setUp(): void
	{
		parent::setUp();

		$this->holidayMapper = $this->createMock(HolidayMapper::class);
		$suppressionMapper = $this->createMock(HolidaySuppressionMapper::class);
		$userSettings = $this->createMock(UserSettingsMapper::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				if ($key === 'statutory_auto_reseed') {
					return '0';
				}
				return (string)$default;
			}
		);

		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturn(null);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $s) => $s);

		$this->service = new HolidayService(
			$this->holidayMapper,
			$suppressionMapper,
			$userSettings,
			$config,
			$cacheFactory,
			$l10n,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testStatutoryHalfDayKeepsHalfWeightInRangeDto(): void
	{
		$half = new Holiday();
		$half->setId(1);
		$half->setState('CH-ZH');
		$half->setDate(new \DateTime('2026-04-20'));
		$half->setName('Sechseläuten');
		$half->setKind(Holiday::KIND_HALF);
		$half->setScope(Holiday::SCOPE_STATUTORY);
		$half->setSource(Holiday::SOURCE_GENERATED);

		$full = new Holiday();
		$full->setId(2);
		$full->setState('CH-ZH');
		$full->setDate(new \DateTime('2026-01-01'));
		$full->setName('New Year');
		$full->setKind(Holiday::KIND_FULL);
		$full->setScope(Holiday::SCOPE_STATUTORY);
		$full->setSource(Holiday::SOURCE_GENERATED);

		$this->holidayMapper->method('hasStatutoryHolidaysForStateAndYear')->willReturn(true);
		$this->holidayMapper->method('findByStateAndYear')->willReturn([$half, $full]);

		$start = new \DateTime('2026-01-01');
		$end = new \DateTime('2026-12-31');
		$rows = $this->service->getHolidaysForRange('CH-ZH', $start, $end);

		$byDate = [];
		foreach ($rows as $row) {
			$byDate[$row['date']] = $row;
		}

		$this->assertSame(0.5, $byDate['2026-04-20']['weight'], 'E-6: half statutory must not be forced to 1.0');
		$this->assertSame('half', $byDate['2026-04-20']['kind']);
		$this->assertSame(1.0, $byDate['2026-01-01']['weight']);
		$this->assertSame('full', $byDate['2026-01-01']['kind']);
	}

	public function testCompanyHalfDayStillHalfWeight(): void
	{
		$half = new Holiday();
		$half->setId(3);
		$half->setState('CH-ZH');
		$half->setDate(new \DateTime('2026-12-24'));
		$half->setName('Christmas Eve');
		$half->setKind(Holiday::KIND_HALF);
		$half->setScope(Holiday::SCOPE_COMPANY);
		$half->setSource(Holiday::SOURCE_MANUAL);

		$this->holidayMapper->method('hasStatutoryHolidaysForStateAndYear')->willReturn(true);
		$this->holidayMapper->method('findByStateAndYear')->willReturn([$half]);

		$rows = $this->service->getHolidaysForRange(
			'CH-ZH',
			new \DateTime('2026-12-01'),
			new \DateTime('2026-12-31')
		);

		$this->assertCount(1, $rows);
		$this->assertSame(0.5, $rows[0]['weight']);
	}
}
