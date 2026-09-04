#!/usr/bin/env python3
"""Apply remaining hand-curated l10n translations (no MT)."""
from __future__ import annotations

import json
import subprocess
from pathlib import Path

L10N = Path(__file__).parent
ROOT = L10N.parents[2]

# Shared UI strings (msgid -> per-lang value)
SHARED: dict[str, dict[str, str]] = {
    "Help & Feedback": {
        "de": "Hilfe & Feedback",
        "fr": "Aide & commentaires",
        "es": "Ayuda y comentarios",
        "da": "Hjælp og feedback",
        "nl": "Hulp & feedback",
        "it": "Aiuto e feedback",
        "pl": "Pomoc i opinie",
        "sv": "Hjälp & feedback",
        "nb": "Hjelp og tilbakemelding",
        "pt_BR": "Ajuda e feedback",
    },
    "Official Android apps on Google Play": {
        "de": "Offizielle Android-Apps bei Google Play",
        "fr": "Applications Android officielles sur Google Play",
        "es": "Aplicaciones Android oficiales en Google Play",
        "da": "Officielle Android-apps på Google Play",
        "nl": "Officiële Android-apps op Google Play",
        "it": "App Android ufficiali su Google Play",
        "pl": "Oficjalne aplikacje Android w Google Play",
        "sv": "Officiella Android-appar på Google Play",
        "nb": "Offisielle Android-apper på Google Play",
        "pt_BR": "Apps Android oficiais no Google Play",
    },
    "Terminals": {
        "de": "Terminals",
        "fr": "Terminaux",
        "es": "Terminales",
        "da": "Terminaler",
        "nl": "Tablets",
        "it": "Terminali",
        "pl": "Terminale",
        "sv": "Terminaler",
        "nb": "Terminaler",
        "pt_BR": "Terminais",
    },
    "Administrator": {
        "de": "Administrator",
        "fr": "Administrateur",
        "es": "Administrador",
        "da": "Administrator",
        "nl": "Beheerder",
        "it": "Amministratore",
        "pl": "Administrator",
        "sv": "Administratör",
        "nb": "Administrator",
        "pt_BR": "Administrador",
    },
    "Info": {
        "de": "Information",
        "fr": "Informations",
        "es": "Información",
        "da": "Oplysninger",
        "nl": "Informatie",
        "it": "Informazioni",
        "pl": "Informacja",
        "sv": "Information",
        "nb": "Informasjon",
        "pt_BR": "Informações",
    },
    "info": {
        "de": "Information",
        "fr": "informations",
        "es": "información",
        "da": "oplysninger",
        "nl": "informatie",
        "it": "informazioni",
        "pl": "informacja",
        "sv": "information",
        "nb": "informasjon",
        "pt_BR": "informações",
    },
    "Admin": {
        "de": "Verwaltung",
        "fr": "Administration",
        "es": "Administración",
        "da": "Administration",
        "nl": "Beheer",
        "it": "Amministrazione",
        "pl": "Administracja",
        "sv": "Administration",
        "nb": "Administrasjon",
        "pt_BR": "Administração",
    },
    "admin": {
        "de": "Administrator",
        "fr": "administrateur",
        "es": "administrador",
        "da": "administrator",
        "nl": "beheerder",
        "it": "amministratore",
        "pl": "administrator",
        "sv": "administratör",
        "nb": "administrator",
        "pt_BR": "administrador",
    },
    "administrator": {
        "de": "Administrator",
        "fr": "administrateur",
        "es": "administrador",
        "da": "administrator",
        "nl": "beheerder",
        "it": "amministratore",
        "pl": "administrator",
        "sv": "administratör",
        "nb": "administrator",
        "pt_BR": "administrador",
    },
    "Dashboard": {
        "de": "Übersicht",
        "da": "Oversigt",
        "nl": "Overzicht",
        "it": "Panoramica",
        "pl": "Pulpit",
        "sv": "Översikt",
        "nb": "Oversikt",
        "pt_BR": "Painel",
    },
    "On": {
        "de": "Ein",
        "fr": "Activé",
        "es": "Activado",
        "da": "Til",
        "nl": "Aan",
        "it": "Attivo",
        "pl": "Wł.",
        "sv": "På",
        "nb": "På",
        "pt_BR": "Ativado",
    },
    "Off": {
        "de": "Aus",
        "fr": "Désactivé",
        "es": "Desactivado",
        "da": "Fra",
        "nl": "Uit",
        "it": "Disattivo",
        "pl": "Wył.",
        "sv": "Av",
        "nb": "Av",
        "pt_BR": "Desativado",
    },
    "Compliance": {
        "de": "Konformität",
    },
    "h": {
        "de": "Std.",
        "da": "t.",
        "nl": "u",
        "pl": "godz.",
        "sv": "tim",
        "nb": "t",
        "pt_BR": "h",
    },
    "month_closure_pdf_hours_suffix": {
        "da": "t.",
        "sv": "tim",
        "nb": "t",
        "pt_BR": "h",
    },
    "hub": {
        "fr": "Nœud",
        "nl": "Knooppunt",
        "it": "Nodo",
    },
    "staging": {
        "nl": "Testomgeving",
        "it": "Preproduzione",
        "sv": "Testmiljö",
    },
    "AB": {
        "fr": "AB",
        "es": "AB",
        "it": "AB",
        "sv": "FA",
    },
    "status": {
        "de": "Status",
        "nl": "status",
        "pl": "stan",
        "sv": "status",
        "nb": "status",
        "pt_BR": "estado",
    },
    "month_closure_pdf_col_status": {
        "da": "Status",
        "nl": "Status",
        "sv": "Status",
        "nb": "Status",
        "pt_BR": "Estado",
    },
    "WT": {
        "nl": "AZ",
        "pl": "AZ",
        "sv": "AZ",
        "nb": "AZ",
        "da": "AZ",
        "it": "AZ",
    },
    "Team": {
        "pl": "Zespół",
        "it": "Team",
        "sv": "Team",
        "nb": "Team",
        "nl": "Team",
    },
    "online": {
        "da": "online",
        "nl": "online",
        "it": "in linea",
        "sv": "uppkopplad",
        "nb": "tilkoblet",
        "pt_BR": "online",
    },
    "offline": {
        "da": "offline",
        "nl": "offline",
        "it": "non in linea",
        "sv": "frånkopplad",
        "nb": "frakoblet",
        "pt_BR": "offline",
    },
    "Simulator": {
        "pl": "Symulator",
        "da": "Simulator",
        "nl": "Simulator",
        "sv": "Simulator",
        "nb": "Simulator",
    },
    "Status": {
        "da": "Status",
        "nb": "Status",
        "pl": "Stan",
        "pt_BR": "Estado",
    },
    "Details": {
        "nl": "Gegevens",
    },
}

