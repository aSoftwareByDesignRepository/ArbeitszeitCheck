<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Controller\AbsenceController;
use OCA\ArbeitszeitCheck\Controller\ManagerController;
use OCA\ArbeitszeitCheck\Controller\SubstituteController;
use OCA\ArbeitszeitCheck\Controller\TimeTrackingController;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MobileApiAttributesTest extends TestCase {
	/**
	 * @param class-string $class
	 * @param list<string> $methods
	 */
	private function assertMethodsHaveAttribute(string $attributeClass, string $class, array $methods): void {
		foreach ($methods as $methodName) {
			$method = new ReflectionMethod($class, $methodName);
			$found = false;
			foreach ($method->getAttributes() as $attribute) {
				if ($attribute->getName() === $attributeClass) {
					$found = true;
					break;
				}
			}
			$this->assertTrue($found, sprintf('%s::%s must have %s', $class, $methodName, $attributeClass));
		}
	}

	public function testClockEndpointsHaveMobileSecurityAttributes(): void {
		$clockMethods = ['clockIn', 'clockOut', 'startBreak', 'endBreak', 'enforceDailyMaximum'];
		$this->assertMethodsHaveAttribute(NoCSRFRequired::class, TimeTrackingController::class, $clockMethods);
		$this->assertMethodsHaveAttribute(BruteForceProtection::class, TimeTrackingController::class, $clockMethods);
		$this->assertMethodsHaveAttribute(UserRateLimit::class, TimeTrackingController::class, $clockMethods);
	}

	public function testAbsenceMutationEndpointsHaveNoCsrfRequired(): void {
		$this->assertMethodsHaveAttribute(NoCSRFRequired::class, AbsenceController::class, [
			'apiStore',
			'apiUpdate',
			'apiDelete',
			'cancel',
			'shorten',
			'approve',
			'reject',
		]);
	}

	public function testManagerMutationEndpointsHaveNoCsrfRequired(): void {
		$this->assertMethodsHaveAttribute(NoCSRFRequired::class, ManagerController::class, [
			'approveAbsence',
			'rejectAbsence',
			'approveTimeEntryCorrection',
			'rejectTimeEntryCorrection',
			'correctTimeEntry',
			'createEmployeeAbsence',
			'createEmployeeTimeEntry',
		]);
	}

	public function testSubstituteEndpointsHaveNoCsrfRequired(): void {
		$this->assertMethodsHaveAttribute(NoCSRFRequired::class, SubstituteController::class, [
			'getPending',
			'approve',
			'decline',
		]);
	}
}
