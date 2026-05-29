<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Capabilities;
use OCA\ArbeitszeitCheck\Controller\MobileBootstrapController;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory as L10NFactory;
use PHPUnit\Framework\TestCase;

class MobileBootstrapControllerTest extends TestCase {
	public function testBootstrapReturnsEmployeePayload(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$user->method('getDisplayName')->willReturn('Alice');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$widget = $this->createMock(DashboardWidgetDataService::class);
		$widget->method('getEmployeeWidgetData')->willReturn(['status' => 'clocked_out']);

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canAccessManagerDashboard')->willReturn(false);
		$permissions->method('isAdmin')->willReturn(false);

		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(false);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn(true);

		$config = $this->createMock(IConfig::class);
		$appConfig = $this->createMock(IAppConfig::class);

		$l10nFactory = $this->createMock(L10NFactory::class);
		$l10nFactory->method('findLanguage')->willReturn('de');

		$capabilities = $this->createMock(Capabilities::class);
		$capabilities->method('getCapabilities')->willReturn(['arbeitszeitcheck' => ['version' => '1.3.9']]);

		$controller = new MobileBootstrapController(
			'arbeitszeitcheck',
			$this->createMock(IRequest::class),
			$userSession,
			$widget,
			$permissions,
			$bank,
			$appManager,
			$config,
			$appConfig,
			$l10nFactory,
			$capabilities,
		);

		$response = $controller->bootstrap();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertSame('alice', $data['data']['userId']);
		$this->assertTrue($data['data']['pushAvailable']);
		$this->assertSame('clocked_out', $data['data']['employee']['status']);
	}

	public function testBootstrapUnauthorizedWithoutUser(): void {
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		$controller = new MobileBootstrapController(
			'arbeitszeitcheck',
			$this->createMock(IRequest::class),
			$userSession,
			$this->createMock(DashboardWidgetDataService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(OvertimeBankService::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IConfig::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(L10NFactory::class),
			$this->createMock(Capabilities::class),
		);

		$response = $controller->bootstrap();
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}
}
