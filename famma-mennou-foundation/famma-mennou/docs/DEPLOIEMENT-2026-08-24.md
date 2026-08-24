# Mise en ligne du 24 août 2026

Six commits à déployer, depuis `3048e72` jusqu'à `a09a192`. Ce document est la marche à suivre exacte, dans l'ordre.

**Point important sur la base** : le transfert ne touche **que du code**. Aucune des données saisies sur Local ne part avec — ni les textes des pages, ni les coordonnées, ni les promesses de la barre. Il y a donc des réglages à refaire en ligne, listés en phase 3. C'est voulu : écraser une base de production est irréversible.

---

## Ce qui part

| Commit | Contenu |
|---|---|
| `3048e72` | Outillage de transfert (`scripts/deploy/`) |
| `98a460d` | Version arabe en derja — traductions, chargement, correctif RTL |
| `e69db8c` | Création de compte optionnelle au checkout |
| `4b28257` | Mémorisation de la carte de traduction (performance) |
| `c13a3ee` | Plan de site en 200 · validation du compte remise dans le plugin |
| `a09a192` | Rapport d'audit SEO (documentation seule) |

Trois répertoires, et rien d'autre : `wp-content/plugins/famma-core`, `wp-content/themes/famma-child`, `wp-content/mu-plugins`.

---

## Phase 0 — Le point de retour existe déjà

Les sauvegardes prises le 23 août sont toujours sur le serveur, dans `/domains/fammaminnou.tn/` :

- `famma-child-sauvegarde-avant-deploiement-2026-08-23.zip` (302 Ko)
- `famma-core-sauvegarde-avant-deploiement-2026-08-23.zip` (28 Ko)

Elles correspondent à l'état de production **avant** tout le travail de cette session. Aucun déploiement n'a eu lieu depuis, donc elles restent le point de retour valable. Inutile d'en refaire.

---

## Phase 1 — Transférer le code

### Option A — Par l'administration WordPress (la plus simple)

Les deux paquets sont prêts : `famma-core.zip` (44 Ko, 8 fichiers) et `famma-child.zip` (325 Ko, 55 fichiers).

1. **Extensions → Ajouter → Téléverser une extension** → `famma-core.zip` → WordPress détecte la version présente et propose **« Remplacer par la version téléversée »**. Accepter.
2. **Apparence → Thèmes → Ajouter → Téléverser un thème** → `famma-child.zip` → même remplacement.

Ce que cette option **ne** transfère pas : `wp-content/mu-plugins`. Ce n'est pas un problème pour ce déploiement — le seul mu-plugin modifié pendant la session est `famma-local-mail.php`, qui ne sert qu'en développement local.

### Option B — Par `deploy.sh` (si SSH est actif sur votre offre)

```bash
cp famma-mennou-foundation/famma-mennou/scripts/deploy/.env.deploy.example famma-mennou-foundation/famma-mennou/scripts/deploy/.env.deploy
```

Renseigner `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_WP_PATH` — **vérifier ce chemin dans le gestionnaire de fichiers du panneau**, un mauvais chemin écrit à côté sans rien signaler — et mettre `DEPLOY_MODE=tar`.

Simuler d'abord, rien n'est écrit :

```bash
bash famma-mennou-foundation/famma-mennou/scripts/deploy/deploy.sh --dry-run
```

Relire la liste : elle ne doit contenir que les trois répertoires. Puis transférer :

```bash
bash famma-mennou-foundation/famma-mennou/scripts/deploy/deploy.sh
```

Le script demande confirmation, passe `php -l` et `phpcs` avant d'envoyer, et refuse de partir si des modifications ne sont pas commitées.

**Rappel de la limite du mode `tar`** : il ne supprime rien à distance. Sans conséquence ici — ce déploiement n'enlève aucun fichier, il en ajoute et en modifie.

---

## Phase 2 — Vérifier que rien n'est cassé

Dans cet ordre, avant de toucher aux réglages :

1. L'administration répond et n'affiche **aucune erreur PHP**.
2. **Apparence → Thèmes** : *FAMMA Child* toujours actif.
3. **Extensions** : *FAMMA Core* toujours actif. S'il s'est désactivé, c'est une erreur fatale — restaurer la sauvegarde de la phase 0 et me le dire.
4. **Vider le cache LiteSpeed**, puis recharger l'accueil.
5. L'accueil, la boutique et une fiche produit s'affichent normalement en français.

