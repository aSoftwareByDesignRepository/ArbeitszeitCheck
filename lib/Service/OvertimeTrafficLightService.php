<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCP\IConfig;

class OvertimeTrafficLightService
{
	public function __construct(
		private IConfig $config,
	) {
	}

	public function isEnabled(): bool
	{
		return $this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_OVERTIME_TRAFFIC_LIGHT_ENABLED, '0') === '1';
	}

	/**
	 * @return array{yellow_over: float, red_over: float, yellow_under: float, red_under: float}
	 */
	public function getThresholds(): array
	{
		return [
			'yellow_over' => $this->readFloat(Constants::CONFIG_OVERTIME_THRESHOLD_YELLOW_OVER, 5.0),
			'red_over' => $this->readFloat(Constants::CONFIG_OVERTIME_THRESHOLD_RED_OVER, 15.0),
			'yellow_under' => $this->readFloat(Constants::CONFIG_OVERTIME_THRESHOLD_YELLOW_UNDER, 5.0),
			'red_under' => $this->readFloat(Constants::CONFIG_OVERTIME_THRESHOLD_RED_UNDER, 15.0),
		];
	}

	/**
	 * @param array{yellow_over?: mixed, red_over?: mixed, yellow_under?: mixed, red_under?: mixed} $thresholds
	 * @return array{yellow_over: float, red_over: float, yellow_under: float, red_under: float}
	 */
	public function normalizeThresholds(array $thresholds): array
	{
		$normalized = [
			'yellow_over' => $this->sanitizeFloat($thresholds['yellow_over'] ?? 5.0),
			'red_over' => $this->sanitizeFloat($thresholds['red_over'] ?? 15.0),
			'yellow_under' => $this->sanitizeFloat($thresholds['yellow_under'] ?? 5.0),
			'red_under' => $this->sanitizeFloat($thresholds['red_under'] ?? 15.0),
		];

		if ($normalized['yellow_over'] > $normalized['red_over']) {
			throw new \InvalidArgumentException('Overtime yellow threshold must be less than or equal to red threshold.');
		}
		if ($normalized['yellow_under'] > $normalized['red_under']) {
			throw new \InvalidArgumentException('Undertime yellow threshold must be less than or equal to red threshold.');
		}

		return $normalized;
	}

	/**
	 * @param array{yellow_over: float, red_over: float, yellow_under: float, red_under: float} $thresholds
	 * @return array{state: string, direction: string|null, level: string|null}
	 */
	public function classify(float $balanceHours, array $thresholds): array
	{
		if ($balanceHours >= $thresholds['red_over']) {
			return ['state' => 'red_over', 'direction' => 'over', 'level' => 'red'];
		}
		if ($balanceHours >= $thresholds['yellow_over']) {
			return ['state' => 'yellow_over', 'direction' => 'over', 'level' => 'yellow'];
		}

		$underHours = abs($balanceHours);
		if ($balanceHours < 0 && $underHours >= $thresholds['red_under']) {
			return ['state' => 'red_under', 'direction' => 'under', 'level' => 'red'];
		}
		if ($balanceHours < 0 && $underHours >= $thresholds['yellow_under']) {
			return ['state' => 'yellow_under', 'direction' => 'under', 'level' => 'yellow'];
		}

		return ['state' => 'green', 'direction' => null, 'level' => null];
	}

	private function readFloat(string $key, float $default): float
	{
		return $this->sanitizeFloat($this->config->getAppValue('arbeitszeitcheck', $key, (string)$default));
	}

	private function sanitizeFloat(mixed $value): float
	{
		$number = (float)str_replace(',', '.', trim((string)$value));
		if (!is_finite($number)) {
			return 0.0;
		}
		return max(0.0, min(500.0, $number));
	}
}

