<?php

declare(strict_types=1);

/**
 * Render picker options in the global namespace (matches Nextcloud template runtime).
 *
 * @param list<array<string, mixed>> $projects
 */
function azc_test_render_projectcheck_picker_options(array $projects, string $selectedId, \OCP\IL10N $l): string
{
	if (!\function_exists('p')) {
		/**
		 * @param string|list<string> $text
		 */
		function p($text, $args = []): void
		{
			if (\is_array($args) && $args !== []) {
				echo \str_replace(['%s', '%n'], [(string)($args[0] ?? ''), (string)($args[0] ?? '')], (string)$text);
				return;
			}
			echo (string)$text;
		}
	}

	$azcPickerProjects = $projects;
	$azcPickerSelectedId = $selectedId;

	\ob_start();
	include __DIR__ . '/../../templates/common/projectcheck-picker-options.php';

	return (string)\ob_get_clean();
}
