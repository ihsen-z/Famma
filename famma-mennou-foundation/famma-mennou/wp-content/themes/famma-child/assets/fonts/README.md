# Polices auto-hébergées

Le thème attend six fichiers `woff2` dans ce dossier. Tant qu'ils ne sont
pas là, rien ne casse : `famma_child_fonts_available()` détecte leur absence,
`assets/css/fonts.css` n'est pas chargée, aucun `preload` n'est émis, et le
site rend avec la pile système déclarée en repli dans `--famma-font`.

| Fichier attendu | Famille | Graisse |
|---|---|---|
| `poppins-400.woff2` | Poppins | 400 |
| `poppins-500.woff2` | Poppins | 500 |
| `poppins-600.woff2` | Poppins | 600 |
| `poppins-700.woff2` | Poppins | 700 |
| `noto-kufi-arabic-400.woff2` | Noto Kufi Arabic | 400 |
| `noto-kufi-arabic-700.woff2` | Noto Kufi Arabic | 700 |

## Pourquoi pas Google Fonts en direct

Deux raisons, tranchées à l'arbitrage C-3 de `docs/DESIGN-INTEGRATION-PLAN.md` :

1. **Performance.** Un lien vers `fonts.googleapis.com` ajoute une résolution
   DNS, une connexion TLS et une feuille de style bloquante avant même que le
   premier `woff2` ne commence à descendre. Sur une 3G tunisienne, cela se voit.
2. **Vie privée.** Le navigateur du visiteur contacte alors un tiers, qui reçoit
   son adresse IP et l'URL de la page. Servir les fichiers depuis le domaine de
   la boutique supprime la question.

## Comment les obtenir

Les deux familles sont sous **SIL Open Font License 1.1**, qui autorise
l'auto-hébergement, y compris commercial. Sources officielles :

- Poppins — <https://fonts.google.com/specimen/Poppins> (bouton « Get font », puis extraire les `woff2`)
- Noto Kufi Arabic — <https://fonts.google.com/noto/specimen/Noto+Kufi+Arabic>

Les archives Google livrent du `ttf`. Convertir en `woff2` (par exemple avec
`woff2_compress` de `google/woff2`, ou `fonttools`), puis renommer selon le
tableau ci-dessus. Sous-régler les jeux de caractères réduit encore le poids :
latin + latin-ext pour Poppins, arabe pour Noto Kufi. Les plages
`unicode-range` déclarées dans `fonts.css` correspondent déjà à ce découpage.

Déposer aussi la licence (`OFL.txt`) à côté des fichiers : c'est une obligation
de la SIL OFL dès qu'on redistribue les polices, et un site public les
redistribue.
