# Design system (§6, §49)

Premium · modern · Tunisian · mobile-first. Not AliExpress-generic. Orange is an
accent, never the dominant colour.

## Jetons

**Source unique : `famma-child/assets/css/tokens.css`.** Ce document explique les
choix ; le fichier fait foi. Aucune valeur littérale ne doit apparaître ailleurs.

| Groupe | Jetons |
|---|---|
| Marque | `--famma-blue` (+`-600`), `--famma-orange` (+`-700`), `--famma-accent-text`, `--famma-white` |
| Neutres | `--famma-ink`, `--famma-muted`, `--famma-line`, `--famma-bg` |
| Retour | `--famma-success` (+`-700`), `--famma-warning` (+`-700`, `-bg`), `--famma-danger` (+`-700`) |
| Typo | `--famma-font`, `--fs-caption` → `--fs-h1`, `--fs-price`, `--fs-price-old`, `--fs-badge`, `--fs-button`, `--fs-input`, `--fs-brand-*`, `--lh-*`, `--fw-*` |
| Espacement | `--sp-1` … `--sp-12` |
| Rayons | `--famma-radius-sm`, `--famma-radius`, `--famma-radius-lg`, `--famma-radius-pill` |
| Élévation | `--elev-1`, `--elev-2`, `--elev-3`, `--elev-up`, alias `--famma-shadow` |
| Mouvement | `--dur-fast`, `--dur-base`, `--ease-out` |
| Profondeur | `--z-float`, `--z-sticky-cta`, `--z-header`, `--z-drawer`, `--z-modal`, `--z-toast` |
| Gabarit | `--container-max`, `--gutter`, `--sticky-cta-h` |
| Focus | `--focus-ring-color`, `--focus-ring-width`, `--focus-halo` |

### Surface ≠ texte

L'orange de marque est une couleur de **remplissage**. En texte sur fond clair il
tombe à 2,61:1 — sous le seuil AA, y compris pour du grand texte. Chaque couleur de
retour existe donc en deux nuances : la nue pour les surfaces, la `-700` pour le
texte. Contrastes calculés (WCAG 2.1) :

| Couple | Ratio | Usage |
|---|---|---|
| `#0B1F3A` sur blanc | 16,5:1 | texte courant, titres |
| `#0B1F3A` sur `#FF7A00` | **6,31:1** | **texte du CTA principal** |
| blanc sur `#FF7A00` | 2,61:1 | ❌ interdit |
| `#FF7A00` texte sur blanc | 2,61:1 | ❌ interdit — utiliser `-700` |
| `#B85300` sur blanc | 4,91:1 | accent textuel sur fond clair |
| `#B85300` sur `#0B1F3A` | 3,36:1 | ❌ sur fond sombre — reprendre `#FF7A00` |
| `#5B6470` sur blanc | 6,0:1 | texte secondaire |
| blanc sur `#1FA65A` | 3,16:1 | ❌ pour du texte — surface seulement |
| blanc sur `#157A45` | 5,39:1 | CTA WhatsApp |
| `#D64545` sur blanc | 4,38:1 | ❌ juste sous le seuil — utiliser `#C13333` (5,54:1) |
| `#E6A700` sur blanc | 2,12:1 | ❌ bordure/surface uniquement |

Aucune des deux nuances d'orange n'est lisible partout : `--famma-accent-text` est
donc **contextuel**. Il vaut `-700` par défaut et bascule sur l'orange vif dans les
conteneurs sombres (`.famma-hero`, `.famma-legal`, `.famma-on-dark`). Un composant
posé sur fond sombre hérite du bon ton sans surcharge de couleur.

### Correspondance avec la palette de Kadence

Kadence dérive ses liens, ses boutons et ses fonds de quinze variables
`--global-palette1..15`. Elles portaient ses valeurs par défaut (bleu `#2B6CB0`) :
tout ce que le thème parent stylait restait hors marque. `style.css` les redirige
donc vers les jetons FAMMA.

| Kadence | Rôle | FAMMA |
|---|---|---|
| 1 | accent — liens, fond des boutons | `--famma-blue` |
| 2 | accent survolé | `--famma-blue-600` |
| 3 · 4 | texte fort | `--famma-ink` · `--famma-blue` |
| 5 · 6 | texte moyen, texte discret | `--famma-muted` |
| 7 · 8 | fond discret, fond de page | `--famma-line` · `--famma-bg` |
| 9 | blanc | `--famma-white` |
| 10 | complément d'accent | `--famma-orange` |
| 11 → 15 | succès, info, alerte, avertissement, notation | `-700` correspondants |

**L'emplacement 1 vaut le bleu, jamais l'orange** : Kadence en tire la couleur des
liens et le fond des boutons ; de l'orange y placerait du texte à 2,61:1 sur blanc
dans tout le site.

