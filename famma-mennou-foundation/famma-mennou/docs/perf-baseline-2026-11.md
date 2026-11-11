# Référence de vitesse — 11/11/2026 (avant le nouveau look)

Production fammaminnou.tn, famma-core 0.3.8, Kadence 1.5.2, WooCommerce 11.1.2.
Mesures faites depuis la Tunisie, sur l'ordinateur du projet, avec Lighthouse
12.8.2 en mode mobile (simulation 4G lente + processeur divisé par 4). Le quota
gratuit de l'API PageSpeed était épuisé ce jour-là.

## Résultats

| Page | Note | FCP simulé | LCP simulé | TBT | CLS | FCP = LCP observés¹ |
|---|---|---|---|---|---|---|
| Accueil (essai 1) | 60 | 2,4 s | 7,1 s | 370 ms | 0 | 2,5 s |
| Accueil (essai 2) | 64 | 2,1 s | 6,7 s | 360 ms | 0 | 1,5 s |
| Accueil (essai 3) | 65 | 2,3 s | 6,5 s | 330 ms | 0 | 1,5 s |
| Boutique | 91 | 1,9 s | 2,6 s | 200 ms | 0 | 1,9 s |
| Fiche Confort | 57 | 2,9 s | 6,9 s | 440 ms | 0 | 2,4 s |

¹ Temps réellement observés pendant le chargement, sans la simulation : l'élément
LCP s'affiche dès le premier rendu, sur les trois pages.

Contrôle externe (outil Ubersuggest/PageSpeed, mobile, accueil) : LCP 3,4 s,
FCP 1,8 s, TBT 50 ms, CLS 0,002, avec 601 ms perdus par la redirection
`http://` → `https://` (les visiteurs des publicités arrivent déjà en https).

Budget à tenir par le nouveau look : LCP simulé et note de l'accueil au moins
égaux à cette référence ; +8 Ko de JS et +8 Ko de CSS compressés au plus par
page ; CLS 0 ; aucun tiers nouveau.

## Ce qui retarde l'accueil (essai 2)

- **L'élément LCP** est la première carte du héros « stack ». Elle n'est ni
  cachée ni retardée par le JavaScript (opacité 1 dès le HTML). Elle a déjà
  `loading="eager"` et `fetchpriority="high"`.
- **L'image était trop grande** : le `sizes` par défaut de WooCommerce
  (`(max-width: 600px) 100vw, 600px`) faisait choisir la 768×768 (55,7 Ko)
  pour une carte de 320 px au plus. Corrigé sur la branche `claude/nouveau-look-wp`
  (`inc/home-hero.php`) : `(max-width: 432px) calc(100vw - 32px), 400px`.
  Sur un écran 375 px de densité 1,75 (profil Lighthouse), la 600×600 (37,9 Ko)
  est choisie : −18 Ko, −32 %. Les téléphones de densité 2,6 ou 3 gardent la
  1024 : le gain y est nul.
- **Le rendu attend 13 feuilles CSS et 4 scripts** placés dans `<head>`
  (≈ 110 Ko à priorité haute) : jQuery + jQuery Migrate, 2 scripts
  TranslatePress, les feuilles Kadence (dont `woocommerce.min.css`, 20 Ko) et
  celles du thème enfant. C'est la principale cause de l'écart entre le LCP
  simulé (≈ 6,7 s) et le premier rendu (≈ 2,2 s). Y toucher (CSS combinées ou
  asynchrones, scripts différés) relève du « niveau 2 » : à tester sur tout le
  parcours de commande, avec accord du propriétaire.
- **La police arabe** (`noto-kufi-arabic-var.woff2`, 31 Ko, priorité très
  haute) est chargée sur les pages françaises pour le slogan de l'en-tête.
  `font-display: swap` : elle ne bloque pas l'affichage, mais occupe la bande
  passante pendant le chargement de l'image du héros.
- Le processeur : `gtag` (216 ms), le pixel Meta (106 ms), `carousel.js`
  (376 ms d'évaluation et de rendu). Aucune tâche longue n'empêche le premier
  rendu.

## Fiche produit

L'élément LCP est le titre `<h1>` : il s'affiche au premier rendu. Le LCP
simulé de 6,9 s vient de la même file de ressources bloquantes, plus
`product.css` (11 Ko), `photoswipe` et les feuilles inutiles sur une fiche
(`comments.min.css`, `related-posts.min.css`).
