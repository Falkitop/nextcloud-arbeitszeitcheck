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
}
