<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TariffRuleModule;
use OCA\ArbeitszeitCheck\Db\TariffRuleModuleMapper;
use OCA\ArbeitszeitCheck\Db\TariffRuleSet;
use OCA\ArbeitszeitCheck\Db\TariffRuleSetMapper;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignment;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignmentMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModel;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModel;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Service\VacationEntitlementEngine;
use PHPUnit\Framework\TestCase;

class VacationEntitlementEngineTest extends TestCase {
	public function testManualFixedReturnsConfiguredDays(): void {
		$policyMapper = $this->createMock(UserVacationPolicyAssignmentMapper::class);
		$ruleSetMapper = $this->createMock(TariffRuleSetMapper::class);
		$moduleMapper = $this->createMock(TariffRuleModuleMapper::class);
		$userModelMapper = $this->createMock(UserWorkingTimeModelMapper::class);
		$workingTimeModelMapper = $this->createMock(WorkingTimeModelMapper::class);
		$userSettingsMapper = $this->createMock(UserSettingsMapper::class);

		$policy = new UserVacationPolicyAssignment();
		$policy->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$policy->setManualDays(31.0);

		$policyMapper->method('findCurrentByUser')->willReturn($policy);

		$engine = new VacationEntitlementEngine($policyMapper, $ruleSetMapper, $moduleMapper, $userModelMapper, $workingTimeModelMapper, $userSettingsMapper);
		$result = $engine->computeForDate('u1', new \DateTimeImmutable('2026-01-01'));

		$this->assertSame('manual', $result['source']);
		$this->assertEquals(31.0, $result['days']);
	}

	public function testTariffRuleBasedCalculatesFormulaAndRounding(): void {
		$policyMapper = $this->createMock(UserVacationPolicyAssignmentMapper::class);
		$ruleSetMapper = $this->createMock(TariffRuleSetMapper::class);
		$moduleMapper = $this->createMock(TariffRuleModuleMapper::class);
		$userModelMapper = $this->createMock(UserWorkingTimeModelMapper::class);
		$workingTimeModelMapper = $this->createMock(WorkingTimeModelMapper::class);
		$userSettingsMapper = $this->createMock(UserSettingsMapper::class);

		$policy = new UserVacationPolicyAssignment();
		$policy->setVacationMode(Constants::VACATION_MODE_TARIFF_RULE_BASED);
		$policy->setTariffRuleSetId(12);
		$policyMapper->method('findCurrentByUser')->willReturn($policy);

		$ruleSet = new TariffRuleSet();
		$ruleSet->setId(12);
		$ruleSet->setTariffCode('TVOD');
		$ruleSet->setVersion('2026-01');
		$ruleSet->setStatus(Constants::TARIFF_RULE_SET_STATUS_ACTIVE);
		$ruleSetMapper->method('find')->with(12)->willReturn($ruleSet);

		$base = new TariffRuleModule();
		$base->setModuleType('base_formula');
		$base->setConfig(['reference_days' => 30, 'work_days_per_week' => 4, 'reference_week_days' => 5]);
		$add = new TariffRuleModule();
		$add->setModuleType('additional_entitlements');
		$add->setConfig(['days' => 1.5]);
		$round = new TariffRuleModule();
		$round->setModuleType('rounding_rule');
		$round->setConfig(['mode' => 'half_day']);
		$moduleMapper->method('findByRuleSetId')->with(12)->willReturn([$base, $add, $round]);

		$engine = new VacationEntitlementEngine($policyMapper, $ruleSetMapper, $moduleMapper, $userModelMapper, $workingTimeModelMapper, $userSettingsMapper);
		$result = $engine->computeForDate('u2', new \DateTimeImmutable('2026-06-15'));

		$this->assertSame('tariff', $result['source']);
		$this->assertEquals(25.5, $result['days']); // 30*(4/5)=24 + 1.5 => 25.5
		$this->assertSame(12, $result['ruleSetId']);
	}

