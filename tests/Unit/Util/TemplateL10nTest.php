<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Util;

use OCA\ArbeitszeitCheck\Util\TemplateL10n;
use PHPUnit\Framework\TestCase;

class TemplateL10nTest extends TestCase {
	public function testPlaceholderArgumentsForPercentD(): void {
		$arguments = TemplateL10n::placeholderArguments('Date range must not exceed %d days.');
		$this->assertCount(1, $arguments);
		$this->assertIsInt($arguments[0]);
	}

	public function testPlaceholderArgumentsForPercentS(): void {
		$this->assertSame(
			['%s', '%s'],
			TemplateL10n::placeholderArguments('Delete "%s"? Members: %s'),
		);
	}

	public function testPlaceholderArgumentsForPlainMessage(): void {
		$this->assertSame([], TemplateL10n::placeholderArguments('Loading...'));
	}

	public function testPlaceholderArgumentsForPositionalPercentS(): void {
		$this->assertSame(
			['%1$s', '%2$s', '%3$s'],
			TemplateL10n::placeholderArguments('%1$s pending (%2$s h), %3$s already paid.'),
		);
	}

	public function testTranslatePreservesPositionalPlaceholdersForClientSideReplacement(): void {
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $id, array $params): string {
			return vsprintf($id, $params);
		});

		$this->assertSame(
			'%1$s pending (%2$s h), %3$s already paid.',
			TemplateL10n::translate($l, '%1$s pending (%2$s h), %3$s already paid.'),
		);
	}

	public function testTranslatePreservesPositionalNumericPlaceholders(): void {
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $id, array $params): string {
			return vsprintf($id, $params);
		});

		$this->assertSame(
			'%1$d–%2$d of %3$d entries',
			TemplateL10n::translate($l, '%1$d–%2$d of %3$d entries'),
		);
		$this->assertSame(
			'Page %1$d of %2$d',
			TemplateL10n::translate($l, 'Page %1$d of %2$d'),
		);
	}

	public function testTranslatePreservesSequentialNumericPlaceholders(): void {
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $id, array $params): string {
			return vsprintf($id, $params);
		});

		$this->assertSame(
			'Date range must not exceed %d days. Please narrow the range.',
			TemplateL10n::translate($l, 'Date range must not exceed %d days. Please narrow the range.'),
		);
	}

	public function testTranslatePreservesPlaceholdersInReorderedTranslations(): void {
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $id, array $params): string {
			// Simulate a German translation that reorders the placeholders
			return vsprintf('Seite %2$d, davon %1$d', $params);
		});

		$this->assertSame(
			'Seite %2$d, davon %1$d',
			TemplateL10n::translate($l, 'Page %1$d of %2$d'),
		);
	}
}
