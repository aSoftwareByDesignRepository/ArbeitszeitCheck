<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\OvertimeTrafficLightService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class OvertimeTrafficLightServiceTest extends TestCase
{
	public function testClassifiesOvertimeAndUndertimeLevels(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnMap([
			['arbeitszeitcheck', 'overtime_threshold_yellow_over', '5', '5'],
			['arbeitszeitcheck', 'overtime_threshold_red_over', '15', '15'],
			['arbeitszeitcheck', 'overtime_threshold_yellow_under', '5', '5'],
			['arbeitszeitcheck', 'overtime_threshold_red_under', '15', '15'],
			['arbeitszeitcheck', 'overtime_traffic_light_enabled', '0', '1'],
		]);
		$service = new OvertimeTrafficLightService($config);
		$thresholds = $service->getThresholds();

		$this->assertSame('yellow_over', $service->classify(5.5, $thresholds)['state']);
		$this->assertSame('red_over', $service->classify(18.0, $thresholds)['state']);
		$this->assertSame('yellow_under', $service->classify(-6.0, $thresholds)['state']);
		$this->assertSame('red_under', $service->classify(-20.0, $thresholds)['state']);
		$this->assertSame('green', $service->classify(0.5, $thresholds)['state']);
		$this->assertTrue($service->isEnabled());
	}

	public function testRejectsInvalidThresholdOrder(): void
	{
		$config = $this->createMock(IConfig::class);
		$service = new OvertimeTrafficLightService($config);

		$this->expectException(\InvalidArgumentException::class);
		$service->normalizeThresholds([
			'yellow_over' => 10,
			'red_over' => 5,
			'yellow_under' => 5,
			'red_under' => 10,
		]);
	}
}