	public function testSimpleModelFormulaGoldenCases(): void {
		$policyMapper = $this->createMock(UserVacationPolicyAssignmentMapper::class);
		$ruleSetMapper = $this->createMock(TariffRuleSetMapper::class);
		$moduleMapper = $this->createMock(TariffRuleModuleMapper::class);
		$userModelMapper = $this->createMock(UserWorkingTimeModelMapper::class);
		$workingTimeModelMapper = $this->createMock(WorkingTimeModelMapper::class);
		$userSettingsMapper = $this->createMock(UserSettingsMapper::class);

		$policy = new UserVacationPolicyAssignment();
		$policy->setVacationMode(Constants::VACATION_MODE_TARIFF_RULE_BASED);
		$policy->setTariffRuleSetId(42);
		$policyMapper->method('findCurrentByUser')->willReturn($policy);

		$ruleSet = new TariffRuleSet();
		$ruleSet->setId(42);
		$ruleSet->setTariffCode('TVOD');
		$ruleSet->setVersion('2026-01');
		$ruleSet->setStatus(Constants::TARIFF_RULE_SET_STATUS_ACTIVE);
		$ruleSetMapper->method('find')->willReturn($ruleSet);

		$makeBase = static function (float $workDays): TariffRuleModule {
			$m = new TariffRuleModule();
			$m->setModuleType('base_formula');
			$m->setConfig([
				'reference_days' => 30,
				'work_days_per_week' => $workDays,
				'reference_week_days' => 5,
			]);
			return $m;
		};

		$moduleMapper->method('findByRuleSetId')->willReturnOnConsecutiveCalls(
			[$makeBase(4.0)],
			[$makeBase(5.0)],
			[$makeBase(3.0)]
		);
		$engine = new VacationEntitlementEngine($policyMapper, $ruleSetMapper, $moduleMapper, $userModelMapper, $workingTimeModelMapper, $userSettingsMapper);
		$this->assertEquals(24.0, $engine->computeForDate('u', new \DateTimeImmutable('2026-01-01'))['days']);
		$this->assertEquals(30.0, $engine->computeForDate('u', new \DateTimeImmutable('2026-01-01'))['days']);
		$this->assertEquals(18.0, $engine->computeForDate('u', new \DateTimeImmutable('2026-01-01'))['days']);
	}

	public function testModelBasedSimpleUsesWorkingModelDaysPerWeek(): void {
		$policyMapper = $this->createMock(UserVacationPolicyAssignmentMapper::class);
		$ruleSetMapper = $this->createMock(TariffRuleSetMapper::class);
		$moduleMapper = $this->createMock(TariffRuleModuleMapper::class);
		$userModelMapper = $this->createMock(UserWorkingTimeModelMapper::class);
		$workingTimeModelMapper = $this->createMock(WorkingTimeModelMapper::class);
		$userSettingsMapper = $this->createMock(UserSettingsMapper::class);

		$policy = new UserVacationPolicyAssignment();
		$policy->setVacationMode(Constants::VACATION_MODE_MODEL_BASED_SIMPLE);
		$policyMapper->method('findCurrentByUser')->willReturn($policy);

		$userModelAssignment = new UserWorkingTimeModel();
		$userModelAssignment->setWorkingTimeModelId(7);
		$userModelMapper->method('findByUserAndDate')->willReturn($userModelAssignment);

		$workingTimeModel = new WorkingTimeModel();
		$workingTimeModel->setWorkDaysPerWeek(4.0);
		$workingTimeModelMapper->method('find')->with(7)->willReturn($workingTimeModel);

		$engine = new VacationEntitlementEngine($policyMapper, $ruleSetMapper, $moduleMapper, $userModelMapper, $workingTimeModelMapper, $userSettingsMapper);
		$result = $engine->computeForDate('u4', new \DateTimeImmutable('2026-06-01'));

		$this->assertSame('simple_model', $result['source']);
		$this->assertEquals(24.0, $result['days']);
	}
}

