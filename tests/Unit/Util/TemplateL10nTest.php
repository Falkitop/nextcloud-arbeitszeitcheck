<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Util;

use OCA\ArbeitszeitCheck\Util\TemplateL10n;
use PHPUnit\Framework\TestCase;

class TemplateL10nTest extends TestCase {
	public function testPlaceholderArgumentsForPercentD(): void {
		$this->assertSame(
			[0],
			TemplateL10n::placeholderArguments('Date range must not exceed %d days.'),
		);
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
}
