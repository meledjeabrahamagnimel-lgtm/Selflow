# Selflow — journal de bord

**Ce fichier est la mémoire du projet.** Une session d'assistant qui démarre à
froid n'a pas l'historique de nos conversations : elle a ce dépôt, `CLAUDE.md`,
et ce fichier. Tout ce qui a été décidé, tout ce qui a été écarté et pourquoi,
tout ce qui reste à faire doit donc figurer ici — et y être tenu à jour à chaque
lot terminé.

Dernière mise à jour : 8 août 2026 — lots 4.1 et 4.2 : l’imputation se lit sur
le rayon, et le stock prend enfin une valeur.

---

## 1. Les deux applications

| | Rôle | Dépôt |
|---|---|---|
| **Selflow** | Gestion commerciale : ventes, achats, stocks, points de vente, certification FNE auprès de la DGI. Produit les écritures comptables. | `meledjeabrahamagnimel-lgtm/Selflow` |
| **Comptaflow** | Application comptable complète : balance, grand livre, états SYSCOHADA, analytique. Reçoit les écritures de Selflow. | `guysergekouassi/COMPTAFLOW` |

Selflow écrit, Comptaflow exploite. Un client sans abonnement Comptaflow doit
malgré tout disposer d'une balance de contrôle dans Selflow — décidé, à faire.

---

## 2. La règle d'or : la conformité FNE est gelée

Voir `CLAUDE.md`, qui fait foi. En résumé : ne plus toucher aux payloads, aux
champs `fne_*`, au barème du timbre, au code QR, aux blocs de certification des
documents imprimés ni à `FnePayloadTest`. Trois exceptions seulement — nouvelle
version du référentiel DGI, rejet prouvé par le journal, demande explicite du
propriétaire.

Seul point de contact autorisé : l'écriture comptable du BAPA, en lecture seule
de ce que la plateforme a déjà retourné.

### La seule modification autorisée à ce jour — quantités décimales

**Exception 3 : demande explicite du propriétaire, le 8 août 2026**, après
vérification du référentiel technique.

`FneService` transmettait les quantités par `intval($detail->quantite)`, à six
endroits. Depuis le lot 3.1 les lignes portent des décimales : une vente de
12,5 kg de cacao partait certifiée pour **12 kg**, et la pièce certifiée
divergeait de la pièce établie dans Selflow, sans que rien ne le signale.

`CONFIGURATION FNE/Procedure_technique_integration_API.txt` est formel — le
champ est un **`number`**, dans les trois tableaux de champs :

| Endpoint | Ligne | Déclaration |
|---|---|---|
| Certification de facture | 272 | `quantity \| number \| Quantité \| O` |
| Reçu normalisé | 508 | `quantity \| number \| Quantité \| O` |
| Avoir / remboursement | 684 | `quantity \| number \| La quantité de l'article à retourner \| Y` |

Un `number` JSON n'est pas un entier. `FneService::quantiteFne()` arrondit
désormais au millième — la précision de la colonne `decimal(15,3)`, car
transmettre plus de décimales que la base n'en garde rendrait la pièce certifiée
irreproductible depuis nos données.

**La forme sur le fil ne change pas pour ce qui passait déjà** : `json_encode`
écrit `30` et non `30.0` pour un flottant sans partie décimale. Les pièces déjà
certifiées partent à l'identique ; seul ce qui était tronqué change.

Trois tests le verrouillent dans `FnePayloadTest` — une quantité fractionnée,
une quantité entière (dont la forme ne doit pas bouger), et l'alignement sur les
trois décimales.

---

## 3. Décisions arrêtées

Elles ne se rediscutent pas sans raison neuve.

| Sujet | Décision |
|---|---|
| Inventaire | **Permanent**, valorisé au **CUMP** (Coût Unitaire Moyen Pondéré) |
| Sortie de stock | **À la livraison**. La vente au comptant émet une livraison implicite immédiate |
| Données existantes | **Supprimées** — le développement repart de zéro, aucune donnée réelle |
| Numérotation des comptes | **Six chiffres** pour toute la partie locale (`7011` → `701100`) |
| Rôle du superadmin | **Active tout par défaut**, quel que soit l'abonnement. L'utilisateur choisit ensuite ce qu'il veut voir |
| Comptes, produits, journaux | **Livrés par défaut** avec l'application, indépendamment de Comptaflow, tous archivables |
| Journaux de trésorerie | Nommés librement : MTN, Orange, Caisse, ou le nom de la banque |
| Identifiants dans l'URL | **Masqués** par un identifiant public opaque — après les contrôles d'appartenance, jamais avant |
| États comptables | Comptaflow. Selflow garde une balance de contrôle |
| Souscription | Parcours qui se déplie étape par étape, **sans intervention du superadmin** |
| Choix hors référentiel | Un champ **« Autre »** à saisie libre partout où l'utilisateur choisit dans une liste fermée |
| Sigle CUMP | Toujours écrit **CUMP (Coût Unitaire Moyen Pondéré)**, définition entre parenthèses, à chaque occurrence — code, commentaires, journal, échanges |
| Secrets | Aucun mot de passe ni clé dans le code versionné. Ils viennent de l'environnement, ou sont tirés au hasard et affichés une seule fois |

---

## 4. Le référentiel de préparamétrage

Source : `sellflow_parametrage_activites_1.xlsx` (version 1.2, 31/07/2026).

Cinq niveaux, du plus large au plus fin :

| Niveau | Nombre | Rôle |
|---|---|---|
| **Catégorie** | 12 | Regroupement d'affichage. C'est l'équivalent de l'ancien « secteur d'activité ». Ne décide de rien |
| **Profil** | 71 | Le métier réel. Ce que l'utilisateur choisit ; décide des modules à ouvrir. **Niveau neuf** |
| **Famille** | 197 | Le rayon. **Porte les quatre comptes** : vente, achat, stock, variation |
| **Article** | 616 | Le produit. Hérite des comptes de sa famille |
| **Type d'article** | 10 | Transversal. **Détermine les comptes** ; la famille subdivise la racine qu'il impose |

Les 71 profils ne produisent que **cinq combinaisons de modules** :

| Combinaison | Profils |
|---|---|
| Stock seul | 31 |
| Stock + Production | 15 |
| Aucun module | 10 |
| Stock + Cycles agricoles | 8 |
| Stock + Chantiers | 7 |

---

## 5. État des lots

### Lot 0 — Fermer les portes ouvertes — **TERMINÉ**

