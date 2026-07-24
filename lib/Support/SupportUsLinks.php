<?php

declare(strict_types=1);

/**
 * Canonical external links and mailto builders for the in-app Support & Us surface.
 *
 * Security notes (auditor-facing):
 * - All destinations are compile-time constants or derived from validated app metadata.
 * - Mailto subjects are rawurlencoded; app display names reject CR/LF/control chars
 *   to block header injection.
 * - No user/request input is interpolated into hrefs.
 * - This surface never gates AGPL features; it is informational CTAs only.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

final class SupportUsLinks {
	public const CONTACT_EMAIL = 'info@software-by-design.de';
	public const SPONSORS_URL = 'https://github.com/sponsors/aSoftwareByDesignRepository';
	public const SITE_ORIGIN = 'https://nextcloud.software-by-design.de';
	public const VENDOR_NAME = 'Software by Design GbR';

	private string $appDisplayName;
	private bool $hasOfficialMobileLicenses;
	private ?string $licensePageUrl;

	/**
	 * @param string $appDisplayName Human product name used in mailto subjects (e.g. ArbeitszeitCheck)
	 * @param bool $hasOfficialMobileLicenses When true, Block E (license CTA) is exposed
	 * @param string|null $licensePageUrl Absolute or app-relative URL to the License admin page
	 */
	public function __construct(
		string $appDisplayName,
		bool $hasOfficialMobileLicenses = false,
		?string $licensePageUrl = null,
	) {
		$normalized = trim($appDisplayName);
		if ($normalized === '' || !$this->isSafeDisplayName($normalized)) {
			throw new \InvalidArgumentException('SupportUsLinks: invalid app display name');
		}
		if ($hasOfficialMobileLicenses) {
			$url = $licensePageUrl !== null ? trim($licensePageUrl) : '';
			if ($url === '' || !$this->isSafeHttpOrRelativeUrl($url)) {
				throw new \InvalidArgumentException('SupportUsLinks: license page URL required and must be http(s) or relative');
			}
			$this->licensePageUrl = $url;
		} else {
			$this->licensePageUrl = null;
		}
		$this->appDisplayName = $normalized;
		$this->hasOfficialMobileLicenses = $hasOfficialMobileLicenses;
	}

	public function appDisplayName(): string {
		return $this->appDisplayName;
	}

	public function hasOfficialMobileLicenses(): bool {
		return $this->hasOfficialMobileLicenses;
	}

	public function licensePageUrl(): ?string {
		return $this->licensePageUrl;
	}

	public function contactEmail(): string {
		return self::CONTACT_EMAIL;
	}

	public function contactMailto(): string {
		return 'mailto:' . self::CONTACT_EMAIL;
	}

	public function sponsorsUrl(): string {
		return self::SPONSORS_URL;
	}

	public function vendorName(): string {
		return self::VENDOR_NAME;
	}

	/**
	 * Prefer German copy/subjects when the Nextcloud language is German.
	 */
	public function isGermanLocale(string $languageCode): bool {
		$lang = strtolower(str_replace('_', '-', trim($languageCode)));
		if ($lang === '') {
			return false;
		}
		return str_starts_with($lang, 'de');
	}

	public function partnerMailto(string $languageCode): string {
		$subject = $this->isGermanLocale($languageCode)
			? $this->appDisplayName . ': Partner / Care Retainer'
			: $this->appDisplayName . ': partner / care retainer';
		return $this->mailtoWithSubject($subject);
	}

	public function onboardingMailto(string $languageCode): string {
		// Shared DE/EN subject token per family standard (Einrichtung / Schulung).
		$subject = $this->appDisplayName . ': Einrichtung / Schulung';
		return $this->mailtoWithSubject($subject);
	}

	public function featureMailto(string $languageCode): string {
		$subject = $this->appDisplayName . ': Feature-Auftrag';
		return $this->mailtoWithSubject($subject);
	}

	/**
	 * Dedicated support page when published; always https under SITE_ORIGIN.
	 */
	public function supportPageUrl(string $languageCode): string {
		$path = $this->isGermanLocale($languageCode) ? '/de/support.html' : '/en/support.html';
		return self::SITE_ORIGIN . $path;
	}

	public function appsPageUrl(string $languageCode): string {
		$path = $this->isGermanLocale($languageCode) ? '/de/apps.html' : '/en/apps.html';
		return self::SITE_ORIGIN . $path;
	}

	/**
	 * Stable payload for templates, JS bootstrap, and contract tests.
	 *
	 * @return array{
	 *   appDisplayName: string,
	 *   hasOfficialMobileLicenses: bool,
	 *   licensePageUrl: ?string,
	 *   contactEmail: string,
	 *   contactMailto: string,
	 *   partnerMailto: string,
	 *   onboardingMailto: string,
	 *   featureMailto: string,
	 *   supportPageUrl: string,
	 *   appsPageUrl: string,
	 *   sponsorsUrl: string,
	 *   vendorName: string,
	 *   isGerman: bool
	 * }
	 */
	public function forLocale(string $languageCode): array {
		return [
			'appDisplayName' => $this->appDisplayName,
			'hasOfficialMobileLicenses' => $this->hasOfficialMobileLicenses,
			'licensePageUrl' => $this->licensePageUrl,
			'contactEmail' => self::CONTACT_EMAIL,
			'contactMailto' => $this->contactMailto(),
			'partnerMailto' => $this->partnerMailto($languageCode),
			'onboardingMailto' => $this->onboardingMailto($languageCode),
			'featureMailto' => $this->featureMailto($languageCode),
			'supportPageUrl' => $this->supportPageUrl($languageCode),
			'appsPageUrl' => $this->appsPageUrl($languageCode),
			'sponsorsUrl' => self::SPONSORS_URL,
			'vendorName' => self::VENDOR_NAME,
			'isGerman' => $this->isGermanLocale($languageCode),
		];
	}

	private function mailtoWithSubject(string $subject): string {
		if (!$this->isSafeMailtoSubject($subject)) {
			throw new \InvalidArgumentException('SupportUsLinks: unsafe mailto subject');
		}
		return 'mailto:' . self::CONTACT_EMAIL . '?subject=' . rawurlencode($subject);
	}

	private function isSafeDisplayName(string $value): bool {
		if (strlen($value) > 80) {
			return false;
		}
		// No CR/LF/NUL/other controls — prevents mailto header injection if ever misused.
		return preg_match('/^[\p{L}\p{N} .+\-_&()]+$/u', $value) === 1
			&& !preg_match('/[\x00-\x1F\x7F]/', $value);
	}

	private function isSafeMailtoSubject(string $value): bool {
		if ($value === '' || strlen($value) > 200) {
			return false;
		}
		return !preg_match('/[\x00-\x1F\x7F]/', $value);
	}

	private function isSafeHttpOrRelativeUrl(string $url): bool {
		if (str_starts_with($url, '/')) {
			// App-relative path (Nextcloud linkToRoute output is typically absolute URL;
			// relative paths are allowed for in-app license jumps).
			return !preg_match('/[\x00-\x1F\x7F\s]/', $url)
				&& !str_contains($url, '://')
				&& !str_contains($url, '\\');
		}
		if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
			return false;
		}
		$parts = parse_url($url);
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			return false;
		}
		$scheme = strtolower((string)$parts['scheme']);
		return $scheme === 'https' || $scheme === 'http';
	}
}
