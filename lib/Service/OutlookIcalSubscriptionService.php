<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\OutlookIcalSubscriptionToken;
use OCA\ArbeitszeitCheck\Db\OutlookIcalSubscriptionTokenMapper;
use OCA\ArbeitszeitCheck\Db\TeamMapper;
use OCA\ArbeitszeitCheck\Db\TeamMemberMapper;
use OCA\ArbeitszeitCheck\Db\TeamManagerMapper;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionAuthException;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionBadRequestException;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionFeedLimitException;
use OCA\ArbeitszeitCheck\Kiosk\KioskCrypto;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Security\ICrypto;

/**
 * Backend business logic for Outlook RFC 5545 subscription feeds.
 */
final class OutlookIcalSubscriptionService
{
	public const MAX_EVENT_COUNT = 5000;

	public function __construct(
		private readonly OutlookIcalSubscriptionTokenMapper $tokenMapper,
		private readonly OutlookIcalSubscriptionFeedService $feedService,
		private readonly AbsenceMapper $absenceMapper,
		private readonly TeamMapper $teamMapper,
		private readonly TeamMemberMapper $teamMemberMapper,
		private readonly TeamManagerMapper $teamManagerMapper,
		private readonly TeamResolverService $teamResolver,
		private readonly PermissionService $permissionService,
		private readonly IUserManager $userManager,
		private readonly IConfig $config,
		private readonly IDBConnection $db,
		private readonly IFactory $l10nFactory,
		private readonly ICrypto $crypto,
	) {
	}

	public static function isOrgWideScope(int $teamId): bool
	{
		return $teamId === Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID;
	}

	/**
	 * Rolling feed window: last {@see Constants::SUBSCRIPTION_ROLLING_PAST_MONTHS} months through
	 * the next {@see Constants::SUBSCRIPTION_ROLLING_FUTURE_MONTHS} months (UTC date-only).
	 *
	 * @return array{start:DateTimeImmutable, end:DateTimeImmutable, startYmd:string, endYmd:string}
	 */
	public static function resolveRollingFeedRange(?DateTimeImmutable $anchor = null): array
	{
		$tz = new DateTimeZone('UTC');
		$anchor = ($anchor ?? new DateTimeImmutable('today', $tz))->setTime(0, 0, 0);
		$start = $anchor->modify('-' . Constants::SUBSCRIPTION_ROLLING_PAST_MONTHS . ' months');
		$end = $anchor->modify('+' . Constants::SUBSCRIPTION_ROLLING_FUTURE_MONTHS . ' months');

		return [
			'start' => $start,
			'end' => $end,
			'startYmd' => $start->format('Y-m-d'),
			'endYmd' => $end->format('Y-m-d'),
		];
	}

	/**
	 * Preview the number of approved absences for a team/manager scope (current rolling window).
	 *
	 * @return array{eventCount:int, windowStart:string, windowEnd:string}
	 *
	 * @throws OutlookIcalSubscriptionAuthException|OutlookIcalSubscriptionBadRequestException|OutlookIcalSubscriptionFeedLimitException
	 */
	public function previewApprovedAbsenceCount(string $managerUserId, int $teamId, ?DateTimeImmutable $anchor = null): array
	{
		$this->assertValidManagedScope($managerUserId, $teamId);
		$range = self::resolveRollingFeedRange($anchor);
		$this->validateRangeWithinLimits($range);

		$members = $this->resolveScopeMemberUserIds($managerUserId, $teamId);
		$approvedCount = $this->eventCountWithinLimits($members, $range);
		if ($approvedCount > self::MAX_EVENT_COUNT) {
			throw new OutlookIcalSubscriptionFeedLimitException(OutlookIcalSubscriptionFeedLimitException::ERROR_EVENT_COUNT_TOO_LARGE);
		}

		return [
			'eventCount' => $approvedCount,
			'windowStart' => $range['startYmd'],
			'windowEnd' => $range['endYmd'],
		];
	}