Poussé sur **Selflow**, fusionné dans `main`.

- Contrôle du propriétaire sur `VenteControleur::normaliser()` — c'était la seule
  action du projet qui chargeait une pièce sans vérifier à qui elle appartient.
- **34 règles `exists:` cloisonnées** par `App\Modules\Admin\Regles\Appartenance`,
  dans 12 contrôleurs. Une table dont le rattachement n'est pas déclaré lève une
  exception plutôt que de laisser passer.
- Secret de synchronisation : plus de valeur de repli dans le code,
  comparaison par `hash_equals`. **`EXTERNAL_SYNC_SECRET` doit être posé dans
  `.env`**, sinon la synchronisation refuse tout appel.
- Neuf `addslashes` remplacés par `@js()`.
- `php artisan verifier:variables` — détecte les variables lues sans avoir été
  écrites, la faute que PHP ne révèle qu'au clic de l'utilisateur.
- `tests/Feature/CloisonnementTest.php` — 7 tests.

### Lot 1 — Le référentiel en base — **TERMINÉ**

Poussé sur **Selflow**. Ce qui est fait :

- Six tables `referentiel_*` : catégories, profils, types d'article, familles,
  articles, comptes. Elles portent le catalogue livré avec l'application et ne
  contiennent aucune donnée client.
- Le classeur converti en JSON versionné dans `database/data/referentiel/`. Le
  JSON est au dépôt, pas le tableur : un fichier de tableur ne se relit pas dans
  une revue de code et ne se compare pas d'une version à l'autre.
- `ReferentielSeeder`, idempotent — il peut tourner à chaque déploiement et à
  chaque nouvelle version du classeur sans rien dupliquer.
- Conversion des racines SYSCOHADA vers six chiffres : `701` → `701000`,
  `7011` → `701100`, `60311` → `603110`. Elle colle à la convention déjà en
  place dans `config/selflow.php` (`411000`, `601000`).
- `tests/Feature/ReferentielTest.php` — 10 tests, dont la vérification que les
  cinq combinaisons de modules couvrent bien les 71 profils.

- Le **plan comptable OHADA complet** — 1 256 comptes, 9 classes — chargé comme
  dictionnaire depuis l'acte uniforme. Il ne remplit pas le plan des
  entreprises : il sert à nommer. Sans lui, la famille « Vivres » imputait en
  `701100` sans que rien ne nomme ce compte, et la balance à venir aurait été
  illisible. `Compte::nommer()` résout un numéro absent par sa racine la plus
  longue.

- La numérotation des comptes du référentiel **corrigée et alignée sur l'acte
  uniforme** — voir la section 5 bis.

- Le **trousseau de départ** d'une entreprise : `TrousseauEntrepriseService`
  pose les 34 comptes communs et 9 journaux dès la création, sur les quatre
  chemins qui créent une entreprise — inscription, Google, superadmin,
  synchronisation Comptaflow. Sans lui, la première vente s'imputait sur des
  comptes inventés à la volée.

  **Les dix journaux livrés :**

  | Code | Type | Compte | Intitulé | |
  |---|---|---|---|---|
  | `VTE` | Vente | — | Journal des ventes | |
  | `ACH` | Achat | — | Journal des achats | |
  | `OD` | OD | — | Opérations diverses | |
  | `RAN` | RAN | — | Report à nouveau | *système* |
  | `CAI` | Caisse | `571000` | Caisse | |
  | `BQE` | Banque | `521000` | Banque | |
  | `MTN` | Banque | `521500` | MTN Mobile Money | |
  | `OMY` | Banque | `521600` | Orange Money | |
  | `MOOV` | Banque | `521700` | Moov Money | |
  | `WAVE` | Banque | `521800` | Wave | |

  Seuls les journaux de trésorerie portent un compte général : `VTE`, `ACH`,
  `OD` et `RAN` n'en ont aucun, et la colonne est devenue facultative pour
  cela. La caisse pointe sur `571000` et la banque sur `521000` — deux racines
  de même rang ; `521100` aurait désigné « Banque X », une des places que
  l'acte uniforme réserve aux banques de l'entreprise.

  Le report à nouveau est marqué **`systeme`** : la clôture l'écrit, personne
  ne le saisit. Il disparaît des listes de saisie — portée `saisissables()` —
  et reste visible en consultation, le grand livre devant afficher les
  écritures de clôture.

  Le mobile money est rangé en subdivision de `521` « Banques locales », à
  partir de `521500` : c'est un avoir détenu chez un établissement agréé, pas
  de l'espèce en caisse. L'acte uniforme est antérieur à ces moyens de paiement
  et n'en prévoit aucun compte ; `5211` à `5214` restent libres pour les
  banques de l'entreprise.

  Rien n'écrase ce que l'utilisateur a modifié, et **ce qui ne sert pas
  s'archive** — `archive_le` sur `plan_comptable` et `codes_journaux`, avec les
  portées `actifs()` et `archives()`. Un compte supprimé après avoir servi
  laisserait des écritures orphelines.

- `tests/Feature/TrousseauEntrepriseTest.php` — 10 tests.

- L'**écran superadmin de consultation** : `/superadmin/referentiel`. Il montre
  les types d'article et leurs comptes, les dix journaux livrés, et les profils
  filtrables par catégorie ; le détail d'un profil liste ses familles avec leurs
  quatre comptes et ses articles types. Consultation seule — le référentiel se
  modifie dans le classeur puis se recharge par le seeder.

**Lot 1 terminé.** Les produits par défaut viendront avec la souscription d'un
profil (lot 2), puisqu'ils en dépendent.

### Lot 2 — Le parcours de souscription — **TERMINÉ**

Poussé sur **Selflow**.

- Table `entreprise_profils` : une entreprise souscrit à un ou plusieurs
  profils, et l'on garde trace de ce que chaque souscription a créé.
