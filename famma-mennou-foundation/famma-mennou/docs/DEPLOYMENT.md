# Déploiement — local → staging → production (§43)

> Rien n'a encore été déployé. Ce document décrit ce qu'il faudra faire, et
> surtout les pièges déjà rencontrés en local qui se reproduiront ailleurs.

## Ce qui est à nous, et ce qui ne l'est pas

Seuls trois éléments sont du code FAMMA :

| Élément | Chemin |
|---|---|
| Plugin métier | `wp-content/plugins/famma-core/` |
| Thème enfant | `wp-content/themes/famma-child/` |
| Extensions obligatoires | `wp-content/mu-plugins/` |

WordPress, WooCommerce, Kadence et TranslatePress sont installés à l'exécution
et **ne sont pas versionnés**. Une migration consiste donc à transporter la
base de données, les fichiers téléversés et ce dépôt — rien d'autre.

## Ce que la base de données contient d'indispensable

- Les commandes, en tables HPOS (`wc_orders*`), **pas** dans `posts`.
- Les **traductions TranslatePress**, dans ses propres tables
  `wp_trp_dictionary_*` et `wp_trp_gettext_*`. Les oublier revient à perdre
  tout le contenu arabe. Un `mysqldump` complet les emporte ; un export
  sélectif par table, non.
- Les réglages WooCommerce et les options `famma_*`.

## Variables d'environnement côté serveur

Le pont `.env` → constantes passe par `wp-content/mu-plugins/famma-env.php`,
qui lit `getenv()`. Sur un hébergement mutualisé sans variables
d'environnement, il faudra les définir autrement — par exemple en ajoutant les
`define()` correspondants dans `wp-config.php`, que le mu-plugin respectera
puisqu'il teste `defined()` avant d'écrire.

Variables à fournir : `FAMMA_WHATSAPP_NUMBER`, `FAMMA_META_PIXEL_ID`,
`FAMMA_META_ACCESS_TOKEN`, `FAMMA_META_TEST_EVENT_CODE`,
`FAMMA_TIKTOK_PIXEL_ID`, `FAMMA_GA4_MEASUREMENT_ID`,
`FAMMA_GOOGLE_SITE_VERIFICATION`, plus `WP_ENVIRONMENT_TYPE=production`.

## Pièges déjà rencontrés, qui se reproduiront

**L'image Docker ne met jamais à jour une installation existante.** Elle ne
copie WordPress que si `/var/www/html` est vide. Changer le tag de l'image sur
un volume déjà peuplé ne fait rien : il faut `wp core update`. Volume et image
peuvent donc diverger silencieusement.

**`WORDPRESS_CONFIG_EXTRA` n'agit qu'à la création de `wp-config.php`.** Tout
ce qu'on y ajoute après le premier démarrage n'atteint jamais PHP. C'est
précisément pourquoi la configuration passe par un mu-plugin.

**Le routage du courrier local doit rester local.** `famma-local-mail.php`
envoie tout vers Mailpit, mais se désactive seul dès que
`wp_get_environment_type()` ne vaut plus `local`. En production il faut donc
configurer un vrai service SMTP — sans quoi WordPress retombera sur
`sendmail`, absent de la plupart des conteneurs, et **aucun e-mail ne
partira** (l'échec est silencieux).

**`paid_statuses()` retire `processing` des statuts payés.** C'est correct
tant que le paiement à la livraison est le seul moyen de paiement. **Le jour
où une passerelle en ligne est ajoutée, cette ligne doit être revue** : une
commande prépayée est légitimement payée dès `processing`.

## Liste de contrôle avant mise en production

- [ ] Sauvegarde complète (`scripts/backup.sh`) **avant** toute opération (§36).
- [ ] `WP_DEBUG=0`, `WP_ENVIRONMENT_TYPE=production`.
- [ ] Les encarts « À compléter par le propriétaire » ont disparu des pages
      légales — ils sont visibles par les clients (§45).
- [ ] Conditions générales, retours et mentions légales rédigés et validés.
- [ ] Adresse réelle de la boutique renseignée dans WooCommerce.
- [ ] Numéro WhatsApp réel : le bouton et les CTA apparaissent alors seuls.
- [ ] Identifiants de tracking renseignés, et **le token Meta vérifié absent
      du HTML** (`curl … | grep -i token`).
- [ ] Certificat TLS actif, `WP_HOME` et `WP_SITEURL` en `https://`.
- [ ] Permaliens régénérés après migration.
- [ ] Au moins un vrai produit publié avant d'ajouter « Boutique » au menu.
- [ ] `bash scripts/lint.sh` vert, `debug.log` vide après un parcours complet.
- [ ] Une commande de bout en bout passée sur l'environnement cible, jusqu'au
      statut « livrée », en vérifiant que le stock décrémente une seule fois.

## Planifier les sauvegardes (§36)

`scripts/backup.sh` exporte la base et les téléversements, vérifie que
l'export n'est pas vide, signale l'absence des tables WooCommerce ou
TranslatePress, et supprime les sauvegardes de plus de 14 jours
(`FAMMA_BACKUP_KEEP_DAYS` pour changer ce seuil, `0` pour tout garder).

Il **échoue bruyamment** plutôt que d'écrire une archive vide : une
sauvegarde silencieusement corrompue est pire que pas de sauvegarde, puisque
l'on se croit couvert.

La planification est une action au niveau du système, à lancer par le
propriétaire — l'agent n'y a pas accès.

**Windows** — tâche quotidienne à 3 h du matin :

```
schtasks /create /sc daily /st 03:00 /tn "FAMMA sauvegarde" ^
  /tr "\"C:\Program Files\Git\bin\bash.exe\" -lc \"cd /e/Ecom/famma-mennou-foundation/famma-mennou && ./scripts/backup.sh\""
```

**Linux / serveur** — ligne de crontab :

```
0 3 * * * cd /srv/famma-mennou && ./scripts/backup.sh >> backups/cron.log 2>&1
```

Deux points à ne pas oublier : la sauvegarde n'a de valeur que **testée**
(restaurer un export dans une base jetable au moins une fois), et le dossier
`backups/` contient des données clients — il est ignoré par Git, et doit le
rester.

## Ce qui n'est pas prêt pour la production

- **Conversions API Meta** : l'action `famma_capi_purchase` se déclenche bien à
  la livraison avec son `event_id`, mais **aucun client HTTP ne l'écoute**.
  Aucun événement Purchase ne part côté serveur.
- **Consentement aux cookies** : aucune bannière. À arbitrer avant d'activer
  les outils de mesure.
- **Sauvegardes automatiques** : `scripts/backup.sh` est manuel. Aucune
  planification n'existe.
