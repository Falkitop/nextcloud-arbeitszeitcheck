# AZC2 vendor public key — deployment

The **public** Ed25519 key is embedded in the server app and both native apps. It is **not secret**. The **private** signing seed stays only in `~/ops/azc/.azc-signing-key` (or `sbd_signing_key_path` on your internal Nextcloud).

## Dev / CI (default)

All apps ship with the same default key as `tests/fixtures/license_azc2.json`. Demo keys from `seed-*-demo.sh` work out of the box.

## Production

1. Generate a **production** keypair (once):

   ```bash
   php ~/ops/azc/generate-keypair.php   # without --dev-test
   ```

2. Print the public key:

   ```bash
   php nextcloud/scripts/print-azc-vendor-public-key.php ~/ops/azc/.azc-signing-key
   ```

3. **Nextcloud server** — Docker (recommended):

   ```bash
   cd nextcloud
   cp .env.example .env
   # Edit .env: AZC_VENDOR_PUBLIC_KEY_B64=<base64url-public-key>
   docker compose up -d nextcloud
   ```

   The `nextcloud` service reads `AZC_VENDOR_PUBLIC_KEY_B64` from `.env` via `docker-compose.yml`. When unset, the built-in dev/CI key is used.

4. **Mobile + Terminal apps** — EAS production secret:

   ```bash
   eas secret:create --scope project --name EXPO_PUBLIC_AZC_VENDOR_PUBLIC_KEY_B64 --value <base64url-public-key>
   ```

   Run in both `mobile/arbeitszeitcheck` and `mobile/arbeitszeitcheck-kiosk`.

5. Issue customer keys only **after** all three surfaces use the matching public key.

## Verification

- PHPUnit: `tests/Unit/Config/VendorPublicKeyTest.php`
- Shared package: `mobile/shared/arbeitszeitcheck-licensing`
- Paste a freshly generated AZC2 key in **Administration → License** — must show Active.
