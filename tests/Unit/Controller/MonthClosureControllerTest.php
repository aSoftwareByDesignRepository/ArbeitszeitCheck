<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Controller\MonthClosureController;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\MonthClosure;
use OCA\ArbeitszeitCheck\Service\MonthClosureService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MonthClosureControllerTest extends TestCase
{
	private function makeController(
		IRequest $request,
		IUserSession $userSession,
		MonthClosureService $monthClosureService,
		PermissionService $permissionService,
		IConfig $config,
		IL10N $l10n,
		IUserManager $userManager,
		AuditLogMapper $auditLogMapper,
		LoggerInterface $logger
	): MonthClosureController {
		return new MonthClosureController(
			'arbeitszeitcheck',
			$request,
			$userSession,
			$monthClosureService,
			$permissionService,
			$config,
			$l10n,
			$userManager,
			$auditLogMapper,
			$logger
		);
	}

	private function sessionUser(string $uid): IUserSession
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn('Actor Display');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return $session;
	}

	public function testFinalizedMonthsSelf(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static function (string $name, $default = null) {
			return $name === 'userId' ? '' : $default;
		});

		$mcs = $this->createMock(MonthClosureService::class);
		$mcs->expects($this->once())->method('listFinalizedYearMonthsForUser')->with('alice')->willReturn([
			['year' => 2026, 'month' => 3],
		]);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('1');

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$ps = $this->createMock(PermissionService::class);

		$um = $this->createMock(IUserManager::class);
		$audit = $this->createMock(AuditLogMapper::class);
		$logger = $this->createMock(LoggerInterface::class);

		$c = $this->makeController($request, $this->sessionUser('alice'), $mcs, $ps, $config, $l10n, $um, $audit, $logger);
		$res = $c->finalizedMonths();
		$this->assertInstanceOf(JSONResponse::class, $res);
		/** @var array $data */
		$data = $res->getData();
		$this->assertTrue($data['success']);
		$this->assertSame(2026, $data['months'][0]['year']);
		$this->assertSame(3, $data['months'][0]['month']);
	}

	public function testFinalizedMonthsOtherUserForbidden(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static function (string $name, $default = null) {
			return $name === 'userId' ? 'bob' : $default;
		});

		$mcs = $this->createMock(MonthClosureService::class);
		$mcs->expects($this->never())->method('listFinalizedYearMonthsForUser');

		$config = $this->createMock(IConfig::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$ps = $this->createMock(PermissionService::class);
		$ps->expects($this->once())->method('canManageEmployee')->with('alice', 'bob')->willReturn(false);
		$ps->expects($this->once())->method('logPermissionDenied');

		$bob = $this->createMock(IUser::class);
		$um = $this->createMock(IUserManager::class);
		$um->method('get')->with('bob')->willReturn($bob);

		$audit = $this->createMock(AuditLogMapper::class);
		$logger = $this->createMock(LoggerInterface::class);

		$c = $this->makeController($request, $this->sessionUser('alice'), $mcs, $ps, $config, $l10n, $um, $audit, $logger);
		$res = $c->finalizedMonths();
		$this->assertInstanceOf(JSONResponse::class, $res);
		$this->assertSame(Http::STATUS_FORBIDDEN, $res->getStatus());
	}

	public function testFinalizedMonthsUnknownUser(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static function (string $name, $default = null) {
			return $name === 'userId' ? 'nobody' : $default;
		});

		$mcs = $this->createMock(MonthClosureService::class);
		$config = $this->createMock(IConfig::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$ps = $this->createMock(PermissionService::class);
		$um = $this->createMock(IUserManager::class);
		$um->method('get')->with('nobody')->willReturn(null);
		$audit = $this->createMock(AuditLogMapper::class);
		$logger = $this->createMock(LoggerInterface::class);

		$c = $this->makeController($request, $this->sessionUser('alice'), $mcs, $ps, $config, $l10n, $um, $audit, $logger);
		$res = $c->finalizedMonths();
		$this->assertInstanceOf(JSONResponse::class, $res);
		$this->assertSame(Http::STATUS_NOT_FOUND, $res->getStatus());
	}

	public function testPdfSelfUsesBuildPdfContent(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static function (string $name, $default = null) {
			if ($name === 'year') {
				return '2026';
			}
			if ($name === 'month') {
				return '3';
			}
			if ($name === 'userId') {
				return '';
			}
			return $default;
		});

		$mcs = $this->createMock(MonthClosureService::class);
		$mcs->expects($this->once())->method('buildPdfContent')->with(
			'alice',
			2026,
			3,
			'Actor Display',
			$this->anything()
		)->willReturn('%PDF-1.4 fake');
		$mcs->method('getClosureRow')->willReturn(null);

		$config = $this->createMock(IConfig::class);
		$l10n = $this->createMock(IL10N::class);
		$ps = $this->createMock(PermissionService::class);
		$um = $this->createMock(IUserManager::class);
		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects($this->never())->method('logAction');
		$logger = $this->createMock(LoggerInterface::class);

		$c = $this->makeController($request, $this->sessionUser('alice'), $mcs, $ps, $config, $l10n, $um, $audit, $logger);
		$res = $c->pdf();
		$this->assertInstanceOf(DataDownloadResponse::class, $res);
	}

	public function testPdfDelegatedCallsAudit(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static function (string $name, $default = null) {
			if ($name === 'year') {
				return '2026';
			}
			if ($name === 'month') {
				return '3';
			}
			if ($name === 'userId') {
				return 'bob';
			}
			return $default;
		});

		$row = new MonthClosure();
		$row->setId(99);
		$row->setUserId('bob');

		$mcs = $this->createMock(MonthClosureService::class);
		$mcs->expects($this->once())->method('buildPdfContent')->with(
			'bob',
			2026,
			3,
			'Bob Name',
			$this->anything()
		)->willReturn('%PDF-1.4 fake');
		$mcs->method('getClosureRow')->with('bob', 2026, 3)->willReturn($row);

		$config = $this->createMock(IConfig::class);
		$l10n = $this->createMock(IL10N::class);
		$ps = $this->createMock(PermissionService::class);
		$ps->method('canManageEmployee')->with('alice', 'bob')->willReturn(true);

		$bob = $this->createMock(IUser::class);
		$um = $this->createMock(IUserManager::class);
		$um->method('get')->with('bob')->willReturn($bob);
		$um->method('getDisplayName')->with('bob')->willReturn('Bob Name');

		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects($this->once())->method('logAction')->with(
			'bob',
			'month_closure_pdf_downloaded',
			'month_closure',
			99,
			null,
			$this->anything(),
			'alice'
		);

		$logger = $this->createMock(LoggerInterface::class);

		$c = $this->makeController($request, $this->sessionUser('alice'), $mcs, $ps, $config, $l10n, $um, $audit, $logger);
		$res = $c->pdf();
		$this->assertInstanceOf(DataDownloadResponse::class, $res);
	}
}
