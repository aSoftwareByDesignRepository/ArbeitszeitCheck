<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionBadRequestException;

/**
 * Whitelist of feed label languages supported by shipped app translations.
 *
 * Security: only catalog codes may be stored on subscription tokens or passed to l10n.
 */
final class OutlookIcalSubscriptionLanguageCatalog
{
	public const DEFAULT_CODE = 'en';

	/** @var list<string> */
	public const SUPPORTED_CODES = [
		'en',
		'de',
		'da',
		'es',
		'fr',
		'it',
		'nb',
		'nl',
		'pl',
		'pt_BR',
		'sv',
	];

	/** @var array<string, string> code => native label for UI select */
	private const NATIVE_LABELS = [
		'en' => 'English',
		'de' => 'Deutsch',
		'da' => 'Dansk',
		'es' => 'Español',
		'fr' => 'Français',
		'it' => 'Italiano',
		'nb' => 'Norsk bokmål',
		'nl' => 'Nederlands',
		'pl' => 'Polski',
		'pt_BR' => 'Português (Brasil)',
		'sv' => 'Svenska',
	];

	/**
	 * @return list<array{code:string, label:string}>
	 */
	public static function optionsForUi(): array
	{
		$options = [];
		foreach (self::SUPPORTED_CODES as $code) {
			$options[] = [
				'code' => $code,
				'label' => self::NATIVE_LABELS[$code] ?? $code,
			];
		}

		return $options;
	}

	public static function resolveDefault(?string $preferredLanguageCode = null): string
	{
		$normalized = self::normalize($preferredLanguageCode);
		if ($normalized !== null) {
			return $normalized;
		}

		return self::DEFAULT_CODE;
	}

	/**
	 * @throws OutlookIcalSubscriptionBadRequestException
	 */
	public static function assertSupported(?string $languageCode): string
	{
		$normalized = self::normalize($languageCode);
		if ($normalized === null) {
			throw new OutlookIcalSubscriptionBadRequestException(
				OutlookIcalSubscriptionBadRequestException::ERROR_INVALID_FEED_LANGUAGE
			);
		}

		return $normalized;
	}

	public static function normalize(?string $languageCode): ?string
	{
		$raw = trim((string)$languageCode);
		if ($raw === '') {
			return null;
		}

		$raw = str_replace('-', '_', $raw);
		if (!preg_match('/^[A-Za-z]{2,3}(_[A-Za-z]{2})?$/', $raw)) {
			return null;
		}

		$parts = explode('_', $raw);
		$language = strtolower($parts[0]);
		$region = isset($parts[1]) ? strtoupper($parts[1]) : '';
		$candidate = $region !== '' ? $language . '_' . $region : $language;

		if (in_array($candidate, self::SUPPORTED_CODES, true)) {
			return $candidate;
		}

		if ($region !== '' && in_array($language, self::SUPPORTED_CODES, true)) {
			return $language;
		}

		return null;
	}
}
