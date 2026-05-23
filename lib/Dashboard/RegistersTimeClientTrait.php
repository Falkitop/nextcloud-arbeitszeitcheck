<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Dashboard;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Support\TimeClientBootstrap;
use OCP\Util;

/**
 * Dashboard widgets render outside app templates; they must register the
 * timezone client stack and desklet styles in {@see load()} themselves.
 */
trait RegistersTimeClientTrait {
	private function registerTimeClientForWidget(TimeClientBootstrap $timeClientBootstrap): void {
		$timeClientBootstrap->register();
	}

	private function registerDeskletStylesForWidget(): void {
		Util::addStyle(Application::APP_ID, 'desklet-nextcloud');
	}
}