	/**
	 * Create a new subscription token for a scope + calendar language.
	 *
	 * @return array{token:string, eventCount:int, windowStart:string, windowEnd:string, feedLanguageCode:string, subscriptionId:int}
	 *
	 * @throws OutlookIcalSubscriptionAuthException|OutlookIcalSubscriptionBadRequestException|OutlookIcalSubscriptionFeedLimitException
	 */
	public function createToken(string $managerUserId, int $teamId, string $feedLanguageCode, ?DateTimeImmutable $anchor = null): array
	{
		$feedLanguageCode = OutlookIcalSubscriptionLanguageCatalog::assertSupported($feedLanguageCode);
		$tenantId = $this->tenantId();
		if ($tenantId === '') {
			throw new OutlookIcalSubscriptionAuthException(OutlookIcalSubscriptionAuthException::ERROR_FORBIDDEN, 403);
		}

		if ($this->tokenMapper->findForScopeLanguage($tenantId, $teamId, $feedLanguageCode) !== null) {
			throw new OutlookIcalSubscriptionBadRequestException(
				OutlookIcalSubscriptionBadRequestException::ERROR_SUBSCRIPTION_ALREADY_EXISTS
			);
		}

		return $this->issueToken($managerUserId, $teamId, $feedLanguageCode, null, $anchor);
	}

	/**
	 * Replace the token for an existing scope + calendar language subscription.
	 *
	 * @return array{token:string, eventCount:int, windowStart:string, windowEnd:string, feedLanguageCode:string, subscriptionId:int}
	 *
	 * @throws OutlookIcalSubscriptionAuthException|OutlookIcalSubscriptionBadRequestException|OutlookIcalSubscriptionFeedLimitException
	 */
	public function rotateToken(string $managerUserId, int $teamId, string $feedLanguageCode, ?DateTimeImmutable $anchor = null): array
	{
		$feedLanguageCode = OutlookIcalSubscriptionLanguageCatalog::assertSupported($feedLanguageCode);
		$tenantId = $this->tenantId();
		if ($tenantId === '') {
			throw new OutlookIcalSubscriptionAuthException(OutlookIcalSubscriptionAuthException::ERROR_FORBIDDEN, 403);
		}

		$existing = $this->tokenMapper->findForScopeLanguage($tenantId, $teamId, $feedLanguageCode);
		if ($existing === null) {
			throw new OutlookIcalSubscriptionBadRequestException(
				OutlookIcalSubscriptionBadRequestException::ERROR_SUBSCRIPTION_NOT_FOUND
			);
		}

		return $this->issueToken($managerUserId, $teamId, $feedLanguageCode, $existing, $anchor);
	}

	/**
	 * Decrypt a stored subscription token for admin display (app admins only at call site).
	 */
	public function decryptStoredToken(OutlookIcalSubscriptionToken $tokenRecord): ?string
	{
		$encrypted = $tokenRecord->getTokenEncrypted();
		if ($encrypted === null || $encrypted === '') {
			return null;
		}

		try {
			return $this->crypto->decrypt($encrypted);
		} catch (\Throwable) {
			return null;
		}
	}

