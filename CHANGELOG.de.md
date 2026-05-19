## 1.3.5 – 2026-05-19

### Neu

- **Admin „Urlaubsanspruchsebenen“ – Komplett-Politur für Produktion** (`js/admin-vacation-layers.js`, `templates/admin-vacation-layers.php`, `css/admin-vacation-layers.css`):
  - **Vollständige WAI-ARIA-1.2-Combobox** für die Mitarbeiter-Suche im Simulator (Pfeiltasten, Home/End, Enter zum Übernehmen, Escape zum Schließen; `aria-activedescendant`, `aria-expanded`, `aria-controls`, `aria-haspopup`).
  - **Sichtbarer Leerzustand**: „Keine passenden Mitarbeitenden gefunden“ wird als `role="status"`-Eintrag angezeigt.
  - **Voraussetzungsprüfungen** auf der Seite: Schaltflächen „Modellstandard hinzufügen“ und „Teamrichtlinie hinzufügen“ werden deaktiviert (inkl. `title` und `aria-disabled`) wenn keine Arbeitszeitmodelle / Teams konfiguriert sind; ein begleitender Hinweis erklärt, wo die Voraussetzung anzulegen ist.
  - **Clientseitige Datumsbereichs-Validierung** (`Gültig von ≤ Gültig bis`, beide strikt `YYYY-MM-DD`) mit Inline-Fehler unter dem Feld — kein 400-Roundtrip mehr für offensichtliche Tippfehler.
  - **Tarif-Regelsatz clientseitig Pflichtfeld** wenn der Modus `tariff_rule_based` ist (Vertrag des Engines wird gespiegelt).
  - **Doppelklick-Schutz** für „Speichern“ und „Simulation starten“: in-flight Requests deaktivieren den Button (`aria-busy="true"`, Label wird zu „Speichern …“ / „Simulation läuft …“).
  - **Sicherer Fokus-Return** beim Dialog-Schließen: wenn das auslösende Element während des Speicher-Roundtrips entfernt wurde, übernimmt die Seitenüberschrift den Fokus (statt `<body>`).
  - **Anzahl-Chips** neben jedem Abschnittstitel (`L0` / `L1` / `L2`).
  - **Leeren-Reset-Knopf** für die hypothetische Team-Auswahl im Simulator.
  - **Mobile-Friendly Trace-Tabelle**: die Auflösungs-Trace fällt unterhalb 720 px auf ein Card-Layout zurück.
  - **`forced-colors: active`**-Regeln erhalten Rahmen, Fokus-Ringe und aktive Hervorhebung im Windows-Hochkontrastmodus.
  - **3-px-Fokus-Outlines** und `outline-offset: 2px` für sämtliche interaktiven Oberflächen.
  - **`clip-path: inset(50%)`** für `.visually-hidden` (veraltetes `clip: rect(...)` entfernt).
  - **Tests**: 25 neue Vitest-Cases (`js/admin-vacation-layers.test.js`) für Parser, Datumsbereichs-Validator, Combobox-Tastatur, Empty-State-Logik. Alle 40 JS-Tests + 624 PHP-Tests laufen grün.

## [Unreleased]

## 1.3.4 – 2026-05-19

### Neu

- **Einzelne Quelle der Wahrheit für Zeitzonen** (`TimeZoneService`, `js/common/time.js`, `templates/common/time-bootstrap.php`). Backend und Frontend nutzen jeweils einen zentralen Pfad für Speicher-TZ, Anzeige-TZ und „jetzt“; siehe `docs/Time-And-Timezone-Architecture.de.md`.
- **Audit-sichere Anzeige-TZ** auf allen nutzerseitigen Oberflächen (Manager-Freigaben, Überlappungskonflikte, ArbZG-Ruhezeit, Erinnerungen, Auto-Pause/Ausstempeln, Zeiteinträge-Bearbeitungsformular).
- **Drift-sicherer Live-Timer** auf dem Dashboard (`server_now` + `performance.now()`-Anker).
- **`TimeClientBootstrap`** — einheitliche Registrierung der Client-Zeitzonen-Stack inkl. Dashboard-Widgets.
- **Korrektur-Anfrage-Dialog** für Mitarbeitende (europäisches Datum, Stunden/Minuten-Auswahl, Pflichtbegründung, WCAG-konforme Fehleranzeige).
- **Gemeinsame JS-L10n-Bundles** für Korrekturen (`time-entry-correction-l10n.php`, `manager-correction-l10n.php`).

### Behoben

- Timer zeigt nach Einstempeln nicht mehr sofort den Client/Server-TZ-Offset.
- „Letzte Einträge“ und Legacy-`index.php` zeigen konsistent die Anzeige-TZ.
- Redirect beim Öffnen von „Korrektur anfragen“; lesbare Validierungsfehler auf dunklen Themes; fehlende Übersetzungsschlüssel.

### Geändert

- `AppLocalNaiveDateTimeNormalizer` nur noch als dünne Fassade; Dienste/Controller nutzen `TimeZoneService` direkt.

### Tests & Dokumentation

- Unit-/Integrations-/E2E-Tests für Zeitzonen-Migration und Timer-Offset; neue Architektur-Dokumentation.

## 1.3.3 – 2026-05-18

### Behoben

- **Admin-Urlaubsdaten** (`PUT /api/admin/users/{userId}/vacation-policy`, `POST /api/admin/vacation-policy/simulate`, `GET /api/admin/vacation-layers` sowie **L0/L1/L2**-Payloads in `LayeredVacationDefaultsService`): ungültige und **überlaufende** Kalenderstrings (z. B. `2026-02-30`) werden mit **HTTP 400** bzw. Feldvalidierung abgewiesen — statt stiller PHP-Normalisierung oder **HTTP 500**. Gemeinsame Logik: `OCA\ArbeitszeitCheck\Support\StrictYmdDates`.
- **`completePausedEntry()` erhält eine vorhandene `end_time`** bei Legacy-Zeilen im Status `paused`, die bereits einen eingefrorenen Endzeitstempel tragen (Status/`end_time`-Inkonsistenz). Ohne diese Absicherung hätte der Service lohnrelevante Stunden mit `updated_at` überschreiben können.
- **`RepairOrphanedPausedEntries`** setzt bei reiner Status-Korrektur (`paused` → `completed` bei vorhandener `end_time`) jetzt auch `ended_reason` und `policy_applied`, damit Upgrade-Reparaturen audit-konsistent zu den übrigen Schritten bleiben.

### Tests

- Neu: `testCompletePausedEntryPreservesExistingEndTime`.
- 577 Unit-Tests grün.

## [Unreleased]

### Neu

- **Ein-Klick-Wiederherstellung für „pausierte" Zeiteinträge** (Issue: time-tracking/paused-entry-recovery, adressiert die gemeldeten Fehler „pausierte Einträge nicht bearbeitbar/abschließbar" und „HTTP 500 beim Ausstempeln/Pause beginnen, keine UI-Methode zum Heilen"). Der neue Endpoint `POST /api/time-entries/{id}/complete` beendet einen im Zustand `paused` festsitzenden Eintrag in einem einzigen, race-sicheren Schritt. Die Endzeit nutzt standardmäßig `updated_at` (der Zeitpunkt, an dem das abgebrochene Ausstempeln den Eintrag eingefroren hat) und fällt notfalls auf `start_time` zurück (Null-Dauer-Sicherheitsnetz). ArbZG §4 (automatische Pause) und ArbZG §3 (Tagesmaximum) werden angewendet, damit die resultierende `completed`-Zeile mit einem normalen Ausstempeln compliance-äquivalent ist. Jede Wiederherstellung wird mit `time_entry_paused_completed` revisionssicher protokolliert; die Eigentümerschaft wird geprüft, der Benutzer-Mutex respektiert.
- **„Sitzung beenden"-Aktion auf Dashboard und Zeiteinträge-Liste**. Bei Status `paused` zeigt die Dashboard-Statuskarte einen klar beschrifteten Button „Sitzung beenden" direkt neben „Nach Pause fortsetzen". Die Zeiteinträge-Liste bekommt pro betroffener Zeile einen Primär-Button „Beenden" sowie ein `role="status"`-Banner, das den Zustand in Klartext erklärt. WCAG 2.1 AA: Mindest-Trefferfläche 44×44, ARIA-Labels und -Titles, niemals nur Farbe als Indikator.
- **`TimeTrackingService::completePausedEntry()`** als kanonischer programmatischer Wiederherstellungs-Pfad. Der Controller ist jetzt eine schmale Hülle, die nur Eingaben parst, an den Service delegiert und Domain-Exceptions sauber abbildet (`BusinessRuleException` → 400/403, `MonthFinalizedException` → 409, `LockedException` → 423, `DoesNotExistException` → 404) — kein generisches HTTP 500 mehr für bekannte Geschäftszustände.
- **`TimeEntryMapper::findAllPausedByUser()`** + Post-Migration-Reparaturschritt `RepairOrphanedPausedEntries`, der bei jedem `occ upgrade` idempotent verbliebene `paused`-Zeilen schließt: Zeilen mit `end_time` werden auf `completed` gesetzt, Zeilen ohne `end_time` werden mit `updated_at` (oder `start_time` als Fallback) geschlossen.