C_SUITE: dict[str, dict[str, str]] = {
    "CEO": {
        "de": "GF",
        "fr": "PDG",
        "es": "DG",
        "da": "Adm.dir.",
        "nl": "CEO",
        "it": "AD",
        "pl": "Prezes",
        "sv": "VD",
        "nb": "Adm.dir.",
        "pt_BR": "CEO",
    },
    "CFO": {
        "de": "CFO",
        "fr": "DAF",
        "es": "DF",
        "da": "CFO",
        "nl": "FD",
        "it": "DF",
        "pl": "DF",
        "sv": "Ekonomichef",
        "nb": "Finansdirektør",
        "pt_BR": "Diretor Financeiro",
    },
    "CHRO": {
        "de": "Personal",
        "fr": "DRH",
        "es": "RR. HH.",
        "da": "HR",
        "nl": "HR",
        "it": "HR",
        "pl": "HR",
        "sv": "HR",
        "nb": "HR",
        "pt_BR": "RH",
    },
    "CIO": {
        "de": "IT",
        "fr": "DSI",
        "es": "DSI",
        "da": "IT",
        "nl": "IT",
        "it": "IT",
        "pl": "IT",
        "sv": "IT-chef",
        "nb": "IT-direktør",
        "pt_BR": "Diretor de TI",
    },
    "CMO": {
        "de": "Marketing",
        "fr": "DCM",
        "es": "DM",
        "da": "CMO",
        "nl": "Marketing",
        "it": "Marketing",
        "pl": "Marketing",
        "sv": "Marknadschef",
        "nb": "Markedsdirektør",
        "pt_BR": "Diretor de Marketing",
    },
    "COO": {
        "de": "COO",
        "fr": "DG",
        "es": "DO",
        "da": "COO",
        "nl": "COO",
        "it": "Operazioni",
        "pl": "COO",
        "sv": "Driftschef",
        "nb": "Driftsdirektør",
        "pt_BR": "COO",
    },
    "CP": {
        "pt_BR": "CP",
    },
    "CSO": {
        "de": "CSO",
        "fr": "RSE",
        "es": "DS",
        "da": "CSO",
        "nl": "CSO",
        "it": "Sicurezza",
        "pl": "CSO",
        "sv": "Säkerhetschef",
        "nb": "Sikkerhetssjef",
        "pt_BR": "OSC",
    },
    "CTO": {
        "de": "CTO",
        "fr": "DT",
        "es": "DT",
        "da": "CTO",
        "nl": "CTO",
        "it": "CTO",
        "pl": "CTO",
        "sv": "Teknikchef",
        "nb": "Teknologidirektør",
        "pt_BR": "Diretor de Tecnologia",
    },
}

