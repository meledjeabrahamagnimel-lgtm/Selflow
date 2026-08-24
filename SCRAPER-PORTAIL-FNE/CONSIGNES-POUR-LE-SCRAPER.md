# Le scraper du portail FNE — ce que Selflow attend de lui

Ce dossier accueille le script qui relève le portail de la DGI. Selflow ne va
sur le portail nulle part : il **lit un dossier** et **publie une file**. Ce que
le scraper fait entre les deux — navigateur piloté, requêtes HTTP, saisie
manuelle — ne le regarde pas, et c'est délibéré : l'interface de la DGI change,
la conformité fiscale de Selflow ne doit pas changer avec elle.

Le contrat tient en deux gestes.

---

## 1. Lire la file — ce qu'il faut aller relever

```
php artisan portail-fne:demandes --json
```

Rend la liste des logins attendus, et rien d'autre :

```json
["1864699A", "2201455B"]
```

Une file vide rend `[]`. Ce n'est pas une erreur : « rien à relever » est une
réponse, et le scraper doit s'arrêter là.

Le **login est celui du portail**, en pratique le NCC de l'entreprise. C'est la
seule donnée que Selflow transmet au scraper : ni identifiant interne, ni nom
d'entreprise, ni numéro de facture.

Sans `--json`, la même commande rend un tableau lisible à l'écran, avec le motif
de chaque demande (« Rejet FA-0042 sur pointOfSale ») et sa date.

**Une demande apparaît dans la file quand la DGI refuse une pièce.** C'est le
seul moment où un relevé sert vraiment à quelque chose. Dix rejets d'affilée sur
le même point de vente n'ouvrent qu'une demande.

Rien n'interdit de relever aussi sans demande — un passage quotidien de nuit est
une bonne idée pour tenir les fiches à jour. La file dit ce qui est *urgent*,
pas ce qui est *permis*.

---

## 2. Déposer les fichiers

**Dossier de dépôt** — celui que désigne `PORTAIL_FNE_DOSSIER_IMPORT` dans
`.env`. Aujourd'hui, sur ce poste :

```
storage/app/portail-fne/
```

**Deux fichiers par entreprise**, nommés `<login>_<date>` :

| Fichier | Contenu |
|---|---|
| `1864699A_20260821.json` | la fiche de l'entreprise |
| `1864699A_20260821.xlsx` | les points de facturation, un par ligne |

Formats de date acceptés : `Ymd` (`20260821`), `Y-m-d`, `d-m-Y`, `dmY`.

**Un nom hors nomenclature est refusé et signalé, jamais rattaché au hasard.**
Ranger le relevé fiscal d'un client dans le dossier d'un autre ne se répare pas.

Le login peut contenir lui-même un tiret bas : la découpe se fait au **dernier**,
donc `LOGIN_CLIENT_20260821.json` désigne bien le login `LOGIN_CLIENT`.

### Le JSON de la fiche

Un objet plat, **clés en français, accents et apostrophes compris**, exactement
comme le portail les affiche. Les quatorze clés reconnues :

```json
{
  "Email": "it.dcknowing@gmail.com",
  "Téléphone": "2722421443",
  "Adresse": "8XVQ+29Q",
  "Commune": "COCODY",
  "Quartier": "RIVIERA II AFRICAINE",
  "Référence Cadastrale": "*",
  "IDU": "*",
  "Propriétaire du local professionnel de l'entreprise": null,
  "Sticker : solde d'alerte": "5000",
  "Références bancaires": null,
  "Timbre de quittance": true,
  "Bordereau d'achat de produits agricoles": true,
  "Pied de page des factures": null,
  "Factures autres mentions": null
}
```

Trois libertés, et une seule contrainte :

- **les valeurs peuvent être typées ou entre guillemets.** `"5000"` et `5000`
  arrivent tous deux dans un entier ; `true` et `"true"` dans un booléen ;
- **`"*"` et `null` sont compris comme « le portail n'a rien »** — ils ne
  comptent pas comme des écarts avec le paramétrage de Selflow ;
