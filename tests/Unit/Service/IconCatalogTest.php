<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\IconCatalog;
use PHPUnit\Framework\TestCase;

class IconCatalogTest extends TestCase
{
	public function testAlertTriangleHasVisibleExclamationMark(): void
	{
		$svg = IconCatalog::render('alert-triangle');
		$this->assertStringContainsString('m12 3 10 17H2Z', $svg);
		$this->assertStringContainsString('M12 9v4', $svg);
		$this->assertStringContainsString('circle cx="12" cy="17"', $svg);
		$this->assertStringNotContainsString('h.01', $svg);
	}

	public function testRenderCalloutWellIncludesVariantClass(): void
	{
		$html = IconCatalog::renderCalloutWell('alert-triangle', 'warning');
		$this->assertStringContainsString('azc-notif-icon-well--warning', $html);
		$this->assertStringContainsString('azc-callout__icon-svg', $html);
	}

	public function testRenderCalloutWellMapsErrorToDanger(): void
	{
		$html = IconCatalog::renderCalloutWell('circle-alert', 'error');
		$this->assertStringContainsString('azc-notif-icon-well--danger', $html);
	}
}
