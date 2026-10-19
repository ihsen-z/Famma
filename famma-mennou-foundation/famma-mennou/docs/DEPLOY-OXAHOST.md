# Manuel de déploiement — Oxahost (DirectAdmin) · fammaminnou.tn

Cible : **Oxahost**, plan *DirectAdmin Shared Hosting*, LiteSpeed, datacenter
Tunis. Domaine **`fammaminnou.tn`**.

Ce manuel est séquentiel. Chaque phase se termine par un contrôle : ne pas
passer à la suivante avant qu'il soit vert. L'ordre n'est pas arbitraire —
importer la base avant d'avoir réglé PHP, par exemple, produit des erreurs
qu'on impute ensuite au mauvais endroit.

Ce qui est **automatisé** vit dans `scripts/deploy/` ; ce qui est **manuel** est
détaillé ici. Rien dans ce dépôt ne contient d'identifiant.

---

## Phase 0 — Vérifier l'offre avant de construire dessus

Les paliers DirectAdmin d'Oxahost ne se valent pas : **SSH n'existe que sur les
paliers supérieurs**, et la fréquence des sauvegardes va d'hebdomadaire à temps
réel selon le plan. Ces deux points changent la suite du manuel.

Ouvrir un ticket au support et faire confirmer **par écrit** :

| # | Question | Valeur exigée | Si non |
|---|---|---|---|
| 1 | `max_input_vars` est-il modifiable jusqu'à **3000** ? | 3000 | **Bloquant.** Voir l'encadré |
| 2 | `memory_limit` peut-il monter à **256M** ? | 256M | Bloquant pour l'admin WooCommerce |
| 3 | **PHP 8.3** est-il disponible ? | 8.3 | Bloquant : le code est typé PHP 8.1+ |
| 4 | **Accès SSH** inclus sur mon palier ? Sur quel port ? | oui | Mode FTP dégradé (Phase 5) |
| 5 | **WP-CLI** installé ? | oui | Les scripts du dépôt se rejouent à la main |
| 6 | Fréquence **minimale du cron** ? | ≤ 5 min | Action Scheduler prend du retard |
| 7 | Les **connexions HTTPS sortantes** vers `graph.facebook.com` sont-elles autorisées ? | oui | Plus aucune conversion Meta serveur |
| 8 | **Taille maximale** de la base ? | non plafonnée, ou ≥ 2 Go | Boutique en panne à saturation |
| 9 | **Sauvegardes** : fréquence, rétention, restauration en libre-service ? | quotidienne, ≥ 14 j, self-service | Voir Phase 8 |
| 10 | Un **environnement de préproduction** est-il disponible ? | souhaitable | Tests en production, à éviter |

> **`max_input_vars`, le piège le plus coûteux.** La valeur par défaut de PHP
> est 1000. L'écran d'édition produit de WooCommerce la dépasse dès qu'un
> produit a des variations. Au-delà, PHP **tronque le formulaire en silence** :
> on enregistre un produit et une partie des variations a disparu, sans message
> d'erreur ni ligne de journal. Ce n'est pas un réglage de confort.

**Contrôle de fin de phase :** les 10 réponses sont écrites, et les questions
1 à 3 sont satisfaites.

---

## Phase 1 — Domaine et TLS

1. Dans DirectAdmin, vérifier que `fammaminnou.tn` pointe bien sur
   l'hébergement (zone DNS, enregistrement `A`).
2. Décider **une fois pour toutes** de la forme canonique : `fammaminnou.tn` ou
   `www.fammaminnou.tn`. Ce manuel retient **`https://fammaminnou.tn`**. Les
   deux doivent répondre, l'une redirigeant vers l'autre — deux formes servant
   le même contenu divisent le référencement et cassent les cookies de session
   WooCommerce.
