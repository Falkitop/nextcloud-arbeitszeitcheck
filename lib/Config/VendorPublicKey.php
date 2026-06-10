<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Config;

/**
 * Embedded vendor Ed25519 public key (32 bytes) for AZC2 verification.
 *
 * Default matches tests/fixtures/license_azc2.json and @arbeitszeitcheck/licensing dev key.
 *
 * Production: set environment variable AZC_VENDOR_PUBLIC_KEY_B64 on the PHP process
 * (Docker, php-fpm pool, or occ) to the public key from ~/ops/azc/.azc-signing-key.
 * Run: php scripts/print-azc-vendor-public-key.php ~/ops/azc/.azc-signing-key
 */
final class VendorPublicKey
{
	/** Dev/CI default — same bytes in native apps and PHPUnit fixture. */
	public const DEFAULT_PUBLIC_KEY_B64 = '-WT78_07UKj8JKtoVuwzRr3Y30fDXnlb0CBi_1sMdIc';

	/** @deprecated use publicKeyB64() */
	public const PUBLIC_KEY_B64 = self::DEFAULT_PUBLIC_KEY_B64;

	public static function publicKeyB64(): string
	{
		$fromEnv = getenv('AZC_VENDOR_PUBLIC_KEY_B64');
		if (is_string($fromEnv) && trim($fromEnv) !== '') {
			return trim($fromEnv);
		}
		return self::DEFAULT_PUBLIC_KEY_B64;
	}

	public static function bytes(): string
	{
		$decoded = self::base64urlDecode(self::publicKeyB64());
		if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
			throw new \RuntimeException('Invalid vendor public key configuration.');
		}
		return $decoded;
	}

	public static function base64urlDecode(string $data): string|false
	{
		$padded = strtr($data, '-_', '+/');
		$padLen = (4 - strlen($padded) % 4) % 4;
		return base64_decode($padded . str_repeat('=', $padLen), true);
	}

	public static function base64urlEncode(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Derive base64url public key from a hex Ed25519 seed file (~/ops/azc/.azc-signing-key).
	 */
	public static function publicKeyB64FromSeedFile(string $seedFilePath): string
	{
		if (!is_readable($seedFilePath)) {
			throw new \InvalidArgumentException('Signing seed file is not readable: ' . $seedFilePath);
		}
		$hex = trim((string)file_get_contents($seedFilePath));
		if ($hex === '' || !ctype_xdigit($hex)) {
			throw new \InvalidArgumentException('Signing seed file must contain hex Ed25519 seed.');
		}
		$seed = hex2bin($hex);
		if ($seed === false || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
			throw new \InvalidArgumentException('Invalid Ed25519 seed length.');
		}
		$keypair = sodium_crypto_sign_seed_keypair($seed);
		$publicKey = sodium_crypto_sign_publickey($keypair);
		return self::base64urlEncode($publicKey);
	}
}
