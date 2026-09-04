<?php

declare(strict_types=1);

/**
 * Persist explicit feed label language on Outlook iCal subscription tokens.
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1041Date20260819210000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('azc_outlook_ical_tokens')) {
			return null;
		}

		$table = $schema->getTable('azc_outlook_ical_tokens');
		if ($table->hasColumn('feed_language_code')) {
			return null;
		}

		$table->addColumn('feed_language_code', Types::STRING, [
			'notnull' => true,
			'length' => 16,
			'default' => 'en',
		]);

		return $schema;
	}
}
