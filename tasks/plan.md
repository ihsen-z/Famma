# Plan de mise en œuvre : formulaire de commande express optimisé pour le navigateur de Facebook

Rédigé le 11/11/2026 sur la branche `claude/formulaire-express-fb`, partie de `origin/main` (3e34084, famma-core 0.3.8 en production).
Version visée : famma-core 0.3.9.

## Vue d'ensemble

Le formulaire express de la fiche produit n'a que trois champs (nom, téléphone, gouvernorat), plus la taille et la quantité. Pourtant, seules 33 % des personnes qui le commencent arrivent à une commande, contre environ 65 % dans une boutique moyenne.

Le blocage principal est le gouvernorat :
- c'est un menu où l'on tape le nom ;
- si le texte tapé n'est pas choisi dans la liste, l'envoi est refusé (« Pick your governorate from the list. ») ;
- 7 des 12 clics « Commander » sans commande venaient de là (rapport abandons du 09/11).

Second constat : 34 visiteurs ont tapé leur nom, mais seulement 17 leur téléphone.

Objectif : passer de 33 % à au moins 60 % des formulaires commencés qui deviennent des commandes, mesuré sur 7 jours après la mise en ligne.

## Décisions du propriétaire (11/11)

1. **Gouvernorat** : garder le menu à recherche, mais il ne doit plus jamais bloquer. Le nom tapé est reconnu à l'envoi, et les villes ou délégations mènent à leur gouvernorat.
2. **Ordre des champs** : téléphone en premier, puis nom, puis gouvernorat. Même si le client s'arrête, son numéro arrive dans « Saisies à relancer ».
3. **Phrase rassurante** sous le téléphone, en FR et en derja, modifiable dans FAMMA → Pages → Fiche produit. Si elle est vide, rien n'est affiché.
4. **Mise en ligne** dès que la correction est prête et testée, avec le feu vert explicite du propriétaire. Le verdict du 15/11 lira les chiffres avant et après, jour par jour.

## Décisions d'architecture

- **On ne touche pas aux protections contre les doubles commandes.** Le parcours « devis → envoi final → statut » (`prepare`, `submitFinal`, `sendFinal`, `recover`) et le registre en `sessionStorage` restent tels quels. Les corrections interviennent avant l'envoi : validation, menu, ordre des champs.
- **Rien de nouveau ne part vers Meta.** L'événement `form_error` reste dans le journal des visites (interne). Le Pixel et la CAPI ne changent pas.
- **Aucune valeur saisie dans les événements.** `form_error` ne transporte que des codes de champs, pris dans une liste blanche côté serveur : `phone`, `name`, `state`, `size`, `form`, `storage`, `network`, `server`, `changed`.
- **Compatibilité avec le cache.** LiteSpeed peut servir un ancien HTML avec le nouveau script, ou l'inverse. Le script doit donc fonctionner avec les deux gabarits, comme le correctif 0848b15 l'a déjà fait.
- **Tout texte visible passe par l'admin** (règle « tout paramétrable ») ou par `__()` avec le domaine `famma-core`, en FR et en derja, en propriétés CSS logiques pour l'arabe.
- **Données des délégations** : uniquement la nomenclature officielle (Institut National de la Statistique), source citée en tête du fichier, filtrable par `famma_governorate_places`. Les noms courants hors liste officielle (Djerba, El Mourouj…) sont soumis au propriétaire avant d'être ajoutés. Aucune donnée inventée.
- **Documentation à jour** : Context7 est consulté au début de chaque tâche de code (attributs `autocomplete` et `enterkeyhint` sur MDN, API WooCommerce utilisées). S'il est indisponible, on le dit avant de continuer.
- **Lecture du code par le graphe d'abord** : graphe de famma-core reconstruit le 11/11 (`graphify update`, 1 245 nœuds) ; à relancer après chaque tâche.

## Graphe des dépendances

```
T1 form_error (mesure) ──┬── T2 rapport « Formulaire » (admin)
                         ├── T3 gouvernorat non bloquant ── T4 villes/délégations
                         │            └── T5 téléphone en premier + saisie FB ── T6 phrase rassurante
                         └── T7 commande rattachée à sa visite
T1…T7 ── T8 version 0.3.9, traductions, contrôles, paquet de mise en ligne
```

