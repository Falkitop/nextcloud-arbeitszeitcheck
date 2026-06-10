# ArbeitszeitCheck — Organisationslizenz (Admin-Anleitung)

Diese Anleitung richtet sich an **Nextcloud-Administratoren**, die einen **AZC2-Lizenzschlüssel** von Software by Design für **Mobile** und/oder **Terminal** erhalten haben.

Die **Web-Zeiterfassung** im Browser bleibt **kostenlos** (Open Source). Die Lizenz schaltet nur die offiziellen Apps **Mobile** und **Terminal** frei.

---

## 1. Lizenzseite öffnen

1. Als Nextcloud-Administrator anmelden.
2. **ArbeitszeitCheck** → **Lizenz** (Administrationsmenü).
3. URL-Muster: `…/apps/arbeitszeitcheck/admin/license`

---

## 2. Lizenzschlüssel eintragen

1. Den vollständigen Schlüssel (`AZC2.…`) einfügen.
2. **Lizenz speichern** klicken.
3. Zusammenfassung prüfen:
   - **Gültig bis**
   - **Mobile-Sitze:** zugewiesen / Limit
   - **Terminal-Geräte:** gekoppelt / Limit

Bei Ablehnung: Kopierfehler (keine Zeilenumbrüche) prüfen; Support mit Rechnungsnummer kontaktieren.

---

## 3. Mobile-Sitze (falls lizenziert)

Wenn `mobileSeats > 0`:

1. Auf der Seite **Lizenz** → **Mobile Sitze**.
2. Nextcloud-Benutzer suchen und **zuweisen** (max. Limit).
3. Nur zugewiesene Nutzer können in der **ArbeitszeitCheck Mobile**-App stempeln.
4. Bei vollem Kontingent zuerst einen Sitz **entfernen**.

Nicht zugewiesene Nutzer sehen in der App eine **Lizenzsperre**, auch wenn die Organisationslizenz gültig ist.

---

## 4. Terminal / Kiosk (falls lizenziert)

Wenn `terminalDevices > 0`:

1. **ArbeitszeitCheck** → **Kiosk**.
2. **Kiosk aktiviert** einschalten.
3. Pro Mitarbeiter mit Stempelrecht: **Kiosk erlaubt**.
4. Terminal anlegen → **Kopplcode** notieren (10 Minuten gültig).
5. Tablet: **ArbeitszeitCheck Terminal** aus Play Store → Server-URL + Kopplcode.
6. NFC-Ausweise (**Karte am Tablet scannen lassen**) oder **PIN** vergeben.

Ausführliche IT-Anleitungen (mit der App mitgeliefert):

- [Terminal-NFC-Setup.de.md](Terminal-NFC-Setup.de.md)
- [Terminal-Kiosk-Android.de.md](Terminal-Kiosk-Android.de.md)
- [IT-Onboarding-Checklist.de.md](IT-Onboarding-Checklist.de.md)

---

## 5. Verlängerung

1. Neuen AZC2-Schlüssel vor Ablauf von **Gültig bis** erhalten.
2. Unter **Lizenz** einfügen → **Lizenz speichern**.
3. Sitze und Terminal-Zuweisungen bleiben, sofern das neue Limit nicht niedriger ist.

---

## 6. Fehlerbehebung

| Symptom | Prüfen |
|---------|--------|
| Mobile-App: „Server nicht lizenziert“ | Schlüssel eingetragen? Signatur gültig? Nicht abgelaufen? |
| Mobile-App: „Kein Sitz zugewiesen“ | Nutzer unter **Mobile Sitze** |
| Terminal-Kopplung schlägt fehl | Terminal-Lizenz aktiv? Gerätelimit erreicht? |
| Terminal: 402 / Lizenzfehler | Kiosk aktiv? Gültige Terminal-Lizenz? |
| Web-Stempel ok, Mobile blockiert | Erwartet — Mobile-Schreibzugriffe brauchen Sitz + Lizenz |

**Support:** info@software-by-design.de — Rechnungsnummer und Nextcloud-Version angeben.

---

## 7. Produktions-Signaturschlüssel

Vor dem Ausstellen echter Kundenschlüssel den passenden **Vendor-Public-Key** auf Server und in den Play-Store-Builds bereitstellen. Siehe [Vendor-Public-Key.de.md](Vendor-Public-Key.de.md).

---

## 8. Sicherheit

- Der Lizenzschlüssel ist **kein Passwort**, aber geschäftliche Berechtigung — nur an IT weitergeben.
- Schlüssel werden **offline** geprüft (Ed25519). Kein Phone-Home zur Validierung.
- Vendor-Public-Key nur bei dokumentierter Rotation durch Software by Design austauschen.
