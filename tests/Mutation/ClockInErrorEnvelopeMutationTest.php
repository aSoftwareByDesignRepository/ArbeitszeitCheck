<?php

declare(strict_types=1);

/**
 * Mutation-style assertions for clock-in JSON error envelopes.
 *
 * Proves clients can always read a localized `error`/`message` plus stable
 * `error_code` for business-rule failures (mobile must never fall back to
 * opaque "Request failed (400)" when the server answered with JSON).
 */

namespace OCA\ArbeitszeitCheck\Tests\Mutation;

use OCA\ArbeitszeitCheck\BusinessRuleCode;
use OCA\ArbeitszeitCheck\Controller\TimeTrackingController;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class ClockInErrorEnvelopeMutationTest extends TestCase
{
	public function testBusinessRuleFailureIncludesErrorCodeAndMessageDuplicate(): void
	{
		$service = $this->createMock(TimeTrackingService::class);
		$userSession = $this->createMock(IUserSession::class);
		$request = $this->createMock(IRequest::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn ($s) => $s);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession->method('getUser')->willReturn($user);

		$service->method('clockIn')->willThrowException(new BusinessRuleException(
			'Cannot clock in: Maximum daily working hours (10h) already reached.',
			BusinessRuleCode::DAILY_HOURS_LIMIT,
		));

		$controller = new TimeTrackingController(
			'arbeitszeitcheck',
			$request,
			$service,
			$userSession,
			$l10n,
		);

		$response = $controller->clockIn();
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertArrayHasKey('error', $data);
		$this->assertArrayHasKey('message', $data);
		$this->assertSame($data['error'], $data['message']);
		$this->assertSame(BusinessRuleCode::DAILY_HOURS_LIMIT, $data['error_code']);
		$this->assertStringContainsString('Maximum daily working hours', $data['error']);
		$this->assertArrayNotHasKey('error_details', $data);
	}

	public function testRestPeriodFailureIncludesLocalizableErrorDetails(): void
	{
		$service = $this->createMock(TimeTrackingService::class);
		$userSession = $this->createMock(IUserSession::class);
		$request = $this->createMock(IRequest::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn ($s) => $s);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession->method('getUser')->willReturn($user);

		$service->method('clockIn')->willThrowException(new BusinessRuleException(
			'Minimum 11-hour rest period required between shifts (ArbZG §5). Your last shift ended on 17.08.2026 at 18:35. You can clock in after 18.08.2026 05:35 (in 10.8 hours).',
			BusinessRuleCode::REST_PERIOD_REQUIRED,
			[
				'min_rest_hours' => 11,
				'law_label' => 'ArbZG §5',
				'last_end_date' => '17.08.2026',
				'last_end_clock' => '18:35',
				'earliest_clock_in' => '18.08.2026 05:35',
				'hours_remaining' => 10.8,
			],
		));

		$controller = new TimeTrackingController(
			'arbeitszeitcheck',
			$request,
			$service,
			$userSession,
			$l10n,
		);

		$data = $controller->clockIn()->getData();
		$this->assertSame(BusinessRuleCode::REST_PERIOD_REQUIRED, $data['error_code']);
		$this->assertSame(11, $data['error_details']['min_rest_hours']);
		$this->assertSame('ArbZG §5', $data['error_details']['law_label']);
		$this->assertSame('17.08.2026', $data['error_details']['last_end_date']);
		$this->assertSame('18:35', $data['error_details']['last_end_clock']);
		$this->assertSame('18.08.2026 05:35', $data['error_details']['earliest_clock_in']);
		$this->assertSame(10.8, $data['error_details']['hours_remaining']);
	}

	public function testBusinessRuleWithoutReasonCodeOmitsErrorCodeKey(): void
	{
		$service = $this->createMock(TimeTrackingService::class);
		$userSession = $this->createMock(IUserSession::class);
		$request = $this->createMock(IRequest::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn ($s) => $s);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession->method('getUser')->willReturn($user);

		$service->method('clockIn')->willThrowException(new BusinessRuleException('plain rule'));

		$controller = new TimeTrackingController(
			'arbeitszeitcheck',
			$request,
			$service,
			$userSession,
			$l10n,
		);

		$data = $controller->clockIn()->getData();
		$this->assertSame('plain rule', $data['error']);
		$this->assertArrayNotHasKey('error_code', $data);
	}
}
