"""FAMMA — plan de tests pixel du 19/10/2026, lot 4 (T4.7, T4.9).

Lit la configuration publique d'un pixel Meta téléchargée par
`lot4-prod-passive.sh` (connect.facebook.net/signals/config/<pixel>) et en
tire une empreinte JSON : plugins activés (opt-in), réglages servis par Meta,
listes d'événements restreints ou non vérifiés, mode de données protégées.

Le fichier est traité comme une donnée : il n'est jamais exécuté, seulement
lu ligne par ligne. Les très gros réglages (listes de robots, schémas de
microdonnées) sont résumés par leur taille et leur empreinte SHA-256.

Usage :  python -I scripts/qa/lot4-signals-config.py <config.js> <sortie.json>
"""

import hashlib
import json
import re
import sys

LARGE = 2000  # Au-delà, un réglage est résumé (taille + empreinte) et non recopié.
WATCHED = (
    "IABPCMAEBridge",
    "AutomaticMatching",
    "AutomaticMatchingAI",
    "MicrodataCoverage",
    "MicrodataFieldTransmission",
    "InferredEvents",
    "SmartSetup",
    "SmartSetupTotalPriceExtraction",
    "AutomaticParameters",
    "IWLBootstrapper",
    "ProtectedDataMode",
    "UnwantedData",
    "EventValidation",
)


def main(src: str, dst: str) -> int:
    raw = open(src, "rb").read()
    text = raw.decode("utf-8")

    # Le bloc propre au pixel commence à son registerPlugin("<id numérique>").
    pixel_match = re.search(r'fbq\.registerPlugin\("(\d+)", \{__fbEventsPlugin', text)
    pixel = pixel_match.group(1) if pixel_match else None
    block = text[pixel_match.start():] if pixel_match else text

    opt_ins = []
    for m in re.finditer(r'instance\.optIn\("(\d+)", "([A-Za-z0-9]+)", (true|false)\)', block):
        if m.group(2) not in [o["plugin"] for o in opt_ins]:
            opt_ins.append({"plugin": m.group(2), "value": m.group(3) == "true"})

    load_plugins = sorted(set(re.findall(r'fbq\.loadPlugin\("([a-z0-9]+)"\)', block)))
    bundled = sorted(set(re.findall(r'registerPlugin\("fbevents\.plugins\.([a-z0-9]+)"', text)))

    settings = {}
    # Pas d'ancre en début de ligne : le premier config.set suit le
    # registerPlugin sur la même ligne. Chaque appel finit sa ligne.
    for m in re.finditer(r'config\.set\((null|"\d+"), "([A-Za-z0-9]+)", (.*?)\);\s*$', block, re.M):
        key = m.group(2)
        body = m.group(3)
        scope = "global" if m.group(1) == "null" else "pixel"
        if len(body) > LARGE:
            settings[key] = {
                "scope": scope,
                "resume": True,
                "caracteres": len(body),
                "sha256": hashlib.sha256(body.encode("utf-8")).hexdigest(),
            }
            continue
        try:
            value = json.loads(body)
        except ValueError:
            value = {"brut": body}
        settings[key] = {"scope": scope, "valeur": value}

    opted = {o["plugin"] for o in opt_ins if o["value"]}
    validation = settings.get("eventValidation", {}).get("valeur", {})
    unwanted = settings.get("unwantedData", {}).get("valeur", {})
    gating = settings.get("gating", {}).get("valeur", {}).get("gatings", [])

    summary = {
        "fichier": src.replace("\\", "/").rsplit("/", 1)[-1],
        "octets": len(raw),
        "sha256": hashlib.sha256(raw).hexdigest(),
        "pixel": pixel,
        "opt_ins": opt_ins,
        "plugins_charges": load_plugins,
        "plugins_embarques": bundled,
        "surveilles": {name: (name in opted) for name in WATCHED},
        "restrictedEventNames": validation.get("restrictedEventNames"),
        "unverifiedEventNames": validation.get("unverifiedEventNames"),
        "ProtectedDataMode_opt_in": "ProtectedDataMode" in opted,
        "ProtectedDataMode_mentions_dans_le_code": text.count("ProtectedDataMode"),
        "unwantedData": unwanted,
        "automaticMatching_selectedMatchKeys": settings.get("automaticMatching", {})
        .get("valeur", {})
        .get("selectedMatchKeys"),
        "IABPCMAEBridge": settings.get("IABPCMAEBridge", {}).get("valeur"),
        "gating_passees": [g["name"] for g in gating if g.get("passed")],
        "gating_refusees": [g["name"] for g in gating if not g.get("passed")],
        "reglages": settings,
    }

    with open(dst, "w", encoding="utf-8") as out:
        json.dump(summary, out, ensure_ascii=False, indent=2)
        out.write("\n")
    print(f"{summary['pixel']} : {len(opt_ins)} opt-in, {len(settings)} reglages -> {dst}")
    return 0


if __name__ == "__main__":
    if len(sys.argv) != 3:
        print(__doc__)
        sys.exit(1)
    sys.exit(main(sys.argv[1], sys.argv[2]))
