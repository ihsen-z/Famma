# Polices auto-hébergées

Les fichiers sont présents et servis depuis ce dossier. `fonts.css` est donc
chargée et `famma_child_preload_fonts()` émet les `preload`.

| Fichier | Famille | Graisse | Sous-jeu | Poids |
|---|---|---|---|---|
| `poppins-400.woff2` | Poppins | 400 | latin | 7,7 Ko |
| `poppins-500.woff2` | Poppins | 500 | latin | 7,6 Ko |
| `poppins-600.woff2` | Poppins | 600 | latin | 7,8 Ko |
| `poppins-700.woff2` | Poppins | 700 | latin | 7,6 Ko |
| `noto-kufi-arabic-var.woff2` | Noto Kufi Arabic | 400 → 700 | arabe | 121 Ko |

Total : **152 Ko** pour les deux écritures, dont 31 Ko seulement sur une page
française — l'arabe n'est téléchargé que si un caractère arabe est rendu, grâce
aux `unicode-range` de `fonts.css`.

## Un seul fichier pour l'arabe

Google distribue Noto Kufi Arabic en police **variable** (axe `wght`, 100 à
900). Deux `@font-face` pour 400 et 700 auraient pointé deux noms de fichier
vers des octets identiques, soit 121 Ko téléchargés deux fois. La déclaration
unique porte donc `font-weight: 400 700` et le navigateur interpole.

Poppins, à l'inverse, est distribuée en instances **statiques** : un fichier
par graisse est nécessaire, et chacun ne pèse que 7,7 Ko après sous-réglage
latin.

## Sous-jeux retenus

- **Poppins → `latin`.** Couvre U+0000–00FF, donc tous les accents français
  (é è à ç ù û ô î ï), plus €, les guillemets et les espaces typographiques.
  `latin-ext` (langues d'Europe centrale) et `devanagari` sont écartés : la
  boutique ne les affiche pas.
- **Noto Kufi Arabic → `arabic`.** Couvre l'arabe, les formes de présentation
  et les marques de direction nécessaires au RTL.

Les `unicode-range` de `fonts.css` sont exactement celles de ces sous-jeux :
elles ne sont pas approximatives, elles viennent de la même source.

## Pourquoi pas Google Fonts en direct

Deux raisons, tranchées à l'arbitrage C-3 de `docs/DESIGN-INTEGRATION-PLAN.md` :

1. **Performance.** Un lien vers `fonts.googleapis.com` ajoute une résolution
   DNS, une connexion TLS et une feuille de style bloquante avant même que le
   premier `woff2` ne commence à descendre. Sur une 3G tunisienne, cela se voit.
2. **Vie privée.** Le navigateur du visiteur contacte alors un tiers, qui reçoit
   son adresse IP et l'URL de la page. Servir les fichiers depuis le domaine de
   la boutique supprime la question.

## Licence

Les deux familles sont sous **SIL Open Font License 1.1**, qui autorise
l'auto-hébergement y compris commercial, à condition de redistribuer la
licence. Elle est donc ici :

- `OFL-Poppins.txt` — Copyright 2020 The Poppins Project Authors
- `OFL-NotoKufiArabic.txt` — Copyright 2022 The Noto Project Authors

Ne pas supprimer ces deux fichiers : un site public **redistribue** les
polices, l'obligation s'applique.

## Rafraîchir les fichiers

Les sous-jeux viennent de l'API Google Fonts, qui renvoie un `@font-face` par
couple (graisse, sous-jeu) avec l'URL du `woff2` correspondant :

```
https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap
https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;700&display=swap
```

Interroger avec un `User-Agent` de navigateur récent (sinon l'API renvoie du
`woff`, pas du `woff2`), puis récupérer les URL des blocs `/* latin */` et
`/* arabic */` et renommer selon le tableau ci-dessus. Vérifier que chaque
fichier commence bien par la signature `wOF2`.
