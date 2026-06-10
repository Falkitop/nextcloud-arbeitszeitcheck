# AZC2 Vendor-Public-Key — Bereitstellung

Der **öffentliche** Ed25519-Schlüssel ist in der Server-App und in beiden nativen Apps eingebettet. Er ist **nicht geheim**. Der **private** Signatur-Seed bleibt nur in `~/ops/azc/.azc-signing-key` (bzw. `sbd_signing_key_path` auf der internen Nextcloud).

## Entwicklung / CI (Standard)

Alle Apps nutzen denselben Standard-Schlüssel wie `tests/fixtures/license_azc2.json`. Demo-Schlüssel aus `seed-*-demo.sh` funktionieren ohne Zusatzkonfiguration.

## Produktion

1. **Produktions-Keypair** erzeugen (einmalig):

   ```bash
   php ~/ops/azc/generate-keypair.php   # ohne --dev-test
   ```

2. **Öffentlichen Schlüssel** ausgeben:

   ```bash
   cd nextcloud
   php scripts/print-azc-vendor-public-key.php ~/ops/azc/.azc-signing-key
   ```

3. **Nextcloud-Server** (Docker-Dev oder Produktion):

   ```bash
   cp .env.example .env
   # AZC_VENDOR_PUBLIC_KEY_B64=<base64url-aus-Schritt-2> in .env eintragen
   docker compose up -d nextcloud
   ```

   Ohne Eintrag gilt der Dev-Standard-Schlüssel (nur für Demo/CI).

4. **Mobile + Terminal** (EAS-Produktionsbuilds):

   ```bash
   eas secret:create --scope project --name EXPO_PUBLIC_AZC_VENDOR_PUBLIC_KEY_B64 --value <base64url>
   ```

   In beiden Apps ausführen: `mobile/arbeitszeitcheck` und `mobile/arbeitszeitcheck-kiosk`.

5. Erst **danach** Kundenschlüssel ausstellen — alle drei Oberflächen müssen denselben Public Key nutzen.

## Prüfung

- PHPUnit: `tests/Unit/Config/VendorPublicKeyTest.php`
- Gemeinsame Logik: `mobile/shared/arbeitszeitcheck-licensing`
- Im Browser: AZC2-Schlüssel unter **Administration → Lizenz** einfügen → Status **Aktiv**

Siehe auch [Admin-License.de.md](Admin-License.de.md).
