# Terminal — NFC-Ausweise & PIN einrichten (IT-Anleitung)

**Für:** Nextcloud-Administratoren  
**Produkt:** ArbeitszeitCheck Terminal (`de.softwarebydesign.arbeitszeitcheck.kiosk`)

**Ziel:** Jeder berechtigte Mitarbeiter kann am Tablet per **NFC-Karte** und/oder **Name + PIN** stempeln — ohne persönlichen Nextcloud-Login auf dem Gerät.

---

## 1. Voraussetzungen

| Punkt | Anforderung |
|-------|-------------|
| Lizenz | `terminalDevices ≥ 1` in Organisationslizenz (Tab **Lizenz**) |
| Kiosk | **Kiosk aktivieren** in ArbeitszeitCheck → **Kiosk** |
| Tablet | Gekoppelt (Pairing-Code), online, HTTPS zur Nextcloud |
| Karten | ISO14443 Type A (z. B. MIFARE Classic/NTAG) — **UID-Modus** v1 |
| Leser | Eingebautes NFC **oder** USB-/Bluetooth-**Wedge** (tastaturähnlich) |

**Unterstützte Tags v1:** Lesen der **UID** (hex, z. B. `04A1B2C3D4`). Verschlüsselte/sektorbasierte Kartenlogik ist **nicht** v1.

---

## 2. Übersicht — zwei Ebenen

```text
Ebene A — Tablet ↔ Server     Kopplcode → Terminal-Token (Geräte-Auth)
Ebene B — Mitarbeiter ↔ Konto  Ausweis/PIN → Identify → Stempeln
```

Diese Anleitung betrifft **Ebene B** (Ausweise/PIN). Tablet-Kopplung: ArbeitszeitCheck → **Kiosk** → Terminal anlegen → Pairing-Code auf dem Tablet eingeben.

---

## 3. Schritt für Schritt — Mitarbeiter freischalten

### 3.1 `kiosk_allowed` setzen

Pro Mitarbeiter, der am Terminal stempeln darf:

1. **ArbeitszeitCheck** → **Kiosk** → Ausweise-Tabelle → **Kiosk erlaubt**  
   **oder** Benutzer-Einstellungen (App-Konfiguration pro Benutzer)

| Ohne `kiosk_allowed` | Verhalten |
|----------------------|-----------|
| RFID-Tap | Fehlermeldung am Tablet (generisch „nicht berechtigt“) |
| PIN-Liste | Name erscheint nicht |

### 3.2 NFC-Ausweis zuordnen — drei Wege

#### Weg 1 — **Karte am Tablet scannen** (empfohlen, „Tap to learn“)

1. Admin wählt Nextcloud-Benutzer.  
2. **Karte am Tablet scannen lassen** und **gekoppeltes Terminal** auswählen.  
3. Am Tablet: *„Halten Sie die neue Karte an das Leserfeld“* (ca. 5 Min.).  
4. Mitarbeiter oder IT hält **neue** Karte ans Tablet.  
5. Tablet bestätigt grün; Admin-Oberfläche zeigt **Zuordnung erfolgreich**.

#### Weg 2 — UID manuell eingeben

1. UID auslesen (siehe §4).  
2. Admin → **Kiosk** → **UID manuell** / RFID zuordnen.  
3. Hex-UID einfügen (Großbuchstaben, ohne Leerzeichen).  
4. Nextcloud-Benutzer wählen → **Speichern**.

#### Weg 3 — CSV-Import (viele Mitarbeiter)

Datei `ausweise.csv`:

```csv
uid,user_id,label
04A1B2C3D4,max,Mustermann Halle
04B2C3D4E5,anna,Schmidt Werkstatt
```

Admin → **Kiosk** → **CSV importieren**.

---

### 3.3 PIN einrichten (optional)

| Methode | Wer | Ablauf |
|---------|-----|--------|
| **Admin generiert** | IT | Kiosk → **PIN generieren** → **6-stellige PIN einmalig anzeigen** |
| **Mitarbeiter setzt selbst** | MA | Persönliche Einstellungen → **Kiosk-PIN ändern** (nur wenn `kiosk_allowed`) |

Am Terminal: **PIN eingeben** → Name wählen → PIN-Tastatur.

**Hinweis:** Pro Benutzer maximal **ein** RFID-Eintrag und **ein** PIN-Eintrag.

---

### 3.4 Test — Stempeln prüfen

| # | Aktion | Erwartung |
|---|--------|-----------|
| 1 | Karte an gekoppeltes Tablet | Name + Status + erlaubte Aktion |
| 2 | **Kommen** tippen | Erfolg, Rückkehr zur Warteseite |
| 3 | Web-Zeiterfassung desselben Users | Status „arbeitet“ |
| 4 | Unbekannte Karte | „Karte nicht bekannt“ |
| 5 | User ohne `kiosk_allowed` | Kein erfolgreicher Identify |

---

### 3.5 Ausweis sperren / ersetzen

| Situation | Maßnahme |
|-----------|----------|
| Karte verloren | Admin → Kiosk → Ausweis **löschen**; neue Karte zuordnen |
| PIN vergessen | Admin → **PIN zurücksetzen** |
| PIN gesperrt (5 Fehlversuche) | 5 Min. warten oder Admin entsperrt |

---

## 4. UID auslesen

### 4.1 Mit Android-Test-App

App **„NFC TagInfo“** oder **„NFC Tools“** → UID notieren → `04A1B2C3D4` (ohne Doppelpunkte).

### 4.2 USB-/BT-Wedge-Leser

Leser sendet UID als Tastatureingabe + Enter. Terminal-App fängt Wedge im Idle- und Aufnahmemodus ab.

---

## 5. Häufige Fehler

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| „Karte nicht bekannt“ | UID nicht zugeordnet | §3.2 |
| „Nicht berechtigt“ | `kiosk_allowed` aus | §3.1 |
| Tablet reagiert nicht | NFC aus | NFC in Android aktivieren |
| Lizenz-Fehler | Keine Terminal-Lizenz | Tab **Lizenz** → Terminal-Geräte > 0 |

---

## 6. Checkliste pro Mitarbeiter

- [ ] Nextcloud-Benutzer existiert  
- [ ] **Kiosk erlaubt**  
- [ ] RFID oder PIN zugeordnet  
- [ ] Test-Stempel OK  

---

## 7. Verweise

| Dokument | Inhalt |
|----------|--------|
| [Terminal-Kiosk-Android.de.md](./Terminal-Kiosk-Android.de.md) | Android Vollbild / Kiosk-Modus |
| [User-Manual.de.md](./User-Manual.de.md) | Allgemeine ArbeitszeitCheck-Nutzung |

**Terminal-App (Play):** kostenloser Download — Lizenz auf dem Server.  
**Support:** info@software-by-design.de
