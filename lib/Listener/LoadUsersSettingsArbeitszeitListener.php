<?php

declare(strict_types=1);

/**
 * Injects optional overtime "Stichtag" fields into Nextcloud core user management
 * (Konten → Benutzer) for ArbeitszeitCheck app administrators.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Listener;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\Settings\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Util;

/**
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 */
final class LoadUsersSettingsArbeitszeitListener implements IEventListener {
	private const URL_UID_PLACEHOLDER = 'AZC_UID_TMPL';

	public function __construct(
		private readonly IUserSession $userSession,
		private readonly PermissionService $permissionService,
		private readonly IInitialState $initialState,
		private readonly IURLGenerator $urlGenerator,
		private readonly IFactory $l10nFactory,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null || !$this->permissionService->isAdmin($user->getUID())) {
			return;
		}

		$l10n = $this->l10nFactory->get(Application::APP_ID);

		$putUrlTemplate = $this->urlGenerator->linkToRouteAbsolute(
			'arbeitszeitcheck.admin.updateUserOvertimeSettings',
			['userId' => self::URL_UID_PLACEHOLDER]
		);

		$this->initialState->provideInitialState('arbeitszeitcheckNewUserOvertime', [
			'overtimePutUrlTemplate' => $putUrlTemplate,
			'uidPlaceholder' => self::URL_UID_PLACEHOLDER,
			'strings' => [
				'fieldset' => $l10n->t('ArbeitszeitCheck'),
				'trackingLabel' => $l10n->t('Overtime tracking from (optional)'),
				'trackingHelp' => $l10n->t('If set, this date is saved as the overtime “Stichtag” when the new account is created. Leave empty to configure later in ArbeitszeitCheck → Administration → Employees.'),
				'toastApplied' => $l10n->t('Overtime tracking start date saved for the new account.'),
				'toastSkipped' => $l10n->t('Account created. Overtime start date was not applied (check ArbeitszeitCheck permissions or try again from Employees).'),
			],
		]);

		Util::addStyle(Application::APP_ID, 'settings-users-overtime');
		Util::addScript(Application::APP_ID, 'settings-users-overtime');
	}
}
