<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Layout contract: table-heavy pages use full-width shell; admin list pages merge shell params.
 */
class PageShellLayoutTest extends TestCase
{
	public function testWideShellIncludesTableHeavyPageIds(): void
	{
		$controller = new ReflectionClass(\OCA\ArbeitszeitCheck\Controller\PageController::class);
		$constant = $controller->getConstant('WIDE_SHELL_PAGE_IDS');
		$this->assertIsArray($constant);

		foreach ([
			'admin-users',
			'time-entries',
			'absences',
			'manager-time-entries',
			'manager-absences',
			'compliance-violations',
			'substitution-requests',
		] as $pageId) {
			$this->assertContains($pageId, $constant, 'Expected wide shell for ' . $pageId);
		}
	}

	public function testAdminUsersControllerMergesShellParams(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../lib/Controller/AdminController.php');
		$this->assertStringContainsString("'admin-users', array_merge(", $content);
		$this->assertStringContainsString("buildAdminShellParams(\n\t\t\t\t'admin-users'", $content);
	}

	public function testWorkingTimeModelsControllerMergesShellParams(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../lib/Controller/AdminController.php');
		$this->assertStringContainsString("'admin-working-time-models'", $content);
		$this->assertStringContainsString("buildAdminShellParams(\n\t\t\t\t'admin-working-time-models'", $content);
	}

	public function testSettingsUsesConstrainedShell(): void
	{
		$controller = new ReflectionClass(\OCA\ArbeitszeitCheck\Controller\PageController::class);
		$constant = $controller->getConstant('CONSTRAINED_SHELL_PAGE_IDS');
		$this->assertIsArray($constant);
		$this->assertContains('settings', $constant);
	}

	public function testManagerScopePagesUseFullWidthLayout(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../css/manager-time-entries.css');
		$this->assertStringContainsString('.manager-scope-page', $content);
		$this->assertStringNotContainsString('max-width: 56rem', $content);
	}
}