T3, T4, T5 et T7 touchent tous `assets/js/express-order.js` : ils se font l'un après l'autre. T2 peut avancer en parallèle de T3, car il ne touche que l'admin.

## Liste des tâches

Le détail de chaque tâche (critères d'acceptation, vérification, fichiers) est dans `tasks/todo.md`.

### Phase 1 : mesurer les blocages
- [x] T1 : Enregistrer chaque envoi refusé et le champ en cause (`form_error`)
- [ ] T2 : Entonnoir du formulaire et blocages par champ dans le tableau de bord visiteurs

### Point de contrôle 1
- [ ] Tests Node et PHP verts, phpcs propre
- [ ] En local, un envoi sans gouvernorat apparaît dans le rapport avec le code `state`

### Phase 2 : corriger le formulaire
- [ ] T3 : Le gouvernorat tapé est reconnu à l'envoi et ne bloque plus
- [ ] T4 : Les villes et délégations mènent au bon gouvernorat
- [ ] T5 : Téléphone en premier et saisie adaptée au navigateur de Facebook
- [ ] T6 : Phrase rassurante sous le téléphone, modifiable dans l'admin

### Point de contrôle 2
- [ ] Parcours complet en local, en émulation mobile 390 px avec l'agent utilisateur du navigateur Facebook
- [ ] Revue du propriétaire : ordre des champs, texte de la phrase, noms courants des villes

### Phase 3 : fiabiliser la mesure et livrer
- [ ] T7 : Chaque commande express est rattachée à sa visite, même sans cookie
- [ ] T8 : Version 0.3.9, traductions, contrôles du projet et paquet de mise en ligne

### Point de contrôle 3 : prêt à mettre en ligne
- [ ] Tous les contrôles du § 11 de ClaudeMemo sont verts : `php -l`, phpcs WordPress, `WP_DEBUG` sans notice, parcours COD complet jusqu'à « Livrée », Purchase envoyé une seule fois
- [ ] Feu vert explicite du propriétaire, puis mise en ligne : SHA vérifiés, `~/.lsphp_restart.txt`, purge LiteSpeed
- [ ] Mesure à J+7 : formulaire commencé → commande ≥ 60 %, blocages `state` proches de 0, visite → commande ≥ 2,5 %

## Risques et parades

| Risque | Impact | Parade |
|---|---|---|
| Une erreur dans le menu empêche toute commande | Élevé | Le `<select>` natif reste dans la page ; tests Node sur chaque cas ; repli sur le select si le menu échoue |
| Le cache LiteSpeed sert un ancien HTML avec le nouveau script | Moyen | Script compatible avec les deux gabarits ; purge après la mise en ligne |
| OPcache garde l'ancien PHP après l'envoi | Moyen | Créer `~/.lsphp_restart.txt`, vérifier la version servie |
| Liste des délégations erronée ou inventée | Moyen | Nomenclature officielle de l'INS uniquement, source citée ; les noms courants sont validés par le propriétaire |
| La correction arrive en pleine campagne et brouille le verdict du 15/11 | Faible | Lecture des chiffres par date, avant et après la mise en ligne |
| Une double commande en cas de réseau lent | Élevé | Parcours d'envoi et registre inchangés ; tests d'intégration express rejoués |
| Un zip avec des entrées de dossier casse les droits en production | Élevé | Zip sans entrées de dossier, comme pour les lots précédents |
| Le test réel dans l'appli Facebook n'est possible qu'en production | Moyen | Émulation locale, puis une commande test sur le téléphone du propriétaire après la mise en ligne, annulée ensuite |

## Questions ouvertes

- **Texte de la phrase rassurante** : Claude propose une version FR et derja, le propriétaire la valide ou la réécrit dans l'admin.
- **Noms courants des villes** hors nomenclature officielle (Djerba, El Mourouj, Ennasr…) : liste proposée au point de contrôle 2, à valider.
- **Message d'erreur du gouvernorat** : aujourd'hui en anglais dans le code et traduit par le fichier `.po`. Il passera en FR + derja, comme les autres messages du formulaire.
