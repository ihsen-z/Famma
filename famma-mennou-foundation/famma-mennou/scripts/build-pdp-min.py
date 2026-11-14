"""Copies allégées des scripts de la fiche produit (thème famma-child).

Les sources commentées restent dans `assets/js/` ; le thème sert les copies
de `assets/js/min/`, sauf avec `SCRIPT_DEBUG` (comme les `.min.js` de
WordPress). Les commentaires pèsent environ un tiers des octets compressés :
sans eux, les quatre scripts de la fiche tiennent dans le budget de +8 Ko.

Allègement volontairement prudent, ligne par ligne, sans analyse du JS :
- bloc `/* … */` qui commence une ligne et finit en fin de ligne : retiré ;
- ligne qui n'est qu'un commentaire `// …` : retirée ;
- indentation et lignes vides : retirées.
Le code lui-même n'est jamais réécrit (un `//` ou `/*` à l'intérieur d'une
ligne de code reste tel quel). Refuse un fichier contenant un gabarit
`` ` `` hors commentaire : une ligne de gabarit pourrait être modifiée.

Usage (depuis famma-mennou/) :
    python -I scripts/build-pdp-min.py           # régénère les copies
    python -I scripts/build-pdp-min.py --check   # échoue si une copie est périmée
"""

import re
import sys
from pathlib import Path

JS = Path(__file__).resolve().parent.parent / "wp-content/themes/famma-child/assets/js"
NAMES = ("new-look-pdp", "new-look-pdp-sections", "new-look-pdp-story", "new-look-pdp-buy")
# Un seul bloc : jamais au-delà de son propre `*/` (sinon du code entre deux blocs serait avalé).
BLOCK = re.compile(r"^[ \t]*/\*(?:(?!\*/).)*\*/[ \t]*\n", re.M | re.S)
LINE = re.compile(r"^[ \t]*//.*\n", re.M)


def light(name: str, src: str) -> str:
    code = LINE.sub("", BLOCK.sub("", src))
    if "`" in code:
        raise SystemExit(f"{name}.js : gabarit ` hors commentaire, allègement refusé")
    lines = [line.strip() for line in code.split("\n")]
    head = f"/* famma-child : copie allégée de assets/js/{name}.js (scripts/build-pdp-min.py), ne pas modifier. */"
    return head + "\n" + "\n".join(line for line in lines if line) + "\n"


def main() -> int:
    check = "--check" in sys.argv[1:]
    stale = []
    (JS / "min").mkdir(exist_ok=True)
    for name in NAMES:
        src = (JS / f"{name}.js").read_text(encoding="utf-8")
        out = light(name, src)
        dest = JS / "min" / f"{name}.js"
        if check:
            if not dest.exists() or dest.read_text(encoding="utf-8") != out:
                stale.append(dest.name)
        else:
            dest.write_text(out, encoding="utf-8", newline="\n")
    if stale:
        print("Copies périmées : " + ", ".join(stale) + " (lancer scripts/build-pdp-min.py)")
        return 1
    print("Copies à jour" if check else "Copies régénérées")
    return 0


if __name__ == "__main__":
    sys.exit(main())
