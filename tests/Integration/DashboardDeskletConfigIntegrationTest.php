<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Integration;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Service\DashboardDeskletConfigService;
use OCP\IURLGenerator;
use Test\TestCase;

/**
 * Guards the NC home dashboard desklet: routes must exist before linkToRoute().
 */
class DashboardDeskletConfigIntegrationTest extends TestCase {
	public function testBuildForUserResolvesDeskletApiUrlsWhenAppRoutesWereNotLoadedYet(): void {
		$urlGenerator = \OC::$server->get(IURLGenerator::class);

		$this->assertSame('', $urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.employeeData'));

		$service = \OC::$server->get(DashboardDeskletConfigService::class);
		$config = $service->buildForUser('admin');

		$this->assertStringContainsString('/api/dashboard-widget/employee', (string)$config['employeeDataUrl']);
		$this->assertStringContainsString('/api/dashboard-widget/clock/in', (string)$config['clockInUrl']);
		$this->assertStringContainsString('/dashboard', (string)$config['dashboardUrl']);
		$this->assertTrue(\OC_App::isAppLoaded(Application::APP_ID));
	}
}