### Geändert

- **`TimeTrackingController::buildSafeErrorResponse()`** fängt jetzt explizit `OCP\Lock\LockedException` ab und liefert HTTP 423 mit der übersetzbaren Meldung „Eine andere Änderung an Ihrer Zeiterfassung läuft. Bitte einen Moment warten und erneut versuchen." — der gemeldete generische HTTP 500 bei parallelem Ausstempeln/Pause-Start entfällt damit.
- **Status-Badges „pausiert"/„Pause"/„abgelehnt"** in der Zeiteinträge-Liste nutzen jetzt semantisches `warning`/`error`-Styling und tragen erklärende `title`-Attribute — der Zustand ist über Icon, Farbe *und* Text erkennbar (WCAG 1.4.1).

### Tests

- Neu: 5 Fälle im `TimeTrackingServiceTest` für den Wiederherstellungs-Pfad — Default-Endzeit aus `updated_at`, expliziter Override mit Erzwingung `end ≥ start`, Ablehnung fremder Einträge, Ablehnung nicht-pausierter Status, Schutz gegen ungültige IDs.
- 554 Unit-Tests grün (vorher 549).

- **Mehrstufige Urlaubsanspruchsauflösung — Trace-Flags für entartete Zustände & Impact-Vorschau** (Spezifikation: hr/vacation-entitlement-hierarchy Folge­erweiterung). Der Auflösungs-Trace liefert jetzt explizite Marker: `degraded_org_default_collision` (REQ-ENT-10), `partial_history` (REQ-ENT-13 / EC-11), `clamped` + `raw_*`-Werte (EC-08), `rule_set_status_warning` (EC-05) und `degraded='model_lookup_failed'` (EC-04). Auditoren sehen Fehlkonfigurationen und historisch-eingeschränkte Auflösungen sofort statt eines stillen Fallbacks. Der Admin-Simulator stellt die Flags als beschriftete Chips neben dem Ergebnis dar; der Mitarbeiter-Erklär­dialog zeigt eine redigierte Untermenge (`degraded`, `clamped`, `partial_history`) ohne interne IDs (REQ-SEC-05).
- **Impact-Vorschau-Endpoint** `GET /api/admin/vacation-layers/impact?scope={org,model,team}&targetId={int}` (REQ-UX-03). Der Urlaubsebenen-Dialog zeigt direkt „Bis zu N Mitarbeitende werden von dieser Änderung neu aufgelöst" an, bevor der Admin auf Speichern klickt — WCAG-konform mit Icon, Statustext und ARIA-Live-Region (nie nur Farbe).

### Geändert

- **`LayeredVacationConflictException` für Lock-Konflikte** (REQ-SEC-04 / EC-07). `LayeredVacationDefaultsService` umschließt Nextclouds `OCP\Lock\LockedException`, sodass der `AdminController` HTTP 409 mit einem übersetzbaren Hinweis „Eine Administratorin/ein Administrator bearbeitet diese Ebene gerade" zurückliefert statt eines generischen 500. Das Admin-JS zeigt die Meldung im Dialog-Feedback an, ohne das Formular zu schließen.

### Tests

- Neu: `LayeredVacationEntitlementEngineTest` erhält 14 Fälle für Degraded-Flags und das Pass-Through der redigierten Trace; `LayeredVacationDefaultsServiceTest` 6 Fälle für `previewImpact` (Validierung, fehlende Deps, Model-Zählung, Team-Subtree-Aggregation); `AdminControllerTest` 4 Fälle für 409 beim Speichern/Löschen und 200/400 beim Impact-Endpoint.
- 534 Unit-Tests grün.

## 1.3.1 – 2026-05-12

### Neu