Conséquence à connaître : depuis ce basculement, le bouton d'achat doit être
récupéré par un sélecteur composé. Kadence le stylise via
`.woocommerce button.button.alt` (0,3,1) ; sans surenchère de spécificité le CTA
repasse en bleu — lisible, mais ce n'est plus l'orange de la marque sur le bouton
qui fait la vente.

### Focus

Anneau **bleu**, jamais orange : un anneau orange sur un CTA orange n'existe pas.
Le halo blanc (`--focus-halo`) le détache aussi bien d'un fond clair que du héros
sombre ou d'une image. Le bouton WhatsApp de `famma-core` applique la même règle,
avec des valeurs de repli en dur pour rester autonome sans le thème enfant.

## Logo et identité (§6, §7)

Source : projet Claude Design **« Logo Famma e-commerce »**, planches **2a**
(lockup horizontal) et **2b** (badge circulaire).

| Fichier | Rôle | Nature |
|---|---|---|
| `assets/img/famma-mark.svg` | icône seule, couleur | vectoriel pur, aucun texte |
| `assets/img/famma-mark-mono-dark.svg` | icône sur fond sombre | vectoriel pur |
| `assets/img/famma-favicon.svg` | onglet, écran d'accueil | version simplifiée, tient à 16 px |
| `assets/img/famma-badge.svg` | sceau 2b — photo de profil réseaux, sticker colis | contient du texte |

**Lockup d'en-tête** : rendu en HTML par `famma_child_lockup_markup()`, pas
servi comme fichier. L'icône est du SVG en ligne, le mot FAMMA et la baseline
arabe sont du texte réel. Un logo gravé dans un SVG dépendrait de polices non
embarquées ; ici la typo est celle du site, nette à toute taille, et le titre
du site reste lisible par les lecteurs d'écran.

Le tracé de l'icône utilise `currentColor` : elle passe en blanc sur fond
sombre sans second fichier. Seuls l'antenne, les étincelles et le point du A
restent orange — l'accent, jamais la couleur dominante (§6).

**Si le propriétaire téléverse un logo** dans le personnalisateur, il gagne
toujours : le lockup n'est qu'un repli (§7 — ne pas modifier le logo sans
autorisation).

### Limite connue — image de partage social
`og:image` n'est **pas** défini. Facebook, WhatsApp et Instagram refusent le
SVG, et aucune rastérisation n'est possible ici : l'extension Imagick du
conteneur n'a pas de délégué SVG. Il faut un PNG 1200×630 exporté depuis
l'outil de design avant d'activer les balises Open Graph (§16).

## CTA principal
Fond `--famma-orange`, texte `--famma-blue` (voir « Surface ≠ texte »),
`--famma-radius`, cible tactile 48×48 px minimum. Libellé : FR **« Commander »** /
Derja **« أطلب توا »**. Sur la fiche produit et le checkout, barre collante en bas
d'écran sur mobile (§13, §30).

La barre collante réserve sa hauteur (`--sticky-cta-h`) en bas de `body` : sans
cette réserve elle recouvre la fin de la page. La réserve reste posée même quand la
barre est rangée, pour ne pas provoquer de saut de mise en page.

## Typographie bilingue

Le couple FR/AR définitif n'est **pas tranché** (décision D-9, voir `UX-PLAN.md`).
Tant qu'aucun `woff2` n'est déposé, la pile système s'applique et le rendu arabe
dépend de l'appareil — c'est une dette identifiée, pas un choix.

Règles valables quel que soit le couple retenu :

| Règle | Raison |
|---|---|
| `letter-spacing: 0` sur tout l'arabe | l'interlettrage brise les ligatures |
| jamais `text-transform: uppercase` sur de l'arabe | sans effet — signale un composant non testé |
| `line-height: var(--lh-ar)` (1.7) pour le corps arabe | les diacritiques ont besoin d'air |
| chiffres occidentaux (0-9) pour tous les prix | cohérence des montants dans les deux langues |
| `lang` + `dir` sur chaque fragment de langue mixte | lecteurs d'écran et rendu correct |
| `--fs-input` jamais sous 16 px | en dessous, Safari iOS zoome au focus et casse le checkout |

## Trust chips (reused across pages) — §48
- 🚚 FR "Livraison en Tunisie" / Derja "التوصيل للدار"
- 💵 FR "Paiement à la livraison" / Derja "الدفع عند التوصيل"
- 💬 WhatsApp support

## RTL
Arabic renders RTL; keep layout logical-property based (`margin-inline`,
`padding-inline`, `inset-inline`) so LTR/RTL share one stylesheet. Test both.

## Empty-catalog homepage (§9, §58)
No fake products. Show a controlled "nouveautés / استنّاو الجديد" state:
- Hero with logo + slogan **فمّا ديما حاجة جديدة**
- Brand promise, COD + delivery trust sections, FAQ, WhatsApp CTA
FR fallback: *"Il y a toujours quelque chose de nouveau."*

## Breakpoints to test (§10)
320 · 360 · 375 · 390 · 414 · 768 · 1024 · 1440+
