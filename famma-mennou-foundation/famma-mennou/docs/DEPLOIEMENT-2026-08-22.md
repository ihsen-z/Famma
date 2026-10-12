# Journal de déploiement — fammaminnou.tn (22/08/2026)

Ce document est un **compte rendu**, pas une procédure : il dit ce qui a été
réellement fait, dans quel ordre, et ce qui a résisté. La procédure cible reste
`DEPLOY-OXAHOST.md` ; le passage Docker → Local est dans `MIGRATION-LOCAL.md` ;
la décision d'environnement est l'ADR-006 de `DECISIONS.md`.

Contrainte du jour : **pas de SSH** sur ce palier Oxahost, donc pas de WP-CLI
côté serveur. Tout est passé par DirectAdmin (File Manager), phpMyAdmin et
l'administration WordPress.

## 1. État de l'hébergement à l'arrivée

| Constat | Valeur |
|---|---|
| Panneau | DirectAdmin `da5.tn.oxa.host:2222`, compte `fammamin`, LiteSpeed |
| PHP | **8.3.26** (CloudLinux présent ; aucun sélecteur de version dans le panneau) |
| Base | `fammamin_db` créée, **0 table** ; utilisateur `fammamin_dba_dmin`, hôte `localhost` |
| `public_html` | cœur **WordPress 7.0.4** déposé mais **non configuré** : pas de `wp-config.php`, `wp-content` réduit à `plugins`/`themes`/`index.php`, aucun `uploads` |
| SSL | **aucun certificat**, Let's Encrypt en échec |
| DNS | **`fammaminnou.tn` non enregistré** — le registre `.tn` (`ns2.ati.tn`) répond NXDOMAIN |

Réponses de la Phase 0 obtenues **sans ticket**, en lisant le panneau et en
exécutant du PHP :

- `max_input_vars` **et** `memory_limit` sont surchargeables depuis *PHP
  Settings* (avec `max_execution_time`, `upload_max_filesize`, `post_max_size`).
  Un réglage de panneau survit à une migration, contrairement à un `.user.ini` :
  le gabarit du manuel devient donc inutile ici.
- PHP 8.3 est **déjà** la version servie → la question bloquante du manuel est
  sans objet.
- Les **connexions HTTPS sortantes** fonctionnent (le serveur a téléchargé
  depuis wordpress.org), ce dont dépend le tracking Meta côté serveur.
- **Pas de SSH**, pas de terminal, pas de WP-CLI.

## 2. Ce qui a été déposé, et comment

Le dépôt ne contient que le code FAMMA : faire passer WordPress, WooCommerce,
TranslatePress et Kadence par un téléversement navigateur (~150 Mo) était
inutile puisque **le serveur sait les télécharger lui-même**.

1. **`wp-config.php` de production** téléversé (76 lignes) : `utf8mb4`, clés de
   sécurité neuves d'`api.wordpress.org`, `WP_DEBUG` à `false`,
   `DISALLOW_FILE_EDIT`, `WP_MEMORY_LIMIT`, `FS_METHOD direct` (sinon LiteSpeed
   réclame des identifiants FTP à chaque installation d'extension),
   `WP_AUTO_UPDATE_CORE = 'minor'`, les 15 constantes `FAMMA_*` **vides** (§60),
   et `FORCE_SSL_ADMIN` **commenté** — l'activer avant le certificat rendrait
   l'administration inaccessible. Le mot de passe de base a été saisi par le
   propriétaire, jamais par l'agent.
2. **`famma-code.zip`** (330 Ko, 51 fichiers) téléversé dans `wp-content` puis
   extrait : `famma-core` (4), `famma-child` (45), et **2** mu-plugins.
   `famma-local-mail.php` est **volontairement exclu** : il route le courrier
   vers Mailpit, c'est de l'infrastructure de développement.
3. **Un script PHP jetable**, à nom imprévisible, a téléchargé côté serveur
   Kadence 1.5.2, WooCommerce 11.0.1 et TranslatePress 3.3.3 — **versions
   identiques à Local**, taille contrôlée à l'octet — les a extraits, puis a
   remis les permissions correctes. Supprimé définitivement juste après.
4. **Base importée** par phpMyAdmin : 390 requêtes. Le dump avait été produit sur
   Local par `wp search-replace ... --export`, donc **87 URL réécrites** en
   respectant les données sérialisées ; contrôle : zéro occurrence de
   `famma.local` dans le fichier.
5. **Cœur mis à jour 7.0.4 → 7.1** depuis l'administration (téléchargement côté
   serveur), pour s'aligner sur Local.
6. **Traductions françaises** installées pour le cœur, WooCommerce,
   TranslatePress et Kadence.

## 3. Les quatre pièges rencontrés