- **Droits et préférences séparés** : `modules_autorises` (superadmin, tout
  ouvert par défaut) et `modules_actifs` (choix de l'utilisateur). Un module
  n'est actif que s'il est aussi autorisé. Personne ne savait jusqu'ici si un
  module absent venait d'un abonnement restreint ou d'une préférence.
- `souscription_etape` et `souscription_terminee_le` : le parcours se quitte
  et se reprend.
- `activite_autre` : ce que l'utilisateur saisit quand aucun profil ne lui
  convient, plutôt que d'être forcé dans une case. Alimentera les versions
  suivantes du classeur.
- `SouscriptionProfilService::souscrire()` copie chez l'entreprise les familles
  (en `categories`), les articles (en `produits`) et les quatre comptes de
  chaque famille (au `plan_comptable`), puis ouvre les modules du profil dans
  la limite des droits. Le trousseau est posé au passage s'il ne l'était pas.
- `TypeArticle::typeProduit()` traduit les dix natures comptables du référentiel
  vers les six types du catalogue. Travaux, services, sous-traitance et
  financements se rejoignent sous « service » : aucun ne se stocke.
- `tests/Feature/SouscriptionProfilTest.php` — 12 tests.

Trois garanties tenues par des tests : une activité mixte cumule sans doubler
(ni catégorie, ni préfixe en collision), ce que l'utilisateur a modifié survit
à une nouvelle souscription, et un code de profil inconnu est refusé sans rien
créer — les codes viennent d'un formulaire.

- **Le parcours de configuration** : `/admin/souscription`, cinq étapes qui se
  déplient — domaine, métier, modules, rayons, prix. Chacune fait quelque chose ;
  je n'en ai pas gardé sept, trois se réduisaient à de la consultation que les
  écrans ordinaires assurent déjà.

  | Étape | Ce qu'elle fait |
  |---|---|
  | 1 · Domaine | Restreint la liste suivante |
  | 2 · Métier | Choix multiple — activité mixte — ou champ **« Autre »** |
  | 3 · Modules | Ce que les métiers ouvrent, décochable |
  | 4 · Rayons | Tous cochés ; décocher retire les articles et les comptes |
  | 5 · Prix et stock | Renommer, fixer les montants que le classeur laisse vides, et compter l'inventaire d'ouverture |

  La souscription s'effectue à l'étape 4 : tout ce qui précède n'est qu'un choix.
  L'étape atteinte est retenue sur l'entreprise, pas en session — un changement
  de poste ne fait pas tout recommencer. Le parcours vit hors du groupe
  « modules » : une entreprise qui n'a rien configuré n'a aucun module actif, et
  le middleware la rejetterait de son propre écran de configuration.

- **Le stock d'ouverture, à l'étape 5** : une colonne de plus, qui reçoit les
  quantités comptées le jour du démarrage sur le site actif. Elle n'apparaît que
  si les trois conditions sont réunies — module `stock` ouvert, un point de vente
  existant, et au moins un article qui se compte. Sans site, le champ n'aurait
  nulle part où écrire et avalerait la saisie en silence ; pour un cabinet
  comptable, dont tous les articles sont des missions, ce serait une colonne de
  tirets. Le formulaire et l'enregistrement lisent la même méthode
  (`siteDuStock()`) : la colonne affichée est exactement celle qui sera écrite.
  Un identifiant de site resté en session après un changement de compte est
  vérifié contre l'entreprise avant d'être retenu.

- `tests/Feature/ParcoursSouscriptionTest.php` — 16 tests.

**Sécurité du parcours**, chaque point tenu par un test :

- une étape non atteinte est refusée — sans quoi un formulaire forgé sauterait
  le choix des métiers et souscrirait à des familles qui n'appartiennent à
  aucun d'eux ;
- un module non autorisé ne s'active pas en l'ajoutant à la requête ;
- fixer le prix d'un produit d'une autre entreprise est refusé par
  `Appartenance`, avec la vérification de propriétaire en second rideau.

- **La bannière de configuration** : une entreprise qui n'a pas fait son
  parcours part d'une page blanche. Le tableau de bord le dit, et annonce le
  point que l'utilisateur doit connaître avant sa première facture — le
  catalogue arrive rempli, mais **sans prix**, eux seuls variant selon la zone
  et la période. Elle disparaît une fois la configuration faite, et ne
  s'affiche pas pendant le parcours lui-même.

- **La visite guidée de première utilisation** : une main désigne l'élément,
  une bulle explique à quoi il sert. Six étapes — configuration, catalogue,
  vente, clients, FNE, paramètres. Elle se retient **par utilisateur et en
  base** : un vendeur qui rejoint une entreprise déjà configurée la voit à son
  tour, et changer de poste ne la fait pas recommencer.

  Les cibles sont des attributs `data-visite` posés exprès dans le gabarit :
  une classe CSS change au gré des retouches, un repère posé pour cela ne bouge
  pas. Une étape dont la cible est absente — un module fermé — est sautée.
  `Échap` ferme, et `prefers-reduced-motion` coupe les animations.

  Elle se relance depuis le menu du profil — **« Revoir la visite guidée »** —
  par un formulaire ordinaire et non par un appel JavaScript : une visite doit
  pouvoir se rouvrir même quand un script a échoué, ce qui est précisément le
  moment où l'utilisateur en a besoin. La route répond en JSON à un appel de
  script, et par un retour au tableau de bord sinon.

- `tests/Feature/VisiteGuideeTest.php` — 11 tests.

- **Les unités restent à saisie libre**, avec des suggestions. Un `datalist`
  (`admin::partials.unites-suggerees`) et non une liste fermée : le tâcheron
  facture au « voyage », le vétérinaire à la « tête », l'école à l'« élève », et
  le premier conditionnement absent d'une liste fermée bloquerait la fiche. Les
  unités déjà employées par l'entreprise passent en tête, suivies de
  `Produit::UNITES_COURANTES` ; la comparaison ignore la casse, ce qui évite
  d'avoir « pièce », « piece » et « Pièce » comme trois unités distinctes.

**Deux défauts trouvés et corrigés en cours de lot :**

- une ligne de l'étape 5 sans clé `nom` — corps de requête partiel, et non le
  formulaire — produisait un 500 sur `$ligne['nom']`. Chaque clé est désormais
  facultative, une valeur absente laissant l'existante en place ;
- `Produit::stockSur()` lit la relation déjà chargée quand elle l'est : sans
  cela, un catalogue de deux cents lignes déclenchait deux cents requêtes à
  l'affichage de l'étape 5.

### Lot 3 — Le stock suit l'événement — **TERMINÉ**

| Étape | État |
|---|---|
| 3.1 · Quantités décimales de bout en bout | **TERMINÉ** |
| 3.2 · `StockService` unique, journal en écriture seule | **TERMINÉ** |
| 3.3 · Rebrancher les appelants, fermer la double porte d'achat | **TERMINÉ** |
| 3.4 · Sort de la marchandise sur les avoirs | **TERMINÉ** |
| 3.5 · Module d'inventaire physique | **TERMINÉ** |
| 3.6 · Engagements calculés, plus stockés | **TERMINÉ** |

#### 3.1 — Les quantités cessent d'être entières

`quantite_disponible` était un `integer`. Le référentiel livre pourtant des
kilos, des litres et des mètres carrés : 12,5 kg de cacao entraient en stock
pour 12, sans erreur ni trace. Au bout d'un an de réceptions, l'écart entre le
stock théorique et le comptage physique n'avait plus d'explication.

La correction porte sur **toute la chaîne**, pas seulement sur la fiche de
stock — une quantité tronquée sur la ligne de vente l'est déjà avant d'atteindre
le stock :

| Niveau | Ce qui a changé |
|---|---|
| Base | Six tables passées en `decimal(15,3)` : `stocks`, `mouvements_stock`, `vente_details`, `achat_details`, `produits`, `transferts_stock` |
| Modèles | Casts en `float`, `Produit::stockActuel()` et ses voisines retypées `int` → `float` |
| Contrôleurs | Quatre `intval` / `(int)` qui tronquaient à la lecture ou à l'écriture |
| Validation | `App\Modules\Admin\Regles\Quantite`, énoncée une fois pour onze contrôleurs |
| Navigateur | `parseInt` → `quantiteSaisie()`, et `step="0.001"` sur onze champs |

**Trois décimales**, et pas davantage : le gramme sur le kilo, le millilitre sur
le litre. Deux colonnes gardent leurs quatre décimales,
`fiche_technique_details.quantite` et `ordres_production.quantite_cible` : la
première n'est pas une quantité mais un coefficient de nomenclature — 0,0125 kg
de colorant par unité produite — et arrondir un coefficient fausse toute la
série qu'il multiplie.

`Quantite::physique()` refuse le zéro, le négatif et la quatrième décimale.
Refuser plutôt qu'arrondir : la colonne n'en garde que trois, et un arrondi
silencieux est précisément ce que ce lot corrige. Le sens d'un mouvement est
porté par son type — jamais par le signe de sa quantité.

**Un défaut de saisie trouvé au passage** : les champs de quantité des avoirs
portaient `min="0.01"` sans `step`. En HTML, la base du pas est `min` : les
seules valeurs acceptées étaient 0,01 / 1,01 / 2,01… et « 12 » était refusé par
le navigateur, sans message compréhensible.

**Un point signalé et laissé au propriétaire** : `FneService` transmet
`intval($detail->quantite)`. Voir la section 2 — code gelé.

- `tests/Feature/QuantitesDecimalesTest.php` — 15 tests.

#### 3.2 — Une seule porte, un journal qui ne s'efface pas

Le couple « modifier la fiche, puis écrire le mouvement » était recopié dans
plus de douze endroits, sur sept contrôleurs et deux contrôleurs d'API. Trois
conséquences, toutes constatées dans le dépôt :

- **rien ne garantissait la paire.** Une exception entre les deux gestes
  laissait un stock modifié sans trace, ou une trace sans stock ; plusieurs de
  ces blocs n'étaient même pas dans une transaction ;
- **`stock_avant` était lu sans verrou.** Deux ventes simultanées sur le même
  article lisaient la même valeur, écrivaient chacune leur `stock_apres`, et le
  journal annonçait un stock que la fiche démentait ;
- **les libellés divergeaient.** Six formes différentes pour quatre notions.

`App\Modules\Admin\Services\StockService` rend les trois gestes indissociables —
verrouiller sous `lockForUpdate`, modifier la fiche, journaliser — et les motifs
deviennent des constantes de `MouvementStock`.

| Méthode | Ce qu'elle fait |
|---|---|
| `entree` / `sortie` | Le geste unique, avec son motif |
| `transferer` | Les deux jambes, verrous pris dans un ordre stable pour éviter l'interblocage |
| `inventorier` | L'écart d'un comptage physique, dans le sens qu'il faut ; rien si le comptage est conforme |
| `contrePasser` | L'écriture de sens inverse, qui désigne celle qu'elle annule |
| `contrePasserLaPiece` | Toutes celles d'une vente ou d'un achat |
| `disponible` | La quantité lue **sous le verrou** qui servira à l'écriture |

**Le journal ne s'efface pas.** `MouvementStock` refuse la suppression et la
modification des colonnes qui font foi ; `reference_document` reste modifiable,
c'est un libellé d'affichage. Contre-passer deux fois le même mouvement est
refusé : cela fabriquerait de la marchandise qui n'a jamais existé.

Une pièce se rattache désormais vraiment à ses mouvements
(`piece_type` / `piece_id`). `reference_document` est une chaîne libre :
renuméroter une facture rompait le lien sans que rien ne le signale.

- `tests/Feature/StockServiceTest.php` — 20 tests.

#### 3.3 — Les douze copies disparaissent

Sept contrôleurs et deux contrôleurs d'API passent par le service. Il ne reste
aucun `MouvementStock::create` hors du service, et `Produit::incrementStock()` /
`decrementStock()` sont marquées `@deprecated` — elles ne subsistent que pour le
jeu de données de démonstration.

**La double porte de l'achat est fermée.** `AchatControleur` incrémentait le
stock à la facture mais ne marquait pas la ligne reçue : la commande restait
dans la file « Réceptions à traiter », et valider la réception incrémentait
**une seconde fois**. Rien ne l'interdisait. La facture vaut désormais réception
immédiate — `quantite_receptionnee` est posée — exactement comme la vente au
comptant vaut livraison immédiate.

**La modification d'une vente contre-passe au lieu d'effacer.**
`VenteControleur` faisait
`MouvementStock::where('reference_document', ...)->delete()` : le stock revenait
juste, mais la sortie de dix sacs disparaissait du journal, et avec elle toute
chance d'expliquer un écart d'inventaire six mois plus tard.

**Quatre motifs inventés ne s'affichaient nulle part.** `B2bControleur`
écrivait `vente` et `achat`, `BonLivraisonControleur` n'écrivait aucun
sous-type, `ProductionControleur` écrivait `Entree` sans accent. L'écran des
mouvements compare la chaîne exacte : ces lignes existaient, tombaient dans le
défaut, s'affichaient en gris — et une entrée de production apparaissait en
rouge, précédée d'un signe moins.

#### 3.4 — Le sort de la marchandise rendue

Le choix existait déjà à l'écran (`reinject` / `scrap` / `none`), mais il était
consigné dans une clé **`notes` que `mouvements_stock` n'a pas** : Eloquent la
laissait tomber sans rien dire. Et le rebut écrivait une entrée fantôme —
quantité N, `stock_avant` 0, `stock_apres` 0 — qui ne changeait rien mais se
lisait comme une entrée. Un retour vendable et un retour défectueux étaient donc
**indiscernables**, et le rebut invisible.

| Choix | Ce qui s'écrit |
|---|---|
| Revient vendable | Une entrée, motif `retour_client` |
| Revient inutilisable | Une entrée `retour_client`, **puis** une sortie `rebut` |
| N'est pas revenue | Rien : geste commercial, la marchandise est restée chez le client |

Deux écritures pour le rebut plutôt qu'une seule : le stock ne bouge pas au
total, mais les deux faits sont vrais, et le rebut apparaît là où on le cherche.
C'est aussi ce qui permettra de l'imputer en perte au lot 4.

Côté achat, `rendreAuFournisseur()` fait le symétrique. Le mot `reinject` y
désigne l'inverse de ce qu'il désigne côté vente — une **sortie** de stock ; le
nom vient de l'écran des avoirs de vente et prête à confusion, il est documenté
là où il est lu.

- `tests/Feature/CycleStockTest.php` — 9 tests, qui passent par les routes et
  non par le service : les défauts corrigés ici n'étaient pas dans le service,
  ils étaient dans la façon dont douze endroits l'imitaient chacun à sa manière.

#### 3.5 — L'inventaire physique

Le stock théorique est ce que l'application croit ; l'inventaire est ce que l'on
trouve dans le magasin. L'écart existe toujours — casse non déclarée, erreur de
saisie, vol — et il n'avait aucun moyen d'entrer dans Selflow : la seule
correction possible était de passer un rebut, ce qui ne sait pas dire « il y en
a **plus** que prévu ».

`/admin/stock/inventaire` : un site, la liste des articles qui se comptent, le
théorique en regard, un champ de saisie, et l'écart affiché à la frappe — c'est
le moment où l'on peut encore recompter.

Deux règles portées par le code, pas par la consigne :

- **un champ vide veut dire « pas compté », pas « zéro »**. Confondre les deux
  viderait le magasin de tout ce que l'inventaire n'a pas parcouru ;
- **un comptage à zéro reste légitime** : un rayon vide se compte. C'est pour
  cela que le calcul d'écart n'emploie pas `quantiteSaisie()`, qui ramène toute
  valeur nulle au plus petit pas.

#### 3.6 — Les engagements cessent d'être des compteurs

`produits.quantite_commandee` et `quantite_a_receptionner` existaient depuis
l'origine, s'affichaient sur trois écrans, entraient dans le prévisionnel — et
**rien ne les écrivait jamais**. Seul le jeu de démonstration les posait, à
zéro. Le prévisionnel valait donc toujours le stock disponible, et la colonne
« Commandé » d'un magasin qui attend trente sacs affichait 0.

Deux défauts, pas un : personne ne les alimentait, et **elles étaient sur
`produits`, donc globales** — trente sacs commandés à Abidjan seraient apparus
comme engagés à Bouaké.

La correction n'est pas de les écrire partout où il aurait fallu : un compteur
dénormalisé doit être incrémenté à la commande, décrémenté à la livraison,
corrigé à l'annulation, à la modification et à l'avoir — cinq occasions de
dériver. Les colonnes sont supprimées ; l'engagement se déduit des lignes qui
l'ont créé, par site. Une valeur déduite ne dérive pas.

Un devis n'engage rien : c'est une proposition. Seul le bon de commande engage.

#### Simulation d'attaques — `tests/Feature/AttaquesStockTest.php`

16 tentatives, du point de vue d'un utilisateur **légitime** d'une entreprise A
qui forge des requêtes contre l'entreprise B. C'est la surface réelle : un
formulaire ne protège rien, il ne fait que suggérer.

| Tentative | Résultat |
|---|---|
| Inventorier le dépôt du voisin (`point_de_vente_id` forgé) | refusé par `Appartenance` |
| Compter un article du voisin sur son propre site | ignoré, aucun mouvement |
| Lire le stock du voisin par `?point_de_vente_id=` | site non retenu, page vide |
| Mettre au rebut l'article du voisin | refusé |
| Rebut de quantité négative — une entrée déguisée | refusé |
| Quantité à seize chiffres — erreur SQL et page 500 | refusé avant la base |
| `1' OR '1'='1` dans une quantité | refusé |
| Supprimer une ligne du journal | refusé par le modèle |
| Réécrire la quantité d'un mouvement | refusé par le modèle |
| Écrans de stock sans connexion / module fermé | redirection / 403 |
| Nom d'article contenant `<script>` | échappé |
| Ranger son article dans le rayon du voisin | **faille trouvée et corrigée** |

**La faille trouvée** : `ProduitApiControleur` validait `categorie_id` et
`sous_categorie_id` par un `exists:` **sans cloisonnement**. Un appelant de
l'API pouvait rattacher son article à un rayon du concurrent, dont le nom
ressortait ensuite dans ses propres écrans. `Appartenance` reçoit un troisième
mode, `RATTACHEMENT_PAR_CATEGORIE` : `sous_categories` ne porte pas
`entreprise_id`, elle pend à une catégorie qui en porte un.

#### Simulation d'attaques — élévation de privilèges et vol d'accès

`tests/Feature/AttaquesElevationTest.php` — 25 tentatives. C'est l'attaque qui
compte le plus : les autres sont bornées, elles atteignent une donnée. Un compte
`superadmin` volé atteint **toutes les entreprises de la plateforme** — leurs
chiffres d'affaires, leurs clients, leurs clés FNE.

Le principe éprouvé : **rien de ce qui décide des droits ne vient de la
requête.** Le rôle, l'entreprise d'appartenance, les habilitations et le jeton
d'API sont décidés par le serveur.

| Tentative | Résultat |
|---|---|
| Poster `role: superadmin` sur « Mon profil » | ignoré — le contrôleur construit un tableau explicite |
| Poster `entreprise_id` sur « Mon profil » | ignoré |
| Se poser un `jeton_api` choisi | ignoré |
| Créer un compte `superadmin` depuis la gestion du personnel | refusé par `Rule::in` |
| Promouvoir un employé en `superadmin` | refusé |
| Créer un employé chez le voisin (`entreprise_id` forgé) | le serveur impose l'entreprise |
| Modifier l'employé d'une autre entreprise | 403 |
| Atteindre les 4 écrans de la plateforme en tant qu'admin | 302 / 403 |
| Idem sans être connecté | redirection |
| Gestion du personnel en tant que caissier | 302 / 403 |
| Jeton d'API affiché dans une liste | absent |
| Mot de passe haché affiché | absent |
| Jeton inventé sur l'API | 401 |
| Jeton d'autrui : voir au-delà de son entreprise | cloisonné |
| `' OR '1'='1`, `admin@…' --`, `' OR 1=1 #` à la connexion | aucune session |
| Poser `role` en session | sans effet — le rôle se lit en base |
| L'adresse en dur `superadmin@gmail.com` avec un rôle d'admin | refusée |

**La faille la plus grave du dépôt, trouvée et corrigée.** Trois seeders
créaient le compte de la plateforme avec un **mot de passe en clair dans le code
versionné** — `12345678SUPER@` — et `doit_changer_password => false` : rien
n'obligeait jamais à le changer. `SeedMassiveCommand` allait jusqu'à afficher un
avertissement disant que le mot de passe était public, sans rien y changer.

Quiconque avait lu le dépôt disposait d'un accès superadmin sur tout
déploiement ayant exécuté un seeder. Les comptes de démonstration
(`ADMIN@@@###123`, `Caissier@2025`) avaient le même défaut.

Désormais : les mots de passe viennent de l'environnement, ou sont **tirés au
hasard et affichés une seule fois**, à l'exécution. Tous partent avec
`doit_changer_password => true`.

| Variable | Rôle | Défaut si absente |
|---|---|---|
| `SUPERADMIN_EMAIL` | Adresse du compte de la plateforme | `superadmin@gmail.com` |
| `SUPERADMIN_PASSWORD` | Son mot de passe | tiré au hasard, affiché une fois |
| `DEMO_PASSWORD` | Comptes de démonstration | tiré au hasard, affiché une fois |
| `EXTERNAL_SYNC_SECRET` | Passerelle Comptaflow | **aucun** — la synchronisation refuse tout |

**Un point noté, non corrigé** : `VerifierHabilitationRoute` court-circuite tout
contrôle d'habilitation pour l'adresse `superadmin@gmail.com`, écrite en dur.
Ce n'est pas exploitable — le middleware `role:superadmin` passe avant, et un
test le vérifie — mais une identité en dur reste une clé publiée. À remplacer
par un drapeau sur le compte quand l'occasion se présentera.

**Une limite documentée plutôt que tue** : `MouvementStock::where(...)->delete()`
court-circuite les événements Eloquent — le garde-fou du modèle ne s'y applique
pas. Aucun code du dépôt ne le fait plus, et le seul appelant qui le faisait a
été corrigé. Une contrainte de base de données serait le cran suivant, si le
besoin s'en fait sentir. Le test le constate au lieu de le passer sous silence.

### Lot 4 — Les imputations — **EN COURS**

| Étape | État |
|---|---|
| 4.1 · Chaîne d'imputation article → rayon → défaut | **TERMINÉ** |
| 4.2 · CUMP (Coût Unitaire Moyen Pondéré) et inventaire permanent | **TERMINÉ** |
| 4.3 · Règlements et lettrage | à faire |
| 4.4 · BAPA sans TVA déductible | à faire |
| 4.5 · Balance de contrôle dans Selflow | à faire |

#### 4.1 — L'imputation se lit sur le rayon

La question « sur quel compte s'impute cet article » se résolvait par une paire,
recopiée à chaque endroit :

    $detail->produit?->compte_vente ?? config('…vente_defaut')

Deux niveaux là où le référentiel en prévoit trois, et le plus utile — **le
rayon** — sautait. `SouscriptionProfilService` copiait en effet les comptes de
la famille **sur chaque produit**, et la catégorie de l'entreprise n'en gardait
aucun. Trois conséquences :

- un article créé à la main après la souscription n'héritait de rien et tombait
  sur le compte générique `701000` : la balance d'un magasin qui a soigneusement
  réparti ses rayons n'avait **qu'une seule ligne de ventes** ;
- changer le compte d'un rayon obligeait à rouvrir chacun de ses articles ;
- le lien entre le rayon et son imputation — la règle métier — ne figurait plus
  nulle part une fois la copie faite.

Les quatre comptes rejoignent `categories`, et `ImputationService` résout la
chaîne :

| Rang | Source | Ce que cela veut dire |
|---|---|---|
| 1 | `produits.compte_*` | L'exception que l'utilisateur assume, article par article |
| 2 | `categories.compte_*` | Le rayon — la règle métier, celle du référentiel |
| 3 | `config('selflow.plan_comptable_defaut')` | Le filet, quand rien n'est renseigné |

**Le stock n'a pas de filet** : il n'existe pas de « compte de stock générique »
qui voudrait dire quelque chose. Les marchandises vont en 31, les matières en
32, les produits finis en 36 ; les confondre rendrait le bilan **faux** plutôt
qu'imprécis. Un article sans compte de stock ne produit donc pas d'écriture
d'inventaire, et `manqueUnCompte()` permet aux écrans de le dire — un article
mal imputé ne se voit pas avant la balance, et à ce moment-là le mois est passé.

- `tests/Feature/ImputationTest.php` — 12 tests.

#### 4.2 — Le stock prend une valeur

`produits.prix_achat` tenait lieu de coût : un prix de catalogue, figé, saisi
une fois. **La marge affichée était fausse** dès que le fournisseur changeait
ses prix — un sac acheté 12 000 puis 15 000 restait valorisé au prix de la
fiche, et la vente à 17 000 annonçait une marge qu'on n'avait pas faite. Et le
bilan ne pouvait pas être établi : valoriser un stock demande un coût, pas un
prix indicatif.

Le **CUMP** (Coût Unitaire Moyen Pondéré) se recalcule à chaque entrée :

    CUMP = (Q_ancienne × CUMP_ancien + Q_entrée × coût_entrée) ÷ (Q_ancienne + Q_entrée)

À la sortie, rien ne bouge : une sortie **consomme** le coût moyen, elle ne le
déplace pas. C'est la définition du procédé, et ce qui le rend indépendant de
l'ordre des ventes.

Il est porté par la **fiche de stock** — le couple article / site — et non par
l'article : le même sac de riz arrive à des coûts différents à Abidjan et à
Bouaké, transport compris. Un CUMP (Coût Unitaire Moyen Pondéré) global
mélangerait les deux et fausserait les deux. Quatre décimales, une de plus que
les quantités : le coût unitaire d'un article vendu au gramme se joue au centime
du millier.

Le coût d'entrée est le **prix réellement payé**, remise de ligne déduite, et
non le prix de catalogue. Trois situations demandent une décision :

| Situation | Décision | Pourquoi |
|---|---|---|
| Entrée sans coût connu — transfert, retour, écart d'inventaire | On reprend le CUMP en place | La marchandise n'a pas changé de valeur en changeant de main |
| Première entrée sur une fiche vide, sans coût | Repli sur `prix_achat` | Faute de mieux, mais mieux que zéro |
| Stock nul ou négatif après l'entrée | Le coût de l'entrée devient le CUMP | La moyenne pondérée n'a plus de sens sur une quantité qui ne peut pas la porter |

**Les écritures d'inventaire permanent.** Aucun compte de classe 3 n'était
mouvementé — ni 31, ni 32, ni 36, ni les variations 603 et 736. Le stock
existait en quantité, pas en valeur.

| Mouvement | Débit | Crédit |
|---|---|---|
| **Entrée** — le stock grossit | Compte de stock (3x) | Compte de variation (603x, 736) |
| **Sortie** — le stock diminue | Compte de variation | Compte de stock |

L'écriture n'est pas une *conséquence* du mouvement : en inventaire permanent,
elle en **fait partie**. Elle se déclenche donc depuis `StockService`, la porte
unique du lot 3, et non depuis les huit endroits qui déplacent de la
marchandise. Le libellé porte le motif — « Mise au rebut — Riz sac 25 kg » —
pour qu'on retrouve au grand livre la raison du mouvement sans revenir au
journal de stock.

Un ajustement d'inventaire n'a pas d'autre pièce que lui-même : il porte alors
sa propre référence, `MVT-{id}`. La colonne n'accepte pas le vide, et une
écriture sans référence serait introuvable au grand livre.

- `tests/Feature/InventairePermanentTest.php` — 16 tests.

### Lot 5 — La passerelle Comptaflow — les deux dépôts

Concordance des exercices, déversement dans l'exercice qui contient la date,
axe « Points de vente » et sections automatiques, rejeu idempotent, balance de
contrôle dans Selflow.

### Lot 6 — Les manques métier

Devis B2B opposable, nomenclature de production, lots et péremption,
immobilisations, emballages consignés, modèles d'importation complets.

### Lot 7 — La vitrine

Landing page, documentation, politique, présentation de DC-Knowing et de ses
produits, écran superadmin de gestion de la vitrine.

### Lot 8 — Stabilisation

Identifiants opaques dans les URL, habilitations, limitation de débit, un
scénario de bout en bout par combinaison de modules.

---

## 5 bis. La numérotation des comptes — tranché

Le classeur subdivisait certaines racines sur des positions que l'acte uniforme
réserve à un autre usage. Le relevé du plan OHADA a permis de distinguer trois
situations, et deux seulement demandaient correction.

**Ce qui était juste et n'a pas bougé — les stocks.** Sur `31`, `32`, `33` et
`36`, l'acte uniforme prescrit lui-même une ventilation par famille : `311`
« Marchandises A », `312` « Marchandises B ». Le classeur y range Vivres,
Boissons, Hygiène : c'est exactement l'usage prévu. Les comptes de variation
`6031x` suivent les stocks, et `6031` n'a aucune subdivision prescrite.

**Ce qui était faux — les ventes et les achats.** Sur `701`, `702`, `705`, `706`,
`601` et `602`, la quatrième position porte la ventilation *géographique* :

| Compte | Acte uniforme | Le classeur y mettait |
|---|---|---|
| `7011` | Dans la Région | Vivres et alimentation |
| `7012` | Hors Région | Boissons |
| `7013` | Aux entreprises du groupe dans la Région | Hygiène et entretien |
| `6011` | Achats dans la Région | Vivres et alimentation |

Une entreprise ainsi paramétrée n'aurait plus pu produire la ventilation
attendue par la liasse fiscale — celle que Comptaflow établit. **Décision : les
ventes et les achats sont ramenés sur leur racine** (`701000`, `601000`,
`706000`). Le grand livre porte la nature de l'opération ; le détail par famille
reste dans Selflow, et l'analytique le portera jusqu'à Comptaflow. C'est aussi
ce que font en pratique les entreprises ivoiriennes, qui imputent à plat sur
`701`.

**Ce qui était inversé — les produits accessoires.** Sur `707`, l'acte uniforme
prescrit des natures, et le classeur les avait interverties :

| Famille | Le classeur | Corrigé en | Acte uniforme |
|---|---|---|---|
| Livraison facturée | `7072` | `707100` | Ports, emballages perdus et autres frais facturés |
| Commissions dépôt-vente | `7071` | `707200` | Commissions et courtages |

276 imputations corrigées au total, dans le JSON du référentiel. Trois tests
verrouillent les trois situations.

---

## 6. Anomalies constatées et non encore corrigées

Elles sont documentées pour ne pas être redécouvertes.

### Stock

- ~~`quantite_disponible` est un `integer` — le référentiel livre des kg,
  litres, sacs. 12,5 kg de cacao deviennent 12.~~ **CORRIGÉ au lot 3.1** — voir
  le lot 3 pour le détail de la chaîne.
- ~~Deux portes pour la même entrée : `AchatControleur` incrémente à
  l'enregistrement de la facture, `StockControleur` incrémente à la réception.
  Rien n'interdit les deux.~~ **CORRIGÉ au lot 3.3** : la facture pose
  `quantite_receptionnee`, la ligne quitte la file des réceptions.
- ~~`VenteControleur:698` **supprime** les mouvements de stock à la
  modification d'une vente. Un journal se contre-passe, il ne s'efface pas.~~
  **CORRIGÉ au lot 3.3** : `StockService::contrePasserLaPiece()`, et le modèle
  refuse désormais toute suppression.
- ~~Le couple « décrémenter puis écrire le mouvement » est recopié dans plus
  de douze endroits, sur sept contrôleurs et deux contrôleurs d'API.~~
  **CORRIGÉ au lot 3.2/3.3** : `StockService` est la seule porte.
- ~~`reference_document` est une chaîne libre, pas une relation.~~
  **CORRIGÉ au lot 3.2** : couple `piece_type` / `piece_id`, la chaîne restant
  pour l'affichage.
- ~~Une **fiche de stock est créée pour tous les types de produits**, y compris
  les services, avec un minimum de 5 : un service apparaît en permanence dans
  les alertes de rupture.~~ **CORRIGÉ au lot 2** : `ProduitControleur` sort
  avant la boucle et `PointDeVenteControleur` saute l'article si
  `estStockable()` est faux. Même règle dans `poserStockInitial()`. Pour un
  cabinet comptable, dont tous les articles sont des missions, le tableau de
  bord n'annonçait jusqu'ici que des ruptures sur des choses qui ne s'épuisent
  pas.
- ~~Les avoirs réinjectent le stock automatiquement, sans jamais demander si
  la marchandise revient vendable, en rebut, ou pas du tout.~~ **CORRIGÉ au
  lot 3.4** — le choix existait à l'écran mais s'écrivait dans une colonne
  inexistante ; voir le lot 3.4.

### Comptabilité

- ~~Aucun compte de stock (31, 32, 33, 36) ni de variation (6031, 6032, 6033,
  736) n'est mouvementé.~~ **CORRIGÉ au lot 4.2** : `InventairePermanentService`,
  appelé depuis la porte unique du stock.
- ~~Pas de coût de revient : `produits.prix_achat` est un prix catalogue
  figé.~~ **CORRIGÉ au lot 4.2** : CUMP (Coût Unitaire Moyen Pondéré) par fiche
  de stock, alimenté par le prix réellement payé.

### Passerelle Comptaflow

- `deverserEcritures` prend l'exercice **actif** de Comptaflow sans jamais le
  comparer à la date de l'écriture.
- `linkCompany` ne vérifie aucune concordance d'exercice à la liaison.
- L'analytique n'est **jamais** alimentée : zéro occurrence d'`AxeAnalytique` ou
  de `SectionAnalytique` dans le contrôleur de synchronisation, alors que
  Comptaflow possède tout le module.
- **Quatre champs manquent au déversement**, alors que Selflow les possède :

  | Colonne Comptaflow | Reçoit aujourd'hui | Devrait recevoir |
  |---|---|---|
  | `n_saisie` | la référence de pièce, sinon `'SELF_' . time()` | `operations.numero_saisie`, unique par entreprise |
  | `plan_tiers_id` | deviné en cherchant le compte dans le plan tiers | `ecritures_comptables.compte_tiers` |
  | `plan_analytique` | jamais renseigné (booléen) | le drapeau, avec le point de vente |
  | *(analytique)* | rien | `ecritures_comptables.point_de_vente_id` |

  Le repli `'SELF_' . time()` est aussi ce qui interdit toute idempotence : deux
  déversements de la même écriture produisent deux numéros de saisie différents.
- Un journal inconnu se replie sur le premier de la liste : une vente peut
  atterrir dans le journal des achats, silencieusement.
- Un compte absent est créé avec `type_de_compte => 'actif'` en dur et le libellé
  de l'écriture pour intitulé — cela pollue le plan comptable de Comptaflow.
- Aucune idempotence : rejouer une synchronisation redéverse les mêmes écritures.

### Gestionnaire d'exceptions — **CORRIGÉ**

Le gestionnaire interceptait **toutes** les exceptions et renvoyait la page
« 500 — panne détectée » pour chacune. En production, cela voulait dire qu'une
adresse mal tapée, un accès refusé, une session expirée — et surtout **un
formulaire mal rempli, dont la saisie était perdue** — affichaient une panne.
Chacune déclenchait en prime un courriel d'alerte à deux adresses : un robot
cherchant `/wp-admin` inondait la boîte aux lettres.

`App\Exceptions\Panne::estUne()` fait le tri : seules les erreurs serveur
méritent la page de panne. Découvert en écrivant les tests de l'écran
superadmin, qui recevaient 500 là où ils attendaient 403 et 404.

### Comptes de produit — **CORRIGÉ**

`produits.compte_vente` et `compte_achat` étaient obligatoires, avec pour
valeurs par défaut `701100` et `601100` — précisément les positions que l'acte
uniforme réserve à la ventilation géographique. Tout produit créé sans compte
explicite atterrissait donc en « Dans la Région ». Et l'obligation forçait à
inventer un compte pour une matière première, qui ne se vend pas.

Les deux colonnes sont devenues facultatives, sans valeur par défaut.

Au passage : `Produit` n'avait aucune relation vers sa catégorie, et une
colonne `categorie` — l'ancien libellé libre — masquait toute relation
homonyme. D'où `categorieRelation()`.

### Importations

Les modèles d'importation CSV ne couvrent que cinq types — points de vente,
clients, fournisseurs, utilisateurs, produits — et leurs colonnes sont
incomplètes au regard des champs réellement gérés. Manquent notamment les
articles avec leur famille et leurs comptes, les stocks initiaux, le plan
comptable, les codes journaux, et les soldes d'ouverture.

### B2B

Le flux va de la demande de prix à la proposition, puis crée directement une
vente et un achat. **L'étape du devis n'existe pas** : ni pièce opposable, ni
validité, ni acceptation, ni renégociation.

---

## 7. Ce qui est sain, vérifié

Pour ne pas re-auditer inutilement :

- Aucune interpolation de variable dans du SQL brut.
- Aucun modèle avec `$guarded = []`.
- `$request->all()` n'apparaît qu'à l'intérieur d'un validateur.
- Les routes superadmin sont derrière `role:superadmin`.
- Le B2B vérifie systématiquement que la négociation concerne l'entreprise.
- La connexion est limitée en tentatives, sur le web comme sur l'API.
- La normalisation n'apparaît que sur les écrans de factures ; devis, commandes
  et bons de livraison n'en portent pas, et le serveur refuse toute pièce dont
  l'étape n'est pas « Facture ».
- La double normalisation est bloquée des deux côtés.
- L'état `normalise` est porté par la pièce : l'admin voit partout ce qu'un
  responsable a certifié dans son point de vente.

---

## 8. Conventions de travail

- Développement sur `claude/selflow-remises-taxes-dgi-a5je2b`, puis fusion dans
  `main` à chaque lot.
- Toujours préciser **sur quel dépôt** un envoi a été fait — Selflow ou
  Comptaflow.
- `php artisan test` et `php artisan verifier:variables` avant chaque envoi.
- Les commentaires de code expliquent **pourquoi**, en rapportant ce qui avait
  échoué — pas ce que le code fait, qui se lit.
