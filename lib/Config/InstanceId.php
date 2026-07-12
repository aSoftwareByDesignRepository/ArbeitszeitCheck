<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Config;

use OCP\IConfig;

/**
 * Stable Nextcloud instance identifier used for AZC2 instance binding.
 */
final class InstanceId
{
	public function __construct(
		private readonly IConfig $config,
	) {
	}

	public function get(): string
	{
		$id = trim($this->config->getSystemValueString('instanceid', ''));
		return $id !== '' ? $id : 'unknown-instance';
	}
}
