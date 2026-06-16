<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\DashboardDeskletConfigService;
use OCA\ArbeitszeitCheck\Service\DashboardDeskletRenderService;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Guards against regressions that broke the NC dashboard widget (invalid ITemplateManager).
 */
class DashboardDeskletRenderServiceTest extends TestCase
{
	public function testSourceDoesNotReferenceNonExistentTemplateManager(): void
	{
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/lib/Service/DashboardDeskletRenderService.php',
		);
		$this->assertIsString($source);
		$this->assertStringNotContainsString('ITemplateManager', $source);
		$this->assertStringContainsString('new \\OCP\\Template', $source);
	}

	public function testRenderForUserReturnsConfigAndHtml(): void
	{
		$config = ['status' => 'clocked_out', 'l10n' => []];
		$deskletConfig = $this->createMock(DashboardDeskletConfigService::class);
		$deskletConfig->expects($this->once())
			->method('buildForUser')
			->with('user1')
			->willReturn($config);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$service = new DashboardDeskletRenderService($deskletConfig, $l10n);
		$result = $service->renderForUser('user1');

		$this->assertSame($config, $result['config']);
		$this->assertIsString($result['workspaceHtml']);
		$this->assertStringContainsString('data-arbeitszeitcheck-desklet', $result['workspaceHtml']);
		$this->assertStringContainsString('dz-error-panel', $result['workspaceHtml']);
		$this->assertStringContainsString('dz-retry', $result['workspaceHtml']);
		$this->assertStringContainsString('dz-status-section', $result['workspaceHtml']);
	}
}
