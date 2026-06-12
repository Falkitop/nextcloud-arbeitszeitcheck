<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use OCA\ArbeitszeitCheck\Util\TemplateL10n;
use PHPUnit\Framework\TestCase;

/**
 * Guards against Internal Server Error on create/edit when vsprintf placeholders lack arguments.
 */
class TimeEntryFormL10nTest extends TestCase {
	public function testAutoBreakAddedCompliancePreservesClientSidePlaceholder(): void {
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $id, array $params): string {
			return vsprintf($id, $params);
		});

		$this->assertSame(
			'Automatic %s break added for legal compliance',
			TemplateL10n::translate($l, 'Automatic %s break added for legal compliance'),
		);
	}

	public function testBreakRowLabelsPreservePositionalPlaceholders(): void {
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $id, array $params): string {
			return vsprintf($id, $params);
		});

		$this->assertSame(
			'Break %1$s start',
			TemplateL10n::translate($l, 'Break %1$s start'),
		);
		$this->assertSame(
			'Break %1$s end',
			TemplateL10n::translate($l, 'Break %1$s end'),
		);
	}

	public function testMaxBreaksAllowedPreservesNumericPlaceholder(): void {
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $id, array $params): string {
			return vsprintf($id, $params);
		});

		// The placeholder survives translation; js/time-entry-form.js substitutes the limit.
		$this->assertSame(
			'Maximum of %d breaks allowed',
			TemplateL10n::translate($l, 'Maximum of %d breaks allowed'),
		);
	}
}
