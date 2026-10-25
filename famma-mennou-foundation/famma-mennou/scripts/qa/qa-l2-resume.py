"""Clone QA pixel, lot 2 : résumé lisible d'une sortie JSON de test-qa-pixel-integration.php.

Usage : python -I scripts/qa/qa-l2-resume.py <sortie.json>
Une ligne par contrôle : scénario, OK ou ECART, nom, observation (tronquée).
Le texte avant le premier « {» en début de ligne (avis PHP éventuels) est ignoré.
"""
import json
import sys

sys.stdout.reconfigure(encoding="utf-8")
text = open(sys.argv[1], encoding="utf-8").read()
data = json.loads(text[text.index("{\n"):])
for scenario, value in data["scenarios"].items():
    for check in value.get("checks", []):
        observed = json.dumps(check["observe"], ensure_ascii=False)
        status = "OK   " if check["passed"] else "ECART"
        print(f"{scenario:6} {status} {check['name']}: {observed[:400]}")
print("total", data["checks_total"], "ecarts", data["checks_false"], "nettoyage", data["cleanup"])
