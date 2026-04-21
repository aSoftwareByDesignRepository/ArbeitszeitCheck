<?php

declare(strict_types=1);

/**
 * Export controller for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolationMapper;
use OCA\ArbeitszeitCheck\Service\DatevExportService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TimeEntryExportTransformer;
use OCA\ArbeitszeitCheck\Constants;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IL10N;
use OCP\IConfig;

/**
 * ExportController
 */
class ExportController extends Controller
{
	private TimeEntryMapper $timeEntryMapper;
	private AbsenceMapper $absenceMapper;
	private ComplianceViolationMapper $violationMapper;
	private DatevExportService $datevExportService;
	private TimeEntryExportTransformer $timeEntryExportTransformer;
	private IUserSession $userSession;
	private IL10N $l10n;
	private IConfig $config;
	private PermissionService $permissionService;

	public function __construct(
		string $appName,
		IRequest $request,
		TimeEntryMapper $timeEntryMapper,
		AbsenceMapper $absenceMapper,
		ComplianceViolationMapper $violationMapper,
		DatevExportService $datevExportService,
		TimeEntryExportTransformer $timeEntryExportTransformer,
		IUserSession $userSession,
		IL10N $l10n,
		IConfig $config,
		PermissionService $permissionService
	) {
		parent::__construct($appName, $request);
		$this->timeEntryMapper = $timeEntryMapper;
		$this->absenceMapper = $absenceMapper;
		$this->violationMapper = $violationMapper;
		$this->datevExportService = $datevExportService;
		$this->timeEntryExportTransformer = $timeEntryExportTransformer;
		$this->userSession = $userSession;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->permissionService = $permissionService;
	}