	/**
	 * @return array{token:string, eventCount:int, windowStart:string, windowEnd:string, feedLanguageCode:string, subscriptionId:int}
	 */
	private function issueToken(
		string $managerUserId,
		int $teamId,
		string $feedLanguageCode,
		?OutlookIcalSubscriptionToken $existing,
		?DateTimeImmutable $anchor,
	): array {
		$preview = $this->previewApprovedAbsenceCount($managerUserId, $teamId, $anchor);
		$tenantId = $this->tenantId();
		if ($tenantId === '') {
			throw new OutlookIcalSubscriptionAuthException(OutlookIcalSubscriptionAuthException::ERROR_FORBIDDEN, 403);
		}

		$attemptsRemaining = 2;
		while ($attemptsRemaining > 0) {
			$attemptsRemaining--;
			$token = KioskCrypto::generateToken(32);
			$tokenHash = hash('sha256', $token);
			$tokenEncrypted = $this->crypto->encrypt($token);
			$now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
			$createdAt = new \DateTime($now->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));

			$this->db->beginTransaction();
			try {
				if ($existing !== null) {
					$existing->setManagerUserId($managerUserId);
					$existing->setFeedLanguageCode($feedLanguageCode);
					$existing->setTokenHash($tokenHash);
					$existing->setTokenEncrypted($tokenEncrypted);
					$existing->setIsActive(1);
					$existing->setRevokedAt(null);
					$existing->setCreatedAt($createdAt);
					$this->tokenMapper->update($existing);
					$subscriptionId = (int)$existing->getId();
				} else {
					$entity = new OutlookIcalSubscriptionToken();
					$entity->setTenantId($tenantId);
					$entity->setManagerUserId($managerUserId);
					$entity->setTeamId($teamId);
					$entity->setFeedLanguageCode($feedLanguageCode);
					$entity->setTokenHash($tokenHash);
					$entity->setTokenEncrypted($tokenEncrypted);
					$entity->setIsActive(1);
					$entity->setRevokedAt(null);
					$entity->setCreatedAt($createdAt);
					$this->tokenMapper->insert($entity);
					$subscriptionId = (int)$entity->getId();
				}

				$this->db->commit();

				return [
					'token' => $token,
					'eventCount' => $preview['eventCount'],
					'windowStart' => $preview['windowStart'],
					'windowEnd' => $preview['windowEnd'],
					'feedLanguageCode' => $feedLanguageCode,
					'subscriptionId' => $subscriptionId,
				];
			} catch (\Throwable $e) {
				$this->db->rollBack();
				if ($attemptsRemaining > 0 && $this->isUniqueConstraintViolation($e)) {
					if ($existing === null) {
						$existing = $this->tokenMapper->findForScopeLanguage($tenantId, $teamId, $feedLanguageCode);
						if ($existing !== null) {
							throw new OutlookIcalSubscriptionBadRequestException(
								OutlookIcalSubscriptionBadRequestException::ERROR_SUBSCRIPTION_ALREADY_EXISTS
							);
						}
					}
					continue;
				}
				throw $e;
			}
		}

