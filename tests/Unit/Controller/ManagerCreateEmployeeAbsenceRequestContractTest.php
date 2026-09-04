<?php

declare(strict_types=1);

/**
 * NC 34: ManagerController must never call protected Request::getContent().
 * JSON bodies are exposed via public IRequest::getParams() (same as createEmployeeTimeEntry).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

class ManagerCreateEmployeeAbsenceRequestContractTest extends TestCase
{
	private function managerControllerSource(): string
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/ManagerController.php');
		$this->assertNotFalse($src);
		return $src;
	}

	private function createEmployeeAbsenceMethodBody(): string
	{
		$src = $this->managerControllerSource();
		$this->assertMatchesRegularExpression(
			'/public function createEmployeeAbsence\(\): JSONResponse\s*\{/',
			$src
		);
		$start = strpos($src, 'public function createEmployeeAbsence(): JSONResponse');
		$this->assertNotFalse($start);
		$next = strpos($src, "\n\tpublic function ", $start + 10);
		$this->assertNotFalse($next, 'createEmployeeAbsence must be followed by another public method');
		return substr($src, $start, $next - $start);
	}

	public function testCreateEmployeeAbsenceUsesPublicGetParamsOnly(): void
	{
		$body = $this->createEmployeeAbsenceMethodBody();
		// Strip line comments so documentation mentioning the forbidden API does not false-positive.
		$code = preg_replace('/^\s*\/\/.*$/m', '', $body) ?? $body;
		$this->assertStringContainsString('$this->request->getParams()', $code);
		$this->assertStringNotContainsString('->getContent(', $code);
		$this->assertStringNotContainsString("php://input", $code);
		$this->assertStringNotContainsString('json_decode($raw', $code);
	}

	public function testCreateEmployeeAbsenceAlignsWithTimeEntryParamStyle(): void
	{
		$src = $this->managerControllerSource();
		$this->assertStringContainsString('function createEmployeeTimeEntry(): JSONResponse', $src);
		$this->assertStringContainsString(
			'$params = $this->request->getParams();',
			$src,
			'createEmployeeTimeEntry is the reference public-API pattern'
		);
	}

	public function testCreateEmployeeAbsenceKeepsAclAndApprovedServicePath(): void
	{
		$body = $this->createEmployeeAbsenceMethodBody();
		$this->assertStringContainsString('canManageEmployee', $body);
		$this->assertStringContainsString('createApprovedAbsenceForEmployeeByManager', $body);
		$this->assertStringContainsString('assertDateRangeMutable', $body);
		$this->assertStringContainsString('requestParamString', $body);
	}

	public function testManagerAbsencesClientPostsJsonKeysExpectedByController(): void
	{
		$js = file_get_contents(dirname(__DIR__, 3) . '/js/manager-absences.js');
		$this->assertNotFalse($js);
		$this->assertStringContainsString("/apps/arbeitszeitcheck/api/manager/employee-absences", $js);
		$this->assertStringContainsString('userId', $js);
		$this->assertStringContainsString('startDate', $js);
		$this->assertStringContainsString('endDate', $js);
		$this->assertStringContainsString('durationHours', $js);
		$this->assertStringContainsString('requireDurationHours', $js);
		$this->assertStringContainsString('serverMayFillHours', $js);
	}

	public function testRouteWiresPostCreateEmployeeAbsence(): void
	{
		$routes = file_get_contents(dirname(__DIR__, 3) . '/appinfo/routes.php');
		$this->assertNotFalse($routes);
		$this->assertStringContainsString("manager#createEmployeeAbsence", $routes);
		$this->assertMatchesRegularExpression(
			"/'name'\\s*=>\\s*'manager#createEmployeeAbsence'[\\s\\S]*?'verb'\\s*=>\\s*'POST'/",
			$routes
		);
	}
}
