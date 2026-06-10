<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Kiosk;

/**
 * Hashing helpers for kiosk tokens and credentials (never store plaintext secrets).
 */
final class KioskCrypto
{
	public static function generateToken(int $bytes = 32): string
	{
		return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
	}

	public static function generatePairingCode(int $length = 8): string
	{
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$code = '';
		for ($i = 0; $i < $length; $i++) {
			$code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
		}
		return $code;
	}

	public static function generatePin(): string
	{
		return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
	}

	public static function hashSecret(string $secret): string
	{
		$hash = password_hash($secret, PASSWORD_ARGON2ID);
		if ($hash === false) {
			throw new \RuntimeException('Failed to hash kiosk secret.');
		}
		return $hash;
	}

	public static function verifySecret(string $secret, string $hash): bool
	{
		return password_verify($secret, $hash);
	}

	public static function rfidLookupHash(string $normalizedUid, string $salt): string
	{
		return hash_hmac('sha256', $normalizedUid, $salt);
	}

	public static function normalizeRfidUid(string $uid): string
	{
		return strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $uid) ?? '');
	}
}