- **une clé inconnue n'est pas perdue.** Elle part dans `champs_inconnus` et un
  avertissement est journalisé. Le portail peut ajouter un champ du jour au
  lendemain ; il sera visible avant d'être exploité.

La contrainte : **ne pas renommer les clés.** La correspondance est explicite
dans `ImportPortailFneService::CHAMPS_FICHE`, précisément pour qu'un libellé qui
change casse bruyamment au lieu d'arriver en silence dans la mauvaise colonne.

### Le tableur des points de facturation

Une feuille, **une ligne d'en-tête**, puis une ligne par point :

| Nom | Outil | ID du terminal | Statut | Raison de statut | ID de l'établissement | Créé à | Mise à jour à |
|---|---|---|---|---|---|---|---|
| FACTURATION SIEGE | Application FNE | | 1 | | 42200613-f402-… | 2026-07-30T10:38:40.726Z | 2026-07-30T10:38:40.726Z |

**La lecture suit les en-têtes, jamais les positions** : les colonnes peuvent
être réordonnées sans rien casser. Les accents et la casse sont indifférents —
« ID de l'établissement » et « id de l etablissement » désignent la même
colonne. Les lignes entièrement vides sont ignorées.

`Statut` vaut `1` pour un point ouvert. Toute autre valeur est conservée telle
quelle et le point n'est pas tenu pour actif.

---

## Ce que le scraper n'a pas à faire

- **Ne rien déplacer, ne rien supprimer après coup.** C'est l'empreinte SHA-256
  du contenu qui tient lieu de marque de traitement : redéposer le même fichier
  est sans effet, et le dossier peut être relu en entier sans précaution.
- **Ne pas fermer les demandes.** Une demande passe à `servie` uniquement quand
  un fichier portant ce login est réellement rangé en base. Un scraper qui
  échoue en silence laisse donc sa demande ouverte — c'est voulu, et c'est le
  seul endroit où l'on verra qu'il ne fonctionne plus.
- **Ne toucher à aucune table.** Selflow lit le dossier tout seul, toutes les
  heures.

---

## Ce que Selflow fait de son côté, sans qu'on le lui demande

| Quand | Quoi |
|---|---|
| Toutes les heures | `portail-fne:importer` — range ce qui est dans le dossier |
| Toutes les heures, minute 10 | `fne:diagnostiquer-rejets` — rapproche les pièces refusées du dernier relevé |

Sortie des deux tâches : `storage/logs/portail-fne.log`.

Toutes les heures et non toutes les minutes : un relevé se produit au mieux une
fois par jour, et relire un dossier soixante fois par heure ne le fait pas
arriver plus tôt.

⚠️ **Ces tâches ne tournent que si `php artisan schedule:run` est appelé chaque
minute.** Sur ce poste, la tâche Windows « Selflow - planificateur » s'en
charge. En production (Linux), la ligne cron reste à poser :

```
* * * * * cd /chemin/selflow && php artisan schedule:run >> /dev/null 2>&1
```

---

## Ce qui ne se corrige jamais tout seul

Le rapprochement **montre** les écarts entre le portail et le paramétrage de
Selflow ; il n'en applique aucun. Trois des champs relevés —
`timbre_quittance`, `bapa`, `sticker_solde_alerte` — commandent le comportement
fiscal de l'application. Les recopier automatiquement ferait changer une facture
parce qu'un fichier est arrivé dans un dossier, sans que personne ne l'ait
décidé, et sur la foi d'un relevé dont rien ne garantit la fraîcheur.

C'est la règle d'or du projet, et elle vaut aussi pour le scraper : **il apporte
un constat, pas une décision.**

---

## Vérifier que tout est branché

```bash
# La file (vide au repos)
php artisan portail-fne:demandes

# Lire le dossier, ou un seul fichier
php artisan portail-fne:importer
php artisan portail-fne:importer --fichier="chemin/vers/1864699A_20260821.json"

# Rapprocher les rejets du dernier relevé
php artisan fne:diagnostiquer-rejets
```

Un fichier déjà lu ressort en « déjà lu » : c'est le signe que l'empreinte
fonctionne, pas une erreur.
