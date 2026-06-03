<?php

declare(strict_types=1);

/**
 * Validates tariff rule module payloads for admin create/update/activate.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

final class TariffRuleModuleValidator {
	public const MODULE_BASE_FORMULA = 'base_formula';
	public const MODULE_ADDITIONAL = 'additional_entitlements';
	public const MODULE_DEDUCTIONS = 'deductions';
	public const MODULE_ROUNDING = 'rounding_rule';
	public const MODULE_PRO_RATA = 'pro_rata_rule';

	private const ALLOWED_TYPES = [
		self::MODULE_BASE_FORMULA,
		self::MODULE_ADDITIONAL,
		self::MODULE_DEDUCTIONS,
		self::MODULE_ROUNDING,
		self::MODULE_PRO_RATA,
	];

	private const ROUNDING_MODES = ['commercial', 'ceil', 'floor', 'half_day'];
	private const PRO_RATA_MODES = ['none', 'monthly', 'daily'];

	/**
	 * @param list<array<string, mixed>> $modules
	 * @return array<string, string> field => English message key for l10n->t()
	 */
	public static function validateList(array $modules): array {
		$errors = [];
		if ($modules === []) {
			$errors['modules'] = 'At least one calculation module is required';
			return $errors;
		}

		$baseCount = 0;
		foreach ($modules as $index => $module) {
			if (!is_array($module)) {
				$errors['modules'] = 'Invalid module payload';
				return $errors;
			}
			$type = isset($module['moduleType']) ? trim((string)$module['moduleType']) : '';
			if ($type === '' || !in_array($type, self::ALLOWED_TYPES, true)) {
				$errors['modules'] = 'Invalid or missing module type';
				return $errors;
			}
			$config = $module['config'] ?? [];
			if (!is_array($config)) {
				$errors['modules'] = 'Invalid module configuration';
				return $errors;
			}

			if ($type === self::MODULE_BASE_FORMULA) {
				$baseCount++;
				$refDays = self::readNumber($config['reference_days'] ?? null);
				$refWeek = self::readNumber($config['reference_week_days'] ?? null);
				$workWeek = self::readNumber($config['work_days_per_week'] ?? null);
				if ($refDays === null || $refDays < 0 || $refDays > 366) {
					$errors['modules'] = 'Base formula requires reference days between 0 and 366';
					return $errors;
				}
				if ($refWeek === null || $refWeek < 1 || $refWeek > 7) {
					$errors['modules'] = 'Base formula requires reference working days per week between 1 and 7';
					return $errors;
				}
				if ($workWeek !== null && ($workWeek < 1 || $workWeek > 7)) {
					$errors['modules'] = 'Working days per week must be between 1 and 7';
					return $errors;
				}
			} elseif ($type === self::MODULE_ADDITIONAL || $type === self::MODULE_DEDUCTIONS) {
				$days = self::readNumber($config['days'] ?? null);
				if ($days === null || $days < 0 || $days > 366) {
					$errors['modules'] = 'Days must be between 0 and 366';
					return $errors;
				}
			} elseif ($type === self::MODULE_ROUNDING) {
				$mode = isset($config['mode']) ? trim((string)$config['mode']) : '';
				if ($mode === '' || !in_array($mode, self::ROUNDING_MODES, true)) {
					$errors['modules'] = 'Invalid rounding mode';
					return $errors;
				}
			} elseif ($type === self::MODULE_PRO_RATA) {
				$mode = self::normalizeProRataMode(isset($config['mode']) ? trim((string)$config['mode']) : '');
				if ($mode === '' || !in_array($mode, self::PRO_RATA_MODES, true)) {
					$errors['modules'] = 'Invalid pro-rata mode';
					return $errors;
				}
			}

			unset($index);
		}

		if ($baseCount < 1) {
			$errors['modules'] = 'A base formula module is required';
		}

		return $errors;
	}

	public static function normalizeProRataMode(string $mode): string
	{
		return match ($mode) {
			'month' => 'monthly',
			'day' => 'daily',
			default => $mode,
		};
	}

	private static function readNumber(mixed $raw): ?float {
		if ($raw === null || $raw === '') {
			return null;
		}
		if (!is_numeric($raw)) {
			return null;
		}
		$value = (float)$raw;
		return is_finite($value) ? $value : null;
	}
}
