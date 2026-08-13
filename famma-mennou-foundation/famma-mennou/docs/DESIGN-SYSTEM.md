# Design system (§6, §49)

Premium · modern · Tunisian · mobile-first. Not AliExpress-generic. Orange is an
accent, never the dominant colour.

## Design tokens (CSS variables)
```css
:root{
  /* Brand */
  --famma-blue:      #0B1F3A;  /* primary — dark tech blue */
  --famma-blue-600:  #12315C;
  --famma-orange:    #FF7A00;  /* accent only — CTAs, highlights */
  --famma-white:     #FFFFFF;

  /* Neutrals */
  --famma-ink:       #12161C;
  --famma-muted:     #5B6470;
  --famma-line:      #E6E9EE;
  --famma-bg:        #F7F8FA;

  /* Feedback */
  --famma-success:   #1FA65A;  /* COD / delivered */
  --famma-warning:   #E6A700;
  --famma-danger:    #D64545;

  /* Type */
  --famma-font: system-ui, "Segoe UI", Tahoma, "Noto Sans Arabic", sans-serif;
  --famma-radius: 12px;
  --famma-shadow: 0 4px 14px rgba(11,31,58,.10);

  /* Spacing scale */
  --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-6:24px; --sp-8:32px;
}
```

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

## Primary CTA
Background `--famma-orange`, white text, `--famma-radius`, min touch target
48×48px. Label: FR **"Commander"** / Derja **"أطلب توا"**. On product + checkout,
sticky at the bottom on mobile (§13, §30).

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
