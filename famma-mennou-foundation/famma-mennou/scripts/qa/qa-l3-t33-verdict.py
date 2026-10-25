"""Lot 3 du plan pixel (T3.3, H-S1) : verdict croisé fbclid -> _fbc -> méta -> CAPI.

Lit, en lecture seule, les preuves déjà écrites dans le dossier du lot 3 :
- preuve navigateur (cookie _fbc à l'arrivée et au moment de chaque commande) ;
- relevé WP-CLI des méta de commande (qa-l3-orders-check.php) après envoi ;
- requêtes CAPI interceptées par le clone (extrait de qa-http-log.jsonl).

Écrit t3-3-verdict.json. Aucune donnée client réelle : commandes QA-L3 du
clone uniquement, pixel factice, aucun jeton (contrôle de non-présence inclus).

Usage : python -I scripts/qa/qa-l3-t33-verdict.py [dossier_preuves]
"""

import json
import re
import sys
from pathlib import Path

EVIDENCE = Path(sys.argv[1] if len(sys.argv) > 1 else "E:/Ecom/audit-preuves/2026-10-19-pixel/lot-3")
FBC_RE = re.compile(r"^fb\.1\.\d{13}\.FAMMATEST123$")

# Chaque série : preuve navigateur, relevé après envoi, extrait CAPI, commandes.
SERIES = [
    {
        "serie": "reprise (19/10, même session pour les deux commandes)",
        "navigateur": "t3-3-fbc-navigateur-reprise.json",
        "meta": "t3-3-reprise-commandes-apres-envoi.txt",
        "capi": "t3-3-reprise-capi-interceptees.jsonl",
        "commandes": {"express": 171, "classique": 172},
    },
    {
        "serie": "première tentative (express et classique dans deux sessions)",
        "navigateur": {"express": "t3-3-fbc-navigateur.json", "classique": "t3-3-fbc-navigateur-classique.json"},
        "meta": "t3-3-commandes-apres-envoi.txt",
        "capi": "t3-3-capi-interceptees.jsonl",
        "commandes": {"express": 142, "classique": 143},
    },
]


def load_json(name):
    return json.loads((EVIDENCE / name).read_text(encoding="utf-8"))


def load_meta(name):
    text = (EVIDENCE / name).read_text(encoding="utf-8")
    start = text.index("{")
    return json.loads(text[start:])["commandes"]


def load_capi(name):
    events = {}
    raw = (EVIDENCE / name).read_text(encoding="utf-8")
    for line in raw.splitlines():
        if not line.strip():
            continue
        entry = json.loads(line)
        for event in (entry.get("body") or {}).get("data") or []:
            events[event.get("event_id")] = {
                "event_name": event.get("event_name"),
                "fbc": (event.get("user_data") or {}).get("fbc"),
                "fbp": (event.get("user_data") or {}).get("fbp"),
                "outcome": entry.get("outcome"),
                "url": entry.get("url"),
            }
    leak = ("access_token" in raw) or ("QA-FAKE-TOKEN" in raw)
    return events, leak


def browser_cookie(series, parcours):
    nav = series["navigateur"]
    proof = load_json(nav[parcours] if isinstance(nav, dict) else nav)
    return proof["cookieArrivee"]["_fbc"], (proof.get(parcours) or {}).get("cookieFbcAuMomentDeLaCommande")


def main():
    report = {"test": "T3.3", "hypothese": "H-S1", "series": []}
    all_ok = True
    for series in SERIES:
        meta = load_meta(series["meta"])
        capi, leak = load_capi(series["capi"])
        rows = []
        for parcours, order_id in series["commandes"].items():
            arrival, at_order = browser_cookie(series, parcours)
            order_meta = meta.get(str(order_id), {})
            event = capi.get("famma_orderplaced_%d" % order_id, {})
            row = {
                "parcours": parcours,
                "commande": order_id,
                "cookie_fbc_arrivee": arrival,
                "cookie_fbc_au_moment_de_la_commande": at_order,
                "meta_famma_fbc": order_meta.get("_famma_fbc"),
                "capi_event": event.get("event_name"),
                "capi_fbc": event.get("fbc"),
                "capi_outcome": event.get("outcome"),
                "format_cookie_ok": bool(arrival and FBC_RE.match(arrival)),
                "meta_egale_cookie": order_meta.get("_famma_fbc") == at_order,
                "capi_egale_meta": event.get("fbc") == order_meta.get("_famma_fbc"),
                "prefixe_test": order_meta.get("prefixe_test"),
            }
            row["ok"] = row["format_cookie_ok"] and row["meta_egale_cookie"] and row["capi_egale_meta"] and "QA-L3" == row["prefixe_test"]
            all_ok = all_ok and row["ok"]
            rows.append(row)
        report["series"].append({"serie": series["serie"], "jeton_dans_extrait_capi": leak, "commandes": rows})
        all_ok = all_ok and not leak
    report["verdict"] = "réussi" if all_ok else "échoué"
    out = EVIDENCE / "t3-3-verdict.json"
    out.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(report, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main()
