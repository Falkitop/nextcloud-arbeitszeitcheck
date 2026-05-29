<?php

declare(strict_types=1);

/**
 * Capabilities class for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck;

use OCA\ArbeitszeitCheck\Service\MonthClosureFeature;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Capabilities\ICapability;
use OCP\IConfig;
use OCP\IUserSession;

/**
 * Class Capabilities
 */
class Capabilities implements ICapability {
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
		private readonly OvertimeBankService $overtimeBankService,
		private readonly IAppManager $appManager,
		private readonly IUserSession $userSession,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getCapabilities(): array {
		$user = $this->userSession->getUser();
		$pushAvailable = $user !== null
			&& $this->appManager->isEnabledForUser('notifications', $user);

		return [
			'arbeitszeitcheck' => [
				'version' => '1.3.9',
				'features' => [
					'time-tracking',
					'compliance-monitoring',
					'absence-management',
					'reporting',
					'gdpr-compliance',
					'arbzg-compliance',
					'accessibility-wcag-aaa',
					'projectcheck-integration',
				],
				'mobile' => [
					'minAppVersion' => '1.0.0',
					'bootstrapEndpoint' => '/api/mobile/bootstrap',
					'pushAvailable' => $pushAvailable,
					'monthClosure' => MonthClosureFeature::isEnabledFromIConfig($this->config),
					'overtimeBank' => $this->overtimeBankService->isEnabled(),
					'layeredVacationEntitlements' => $this->appConfig->getAppValueString('layered_entitlements_enabled', '0') === '1',
				],
				'compliance' => [
					'german-labor-law' => true,
					'gdpr' => true,
					'audit-logging' => true,
					'data-retention' => true,
				],
				'accessibility' => [
					'wcag-level' => 'AAA',
					'screen-reader' => true,
					'keyboard-navigation' => true,
					'high-contrast' => true,
				],
			],
		];
	}
}