3. Activer le **certificat SSL gratuit** (Let's Encrypt) sur le domaine et son
   `www`, puis la **redirection forcée HTTPS**.

**Contrôle :** `https://fammaminnou.tn` répond, le certificat est valide, et
`http://` redirige bien vers `https://`.

---

## Phase 2 — Runtime PHP

1. Dans DirectAdmin, sélectionner **PHP 8.3** pour le domaine.
2. Appliquer les surcharges du gabarit
   [`scripts/deploy/templates/.user.ini`](../scripts/deploy/templates/.user.ini) :
   `max_input_vars=3000`, `memory_limit=256M`, `upload_max_filesize=64M`,
   `post_max_size=64M`, `max_execution_time=300`, `display_errors=Off`.

   Si le panneau propose ces réglages (sélecteur PHP / *PHP Settings*),
   **préférer le panneau** : un réglage de panneau survit à une migration, un
   fichier peut être effacé par erreur. Sinon, déposer le `.user.ini` dans
   `~/domains/fammaminnou.tn/public_html/`.
3. Vérifier les extensions présentes : `mysqli`, `curl`, `mbstring`, `openssl`,
   `dom`, `zip`, `gd`, `exif`. Notre code n'utilise en propre que `hash`,
   `preg_*` et `json_*` — le reste est requis par WordPress lui-même.

**Contrôle :** l'outil *PHP Info* du panneau affiche `8.3`,
`max_input_vars = 3000` et `memory_limit = 256M`. `.user.ini` est mis en cache
environ 5 minutes : ne pas conclure trop vite.

---

## Phase 3 — WordPress, WooCommerce, thème, traductions

Ces composants **ne sont pas versionnés** : ils s'installent ici, sur le
serveur. Seuls trois répertoires nous appartiennent (Phase 5).

1. Créer la **base de données** et son utilisateur dans DirectAdmin. Mot de
   passe long et unique, conservé dans un gestionnaire de mots de passe.
2. Installer **WordPress** dans `~/domains/fammaminnou.tn/public_html/`.
   Ne laisser aucune installation de démonstration : le catalogue démarre vide.
3. Installer **WooCommerce**, le thème **Kadence** (parent) et
   **TranslatePress**. Versions de référence dans `PLUGIN-REGISTER.md`.
4. **Ne rien configurer** encore : la Phase 6 importe la base locale, qui
   écrase tous les réglages faits ici.

**Contrôle :** l'admin WordPress est accessible en HTTPS, les trois extensions
sont présentes.

---

## Phase 4 — `wp-config.php` de production

C'est la phase la plus risquée du déploiement, parce que son oubli **ne produit
aucune erreur**.

En local, `wp-content/mu-plugins/famma-env.php` lit les variables
d'environnement injectées par Docker via `getenv()`. **Un mutualisé n'en expose
aucune.** Le mu-plugin définit alors chaque constante à la chaîne vide, que la
classe `Config` interprète comme « fonctionnalité désactivée ». Résultat :
Pixel Meta, GA4, TikTok, bouton WhatsApp et coordonnées du pied de page
disparaissent, **sans erreur PHP ni ligne de journal**. Le site marche, il ne
mesure et ne convertit plus rien.

1. Ouvrir le `wp-config.php` du serveur.
2. Y insérer le contenu de
   [`scripts/deploy/templates/wp-config-production.sample.php`](../scripts/deploy/templates/wp-config-production.sample.php),
   **avant** la ligne `require_once ABSPATH . 'wp-settings.php';`. Après cette
   ligne, WordPress est déjà chargé et les constantes arriveraient trop tard.
3. Saisir les vraies valeurs. Une valeur laissée vide désactive proprement la
   fonctionnalité : c'est le comportement voulu tant que l'identifiant n'a pas
   été fourni (§60 — rien n'est inventé).
4. `FAMMA_META_ACCESS_TOKEN` est le seul secret véritable de la liste. Il ne
   sort jamais du serveur : le plugin le place dans le **corps** de la requête
   vers `graph.facebook.com`, jamais dans l'URL — une URL finit dans les
   journaux d'accès, un corps de requête non.
5. Vider `FAMMA_META_TEST_EVENT_CODE` une fois la validation Meta faite :
   laissé en place, il marque les vraies conversions comme des tests.

**Contrôle :** le site répond toujours (une erreur de syntaxe ici produit une
page blanche) et l'admin est accessible.

---

## Phase 5 — Transfert du code

Trois répertoires, et rien d'autre : `wp-content/plugins/famma-core`,
`wp-content/themes/famma-child`, `wp-content/mu-plugins`.

```bash
cp scripts/deploy/.env.deploy.example scripts/deploy/.env.deploy
```

Remplir `.env.deploy` — il est ignoré par Git. Le chemin suit la convention
DirectAdmin : `/home/<user>/domains/fammaminnou.tn/public_html`. **Le vérifier
dans le gestionnaire de fichiers du panneau avant le premier transfert** : un
mauvais chemin écrit à côté sans rien signaler.

Simuler d'abord — rien n'est écrit :

```bash
bash scripts/deploy/deploy.sh --dry-run
```

Lire la liste des fichiers annoncés. Si elle contient autre chose que nos trois
répertoires, s'arrêter et corriger `DEPLOY_WP_PATH`. Puis transférer :

```bash
bash scripts/deploy/deploy.sh
```

`--delete` est volontairement limité à l'intérieur de nos trois dossiers : un
fichier supprimé en local disparaît là-bas, mais WooCommerce, Kadence et
TranslatePress, installés en Phase 3, ne sont jamais touchés.

**Sans SSH**, mettre `DEPLOY_MODE=ftp` et renseigner `DEPLOY_FTP_PASSWORD`. Le
transfert passe alors par `lftp` en **FTPS** — jamais en FTP clair, qui
transporterait le mot de passe en clair sur le réseau.

### Trois modes, et lequel choisir

| Mode | Outil requis | Incrémentiel | Supprime à distance |
|---|---|---|---|
| `ssh` | `rsync` + `ssh` | oui | oui, borné à nos trois dossiers |
| `tar` | `tar` + `ssh` | non | **non** |
| `ftp` | `lftp` | oui | oui |

`ssh` est le mode à préférer. Mais **la machine de développement Windows ne
fournit ni `rsync` ni `lftp`** — seuls `ssh` et `tar` sont présents. D'où le
mode `tar`, qui fonctionne d'emblée sans rien installer.

Sa contrepartie est réelle et doit être connue : il **ne supprime rien** à
distance. Un fichier retiré du dépôt survivra en production et continuera d'y
être chargé. Tant qu'on ajoute et modifie, c'est sans conséquence ; le jour où
l'on supprime un fichier, il faut l'effacer à la main sur le serveur, ou
installer `rsync` et passer en mode `ssh`.

**Contrôle :** dans l'admin, le thème *FAMMA Child* et l'extension *FAMMA Core*
apparaissent et sont activables.

---

## Phase 6 — Migration de la base

C'est ici que voyagent les commandes, les réglages, et **tout le contenu
arabe**.

```bash
bash scripts/deploy/export-db.sh
```

Le script sauvegarde d'abord la base locale, exporte **toutes** les tables, et
réécrit les URL `http://localhost:8080` → `https://fammaminnou.tn`. Il vérifie
la présence des tables HPOS (`wc_orders*`) et TranslatePress, puis génère un
`IMPORTER.md` à suivre.

Deux raisons de ne pas faire ça à la main avec `mysqldump` + `sed` :

- **Les URL sont dans des données sérialisées.** Les options WordPress stockent
  des tableaux PHP où la longueur de chaque chaîne est inscrite. Un `sed`
  change le texte sans corriger la longueur déclarée : l'option devient
  illisible et le réglage est perdu en silence. `wp search-replace --precise`
  désérialise, remplace, resérialise.
- **Toutes les tables sont nécessaires.** Les commandes vivent en HPOS, pas
  dans `posts`. Les traductions arabes vivent dans les tables propres à
  TranslatePress. C'est du travail humain — §5 interdit la traduction
  automatique — donc perdu = à réécrire entièrement.

> **Avant d'importer :** si la boutique a déjà reçu de vraies commandes,
> **ne pas importer**. Un import écrase la base et n'est pas réversible : il
> détruirait ces commandes. Dans ce cas, ne migrer que le code (Phase 5).

Après l'import, **régénérer les permaliens** (`wp rewrite flush`, ou
*Réglages > Permaliens*). Sans ce geste, `/boutique/` et `/panier/` répondent
404 : les règles de réécriture vivent en base.

**Contrôle :** la page d'accueil et `/boutique/` s'affichent, et `/ar/` rend
bien la version arabe en RTL.

---

## Phase 7 — Courrier sortant

`famma-local-mail.php` route tout le courrier vers Mailpit et **se désactive
seul** dès que l'environnement n'est plus `local`. WordPress retombe alors sur
`sendmail`, souvent absent — et **l'échec est silencieux**. Sans cette phase,
aucun client ne reçoit sa confirmation de commande.

1. Créer une adresse e-mail sur le domaine (`contact@fammaminnou.tn`) dans
   DirectAdmin.
2. Configurer l'envoi en **SMTP authentifié** avec les paramètres fournis par
   Oxahost, port 465 (SSL) ou 587 (TLS).
3. Renseigner cette adresse dans WooCommerce comme expéditeur.

**Contrôle :** passer une commande de test et **vérifier qu'un e-mail arrive
réellement** dans une boîte externe (Gmail, etc.) — pas seulement qu'aucune
erreur ne s'affiche.

---

## Phase 8 — Sauvegardes

Point rassurant, à cadrer précisément : **le code n'est pas en danger.** Il est
dans Git, il pèse 736 Ko, il tient en trois répertoires. Serveur détruit, il se
redéploie avec `deploy.sh`.

Ce qui est **irremplaçable** et n'existe qu'en base ou sur disque : les
commandes clients, le contenu arabe, les photos produits, les réglages
WooCommerce.

1. Vérifier ce que couvre la sauvegarde d'Oxahost (JetBackup) : fréquence,
   rétention, **et la possibilité de restaurer soi-même** sans passer par le
   support. Une sauvegarde que seul le support peut restaurer, un dimanche, ne
   vaut rien.
2. Mettre en place **une sauvegarde propre, en plus** : déposer
   [`scripts/deploy/templates/backup-remote.sh`](../scripts/deploy/templates/backup-remote.sh)
   sur le serveur, ajuster `WP_PATH`, et le planifier dans
   *DirectAdmin > Cron Jobs*.
3. **Rapatrier ces archives hors du serveur** régulièrement. Le datacenter
   d'Oxahost est unique, à Tunis : une sauvegarde stockée sur la machine
   qu'elle protège ne protège de rien.
4. **Tester une restauration au moins une fois**, dans une base jetable. Non
   testée, une sauvegarde est une hypothèse — et on se croit couvert.

**Contrôle :** une restauration a réussi au moins une fois.

---

## Phase 9 — Vérification finale

```bash
bash scripts/deploy/verify.sh
```

Le script contrôle sur le site réel : les 5 pages publiques en 200, les 4
redirections d'anciennes URL anglaises, `dir="rtl"` et `lang="ar"` sur `/ar/`,
**l'absence du token Meta dans le HTML**, la présence des scripts de mesure
(c'est le détecteur du piège de la Phase 4), les polices `.woff2` servies,
l'absence de contenu mixte et d'URL `localhost` résiduelles.

Puis, à la main — aucun script ne peut le remplacer :

- [ ] Une commande passée **de bout en bout** jusqu'au statut « livrée ».
- [ ] Le stock décrémente **une seule fois**.
- [ ] La commande est marquée **payée uniquement à « livrée »**, jamais avant.
- [ ] L'e-mail de confirmation arrive réellement.
- [ ] Les encarts « À compléter par le propriétaire » ont disparu des pages
      légales — ils sont visibles par les clients.
- [ ] Conditions générales, retours et mentions légales rédigés et validés.
- [ ] Adresse réelle de la boutique renseignée dans WooCommerce.
- [ ] Au moins un vrai produit publié avant d'ajouter « Boutique » au menu.
- [ ] Sur mobile : le CTA de commande est visible et atteignable.

---

## Déploiements suivants

Une seule commande — préflight qualité, transfert, vérification :

```bash
bash scripts/deploy/deploy.sh
```

`deploy.sh` **ne touche jamais à la base de production.** Écraser une base est
irréversible : la migration de base reste un geste séparé et délibéré.

### Retour arrière de famma-core 0.3.2 vers 0.3.1 : le jeton CAPI d'abord

La 0.3.2 chiffre en base le jeton CAPI saisi dans « Mesure ». La 0.3.1 ne sait
pas le déchiffrer : remettre ses fichiers sans autre geste lui ferait envoyer
une valeur chiffrée à Meta, qui refuserait tous les OrderPlaced et Purchase,
**sans erreur ni alerte**. Avant d'extraire l'archive de retour arrière :

1. FAMMA → Mesure → « Retour à la version 0.3.1 » → **Remettre le jeton en
   clair** (ou, avec WP-CLI : `wp eval 'var_export(
   Famma\Core\Capi_Token_Vault::unseal() );'`, qui doit afficher `true`).
2. Extraire l'archive de la 0.3.1, puis vérifier dans « Mesure » (0.3.1) que
   les 4 derniers caractères affichés sont ceux du vrai jeton.
3. Si l'étape 1 a été oubliée : ressaisir le jeton dans « Mesure », ou le
   poser en constante `FAMMA_META_ACCESS_TOKEN` (lue en priorité par les deux
   versions, jamais chiffrée).

Détails : `wp-content/plugins/famma-core/CHANGELOG.md`, section 0.3.2.

---

## Deux réglages à ne pas oublier après la mise en ligne

**Cache LiteSpeed.** LiteSpeed est un vrai atout de cette offre, mais le panier
et la commande **ne doivent jamais être mis en cache** : un panier servi depuis
le cache pourrait montrer à un client le panier d'un autre. WooCommerce et
LSCache gèrent normalement ces exclusions — le vérifier explicitement en
ajoutant un produit au panier depuis deux navigateurs différents.

**Cloudflare (gratuit), optionnel.** Le datacenter est déjà à Tunis : le gain
de latence est donc faible ici, contrairement au cas d'un hébergeur européen.
Ce qui reste utile : la protection DDoS et la mise en cache des ressources
statiques. À arbitrer, pas indispensable.

---

## Annexe API — migration sans SSH

Le panneau expose l'**API JSON DirectAdmin**, documentée dans
*Dashboard > API Documentation*. Elle change la donne sur un point : elle
permet de faire les phases 1, 3 et 6 **sans accès SSH**, ce qui lève
l'incertitude sur le palier souscrit.

Correspondance entre les phases du manuel et les endpoints :

| Phase | Endpoint |
|---|---|
| 1 — TLS | `POST /api/domain-tls/{domain}/provision-certs` (et `-dry-run`) |
| 3 — base de données | `POST /api/db-manage/create-db-with-user` |
| 3 — WordPress | `POST /api/wordpress/install` |
| 5 — code | `POST /api/filemanager-actions/upload` + `extract-archive` |
| 6 — base | `POST /api/db-manage/databases/{database}/import` |
| 7 — e-mail | section *Email* de l'API |
| 8 — sauvegarde | `GET /api/db-manage/databases/{database}/export` |

Ce que l'API **ne couvre pas** : les réglages PHP (`max_input_vars`,
`memory_limit`, version), les tâches cron, et le test des connexions
sortantes. Ces points restent manuels — voir la Phase 0 et Phase 2.

### Authentification

**Toujours une *login key*, jamais le mot de passe du compte.** Une login key
se révoque en un clic, se limite à une liste de commandes, et peut être
restreinte à une adresse IP et à une date d'expiration. Un mot de passe de
compte n'offre aucune de ces trois protections.

La créer dans le panneau (*Login Keys*), la restreindre aux seules commandes
`wordpress`, `db-manage`, `filemanager`, `domain-tls` — liste exacte via
`GET /api/login-keys/commands` — puis la renseigner dans
`scripts/deploy/.env.deploy`, qui est ignoré par Git.

Elle ne doit apparaître nulle part ailleurs : ni dans un commit, ni dans un
ticket de support, ni dans une conversation. En cas de fuite, la révoquer
avant toute autre action.

### Diagnostic

```bash
bash scripts/deploy/api/doctor.sh
```

**Lecture seule** — uniquement des requêtes `GET`. Il ne crée, ne modifie et
ne supprime rien, et peut être relancé autant de fois que nécessaire.

Il vérifie par les faits : la login key est acceptée, le rôle du compte, les
commandes autorisées par la clé, le serveur de base, la disponibilité de
l'endpoint WordPress, l'existence de `public_html`, les certificats, les
quotas. Et il **liste explicitement ce qu'il ne peut pas vérifier**, plutôt que
de laisser croire à une couverture complète.

Le lancer **avant** de construire quoi que ce soit : découvrir qu'une commande
est refusée après avoir créé la base coûte un nettoyage manuel.
