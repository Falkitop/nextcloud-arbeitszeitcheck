<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\TariffRuleModuleValidator;
use PHPUnit\Framework\TestCase;

class TariffRuleModuleValidatorTest extends TestCase {
	public function testRejectsEmptyModuleList(): void {
		$this->assertNotEmpty(TariffRuleModuleValidator::validateList([]));
	}

	public function testAcceptsValidBaseFormula(): void {
		$errors = TariffRuleModuleValidator::validateList([
			[
				'moduleType' => 'base_formula',
				'config' => [
					'reference_days' => 30,
					'reference_week_days' => 5,
					'work_days_per_week' => 5,
				],
			],
		]);
		$this->assertSame([], $errors);
	}

	public function testRejectsMissingBaseFormula(): void {
		$errors = TariffRuleModuleValidator::validateList([
			[
				'moduleType' => 'additional_entitlements',
				'config' => ['days' => 1],
			],
		]);
		$this->assertArrayHasKey('modules', $errors);
	}

	public function testAcceptsLegacyProRataAliases(): void {
		$errors = TariffRuleModuleValidator::validateList([
			[
				'moduleType' => 'base_formula',
				'config' => ['reference_days' => 30, 'reference_week_days' => 5],
			],
			[
				'moduleType' => 'pro_rata_rule',
				'config' => ['mode' => 'month'],
			],
		]);
		$this->assertSame([], $errors);
	}

	public function testRejectsInvalidProRataMode(): void {
		$errors = TariffRuleModuleValidator::validateList([
			[
				'moduleType' => 'base_formula',
				'config' => ['reference_days' => 30, 'reference_week_days' => 5],
			],
			[
				'moduleType' => 'pro_rata_rule',
				'config' => ['mode' => 'bogus'],
			],
		]);
		$this->assertArrayHasKey('modules', $errors);
	}

	public function testAcceptsMonthlyProRata(): void {
		$errors = TariffRuleModuleValidator::validateList([
			[
				'moduleType' => 'base_formula',
				'config' => ['reference_days' => 30, 'reference_week_days' => 5],
			],
			[
				'moduleType' => 'pro_rata_rule',
				'config' => ['mode' => 'monthly'],
			],
		]);
		$this->assertSame([], $errors);
	}
}
