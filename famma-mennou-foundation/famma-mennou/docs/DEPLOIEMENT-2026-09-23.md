# Déploiement du 23/09/2026 — plan d'action, semaine 1

Suite de l'audit complet (`docs/audit-2026-09-23/`). Production : fammaminnou.tn, OXA Host / DirectAdmin.

## 1. Sauvegarde préalable

- **Sauvegarde complète** créée dans DirectAdmin → Backup and Restore avant toute modification (fichiers, bases, e-mails, les 2 domaines).
- Fichier : `/home/fammamin/backups/backup-Sep-23-2026-1.tar.zst` (95,75 Mo, 23/09 15:50).
- ⚠️ Elle reste **sur le même serveur**. La télécharger et la conserver ailleurs (disque externe, cloud) pour qu'elle protège vraiment.

## 2. Code déployé (commits `c14f124` → `1d0ac36`)

Méthode : archive `git archive` des fichiers commités (plugin famma-core, thème famma-child, mu-plugins `famma-env.php` et `famma-legacy-urls.php`), envoyée dans `public_html/wp-content` puis extraite avec « fusionner et écraser ». `famma-cron.php` a été envoyé à part, une fois le cron serveur vérifié.

Ce déploiement met aussi en ligne les commits des 27 et 28/08 : section vidéo produit, désormais avec cache oEmbed, et sélecteur de quantité à 48 px.

