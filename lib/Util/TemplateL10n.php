<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Util;

use OCP\IL10N;

/**
 * Safe server-side translations for templates (avoids json_encode on lazy IL10NString / vsprintf crashes).
 */
final class TemplateL10n {
	public const JSON_ENCODE_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

	/**
	 * @param list<mixed> $parameters
	 */
	public static function translate(IL10N $l, string $id, array $parameters = []): string {
		if ($parameters === []) {
			$parameters = self::placeholderArguments($id);
		}

		return (string) $l->t($id, $parameters);
	}

	/**
	 * @param list<string> $messageIds Message ids used as both array keys and translation ids
	 *
	 * @return array<string, string>
	 */
	public static function mapFromMessageIds(IL10N $l, array $messageIds): array {
		$map = [];
		foreach ($messageIds as $messageId) {
			$map[$messageId] = self::translate($l, $messageId);
		}

		return $map;
	}

	/**
	 * Default vsprintf arguments when a message still contains format placeholders after translation.
	 * Mirrors templates/common/teams-l10n.php (%s → literal "%s" for client-side replacement).
	 *
	 * @return list<int|string>
	 */
	public static function placeholderArguments(string $id): array {
		if (preg_match_all('/%(?:\d+\$)?[dif]/', $id, $matches) !== false && $matches[0] !== []) {
			$args = [];
			foreach ($matches[0] as $spec) {
				$args[] = (str_ends_with($spec, 'd') || str_ends_with($spec, 'i')) ? 0 : '%s';
			}

			return $args;
		}

		$sprintfCount = substr_count($id, '%s');
		if ($sprintfCount > 0) {
			return array_fill(0, $sprintfCount, '%s');
		}

		return [];
	}
}
