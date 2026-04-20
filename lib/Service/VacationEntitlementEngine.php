<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TariffRuleModuleMapper;
use OCA\ArbeitszeitCheck\Db\TariffRuleSetMapper;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignment;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignmentMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;

class VacationEntitlementEngine {
	public function __construct(
		private UserVacationPolicyAssignmentMapper $policyMapper,
		private TariffRuleSetMapper $ruleSetMapper,
		private TariffRuleModuleMapper $ruleModuleMapper,
		private UserWorkingTimeModelMapper $userWorkingTimeModelMapper,
		private WorkingTimeModelMapper $workingTimeModelMapper,
		private UserSettingsMapper $userSettingsMapper,
	) {
	}

	/**
	 * @return array{days: float, source: string, ruleSetId: int|null, trace: array}
	 */
	public function computeForDate(string $userId, \DateTimeInterface $asOfDate): array {
		$policy = $this->policyMapper->findCurrentByUser($userId, $asOfDate);
		if ($policy === null) {
			$legacy = (float)$this->resolveLegacyManualEntitlement($userId);
			return [
				'days' => $legacy,
				'source' => 'manual',
				'ruleSetId' => null,
				'trace' => [
					'mode' => 'legacy_default',
					'legacy_days' => $legacy,
				],
			];
		}

		return $this->computeForPolicy($userId, $policy, $asOfDate);
	}

	/**
	 * @return array{days: float, source: string, ruleSetId: int|null, trace: array}
	 */
	public function computeForPolicy(string $userId, UserVacationPolicyAssignment $policy, \DateTimeInterface $asOfDate): array {
		$mode = $policy->getVacationMode();
		if ($mode === Constants::VACATION_MODE_MANUAL_FIXED || $mode === Constants::VACATION_MODE_MANUAL_EXCEPTION) {
			$days = round((float)($policy->getManualDays() ?? 0.0), 2);
			return [
				'days' => $days,
				'source' => $mode === Constants::VACATION_MODE_MANUAL_EXCEPTION ? 'manual_exception' : 'manual',
				'ruleSetId' => $policy->getTariffRuleSetId(),
				'trace' => [
					'mode' => $mode,
					'manual_days' => $days,
					'override_reason' => $policy->getOverrideReason(),
				],
			];
		}

		if ($mode === Constants::VACATION_MODE_MODEL_BASED_SIMPLE) {
			$referenceDays = 30.0;
			$referenceWeekDays = 5.0;
			$workDaysPerWeek = 5.0;
			$modelAssignment = $this->userWorkingTimeModelMapper->findByUserAndDate($userId, new \DateTime($asOfDate->format('Y-m-d')));
			if ($modelAssignment !== null) {
				try {
					$workingTimeModel = $this->workingTimeModelMapper->find($modelAssignment->getWorkingTimeModelId());
					$workDaysPerWeek = max(1.0, min(7.0, round((float)$workingTimeModel->getWorkDaysPerWeek(), 2)));
				} catch (\Throwable $e) {
					// Keep safe defaults if model cannot be resolved.
				}
			}
			$days = round($referenceDays * ($workDaysPerWeek / $referenceWeekDays), 2);
			return [
				'days' => $days,
				'source' => 'simple_model',
				'ruleSetId' => null,
				'trace' => [
					'mode' => $mode,
					'formula' => 'reference_days * (work_days_per_week / reference_week_days)',
					'inputs' => [
						'reference_days' => $referenceDays,
						'work_days_per_week' => $workDaysPerWeek,
						'reference_week_days' => $referenceWeekDays,
					],
				],
			];
		}

		if ($mode !== Constants::VACATION_MODE_TARIFF_RULE_BASED) {
			return [
				'days' => (float)Constants::DEFAULT_VACATION_DAYS_PER_YEAR,
				'source' => 'manual',
				'ruleSetId' => null,
				'trace' => ['mode' => 'fallback_default'],
			];
		}

		$ruleSetId = $policy->getTariffRuleSetId();
		if ($ruleSetId === null) {
			throw new \RuntimeException('Tariff mode requires an assigned rule set');
		}
		$ruleSet = $this->ruleSetMapper->find($ruleSetId);
		$modules = $this->ruleModuleMapper->findByRuleSetId($ruleSetId);
		$baseDays = 30.0;
		$referenceWeekDays = 5.0;
		$workDaysPerWeek = 5.0;
		$additional = 0.0;
		$deductions = 0.0;
		$rounding = 'commercial';
		$proRata = 'none';

		foreach ($modules as $module) {
			$config = $module->getConfig();
			switch ($module->getModuleType()) {
				case 'base_formula':
					$baseDays = (float)($config['reference_days'] ?? $baseDays);
					$referenceWeekDays = (float)($config['reference_week_days'] ?? $referenceWeekDays);
					$workDaysPerWeek = (float)($config['work_days_per_week'] ?? $workDaysPerWeek);
					break;
				case 'additional_entitlements':
					$additional += (float)($config['days'] ?? 0.0);
					break;
				case 'deductions':
					$deductions += max(0.0, (float)($config['days'] ?? 0.0));
					break;
				case 'rounding_rule':
					$rounding = (string)($config['mode'] ?? $rounding);
					break;
				case 'pro_rata_rule':
					$proRata = (string)($config['mode'] ?? $proRata);
					break;
			}
		}

		// If tariff module omits work_days_per_week, resolve it from current assigned work model.
		if ($workDaysPerWeek <= 0.0) {
			$modelAssignment = $this->userWorkingTimeModelMapper->findByUserAndDate($userId, new \DateTime($asOfDate->format('Y-m-d')));
			if ($modelAssignment !== null) {
				try {
					$workingTimeModel = $this->workingTimeModelMapper->find($modelAssignment->getWorkingTimeModelId());
					$workDaysPerWeek = (float)$workingTimeModel->getWorkDaysPerWeek();
				} catch (\Throwable $e) {
					$workDaysPerWeek = 5.0;
				}
			}
		}

		$workDaysPerWeek = max(1.0, min(7.0, $workDaysPerWeek));
		$referenceWeekDays = max(1.0, min(7.0, $referenceWeekDays));
		$baseDays = max(0.0, min(366.0, $baseDays));

		$computed = $baseDays * ($workDaysPerWeek / max(1.0, $referenceWeekDays));
		$computed += $additional;
		$computed -= $deductions;
		$computed = max(0.0, min(366.0, $computed));
		$computed = $this->applyRounding($computed, $rounding);
		$computed = $this->applyProRata($computed, $proRata, $asOfDate);

		return [
			'days' => round($computed, 2),
			'source' => 'tariff',
			'ruleSetId' => $ruleSet->getId(),
			'trace' => [
				'mode' => $mode,
				'rule_set' => [
					'id' => $ruleSet->getId(),
					'tariff_code' => $ruleSet->getTariffCode(),
					'version' => $ruleSet->getVersion(),
					'status' => $ruleSet->getStatus(),
				],
				'formula' => 'base + additional - deductions',
				'inputs' => [
					'base_reference_days' => $baseDays,
					'work_days_per_week' => $workDaysPerWeek,
					'reference_week_days' => $referenceWeekDays,
					'additional_days' => $additional,
					'deduction_days' => $deductions,
					'rounding' => $rounding,
					'pro_rata' => $proRata,
					'as_of_date' => $asOfDate->format('Y-m-d'),
				],
				'result_days' => round($computed, 2),
			],
		];
	}

