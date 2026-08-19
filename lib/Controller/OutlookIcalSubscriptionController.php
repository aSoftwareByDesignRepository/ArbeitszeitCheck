<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionAuthException;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionBadRequestException;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionFeedLimitException;
use OCA\ArbeitszeitCheck\Service\OutlookIcalSubscriptionService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCA\ArbeitszeitCheck\Db\Team;
use OCA\ArbeitszeitCheck\Db\TeamMapper;

final class OutlookIcalSubscriptionController extends Controller
{
	private const DAV_APP_ID = 'dav';
	private const DAV_WEBCAL_ALLOW_LOCAL_ACCESS = 'webcalAllowLocalAccess';

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly OutlookIcalSubscriptionService $service,
		private readonly PermissionService $permissionService,
		private readonly TeamResolverService $teamResolver,
		private readonly TeamMapper $teamMapper,
		private readonly IUserManager $userManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l10n,
		private readonly IConfig $config,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Tokenized feed endpoint for calendar subscription refreshes.
	 *
	 * Query params:
	 * - token (secret)
	 * - teamId
	 *
	 * The feed window is computed on each request (last 3 months through next 12 months).
	 * Legacy start/end query params are ignored when present.
	 *
	 * Security: token hash + authorizer scope checks (see service). PublicPage is required
	 * because calendar clients and Nextcloud's webcal fetcher do not send browser session cookies.
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function tokenizedFeed(?string $token = null, ?int $teamId = null, ?string $start = null, ?string $end = null): DataDisplayResponse
	{
		$tenantDomain = $this->tenantDomainFromHostHeader();
		try {
			if ($token === null || $token === '' || $teamId === null) {
				return $this->calendarError(Http::STATUS_BAD_REQUEST);
			}

			$feed = $this->service->buildTokenizedFeed(
				$token,
				$teamId,
				$tenantDomain,
			);

			return new DataDisplayResponse($feed, Http::STATUS_OK, [
				'Content-Type' => 'text/calendar; charset=utf-8',
				'Content-Disposition' => 'inline; filename="arbeitszeitcheck-team-absences.ics"',
				'Cache-Control' => 'private, max-age=300',
			]);
		} catch (OutlookIcalSubscriptionAuthException $e) {
			return $this->calendarError($e->httpStatus);
		} catch (OutlookIcalSubscriptionBadRequestException|OutlookIcalSubscriptionFeedLimitException $e) {
			return $this->calendarError(Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Authenticated manager session feed endpoint.
	 *
	 * Query params:
	 * - teamId
	 *
	 * Security: returns empty body on auth/limit failures.
	 */
	#[NoCSRFRequired]
	public function authenticatedFeed(?int $teamId = null, ?string $start = null, ?string $end = null): DataDisplayResponse
	{
		$tenantDomain = $this->tenantDomainFromHostHeader();

		$user = $this->userSession->getUser();
		if ($user === null || $teamId === null) {
			return $this->calendarError(Http::STATUS_UNAUTHORIZED);
		}

		try {
			$feed = $this->service->buildAuthenticatedFeed(
				$user->getUID(),
				$teamId,
				$tenantDomain,
			);

			return new DataDisplayResponse($feed, Http::STATUS_OK, [
				'Content-Type' => 'text/calendar; charset=utf-8',
				'Content-Disposition' => 'inline; filename="arbeitszeitcheck-team-absences.ics"',
				'Cache-Control' => 'private, max-age=300',
			]);
		} catch (OutlookIcalSubscriptionAuthException $e) {
			return $this->calendarError($e->httpStatus);
		} catch (OutlookIcalSubscriptionBadRequestException|OutlookIcalSubscriptionFeedLimitException $e) {
			return $this->calendarError(Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Whether Nextcloud Calendar may refresh webcal feeds hosted on this same server.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function adminWebcalLocalAccess(): JSONResponse
	{
		if (($response = $this->requireAppAdmin()) !== null) {
			return $response;
		}

		$user = $this->userSession->getUser();

		return new JSONResponse([
			'success' => true,
			'enabled' => $this->isWebcalLocalAccessEnabled(),
			'canEnable' => $user !== null && $this->groupManager->isAdmin($user->getUID()),
		]);
	}

	/**
	 * One-click enable for Nextcloud server administrators (no SSH/occ required).
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function adminEnableWebcalLocalAccess(): JSONResponse
	{
		if (($response = $this->requireAppAdmin()) !== null) {
			return $response;
		}

		$user = $this->userSession->getUser();
		if ($user === null || !$this->groupManager->isAdmin($user->getUID())) {
			return new JSONResponse([
				'success' => false,
				'code' => 'FORBIDDEN',
				'error' => 'Only a Nextcloud server administrator can enable this.',
			], Http::STATUS_FORBIDDEN);
		}

		$this->config->setAppValue(self::DAV_APP_ID, self::DAV_WEBCAL_ALLOW_LOCAL_ACCESS, 'yes');

		return new JSONResponse([
			'success' => true,
			'enabled' => true,
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function adminTeams(?string $search = null, ?int $limit = 20): JSONResponse
	{
		if (($response = $this->requireAppAdmin()) !== null) {
			return $response;
		}

		$useAppTeams = $this->teamResolver->useAppTeams();
		$searchTerm = mb_strtolower(trim((string)($search ?? '')));
		$maxResults = max(1, min((int)($limit ?? 20), 50));

		$orgWideLabel = $this->l10n->t('All employees');
		$entries = [];
		$orgHaystack = mb_strtolower($orgWideLabel);
		$includeOrgWide = $searchTerm === ''
			|| str_contains($orgHaystack, $searchTerm)
			|| $searchTerm === 'all employees';
		if ($includeOrgWide) {
			$entries[] = [
				'id' => Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID,
				'name' => $orgWideLabel,
				'path' => $orgWideLabel,
				'orgWide' => true,
			];
		}

		if ($useAppTeams) {
			$teams = $this->teamMapper->findAll();
			$teamById = [];
			foreach ($teams as $team) {
				$teamById[$team->getId()] = $team;
			}

			foreach ($teams as $team) {
				if (count($entries) >= $maxResults) {
					break;
				}
				$path = $this->teamPathLabel($team, $teamById);
				$haystack = mb_strtolower($team->getName() . ' ' . $path);
				if ($searchTerm !== '' && !str_contains($haystack, $searchTerm)) {
					continue;
				}

				$entries[] = [
					'id' => $team->getId(),
					'name' => $team->getName(),
					'path' => $path,
					'orgWide' => false,
				];
			}
		}

		return new JSONResponse([
			'success' => true,
			'teams' => $entries,
			'useAppTeams' => $useAppTeams,
			'orgWideAvailable' => true,
		]);
	}

	public function adminRotateToken(
		?int $teamId = null,
		?string $managerUserId = null,
		?string $languageCode = null,
		?string $start = null,
		?string $end = null,
	): JSONResponse {
		if (($response = $this->requireAppAdmin()) !== null) {
			return $response;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->adminBadRequest(
				OutlookIcalSubscriptionBadRequestException::ERROR_MISSING_PARAMETERS,
				'Authentication required.'
			);
		}

		if ($teamId === null) {
			return $this->adminBadRequest(
				OutlookIcalSubscriptionBadRequestException::ERROR_MISSING_PARAMETERS,
				'Pick a scope first.'
			);
		}

		if ($languageCode === null || trim($languageCode) === '') {
			return $this->adminBadRequest(
				OutlookIcalSubscriptionBadRequestException::ERROR_MISSING_PARAMETERS,
				'Pick a calendar language first.'
			);
		}

		$authorizerUserId = $user->getUID();

		try {
			$result = $this->service->rotateToken($authorizerUserId, $teamId, $languageCode);

			$feedUrl = $this->buildTokenizedFeedUrl($result['token'], $teamId);

			return new JSONResponse([
				'success' => true,
				'eventCount' => $result['eventCount'],
				'windowStart' => $result['windowStart'],
				'windowEnd' => $result['windowEnd'],
				'feedLanguageCode' => $result['feedLanguageCode'],
				'feedUrl' => $feedUrl,
				'feedWebcalUrl' => $this->buildWebcalFeedUrl($feedUrl),
			]);
		} catch (OutlookIcalSubscriptionFeedLimitException $e) {
			return $this->adminBadRequest($e->errorCode, 'This feed would include too many events for the rolling window.');
		} catch (OutlookIcalSubscriptionBadRequestException $e) {
			return $this->adminBadRequest($e->errorCode, $this->badRequestMessage($e->errorCode));
		} catch (OutlookIcalSubscriptionAuthException $e) {
			return $this->adminBadRequest($e->errorCode, 'The selected manager is not allowed to access this team scope.');
		} catch (\Throwable $e) {
			\OCP\Server::get(\Psr\Log\LoggerInterface::class)->error(
				'Outlook iCal subscription token rotation failed',
				['exception' => $e],
			);

			return new JSONResponse([
				'success' => false,
				'code' => 'INTERNAL_ERROR',
				'error' => 'Could not generate the subscription link. Please try again.',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function calendarError(int $status): DataDisplayResponse
	{
		return new DataDisplayResponse('', $status, [
			'Content-Type' => 'text/calendar; charset=utf-8',
		]);
	}

	private function tenantDomainFromHostHeader(): string
	{
		try {
			$host = strtolower(trim($this->request->getServerHost()));
		} catch (\Throwable) {
			$host = '';
		}

		if ($host === '') {
			return 'localhost';
		}

		// Strip optional port to keep UID stable and Outlook friendly.
		$host = preg_replace('/:\\d+$/', '', $host) ?: $host;
		return $host;
	}

	private function buildTokenizedFeedUrl(string $token, int $teamId): string
	{
		return $this->buildFeedUrlForRoute(
			'arbeitszeitcheck.outlook_ical_subscription.tokenizedFeed',
			$token,
			$teamId,
		);
	}

	private function buildWebcalFeedUrl(string $httpsFeedUrl): string
	{
		if (str_starts_with($httpsFeedUrl, 'https://')) {
			return 'webcal://' . substr($httpsFeedUrl, 8);
		}
		if (str_starts_with($httpsFeedUrl, 'http://')) {
			return 'webcal://' . substr($httpsFeedUrl, 7);
		}

		return $httpsFeedUrl;
	}

	private function buildFeedUrlForRoute(string $routeName, string $token, int $teamId): string
	{
		$route = $this->urlGenerator->linkToRoute($routeName);
		$query = http_build_query([
			'token' => $token,
			'teamId' => $teamId,
		], '', '&', PHP_QUERY_RFC3986);

		return $this->urlGenerator->getAbsoluteURL($route . '?' . $query);
	}

	/**
	 * Backward-compatible alias for feeds generated before the .ics route existed.
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function tokenizedFeedLegacy(?string $token = null, ?int $teamId = null, ?string $start = null, ?string $end = null): DataDisplayResponse
	{
		return $this->tokenizedFeed($token, $teamId, $start, $end);
	}

	private function isWebcalLocalAccessEnabled(): bool
	{
		return $this->config->getAppValue(self::DAV_APP_ID, self::DAV_WEBCAL_ALLOW_LOCAL_ACCESS, 'no') === 'yes';
	}

	private function requireAppAdmin(): ?JSONResponse
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse([
				'success' => false,
				'code' => 'UNAUTHORIZED',
				'error' => 'Authentication required.',
			], Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->permissionService->isAdmin($user->getUID())) {
			return new JSONResponse([
				'success' => false,
				'code' => 'FORBIDDEN',
				'error' => 'Access denied. You are not an ArbeitszeitCheck app administrator.',
			], Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	/**
	 * @param array<int, Team> $teamById
	 */
	private function teamPathLabel(Team $team, array $teamById): string
	{
		$parts = [$team->getName()];
		$parentId = $team->getParentId();
		$seen = [$team->getId() => true];
		$depth = 0;

		while ($parentId !== null && isset($teamById[$parentId]) && $depth < 32) {
			if (isset($seen[$parentId])) {
				break;
			}
			$seen[$parentId] = true;
			$parent = $teamById[$parentId];
			array_unshift($parts, $parent->getName());
			$parentId = $parent->getParentId();
			$depth++;
		}

		return implode(' / ', $parts);
	}

	private function adminBadRequest(string $code, string $message): JSONResponse
	{
		return new JSONResponse([
			'success' => false,
			'code' => $code,
			'error' => $message,
		], Http::STATUS_BAD_REQUEST);
	}

	private function badRequestMessage(string $code): string
	{
		return match ($code) {
			OutlookIcalSubscriptionBadRequestException::ERROR_INVALID_DATE_RANGE => 'Enter a valid start and end date.',
			OutlookIcalSubscriptionBadRequestException::ERROR_RANGE_TOO_LARGE => 'The rolling subscription window is too large.',
			OutlookIcalSubscriptionBadRequestException::ERROR_INVALID_TEAM_SCOPE => 'Choose a valid team.',
			OutlookIcalSubscriptionBadRequestException::ERROR_MANAGER_UNAVAILABLE => 'Choose an enabled manager account.',
			OutlookIcalSubscriptionBadRequestException::ERROR_INVALID_FEED_LANGUAGE => 'Choose a supported calendar language.',
			default => 'Please review the subscription settings and try again.',
		};
	}
}