---

## Phase 3 — Les réglages à refaire en ligne

C'est la partie que le transfert de code ne peut pas faire. Rien ici n'est optionnel : sans ces réglages, les nouvelles fonctionnalités restent inertes.

### 3.1 Comptes clients — *indispensable pour le checkout*

**WooCommerce → Réglages → Comptes et confidentialité** :

| Réglage | Valeur |
|---|---|
| Permettre aux clients de commander sans compte | **coché** |
| Permettre aux clients de créer un compte pendant la commande | **coché** ← le changement |
| Permettre aux clients de créer un compte sur la page « Mon compte » | **coché** |
| Envoyer un mot de passe généré automatiquement | **décoché** |

Tant que la deuxième ligne reste décochée, le choix « avec ou sans compte » ne s'affiche pas et la page de commande reste exactement celle d'aujourd'hui. C'est volontaire : un choix qu'on ne peut pas honorer ne se propose pas.

### 3.2 Contenu des pages — **FAMMA → Pages**

Nouvel écran. Trois sections à remplir :

- **Page À propos** et **Page Contact** : les champs arrivent pré-remplis avec ma proposition, en français et en derja. Cliquer **Enregistrer** fixe ces valeurs en base ; vous les corrigez ensuite librement.
- **Coordonnées** : téléphone, e-mail, adresse, numéro WhatsApp.
- **Barre de promesses** : quatre emplacements, libellé FR + derja, icône, mise en avant.

**Les horaires sont volontairement vides.** Les inventer aurait promis une disponibilité que personne n'a confirmée.

Deux points relevés sur Local, à trancher en ligne :

- Sur la promesse « satisfait ou remboursé », la case **« n'afficher que si un numéro WhatsApp est configuré » doit être décochée** — sinon la promesse disparaît dès que le numéro est vide, sans rapport avec son contenu.
- Les coordonnées en base sur Local (`56261412`, `noreply@fammaminnou.tn`) ne correspondent pas à celles que vous m'avez données (`26767491`, `contact@fammaminnou.tn`). À arbitrer.

### 3.3 Version arabe — ce qui reste du contenu

Le code est traduit en derja. Restent les **libellés de menu** (Accueil, Boutique, Categories, Promotions, Nouveautes, À propos, Contact) et les **noms de catégories produit** (Maison & Cuisine, Électronique, Auto & Moto, Beauté & Santé, Accessoires).

Ce sont des données en base : ça se traduit dans l'éditeur de **TranslatePress** (« Traduction du site » dans la barre d'admin), pas dans le code.

---

## Phase 4 — Vérifier les nouveautés en ligne

1. **Plan de site** : `https://fammaminnou.tn/wp-sitemap.xml` doit répondre **200**, pas 404. C'était le correctif SEO principal, et il n'était pas propre à Local.
2. **Une page inexistante** doit toujours répondre 404.
3. **Version arabe** : `/ar/` affiche l'interface en derja, de droite à gauche, sans débordement horizontal sur téléphone.
4. **Checkout** : le choix « avec ou sans compte » apparaît en tête, l'option sans compte cochée d'avance.
5. **Commande invité de bout en bout** — le parcours critique. Aucun mot de passe demandé, commande créée.
6. **Pages À propos et Contact** : contenu affiché, blocs vides invisibles pour les clients.

---

## Phase 5 — À faire avant l'ouverture réelle

Sans lien avec ce déploiement, mais bloquant pour la mise en service :

- **Supprimer les produits de test** s'il en existe en production. Un catalogue qui montre « Test 1 » signale un site inachevé, à Google comme aux clients.
- **Déléguer le domaine** chez votre registraire `.tn`. Tant que `fammaminnou.tn` ne résout pas publiquement, le site n'est atteignable que par votre fichier `hosts`, et Let's Encrypt ne peut pas émettre de certificat valable.
- **Installer une extension SEO** (Rank Math ou Yoast) — premier point du rapport d'audit, et le meilleur rapport gain/effort.

---

## En cas de problème

Retour arrière : restaurer les deux archives de la phase 0 par le gestionnaire de fichiers DirectAdmin, dans `wp-content/plugins/` et `wp-content/themes/`. La base n'ayant pas été touchée par le transfert, aucune donnée client n'est en jeu.