OUTLOOK_PT_BR = {
    "Calendar": "Calendário",
    "Still busy — trying again…": "Ainda ocupado — tentando novamente…",
    "Vacation year mode cannot be changed right now. Wait a moment and try again.": "O modo de ano de férias não pode ser alterado agora. Aguarde um momento e tente novamente.",
    "Subscription scope": "Escopo da assinatura",
    "Search teams…": "Pesquisar equipes…",
    "Clear selected team": "Limpar equipe selecionada",
    "Matching teams": "Equipes correspondentes",
    "Manager scope": "Escopo do gerente",
    "Search managers by name or login…": "Pesquisar gerentes por nome ou login…",
    "Clear selected manager": "Limpar gerente selecionado",
    "Matching managers": "Gerentes correspondentes",
    "Choose which manager authorization the link should be bound to. If that person later loses access, the feed stops working automatically.": "Escolha a qual autorização de gerente o link deve estar vinculado. Se essa pessoa perder o acesso depois, o feed para de funcionar automaticamente.",
    "Use a compact date range. The maximum window is 365 days and feeds above 5000 approved absences are rejected.": "Use um intervalo de datas compacto. A janela máxima é de 365 dias e feeds com mais de 5000 ausências aprovadas são rejeitados.",
    "Generate feed URL": "Gerar URL do feed",
    "Revoke & rotate": "Revogar e renovar",
    "Approved absences in this feed: 0": "Ausências aprovadas neste feed: 0",
    "Security note: the URL is scoped to the selected team and manager, stores only a token hash on the server, and is invalidated immediately when you rotate it.": "Nota de segurança: a URL é limitada à equipe e ao gerente selecionados, armazena apenas um hash de token no servidor e é invalidada imediatamente quando você a renova.",
    "Choose a team first.": "Selecione uma equipe primeiro.",
    "Loading teams…": "Carregando equipes…",
    "No matching teams found.": "Nenhuma equipe correspondente encontrada.",
    "Type at least 2 characters to search managers for the selected team.": "Digite pelo menos 2 caracteres para pesquisar gerentes da equipe selecionada.",
    "No matching managers found for this team.": "Nenhum gerente correspondente encontrado para esta equipe.",
    "Loading managers…": "Carregando gerentes…",
    "Subscription link ready.": "Link de assinatura pronto.",
    "Subscription link copied.": "Link de assinatura copiado.",
    "Copy the subscription link manually.": "Copie o link de assinatura manualmente.",
    "Generating subscription link…": "Gerando link de assinatura…",
    "Calendar subscription": "Assinatura de calendário",
    "Generate privacy-safe calendar subscription links per team and manager scope.": "Gerar links de assinatura de calendário com privacidade por equipe e escopo de gerente.",
    "Calendar subscription (Per team, privacy-safe)": "Assinatura de calendário (por equipe, com privacidade)",
    "Generate one calendar subscription link per team and manager scope. The feed contains approved absences only and never includes free-text reasons.": "Gere um link de assinatura de calendário por equipe e escopo de gerente. O feed contém apenas ausências aprovadas e nunca inclui motivos em texto livre.",
    "Enable app-owned teams first. Calendar subscriptions are available only for app team scopes.": "Ative primeiro as equipes próprias do app. Assinaturas de calendário estão disponíveis apenas para escopos de equipe do app.",
    "Choose the team whose approved absences should appear in subscribed calendars. Child teams are included automatically.": "Escolha a equipe cujas ausências aprovadas devem aparecer nos calendários assinados. Equipes filhas são incluídas automaticamente.",
    "Tokenized calendar subscription URL": "URL de assinatura de calendário com token",
    "Copy this link into your calendar app and subscribe from URL (Thunderbird, Nextcloud Calendar, Outlook, and others). Keep it secret like a password.": "Copie este link no app de calendário e assine pela URL (Thunderbird, Calendário Nextcloud, Outlook e outros). Trate-o como uma senha.",
    "Rotate the subscription link now? Calendar apps will stop refreshing the old link immediately.": "Renovar o link de assinatura agora? Apps de calendário param de atualizar o link antigo imediatamente.",
    "Enable app-owned teams first. Calendar subscriptions are only available for app team scopes.": "Ative primeiro as equipes próprias do app. Assinaturas de calendário estão disponíveis apenas para escopos de equipe do app.",
    "Each refresh includes approved absences from the last 3 months through the next 12 months. The window moves forward automatically — subscribe once.": "Cada atualização inclui ausências aprovadas dos últimos 3 meses até os próximos 12 meses. A janela avança automaticamente — assine uma vez.",
    "Approved absences in the current window: 0": "Ausências aprovadas na janela atual: 0",
    "Approved absences in the current window: %d": "Ausências aprovadas na janela atual: %d",
    "Current window: %1$s through %2$s (last 3 months through next 12 months).": "Janela atual: %1$s a %2$s (últimos 3 meses até os próximos 12 meses).",
    "Pick a team and manager first.": "Selecione uma equipe e um gerente primeiro.",
    "Subscribe to approved team absences in Thunderbird, Nextcloud Calendar, Outlook, or other calendar apps. The feed refreshes automatically (last 3 months through next 12 months).": "Assine ausências aprovadas da equipe no Thunderbird, Calendário Nextcloud, Outlook ou outros apps de calendário. O feed atualiza automaticamente (últimos 3 meses até os próximos 12 meses).",
    "Requires app-owned teams. Configure links under Global settings → Calendar subscription.": "Requer equipes próprias do app. Configure links em Configurações globais → Assinatura de calendário.",
    "Open calendar subscription settings": "Abrir configurações de assinatura de calendário",
}