| Piège | Symptôme | Ce qui le résout |
|---|---|---|
| **Jonctions invisibles aux archives** | sous Local, `famma-core`/`famma-child`/`mu-plugins` sont des jonctions Windows. `tar` et `find` de Git Bash les traversent **pas** (0 fichier vu) là où PHP, nginx et l'explorateur en voient 45 | construire l'archive avec un outil natif Windows (ici PHP `ZipArchive`) et **compter les fichiers** dans le zip avant de l'envoyer |
| **Permissions 666 après extraction** | l'extracteur de DirectAdmin pose les fichiers accessibles en écriture par tous, et son dialogue *Set Permissions* n'a **pas** d'option récursive | script jetable : répertoires 755, fichiers 644 → **7 833 fichiers** corrigés |
| **404 sur tout sauf l'accueil** | aucun `.htaccess` : le cœur avait été déposé sans lui | *Réglages → Permaliens → Enregistrer* : WordPress l'écrit lui-même |
| **Interface en anglais après import** | `WPLANG` valait `fr_FR` mais les `.mo` vivent dans `wp-content/languages`, qui n'était pas dans l'archive | resélectionner la langue dans *Réglages généraux*, puis *Mises à jour → Mettre à jour les traductions* |

Deux faux problèmes, à ne pas rechercher inutilement :

- **« TranslatePress a rencontré des erreurs SQL »** : l'alerte visait
  `Table 'local.wp_trp_gettext_ar'` — la base **`local`**, horodatée 15h12. Un
  incident **de Local**, transporté par le dump (le `search-replace` avait même
  réécrit l'URL enregistrée). Purgé par
  `DELETE FROM wp_options WHERE option_name = 'trp_db_errors'`.
- **Produits de test migrés** : deux produits créés sur Local pendant les essais
  (18h08 et 18h18, après le provisionnement, avant l'export) se sont retrouvés
  en production. Mis à la corbeille. Aucune commande, aucun client : la seule
  autre donnée transportée est le compte administrateur.

## 4. Contrôles passés

- 15 URL en **200** : accueil, boutique, panier, mon-compte, 8 pages
  d'information, une page de catégorie, `/ar/`, `/ar/boutique/`.
- Anciennes adresses en **301** : `/shop/`, `/cart/`, `/checkout/`,
  `/my-account/` → slugs français (mu-plugin `famma-legacy-urls`).
- `/ar/` en `<html dir="rtl" lang="ar">`, `/` en `lang="fr-FR"`.
- WooCommerce en français (« Votre panier est actuellement vide. ») →
  **l'écart E-28 du plan UX est fermé en production**.
- Réglages : Tunisie, **TND à 3 décimales**, symbole à droite avec espace, taxes
  désactivées, **COD activé** avec le titre bilingue
  « Paiement à la livraison — الدفع عند التوصيل », **livraison gratuite** sur la
  zone couvrant tout le pays, adresse de boutique **vide** (choix du
  propriétaire).
- Section **« Règles métier FAMMA »** présente, multiplicateur **×3**.
- Design servi : `tokens.css`, `layout.css`, `home.css`, `product-card.css`,
  `fonts.css`, `hero.js`, `carousel.js`, `navigation.js`, `favourites.js`, plus
  Kadence en parent. En-tête bilingue, 5 rayons, 6 filtres, compteurs réels.
- Extensions : `famma-core` 0.2.0, WooCommerce, TranslatePress **actives** ;
  Akismet et Hello Dolly inactives (supprimables).
- Le suivi diagnostique proposé par TranslatePress a été **refusé** : aucun
  consentement donné au nom du propriétaire.

## 5. Reste à faire

1. **Enregistrer `fammaminnou.tn`.** Le site est complet mais invisible, et c'est
   ce qui débloquera le certificat. La ligne `hosts` du poste de développement
   est à retirer ensuite.
2. Après émission du certificat : décommenter `FORCE_SSL_ADMIN` et repasser les
   URL en `https` (le dump `famma-prod-https.sql` est prêt, ou un
   `search-replace` suffit).
3. **Mot de passe administrateur** (faible, hérité de Local) et **e-mail du
   compte** (`dev-email@wpengine.local`) : à changer avant toute exposition
   publique. Report assumé par le propriétaire.
4. Vider la corbeille des produits.
5. Renseigner les constantes `FAMMA_*` (WhatsApp, Meta, GA4, TikTok,
   coordonnées, réseaux) et l'adresse de la boutique.
6. Rédiger **Retours et remboursements** et **Conditions générales de vente**,
   volontairement vides.
7. Jouer le **parcours COD de bout en bout** (§11 point 3) avec un produit
   marqué, supprimé ensuite.
8. Mettre en place une sauvegarde régulière côté Oxahost (*Backup and Restore*
   non encore inspecté).