| Correctif | Constat | Vérifié en ligne |
|---|---|---|
| WhatsApp et `tel:` au format international | UX-01 | ✅ 4 liens `wa.me/21656261412`, `tel:+21656261412` |
| E-mails « Nouvelle commande » et « Commande reçue » à la création | CODE-01 | ⏳ à confirmer à la prochaine vraie commande |
| Rayons vides masqués (menu, carrousel, filtres) | UX-09 / SEO-07 | ✅ 6 rayons non vides seulement |
| Liens de rayon vers `/categorie/<slug>/` | SEO-01 | ✅ |
| Bloc « Les premiers produits arrivent » masqué | UX-10 | ✅ absent de l'accueil |
| `/wp/v2/users` fermé aux anonymes | SEC-06 | ✅ HTTP 401 `rest_user_cannot_view` |
| Méthodes authentifiées XML-RPC refusées | SEC-06 | ✅ par le code (blocage complet du fichier : voir §4) |
| Cache oEmbed de la vidéo produit | CODE-07 | ✅ déployé (aucune vidéo en ligne pour l'instant) |
| `famma-local-mail.php` : garde d'environnement | SEC-12 | ✅ (absent de la prod de toute façon) |
| `deploy.sh` : mu-plugins listés un par un | CODE-05 | dépôt uniquement |

Contrôles après déploiement : accueil, boutique, contact, fiche produit, catégorie et panier répondent en HTTP 200, sans erreur fatale. `php -l` et `phpcs` sont propres sur tout le projet (0 erreur).

## 3. Réglages serveur faits

- **Tâche cron** DirectAdmin, toutes les 5 min :
  `/usr/local/bin/php -q /home/fammamin/domains/fammaminnou.tn/public_html/wp-cron.php >> /home/fammamin/wp-cron.log 2>&1`
  Premier passage à 16:05 sans erreur (journal vide).
- **`famma-cron.php`** (mu-plugin) : `DISABLE_WP_CRON` en production. Les visiteurs ne déclenchent plus WP-Cron.
- **WooCommerce 11.1.2** et **TranslatePress 3.3.6** : déjà mis à jour à 15:36 (SEC-02 et SEC-03 fermés).

## 4. Restant à faire

### 4.1 Bloc `.htaccess` prêt à coller (bloqué par le système de permissions)

À insérer dans `public_html/.htaccess`, **juste avant** `# BEGIN WordPress` :

```apache
# BEGIN FAMMA securite (audit du 23/09/2026 : SEC-06, SEC-09, SEC-14)
# En-tetes de securite. HSTS volontairement court au depart (1 jour) :
# a allonger (31536000) une fois la stabilite confirmee.
<IfModule mod_headers.c>
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"
Header always set Strict-Transport-Security "max-age=86400"
</IfModule>
# XML-RPC inutilise (ni appli mobile ni Jetpack) : bloque avant PHP.
<Files xmlrpc.php>
Require all denied
</Files>
# Fichiers qui divulguent les versions ou le code source des traductions.
<FilesMatch "^(readme\.html|license\.txt|wp-config-sample\.php)$|\.(po|pot)$">
Require all denied
</FilesMatch>
# END FAMMA securite
```

Contrôle après enregistrement : l'accueil doit répondre en 200 avec l'en-tête `x-frame-options`, et `/readme.html` puis `/xmlrpc.php` en 403.

### 4.2 Nettoyage

- Supprimer `public_html/wp-content/famma-deploy-1.zip` : c'est l'archive de déploiement, encore téléchargeable publiquement. Elle ne contient que le code du plugin et du thème, sans secret.

### 4.3 Depuis l'administration WordPress (connexion du propriétaire nécessaire)

- Mettre à la corbeille **Prd 1** et l'ancienne fiche **Pince de Câblage Pro** (`pince-de-cablage-pro`).
- FAMMA → Barre supérieure : retirer « Satisfait ou remboursé » tant que la page Retours n'est pas rédigée.
- Retours et CGV : `noindex` via la boîte Référencement FAMMA.
- Réglages → Général : décocher « Tout le monde peut s'inscrire » et vérifier que le rôle par défaut est « Abonné ».
- WooCommerce → Réglages → Comptes : pas de création de compte sur « Mon compte ». Produits : avis réservés aux acheteurs vérifiés.
- Extensions : activer les mises à jour automatiques (WooCommerce, TranslatePress, Site Kit).
- Installer **LiteSpeed Cache** avec les réglages de `audit-2026-09-23/audit-performance.md` §6.
- Apparence → Menus : liens Boutique, Promotions et Nouveautés en `https://` ou relatifs, avec les accents.
- TranslatePress → sélecteur de langue : désactiver le sélecteur flottant.
- FAMMA → Pages : numéro WhatsApp à confirmer (56261412 affiché ; 26767491 noté comme confirmé dans les notes projet).

### 4.4 Réservé au propriétaire (sécurité du compte, création de comptes)

- Activer la **double authentification DirectAdmin** (Profil → Two-Step Authentication).
- Créer un nouvel administrateur au login imprévisible, puis supprimer `admin` en réattribuant ses contenus.
- Créer un compte SMTP transactionnel (le quota de l'hébergeur est de 40 e-mails par jour).

### 4.5 Ticket OXA Host (brouillon, à envoyer par le propriétaire)

> Bonjour,
> Pour le compte **fammamin** (fammaminnou.tn), pourriez-vous :
> 1. Exempter de la page anti-bot Imunify360 (« Un instant… / Votre demande est en cours de vérification ») les navigateurs intégrés Facebook, Instagram et TikTok, ainsi que les robots d'indexation (Googlebot, Bingbot, GPTBot, ClaudeBot, PerplexityBot). Nos clients arrivent par ces publicités et certains voient la page de vérification.
> 2. Nous indiquer si **OPcache** est actif pour PHP sur ce compte, et si Redis ou LSMCD est disponible pour le cache objet de LiteSpeed Cache.
> 3. Relever le quota d'envoi d'e-mails (actuellement 40 par jour) : chaque commande envoie 2 e-mails.
> 4. Confirmer l'existence de sauvegardes côté hébergeur (JetBackup ou autre), leur fréquence et leur rétention.
> Merci.

---

## 5. Déploiement du soir : fiche produit (rapport UX du 23/09)

Suite de `RAPPORT-UX-FICHE-PRODUIT-2026-09-23.md` et des décisions du propriétaire : formulaire à 4 champs obligatoires (ville facultative), « Ajouter au panier » en bouton secondaire, « DT » sur le site français, délai de 24 h à 48 h, WhatsApp au 26767491 « pour le moment ».

### 5.1 Code déployé (commits `89e9040` → `4dada78`)

Archive `git archive` du plugin et du thème, déposée **hors du dossier public** (racine du compte, `famma-deploy-fiche.zip` puis `famma-deploy-vignettes.zip`) et extraite vers `domains/fammaminnou.tn/public_html/wp-content` avec « fusionner et écraser ». Archive de retour arrière gardée sur le poste : état `1d0ac36`.

### 5.2 Réglages faits dans l'administration

| Réglage | Valeur |
|---|---|
| FAMMA → Pages → Numéro WhatsApp | `26767491` (le téléphone de contact reste 56261412, non tranché) |
| FAMMA → Pages → Délai de livraison (FR) | `Livraison en 24 h à 48 h` |
| FAMMA → Pages → Délai de livraison (derja) | `التوصيل في 24 لـ 48 ساعة` |
| TranslatePress → Sélecteur de langues → Floating | désactivé |

### 5.3 Vérifié en ligne (visiteur, 375 px et 1 366 px, FR et AR)

| Point | Avant | Après |
|---|---|---|
| Pages clés (accueil, boutique, 2 fiches, fiche AR, panier, contact) | — | HTTP 200, aucune erreur |
| Colonnes desktop galerie / résumé | 280 / 280 px | 636 / 530 px |
| Marges mobiles | 44 px | 16 px |
| H1 / prix (mobile) | 752 / 973 px | 646 / 725 px |
| Vignettes | 2 rangées, −32 px sur la photo, 50 % d'opacité | 1 rangée défilante, 100 % |
| Prix | orange 2,46:1, « د.ت 79,000 » | bleu nuit, « 79,000 DT » (AR : « 79,000 د.ت ») |
| Formulaire express | 1 191 px, 7 saisies | 983 px, 4 obligatoires + ville facultative |
| Formulaire après appui sur la barre | champ sous l'en-tête (40–88 px) | titre à 158 px, champ à 240–288 px |
| Section Livraison | 5 927 px | 2 995 px |
| Hauteur de la fiche mobile | 8 906 px | 6 031 px |
| Sélecteur de langue flottant, WhatsApp flottant | sur le prix, sur le contenu | retirés de la fiche (WhatsApp dans la barre) |
| Quantité | 2 sélecteurs | 1 seul, synchronisé avec le panier, total recalculé |
| Téléphone « 12 » | accepté jusqu'à l'envoi | refusé à la sortie du champ |
| Checkout classique | Prénom + Nom, ville obligatoire | Nom complet, ville (facultatif), `XX XXX XXX` |
| AR | doublons derja / arabe standard | une ligne par promesse, « أكّد طلبيتك », « زيد للسلّة » |

Corrigé pendant la vérification : les vignettes étaient centrées par Kadence et la première était coupée (commit `4dada78`, redéployé seul).

### 5.4 Restant

- Supprimer `public_html/wp-content/famma-deploy-1.zip` (toujours téléchargeable) ; les deux archives du soir sont hors du dossier public et peuvent aussi être supprimées.
- Parcours COD de bout en bout avec une vraie commande de test (création → confirmé → expédié → livré), à faire avec l'accord du propriétaire.
- Libellé e-mail du checkout : « (facultatif) » apparaît deux fois (constat UX-18, antérieur).
- Décisions en attente : téléphone de contact, « Satisfait ou remboursé », doublon « Pince de Câblage Pro ».

### 5.5 Correctif : favori cliquable sur toute la page (mobile, commit `3d4a5af`)

Sous 1000 px, l'extension tactile `::after` du cœur des cartes (position absolue, `inset: -9px`) s'appliquait aussi au bouton favori de la fiche, en `position: static` : elle se calait sur un ancêtre lointain et recouvrait toute la page, donc chaque tap basculait le favori. Retirée sur la fiche (le bouton fait déjà 48 px).

Déployé seul : `product.css` envoyé dans `themes/famma-child/assets/css/` (écrasement) via le gestionnaire de fichiers DirectAdmin. Vérifié en ligne à 372 px : `::after` à `none`, 0 point sur 78 de la page ne touche le favori, le bouton bascule toujours (`aria-pressed` false → true → false), nouvelle version de la feuille servie sans purge de cache.

---

## 6. 24/09 matin : textes de la fiche produit éditables (commit `387b077`)

Règle « tout paramétrable depuis l'admin » : les textes commerciaux de la fiche, restés dans le code, passent dans **FAMMA → Pages → Fiche produit** (15 textes, chacun en français et en derja). Même mécanisme que les pages À propos et Contact (`Pages_Content`), schéma dans `includes/product-content.php`.

| Texte | Où il s'affiche |
|---|---|
| Sous le prix — paiement, livraison | Liste de réassurance (le délai reste dans Contact › Délai de livraison) |
| Bouton « Commander » | Barre du bas, titre du formulaire express |
| Bouton de validation | « Confirmer ma commande / أكّد طلبيتك » |
| Bouton « Ajouter au panier » | Bouton secondaire de la fiche |
| Bouton WhatsApp sous le formulaire | « Une question sur ce produit ? » |
| WhatsApp — message pré-rempli | Lien WhatsApp de la barre du bas, `{produit}` = nom du produit |
| Bénéfices 1 à 4 (titre, texte) | Bandeau sous la fiche (écrans larges) |

Règles : une ligne vidée disparaît ; un bouton vidé reprend son libellé par défaut ; valeurs par défaut = textes affichés jusqu'ici.

Déployé par DirectAdmin comme la veille (archive hors du dossier public, extraite vers `wp-content`). Retour arrière : archive de l'état `e77a5af` (inclut le correctif du favori de la section 5.5).

Vérifié en ligne : 7 pages en HTTP 200 sans erreur ; textes FR et AR identiques à la veille, sauf les lignes derja affichées sous le français dans la réassurance, qui reprennent désormais le texte de la version arabe (« الدفع كي يوصلك », « التوصيل فابور، في تونس الكل ») au lieu de deux formulations différentes ; section visible dans l'administration, 30 champs préremplis.

Reste dans le code, volontairement : petits textes d'interface traduits par fichiers `.po` (libellés de champs, erreurs, « Lire la suite »), mise en page, règles de fonctionnement. À traiter plus tard : textes de réassurance du **checkout** (`famma_child_checkout_trust()`), encore dans le code.

---

## 6. Déploiement du 24/09 (soir) : panier et commande selon les maquettes

Maquettes Claude Design « FAMMA Panier » et « FAMMA Commande ». Commit `364555c` (thème `famma-child` uniquement ; famma-core inchangé).

### 6.1 Code déployé

Archive `famma-deploy-tunnel.zip` (13 fichiers du thème, `git archive` de `364555c`), déposée à la racine du compte (hors dossier public) et extraite vers `domains/fammaminnou.tn/public_html/wp-content` avec « fusionner et écraser ». Archive de retour arrière gardée sur le poste : `famma-rollback-tunnel.zip` (état `65b4099` des fichiers modifiés) ; en cas de retour arrière, supprimer aussi les fichiers ajoutés `inc/tunnel.php`, `inc/cart.php`, `inc/checkout-layout.php`, `assets/css/tunnel.css`, `assets/css/cart.css`, `assets/js/cart.js`.

| Écran | Changement |
|---|---|
| Panier, commande, confirmation | En-tête commun : fil d'Ariane, étapes Panier → Commande → Confirmation, titre (remplace le bandeau Kadence) |
| Panier | Lignes en cartes (vignette, réf., stock, prix unitaire, prix barré), sélecteur − / + avec mise à jour automatique, code promo en encadré, récapitulatif collant, « Passer la commande », réassurance de l'administration, encart WhatsApp, carte « panier vide » |
| Commande | Cartes 1 Vos coordonnées / 2 Adresse de livraison / 3 Mode de livraison, récapitulatif collant avec vignettes, paiement COD en carte, « Confirmer ma commande » |

Écarts assumés avec la maquette : ni frais ni seuil de livraison, ni express, ni frais COD, ni carte/virement, ni code FAMMA10, ni « retour gratuit 7 jours », ni SMS promis ; « Nom complet » conservé.

### 6.2 Réglage fait

- WooCommerce → Réglages → Comptes et confidentialité : textes de confidentialité (commande et inscription) passés de l'anglais au libellé français officiel de WooCommerce.

### 6.3 Vérifié

- En local (WP_DEBUG, `debug.log` propre) : mise à jour auto 2 → 3, suppression jusqu'au panier vide (AJAX), validation du formulaire vide, commande complète jusqu'à la confirmation (commande test #99 mise à la corbeille), AR/RTL, 375 px sans défilement horizontal.
- En ligne : accueil, boutique, panier, fiche, panier AR en HTTP 200 sans erreur ; `/commande/` redirige vers le panier quand il est vide (normal). Panier et commande 1 536 px conformes ; − en direct 2 → 1, total 58 → 29 DT ; à 375 px (FR et AR) : largeur de page 357 px, aucun défilement horizontal, étapes et boutons corrects (« أكّد طلبيتك », « عدّي الطلبية »).
- `php -l` et `phpcs` propres sur tout le projet.

### 6.4 Restant

- `famma-deploy-tunnel.zip` à la racine du compte (hors dossier public) peut être supprimée avec les archives précédentes.
- « Complétez votre commande » n'apparaît que si des ventes croisées sont renseignées sur les fiches produit.
- Parcours COD réel (confirmé → expédié → livré) toujours à faire avec l'accord du propriétaire.

---

## 7. Déploiement du 24/09 (nuit) : newsletter et « Ils nous font confiance » sur l'accueil

Maquette « FAMMA Accueil ». Commits `a6e2b87` et `38140b5` (famma-core + thème).

### 7.1 Code déployé

Archive `famma-deploy-accueil.zip` (13 fichiers), racine du compte, extraite vers `domains/fammaminnou.tn/public_html/wp-content` (« fusionner et écraser »). Retour arrière sur le poste : `famma-rollback-accueil.zip` (état `ca85c2d`) ; supprimer aussi `plugins/famma-core/includes/home-content.php`, `plugins/famma-core/includes/newsletter.php` et `themes/famma-child/inc/home-social.php`.

| Élément | Où le régler |
|---|---|
| Newsletter : titre, texte, champ, bouton, mention, message de confirmation (FR + derja) | FAMMA → Pages → « Page d'accueil » (bouton vide = section masquée) |
| Inscrits : liste, recherche, corbeille, export CSV | FAMMA → Newsletter |
| Confiance : titre, chiffre + légende, 3 témoignages (FR + derja) | FAMMA → Pages → « Page d'accueil » |
| Note moyenne | calculée sur les avis WooCommerce approuvés, jamais saisie |

Sécurité : nonce, pot de miel, 5 inscriptions / heure / IP (empreinte), `sanitize_email` + `is_email`, adresse déjà inscrite = même réponse (pas d'énumération). Le site enregistre les adresses, il n'envoie aucun e-mail (quota de 40/jour) : les campagnes se font depuis l'export CSV.

Aucun chiffre ni témoignage par défaut (§58) : la section « Ils nous font confiance » reste masquée tant que le propriétaire n'a pas saisi de vraies données ou qu'aucun avis n'est approuvé.

### 7.2 Vérifié

- Local : rendu 1 366 et 375 px sans défilement horizontal, adresse invalide refusée, inscription valide enregistrée (privée, langue fr), requête forgée sans nonce refusée, section confiance affichée avec une donnée de test puis retirée ; `debug.log` propre.
- En ligne : accueil FR et AR, boutique, panier, fiche, À propos, Contact en HTTP 200 sans erreur ; FAMMA → Pages affiche la section « Page d'accueil » (15 champs × 2 langues) ; inscription réelle `test-deploiement@example.com` → message vert, visible dans FAMMA → Newsletter, puis mise à la corbeille.
- `php -l` et `phpcs` propres ; type de retour `never` remplacé par `void` (compatibilité PHP 8.0).

### 7.3 Restant

- Saisir, si souhaité, un vrai chiffre et de vrais témoignages (avec l'accord des clients) pour faire apparaître « Ils nous font confiance ».
- Vider la corbeille de FAMMA → Newsletter et supprimer `famma-deploy-accueil.zip` à la racine du compte.
- Si un cache de page (LiteSpeed) est activé plus tard, exclure l'accueil du cache au-delà de 12 h ou le purger chaque jour : le nonce du formulaire expire au bout de 12 à 24 h.


---

## 8. Nuit du 24 au 25/09 : pages vérifiées, anglais et promesses non tenues retirés

Audit automatique des 70 pages en ligne (FR + AR : pages, 14 fiches, 5 rayons, recherche, 404, panier et commande remplis) : texte visible, attributs lus par les lecteurs d'écran (`aria-label`, `alt`, `placeholder`) et balises de partage.

### 8.1 Code déployé (commits `bac1ac5`, `357098d`, `bb0f2bd`)

Trois archives partielles, extraites dans `wp-content/` : seuls les fichiers modifiés, dont la version en ligne était vérifiée identique au commit précédent.

| Constat | Correctif |
|---|---|
| 404 arabe : « عفوا! ’t لا يمكن… » puis une phrase en anglais ; 404 française : « n'ai été trouvé » | les deux phrases de Kadence réécrites (FR + derja) |
| Fil d'Ariane : `aria-label="Breadcrumb"` en anglais sur la boutique, les rayons et les fiches | libellé traduit |
| « Promotions » au menu et « En promotion (1) » en boutique, alors qu'aucun produit n'a de prix barré (la liste de WooCommerce est périmée) | compte revérifié produit par produit ; le lien et la case disparaissent sans promotion et reviennent seuls |
| Tri « par notes moyennes » sans aucun avis | retiré tant qu'aucun produit n'est noté |
| E-mail au checkout : « (facultatif) » écrit deux fois | traduction corrigée (FR + AR) |
| Champs multilignes en derja : « من 9:00 » affiché « ن 9:00 » (octet 0x85 de « م » pris pour un saut de ligne) | `preg_split( '/\R/u' )` dans les pages, la FAQ produit et le référencement |

Retour arrière : `famma-rollback-pages.zip` (état `38140b5`) pour le premier lot ; les deux autres correctifs se retirent en rechargeant les fichiers de `bac1ac5`.

### 8.2 Réglages faits dans l'administration

| Où | Avant | Après |
|---|---|---|
| Apparence → Menus → Principal | « Categories », « Nouveautes », liens en `http://` | « Catégories » (classe `famma-menu-categories`), « Nouveautés », liens en `https://` |
| Widgets de la boutique | titres saisis « Categories », « Disponibilite » (non traduits en arabe) | titres vidés : les libellés du thème s'appliquent (« Catégories / الأقسام », « Disponibilité / التوفّر »…) |
| FAMMA → Pages → barre supérieure, promesse 4 | « Satisfait ou remboursé / كان ما تخرجش فرحان نرجعولك فلوسك » (aucune politique de retour publiée) | « Livraison en 24 h à 48 h / التوصيل في 24 لـ 48 ساعة », icône horloge |
| FAMMA → Pages → À propos | « On cherche, on teste, on sélectionne » | « On cherche, on sélectionne » (FR + derja) |
| FAMMA → Pages → Contact, horaires | « 9h -> 18h », rien en derja | « De 9 h à 18 h » / « من 9:00 لـ 18:00 » |

### 8.3 Vérifié en ligne

404 FR et AR, fil d'Ariane FR (« Fil d'Ariane ») et AR, menu FR/AR sans « Promotions », boutique sans « En promotion » ni tri par notes, titres de filtres FR/AR, barre supérieure FR/AR, À propos, Contact FR/AR : HTTP 200, aucune erreur PHP dans le HTML. Nouvel audit : plus aucun anglais hors textes des fiches produit et noms techniques `utm_*` de la politique de confidentialité.

### 8.4 Validé par le propriétaire le 25/09, puis appliqué

- **13 fiches produit** réécrites par l'API WooCommerce, texte d'origine (nom, description, résumé) sauvegardé dans la méta `_famma_backup_textes_2026_09_25` de chaque produit :
  - anglais → français : « (Salad Pod) » retiré du nom, « Shark » → « Requin », BPA Free, Fresh, Junk Food, Dry Top, Water-Stop, Panoramic View, Brushless, RPM, DIY, Forward & Reverse, Chuck, Power Stack, Adjustable Torque, No Ink / No Paint / Clean Hands, Fine Motor Skills, Reusable, Lightweight & Stable, Red Grid, Express Cleaning, Auto-Rebound, Shoulder Harness, Buckle Strap, Sensitive Skin, LBS ;
  - affirmations invérifiables retirées : « أسرع بـ 10 مرات », « آلاف المرّات », « 100 % » (étanchéité, PVC, sans BPA), « الأصلي / أصلي », « حتى قطرة ماء ما تدخلش », « تدوم معاك سنين » ;
  - gilet enfant : certification U.S. Coast Guard / Transport Canada retirée (titre, paragraphe, puce, ligne du tableau), ainsi que « يعوم بكل أمان » et « حماية جبّارة » ; ajout d'un rappel : la sangle aide à flotter mais ne remplace pas la surveillance d'un adulte.
- **16 photos** (boîte à salade, masque) : texte alternatif et titre sans « Salad Pod » ni « Shark ».
- **Retours** et **CGV** : notes internes remplacées par un texte d'attente qui annonce la rédaction en cours et ne reprend que des faits déjà publiés (prix en dinars livraison comprise, commande sans compte, appel de confirmation, livraison offerte dans les 24 gouvernorats, paiement en espèces à la réception), FR + derja. Les rappels « À compléter par le propriétaire » restent, visibles des seuls administrateurs. Révisions WordPress conservées.
- **Livraison** : rubrique « Délai : 24 h à 48 h » ajoutée.

Nouvel audit des 70 pages : aucun anglais visible (hors noms techniques `utm_*`), aucune des promesses listées. « shark » ne subsiste que dans l'adresse de la fiche et les noms de fichiers des photos : changer l'adresse casserait les liens déjà partagés.

### 8.5 Restant

- CGV et politique de retour définitives, avec les informations légales (raison sociale, matricule fiscal, registre du commerce) : à fournir par le propriétaire.
- Marque « Body Glove Paddle Pals » citée sur le gilet : à confirmer (produit d'origine ou non).
- Le délai « 24 h à 48 h » est écrit en dur dans les pages Livraison : à changer aussi dans FAMMA → Pages → Contact si le délai évolue.
- Menus et pages en arabe : les libellés (Accueil, Boutique, Livraison…) restent en français sur /ar/ tant qu'ils ne sont pas traduits dans TranslatePress.
- Supprimer les archives `famma-deploy-pages.zip`, `famma-deploy-promos.zip`, `famma-deploy-lignes.zip` à la racine du compte.
