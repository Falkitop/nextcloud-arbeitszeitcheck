# ArbeitszeitCheck — Organisation license (admin guide)

This guide is for **Nextcloud administrators** who received an **AZC2 license key** from Software by Design for **Mobile** and/or **Terminal** access.

**Web time tracking** in the browser remains **free** (open source). The license unlocks only the official **Mobile** and **Terminal** apps.

---

## 1. Open the license page

1. Sign in as a Nextcloud administrator.
2. Open **ArbeitszeitCheck** → **License** (administration menu).
3. URL pattern: `…/apps/arbeitszeitcheck/admin/license`

---

## 2. Apply the license key

1. Paste the full key (`AZC2.…`) into the text field.
2. Click **Save license**.
3. Verify the summary:
   - **Valid until** date
   - **Mobile seats:** assigned / limit
   - **Terminal devices:** paired / limit

If the key is rejected, check for copy/paste errors (no line breaks) and contact support with your invoice number.

---

## 3. Mobile seats (if licensed)

When `mobileSeats > 0`:

1. On the **License** page, open **Mobile seats**.
2. Search for Nextcloud users and **assign** up to the licensed limit.
3. Only assigned users can clock in/out from the **ArbeitszeitCheck Mobile** app.
4. Remove a seat before assigning someone else if the limit is reached.

Unassigned users see a **license gate** in the app even when the organisation license is valid.

---

## 4. Terminal / kiosk (if licensed)

When `terminalDevices > 0`:

1. Open **ArbeitszeitCheck** → **Kiosk**.
2. Enable **Kiosk activated**.
3. For each employee who may stamp at a tablet: set **Kiosk allowed**.
4. Create a terminal → note the **pairing code** (valid 10 minutes).
5. On the tablet: install **ArbeitszeitCheck Terminal** from Play Store → enter server URL + pairing code.
6. Assign NFC badges (**Tap to learn** at tablet) or generate a **PIN**.

Detailed IT guides (shipped with the app):

- [Terminal-NFC-Setup.de.md](Terminal-NFC-Setup.de.md)
- [Terminal-Kiosk-Android.de.md](Terminal-Kiosk-Android.de.md)
- [IT-Onboarding-Checklist.de.md](IT-Onboarding-Checklist.de.md)

---

## 5. Renewal

1. Receive a new AZC2 key before **valid until** expires.
2. Paste it on the **License** page → **Save license**.
3. Seat and terminal assignments are kept unless the new key has lower limits.

---

## 6. Troubleshooting

| Symptom | Check |
|---------|--------|
| Mobile app: “Server not licensed” | Key applied? Signature valid? Not expired? |
| Mobile app: “No seat assigned” | User listed under **Mobile seats** |
| Terminal pairing fails | Terminal license active? `terminalDevices` limit not exceeded? |
| Terminal: 402 / license error | Kiosk enabled? Valid terminal license? |
| Web clock works, mobile blocked | Expected — mobile writes require seat + license |

**Support:** info@software-by-design.de — include invoice number and Nextcloud version.

---

## 7. Production signing key

Before issuing real customer keys, deploy the matching **vendor public key** on the server and in Play store builds. See [Vendor-Public-Key.en.md](Vendor-Public-Key.en.md).

---

## 8. Security notes

- The license key is **not secret** like a password, but treat it as commercial entitlement — send only to your IT contact.
- Keys are **offline-verified** (Ed25519). The server does not phone home to validate.
- Replace the embedded vendor public key only when Software by Design publishes a key rotation.
