# Changelog — FAMMA Core

## 0.2.6 — 2026-10-13

### Meta Conversions API : meilleure correspondance (fbc / fbp)

- **Cookie `_fbc` créé dès l'arrivée** : quand l'URL contient `?fbclid=` et
  qu'aucun `_fbc` n'existe, un petit script pose `fb.1.<horodatage ms>.<fbclid>`
  pour 90 jours sur le domaine principal. Il ne dépend plus du chargement
  différé du Pixel Meta et fonctionne sur les pages servies par le cache
  LiteSpeed (aucun PHP n'y est exécuté).
- **Validation à la commande** : `_fbc` et `_fbp` ne sont enregistrés que s'ils
  respectent le format Meta (`fb.<chiffre>.<horodatage>.<valeur>[.<appendice>]`) ;
  l'IP est validée, le user agent assaini et borné à 512 caractères. Mêmes
  métadonnées qu'avant (`_famma_fbc`, `_famma_fbp`, `_famma_client_ip`,
  `_famma_client_ua`).
- **OrderPlaced et Purchase** lisent ces métadonnées (revalidées à l'envoi).
  Purchase, envoyé depuis l'admin, n'utilise jamais l'IP ni le navigateur de
  l'admin. Les commandes antérieures sans métadonnées partent sans ces champs.
- **Mode test** : constante `FAMMA_META_CAPI_TEST_MODE` (true) ou option
  `famma_meta_capi_test_mode` (1), éteint par défaut. Activé, les événements
  réels portent le `test_event_code` configuré (format `TEST…`) et un avis
  s'affiche dans l'admin.
- Inchangés : noms d'événements, moment d'envoi, `event_id`, reste de
  `user_data`, envoi non bloquant.