def load_json(path: Path) -> dict:
    with path.open(encoding="utf-8") as f:
        return json.load(f)


def save_json(lang: str, data: dict) -> None:
    with (L10N / f"{lang}.json").open("w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
        f.write("\n")


def build_fixes(lang: str) -> dict[str, str]:
    fixes: dict[str, str] = {}
    for msgid, per_lang in SHARED.items():
        if lang in per_lang:
            fixes[msgid] = per_lang[lang]
    for msgid, per_lang in C_SUITE.items():
        if lang in per_lang:
            fixes[msgid] = per_lang[lang]
    if lang == "pt_BR":
        fixes.update(OUTLOOK_PT_BR)
    # Merge existing quality fixes if present
    qf = L10N / f"_quality_fixes_{lang}.json"
    if qf.exists():
        fixes.update(load_json(qf))
    return fixes


def main() -> None:
    langs = ["de", "fr", "es", "da", "nl", "it", "pl", "sv", "nb", "pt_BR"]
    total = 0
    for lang in langs:
        data = load_json(L10N / f"{lang}.json")
        trans = data["translations"]
        fixes = build_fixes(lang)
        applied = 0
        for key, value in fixes.items():
            if key in trans and value:
                if trans[key] != value:
                    trans[key] = value
                    applied += 1
        data["translations"] = trans
        save_json(lang, data)
        print(f"{lang}: {applied} updates")
        total += applied
    subprocess.run(
        ["php", str(ROOT / "scripts/l10n/regenerate-l10n-js.php"), "--app=arbeitszeitcheck"],
        check=True,
        cwd=ROOT,
    )
    print(f"Total: {total}")


if __name__ == "__main__":
    main()
