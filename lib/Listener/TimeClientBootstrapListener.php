<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Listener;

use OCA\ArbeitszeitCheck\Support\TimeClientBootstrap;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;

/**
 * Ensures timezone InitialState is present on every logged-in ArbeitszeitCheck page,
 * even when a controller/template forgot to include `time-bootstrap.php`.
 *
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 */
class TimeClientBootstrapListener implements IEventListener {
	public function __construct(
		private readonly TimeClientBootstrap $timeClientBootstrap,
		private readonly IRequest $request,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent || !$event->isLoggedIn()) {
			return;
		}

		if (!$this->isArbeitszeitCheckRequest()) {
			return;
		}

		$this->timeClientBootstrap->registerConfig();
	}

	private function isArbeitszeitCheckRequest(): bool {
		$path = $this->request->getPathInfo();
		if (str_contains($path, '/apps/arbeitszeitcheck') || str_contains($path, 'arbeitszeitcheck')) {
			return true;
		}

		$uri = $this->request->getRequestUri();
		return is_string($uri) && str_contains($uri, 'arbeitszeitcheck');
	}
}
