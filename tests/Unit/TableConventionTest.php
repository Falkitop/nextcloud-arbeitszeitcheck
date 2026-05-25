<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Ensures routed UI tables follow the unified table-container + table--hover convention.
 */
class TableConventionTest extends TestCase
{
	private const TEMPLATE_SKIP = [
		'admin-notifications.php', // matrix grids use azc-table--matrix
	];

	/**
	 * @return list<string>
	 */
	public static function routedTemplateProvider(): array
	{
		$dir = __DIR__ . '/../../templates';
		$files = [];
		$it = new RegexIterator(
			new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)),
			'/\.php$/'
		);
		foreach ($it as $file) {
			$path = $file->getPathname();
			if (str_contains($path, '/common/') || str_contains($path, '/email/')) {
				continue;
			}
			$base = basename($path);
			if (in_array($base, self::TEMPLATE_SKIP, true)) {
				continue;
			}
			$content = (string)file_get_contents($path);
			if (!str_contains($content, '<table')) {
				continue;
			}
			$files[] = $path;
		}
		sort($files);
		return array_map(static fn (string $p): array => [$p], $files);
	}

	/**
	 * @dataProvider routedTemplateProvider
	 */
	public function testTemplatesUseTableContainerAndHover(string $path): void
	{
		$content = (string)file_get_contents($path);

		if (!preg_match_all('/<table\b[^>]*class="([^"]*)"/', $content, $matches)) {
			return;
		}

		foreach ($matches[1] as $classAttr) {
			if (str_contains($classAttr, 'azc-table--matrix')
				|| str_contains($classAttr, 'correction-snapshot')
				|| str_contains($classAttr, 'trace-table')) {
				continue;
			}
			$this->assertStringContainsString(
				'table',
				$classAttr,
				$path . ' table must include .table class: ' . $classAttr
			);
			$this->assertStringContainsString(
				'table--hover',
				$classAttr,
				$path . ' table must include .table--hover: ' . $classAttr
			);
		}

		if (str_contains($content, 'table-responsive') && !str_contains($content, 'table-container')) {
			$this->fail($path . ' uses table-responsive without table-container — migrate to table-container');
		}
	}

	public function testPagePatternsDefineShellTableRules(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../css/common/page-patterns.css');
		$this->assertStringContainsString('#app-content.azc-app .table-container', $content);
		$this->assertStringContainsString('.azc-table-actions-col', $content);
	}

	public function testReportsJsWrapsDynamicTables(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../js/reports.js');
		$this->assertStringContainsString('table-container', $content);
		$this->assertStringContainsString('table table--hover report-table', $content);
	}
}
