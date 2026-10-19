"""Clone QA pixel, lot 2 : comparaison passe 1 (HPOS activé) / passe 2 (HPOS désactivé).

Usage : python -I scripts/qa/qa-l2-compare-passes.py <dossier passe-1> <dossier passe-2>

Compare, fichier par fichier, ce qui existe dans les deux dossiers :
  - T2.1 (t2.1-*.txt) : code de sortie, contrôles nommés (JSON « name »/« passed »
    à toute profondeur, ou lignes « PASS|FAIL <nom> »), erreurs fatales ;
  - T2.2 à T2.13 (JSON de test-qa-pixel-integration.php) : statut de chaque
    contrôle et observation normalisée (numéros de commande et de tâche,
    dates, horodatages et event_id remplacés par des jetons neutres) ;
  - T2.12 (tableau du scan) : code HTTP et occurrences par page, numéros de
    commande neutralisés ; écrans HPOS et classiques (édition d'une commande,
    liste des commandes) rapprochés sous un même libellé, puisque l'écran
    actif dépend du stockage.
N'affiche que des comptes et des noms de contrôles, plus les observations
des contrôles qui diffèrent (elles ne contiennent que des données QA).
"""
import json
import os
import re
import sys

sys.stdout.reconfigure(encoding="utf-8")


def first_json(text):
    """Premier objet JSON qui commence en début de ligne, ou None."""
    for m in re.finditer(r"(?m)^\{", text):
        try:
            obj, _ = json.JSONDecoder().raw_decode(text[m.start():])
            return obj
        except json.JSONDecodeError:
            continue
    return None


def named_checks(node, out):
    """Collecte (nom, réussi) dans tout dict portant « name » et un booléen de réussite."""
    if isinstance(node, dict):
        if "name" in node:
            for key in ("passed", "ok", "pass", "success"):
                if isinstance(node.get(key), bool):
                    out.append((str(node["name"]), node[key]))
                    break
        for value in node.values():
            named_checks(value, out)
    elif isinstance(node, list):
        for value in node:
            named_checks(value, out)
    return out


def suite_file(path):
    text = open(path, encoding="utf-8", errors="replace").read()
    rc = re.findall(r"code_sortie(?:_suite)?=(\d+)", text)
    data = first_json(text)
    checks = named_checks(data, []) if data is not None else []
    checks += [(m.group(2), m.group(1) == "PASS") for m in re.finditer(r"(?m)^(PASS|FAIL) (\S+)", text)]
    return {
        "codes": rc,
        "checks": checks,
        "fatal": len(re.findall(r"Fatal error", text)),
    }


