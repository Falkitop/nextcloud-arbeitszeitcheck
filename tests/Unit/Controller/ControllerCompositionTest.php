<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards against PHP 8.4 fatal errors when a controller promotes CSPService
 * while also using CSPTrait (duplicate $cspService property).
 */
class ControllerCompositionTest extends TestCase
{
	public function testControllersUsingCspTraitDoNotPromoteCspService(): void
	{
		$dir = dirname(__DIR__, 3) . '/lib/Controller';
		$this->assertDirectoryExists($dir);

		$violations = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}
			$path = $file->getPathname();
			$source = (string)file_get_contents($path);
			if (!str_contains($source, 'use CSPTrait')) {
				continue;
			}
			if (preg_match('/private\s+(?:readonly\s+)?CSPService\s+\$cspService/', $source) === 1) {
				$violations[] = basename($path);
			}
		}

		$this->assertSame(
			[],
			$violations,
			'Controllers must not promote CSPService when using CSPTrait: ' . implode(', ', $violations),
		);
	}

}
