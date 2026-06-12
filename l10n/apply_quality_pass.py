#!/usr/bin/env python3
"""Apply manually curated l10n quality fixes (key→value only, no regex on strings)."""
from __future__ import annotations

import json
from pathlib import Path

L10N = Path(__file__).parent

PLURAL_FORMS = {
    "pl": "nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);",
    "sv": "nplurals=2; plural=(n != 1);",
    "nb": "nplurals=2; plural=(n != 1);",
    "it": "nplurals=2; plural=(n != 1);",
}


def load_json(lang: str) -> dict:
    with (L10N / f"{lang}.json").open(encoding="utf-8") as f:
        return json.load(f)


def save_json(lang: str, data: dict) -> None:
    with (L10N / f"{lang}.json").open("w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
        f.write("\n")


def write_js(lang: str, trans: dict) -> None:
    lines = ['OC.L10N.register(\n\t"arbeitszeitcheck", {\n']
    items = list(trans.items())
    for i, (k, v) in enumerate(items):
        if isinstance(v, list):
            val = "[" + ", ".join(json.dumps(p, ensure_ascii=False) for p in v) + "]"
        else:
            val = json.dumps(v, ensure_ascii=False)
        comma = "," if i < len(items) - 1 else ""
        lines.append(f"\t{json.dumps(k, ensure_ascii=False)} : {val}{comma}\n")
    pf = PLURAL_FORMS.get(lang, "nplurals=2; plural=(n != 1);")
    lines.append("}, {\n")
    lines.append(f'\t"pluralForm": {json.dumps(pf, ensure_ascii=False)},\n')
    lines.append(f'\t"pluralRule": {json.dumps(pf, ensure_ascii=False)}\n')
    lines.append("});\n")
    (L10N / f"{lang}.js").write_text("".join(lines), encoding="utf-8")


def apply_fixes(lang: str, fixes: dict[str, str]) -> int:
    data = load_json(lang)
    trans = data["translations"]
    applied = 0
    for k, v in fixes.items():
        if k in trans:
            trans[k] = v
            applied += 1
    data["translations"] = trans
    save_json(lang, data)
    write_js(lang, trans)
    return applied


# Extra fixes not in the JSON bundles (it/nb/pl federal-state label).
EXTRA: dict[str, dict[str, str]] = {
    "pl": {
        "Default federal state for holidays": "Domyślny kraj związkowy dla świąt",
        "No time entries for this day": "Brak wpisów czasu na ten dzień",
        "Overview of your vacation days and sick leave": "Przegląd Twoich dni urlopowych i zwolnień lekarskich",
        "Overview of your working hours, break time, and overtime for today.": "Przegląd Twoich godzin pracy, czasu przerwy i nadgodzin na dziś.",
        "Overview of your working time and status": "Przegląd Twojego czasu pracy i statusu",
        "Payroll must record the overtime payout for this month before you can finalize.": "Księgowość płac musi zarejestrować wypłatę nadgodzin za ten miesiąc, zanim będzie można go zamknąć.",
        "Request absence for this day": "Złóż wniosek o nieobecność na ten dzień",
        "See all important details for this absence in one simple overview.": "Zobacz wszystkie ważne szczegóły tej nieobecności w jednym prostym podglądzie.",
        "Select the date for this time entry": "Wybierz datę dla tego wpisu czasu",
        "Statutory holidays are automatically restored when the calendar is viewed, unless \"Auto-restore statutory holidays\" is disabled in Settings.": "Święta ustawowe są automatycznie przywracane przy wyświetlaniu kalendarza, chyba że opcja „Automatyczne przywracanie świąt ustawowych” jest wyłączona w Ustawieniach.",
        "Typical number of work days each week for this model (e.g. 4 for a four-day week). Used for model-based vacation entitlement calculations.": "Typowa liczba dni roboczych w tygodniu dla tego modelu (np. 4 przy czterodniowym tygodniu). Używana do obliczeń urlopowych na podstawie modelu.",
        "View and manage all time entries for this day. You can edit entries or request corrections.": "Przeglądaj i zarządzaj wszystkimi wpisami czasu na ten dzień. Możesz edytować wpisy lub wnioskować o korekty.",
        "When a colleague requests an absence and selects you as their substitute, you will see the request here.": "Gdy współpracownik złoży wniosek o nieobecność i wybierze Cię jako zastępcę, zobaczysz prośbę tutaj.",
        "You are not the designated substitute for this absence": "Nie jesteś wyznaczonym zastępcą dla tej nieobecności",
        "Your absence history will appear here": "Tutaj pojawi się historia Twoich nieobecności",
        "endDateHelp": "Opcjonalna data zakończenia tego przypisania. Bez daty model pozostaje ważny do zmiany.",
        "vacationDaysHelp": "Liczba dni urlopu rocznie dla tego modelu czasu pracy lub przypisania.",
        "Your administrator disabled adding hours by hand. You can still view entries and request corrections if something is wrong.": "Twój administrator wyłączył ręczne dodawanie godzin. Nadal możesz przeglądać wpisy i wnioskować o korekty, jeśli coś jest nie tak.",
    },
    "it": {
        "gdpr_data_deletion_request": "Richiesta di cancellazione dei dati GDPR",
    },
    "nb": {
        "Automatic clock-out (ArbZG §3) could not be completed. Please clock out manually.": "Automatisk utstempling (ArbZG §3) kunne ikke fullføres. Stemple ut manuelt.",
        "Failed to add member": "Kunne ikke legge til medlem",
        "Failed to copy model": "Kunne ikke kopiere modellen",
        "Failed to delete model": "Kunne ikke slette modellen",
        "Failed to delete team": "Kunne ikke slette teamet",
        "Failed to delete unit": "Kunne ikke slette enheten",
        "Failed to save setting": "Kunne ikke lagre innstillingen",
        "Failed to save settings": "Kunne ikke lagre innstillingene",
        "Failed to save vacation entitlement layer": "Kunne ikke lagre ferierettighetslaget",
        "Failed to update team": "Kunne ikke oppdatere teamet",
        "Failed to update unit": "Kunne ikke oppdatere enheten",
        "Holiday could not be removed.": "Helligdagen kunne ikke fjernes.",
        "Holiday could not be saved.": "Helligdagen kunne ikke lagres.",
        "Technical error: Required fields for the holiday could not be found.": "Teknisk feil: Påkrevde felt for helligdagen ble ikke funnet.",
        "The default federal state could not be saved.": "Standard forbundsstat kunne ikke lagres.",
        "Working time was saved, but billing hours in ProjectCheck could not be updated. Please check the project link or contact an administrator.": "Arbeidstid ble lagret, men faktureringstimer i ProjectCheck kunne ikke oppdateres. Sjekk prosjektkoblingen eller kontakt en administrator.",
        "Number of years to keep time tracking data before automatic deletion (typically at least 2 years).": "Antall år tidsregistreringsdata skal beholdes før automatisk sletting (vanligvis minst 2 år).",
        "Automatic clock-out (ArbZG §3) failed repeatedly. Please clock out manually or contact your administrator.": "Automatisk utstempling (ArbZG §3) mislyktes gjentatte ganger. Stemple ut manuelt eller kontakt administratoren din.",
        "Clock times are stored using the organization timezone (%1$s) and shown in your personal timezone (%2$s). Exports may follow export settings.": "Klokkeslett lagres med organisasjonens tidssone (%1$s) og vises i din personlige tidssone (%2$s). Eksporter kan følge eksportinnstillingene.",
        "Failed to clock out": "Kunne ikke stemple ut",
        "How times are stored": "Hvordan tider lagres",
        "If set, this date is saved as the overtime “Stichtag” when the new account is created. Leave empty to configure later in ArbeitszeitCheck → Administration → Employees.": "Hvis angitt, lagres denne datoen som overtidens «Stichtag» når den nye kontoen opprettes. La stå tom for å konfigurere senere i ArbeitszeitCheck → Administrasjon → Ansatte.",
        "Note: Team reports currently support single user selection. Multiple users can be added in future updates.": "Merk: Teamrapporter støtter foreløpig valg av én bruker. Flere brukere kan legges til i fremtidige oppdateringer.",
        "Note: You are approaching the maximum working hours. Extended hours must be compensated within 6 months (ArbZG §3).": "Merk: Du nærmer deg maksimal arbeidstid. Forlenget arbeidstid må kompenseres innen 6 måneder (ArbZG §3).",
        "Pause or clock out": "Pause eller stemple ut",
        "Removed statutory holidays are restored automatically while auto-restore is enabled in settings.": "Fjernede lovpålagte helligdager gjenopprettes automatisk mens automatisk gjenoppretting er aktivert i innstillingene.",
        "Select a team member to generate the team report. Multiple users can be added in future updates.": "Velg et teammedlem for å generere teamrapporten. Flere brukere kan legges til i fremtidige oppdateringer.",
        "Start and end times are shown in your personal timezone (%2$s). Values are stored using the organization timezone (%1$s) so daylight saving time is handled consistently.": "Start- og sluttidspunkter vises i din personlige tidssone (%2$s). Verdier lagres med organisasjonens tidssone (%1$s), slik at sommertid håndteres konsekvent.",
        "Statutory holiday removed. It will be added again automatically because auto-restore is enabled.": "Lovpålagt helligdag fjernet. Den legges til igjen automatisk fordi automatisk gjenoppretting er aktivert.",
        "Statutory holidays are automatically restored when the calendar is viewed, unless \"Auto-restore statutory holidays\" is disabled in Settings.": "Lovpålagte helligdager gjenopprettes automatisk når kalenderen vises, med mindre «Automatisk gjenoppretting av lovpålagte helligdager» er deaktivert i Innstillinger.",
        "When enabled, breaks required by German law are automatically added. Disable to manage breaks manually.": "Når aktivert, legges pauser som kreves av tysk lov til automatisk. Deaktiver for å administrere pauser manuelt.",
        "When enabled, missing statutory holidays are added when the calendar is viewed. Disable if you want deleted holidays to stay removed.": "Når aktivert, legges manglende lovpålagte helligdager til når kalenderen vises. Deaktiver hvis du vil at slettede helligdager skal forbli fjernet.",
        "You can set this even while month finalization is disabled; the value is saved with \"Save all settings\" and applies when you enable month finalization above.": "Du kan angi dette selv om månedsavslutning er deaktivert; verdien lagres med «Lagre alle innstillinger» og gjelder når du aktiverer månedsavslutning ovenfor.",
        "You can set this even while month finalization is disabled; the value is saved with “Save all settings” and applies when you enable month finalization above.": "Du kan angi dette selv om månedsavslutning er deaktivert; verdien lagres med «Lagre alle innstillinger» og gjelder når du aktiverer månedsavslutning ovenfor.",
        "Your manager must approve this change before it is saved.": "Lederen din må godkjenne denne endringen før den lagres.",
        "Your selection is saved automatically.": "Valget ditt lagres automatisk.",
        "month_closure_pdf_integrity_note": "Det kryptografiske seglet for denne måneden er SHA-256-snapshot-hashen vist i identifikasjonsdelen ovenfor. Den fullstendige kanoniske dataposten lagres på serveren og er ikke innebygd i denne PDF-en.\n\nDette dokumentet oppsummerer de samme faktum for arkivformål. All formell verifisering bruker den lagrede hashen og nyttelasten, ikke en utskrift eller kopi av denne filen.",
        "Auto clock-out after break (minutes)": "Automatisk utstempling etter pause (minutter)",
        "Automatic clock-out blocked: this calendar month has been finalized. Please contact an administrator.": "Automatisk utstempling er blokkert: denne kalendermåneden er avsluttet. Kontakt administratoren din.",
        "Break was still active after %1$d minutes. Automatic clock-out was applied at %2$s (%3$s policy).": "Pausen var fortsatt aktiv etter %1$d minutter. Automatisk utstempling ble brukt ved %2$s (%3$s-policy).",
        "Clock in/out (stamping) is not enabled for your account. Please contact your administrator.": "Inn-/utstempling (stempling) er ikke aktivert for kontoen din. Kontakt administratoren din.",
        "Clock in/out is not enabled for your account. Record your hours under Time entries instead.": "Inn-/utstempling er ikke aktivert for kontoen din. Registrer timene dine under Tidsregistreringer i stedet.",
        "Clock in/out is turned off for you": "Inn-/utstempling er slått av for deg",
        "Enable clock in/out or manual time entries — at least one method is required for the organisation.": "Aktiver inn-/utstempling eller manuelle tidsregistreringer — minst én metode er påkrevd for organisasjonen.",
        "Enable clock in/out or manual time entries — at least one method is required.": "Aktiver inn-/utstempling eller manuelle tidsregistreringer — minst én metode er påkrevd.",
        "For non-shift models (flex policy), automatic clock-out is suppressed inside this daytime window. Shift work remains strict.": "For ikke-skiftmodeller (fleksibel policy) undertrykkes automatisk utstempling i dette dagsvinduet. Skiftarbeid forblir strengt.",
    },
}

SV_EXTRA: dict[str, str] = {
    "Automatic clock-out (ArbZG §3) failed repeatedly. Please clock out manually or contact your administrator.": "Automatisk utstämpling (ArbZG §3) misslyckades upprepade gånger. Stämpla ut manuellt eller kontakta din administratör.",
    "Clock in/out (stamping) is not enabled for your account. Please contact your administrator.": "In-/utstämpling (stämpling) är inte aktiverad för ditt konto. Kontakta din administratör.",
    "Clock in/out is not enabled for your account. Record your hours under Time entries instead.": "In-/utstämpling är inte aktiverad för ditt konto. Registrera i stället dina timmar under Tidsregistreringar.",
    "Clock in/out is turned off for you": "In-/utstämpling är avstängd för dig",
    "Pause or clock out": "Rast eller stämpla ut",
}


def main() -> None:
    from apply_quality_fixes import IT_MANUAL, PL_MANUAL, SV_NB_SHARED

    pl_fixes = json.loads((L10N / "_quality_fixes_pl.json").read_text(encoding="utf-8"))
    pl_fixes.update(PL_MANUAL)
    pl_fixes.update(EXTRA.get("pl", {}))
    sv_fixes = json.loads((L10N / "_quality_fixes_sv.json").read_text(encoding="utf-8"))
    sv_fixes.update(SV_NB_SHARED.get("sv", {}))
    sv_fixes.update(SV_EXTRA)
    nb_fixes = dict(EXTRA.get("nb", {}))
    nb_fixes.update(SV_NB_SHARED.get("nb", {}))
    it_fixes = dict(IT_MANUAL)
    it_fixes.update(EXTRA.get("it", {}))

    print(f"PL: {apply_fixes('pl', pl_fixes)} fixes")
    print(f"SV: {apply_fixes('sv', sv_fixes)} fixes")
    print(f"IT: {apply_fixes('it', it_fixes)} fixes")
    print(f"NB: {apply_fixes('nb', nb_fixes)} fixes")


if __name__ == "__main__":
    main()
