<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Dashboard;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCP\IURLGenerator;
use RuntimeException;

/**
 * Resolves dashboard / desklet icon URLs for all Nextcloud themes.
 *
 * Per {@see \OCP\Dashboard\IIconWidget}: icons must be black (or uncoloured);
 * clients apply {@code --background-invert-if-dark}. {@see app-dashboard.svg}
 * is 44×44 for {@see \OCP\Dashboard\Model\WidgetItem}; list rows also use the
 * same asset via {@see desklet-nextcloud.css} because the dashboard Vue item
 * component falls back to a generic file icon when {@code img} load fails.
 */
final class WidgetIconHelper {
	public function __construct(
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function getAbsoluteIconUrl(): string {
		foreach (['app-dashboard.svg', 'app-dark.svg', 'app.svg'] as $iconFile) {
			try {
				return $this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, $iconFile)
				);
			} catch (RuntimeException) {
				// Try next candidate.
			}
		}

		try {
			return $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath('core', 'actions/history.svg')
			);
		} catch (RuntimeException) {
			return '';
		}
	}
}
