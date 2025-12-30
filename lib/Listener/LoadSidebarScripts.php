<?php

declare(strict_types=1);

/**
 * LoadSidebarScripts listener for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * LoadSidebarScripts
 */
class LoadSidebarScripts implements IEventListener {

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		// Load scripts and styles when the sidebar is loaded
		Util::addScript('arbeitszeitcheck', 'arbeitszeitcheck-main');
		Util::addStyle('arbeitszeitcheck', 'arbeitszeitcheck-main');
	}
}