#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Compare printf-style placeholder arity between l10n/en.json and l10n/de.json
 * for the same message keys. Mismatches cause runtime ValueError when IL10N
 * renders a translation with the wrong number of parameters.
 *
 * Usage (from app root):
 *   php scripts/check-l10n-placeholders.php
 *
 * Exit codes: 0 = OK, 1 = mismatches or invalid JSON.
 */

$root = dirname(__DIR__);
$enPath = $root . '/l10n/en.json';
$dePath = $root . '/l10n/de.json';

foreach ([$enPath, $dePath] as $p) {
	if (!is_readable($p)) {
		fwrite(STDERR, "Missing or unreadable: {$p}\n");
		exit(1);
	}
}

$en = json_decode((string)file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
$de = json_decode((string)file_get_contents($dePath), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($en) || !is_array($de)) {
	fwrite(STDERR, "Expected JSON objects in en.json / de.json\n");
	exit(1);
}

/**
 * Count vsprintf-style placeholders, ignoring escaped %% pairs.
 * Matches common Nextcloud patterns: %s %d %.2f %1$s %2$d
 */
function placeholder_count(string $s): int
{
	if ($s === '' || !str_contains($s, '%')) {
		return 0;
	}
	$t = str_replace('%%', '', $s);
	if (!preg_match_all(
		'/%(?:[1-9]\d*\$)?[-+ ]?(?:\d+|\*)?(?:\.\d+)?[sdifeEgGFouxXbc]/',
		$t,
		$m
	)) {
		return 0;
	}
	return count($m[0]);
}

$mismatches = [];
$missingInDe = [];

foreach ($en as $key => $enVal) {
	if (!is_string($enVal) || !str_contains($enVal, '%')) {
		continue;
	}
	$cEn = placeholder_count($enVal);
	if ($cEn === 0) {
		continue;
	}
	if (!array_key_exists($key, $de)) {
		$missingInDe[] = $key;
		continue;
	}
	$deVal = $de[$key];
	if (!is_string($deVal)) {
		$mismatches[] = [$key, $cEn, 'non-string de value'];
		continue;
	}
	$cDe = placeholder_count($deVal);
	if ($cEn !== $cDe) {
		$mismatches[] = [$key, $cEn, $cDe];
	}
}

if ($missingInDe !== []) {
	fwrite(STDERR, 'Keys with placeholders in en.json missing in de.json: ' . count($missingInDe) . "\n");
	foreach (array_slice($missingInDe, 0, 30) as $k) {
		fwrite(STDERR, "  - {$k}\n");
	}
	if (count($missingInDe) > 30) {
		fwrite(STDERR, '  ... and ' . (count($missingInDe) - 30) . " more\n");
	}
}

if ($mismatches !== []) {
	fwrite(STDERR, 'Placeholder count mismatches (en vs de): ' . count($mismatches) . "\n");
	foreach ($mismatches as $row) {
		[$key, $a, $b] = $row;
		fwrite(STDERR, "  - {$key}: en={$a} de={$b}\n");
	}
	exit(1);
}

if ($missingInDe !== []) {
	exit(1);
}

fwrite(STDOUT, "l10n placeholder check: OK (en/de arity aligned for keyed strings with placeholders).\n");
exit(0);
