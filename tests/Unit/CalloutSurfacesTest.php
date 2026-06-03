<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Notification/callout surface contract — theme-safe tokens and shared partial.
 */
class CalloutSurfacesTest extends TestCase
{
	public function testNotificationSurfacesUseThemeTintsNotRawRgbaRgb(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../css/common/notification-surfaces.css');

		$this->assertStringContainsString('--azc-tint-warning', $content);
		$this->assertStringContainsString('--azc-tint-danger', $content);
		$this->assertStringContainsString('--azc-tint-success', $content);
		$this->assertStringContainsString('--azc-tint-info', $content);
		$this->assertStringNotContainsString('rgba(var(--color-warning-rgb', $content);
		$this->assertStringNotContainsString('rgba(var(--color-error-rgb', $content);
		$this->assertStringContainsString('.azc-nc-admin-settings .azc-callout--warning', $content);
	}

	public function testTokensDefineNotificationWellsOnBody(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../css/common/tokens.css');

		$this->assertStringContainsString('--azc-notif-fg-warning:', $content);
		$this->assertStringContainsString('--color-warning-text', $content);
		$this->assertStringContainsString('--azc-tint-warning:', $content);
	}

	public function testAlertCalloutPartialUsesIconWellAndBody(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../templates/common/alert-callout.php');

		$this->assertStringContainsString('renderCalloutWell', $content);
		$this->assertStringContainsString('azc-callout__body', $content);
		$this->assertStringContainsString('azc-callout__title', $content);
	}

	public function testComplianceDashboardWarningCalloutHasTitleId(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../templates/compliance-dashboard.php');

		$this->assertStringContainsString('compliance-status-warning-title', $content);
		$this->assertStringContainsString('alert-callout.php', $content);
	}

	public function testNotificationSurfacesUseTintedWellsAndSemanticStroke(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../css/common/notification-surfaces.css');

		$this->assertStringContainsString('background: var(--azc-tint-warning)', $content);
		$this->assertStringContainsString('color: var(--azc-notif-fg-warning)', $content);
		$this->assertStringContainsString('.azc-notif-icon-well--warning', $content);
		$this->assertStringContainsString('azc-semantic-panel--warning .azc-callout__icon', $content);
	}

	public function testDashboardAlertsUseAlertCalloutPartial(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../templates/dashboard.php');

		$this->assertStringContainsString('azc-dashboard-alerts', $content);
		$this->assertGreaterThanOrEqual(
			2,
			substr_count($content, 'alert-callout.php'),
			'dashboard error and WTM alerts should use the shared partial'
		);
	}
}
