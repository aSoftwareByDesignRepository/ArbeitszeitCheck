<?php

declare(strict_types=1);

/**
 * Guard: CH canton msgids used by RegionRegistry must have German translations.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\RegionRegistry;
use PHPUnit\Framework\TestCase;

class SwissCantonL10nGuardTest extends TestCase
{
	public function testGermanLocaleTranslatesEverySwissCantonMsgid(): void
	{
		$path = dirname(__DIR__, 3) . '/l10n/de.json';
		$data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		$translations = $data['translations'] ?? [];
		$this->assertIsArray($translations);

		$missing = [];
		$englishLeft = [];
		foreach (RegionRegistry::regionsForCountry(RegionRegistry::COUNTRY_CH) as $code => $msgid) {
			if (!array_key_exists($msgid, $translations)) {
				$missing[] = $code . ':' . $msgid;
				continue;
			}
			// Names that are identical in DE/EN (e.g. Bern, Zug) are fine;
			// names that differ must not still show the English msgid as target.
			$translated = (string)$translations[$msgid];
			$mustDiffer = [
				'Zurich' => 'Zürich',
				'Geneva' => 'Genf',
				'Lucerne' => 'Luzern',
				'Ticino' => 'Tessin',
				'Vaud' => 'Waadt',
				'Valais' => 'Wallis',
				'Fribourg' => 'Freiburg',
				'Neuchâtel' => 'Neuenburg',
			];
			if (isset($mustDiffer[$msgid]) && $translated !== $mustDiffer[$msgid]) {
				$englishLeft[] = $msgid . '=>' . $translated;
			}
		}

		$this->assertSame([], $missing, 'Missing CH canton keys in de.json: ' . implode(', ', $missing));
		$this->assertSame([], $englishLeft, 'Wrong CH canton DE translations: ' . implode(', ', $englishLeft));
	}
}