NORMALIZE = [
    (re.compile(r"famma_(purchase|orderplaced)_\d+"), r"famma_\1_<id>"),
    (re.compile(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\+00:00|Z)?"), "<date>"),
    (re.compile(r"\b1[78]\d{8}\b"), "<ts>"),
    (re.compile(r"\"(\d+)\":"), '"<n>":'),
    (re.compile(r"\"(commande|order|order_id|id|post|produit|product_id)\": \d+"), r'"\1": <id>'),
    (re.compile(r"order=\d+"), "order=<id>"),
    (re.compile(r"#\d+"), "#<id>"),
    (re.compile(r"\"commandes\": \[[0-9, ]*\]"), '"commandes": [<ids>]'),
    (re.compile(r"QA-L2 \d{8}"), "QA-L2 <tel>"),
    (re.compile(r"qa-l2-\d{8}"), "qa-l2-<tel>"),
]


def normalize(value):
    text = json.dumps(value, ensure_ascii=False, sort_keys=True)
    for rx, repl in NORMALIZE:
        text = rx.sub(repl, text)
    return text


def integration_checks(path):
    text = open(path, encoding="utf-8", errors="replace").read()
    data = first_json(text)
    out = {}
    if not data or not isinstance(data.get("scenarios"), dict):
        return out, data
    for scenario, value in data["scenarios"].items():
        for i, check in enumerate(value.get("checks", [])):
            name = re.sub(r"\d{8}", "<tel>", check["name"])
            out[f"{scenario} {name} #{i}"] = (check["passed"], normalize(check.get("observe")), check.get("observe"))
    return out, data


def scan_rows(path):
    rows = {}
    for line in open(path, encoding="utf-8", errors="replace"):
        parts = line.rstrip("\n").split("\t")
        if len(parts) != 6:
            continue
        ctx, code, _size, tok, acc, url = parts
        url = re.sub(r"(post=|id=|orders/)\d+", r"\1<id>", url)
        url = url.replace("/wp-admin/post.php?post=<id>&action=edit", "<ecran-edition-commande>")
        url = url.replace("/wp-admin/admin.php?page=wc-orders&action=edit&id=<id>", "<ecran-edition-commande>")
        # Liste des commandes : l'écran actif répond 200, l'autre redirige (301) selon le stockage.
        url = url.replace("/wp-admin/edit.php?post_type=shop_order", "<liste-commandes>")
        url = url.replace("/wp-admin/admin.php?page=wc-orders", "<liste-commandes>")
        key = (ctx, url)
        rows.setdefault(key, []).append((code, tok, acc))
    return rows


def main(p1, p2):
    print(f"# Comparaison lot 2 : passe 1 = {p1} ; passe 2 = {p2}")
    diffs = 0

    print("\n## T2.1 — suites existantes (fichiers t2.1-*.txt présents dans les deux passes)")
    pairs = []
    for f in sorted(os.listdir(p2)):
        if not (f.startswith("t2.1-") and f.endswith(".txt")):
            continue
        if os.path.exists(os.path.join(p1, f)):
            pairs.append((f, f))
        elif "-relance-" in f and os.path.exists(os.path.join(p1, f.split("-relance-")[0] + ".txt")):
            # Relance propre à la passe 2 : comparée à la preuve d'origine de la passe 1.
            pairs.append((f.split("-relance-")[0] + ".txt", f))
    for f1, f in pairs:
        a, b = suite_file(os.path.join(p1, f1)), suite_file(os.path.join(p2, f))
        if f1 != f:
            f = f"{f} (passe 1 : {f1})"
        if not a["codes"] and not b["codes"] and not a["checks"] and not b["checks"]:
            continue
        ca, cb = dict(a["checks"]), dict(b["checks"])
        fa = sorted(n for n, ok in a["checks"] if not ok)
        fb = sorted(n for n, ok in b["checks"] if not ok)
        only_a = sorted(set(ca) - set(cb))
        only_b = sorted(set(cb) - set(ca))
        changed = sorted(n for n in set(ca) & set(cb) if ca[n] != cb[n])
        same = a["codes"] == b["codes"] and not only_a and not only_b and not changed and a["fatal"] == b["fatal"]
        diffs += 0 if same else 1
        print(f"{'IDEM ' if same else 'DIFF '} {f}: codes {a['codes']} / {b['codes']} ; controles {len(a['checks'])} / {len(b['checks'])} ; echecs {len(fa)} / {len(fb)} ; fatal {a['fatal']} / {b['fatal']}")
        for label, items in (("seulement passe 1", only_a), ("seulement passe 2", only_b), ("statut change", changed)):
            if items:
                print(f"      {label} : {items[:20]}")
        if fb:
            print(f"      echecs passe 2 : {fb[:20]}")

    print("\n## T2.2 à T2.13 — JSON de test-qa-pixel-integration.php")
    for f in sorted(f for f in os.listdir(p2) if f.endswith(".json") and os.path.exists(os.path.join(p1, f))):
        a, da = integration_checks(os.path.join(p1, f))
        b, db = integration_checks(os.path.join(p2, f))
        if not a and not b:
            continue
        hpos = ((da or {}).get("environment", {}).get("hpos"), (db or {}).get("environment", {}).get("hpos"))
        print(f"### {f} : controles {len(a)} / {len(b)} ; ecarts {sum(1 for v in a.values() if not v[0])} / {sum(1 for v in b.values() if not v[0])} ; hpos {hpos[0]} / {hpos[1]}")
        for key in sorted(set(a) | set(b)):
            va, vb = a.get(key), b.get(key)
            if va is None or vb is None:
                diffs += 1
                print(f"  DIFF {key} : {'absent passe 1' if va is None else 'absent passe 2'}")
                continue
            if va[0] != vb[0]:
                diffs += 1
                print(f"  DIFF statut {key} : {va[0]} -> {vb[0]}")
                print(f"       passe 1 : {json.dumps(va[2], ensure_ascii=False)[:500]}")
                print(f"       passe 2 : {json.dumps(vb[2], ensure_ascii=False)[:500]}")
            elif va[1] != vb[1]:
                diffs += 1
                print(f"  DIFF observation {key} (statut {va[0]} inchange)")
                print(f"       passe 1 : {va[1][:500]}")
                print(f"       passe 2 : {vb[1][:500]}")

    scan1, scan2 = os.path.join(p1, "t2.12-scan-jeton.txt"), os.path.join(p2, "t2.12-scan-jeton.txt")
    if os.path.exists(scan1) and os.path.exists(scan2):
        print("\n## T2.12 — scan HTML/JS (code HTTP, occurrences jeton, occurrences access_token)")
        r1, r2 = scan_rows(scan1), scan_rows(scan2)
        same_rows = 0
        for key in sorted(set(r1) | set(r2)):
            v1, v2 = sorted(r1.get(key, [])), sorted(r2.get(key, []))
            if v1 == v2:
                same_rows += 1
                continue
            diffs += 1
            print(f"  DIFF {key[0]} {key[1]} : {v1} / {v2}")
        print(f"  lignes identiques : {same_rows}")

    print(f"\n# total differences relevees : {diffs}")


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
