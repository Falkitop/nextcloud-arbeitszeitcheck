<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Ensures shared ProjectCheck picker markup never emits blank <option> labels.
 */
class ProjectCheckPickerOptionsTest extends TestCase
{
	public function testRendersGroupedLabelsAndSkipsBlankNames(): void
	{
		require_once __DIR__ . '/../fixtures/render-projectcheck-picker-options.php';

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text, array $args = []) => $args === []
			? $text
			: str_replace(['%s', '%n'], [(string)($args[0] ?? ''), (string)($args[0] ?? '')], $text));

		$projects = [
			['id' => '6', 'name' => 'KKK', 'customerName' => 'Fritz Cola', 'displayName' => 'KKK (Fritz Cola)'],
			['id' => '4', 'name' => '', 'customerName' => 'Acme', 'displayName' => 'Fallback Project'],
			['id' => '', 'name' => 'No id', 'customerName' => 'X'],
		];

		$html = azc_test_render_projectcheck_picker_options($projects, '99', $l);

		$this->assertStringContainsString('No project — just track my time', $html);
		$this->assertStringContainsString('<optgroup label="Fritz Cola">', $html);
		$this->assertStringContainsString('value="6"', $html);
		$this->assertStringContainsString('>KKK<', $html);
		$this->assertStringContainsString('Fallback Project', $html);
		$this->assertStringNotContainsString('value="4"></option>', $html);
		$this->assertStringContainsString('Linked project #99', $html);
		$this->assertStringNotContainsString('No id', $html);
	}
}
