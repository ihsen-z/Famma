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
