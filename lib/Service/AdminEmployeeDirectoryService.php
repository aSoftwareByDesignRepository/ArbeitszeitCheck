<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Exception\InvalidEmployeeListFilterException;
use OCA\ArbeitszeitCheck\Support\UserDirectorySearch;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Resolves admin employee directory pages: scan → access filter → optional active_today → sort → slice.
 */
final class AdminEmployeeDirectoryService
{
	public const FILTER_ALL = 'all';
	public const FILTER_APP_ACCESS = 'app_access';

	private const MAX_SEARCH_LENGTH = 200;

	public function __construct(
		private readonly IUserManager $userManager,
		private readonly PermissionService $permissionService,
		private readonly \OCA\ArbeitszeitCheck\Db\TimeEntryMapper $timeEntryMapper,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
	}

	public function resolveDefaultFilter(): string
	{
		return $this->permissionService->isAccessRestrictionEnabled()
			? self::FILTER_APP_ACCESS
			: self::FILTER_ALL;
	}

	public function isValidFilter(string $filter): bool
	{
		return in_array($filter, [self::FILTER_ALL, self::FILTER_APP_ACCESS], true);
	}

	/**
	 * @param string|null $filter Raw request value; null/empty uses {@see resolveDefaultFilter()}.
	 *
	 * @return array{
	 *   users: list<IUser>,
	 *   total: int,
	 *   directoryTotal: int|null,
	 *   hiddenCount: int|null,
	 *   offset: int,
	 *   limit: int,
	 *   truncated: bool,
	 *   filter: string,
	 *   defaultFilter: string,
	 *   activeToday: bool
	 * }
	 */
	public function listUsers(
		?string $filter,
		string $search,
		int $limit,
		int $offset,
		bool $activeTodayOnly = false,
	): array {
		$defaultFilter = $this->resolveDefaultFilter();
		$normalizedFilter = $filter !== null ? strtolower(trim($filter)) : '';
		if ($normalizedFilter === '') {
			$normalizedFilter = $defaultFilter;
		}
		if (!$this->isValidFilter($normalizedFilter)) {
			throw new InvalidEmployeeListFilterException(
				$this->l10n->t('Invalid employee list filter.')
			);
		}

		$searchTerm = trim($search);
		if (mb_strlen($searchTerm) > self::MAX_SEARCH_LENGTH) {
			$searchTerm = mb_substr($searchTerm, 0, self::MAX_SEARCH_LENGTH);
		}

		$limit = max(1, min($limit, Constants::MAX_LIST_LIMIT));
		$offset = max(0, $offset);

		$scanCap = Constants::ADMIN_EMPLOYEE_FILTER_MAX_SCAN;
		$candidateResult = $this->collectCandidates($searchTerm, $scanCap);
		/** @var list<IUser> $candidates */
		$candidates = $candidateResult['users'];
		$scanTruncated = $candidateResult['truncated'];

		$afterAccessFilter = [];
		foreach ($candidates as $user) {
			if ($this->matchesAccessFilter((string)$user->getUID(), $normalizedFilter)) {
				$afterAccessFilter[] = $user;
			}
		}

		$directoryTotal = null;
		$hiddenCount = null;
		if ($this->permissionService->isAccessRestrictionEnabled()
			&& $normalizedFilter === self::FILTER_APP_ACCESS) {
			$directoryTotal = count($candidates);
			$hiddenCount = max(0, $directoryTotal - count($afterAccessFilter));
		}

		$filtered = $afterAccessFilter;
		if ($activeTodayOnly) {
			$today = new \DateTime();
			$today->setTime(0, 0, 0);
			$activeTodayLookup = array_fill_keys(
				$this->timeEntryMapper->findDistinctUserIdsByDate($today),
				true
			);
			$filtered = array_values(array_filter(
				$filtered,
				static fn (IUser $user): bool => isset($activeTodayLookup[(string)$user->getUID()])
			));
		}

		usort($filtered, static function (IUser $a, IUser $b): int {
			$byName = strcasecmp((string)$a->getDisplayName(), (string)$b->getDisplayName());
			if ($byName !== 0) {
				return $byName;
			}
			return strcmp((string)$a->getUID(), (string)$b->getUID());
		});

		$total = count($filtered);
		$page = array_slice($filtered, $offset, $limit);

		if ($scanTruncated) {
			$this->logger->warning('admin_employee_list_scan_truncated', [
				'filter' => $normalizedFilter,
				'search' => $searchTerm,
				'scanCap' => $scanCap,
				'directoryTotal' => $directoryTotal ?? count($candidates),
			]);
		}

		return [
			'users' => $page,
			'total' => $total,
			'directoryTotal' => $directoryTotal,
			'hiddenCount' => $hiddenCount,
			'offset' => $offset,
			'limit' => $limit,
			'truncated' => $scanTruncated,
			'filter' => $normalizedFilter,
			'defaultFilter' => $defaultFilter,
			'activeToday' => $activeTodayOnly,
		];
	}

	private function matchesAccessFilter(string $userId, string $filter): bool
	{
		if ($filter === self::FILTER_ALL) {
			return true;
		}
		return $this->permissionService->isUserAllowedByAccessGroups($userId);
	}

	/**
	 * @return array{users: list<IUser>, truncated: bool}
	 */
	private function collectCandidates(string $searchTerm, int $scanCap): array
	{
		if ($searchTerm !== '') {
			$byId = (array)$this->userManager->search($searchTerm, $scanCap + 1, 0);
			$byDisplayName = (array)$this->userManager->searchDisplayName($searchTerm, $scanCap + 1, 0);
			$merged = UserDirectorySearch::mergeUnique($byId, $byDisplayName);
			$backendCapped = count($byId) >= $scanCap + 1 || count($byDisplayName) >= $scanCap + 1;
			$truncated = $backendCapped || count($merged) > $scanCap;
			if (count($merged) > $scanCap) {
				$merged = array_slice($merged, 0, $scanCap);
			}
			return ['users' => $merged, 'truncated' => $truncated];
		}

		$users = (array)$this->userManager->search('', $scanCap + 1, 0);
		$truncated = count($users) > $scanCap;
		if ($truncated) {
			$users = array_slice($users, 0, $scanCap);
		}
		return ['users' => $users, 'truncated' => $truncated];
	}
}