- **L3-Vererbung als Option im Admin-Benutzerdialog** (REQ-WF-04). Die Auswahl „Wie soll der Jahresurlaub berechnet werden?" enthält jetzt „Aus Team / Modell / Organisation übernehmen" als erstklassige Option. Bei Auswahl werden manuelle Tage / Tarifregelwerk / Begründung deaktiviert (die Engine würde sie ohnehin ignorieren). Persistiert wird sowohl die Boolean-Spalte `inheritLowerLayers` als auch der Sentinel `vacation_mode = 'inherit'`, damit beide Repräsentationen synchron bleiben. Die Admin-Benutzerliste liefert `inheritLowerLayers` jetzt im Payload mit, sodass der Dialog den Zustand korrekt zurückspielt.
- **Hypothetische Teammitgliedschaft im Simulator** (REQ-WF-05). Der Simulator auf `/admin/vacation-layers` erlaubt HR jetzt, ein *gedachtes* Team-Set für eine What-if-Berechnung anzugeben („Was bekäme die Mitarbeiterin, wenn sie zum Team Berlin wechselt?") – ganz ohne die echten Teammitgliedschaften zu ändern. Die Engine wertet L2 gegen das Override aus und propagiert ein explizites `hypothetical: true`-Flag in den Trace; die UI zeigt ein dediziertes Banner, damit das Ergebnis nie mit dem *aktuellen* Anspruch verwechselt wird.
- **Erkennung überlappender geschlossener L0-Zeiträume** (REQ-DAT-03). `OrgVacationDefaultMapper::findOverlappingRanges` lehnt neue Organisations-Defaults aktiv ab, wenn sie sich mit einer bestehenden *geschlossenen* Gültigkeit überschneiden. Zuvor wurden nur offene Bereiche automatisch geschlossen; geschlossen-vs-geschlossen-Konflikte fielen erst zur Auflösungszeit als `degraded_org_default_collision` auf. Die Admin-UI zeigt zusätzlich ein `role="alert"`-Warnbanner über der aktiven L0-Zeile, sobald mehr als eine Regel heute gleichzeitig aktiv ist – sichtbar bevor überhaupt eine Simulation gestartet wird.
- **Progressive Disclosure im Simulator** (REQ-UX-02). Das Simulator-Ergebnis startet mit einem Ein-Satz-Resümee („Am {Datum} erhält die/der Mitarbeitende {Tage} Urlaubstage pro Jahr, festgelegt durch die {Ebene}.") inklusive großgesetzter Tage. Die volle Schicht-Trace landet in einem aufklappbaren `<details>`-Element; L2-Tiebreaker-Kandidaten (Tiefe / Priorität / Policy-ID) in einem geschachtelten `<details>`. So bleibt die Detail-Tiefe für Auditoren erhalten, ohne HR im Default-Fall mit JSON zu überfordern.

### Behoben

- **Fokusrückgabe nach Dialog** (WCAG 2.4.3 / Fokusreihenfolge). Der Vacation-Layer-Dialog merkt sich beim Öffnen das auslösende Element und gibt den Fokus bei explizitem Schließen *und* bei ESC dorthin zurück. Bisher landete der Fokus auf `<body>`, sodass Tastatur­nutzer:innen die Seitenhierarchie erneut durchhangeln mussten.
- **Simulator-Fokus & Fehlermeldung**. Nach einer Simulation erhält die Ergebnis­region (`aria-live="polite"`, `tabindex="-1"`) den Fokus, damit Screenreader das Ergebnis sofort vorlesen. Fehler laufen jetzt über die bestehende `aria-live`-Statusregion und stehen nicht mehr ausschließlich in der Ergebniskarte.

### Tests

- Neu: 7 Fälle — `LayeredVacationEntitlementEngineTest` 3× für hypothetische Team-Injektion (Override, Cleanup, Sanitisierung); `LayeredVacationDefaultsServiceTest` 2× für die Ablehnung überlappender geschlossener Bereiche vs. Auto-Trim offener Bereiche; `AdminControllerTest` 2× für IDOR-404 im Simulator und das Weiterreichen hypothetischer Teams.
- 548 Unit-Tests grün (vorher 541).

## 1.3.0 – 2026-05-12

### Neu

- **Mehrstufige Urlaubsanspruchsauflösung** (Spezifikation: hr/vacation-entitlement-hierarchy). Der jährliche Urlaubsanspruch wird über eine deterministische, prüfbare Präzedenzkette aufgelöst: L3 Individualregel → L2 Team-/Kohorten-Richtlinie → L1 Arbeitszeitmodell-Default → L0 Organisations-Default → klassischer Sicher­heits-Fallback. Jede Ebene kann manuell, über die Formel `model_based_simple` oder über ein aktives Tarifregelwerk konfiguriert werden. L3-Zuweisungen erhalten ein `inherit_lower_layers`-Flag (neuer Modus `inherit`), damit HR explizit auf die Kette zurückgreifen kann, ohne die Zeile zu löschen. Konflikte auf L2 werden deterministisch nach Team-Tiefe → Priorität → kleinster Team-ID aufgelöst. Jede Auflösung erzeugt einen strukturierten Trace v1 (`algorithm_version`, `as_of_date`, `matched_layer`, `layers_evaluated`, `winner`, `inputs_redacted`), der für Lohn-Audits in Entitlement-Snapshots persistiert wird (REQ-AUD-01).
- **Admin-Seite "Urlaubsanspruch"** mit WCAG 2.1 AA + responsivem Layout (`/admin/vacation-layers`): Stepper-Übersicht der Präzedenz, separate Karten für L0/L1/L2 mit voller Historie, native `<dialog>`-basierte Anlage-/Bearbeitungs-Drawer mit Inline-Validierung sowie ein eingebauter Simulator, der den Anspruch für jeden Mitarbeiter zu jedem Stichdatum mit voller Trace anzeigt.
- **Erklär-Dialog "Wie wird das berechnet?"** auf der Abwesenheitenseite. Zeigt einen ID-freien, redigierten Trace aus `VacationEntitlementEngine::redactTraceForUser()`, damit niemals Richtlinien-Namen anderer Kollegen oder interne IDs durchsickern (REQ-SEC-05).
- **Schreibsicherheit** via Advisory-Locks (`OCP\Lock\ILockingProvider`) pro Ebene/Ressource (REQ-SEC-04, EC-07) und schreibendem Transaktionsrahmen über `TTransactional`.
- **Audit-Einträge** für jede Anlage/Löschung auf L0/L1/L2 (`org_vacation_default`, `model_vacation_default`, `team_vacation_policy`) inkl. Before-/After-JSON (REQ-AUD-02).
- **Feature-Flag** `arbeitszeitcheck.layered_entitlements_enabled` (Default AN). Bei `0` läuft die Engine deterministisch über L3 → Legacy-Fallback und das bisherige Verhalten bleibt Byte-für-Byte erhalten.

### Behoben

- **GAP-01** — vereinheitlichtes Runden des Urlaubsanspruchs in Engine, `VacationAllocationService`, `AbsenceService` und `EntitlementSnapshotService`. Die einzige kanonische Implementierung `VacationEntitlementEngine::roundDays()` klemmt auf `[0, 366]` und rundet auf 2 Nachkommastellen mit `PHP_ROUND_HALF_UP`. Frühere Pfade mischten `(int)round(...)` mit `round(value, 2)` – Ergebnis: an `.5`-Grenzen konnte der Jahresanspruch um ±1 Tag schwanken.

### Schema

- Neue Tabellen: `at_org_vacation_defaults` (L0), `at_model_vacation_defaults` (L1), `at_team_vacation_policies` (L2 mit FK → `at_teams ON DELETE CASCADE`).
- `at_user_vacation_policies` (L3) erhält Spalte `inherit_lower_layers BOOLEAN DEFAULT false` (im Schema nullable wegen Nextcloud-Portabilität; die Anwendung behandelt NULL wie false) — golden-file-äquivalent für alle bestehenden Zeilen.

### Dokumentation

- **Entwicklung:** `docs/Developer-Documentation.en.md` — gestufte Auflösung L0–L3, Admin-Routen, Audit/Locking, Produktions-Rollback über `layered_entitlements_enabled`, sowie der `Entity`-/`QBMapper::insert`-Fallstrick (Dirty-Tracking) bei den neuen Layer-Entitäten.
- **Betrieb / Anwender:** `docs/User-Manual.en.md`, `docs/User-Manual.de.md` — Admin-Seite **Urlaubsanspruch**, Notfall-Konfig-Rollback, Mitarbeitenden-Hinweis zur Berechnung; Index in `docs/README.md` angepasst.
- **Produktspezifikation (Repo):** `pm/app-ideas/arbeitszeitcheck/vacation-entitlement-hierarchy.md` — Adopted-Status, Fact-Base an ausgelieferten Code, L2-Tie-Break-Text wie Implementierung, Migrations-/Rückwärtskompatibilität für Produktions-Upgrades.

### Tests

- Neu: `LayeredVacationEntitlementEngineTest` (ebenenübergreifende Präzedenz, Tie-Breaking, Simulation, Trace-Envelope), `LayeredVacationDefaultsServiceTest` (Validierung, Audit, Lock, Transaktion), `LayeredVacationEntitlementSchemaTest` (Migrationsschema + FK + Idempotenz).
- 511 Unit-Tests grün.

## 1.2.9 – 2026-05-12

### Behoben

- **Installations-Abbruch auf PostgreSQL durch falschen Foreign Key** (Issue #4): Die Migration `Version1014Date20260409120000` hat den Fremdschlüssel `at_mcr_closure_fk` auf `at_month_closure_revision.closure_id` mit dem rohen, *un-präfixierten* String `'at_month_closure'` an `addForeignKeyConstraint()` übergeben. Doctrine erzeugte daraus SQL, das direkt die Relation `at_month_closure` referenziert – statt der präfixierten `oc_at_month_closure`. Auf jeder PostgreSQL-Instanz brach die Installation damit mit `SQLSTATE[42P01] / Undefined table: 7 / FEHLER: Relation »at_month_closure« existiert nicht` ab. Der FK wird jetzt über `$schema->getTable('at_month_closure')` deklariert, sodass der `dbtableprefix` korrekt angewendet wird. MariaDB/MySQL waren ebenfalls betroffen, jedoch *stillschweigend*: dort wurde der FK nie angelegt, sodass die Audit-Trail-Kette des Monatsabschlusses ohne referenzielle Integrität lief.
- **Nachrüstung des fehlenden Monatsabschluss-FK auf bestehenden Installationen**: Neue Migration `Version1023Date20260512143000`. Sie entfernt zunächst eventuelle Waisen-Revisionen (`at_month_closure_revision`-Zeilen, deren `closure_id` auf keinen existierenden Closure zeigt – ein Nebeneffekt des bisher fehlenden FK auf MariaDB) und legt anschließend den FK mit `ON DELETE CASCADE` an. Vollständig idempotent – auf gesunden Installationen ein No-Op.
- **Locale-fragile „Tabelle existiert nicht"-Erkennung in Migrationen**: `Version1008`, `Version1009` und `Version1015` haben fehlende Tabellen auf frischen Installationen über String-Matching englischer Treiberfehler erkannt ("doesn't exist", "no such table" …). PostgreSQL übersetzt diese Texte ("Relation … existiert nicht" auf deutschen Clustern), wodurch die Prüfung durchrutschte und Migrationen mit übersetzten DB-Fehlern abbrachen. Ersetzt durch explizite `IDBConnection::tableExists()`-Guards.
- **Locale-fragile Laufzeitprüfungen** in `SettingsController::index_api`, `AdminController::getTeams` und `TeamResolverService`: String-Matching auf Fehlermeldungen wurde durch `OCP\DB\Exception::getReason() === REASON_DATABASE_OBJECT_NOT_FOUND` ersetzt – das portable, locale-unabhängige Vertragsmerkmal des Nextcloud-DBAL-Wrappers. Ein kleiner String-Fallback bleibt nur für nicht-DBAL-Pfade (z. B. Test-Doubles).

### Tests

- **Schema-basierter Regressionstest für den FK-Bug**: Neuer `tests/Unit/Migration/MonthClosureForeignKeyTest` führt den Schemaaufbau der Migration gegen ein echtes Doctrine-`Schema` mit angewendetem Nextcloud-Präfix aus und prüft, dass der FK auf die *präfixierte* Tabelle zeigt und dass ein erneutes Ausführen ein No-Op bleibt. Dieser Test fixiert genau den Vertrag, der ursprünglich gebrochen war, und erkennt zukünftige Migrationen, die versehentlich einen rohen, un-präfixierten Tabellennamen an `addForeignKeyConstraint()` übergeben.

## 1.2.8 – 2026-04-30

### Sicherheit

- **CSRF-Schutz auf allen mutierenden Endpunkten**: `#[NoCSRFRequired]` wurde auf POST/PUT/DELETE-Methoden in `AbsenceController`, `TimeEntryController`, `TimeTrackingController`, `ComplianceController`, `SubstituteController`, `SettingsController`, `MonthClosureController`, `GdprController` und `AdminController` entfernt. Das Frontend sendet `requesttoken` bereits konsistent über `ArbeitszeitCheckUtils.ajax`; mutierende Routen lehnen Cross-Site-Anfragen damit per Default ab. Reine GET-Endpunkte bleiben bewusst `#[NoCSRFRequired]` (CSRF ist für Read-Only-GETs im Nextcloud-Framework nicht relevant).
- **Keine ungefilterten Exception-Texte mehr in JSON-Antworten**: `AbsenceController::getSafeErrorMessage` wurde gehärtet – Exception-Nachrichten werden nur dann weitergereicht, wenn sie aus expliziten Business-Rule-`\Exception`-Klassen stammen; Texte mit technischen Indikatoren (SQL-Fragmente, Pfade, Stacktraces, übergroße Payloads) werden durch eine generische lokalisierte Fehlermeldung ersetzt. Der gehärtete Helper wird in `AbsenceController::store/update` verwendet. Direkte `getMessage()`-Leaks in `AdminController::getTeams`, `SettingsController::index_api` und in den Page-Render-Fehlerpfaden des `PageController` wurden durch sanitisierte, übersetzte Texte ersetzt.
- **Korrekter HTTP-Status bei Auth-Fehlern**: `SettingsController::update` liefert bei unauthentifizierten Aufrufen jetzt `HTTP 401 Unauthorized` (vorher `HTTP 400 Bad Request`), damit API-Clients und Loadbalancer das richtige Verhalten erkennen.

### Geändert

- **Organisations-weiter Monats-Report-Download**: `reports.js` reicht die im Preview ermittelten User-IDs an den `report.team`-Endpunkt weiter und zeigt bei leerem Organisations-Preview die klare Meldung „Im gewählten Zeitraum hat keine Person der Organisation Arbeitszeit erfasst – es gibt nichts herunterzuladen." statt des irreführenden Hinweises auf einen erforderlichen Preview.
- **Sanitisierte Dashboard-Fehleranzeige**: `dashboard.js`/`dashboard.css` zeigen statt roher Widget-Exceptions eine lokalisierte Live-Region-Meldung „Einige Dashboard-Daten konnten nicht geladen werden.".
- **„Resume after break" vereinheitlicht**: Clock-In-Texte und l10n verwenden konsistent „Resume after break" anstelle des Platzhalters `clock_in_resume`.

### Barrierefreiheit (WCAG 2.1 AA)

- **Landmark `<main>` auf allen Seiten**: 17 Page-Templates exponieren jetzt jeweils genau ein `<main id="app-content" role="main" aria-label="...">`-Landmark für Hilfstechnologien (`dashboard`, `index`, `timeline`, `calendar`, `settings`, `personal-settings`, `reports`, `compliance-dashboard`, `compliance-reports`, `compliance-violations`, `working-time-models`, `admin-dashboard`, `admin-teams`, `admin-users`, `admin-holidays`, `manager-dashboard`, `manager-time-entries`, `manager-absences`, `manager-month-closures`).
- **Skip-Link / `<main>`-Konsistenz**: `time-entries`, `absences`, `admin-settings`, `admin-notifications`, `substitution-requests` und `audit-log` hatten `id="app-content"` auf einem reinen `<div>`, während `role="main"` auf dem Kind-Wrapper saß – der Skip-Link „Zum Hauptinhalt springen" landete dadurch auf einem Nicht-Landmark. Alle sechs Templates verwenden jetzt direkt `<main id="app-content" role="main">`. Das überflüssige `role="banner"` auf dem `<header>` innerhalb der Main-Region von `audit-log` wurde entfernt.
- **Zugängliche Namen auf allen Datentabellen**: Die Feiertagsliste und die beiden Benachrichtigungs-Matrix-Tabellen erhalten `aria-label`/`aria-labelledby` plus eine Screenreader-`<caption>`; sie hatten zuvor keinen zugänglichen Namen.
- **Live-Fehlermeldung im Dashboard**: Der Dashboard-Fehlerbereich liegt in einer `aria-live`-Region; teilweise Widget-Fehler werden angekündigt, ohne den Fokus zu stören.
- **Manager-Dashboard-Kennzahlen jetzt vorlesbar**: Die Statistik-Werte (Teammitglieder / Heute aktiv / Stunden heute / Offene Anträge) hatten `aria-hidden="true"` auf den Wert-Spans – Screenreader-Nutzer:innen verloren damit jede einzelne Zahl. Jede Karte exponiert jetzt einen vollständig vorlesbaren Namen (z. B. „5 Teammitglieder heute aktiv") über einen `role="group"`-Wrapper; das visuelle Layout bleibt identisch.
- **Konflikt zwischen `role="alert"` und `aria-live="polite"` aufgelöst**: Das überschreibende `aria-live="polite"` wurde in `absences.php` (Formularfehler), `admin-settings.php` (globaler Fehlerbanner) und drei Inline-Fehlern im Time-Entry-Formular entfernt. `role="alert"` impliziert bereits assertive Ankündigungen; `polite` hatte kritisches Validierungsfeedback für Hilfstechnologien verzögert.
- **Heading-Hierarchie normalisiert**: Jedes Hauptseiten-Template hat jetzt genau ein `<h1>` (dashboard, time-entries, absences, calendar, timeline, reports, settings, personal-settings, compliance-dashboard, compliance-reports, compliance-violations, working-time-models, admin-dashboard, admin-users, admin-holidays, admin-settings, admin-notifications, manager-dashboard). Die meisten Seiten begannen vorher bei `<h2>`. Untergeordnete Section-Überschriften in time-entries und absences wurden auf `h2`/`h3` angehoben, sodass keine Ebene mehr übersprungen wird. Eine Stilregel für `.section-header h1` wurde ergänzt, damit die bestehende `h2`-Optik erhalten bleibt.
- **Breadcrumb im Manager-Dashboard**: Standard-Breadcrumb „Dashboard › Manager Dashboard" ergänzt – konsistent mit allen anderen Hauptseiten und besser orientiert für Tastatur- und Screenreader-Nutzer:innen.
- **Kalender-Ladezustand wird angekündigt**: Der „Kalender wird geladen…"-Platzhalter nutzt jetzt `role="status"` mit `aria-live="polite"`; Lade- und Fertig-Übergänge werden angekündigt, der dekorative Spinner ist `aria-hidden`.
- **Fokus-Indikatoren wiederhergestellt**: `outline: none` auf den Timeline-Filter-Checkboxen und den Admin-Userpicker-Items hatte die Tastatur-Fokussichtbarkeit unterdrückt. Beide haben jetzt `:focus-visible`-Outlines in der Primärfarbe; Hover-Stile bleiben erhalten.
- **Touch-Ziele auf Mobile**: `.btn--sm` war auf Mobilgeräten 36 × 36 px – unterhalb der WCAG-2.5.5-Empfehlung von 44 × 44 px. Die Mobile-Media-Query erzwingt jetzt 44 × 44 px für kleine Buttons; Desktop-Größen bleiben unverändert.
- **Empty-State-Zeile im Legacy-`index.php`-Time-Entries-View**: Fehlende Empty-State-Zeile bei leerer Liste wiederhergestellt (Parität mit den anderen Tabellen-Views im selben Template).
- **Reports-Zugang: Fallback-Navigation**: Inline-`onclick`-Redirect in der „Kein Zugriff"-Empty-State von `reports.php` durch einen echten `<a>`-Link ersetzt – funktioniert auch ohne JavaScript und folgt Standard-Link-Semantik.

### Entfernt

- **Veraltetes Personal-Settings-Panel im Nextcloud-Settings-Bereich**: Das alte `personal-settings.php`-Panel im Nextcloud-User-Settings-Bereich enthielt hartcodierte Felder für Urlaubstage / tägliche Arbeitszeit und Erinnerungs-Checkboxen, die nie an ein Backend angebunden waren. Ersetzt durch ein klares Info-Panel, das die Nutzer:innen zur In-App-Personal-Settings-Seite (dort werden Einstellungen real über `SettingsController::update` persistiert) leitet, plus einem kurzen DSGVO-Hinweis. Im Legacy-`index.php`-Settings-Branch (toter Code, aber noch im Code) wurde der hartcodierte Versionsstring `1.0.1` durch `IAppManager::getAppVersion('arbeitszeitcheck')` ersetzt.

### Tests

- **AccessibilityTest gehärtet**: Die Assertion „muss `<button>` enthalten" (die Seiten ohne tastaturerreichbare Steuerelemente erlaubte und reine Link-Panels fälschlich beanstandete) wurde durch zwei strengere Prüfungen ersetzt: (1) `<div onclick=…>`-Anti-Pattern explizit verbieten und (2) mindestens ein `<button>` oder `<a href>` pro auditiertem Template erzwingen. Gesamte Suite: 455 Tests, 1 652 Assertions, alle grün.

## 1.2.7 – 2026-04-27

### Hinzugefügt

- **Audit-Checkliste für kritische Workflows**: `tests/WORKFLOW_AUDIT_CHECKLIST.md` ergänzt als kompakte Release-Checkliste für Zeiterfassung, Korrekturen manueller Einträge, Abwesenheiten/Genehmigungen, Monatsfinalisierung, Reporting/Compliance/Export und öffentliche Fehleroberflächen.

### Geändert

- **Mutationssicherheit in der Zeiterfassung**: Clock-/Pausen-Mutationen nutzen nun nutzerspezifische Locks und Transaktionen; Statusabfragen bleiben read-only, während automatische Pausen-Fallbacks und Tagesmaximum-Finalisierung über explizite Mutationspfade/Hintergrundjobs laufen.
- **API-Eingaben und Fehlerbehandlung gehärtet**: Report-, Export-, Compliance-, Manager- und Time-Tracking-Endpunkte verwenden strengere Datums-/Zeitvalidierung, sichere Validierungsantworten und generische öffentliche Fehlermeldungen bei unerwarteten Fehlern.
- **Monatsabschluss konsequenter durchgesetzt**: Abwesenheits-Update/Delete/Cancel/Shorten/Freigabe-/Vertretungsflows prüfen die Änderbarkeit des Monats erneut, bevor Workflow-Mutationen geschrieben werden.

### Behoben

- **Fingerprinting am Health-Endpunkt**: Die öffentliche Health-Antwort enthält keine App- oder Nextcloud-Versionsfelder mehr.

## 1.2.6 – 2026-04-24

### Hinzugefügt

- **Forensik bei Abwesenheitsfreigaben**: `approved_by_user_id` wird nun direkt am Abwesenheitsdatensatz gespeichert (Freigabe/Ablehnung/Auto-Freigabe), inkl. Migration und API-Ausgabe.

### Geändert

- **Integrität der Urlaubsanspruch-Snapshots**: Deterministisches Key-Upsert auf `(user_id, period_key, as_of_date)` ergänzt und per Migrations-Unique-Index auf Datenbankebene abgesichert.
- **Nebenläufigkeitskontrolle in kritischen Workflows**: Create/Update/Approve/Reject/Substitute-Flows sind nun mit nutzerspezifischen Mutations-Locks plus transaktionalen Rechecks/Row-Locks abgesichert, um Race-Conditions bei Überschneidungen und Überfreigaben zu verhindern.
- **Release-Absicherung**: Workflow-bezogene Unit- und Integrationstests wurden auf die gehärteten Mutationspfade angepasst und erfolgreich ausgeführt.

### Behoben

- **Legacy-Snapshot-Reparaturpfad**: Upsert behandelt historische fehlerhafte Zeilen sowie parallele Unique-Key-Konflikte robust via deterministischem Retry-Update.
- **Race-Condition bei Resturlaub-Updates**: `VacationYearBalanceMapper::upsert` löst gleichzeitige Unique-Key-Konflikte jetzt per Re-Read/Update-Fallback.

## 1.2.5 – 2026-04-22

### Geändert

- **Release-Paket aktualisiert**: App-Metadaten auf `1.2.5` angehoben und den signierten Release-Artefaktsatz für App-Store- und GitHub-Veröffentlichung neu erzeugt.

## 1.2.4 – 2026-04-21

### Geändert

- **Release-Stand veröffentlichbar gemacht**: App-Metadaten auf `1.2.4` angehoben und ein neuer signierter Release-Artefaktsatz (Archiv, Checksummen, App-Store-Signatur) für App-Store-/GitHub-Veröffentlichung erstellt.

## 1.2.3 – 2026-04-21

### Geändert

- **Release-Paket aktualisiert**: Neues signiertes App-Store-/GitHub-Release-Archiv für den aktuellen Code-Stand mit Docker-basiertem Signatur-Workflow erstellt.

## 1.2.2 – 2026-04-21

### Behoben

- **Lokalisierte Dezimaleingaben in den Admin-Einstellungen**: Tagesstunden-Felder akzeptieren jetzt zuverlässig Kommawerte wie `7,74` und behalten zwei Nachkommastellen korrekt bei.
- **Parsing bei Legacy-Hours-Payloads**: Time-Entry-Endpunkte verarbeiten optionale Stundenwerte nun konsistent mit Komma und Punkt, sodass in rückwärtskompatiblen Requests keine stillen Abschneidungen mehr auftreten.

### Geändert

- **Präzisionshinweise in Eingabefeldern**: `step`-Werte und Hilfetexte wurden auf Zwei-Nachkommastellen-Szenarien (z. B. 38,7-Stunden-Woche) abgestimmt.

## 1.2.1 – 2026-04-21

### Behoben

- **Wiederzugriff auf pausierte Einträge**: Pausierte Einträge sind im Bearbeiten-/Löschen-Workflow wieder erreichbar und werden beim Speichern mit Endzeit konsistent als `completed` finalisiert.
- **Fortsetzen statt Duplikat bei gleichem Tag**: `Clock In` setzt einen pausierten Tages-Eintrag fort, statt einen neuen automatischen Eintrag zu erzeugen; die Pausenlücke wird korrekt als Break-Historie archiviert.
- **Historische Restfälle bei `paused`**: Neue Migration `Version1020Date20260421000000` repariert verbleibende verwaiste `paused`-Datensätze (auch Fälle außerhalb der früheren Einmal-Migration).

### Hinzugefügt

- **Auto-Fallback mit Nachvollziehbarkeit**: Zeiteinträge speichern jetzt `ended_reason` und `policy_applied` (z. B. `manual_clock_out` oder `auto_break_fallback`) für klare Audit-/Export-Nachweise.
- **Einmalige Nutzerinfo nach Auto-Ausstempeln**: Beim nächsten Statusabruf wird eine neutrale, konkrete Meldung mit Uhrzeit und Regel eingeblendet.
- **Urlaubsanspruch-Policy-Engine**: Neue berechnungslogikbasierte Anspruchsermittlung mit Modi `manual_fixed`, `model_based_simple`, `tariff_rule_based` und `manual_exception` inkl. Simulations-Endpunkt für Admins.
- **Tarifregel-Datenmodell und APIs**: Versionierte Tarif-Regelwerke/Module sowie Admin-Endpunkte zum Erstellen, Aktualisieren, Aktivieren, Stilllegen und Zuweisen von Urlaubs-Policies.
- **Snapshots der Anspruchsberechnung**: Persistente Snapshots (`at_entitlement_snapshots`) mit Berechnungstrace/Policy-Fingerprint für Nachvollziehbarkeit und Diagnose.
- **Neue Admin-Seite „Benachrichtigungen“**: Eigene Oberfläche für HR-Empfänger und Ereignis-Matrix inkl. dedizierter Notifications-API.

### Geändert

- **Fallback-Logik differenziert nach Einsatzart**: Für Schichtarbeit gilt standardmäßig eine strikte Fallback-Regel; für Nicht-Schichtmodelle eine flexible Regel mit tagsüber konfigurierbarem Ruhefenster (z. B. Familien-/Mittagsunterbrechung ohne Auto-Ausstempeln).
- **Export-Transparenz**: CSV/JSON-Zeilen enthalten jetzt `ended_reason` und `policy_applied`, damit automatische Beendigungen in Reports eindeutig erkennbar sind.
- **Urlaubsallokation integriert**: Jahresallokation nutzt nun die neue `VacationEntitlementEngine` und liefert Quelle/Regelwerk/Trace in der Ergebnisstruktur zurück.
- **Migrations-Kompatibilität**: Bestehende Urlaubswerte aus Nutzer-Modellzuweisungen werden in Policy-Zuordnungen überführt (`Version1018Date20260420123000`), damit Bestandsinstallationen konsistent weiterlaufen.
- **Admin-Einstellungsfluss für Abwesenheiten**: Carryover-/Rollover-, Vertretungs- und E-Mail-Schalter sind zentral über die neue Notifications-Seite/API steuerbar.
- **Schema Arbeitszeitmodelle**: `at_models` enthält jetzt `work_days_per_week` (`Version1019Date20260420150000`) als Grundlage für Formeln.

### Behoben

- **Aufräumen bei Nutzerlöschung**: Beim Entfernen eines Nutzers werden jetzt auch Urlaubs-Policy-Zuordnungen und Entitlement-Snapshots gelöscht (keine verwaisten Policy-/Berechnungsdaten).

## 1.2.0 – 2026-04-15

### Behoben

- **Zeitzonen-Konsistenz (Europe/Berlin)**: Server-/PHP-Zeitzone für ArbeitszeitCheck auf Deutschland ausgerichtet; neue Migration `Version1015Date20260415120000` konvertiert bestehende UTC-DATETIME-Werte in App-Tabellen nach `Europe/Berlin` und setzt `app_timezone` explizit.
- **Ausstempeln-Semantik korrigiert**: `clockOut()` finalisiert Einträge nun zuverlässig mit `end_time` und `status=completed` (statt `paused` ohne Endzeit). Dadurch sind Exporte/Reports wieder vollständig und konsistent.
- **Historische Pausiert-Einträge repariert**: Migration schließt verwaiste Einträge mit `status=paused` und `end_time IS NULL` automatisch über `end_time = updated_at` und Statuswechsel auf `completed`.
- **Mehrfach-Pausen ohne Datenverlust**: Beim Start einer weiteren Pause wird die zuvor abgeschlossene Pause zuerst in `breaks` (JSON) archiviert; Break-Dauern bleiben vollständig für ArbZG-Prüfungen erhalten.
- **Break-Status-Berechnung korrigiert**: `getBreakStatus()` zählt aktive Sitzungszeit nicht mehr doppelt; Warnstufen und Restpausen-Hinweise sind wieder korrekt.
- **Export-Spalten korrigiert**: `duration_hours` liefert jetzt Brutto-Dauer (Wall-Clock), `working_hours` Netto-Arbeitszeit (abzgl. Pausen). Vorher waren beide Spalten identisch.

### Geändert

- **Export-Transparenz**: CSV/JSON-Exporte enthalten jetzt explizite Zeitzonen-Metadaten (`timezone`, `exported_at`), damit nachgelagerte Systeme die Uhrzeiten eindeutig interpretieren.
- **UI-Klarheit**: Dashboard zeigt sichtbaren Zeitzonen-Hinweis (`Europe/Berlin (MEZ/MESZ)`), Export-Hinweis auf der Zeiteintragsseite nennt die verwendete Zeitzone.
- **Bediensicherheit**: Vor `Clock Out` erscheint eine Bestätigungsabfrage mit klarer Abgrenzung zwischen „Pause starten“ und „Ausstempeln“.
- **Admin-Transparenz**: In den Admin-Einstellungen wird die konfigurierte Zeitzone sichtbar angezeigt.

## 1.1.14 – 2026-04-14

### Behoben

- **Genehmigungs-Deadlock (App-Teams)**: Abwesenheiten und Zeiteintrags-Korrekturen behandeln „hat Kolleg:innen“ nicht mehr wie „hat eine:n Vorgesetzte:n“. Auto-Genehmigung, wenn **kein zuweisbarer Genehmiger** existiert, folgt `TeamResolverService::hasAssignableManagerForEmployee()` (explizite Team-Manager bei App-Teams; Legacy-Gruppenmodus weiterhin Kollegen-Proxy). Verhindert Anträge, die dauerhaft auf Managerfreigabe warten, obwohl niemand freigeben darf.
- **Zeiteintrags-Korrekturen**: Gleiche Zuweisbarkeitsregel wie bei Abwesenheiten (zuvor nur Kollegen-IDs).
- **Admin-Users API auf `/index.php`-Instanzen**: Refresh/Edit/History/Update nutzen nun zuverlässig aufgelöste App-URLs; fehlerhafte Requests wie `search=[object PointerEvent]` treten nicht mehr auf.
- **Admin-Teams und Settings auf Rewrite-losen Setups**: Zentrale URL-Auflösung enthält jetzt einen robusten `/index.php`-Fallback, wenn `OC.generateUrl()` im Seitenkontext fehlt oder unvollständig ist.

### Hinzugefügt

- **Repair-Schritt** `ReleaseStuckPendingAbsences`: setzt nach Migration verbliebene `pending`-Abwesenheiten unter derselben Bedingung automatisch auf genehmigt (idempotent).
- **Frontend-URL-Sicherheitsleitplanken**: Die gemeinsame AJAX-Schicht blockiert externe Cross-Origin-Calls standardmäßig (explizit `allowExternal: true` nötig); Unit-Tests decken URL-Normalisierung und External-Handling ab.
- **Lint-Leitplanken**: ESLint-Regeln verhindern neue rohe `fetch('/apps/arbeitszeitcheck/...')`-Aufrufe und implizite externe `fetch(...)`-Nutzung außerhalb der vorgesehenen Abstraktionen.

### Geändert

- **UX**: Abwesenheiten zeigen einen Hinweis, wenn App-Teams aktiv sind und kein Genehmiger zugeordnet ist; in der Detailansicht erscheint bei veralteten hängenden Anträgen ein Warnhinweis (bis Repair/Admin die Teamkonfiguration korrigiert).
- **Frontend-Architektur**: `ArbeitszeitCheckUtils` stellt nun zentral `getRequestToken()`, `resolveUrl()` und `isExternalUrl()` bereit; genutzt u. a. in `admin-users`, `reports`, `settings` und `validation`.
- **Mobile UX Konsistenz (WCAG 2.1 AA)**: iPhone-Safe-Area-konforme Abstände, bessere Touch-Targets, klarere Abschnittsstruktur und visuelle Hierarchie für Nutzerseiten (`dashboard`, `time-entries`, `absences`) sowie Managerseiten (`manager-dashboard`, `manager-time-entries`, Mitarbeiter-Abwesenheiten).

### Dokumentation

- Nutzerhandbücher (EN/DE), `tests/WORKFLOW_ROLE_MATRIX.md` und Entwicklerdokumentation zur Semantik „zuweisbarer Manager“ und zum Repair-Schritt ergänzt.
- README und Entwicklerdokumentation um zentrale Frontend-URL-Policy, striktes External-Call-Verhalten und Mobile/iOS-Layout-Hinweise ergänzt.

## 1.1.13 – 2026-04-13

### Hinzugefügt

- **Monatsabschluss: Karenz und Auto-Finalisierung**: Admin-Einstellung `month_closure_grace_days_after_eom` (0–90, Standard 0). Nach Monatsende haben Mitarbeitende so viele Kalendertage zur manuellen Finalisierung; ist der Monat danach noch offen, finalisiert ein täglicher Hintergrundauftrag automatisch (gleicher Snapshot wie manuell). Ausstehende Zeiteintragsfreigaben und offene Abwesenheits-Workflows blockieren die Auto-Finalisierung. Wiederöffnen bleibt Administrator:innen vorbehalten.
- **App-Admin-Whitelist**: Neue Admin-Einstellung `app_admin_user_ids`, um die Administration von ArbeitszeitCheck auf eine ausgewählte Teilmenge der Nextcloud-Admins zu begrenzen. Leere Auswahl bleibt rückwärtskompatibel (alle Nextcloud-Admins dürfen die App verwalten).
- **Docker-Testziel für Security-Role-Gating**: Verdrahtung von `scripts/test-security-role-gating-docker.sh` über `make test-security-role-gating-docker` und `composer test:security-role-gating:docker` für schnelle Autorisierungs-Regressionstests im Container-Setup.

### Geändert

- **Monatsabschluss UX/API**: Klarere Karten-UI, sichtbares Erfolgs-/Fehlerfeedback (WCAG), serverseitiges `canFinalize` mit lokalisierten Sperrgründen; manuelle Finalisierung lehnt zukünftige Kalendermonate ab; Abwesenheits-Workflow (`pending`, `substitute_pending`, `substitute_declined`) zusätzlich zu ausstehenden Zeiteintragskorrekturen; API 401 bei fehlender Anmeldung wo passend; Admin: eigener Abschnitt „Monatsabschluss“; Karenzfeld bleibt editierbar mit Hinweis, dass der Wert gespeichert wird und bei aktivierter Funktion gilt; Wiederöffnen mit durchsuchbarer Mitarbeitenden-Auswahl und klarerer Rollenbeschreibung; Validierungsfehler mit höherem Kontrast über Themes hinweg. Auto-Finalize protokolliert Einzelfehler.
- **Release-/Signatur-Workflow für Integritätsprüfung gehärtet**: `make release-signed` signiert jetzt den entpackten Release-Archivinhalt (nicht den lokalen Entwicklungs-Checkout), prüft verbotene Entwicklungs-Pfade und packt das signierte Archiv für Deployment/App-Store neu.
- **Admin-Autorisierung zentral erzwungen**: Zugriffe auf `AdminController`-Routen werden jetzt per Middleware auf App-Admin-Rechte geprüft; nicht berechtigte angemeldete Nutzer erhalten eine konsistente 403-Seite.

### Dokumentation

- **Deployment-Hinweise ergänzt**: Die Release-Dokumentation fordert nun explizit das Deployment aus dem signierten Tarball und beschreibt das typische Fehlerbild (`.git/*` / `node_modules/*`) bei versehentlicher Signierung eines Dev-Trees.
- **Deployment-Helferskript**: `release/deploy-from-release.sh` hinzugefügt für Deployment aus signierten Release-Archiven mit Sicherheitsprüfungen (verbotene Pfade, erforderliche `signature.json`, optionales Disable/Enable und `occ integrity:check-app`).
- **Admin-Betrieb**: Nutzer-/Entwicklerdokumentation ergänzt um Einrichtung der App-Admin-Whitelist, Rückfallverhalten bei leerer Auswahl und Verifikation des Role-Gatings im Docker-Testlauf.

## 1.1.12 – 2026-04-09

### Hinzugefügt

- **Revisionssichere Monatsfinalisierung (optional)**: Admin-Schalter `month_closure_enabled` (Standard aus). Mitarbeitende können einen vollen Kalendermonat finalisieren; die App speichert kanonischen JSON-Snapshot, SHA-256-Hashkette, Anhänge-Revisionen, Audit-Ereignisse und ein schlankes PDF. Finalisierte Monate sind über normale App-APIs nicht mehr änderbar; Administrator:innen können einen Monat mit Pflichtbegründung wieder öffnen (Audit). Monatsberichte für finalisierte Monate lesen den gespeicherten Snapshot. Datenbank: `at_month_closure`, `at_month_closure_revision` (Migration `Version1014Date20260409120000`).

### Dokumentation

- Nutzerhandbücher (DE/EN), Entwicklerdokumentation und Compliance-Hinweise zu Monatsabschluss, Aufbewahrung und Grenzen (Nachweis in der App, keine QES) ergänzt.

## 1.1.11 – 2026-04-09

### Hinzugefügt

- **Manager-Ansicht „Mitarbeiter-Abwesenheiten“**: Neue In-App-Seite und API für Manager/Admins zur Einsicht von Abwesenheiten mit sicherer Bereichsfilterung, Pagination und lokalisierten Statusbezeichnungen.
- **Kopierfunktion für Arbeitszeitmodelle**: Neue Kopieraktion mit Modal-UX, eindeutiger Namensvorschlag-Logik und Schutz gegen Doppelklicks.

### Geändert

- **Manager-Navigation / Sidebar**: Struktur in klarere Manager-/Admin-Untermenüs überführt; Berichte in den Manager-Kontext verschoben; Compliance-Link zur besseren Übersicht umgruppiert.
- **UX Mitarbeiter-Zeiteinträge (Manager)**: Standard-Datumswerte sowie Datums-/Übersetzungsdarstellung im Filterfluss verbessert.
- **Kalender-Verhalten (Rollback-Bereinigung)**: Angefangene Funktionalität für direkte Kalendereinträge sowie zugehörige Admin-Optionen/Status/Test-Endpunkte wurde entfernt. Das unterstützte Verhalten bleibt unverändert: keine Synchronisation mit der Nextcloud-Kalender-App; optionale `.ics`-Anhänge werden weiterhin per E-Mail in den konfigurierten Abwesenheits-Workflows versendet.

### Behoben

- **Arbeitszeitmodell-Modaldialoge**: Interaktionsprobleme im Kopier-Modal, Darstellung des Quellmodells sowie Lokalisierung/Formatierung im Lösch-Dialog korrigiert.
- **Abwesenheits-iCal-Härtung**: Strengere Status-/Datumsprüfungen, Empfänger-Deduplizierung und datenschutzärmere Beschreibungen für Vertretung/Manager ergänzt.

### Dokumentation

- Nutzerhandbücher und Changelogs an das finale Kalenderverhalten (optionale `.ics`-Mail, keine direkte Kalender-App-Synchronisation) sowie die aktuelle Manager/Admin-UX-Struktur angepasst.

## 1.1.10 – 2026-04-07

### Hinzugefügt

- **Urlaubsübertrag / Rollover**: `VacationRolloverService`, Hintergrundauftrag, `occ arbeitszeitcheck:vacation-rollover`, Migration `Version1013Date20260407120000` mit `at_vacation_rollover_log`; Unit-Tests.

### Geändert

- **Frontend-L10n**: Gemeinsame Partials `templates/common/main-ui-l10n.php` und `teams-l10n.php`, damit Übersetzungen früh verfügbar sind; zugehörige Template- und JS-Anpassungen.

### Behoben

- **Manager-Dashboard — ausstehende Abwesenheiten**: Die API liefert `summary.typeLabel` (serverseitig übersetzter Abwesenheitstyp); die Oberfläche nutzt das bevorzugt, damit Karten lokalisierte Bezeichnungen zeigen (z. B. *Urlaub*) statt Rohcodes wie `vacation`.

### Dokumentation

- `docs/Developer-Documentation.en.md`: API-Hinweis zu `typeLabel` bei Pending Approvals; Nutzerhandbücher EN/DE: Hinweis zu lokalisierten Abwesenheitstypen bei ausstehenden Genehmigungen.

## 1.1.9 – 2026-04-05

### Entfernt

- **Nextcloud-Kalender-App (CalDAV)**: Synchronisation von Abwesenheiten in die Kalender-App ist entfernt; Migration `Version1012Date20260406120000` entfernt die Tabelle `at_absence_calendar`. Bereits angelegte Kalender in der Kalender-App bleiben bestehen, bis Nutzer sie dort löschen.

### Geändert

- **Feiertage / Kalenderlogik**: In der Klasse `HolidayService` gebündelt.

### Behoben

- **AdminController**: Doppelte `use`-Anweisung für `HolidayService` führte zu einem PHP-Fatal (u. a. beim Laden durch PHPUnit).

### Dokumentation

- Nutzerhandbücher EN/DE (`docs/User-Manual.*`), README- und Entwicklerdokumentation aktualisiert; Hilfsskript `docker/run-app-phpunit.sh` für PHPUnit im Container.

## 1.1.7 – 2026-04-05

### Hinzugefügt

- **Resturlaub / Urlaubsübertrag**: Pro Nutzer und Kalenderjahr Eröffnungsbestand `carryover_days` in `at_vacation_year_balance`; globale Admin-Einstellung für Ablauf des Vorjahresurlaubs (Monat/Tag, Standard 31.03.). `VacationAllocationService` wendet FIFO auf genehmigten Urlaub an (nach `start_date`, dann `id`) und teilt Arbeitstage vor/nach Ablauf, sodass Resturlaub zuerst verbraucht wird, solange er gültig ist.
- **Validierung & Freigaben**: Urlaubsanträge werden bei Manager-Freigabe (und bei Auto-Approve) erneut geprüft, damit parallele Anträge nach Genehmigung das Kontingent nicht überziehen.
- **API & UI**: `AbsenceController::stats` liefert Anspruch, Übertrag, Summen und ablaufbezogene Felder; Dashboard und Abwesenheiten zeigen eine klare Urlaubsübersicht; Admin-Einstellungen enthalten Ablauffelder.
- **DSGVO**: `UserDeletedListener` löscht Urlaubs-Jahresbestände bei Kontolöschung.
- **Migration / Massenpflege**: `occ arbeitszeitcheck:import-vacation-balance` importiert CSV `user_id,year,carryover_days` mit `--dry-run`.

### Tests

- Unit-Tests für `VacationAllocationService`; erweiterte Tests für `AbsenceService` und zugehörige Controller.

## 1.1.6 – 2026-03-27

### Hinzugefügt

- **Entwicklung**: CLI `occ arbeitszeitcheck:generate-test-data` für deterministische Demo-Daten (Zeiteinträge, Abwesenheiten, optional Verstöße, Demo-App-Team) zum Testen von UI, Berichten und Workflows.
- **Exporte**: `TimeEntryExportTransformer` bündelt Feldzuordnung und CSV-Aufbereitung für Zeiteintrags-Exporte; `ExportController` delegiert daran für eine einheitliche, testbare Pipeline.

### Behoben

- **Berichte-UI**: Berichtstyp-Karten werden bei teambezogenem Scope nicht mehr fälschlich deaktiviert.
- **Berichte (Tests)**: CSV-Download-Test nutzt `DataDownloadResponse::render()` für den Dateiinhalt.
- **Team-Berichte**: Nutzer-IDs werden vor Berechtigungsprüfung und Aggregation dedupliziert (keine Doppelzählung bei Mehrfach-Teams).
- **Abwesenheits-Badges**: Besser lesbare, theme-sichere Kontraste für Urlaub / Krank / Homeoffice / Sonstiges.

### Geändert

- **Kompatibilität (Dev)**: Lokale Entwicklungsumgebungen an Nextcloud 33.x ausgerichtet (z. B. offizielles `nextcloud`-Docker-Image).
- **Berichte-Layout**: Zu aggressive Vollbreiten-Regel für das Parameterformular zurückgenommen (verbessert Scroll/Layout).
- **Berichte-UI**: Anpassungen an Templates, JavaScript und Styles auf der Berichtsseite; Admin-Einstellungen mit zugehörigem Hook.
- **Reporting**: Anpassungen in `ReportController` und `ReportingService` passend zum Export-Refactoring.

### Tests

- Unit-Tests für `TimeEntryExportTransformer`; erweiterte `ReportController`-Tests; `ExportController`-Tests an neue Verdrahtung angepasst.

## 1.1.4 – 2026-03-25

### Behoben
- **Routing/Kompatibilität**: `indexApi()`-Kompatibilitätsaliases für Legacy-Endpunkte ergänzt, um 500-Fehler in den Nextcloud-Logs zu verhindern.
- **PHP-Fatals**: Konstruktor-Signaturprobleme in `AbsenceService` und `ComplianceService` behoben (konnte die App beim Laden von Services oder beim Speichern von Einstellungen zum Absturz bringen).
- **Reports-Sicherheit**: Vorschau-Endpunkte gehärtet (`start <= end` Validierung + maximale Zeitraumbegrenzung) um DoS-Risiken durch untrusted Parameter zu reduzieren.
- **Admin-“Gesamte Organisation”**: Admin-Organisation-Scope korrekt verarbeitet (`userId=""` = alle aktivierten Nutzer) inklusive passender Zugriffsprüfung, damit Preview/Download konsistent bleiben.
- **Reports-Rendering**: Preview-Darstellung für **Abwesenheiten** und **Compliance** verbessert, sodass sie zur tatsächlichen Ergebnisstruktur passt.

### Geändert
- **Reports-UI-Semantik**: Team-Scope auf Team-Overview-/Export-Semantik eingeschränkt (verhindert irreführende Preview/Downloads).
- **Organisation-Download Hinweis**: UI-Hinweis ergänzt, dass Organisation-Download erst vollständig unterstützt ist, sobald dedizierte Organization-Export-Endpunkte verfügbar sind.

## 1.1.3 – 2025-03-14
### Behoben
- **ArbZG-Compliance**: Pausenprüfung korrigiert (9h/45min-Zweig erreichbar; Prüfung ≥9h vor ≥6h)
- **Manager-Logik**: `employeeHasManager()` nutzt nun `getManagerIdsForEmployee()` statt `getColleagueIds()`
- **Berichte**: `getTeamHoursSummary()` berücksichtigt Periodenparameter (Woche/Monat)
- **Admin-Benutzer**: `hasTimeEntriesToday` pro Benutzer statt systemweit
- **UserSettingsMapper**: Falsy-Null/Leerstring-Behandlung in getIntegerSetting, getFloatSetting, getStringSetting
- **Routing**: exportUsers-Route vor getUser verschoben (Shadowing behoben)
- **Version1009-Migration**: MySQL-Backticks durch portablen QueryBuilder ersetzt; OCP\DB\Types
- **Doppelte Notifier-Registrierung**: Aus Application.php boot() entfernt
- **API-Sicherheit**: Generische Fehlermeldungen statt roher Exceptions (SubstituteController, GdprController)
- **PDF-Export**: HTTP 422 mit klarer Meldung statt stillem CSV-Fallback
- **LIKE-Injection**: WorkingTimeModelMapper::searchByName() verwendet escapeLikeParameter()
- **XSS**: Modal-Titel in components.js escaped; compliance-violations.js innerHTML escaped
- **Admin-Einstellungen**: CSRF-requesttoken ergänzt
- **AbsenceService DI**: Konstruktorargument-Reihenfolge (IDBConnection) korrigiert
- Admin-Feiertage und -Einstellungen: englische Quellstrings für l10n
- UserDeletedListener: TeamMemberMapper und TeamManagerMapper per Injection
- XSS: Team-Namen in admin-teams.js bereinigt

### Geändert
- **CSS**: Shadow-Light-Variable, scopierte Resets, Dark-Mode color-mix, semantische Farben, Navigationshöhe/z-index
- **Uhr-Buttons**: Doppel-Submit-Guard (deaktiviert während API-Aufrufen)
- **initTimeline()**: Max-Retry (20) gegen Endlosschleife
- **Barrierefreiheit**: aria-label auf Header-Buttons, Label für Admin-Suche, aria-modal im Willkommens-Dialog, englische l10n-Keys in Navigation
- **Dokumentation**: Interne Docs entfernt; docs/README ergänzt; Repo-URLs korrigiert
- **Manager-Dashboard**: l10n von PHP an JS übergeben für Übersetzungen
- Constants.php; benutzerfreundliche Fehlermeldungen
- **Zeiteintrags-Export**: Optional (per Admin-Einstellung) können Einträge, die über Mitternacht laufen, im CSV/JSON-Export rein darstellungsbezogen in zwei Kalendertage segmentiert werden (vor/nach 00:00). Die zugrundeliegende Arbeitszeit- und ArbZG-Compliance-Berechnung bleibt unverändert auf Basis des originalen, unsplitteten Zeiteintrags.

### Hinzugefügt
- **Version1010-Migration**: Zusammengesetzte Indizes auf at_entries, at_violations, at_holidays, at_absences

## 1.1.2 – 2025-03-07
### Geändert
- Langfristiges Refactoring: Ersetzung aller `\OC::$server`-Verwendungen durch OCP-APIs und Konstruktor-Injection
- CSPService: ContentSecurityPolicyNonceManager per Konstruktor injiziert
- Controller: manuelles cspNonce entfernt (configureCSP übernimmt dies); IURLGenerator und IConfig injiziert, wo nötig
- PageController: IURLGenerator und IConfig injiziert; übergibt urlGenerator an Templates
- HealthController: IDBConnection für Datenbank-Check injiziert
- ProjectCheckIntegrationService: LoggerInterface statt OC::$server->getLogger() injiziert
- Templates: `\OC::$server` durch `\OCP\Server::get()` (öffentliche OCP-API) ersetzt
- GitHub-Actions-Release-Workflow hinzugefügt (`.github/workflows/release.yml`)
- PageControllerTest mit vollständigen Konstruktor-Mocks aktualisiert

## 1.1.1 – 2025-01-07
### Behoben
- Doppelte Routen-Namen in der Abwesenheits-API behoben (absence#store, absence#show, absence#update, absence#delete)
- Klassen-Namen der Settings in info.xml korrigiert, um den vollständigen OCA-Namespace zu verwenden
- `declare(strict_types=1)` zu routes.php hinzugefügt

### Geändert
- Nicht vorhandene Screenshot-Referenzen aus info.xml entfernt, bis echte Screenshots verfügbar sind

## 1.1.0 – 2025-01-04
### Hinzugefügt
- ProjectCheck-Integration für Projektzeiterfassung
- Zusätzliche Migrationen für Schema-Updates

## 1.0.3 – 2025-01-03
### Hinzugefügt
- Weitere Verfeinerungen des Datenbankschemas

## 1.0.2 – 2025-01-02
### Hinzugefügt
- Arbeitszeitmodelle
- Zuweisung von Arbeitszeitmodellen zu Nutzern

## 1.0.1 – 2025-01-01
### Hinzugefügt
- Abwesenheitsverwaltung
- Audit-Logging
- Benutzer-Einstellungen
- Tracking von Compliance-Verstößen

## 1.0.0 – 2024-12-29
### Hinzugefügt
- Erste Veröffentlichung
- Arbeitszeiterfassung gemäß deutschem Arbeitszeitgesetz (ArbZG)
- Kommen-/Gehen- und Pausen-Erfassung
- Verwaltung von Zeiteinträgen (Erstellen, Bearbeiten, Löschen, manuelle Einträge)
- Grundlegende Compliance-Prüfungen (max. 8h/Tag, Pausenanforderungen)
- DSGVO-konforme Datenverarbeitung
- Deutsche und englische Übersetzungen
- WCAG-2.1-AAA-Accessibility-Compliance

