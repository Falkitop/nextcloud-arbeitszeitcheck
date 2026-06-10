# Terminal — Android Kiosk-Modus (Vollbild)

**Für:** IT-Administratoren  
**Begleiter:** [Terminal-NFC-Setup.de.md](./Terminal-NFC-Setup.de.md)

---

## Ziel

Das Tablet zeigt dauerhaft nur **ArbeitszeitCheck Terminal** — keine Systemleiste, kein versehentliches Verlassen der App.

## Empfohlene Optionen (v1)

| Methode | Aufwand | Hinweis |
|---------|---------|---------|
| **Bildschirm anpinnen** (Pinning) | Niedrig | Android Einstellungen → Sicherheit → Anheften |
| **Lock Task Mode** | Mittel | Dedicated device / Device Owner (MDM) |
| **Fully Kiosk / Scalefusion** | Mittel | Drittanbieter-Kiosk-Apps |

## Kurzanleitung — Bildschirm anpinnen

1. Entwickleroptionen: **Benutzerfixierung** erlauben.  
2. ArbeitszeitCheck Terminal öffnen.  
3. Übersicht → App-Symbol → **Anheften**.  
4. Zum Lösen: Zurück + Übersicht gleichzeitig (gerätespezifisch).

## Lock Task (IT)

- Tablet mit **Managed Google Play** oder Device Owner einrichten.  
- Nur Terminal-App in Whitelist.  
- Auto-Start nach Neustart konfigurieren.

## Checkliste

- [ ] Benachrichtigungen stören deaktiviert  
- [ ] Display-Timeout ≥ 30 Min. oder „Nie“  
- [ ] WLAN fest verbunden  
- [ ] Play-Updates außerhalb der Geschäftszeiten  
- [ ] Terminal-Lizenz und Kiosk auf Nextcloud aktiv  
- [ ] Tablet gekoppelt und Test-Stempel durchgeführt  

**Support:** info@software-by-design.de
