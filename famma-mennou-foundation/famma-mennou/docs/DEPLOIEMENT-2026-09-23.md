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
