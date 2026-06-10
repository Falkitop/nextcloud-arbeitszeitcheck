# IT-Checkliste — ArbeitszeitCheck Mobile & Terminal

**Für:** Nextcloud-Administratoren  
**Voraussetzung:** ArbeitszeitCheck installiert, Lizenzschlüssel vom Hersteller erhalten

**Detail Terminal:** [Terminal-NFC-Setup.de.md](./Terminal-NFC-Setup.de.md) · [Terminal-Kiosk-Android.de.md](./Terminal-Kiosk-Android.de.md)

---

## A. Lizenz

- [ ] **ArbeitszeitCheck** → **Lizenz** (Administrationsmenü)
- [ ] Lizenzschlüssel einfügen → **Lizenz speichern**
- [ ] Prüfen: Gültig bis, Mobile-Sitze, Terminal-Geräte angezeigt

## B. Mobile Zugang (falls lizenziert)

- [ ] Tab **Lizenz** → **Mobile Sitze** → Mitarbeitende zuweisen
- [ ] HTTPS für Nextcloud
- [ ] Play Store → **ArbeitszeitCheck** installieren (kostenlos)
- [ ] Test: zugewiesener Nutzer stempelt; nicht zugewiesener → kein Zugang

## C. Terminal / Kiosk (falls lizenziert)

### C.1 Vorbereitung

- [ ] Android-Tablet (NFC oder Wedge-Leser), WLAN, HTTPS
- [ ] **Kiosk** → **Kiosk aktiviert** einschalten
- [ ] Pro Mitarbeiter: **Kiosk erlaubt**

### C.2 Tablet koppeln

- [ ] **Kiosk** → Terminal anlegen → **Kopplcode** notieren
- [ ] Play Store → **ArbeitszeitCheck Terminal** installieren
- [ ] App: Server-URL + Kopplcode

### C.3 Ausweise / PIN

- [ ] **Karte am Tablet scannen lassen** (empfohlen) **oder** UID manuell / CSV
- [ ] Optional: **PIN generieren**

Siehe [Terminal-NFC-Setup.de.md](./Terminal-NFC-Setup.de.md)

### C.4 Test

- [ ] Karte oder PIN → Kommen → Pause → Gehen
- [ ] Web-Zeiterfassung zeigt gleichen Status

### C.5 Vollbild

- [ ] [Terminal-Kiosk-Android.de.md](./Terminal-Kiosk-Android.de.md)

## D. Compliance

- [ ] Betriebsrat informiert
- [ ] Dokumentation: Sitze / Terminals / Ausweise (ohne PINs)

**Support:** info@software-by-design.de
