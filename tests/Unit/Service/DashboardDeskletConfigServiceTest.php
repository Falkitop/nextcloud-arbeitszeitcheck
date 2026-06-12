<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\DashboardDeskletConfigService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class DashboardDeskletConfigServiceTest extends TestCase {
	public function testBuildL10nPreservesPlaceholderTemplates(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $id, array $params = []): string {
			return vsprintf($id, $params);
		});

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturn('/route');

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canAccessManagerDashboard')->willReturn(false);
		$permissions->method('isAdmin')->willReturn(false);

		$service = new DashboardDeskletConfigService($urlGenerator, $permissions, $l10n);
		$config = $service->buildForUser('alice');
		$l10nMap = $config['l10n'];

		$this->assertSame('Status: %1$s', $l10nMap['statusLine']);
		$this->assertSame('Last updated: %1$s', $l10nMap['lastUpdated']);
		$this->assertSame('%1$s successful', $l10nMap['actionDone']);
		$this->assertSame('%1$s: %2$s (%3$s h)', $l10nMap['peopleRow']);
		$this->assertSame('Working', $l10nMap['working']);
	}
}