	/**
	 * Export time entries
	 *
	 * @param string $format Format: csv, json, pdf, datev
	 * @param string|null $startDate Start date (Y-m-d format)
	 * @param string|null $endDate End date (Y-m-d format)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	/**
	 * @return DataDownloadResponse|JSONResponse
	 */
	public function timeEntries(string $format = 'csv', ?string $startDate = null, ?string $endDate = null): DataDownloadResponse|JSONResponse
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				throw new \Exception($this->l10n->t('User not authenticated'));
			}

			$userId = $user->getUID();

		// Determine date range (default to last 30 days if not specified)
		$end = $endDate ? new \DateTime($endDate) : new \DateTime();
		// Normalise to midnight of the end date for range calculations and display.
		$end->setTime(0, 0, 0);
		$start = $startDate ? new \DateTime($startDate) : clone $end;
		if (!$startDate) {
			$start->modify('-30 days');
		}
		$start->setTime(0, 0, 0);

		if ($start > $end) {
			throw new \Exception($this->l10n->t('Start date must be before or equal to end date'));
		}

		// Enforce max date range to prevent heavy queries (midnight-to-midnight is exact).
		$diff = $end->diff($start);
		$days = (int) $diff->format('%a');
		if ($days > Constants::MAX_EXPORT_DATE_RANGE_DAYS) {
			throw new \Exception($this->l10n->t(
				'Export date range must not exceed %d days. Please narrow the range.',
				[Constants::MAX_EXPORT_DATE_RANGE_DAYS]
			));
		}

		// Exclusive upper bound for DB query: start of next day (findByUserAndDateRange uses strict <).
		$endExclusive = (clone $end)->modify('+1 day');

		// Get time entries from database
		$entries = $this->timeEntryMapper->findByUserAndDateRange($userId, $start, $endExclusive);

			// Decide whether to virtually split entries that span midnight in CSV/JSON export
			$enableMidnightSplit = $this->config->getAppValue('arbeitszeitcheck', 'export_midnight_split_enabled', '1') === '1';

			$layoutParam = (string)$this->request->getParam('layout', 'long');
			$layout = in_array($layoutParam, ['long', 'wide'], true) ? $layoutParam : 'long';

			$data = [];
			foreach ($entries as $entry) {
				try {
					foreach ($this->timeEntryExportTransformer->entryToExportRows($entry, $enableMidnightSplit) as $row) {
						$data[] = $row;
					}
				} catch (\Throwable $e) {
					\OCP\Log\logger('arbeitszeitcheck')->error('Error processing time entry ' . $entry->getId() . ' in export: ' . $e->getMessage(), ["exception" => $e]);
				}
			}

			if ($layout === 'wide' && ($format === 'csv' || $format === 'json')) {
				$displayName = $user->getDisplayName();
				foreach ($data as &$r) {
					$r['user_id'] = $userId;
					$r['display_name'] = $displayName;
				}
				unset($r);
				$l10n = $this->l10n;
				$data = $this->timeEntryExportTransformer->longExportRowsToWideDaily(
					$data,
					static function (string $dateStr) use ($l10n): string {
						$keys = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
						$n = (int)(new \DateTime($dateStr))->format('N');
						return $l10n->t($keys[$n - 1]);
					}
				);
			}

			$filename = 'time-entries-' . date('Y-m-d') . '.' . $format;

			if ($format === 'pdf') {
				return new JSONResponse([
					'error' => $this->l10n->t('PDF export is not available. Please use CSV.')
				], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$timezone = $this->getExportTimezone();
			return match($format) {
				'csv' => $this->exportAsCsv($data, $filename, $timezone),
				'json' => $this->exportAsJson($data, $filename, $timezone),
				'datev' => $this->exportAsDatev($userId, $start, $end),
				default => $this->exportAsCsv($data, $filename, $timezone)
			};
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in ExportController::timeEntries: ' . $e->getMessage(), ["exception" => $e]);
			// Return error as CSV with error message
			$errorData = [['error' => $this->l10n->t('Export failed: %s', [$e->getMessage()])]];
			return $this->exportAsCsv($errorData, 'time-entries-export-error-' . date('Y-m-d') . '.csv', $this->getExportTimezone());
		}
	}

	/**
	 * Export absences
	 *
	 * @param string $format Format: csv, json, pdf
	 * @param string|null $startDate Start date (Y-m-d format)
	 * @param string|null $endDate End date (Y-m-d format)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	/**
	 * @return DataDownloadResponse|JSONResponse
	 */
	public function absences(string $format = 'csv', ?string $startDate = null, ?string $endDate = null): DataDownloadResponse|JSONResponse
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				throw new \Exception($this->l10n->t('User not authenticated'));
			}

			$userId = $user->getUID();

		// Determine date range (default to last year if not specified)
		$end = $endDate ? new \DateTime($endDate) : new \DateTime();
		$end->setTime(0, 0, 0);
		$start = $startDate ? new \DateTime($startDate) : clone $end;
		if (!$startDate) {
			$start->modify('-1 year');
		}
		$start->setTime(0, 0, 0);

		if ($start > $end) {
			throw new \Exception($this->l10n->t('Start date must be before or equal to end date'));
		}

		// Enforce max date range (midnight-to-midnight gives exact day count).
		$diff = $end->diff($start);
		$days = (int) $diff->format('%a');
		if ($days > Constants::MAX_EXPORT_DATE_RANGE_DAYS) {
			throw new \Exception($this->l10n->t(
				'Export date range must not exceed %d days. Please narrow the range.',
				[Constants::MAX_EXPORT_DATE_RANGE_DAYS]
			));
		}

			// Get absences from database
			$absences = $this->absenceMapper->findByUserAndDateRange($userId, $start, $end);

			// Convert to array format
			$data = [];
			foreach ($absences as $absence) {
				try {
					$startDate = $absence->getStartDate();
					$endDate = $absence->getEndDate();
					$createdAt = $absence->getCreatedAt();
					$data[] = [
						'id' => $absence->getId(),
						'type' => $absence->getType(),
						'start_date' => $startDate ? $startDate->format('Y-m-d') : '',
						'end_date' => $endDate ? $endDate->format('Y-m-d') : '',
						'days' => $absence->getDays(),
						'reason' => $absence->getReason() ?? '',
						'status' => $absence->getStatus(),
						'approver_comment' => $absence->getApproverComment() ?? '',
						'approved_at' => $absence->getApprovedAt() ? $absence->getApprovedAt()->format('Y-m-d H:i:s') : '',
						'created_at' => $createdAt ? $createdAt->format('Y-m-d H:i:s') : ''
					];
				} catch (\Throwable $e) {
					\OCP\Log\logger('arbeitszeitcheck')->error('Error processing absence ' . $absence->getId() . ' in export: ' . $e->getMessage(), ["exception" => $e]);
					continue;
				}
			}

			$filename = 'absences-' . date('Y-m-d') . '.' . $format;

			if ($format === 'pdf') {
				return new JSONResponse([
					'error' => $this->l10n->t('PDF export is not available. Please use CSV.')
				], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$timezone = $this->getExportTimezone();
			return match($format) {
				'csv' => $this->exportAsCsv($data, $filename, $timezone),
				'json' => $this->exportAsJson($data, $filename, $timezone),
				default => $this->exportAsCsv($data, $filename, $timezone)
			};
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in ExportController::absences: ' . $e->getMessage(), ["exception" => $e]);
			// Return error as CSV with error message
			$errorData = [['error' => $this->l10n->t('Export failed: %s', [$e->getMessage()])]];
			return $this->exportAsCsv($errorData, 'absences-export-error-' . date('Y-m-d') . '.csv', $this->getExportTimezone());
		}
	}

	/**
	 * Export compliance reports
	 *
	 * @param string $format Format: csv, json
	 * @param string|null $startDate Start date (Y-m-d format)
	 * @param string|null $endDate End date (Y-m-d format)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function compliance(string $format = 'csv', ?string $startDate = null, ?string $endDate = null): DataDownloadResponse
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				throw new \Exception($this->l10n->t('User not authenticated'));
			}

			$userId = $user->getUID();

		// Determine date range (default to last 30 days if not specified)
		$end = $endDate ? new \DateTime($endDate) : new \DateTime();
		$end->setTime(0, 0, 0);
		$start = $startDate ? new \DateTime($startDate) : clone $end;
		if (!$startDate) {
			$start->modify('-30 days');
		}
		$start->setTime(0, 0, 0);

		if ($start > $end) {
			throw new \Exception($this->l10n->t('Start date must be before or equal to end date'));
		}

		// Enforce max date range (midnight-to-midnight gives exact day count).
		$diff = $end->diff($start);
		$days = (int) $diff->format('%a');
		if ($days > Constants::MAX_EXPORT_DATE_RANGE_DAYS) {
			throw new \Exception($this->l10n->t(
				'Export date range must not exceed %d days. Please narrow the range.',
				[Constants::MAX_EXPORT_DATE_RANGE_DAYS]
			));
		}

		// Get compliance violations for user from database
			$violations = $this->violationMapper->findByDateRange($start, $end, $userId);

			// Convert to array format
			$data = [];
			foreach ($violations as $violation) {
				try {
					$date = $violation->getDate();
					if (!$date) {
						continue; // Skip violations with no date
					}
					$data[] = [
						'id' => $violation->getId(),
						'date' => $date->format('Y-m-d'),
						'violation_type' => $violation->getViolationType(),
						'description' => $violation->getDescription(),
						'severity' => $violation->getSeverity(),
						'resolved' => $violation->getResolved() ? 'Yes' : 'No',
						'resolved_at' => $violation->getResolvedAt() ? $violation->getResolvedAt()->format('Y-m-d H:i:s') : '',
						'time_entry_id' => $violation->getTimeEntryId()
					];
				} catch (\Throwable $e) {
					\OCP\Log\logger('arbeitszeitcheck')->error('Error processing violation ' . $violation->getId() . ' in export: ' . $e->getMessage(), ["exception" => $e]);
					continue;
				}
			}

			$filename = 'compliance-report-' . date('Y-m-d') . '.' . $format;

			$timezone = $this->getExportTimezone();
			return match($format) {
				'csv' => $this->exportAsCsv($data, $filename, $timezone),
				'json' => $this->exportAsJson($data, $filename, $timezone),
				default => $this->exportAsCsv($data, $filename, $timezone)
			};
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in ExportController::compliance: ' . $e->getMessage(), ["exception" => $e]);
			// Return error as CSV with error message
			$errorData = [['error' => $this->l10n->t('Export failed: %s', [$e->getMessage()])]];
			return $this->exportAsCsv($errorData, 'compliance-export-error-' . date('Y-m-d') . '.csv', $this->getExportTimezone());
		}
	}

	/**
	 * Export data as CSV
	 *
	 * @param array $data Data to export
	 * @param string $filename Filename
	 * @return DataDownloadResponse
	 */
	private function exportAsCsv(array $data, string $filename, string $timezone): DataDownloadResponse
	{
		$fp = fopen('php://temp', 'r+');
		fputcsv($fp, ['# ' . $this->l10n->t('Timezone'), $timezone]);
		fputcsv($fp, ['# ' . $this->l10n->t('Exported at'), (new \DateTime('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i:s T')]);

		if (!empty($data)) {
			fputcsv($fp, array_keys($data[0]));
			foreach ($data as $row) {
				fputcsv($fp, $row);
			}
		} else {
			fputcsv($fp, ['message', 'No data available']);
		}
		rewind($fp);
		$csv = stream_get_contents($fp);
		fclose($fp);

		return new DataDownloadResponse($csv, $filename, 'text/csv; charset=utf-8');
	}

	/**
	 * Export data as JSON
	 *
	 * @param array $data Data to export
	 * @param string $filename Filename
	 * @return DataDownloadResponse
	 */
	private function exportAsJson(array $data, string $filename, string $timezone): DataDownloadResponse
	{
		$json = json_encode([
			'timezone' => $timezone,
			'exported_at' => (new \DateTime('now', new \DateTimeZone($timezone)))->format('c'),
			'record_count' => count($data),
			'entries' => $data,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		
		if ($json === false) {
			throw new \Exception($this->l10n->t('Failed to encode data as JSON'));
		}

		return new DataDownloadResponse($json, $filename, 'application/json; charset=utf-8');
	}

	/**
	 * Export data as PDF (simple text-based PDF)
	 * Note: For production, consider using a PDF library like TCPDF or FPDF
	 *
	 * @param array $data Data to export
	 * @param string $filename Filename
	 * @param string $title Report title
	 * @return DataDownloadResponse
	 */
	private function exportAsPdf(array $data, string $filename, string $title): DataDownloadResponse
	{
		// For now, export as CSV since PDF generation requires external libraries
		// In production, this should use a proper PDF library
		// This is a workaround that provides the data in a usable format
		return $this->exportAsCsv($data, str_replace('.pdf', '.csv', $filename), $this->getExportTimezone());
	}

	/**
	 * Export time entries in DATEV format
	 *
	 * @param string $userId User ID
	 * @param \DateTime $startDate Start date
	 * @param \DateTime $endDate End date
	 * @return DataDownloadResponse
	 */
	private function exportAsDatev(string $userId, \DateTime $startDate, \DateTime $endDate): DataDownloadResponse
	{
		try {
			$content = $this->datevExportService->exportTimeEntries($userId, $startDate, $endDate);
			$filename = 'datev-export-' . date('Y-m-d') . '.txt';
			
			return new DataDownloadResponse($content, $filename, 'text/plain; charset=iso-8859-1');
		} catch (\Throwable $e) {
			// Return error as CSV with error message
			$errorData = [['error' => $e->getMessage()]];
			return $this->exportAsCsv($errorData, 'datev-export-error-' . date('Y-m-d') . '.csv', $this->getExportTimezone());
		}
	}

	private function getExportTimezone(): string
	{
		return $this->timeEntryExportTransformer->getTimezone();
	}

	/**
	 * Export time entries in DATEV format
	 *
	 * @param string|null $startDate Start date (Y-m-d format)
	 * @param string|null $endDate End date (Y-m-d format)
	 * @return DataDownloadResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function datev(?string $startDate = null, ?string $endDate = null): DataDownloadResponse
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				throw new \Exception($this->l10n->t('User not authenticated'));
			}

			$userId = $user->getUID();

		// Determine date range (default to last 30 days if not specified)
		$end = $endDate ? new \DateTime($endDate) : new \DateTime();
		$end->setTime(0, 0, 0);
		$start = $startDate ? new \DateTime($startDate) : clone $end;
		if (!$startDate) {
			$start->modify('-30 days');
		}
		$start->setTime(0, 0, 0);

		if ($start > $end) {
			throw new \Exception($this->l10n->t('Start date must be before or equal to end date'));
		}

		// Enforce max date range (midnight-to-midnight gives exact day count).
		$diff = $end->diff($start);
		$days = (int) $diff->format('%a');
		if ($days > Constants::MAX_EXPORT_DATE_RANGE_DAYS) {
			throw new \Exception($this->l10n->t(
				'Export date range must not exceed %d days. Please narrow the range.',
				[Constants::MAX_EXPORT_DATE_RANGE_DAYS]
			));
		}

		// Exclusive upper bound: start of next day (DATEV service uses strict < comparisons).
		$endExclusive = (clone $end)->modify('+1 day');

		// Use the existing DATEV export method
		return $this->exportAsDatev($userId, $start, $endExclusive);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in ExportController::datev: ' . $e->getMessage(), ["exception" => $e]);
			// Return error as CSV with error message
			$errorData = [['error' => $e->getMessage()]];
			return $this->exportAsCsv($errorData, 'datev-export-error-' . date('Y-m-d') . '.csv', $this->getExportTimezone());
		}
	}

	/**
	 * Get DATEV export configuration status
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function datevConfig(): JSONResponse
	{
		try {
			$user = $this->userSession->getUser();
			if ($user === null) {
				return new JSONResponse([
					'success' => false,
					'error' => $this->l10n->t('User not authenticated')
				], Http::STATUS_UNAUTHORIZED);
			}
			$userId = $user->getUID();
			if (!$this->permissionService->isAdmin($userId)) {
				$this->permissionService->logPermissionDenied($userId, 'read_datev_config', 'datev_config');
				return new JSONResponse([
					'success' => false,
					'error' => $this->l10n->t('Access denied')
				], Http::STATUS_FORBIDDEN);
			}
			$status = $this->datevExportService->getConfigurationStatus();
			return new JSONResponse([
				'success' => true,
				'config' => $status
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('Export failed: %s', [$e->getMessage()])
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}