		throw new \RuntimeException('Outlook iCal token issuance exhausted retries unexpectedly.');
	}

	/**
	 * Tokenized feed endpoint (Outlook-friendly).
	 *
	 * @throws OutlookIcalSubscriptionAuthException|OutlookIcalSubscriptionBadRequestException|OutlookIcalSubscriptionFeedLimitException
	 */
	public function buildTokenizedFeed(string $rawToken, int $teamId, string $tenantDomain, ?DateTimeImmutable $anchor = null): string
	{
		$tenantId = $this->tenantId();
		if ($tenantId === '') {
			// Defensive: without a tenant id we cannot enforce proper isolation.
			throw new OutlookIcalSubscriptionAuthException(OutlookIcalSubscriptionAuthException::ERROR_FORBIDDEN, 403);
		}

		$tokenHash = hash('sha256', $rawToken);

		$tokenRecord = $this->tokenMapper->findActiveByTeamAndTokenHash($tenantId, $teamId, $tokenHash);
		if ($tokenRecord === null) {
			throw new OutlookIcalSubscriptionAuthException(OutlookIcalSubscriptionAuthException::ERROR_UNAUTHORIZED, 401);
		}

		$managerUserId = $tokenRecord->getManagerUserId();
		if (!$this->isManagerAllowedForTeamScope($managerUserId, $teamId)) {
			throw new OutlookIcalSubscriptionAuthException(OutlookIcalSubscriptionAuthException::ERROR_FORBIDDEN, 403);
		}

		return $this->buildFeedForScope(
			$managerUserId,
			$teamId,
			$tenantDomain,
			$this->resolveStoredFeedLanguageCode($tokenRecord),
			$anchor,
		);
	}

	/**
	 * Authenticated feed endpoint (manager session).
	 *
	 * @throws OutlookIcalSubscriptionAuthException|OutlookIcalSubscriptionBadRequestException|OutlookIcalSubscriptionFeedLimitException
	 */
	public function buildAuthenticatedFeed(
		string $managerUserId,
		int $teamId,
		string $tenantDomain,
		?string $feedLanguageCode = null,
		?DateTimeImmutable $anchor = null,
	): string {
		// Endpoint contract: manager session can only see data for scopes they are allowed to manage.
		$this->assertValidManagedScope($managerUserId, $teamId);

		$languageCode = $feedLanguageCode !== null && $feedLanguageCode !== ''
			? OutlookIcalSubscriptionLanguageCatalog::assertSupported($feedLanguageCode)
			: $this->resolveAuthorizerLanguageFallback($managerUserId);

		return $this->buildFeedForScope($managerUserId, $teamId, $tenantDomain, $languageCode, $anchor);
	}

	public function managerCanAccessScope(string $managerUserId, int $teamId): bool
	{
		try {
			$this->assertTeamExists($teamId);
		} catch (OutlookIcalSubscriptionBadRequestException) {
			return false;
		}

		$user = $this->userManager->get($managerUserId);
		if ($user === null || !$user->isEnabled()) {
			return false;
		}

		return $this->isManagerAllowedForTeamScope($managerUserId, $teamId);
	}

	/**
	 * @throws OutlookIcalSubscriptionBadRequestException|OutlookIcalSubscriptionFeedLimitException
	 */
	private function buildFeedForScope(
		string $managerUserId,
		int $teamId,
		string $tenantDomain,
		string $feedLanguageCode,
		?DateTimeImmutable $anchor = null,
	): string {
		$range = self::resolveRollingFeedRange($anchor);
		$this->validateRangeWithinLimits($range);

		$members = $this->resolveScopeMemberUserIds($managerUserId, $teamId);
		$approvedCount = $this->eventCountWithinLimits($members, $range);

		if ($approvedCount > self::MAX_EVENT_COUNT) {
			throw new OutlookIcalSubscriptionFeedLimitException(OutlookIcalSubscriptionFeedLimitException::ERROR_EVENT_COUNT_TOO_LARGE);
		}

		$absences = $this->absenceMapper->findByUsersAndDateRange(
			$members,
			$range['start'],
			$range['end'],
			Absence::STATUS_APPROVED,
			null,
			self::MAX_EVENT_COUNT + 1,
		);

		if (count($absences) > self::MAX_EVENT_COUNT) {
			throw new OutlookIcalSubscriptionFeedLimitException(OutlookIcalSubscriptionFeedLimitException::ERROR_EVENT_COUNT_TOO_LARGE);
		}

		$displayNames = $this->buildDisplayNamesMap($members);
		foreach ($absences as $absence) {
			$userId = (string)$absence->getUserId();
			if ($userId !== '' && !array_key_exists($userId, $displayNames)) {
				$displayNames[$userId] = $this->resolveEmployeeDisplayName($userId);
			}
		}

		return $this->feedService->buildFeed($tenantDomain, $absences, $displayNames, $feedLanguageCode);
	}

	private function assertValidManagedScope(string $managerUserId, int $teamId): void
	{
		$this->assertTeamExists($teamId);
		$this->assertEnabledUserExists($managerUserId);
		if (!$this->isManagerAllowedForTeamScope($managerUserId, $teamId)) {
			throw new OutlookIcalSubscriptionAuthException(OutlookIcalSubscriptionAuthException::ERROR_FORBIDDEN, 403);
		}
	}

	private function assertTeamExists(int $teamId): void
	{
		if (self::isOrgWideScope($teamId)) {
			return;
		}

		try {
			$this->teamMapper->find($teamId);
		} catch (DoesNotExistException) {
			throw new OutlookIcalSubscriptionBadRequestException(OutlookIcalSubscriptionBadRequestException::ERROR_INVALID_TEAM_SCOPE);
		}
	}

	private function assertEnabledUserExists(string $userId): void
	{
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled()) {
			throw new OutlookIcalSubscriptionBadRequestException(OutlookIcalSubscriptionBadRequestException::ERROR_MANAGER_UNAVAILABLE);
		}
	}

	/**
	 * @return list<string>
	 */
	private function resolveScopeMemberUserIds(string $managerUserId, int $teamId): array
	{
		if (self::isOrgWideScope($teamId)) {
			return $this->normalizeScopeUserIds($this->permissionService->listAppAccessUserIds());
		}

		if (!$this->teamResolver->useAppTeams()) {
			throw new OutlookIcalSubscriptionAuthException(OutlookIcalSubscriptionAuthException::ERROR_FORBIDDEN, 403);
		}

		$teamIds = $this->teamMapper->getIdsWithDescendants($teamId);
		$memberUserIds = $this->teamMemberMapper->getMemberUserIdsByTeamIds($teamIds);
		$managerUserIds = $this->teamManagerMapper->getManagerUserIdsByTeamIds($teamIds);

		return $this->normalizeScopeUserIds(array_merge($memberUserIds, $managerUserIds));
	}

	/**
	 * @param list<string> $userIds
	 * @return list<string>
	 */
	private function normalizeScopeUserIds(array $userIds): array
	{
		$unique = [];
		foreach ($userIds as $uid) {
			$uid = trim((string)$uid);
			if ($uid === '' || isset($unique[$uid])) {
				continue;
			}
			$user = $this->userManager->get($uid);
			if ($user === null || !$user->isEnabled()) {
				continue;
			}
			$unique[$uid] = true;
		}

		return array_keys($unique);
	}

	/**
	 * @param list<string> $members
	 * @param array{start:DateTimeImmutable, end:DateTimeImmutable, startYmd:string, endYmd:string} $range
	 */
	private function eventCountWithinLimits(array $members, array $range): int
	{
		if ($members === []) {
			return 0;
		}

		return $this->absenceMapper->countByUsersAndDateRange(
			$members,
			$range['start'],
			$range['end'],
			Absence::STATUS_APPROVED,
			null
		);
	}

	/**
	 * @param array{start:DateTimeImmutable, end:DateTimeImmutable, startYmd:string, endYmd:string} $range
	 */
	private function validateRangeWithinLimits(array $range): void
	{
		$spanDays = (int)$range['start']->diff($range['end'])->days + 1;
		if ($spanDays > Constants::MAX_SUBSCRIPTION_DATE_RANGE_DAYS) {
			throw new OutlookIcalSubscriptionBadRequestException(OutlookIcalSubscriptionBadRequestException::ERROR_RANGE_TOO_LARGE);
		}
	}

	private function isManagerAllowedForTeamScope(string $managerUserId, int $teamId): bool
	{
		if (self::isOrgWideScope($teamId)) {
			if ($this->permissionService->isAdmin($managerUserId)) {
				return true;
			}
			if (!$this->teamResolver->useAppTeams()) {
				return false;
			}

			return $this->teamResolver->getTeamMemberIds($managerUserId) !== [];
		}

		// Nextcloud/app admins can see everything.
		if ($this->permissionService->isAdmin($managerUserId)) {
			return true;
		}

		if (!$this->teamResolver->useAppTeams()) {
			return false;
		}

		$managedTeamIds = $this->teamManagerMapper->getTeamIdsForManager($managerUserId);
		if ($managedTeamIds === []) {
			return false;
		}

		$allowed = [];
		foreach ($managedTeamIds as $managedTeamId) {
			foreach ($this->teamMapper->getIdsWithDescendants($managedTeamId) as $tid) {
				$allowed[$tid] = true;
			}
		}

		return isset($allowed[$teamId]);
	}

	/**
	 * @return array<string, string> userId => displayName
	 */
	private function buildDisplayNamesMap(array $memberUserIds): array
	{
		$out = [];
		foreach ($memberUserIds as $uid) {
			$out[$uid] = $this->resolveEmployeeDisplayName($uid);
		}
		return $out;
	}

	private function resolveEmployeeDisplayName(string $userId): string
	{
		$user = $this->userManager->get($userId);
		if ($user !== null) {
			$display = trim((string)$user->getDisplayName());
			if ($display !== '') {
				return $display;
			}
		}

		$fallback = trim((string)$this->userManager->getDisplayName($userId));
		if ($fallback !== '') {
			return $fallback;
		}

		return $userId;
	}

	private function resolveStoredFeedLanguageCode(\OCA\ArbeitszeitCheck\Db\OutlookIcalSubscriptionToken $tokenRecord): string
	{
		$stored = OutlookIcalSubscriptionLanguageCatalog::normalize($tokenRecord->getFeedLanguageCode());
		if ($stored !== null) {
			return $stored;
		}

		return $this->resolveAuthorizerLanguageFallback($tokenRecord->getManagerUserId());
	}

	private function resolveAuthorizerLanguageFallback(string $authorizerUserId): string
	{
		$user = $this->userManager->get($authorizerUserId);
		if ($user !== null) {
			$lang = OutlookIcalSubscriptionLanguageCatalog::normalize(
				(string)$this->l10nFactory->getUserLanguage($user)
			);
			if ($lang !== null) {
				return $lang;
			}
		}

		return OutlookIcalSubscriptionLanguageCatalog::resolveDefault(
			(string)$this->l10nFactory->findLanguage('arbeitszeitcheck')
		);
	}

	/**
	 * Active subscription metadata for admin UI (no secret URLs — only hashes are stored).
	 *
	 * @return list<array{id:int, teamId:int, feedLanguageCode:string, createdAt:string, orgWide:bool, urlAvailable:bool}>
	 */
	public function listActiveSubscriptionRecords(): array
	{
		$tenantId = $this->tenantId();
		if ($tenantId === '') {
			return [];
		}

		$records = [];
		foreach ($this->tokenMapper->findAllActiveForTenant($tenantId) as $token) {
			$teamId = (int)$token->getTeamId();
			$createdAt = $token->getCreatedAt();
			$records[] = [
				'id' => (int)$token->getId(),
				'teamId' => $teamId,
				'feedLanguageCode' => (string)($token->getFeedLanguageCode() ?? ''),
				'createdAt' => $createdAt !== null ? $createdAt->format(\DateTimeInterface::ATOM) : '',
				'orgWide' => self::isOrgWideScope($teamId),
				'urlAvailable' => $this->decryptStoredToken($token) !== null,
			];
		}

		return $records;
	}

	/**
	 * Admin-only metadata including decrypted token when stored (for URL display).
	 *
	 * @return list<array{id:int, teamId:int, feedLanguageCode:string, createdAt:string, orgWide:bool, urlAvailable:bool, token:?string, eventCount:int, windowStart:string, windowEnd:string}>
	 */
	public function listActiveSubscriptionsForAdmin(string $managerUserId): array
	{
		$tenantId = $this->tenantId();
		if ($tenantId === '') {
			return [];
		}

		$records = [];
		/** @var array<int, array{eventCount:int, windowStart:string, windowEnd:string}> $previewByTeamId */
		$previewByTeamId = [];
		foreach ($this->tokenMapper->findAllActiveForTenant($tenantId) as $token) {
			$teamId = (int)$token->getTeamId();
			$createdAt = $token->getCreatedAt();
			$plaintext = $this->decryptStoredToken($token);
			if (!array_key_exists($teamId, $previewByTeamId)) {
				try {
					$previewByTeamId[$teamId] = $this->previewApprovedAbsenceCount($managerUserId, $teamId);
				} catch (\Throwable) {
					$previewByTeamId[$teamId] = [
						'eventCount' => 0,
						'windowStart' => '',
						'windowEnd' => '',
					];
				}
			}
			$preview = $previewByTeamId[$teamId];

			$records[] = [
				'id' => (int)$token->getId(),
				'teamId' => $teamId,
				'feedLanguageCode' => (string)($token->getFeedLanguageCode() ?? ''),
				'createdAt' => $createdAt !== null ? $createdAt->format(\DateTimeInterface::ATOM) : '',
				'orgWide' => self::isOrgWideScope($teamId),
				'urlAvailable' => $plaintext !== null,
				'token' => $plaintext,
				'eventCount' => (int)$preview['eventCount'],
				'windowStart' => (string)$preview['windowStart'],
				'windowEnd' => (string)$preview['windowEnd'],
			];
		}

		return $records;
	}

	private function tenantId(): string
	{
		return (string)$this->config->getSystemValue('instanceid', '');
	}

	private function isUniqueConstraintViolation(\Throwable $e): bool
	{
		if ($e instanceof UniqueConstraintViolationException) {
			return true;
		}

		$previous = $e->getPrevious();
		return $previous instanceof UniqueConstraintViolationException;
	}
}
