"""Lot 3 du plan pixel (T3.5, H-N6) : synthèse des essais réseau lent / départ rapide.

Lit, en lecture seule, les preuves t3-5-<condition>[-mesure].json écrites par
scripts/qa/browser/t3-5-slow.js et écrit t3-5-synthese.json :
- part d'événements perdus par condition, modèle et scénario (5 essais) ;
- chronologie des essais témoins (sans départ) : affichage (DOMContentLoaded),
  « load », demande de fbevents.js, premier événement parti, et la fenêtre
  « affichage -> premier envoi » pendant laquelle un départ perd tout.

Usage : python -I scripts/qa/qa-l3-t35-synthese.py [dossier_preuves]
"""

import json
import sys
from pathlib import Path

EVIDENCE = Path(sys.argv[1] if len(sys.argv) > 1 else "E:/Ecom/audit-preuves/2026-10-19-pixel/lot-3")
FILES = [
    ("aucun", "190k", "t3-5-aucun.json"),
    ("fast3g", "190k", "t3-5-fast3g.json"),
    ("slow3g", "190k", "t3-5-slow3g.json"),
    ("aucun", "mesure", "t3-5-aucun-mesure.json"),
    ("fast3g", "mesure", "t3-5-fast3g-mesure.json"),
    ("slow3g", "mesure", "t3-5-slow3g-mesure.json"),
]


def timeline(trial):
    snap = trial.get("instantane") or {}
    sent = trial.get("envoyes") or []
    lib = [b for b in trial.get("bibliotheque") or [] if b.get("ressource", "fbevents.js") == "fbevents.js"]
    first = min((e["envoyeMs"] for e in sent), default=None)
    dcl = snap.get("dclMs")
    return {
        "affichage_dcl_ms": dcl,
        "load_ms": snap.get("loadMs"),
        "fbevents_demande_ms": lib[0]["demandeeMs"] if lib else None,
        "premier_envoi_ms": first,
        "fenetre_affichage_vers_envoi_ms": (first - dcl) if (first is not None and dcl) else None,
        "reference": "début de la navigation (ajout : clic sur Ajouter au panier)",
    }


def main():
    rows = []
    for cond, model, name in FILES:
        path = EVIDENCE / name
        if not path.exists():
            rows.append({"condition": cond, "modele": model, "fichier": name, "absent": True})
            continue
        data = json.loads(path.read_text(encoding="utf-8"))
        witnesses = {t["scenario"]: timeline(t) for t in data["essais"] if isinstance(t.get("essai"), str)}
        rows.append(
            {
                "condition": data["condition"],
                "modele": data.get("modele", "1 requête de 190 Kio (commentaire PERF-07 de famma-core)"),
                "fichier": name,
                "delai_bibliotheque_ms": data.get("delaiBibliothequeSimuleMs"),
                "delai_config_ms": data.get("delaiConfigSimuleMs", 0),
                "depart_ms_apres_affichage": data.get("departMsApresAffichage"),
                "resultats": data.get("synthese"),
                "temoins": witnesses,
                "erreurs": sum(1 for t in data["essais"] if t.get("erreur")),
                "requetes_hors_clone": sum(len(t.get("horsClone") or []) for t in data["essais"]),
            }
        )
    out = {"test": "T3.5", "hypothese": "H-N6", "series": rows}
    target = EVIDENCE / "t3-5-synthese.json"
    target.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    for r in rows:
        if r.get("absent"):
            print(r["fichier"], "absent")
            continue
        res = r["resultats"] or {}
        print(
            r["fichier"],
            "| fiche perdus", res.get("fiche", {}).get("partPerdue"),
            "| ajout perdus", res.get("ajout", {}).get("partPerdue"),
            "| fenêtre fiche", r["temoins"].get("fiche", {}).get("fenetre_affichage_vers_envoi_ms"),
            "| fenêtre ajout", r["temoins"].get("ajout", {}).get("fenetre_affichage_vers_envoi_ms"),
        )


if __name__ == "__main__":
    main()