	private function resolveLegacyManualEntitlement(string $userId): int {
		$currentModel = $this->userWorkingTimeModelMapper->findCurrentByUser($userId);
		if ($currentModel !== null && $currentModel->getVacationDaysPerYear() !== null) {
			return max(0, min(366, (int)$currentModel->getVacationDaysPerYear()));
		}
		return max(0, min(366, (int)$this->userSettingsMapper->getIntegerSetting(
			$userId,
			'vacation_days_per_year',
			Constants::DEFAULT_VACATION_DAYS_PER_YEAR
		)));
	}

	private function applyRounding(float $value, string $mode): float {
		return match ($mode) {
			'floor' => floor($value),
			'ceil' => ceil($value),
			'half_day' => round($value * 2.0) / 2.0,
			default => round($value, 2),
		};
	}

	private function applyProRata(float $value, string $mode, \DateTimeInterface $asOfDate): float {
		if ($mode === 'none') {
			return $value;
		}
		$month = (int)$asOfDate->format('n');
		if ($mode === 'monthly') {
			return ($value / 12.0) * $month;
		}
		if ($mode === 'daily') {
			$dayOfYear = (int)$asOfDate->format('z') + 1;
			$yearDays = (int)$asOfDate->format('L') === 1 ? 366 : 365;
			return ($value / $yearDays) * $dayOfYear;
		}
		return $value;
	}
}

