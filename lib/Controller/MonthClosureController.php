<?php

declare(strict_types=1);

/**
 * API for revision-safe month closure (finalize, status, PDF, admin reopen).
 *
 * JSON POST bodies are not decoded before the CSRF middleware runs, so mutating routes use
 * {@see NoCSRFRequired} (same pattern as other app JSON APIs). Clients must still send a valid
 * session; the frontend includes `requesttoken` in headers where applicable.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Db\MonthClosure;
use OCA\ArbeitszeitCheck\Service\MonthClosureFeature;
use OCA\ArbeitszeitCheck\Service\MonthClosureService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IL10N;

class MonthClosureController extends Controller
{
	private IUserSession $userSession;
	private MonthClosureService $monthClosureService;
	private PermissionService $permissionService;
	private IConfig $config;
	private IL10N $l10n;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		MonthClosureService $monthClosureService,
		PermissionService $permissionService,
		IConfig $config,
		IL10N $l10n
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->monthClosureService = $monthClosureService;
		$this->permissionService = $permissionService;
		$this->config = $config;
		$this->l10n = $l10n;
	}

	private function uid(): string
	{
		$u = $this->userSession->getUser();
		if ($u === null) {
			throw new \RuntimeException('not_logged_in');
		}
		return $u->getUID();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function feature(): JSONResponse
	{
		return new JSONResponse([
			'enabled' => MonthClosureFeature::isEnabledFromIConfig($this->config),
			'graceDaysAfterEom' => $this->monthClosureService->getGraceDaysAfterEndOfMonth(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function status(): JSONResponse
	{
		try {
			$userId = $this->uid();
			$year = (int)$this->request->getParam('year', 0);
			$month = (int)$this->request->getParam('month', 0);
			if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Invalid month')], Http::STATUS_BAD_REQUEST);
			}
			$row = $this->monthClosureService->getClosureRow($userId, $year, $month);
			$grace = $this->monthClosureService->getGraceDaysAfterEndOfMonth();
			$deadline = $grace > 0 ? $this->monthClosureService->getManualFinalizeDeadlineDate($year, $month) : null;
			$featureEnabled = MonthClosureFeature::isEnabledFromIConfig($this->config);
			$isFinalized = $row !== null && $row->getStatus() === MonthClosure::STATUS_FINALIZED;

			$finalizeBlockedReason = null;
			$canFinalize = false;
			if (!$featureEnabled) {
				$finalizeBlockedReason = 'feature_disabled';
			} elseif ($isFinalized) {
				$canFinalize = false;
			} elseif ($this->monthClosureService->isCalendarMonthStrictlyAfterCurrent($year, $month)) {
				$finalizeBlockedReason = 'future_month';
			} elseif ($this->monthClosureService->monthBlocksFinalization($userId, $year, $month)) {
				$finalizeBlockedReason = 'pending_workflow';
			} else {
				$canFinalize = true;
			}

			$finalizeBlockedMessage = null;
			if ($finalizeBlockedReason !== null) {
				$finalizeBlockedMessage = match ($finalizeBlockedReason) {
					'feature_disabled' => $this->l10n->t('Month finalization is disabled by the administrator.'),
					'future_month' => $this->l10n->t('You can only finalize past or the current calendar month.'),
					'pending_workflow' => $this->l10n->t('Resolve pending time entry or absence approvals in this month before finalizing.'),
					default => null,
				};
			}

			return new JSONResponse([
				'success' => true,
				'featureEnabled' => $featureEnabled,
				'year' => $year,
				'month' => $month,
				'status' => $row ? $row->getStatus() : null,
				'version' => $row ? $row->getVersion() : null,
				'snapshotHash' => $row ? $row->getSnapshotHash() : null,
				'finalizedAt' => $row && $row->getFinalizedAt() ? $row->getFinalizedAt()->format(\DateTimeInterface::ATOM) : null,
				'finalizedBy' => $row ? $row->getFinalizedBy() : null,
				'reopenedAt' => $row && $row->getReopenedAt() ? $row->getReopenedAt()->format(\DateTimeInterface::ATOM) : null,
				'graceDaysAfterEom' => $grace,
				'manualFinalizeDeadline' => $deadline ? $deadline->format('Y-m-d') : null,
				'autoFinalized' => $row && $row->getFinalizedBy() === MonthClosureService::AUTO_FINALIZE_ACTOR_ID,
				'canFinalize' => $canFinalize,
				'finalizeBlockedReason' => $finalizeBlockedReason,
				'finalizeBlockedMessage' => $finalizeBlockedMessage,
			]);
		} catch (\RuntimeException $e) {
			if ($e->getMessage() === 'not_logged_in') {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Authentication required')], Http::STATUS_UNAUTHORIZED);
			}
			return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Error')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\Throwable $e) {
			return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Error')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function finalize(): JSONResponse
	{
		try {
			$userId = $this->uid();
			$params = $this->request->getParams();
			$year = (int)($params['year'] ?? 0);
			$month = (int)($params['month'] ?? 0);
			if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Invalid month')], Http::STATUS_BAD_REQUEST);
			}
			$this->monthClosureService->finalizeMonth($userId, $userId, $year, $month);
			return new JSONResponse(['success' => true]);
		} catch (\RuntimeException $e) {
			$code = $e->getMessage();
			$map = [
				'not_logged_in' => [Http::STATUS_UNAUTHORIZED, $this->l10n->t('Authentication required')],
				'feature_disabled' => [Http::STATUS_FORBIDDEN, $this->l10n->t('Month closure is disabled by the administrator.')],
				'forbidden' => [Http::STATUS_FORBIDDEN, $this->l10n->t('Access denied')],
				'already_finalized' => [Http::STATUS_CONFLICT, $this->l10n->t('This month is already finalized.')],
				'future_month' => [Http::STATUS_BAD_REQUEST, $this->l10n->t('You can only finalize past or the current calendar month.')],
				'pending_correction' => [Http::STATUS_CONFLICT, $this->l10n->t('Resolve pending time entry or absence approvals in this month before finalizing.')],
			];
			if (isset($map[$code])) {
				return new JSONResponse(['success' => false, 'error' => $map[$code][1]], $map[$code][0]);
			}
			return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Could not finalize month')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function pdf(): DataDownloadResponse|JSONResponse
	{
		try {
			$userId = $this->uid();
			$year = (int)$this->request->getParam('year', 0);
			$month = (int)$this->request->getParam('month', 0);
			if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Invalid month')], Http::STATUS_BAD_REQUEST);
			}
			$u = $this->userSession->getUser();
			$name = $u ? $u->getDisplayName() : $userId;
			$pdf = $this->monthClosureService->buildPdfContent($userId, $year, $month, $name);
			$fn = sprintf('arbeitszeitcheck-month-%04d-%02d.pdf', $year, $month);
			return new DataDownloadResponse($pdf, $fn, 'application/pdf');
		} catch (\RuntimeException $e) {
			if ($e->getMessage() === 'not_logged_in') {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Authentication required')], Http::STATUS_UNAUTHORIZED);
			}
			if ($e->getMessage() === 'not_finalized') {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Month is not finalized.')], Http::STATUS_NOT_FOUND);
			}
			return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Error')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function reopen(): JSONResponse
	{
		try {
			$adminId = $this->uid();
			if (!$this->permissionService->isAdmin($adminId)) {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Only administrators can reopen a finalized month.')], Http::STATUS_FORBIDDEN);
			}
			$params = $this->request->getParams();
			$year = (int)($params['year'] ?? 0);
			$month = (int)($params['month'] ?? 0);
			$targetUserId = isset($params['userId']) ? (string)$params['userId'] : '';
			if ($targetUserId === '') {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('User ID is required.')], Http::STATUS_BAD_REQUEST);
			}
			$reason = isset($params['reason']) ? trim((string)$params['reason']) : '';
			if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Invalid month')], Http::STATUS_BAD_REQUEST);
			}
			$this->monthClosureService->reopenMonth($adminId, $targetUserId, $year, $month, $reason);
			return new JSONResponse(['success' => true]);
		} catch (\RuntimeException $e) {
			$code = $e->getMessage();
			if ($code === 'not_logged_in') {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Authentication required')], Http::STATUS_UNAUTHORIZED);
			}
			if ($code === 'reason_required') {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('A reason is required.')], Http::STATUS_BAD_REQUEST);
			}
			if ($code === 'not_finalized') {
				return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Month is not finalized.')], Http::STATUS_NOT_FOUND);
			}
			return new JSONResponse(['success' => false, 'error' => $this->l10n->t('Error')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
