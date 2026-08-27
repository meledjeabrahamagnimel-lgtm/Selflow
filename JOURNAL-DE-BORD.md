# Selflow — journal de bord

**Ce fichier est la mémoire du projet.** Une session d'assistant qui démarre à
froid n'a pas l'historique de nos conversations : elle a ce dépôt, `CLAUDE.md`,
et ce fichier. Tout ce qui a été décidé, tout ce qui a été écarté et pourquoi,
tout ce qui reste à faire doit donc figurer ici — et y être tenu à jour à chaque
lot terminé.

Dernière mise à jour : 8 août 2026 — lot 5 côté Selflow : la passerelle
Comptaflow devient idempotente, et le secret partagé cesse d’être public.

---

## 1. Les deux applications

| | Rôle | Dépôt |
|---|---|---|
| **Selflow** | Gestion commerciale : ventes, achats, stocks, points de vente, certification FNE auprès de la DGI. Produit les écritures comptables. | `meledjeabrahamagnimel-lgtm/Selflow` |
| **Comptaflow** | Application comptable complète : balance, grand livre, états SYSCOHADA, analytique. Reçoit les écritures de Selflow. | `guysergekouassi/COMPTAFLOW` |

Selflow écrit, Comptaflow exploite. Un client sans abonnement Comptaflow dispose
malgré tout d’une balance de contrôle dans Selflow — faite au lot 4.5.

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
| Impression du reçu | **Par le navigateur** (`window.print()`, format 80 mm) pendant les tests, boîte de dialogue comprise, pour voir le rendu. **À retirer sur ordre du propriétaire**, pas avant |
| TERNE | **Pas de terminal fiscal.** Selflow passe par l'API de la DGI, qui renvoie les trois éléments du sticker. L'imprimante de caisse est un périphérique ordinaire |

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

### Lot 4 — Les imputations — **TERMINÉ**

| Étape | État |
|---|---|
| 4.1 · Chaîne d'imputation article → rayon → défaut | **TERMINÉ** |
| 4.2 · CUMP (Coût Unitaire Moyen Pondéré) et inventaire permanent | **TERMINÉ** |
| 4.3 · Lettrage | **TERMINÉ** |
| 4.6 · Grand livre | **TERMINÉ** |
| 4.4 · BAPA sans TVA déductible | **TERMINÉ** |
| 4.5 · Balance de contrôle dans Selflow | **TERMINÉ** |

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

#### 4.4 — Le bordereau d'achat ne déduit plus de TVA

Un **BAPA** constate un achat auprès d'un tiers **non immatriculé** : il ne
facture aucune TVA, et il n'y a donc rien à déduire. `ventilationAchat`
recalculait pourtant la TVA depuis `produits.taux_tva`, le taux du catalogue.

L'écriture débitait donc un compte `445` de TVA déductible **sur une taxe que
personne n'avait payée**. Ce n'est pas une imprécision comptable : c'est une
déduction indue, et c'est l'entreprise qui en répond devant l'administration.

C'est le même défaut que celui corrigé au lot 1 sur le document imprimé et sur
le payload FNE — il subsistait dans la troisième sortie, l'écriture comptable.
Le repli sur `achats.montant_tva` est écarté lui aussi : un bordereau dont la
pièce porterait une TVA, saisie à tort ou héritée d'une conversion, la verrait
sinon revenir par la fenêtre.

Deux tests, et le second compte autant que le premier : écarter la TVA partout
serait aussi faux que la déduire partout, donc un achat ordinaire doit continuer
de déduire la sienne.

#### 4.5 — La balance de contrôle

Selflow écrit les écritures, Comptaflow les exploite. Mais **un client sans
abonnement Comptaflow n'avait aucun moyen de vérifier ce que Selflow avait
écrit** : les écritures existaient en base, et nulle part un écran ne les
totalisait. Une erreur d'imputation ne se voyait donc jamais.

`/admin/comptabilite/balance` répond à trois questions, et à trois seulement :

1. **Les débits égalent-ils les crédits ?** Si non, une écriture est incomplète,
   et tout ce qui en découle — résultat, bilan — est faux. C'est le contrôle qui
   prime sur les autres, et il s'affiche en tête.
2. **Quels comptes ont bougé, et de combien ?**
3. **Quelque chose est-il tombé sur un compte générique ?** Une ligne en
   `701000` alors que les rayons portent leurs comptes signale un article créé à
   la main, sans rayon — exactement ce que le lot 4.1 corrige, et ce contrôle
   dit s'il en reste.

Chaque écriture porte **un compte au débit et un compte au crédit**, sur la même
ligne : ce n'est pas une ligne par compte. La balance agrège donc les deux
colonnes séparément avant de les réunir par numéro.

Le solde est **une seule colonne signée** — positif débiteur, négatif créditeur.
L'écran présente ce qu'il veut ; le calcul n'a pas à choisir pour lui.

Ce n'est **pas un état comptable au sens légal**, et l'écran le dit : les états
SYSCOHADA — grand livre, bilan, compte de résultat — restent produits par
Comptaflow.

- `tests/Feature/BalanceTest.php` — 15 tests, dont le filtre de site confronté à
  l'identifiant d'une autre entreprise : sans ce contrôle, `?pdv_id=`
  afficherait le chiffre d'affaires du concurrent.

#### 4.3 et 4.6 — Lettrage et grand livre, d'après Comptaflow

Le dépôt `guysergekouassi/comptaflow` a servi de référence, sur demande du
propriétaire. Ce que j'y ai lu et repris :

| Chez Comptaflow | Dans Selflow |
|---|---|
| `lettrages` : `code`, `date_lettrage`, `user_id`, `company_id` | idem, avec `entreprise_id` et `utilisateur_id` |
| `ecriture_comptables.lettrage_id` | `ecritures_comptables.lettrage_id` |
| Grand livre sur une **plage de comptes**, du compte A au compte B | idem |
| `fetchSoldesInitiaux()` : cumul de tout ce qui précède `date_debut` | `GrandLivreService::soldesInitiaux()` |
| Colonne de lettrage au grand livre (`leftJoin lettrages`) | idem, par relation Eloquent |

**Une différence de structure, et elle décide de tout le reste.** Chez
Comptaflow, une écriture porte **un** compte — `plan_comptable_id`, avec son
débit et son crédit ; une opération compte donc plusieurs écritures. Dans
Selflow, une écriture porte **les deux** comptes sur la même ligne.

Conséquence directe : **une écriture de Selflow produit deux lignes de grand
livre**, une sur le compte débité, une sur le compte crédité, chacune portant
l'autre compte en contrepartie. C'est aussi la traduction que le déversement du
lot 5 devra faire — et c'est ici qu'elle se lit le plus clairement.

**Le défaut que cette différence a failli me faire écrire.** J'avais d'abord
contrôlé l'équilibre d'un lettrage par `SUM(debit) − SUM(credit)`, comme le
ferait Comptaflow. Dans Selflow, chaque écriture porte un débit et un crédit
**égaux** sur la même ligne : cette somme vaut donc toujours zéro, et
**n'importe quel ensemble aurait passé pour équilibré** — une facture de 100 000
se serait lettrée avec un acompte de 40 000, déclarant éteinte une créance dont
il restait 60 000 dus. Le test l'a montré avant le premier utilisateur.

L'équilibre se juge donc **du point de vue du compte lettré** : la facture le
débite, le règlement le crédite, et l'écart est ce qui reste dû. C'est pourquoi
`LettrageService::lettrer()` prend le compte en paramètre — ce n'est pas un
détail de signature.

Deux règles portées par le code :

- **un lettrage équilibre** — un règlement partiel se lettre quand le solde est
  atteint, pas avant ;
- **un lettrage ne se réécrit pas** — on le défait et on recommence. Un code
  n'est jamais recyclé : il désignerait deux rapprochements différents dans
  l'histoire du compte, et le grand livre d'un exercice clos deviendrait faux.

Les codes suivent la convention comptable — `A`, `B`, … `Z`, puis `AA` — et non
un identifiant numérique : c'est ce qu'un comptable s'attend à lire.

**La balance gagne son solde initial** au passage, de la même source. Sans lui,
la balance d'un mois de mars donnerait le solde *de mars* et non le solde du
compte. Le premier jour de la période n'entre pas dans le report : il serait
compté deux fois.

**Le lettrage se pose de lui-même au règlement.** Demander le rapprochement en
seconde manipulation reviendrait à ne jamais l'obtenir, et le compte client
redeviendrait illisible en trois mois. `LettrageService::lettrerLaPiece()` est
appelé après chaque règlement, client comme fournisseur : si l'encaissement
éteint la créance, il lettre ; sinon il **ne fait rien** plutôt que de mal faire.
Un règlement partiel reste ouvert, et c'est le bon résultat — il reste dû.

- `tests/Feature/GrandLivreLettrageTest.php` — 25 tests.

### Lot 5 — La passerelle Comptaflow — **CÔTÉ SELFLOW TERMINÉ**

Le dépôt `guysergekouassi/comptaflow` a servi de référence **et** de cible. Le
côté Selflow est poussé ; **le côté Comptaflow est écrit, vérifié, et attend
d'être appliqué** — la session de développement n'autorise pas l'écriture sur un
dépôt d'un autre propriétaire. Le correctif est versionné dans
`PASSERELLE-COMPTAFLOW/`, avec son mode d'emploi.

#### Un défaut que le lot 4.2 avait introduit, trouvé en lisant le déversement

`ComptabiliteService` écrit **un compte par ligne** depuis l'origine — comme
Comptaflow. Mon `InventairePermanentService` en écrivait **deux sur la même
ligne**. Cela fonctionnait pour la balance et le grand livre de Selflow, qui
gèrent les deux formes, mais le point d'entrée de Comptaflow retient
`compte_debit` s'il est présent et **ignore `compte_credit`** : les deux montants
se seraient imputés sur le seul compte de stock, le compte de variation serait
resté vide, et les deux balances auraient divergé sans que rien ne le signale.

Corrigé : deux lignes, comme partout ailleurs.

#### Le secret partagé, des deux côtés

`config/selflow.php` ne portait plus de valeur de repli depuis le lot 0 — mais
**six sites passaient leur propre repli à l'appel**, ce qui annulait la
correction. `config('selflow.comptaflow_api_secret', 'selflow-comptaflow-secret-2026')`
rend la chaîne en dur quand la variable manque.

Même défaut chez Comptaflow, à sept endroits, et une comparaison par `!==` qui
révèle le secret caractère par caractère au chronomètre.

#### Ce que Selflow envoie désormais

| Champ | Pourquoi il manquait |
|---|---|
| `compte_tiers` | Comptaflow le cherchait dans son plan de tiers depuis le compte général, ne le trouvait pas, et rattachait tout au compte collectif. **Le relevé d'un client était impossible à établir** |
| `cle_selflow` | `n_saisie` recevait la référence de pièce : rejouer une synchronisation dupliquait tout, et la balance doublait |
| `point_de_vente` | Les points de vente sont les axes analytiques, leurs noms les sections. Sans lui, aucune ventilation par magasin |
| `exercice_debut` / `exercice_fin` | Comptaflow prenait le sien sans comparer : une pièce d'un exercice clos se rangeait dans l'exercice courant |

- `tests/Feature/PasserelleComptaflowTest.php` — 9 tests.

#### Ce que le correctif Comptaflow corrige

Secret sans repli et `hash_equals` ; idempotence par `cle_selflow` avec
contrainte d'unicité ; compte de tiers retenu sans jamais créer de fiche ;
exercices comparés, refus en 409 s'ils sont disjoints ; journal inconnu signalé
au lieu de retomber sur le premier de la liste ; `type_de_compte` déduit de la
classe SYSCOHADA au lieu d'`actif` en dur ; compte rendu portant `count`,
`ignorees` et `refus`.

**Le principe posé par le propriétaire est respecté** : code journal, plan
comptable et plan de tiers déjà en place ne sont jamais réécrits par Selflow. Ce
qui manque est créé en dessous, en respectant la configuration initiale de
Comptaflow.

#### Ce qui reste

Pousser le correctif — voir `PASSERELLE-COMPTAFLOW/LISEZ-MOI.md` — et poser
`EXTERNAL_SYNC_SECRET` des deux côtés. Sans elle, la synchronisation refuse
désormais tout appel, ce qui est le comportement voulu.

Reste aussi, côté Selflow : le déversement se fait dans un `created()` de modèle,
par un appel HTTP synchrone de trois secondes. Chaque vente attend donc la
réponse de Comptaflow. À passer en file d'attente.

### Lot 6 — Les manques métier

Devis B2B opposable, nomenclature de production, lots et péremption,
immobilisations, emballages consignés, modèles d'importation complets.

#### Lot 6.1 — La fabrication ne compte plus double — **TERMINÉ**

Le lot 4.2 a fait écrire l'inventaire permanent à la porte unique du stock.
`ProductionControleur::validerOrdre()` appelait **en plus**
`ComptabiliteService::genererEcritureProduction()`, qui écrivait les deux mêmes
paires. Trois conséquences, toutes silencieuses :

| Défaut | Effet |
|---|---|
| **Double écriture** | Le coût de production ressortait au double. Les matières deux fois en charge, le compte de stock crédité deux fois pour une seule sortie physique. Sur un atelier qui produit tous les jours, le stock de matières partait en négatif au bilan |
| **Comptes en dur** | `311000` et `351100` quelle que soit la famille. Une boulangerie et une quincaillerie imputaient au même compte, et `351100` n'existe dans aucun plan — le stock de produits finis est en 36 |
| **Produit fini valorisé à `prix_achat`** | Le prix d'achat d'une chose qu'on ne rachète pas : presque toujours nul. Les matières partaient en charge, rien n'entrait en face, et la fabrication apparaissait en **perte sèche** |

**Corrigé.** `genererEcritureProduction()` est retirée — le commentaire qui prend
sa place dans `ComptabiliteService` dit pourquoi, pour qu'elle ne renaisse pas.
Le produit fini entre au coût de ce qui l'a fabriqué : la somme des sorties
valorisées au CUMP (Coût Unitaire Moyen Pondéré), divisée par la quantité
produite. Les comptes viennent de la chaîne article → rayon → défaut.

**Un garde-fou nouveau.** Chaque mouvement écrit sa paire équilibrée ou n'écrit
rien : une fabrication dont les matières s'imputent mais dont le produit fini ne
s'impute pas reste équilibrée au bilan et **fausse au compte de résultat**. Ce
cas précis — et lui seul — arrête désormais la validation avec le nom du compte
manquant. Un atelier qui n'a rien paramétré du tout fabrique sans écriture, ce
que rien ne lui interdit.

Les deux générateurs de données de démonstration — `SeedMassiveCommand` et
`seed_mega.php` — passaient par `decrementStock()` et par des écritures directes
dans `mouvements_stock`, hors de la porte unique. Leur bloc de production passe
par `StockService` : la balance de démonstration cesse d'être fausse.

Corrigé au passage : `ProductionControleur` initialisait les fiches de stock
d'un produit fini créé à la volée avec une colonne `quantite` qui n'existe pas
et n'est pas dans `$fillable` — la valeur passait à la trappe sans rien signaler.

- `tests/Feature/ProductionTest.php` — 16 tests.

#### Lot 6.2 — Le devis opposable — **TERMINÉ**

Le devis existait déjà comme étape de la vente — Devis → Bon de commande →
Facture — et ne touchait ni le stock, ni la comptabilité, ni la plateforme. Tout
cela était juste. **Mais il n'engageait personne**, et quatre manques
l'expliquaient :

| Manque | Ce qu'il produisait |
|---|---|
| **Aucune date de validité** | Un devis de janvier restait présentable en décembre, aux prix de janvier. Le client qui l'accepte a raison de le faire : rien n'y dit le contraire |
| **Aucune trace de l'acceptation** | Ni la date, ni le nom de qui a accepté. En cas de contestation, rien à opposer |
| **La conversion se rejouait** | `archived` disait qu'elle avait eu lieu sans dire en quoi, et n'empêchait pas la seconde : **le même devis produisait deux bons de commande**, donc deux livraisons et deux factures |
| **La pièce née héritait de la date de son aînée** | `replicate()` recopiait `date_vente` : une facture de juin issue d'un devis de janvier était **datée de janvier**, se rangeait dans la période de janvier et entrait dans la déclaration de TVA du mauvais mois |

**Ce que le lot pose.** Trois colonnes — `date_validite`, `date_acceptation`,
`accepte_par` — et une quatrième, `converti_en_id`, qui dit ce qu'une offre est
devenue. Trente jours de validité par défaut : l'usage commercial courant, et le
délai que retiennent les tribunaux quand l'offre est muette. Le jour du terme
est compris.

Une offre acceptée ou convertie **se relit, elle ne se réécrit pas** : c'est ce
qui fait la différence entre un document opposable et une note. La correction
passe par un nouveau devis, ce qui laisse les deux versions lisibles. Une offre
convertie ne se supprime pas non plus — elle fonde la pièce qui en découle.

Prolonger reste possible tant que l'offre n'a rien produit : c'est refaire
l'offre, donc un geste explicite et tracé. Une offre expirée ne s'accepte plus —
l'accepter après coup ferait croire à un engagement qui n'existe pas.

**Le terme figure sur le document remis**, non seulement dans la base : un bloc
de validité s'affiche sur les quatre modèles d'impression, et porte l'accord du
client dès qu'il est enregistré. Rien de tout cela ne touche à la certification :
un devis n'est ni normalisé, ni transmis, ni certifié, et le lot ne lit ni
n'écrit aucune colonne `fne_*`.

- `2026_08_12_000001_devis_opposable.php`
- `tests/Feature/DevisOpposableTest.php` — 36 tests, dont quatre sur ce que le
  client lit et deux sur le cloisonnement entre entreprises.

#### Lot 6.3 — Les lots et la péremption — **TERMINÉ**

`produits.date_peremption` porte **une seule date par article**. Une pharmacie
qui reçoit trois arrivages de paracétamol — mars, juin, novembre — n'en
enregistre qu'un : la saisie du troisième écrase les deux premiers, et les
boîtes de mars restent en rayon sans que rien ne les signale. Le même défaut
vaut pour un dépôt de boissons, une boulangerie, un magasin de cosmétiques.

| Manque | Ce qu'il produisait |
|---|---|
| **Une date par article** | Le troisième arrivage écrase les deux premiers |
| **Aucune traçabilité** | Un rappel de lot du fabricant est impossible à honorer : rien ne dit quel arrivage est parti chez quel client |
| **Aucun ordre de sortie** | Rien n'impose de servir d'abord ce qui périme le plus tôt ; la marchandise la plus ancienne reste au fond du rayon |
| **`bientotPerime()` était faux** | `diffInDays()` rend une différence **signée** : `-200 <= 30` est vrai, donc l'écran des rebuts annonçait **le catalogue entier** comme proche de la péremption. Une alerte qui crie tout le temps ne se lit plus |
| **Le formulaire ne sauvait pas** | `date_arrivee` et `date_peremption` figuraient sur la fiche article et **n'étaient jamais enregistrées** : l'écran les reprenait vides à la visite suivante |

**La structure.** Un lot est un arrivage : un numéro, une date, une quantité, sur
un site. `mouvement_lots` dit quels lots un mouvement a consommés — **le
mouvement reste un seul mouvement**, la comptabilité, le CUMP (Coût Unitaire
Moyen Pondéré) et le journal ne changent pas. Faire des lots une seconde
valorisation donnerait deux vérités sur la valeur du stock, qui divergeraient au
premier arrondi, et la balance ne saurait plus laquelle croire.

**FEFO, et non FIFO** — *First Expired, First Out*. Les deux règles coïncident
souvent, jamais toujours : un arrivage récent à date courte doit partir avant un
arrivage ancien à date longue, et le FIFO laisserait périmer le premier.

**Le refus de servir du périmé.** Vendre un produit périmé engage la
responsabilité du commerçant, et aucun écran ne rattrape cela après coup. La
marchandise périmée quitte le stock par le rebut, le retour fournisseur, la
contre-passation ou l'inventaire — jamais par la livraison. La vente le dit
**avant** d'écrire quoi que ce soit.

Le suivi s'active article par article : un sac de ciment n'a pas de date, et
imposer un numéro de lot à sa réception ferait perdre du temps sans rien
apporter. Le préavis suit l'article — trente jours conviennent à l'alimentaire ;
un médicament se retire des rayons bien plus tôt, un cosmétique bien plus tard.

Ce qui n'a pas de lot n'est pas bloqué : le stock a pu être posé avant
l'activation du suivi. La ventilation est alors partielle, et l'écart entre le
stock et la somme des lots dit ce qui reste à régulariser.

- `2026_08_13_000001_lots_et_peremption.php`
- `Lot`, `MouvementLot`, `LotService`
- `tests/Feature/LotsEtPeremptionTest.php` — 35 tests, dont cinq de simulation
  d'attaque : vente de périmé par la route, préavis démesuré tronqué en base,
  lecture des lots d'une autre entreprise.

#### Lot 6.4 — Les immobilisations et leur amortissement — **TERMINÉ**

**Rien n'existait.** Un camion, un four, un ordinateur achetés par l'entreprise
passaient en charge de l'exercice, ou ne passaient nulle part. Trois
conséquences, et la troisième coûte de l'argent :

- **le bilan était faux** — l'actif immobilisé, la classe 2, restait vide. Une
  entreprise qui possède un camion de dix millions présentait un bilan qui n'en
  portait pas trace ;
- **le résultat était faux** — un investissement passé en charge écrase le
  résultat de l'année où il est fait, et l'allège indûment les suivantes ;
- **la charge d'amortissement, déductible, n'était pas prise.** Une entreprise
  qui n'amortit pas **paie l'impôt sur un bénéfice qu'elle n'a pas.**

**Le plan se calcule d'avance**, à la mise en service — c'est lui que le
comptable présente au contrôle. C'est la **mise en service** qui déclenche
l'amortissement, non l'acquisition : un matériel acheté en novembre et installé
en janvier ne s'amortit pas sur novembre et décembre. La dernière annuité solde
le plan, sinon les arrondis laisseraient quelques francs non amortis et le bien
resterait indéfiniment au bilan pour ce reliquat.

Les écritures, en SYSCOHADA révisé — les numéros viennent du relevé OHADA du
dépôt, non d'une mémoire :

| Écriture | Débit | Crédit |
|---|---|---|
| **Dotation** | 681x | 28x |
| **Cession** — solde de l'amortissement | 28x | — |
| **Cession** — valeur comptable nette | 810000 | — |
| **Cession** — sortie du bien | — | 2x |
| **Cession** — le prix | 485000 | 820000 |

**La plus-value ne s'écrit pas** : elle apparaît d'elle-même comme différence
entre le 82 et le 81 au compte de résultat. L'inscrire doublerait le résultat de
cession — erreur qu'on retrouve dans beaucoup de logiciels.

**La dotation de l'exercice de sortie est due jusqu'au jour de la sortie.** Le
bien a servi ; l'omettre gonflerait la valeur nette, donc minorerait la charge
et **majorerait la plus-value, sur laquelle l'entreprise serait imposée**.

Une dotation ne se passe qu'une fois — `comptabilise_at` le garantit. La
repasser doublerait la charge et amortirait le bien au double de sa valeur, et
l'erreur ne se verrait qu'au bilan de l'année suivante. Une fiche dont une
dotation est passée ne se retouche plus.

**La seule convention d'usage du lot est isolée et nommée.** Le prorata temporis
se compte en jours sur une année commerciale de 360 jours, chaque mois valant 30
jours — usage OHADA courant. C'est le seul point qui ne vient pas d'un texte du
dépôt : il tient dans `AmortissementService::JOURS_PAR_AN`, pour qu'un cabinet
qui compte en mois entiers change une constante et non le service.
**Confirmé par le propriétaire du projet.**

**Le dégressif n'est pas calculé.** Ses coefficients relèvent d'un texte que le
dépôt ne contient pas, et les supposer donnerait un plan faux que rien ne
signalerait — c'est exactement l'écart qui a produit un timbre de quittance à
1,5 %, taux qui ne figure dans aucun texte.

- `2026_08_14_000001_immobilisations_et_amortissements.php`
- `Immobilisation`, `DotationAmortissement`, `AmortissementService`,
  `ImmobilisationControleur`, trois vues
- `tests/Feature/AmortissementTest.php` — 37 tests, dont dix par les écrans :
  code déjà pris, mise en service antérieure à l'acquisition, valeur résiduelle
  supérieure à la valeur d'acquisition, fiche engagée, sortie antérieure à la
  mise en service, et la fiche d'un bien d'une autre entreprise.

#### Lot 6.5 — Les emballages consignés — **TERMINÉ**

**Rien n'existait**, et c'est le quotidien d'un dépôt de boissons, d'un
distributeur de gaz, d'un grossiste en eau minérale — c'est-à-dire d'une part
considérable du commerce ivoirien.

| Manque | Ce qu'il produisait |
|---|---|
| **La consignation passait en vente, ou nulle part** | Une caisse consignée 2 000 francs gonflait le chiffre d'affaires de 2 000 francs que l'entreprise devra rendre. Ce n'est pas un produit, c'est **une dette** |
| **Rien ne disait ce qui est dehors** | Un dépôt ne savait pas combien de casiers dorment chez ses clients, ni depuis quand, ni chez qui |
| **Le non-retour ne se constatait pas** | La consignation gardée restait indéfiniment en dette au bilan alors qu'elle était devenue un produit |

Les comptes viennent du relevé OHADA du dépôt :

| Sens | Compte | Intitulé |
|---|---|---|
| Consigné **au client** | `419400` | Clients, dettes pour emballages et matériels consignés |
| Consigné **par un fournisseur** | `409400` | Fournisseurs, créances pour emballages et matériels à rendre |
| Gain à la reprise ou au non-retour | `707400` | Bonis sur reprises et cessions d'emballages |
| Perte sur ce qu'on ne rend pas | `622400` | Malis sur emballages |

**Une dette d'un côté, une créance de l'autre** : les confondre met le bilan à
l'envers. Chez le fournisseur, tout s'inverse — ce qu'on ne rend pas devient un
*mali*, une charge, et non un boni.

Les retours partiels sont la règle : un client rend huit casiers sur dix et
garde les deux autres. La reprise à prix réduit laisse un boni — c'est ce qui se
pratique quand l'emballage revient abîmé. Rembourser **plus** que le prix
consigné est refusé : l'entreprise rendrait plus qu'elle n'a reçu, et perdrait
de l'argent sans qu'aucune ligne ne le dise.

**Le service n'établit aucune facture, et c'est délibéré.** Le non-retour est
une vente, soumise à la TVA et à la certification de la plateforme : elle passe
par l'écran de vente ordinaire, dont la conformité est acquise et **gelée**.
Fabriquer ici une seconde route vers la FNE remettrait cette conformité en jeu
pour un gain nul. Le service constate le boni en comptabilité, et l'écran
renvoie l'utilisateur vers la facture pour la part fiscale.

- `2026_08_15_000001_emballages_consignes.php`
- `Consignation`, `ConsignationService`, `ConsignationControleur`, une vue
- `tests/Feature/ConsignationTest.php` — 35 tests, dont sept par les écrans :
  remboursement supérieur au prix consigné tenté par la route, reprise de plus
  que ce qui est dehors, consignation sans tiers, et la consignation d'une autre
  entreprise.

#### Lot 6.6 — Les modèles d'importation — **TERMINÉ**

L'import existait pour cinq modules. Quatre défauts, dont deux arrêtaient net
une migration :

| Défaut | Ce qu'il produisait |
|---|---|
| **Une ligne plus longue que l'en-tête tuait tout le fichier** | `array_combine` lève une erreur dès que les deux tableaux n'ont pas la même taille. **Un simple point-virgule en fin de ligne suffisait** — et Excel comme LibreOffice en produisent. Le fichier entier partait en « Erreur critique », sans dire quelle ligne |
| **`firstOrCreate(['email' => …])` cherchait sur l'adresse seule** | L'adresse est unique sur toute la plateforme : celle d'un autre inscrit faisait que rien n'était créé, et **la ligne comptait pour un succès**. L'administrateur annonçait un accès à un salarié qui ne pourrait jamais se connecter. C'était aussi un **oracle d'existence** silencieux et gratuit : importer une liste d'adresses et lire le compteur suffisait à savoir lesquelles sont inscrites |
| **Un service ne pouvait pas s'importer** | `prix_achat > 0` était exigé de tous. Un cabinet comptable, dont tous les articles sont des missions, ne passait aucune ligne |
| **Le modèle des articles portait douze colonnes** | La fiche en compte deux fois plus. Stock d'ouverture, comptes de stock, suivi par lot, consignation : tout se ressaisissait fiche par fiche après l'import |

**Deux modèles nouveaux.** `stock-initial` alimente un catalogue déjà en place,
site par site et lot par lot ; `immobilisations` reprend un parc avec son
antériorité et établit chaque plan. Le stock passe par `StockService`, la porte
unique — un stock posé à côté n'aurait ni trace au journal, ni valeur au bilan —
et le motif retenu est l'**inventaire** : une ouverture est un comptage, pas une
réception, il n'y a pas de fournisseur derrière.

**L'import des immobilisations ne passe aucune dotation en comptabilité.** Il
établit le plan ; c'est la clôture qui écrit. Passer ici des dotations
antérieures produirait des charges sur des exercices déjà arrêtés.

Corrigé aussi : les en-têtes se lisent quels que soient la casse, les accents et
les doublons ; les nombres à la française — « 12 000,50 », dont `(float)`
rendait **12** — se lisent ; les dates acceptent jj/mm/aaaa comme aaaa-mm-jj ; et
**le taux de TVA ne se rattrape plus en silence.** Une liste `[0, 9, 18]`
recopiée dans l'import aurait dérivé de la source ; c'est `Produit::TAUX_TVA_DGI`
qui fait foi, et un taux hors barème est refusé plutôt que ramené à 18 %.

#### Le défaut du lot 6.4 que ce lot a mis au jour

Un test d'import a fait tomber `AmortissementService::etablirLePlan()`. Pour un
bien dont la durée vaut zéro — un terrain, un fonds de commerce —, la règle « la
dernière annuité solde le plan » s'appliquait à l'unique exercice du parcours et
écrivait une dotation **égale à la valeur entière du bien** : un terrain de 25
millions passait 25 millions en charge.

**Le défaut ne se voyait pas sur une mise en service au 1er janvier** — ce
jour-là, la borne de fin tombe l'année précédente et la boucle ne s'exécute pas
du tout, ce qui est exactement le cas que le test du lot 6.4 couvrait. Un plan
vide est désormais rendu dès que la durée ou la base amortissable est nulle, et
deux tests verrouillent les deux situations.

- `tests/Feature/ImportTest.php` — 36 tests, dont sept de simulation d'attaque :
  adresse d'une autre entreprise, rôle `superadmin` glissé dans le fichier,
  `entreprise_id` injecté dans une colonne, et stock d'ouverture posé sur
  l'article d'un concurrent.

### Lot 7 — La vitrine — **INFRASTRUCTURE TERMINÉE, CONTENU EN ATTENTE**

Landing page, documentation, politique, présentation de DC-Knowing et de ses
produits, écran superadmin de gestion de la vitrine.

Le contenant est livré ; **le contenu ne l'est pas, et ne peut pas l'être
d'ici.** Le texte d'une vitrine engage l'entreprise qu'elle présente : il vient
de son propriétaire, jamais d'un exemple laissé là. Une vitrine vide affiche
une page d'attente honnête plutôt que du faux texte qui finirait en production
le jour où personne ne penserait à le remplacer.

Ce qui existe :

| Élément | Détail |
|---|---|
| Deux tables | `vitrine_sections` et `vitrine_cartes`, cartes en cascade |
| Cinq gabarits | bandeau, colonnes, liste, tarifs, texte — un gabarit inconnu est refusé |
| Page publique | `/presentation`, route `vitrine`, **hors du groupe `guest`** : un connecté la lit aussi |
| Écran superadmin | création, modification, ordre, publication réversible, cartes avec image |
| Publication | une section **naît hors ligne** : publier est un geste délibéré |
| Habilitation | `gestion_vitrine`, accordée aux superadministrateurs en place par migration — le contrôle ferme par défaut, sans elle l'écran serait refusé à tout le monde le jour de sa mise en ligne |
| Liens | `http(s)://` ou chemin interne `/…` seulement : un `javascript:` déposé là s'exécuterait chez chaque visiteur |
| Épreuves | `tests/Feature/VitrineTest.php` — 19, dont 3 simulations d'attaque |

**La vitrine est branchée — 15/08/2026.** Elle existait à `/presentation`,
mais **rien n'y menait** : la racine conduisait le visiteur droit au
formulaire de connexion, et il fallait connaître l'adresse pour lire la
présentation — le contraire d'une vitrine. `/` la sert désormais à qui n'est
pas connecté ; `/presentation` reste valable.

L'état d'attente porte les deux liens qui manquaient — se connecter, créer un
compte. Sans eux, un visiteur arrivé avant la publication n'avait nulle part
où aller.

**La vraie page d'accueil — 15/08/2026.** La première version savait afficher
des cartes en colonnes ; ce n'était pas une page de présentation. Elle a été
refaite :

| Élément | Détail |
|---|---|
| Neuf dispositions | bandeau, colonnes, liste, **produits**, **équipe**, **chiffres**, **média**, tarifs, texte |
| Entrée au défilement | `IntersectionObserver`, une observation par élément, décalage progressif entre les cartes |
| Mouvement réduit | `prefers-reduced-motion` respecté en CSS **et** en JavaScript — tout s'affiche d'emblée |
| La facture qui part à la DGI | entièrement dessinée en CSS : ondes, flottement, piste de transmission, code QR, pastille « Normalisée ». Aucune image à charger |
| Médias | une image ou une vidéo par section, fichier déposé (20 Mo) ou adresse. La vidéo est `muted`, sans quoi le navigateur refuse la lecture automatique |
| Fonds | clair, blanc, sombre — c'est l'alternance qui découpe la page à l'œil |
| Menu | ancres vers les sections, repliable sous 700 px, se referme derrière soi |
| Responsive | trois paliers ; les grilles passent de 4 à 2 à 1 colonne |

**Le semeur `VitrineSeeder` pose la charpente et les textes dictés par le
propriétaire** : les six applications — Selflow, Comptaflow, RHFlow, LegalFlow,
Agent-AI, CGA-Connect —, la fiche du développeur, celle du cabinet, et les
deux entrées documentation et politique. Il passe par `firstOrCreate` sur
chaque clé : le relancer après une saisie n'efface rien, ce qui permet de le
laisser dans `DatabaseSeeder`.

**Ce qui reste vide, et pourquoi.** Photos, vidéos, noms des autres membres :
aucun n'a été donné. Et trois descriptions se réduisent à leur domaine —
RHFlow, LegalFlow, Agent-AI — parce que c'est tout ce que leur nom permet
d'affirmer. **Elles sont à relire.**

La présentation de DC-Knowing ne porte que ce que le propriétaire a dit :
cabinet comptable, et M. Keyman Constant directeur général. Une recherche
publique ne trouve la maison que dans la liste 2023 des Centres de Gestion
Agréés de la DGI, sous un autre nom de responsable — rien qui permette
d'écrire son histoire sans l'inventer.

### Les accès délégués — **TRANCHÉ ET LIVRÉ**

`admin_secondaire` et `responsable_pdv` n'étaient rattachés à aucun espace :
`role:admin` compare à l'identique, et la racine les renvoyait dans la boucle
de redirection. La question restait ouverte de savoir s'il fallait les garder.

**Décision du propriétaire du projet, 15/08/2026 :** ce sont des **accès
délégués**. « C'est comme si je veux déléguer mon travail, donc je crée un
accès pour une autre personne pour avoir accès à mon espace. » Le même espace
que le propriétaire, avec les habilitations qu'il accorde.

Deux corrections, et la seconde est la plus importante :

- **`role:admin` accepte désormais les rôles délégués.** Le middleware dit
  dans quel espace on travaille ; `habilitation`, qui s'exécute juste après et
  ferme par défaut, dit ce qu'on y fait. La racine les conduit au tableau de
  bord d'administration ;
- **`aHabilitation()` rendait `true` pour tout à `admin_secondaire`**, au même
  titre que le propriétaire. Déléguer revenait donc à **céder l'entreprise
  entière** : le compte créé « pour aider aux ventes » atteignait la
  comptabilité, les paramètres fiscaux, et l'écran qui distribue les droits —
  d'où il pouvait s'en accorder d'autres, ou en retirer au propriétaire. Seuls
  le superadministrateur et le propriétaire ont tout.

**Conséquence à connaître :** un compte délégué sans habilitation ne voit
rien, et reçoit un 403 (Forbidden — accès interdit) explicite. C'est le sens
d'un contrôle qui ferme par défaut ; les droits se cochent depuis l'onglet des
habilitations, écran du personnel. Les comptes `admin_secondaire` existants
perdent leur accès total : il faut leur accorder ce qu'ils doivent avoir.

Neuf épreuves dans `AiguillageRacineTest`.

### Lot 8 — Stabilisation

Identifiants opaques dans les URL, habilitations, limitation de débit, un
scénario de bout en bout par combinaison de modules.

#### Lot 8.1 — Les habilitations ferment par défaut — **TERMINÉ**

Le dictionnaire vivait dans le middleware, et la vérification s'écrivait :

```php
if (isset($correspondances[$route])) { … }
```

**Une route absente du dictionnaire passait donc sans contrôle.** Ce n'était pas
une décision, c'était un oubli qui s'aggravait à chaque lot : au moment de
l'audit, **quatre-vingt-huit routes** n'y figuraient pas.

**Ce qui était réellement atteignable, et ce qui ne l'était pas.** L'espace du
caissier compte vingt-six routes ; **neuf d'entre elles** étaient dans les
quatre-vingt-huit, et un caissier n'ayant que `nouvelle_vente` pouvait donc les
emprunter : transformer une commande en facture, enregistrer l'acceptation d'un
client, prolonger une offre, et surtout **créer puis valider des bons de
livraison — qui font sortir la marchandise du stock**. Le reste des
quatre-vingt-huit vit sous `admin.`, derrière `role:admin`, et n'était pas
atteignable ; ce qui les rendait dangereuses n'est pas ce qu'elles ouvraient
hier, mais qu'elles se seraient ouvertes sans bruit le jour où l'espace du
caissier se serait élargi.

**Le sens est inversé : ce qui n'est pas classé est refusé.** Le classement vit
désormais dans `App\Modules\Authentification\Regles\Habilitations`, en deux
tableaux — `PAR_ROUTE` et `OUVERTES`, chaque route ouverte portant sa raison
écrite. Et **le test qui compte échoue tant qu'une route nouvelle n'a pas été
rangée** : la dérive devient impossible au lieu d'être corrigée une fois de plus
dans six mois. Il a d'ailleurs immédiatement trouvé deux routes
`superadmin.referentiel.*` que le dictionnaire d'administration ignorait aussi.

#### L'adresse écrite en dur

```php
if ($utilisateur->email === 'superadmin@gmail.com') return $next($request);
```

Elle n'était pas exploitable — `role:superadmin` s'exécute avant — mais elle
publiait dans le dépôt **l'identité du compte le plus puissant de la
plateforme**, ce qui suffit à en faire la cible de toute tentative. Elle est
retirée.

Le privilège devient une **donnée** : `Habilitations::PLATEFORME` porte les six
habilitations d'administration, les semeurs les attribuent au compte principal,
et une migration les donne aux superadministrateurs déjà en place — sans quoi ils
se retrouveraient enfermés dehors au premier déploiement, personne ne pouvant
leur rendre leurs droits puisque ce sont ces écrans-là qui les distribuent. La
migration ne touche que les comptes dont la colonne est vide : un
superadministrateur volontairement restreint garde sa restriction.

- `App\Modules\Authentification\Regles\Habilitations`
- `2026_08_16_000001_habilitations_des_superadministrateurs.php`
- `tests/Feature/HabilitationsTest.php` — 17 tests.

#### Lot 8.2 — Les limites de débit — **TERMINÉ**

La connexion comptait ses échecs — cinq essais par couple adresse/adresse IP, et
c'était juste. **Tout le reste était libre.**

| Porte | Ce qui n'était borné nulle part |
|---|---|
| **Les 51 routes d'API** | **Le groupe `api` n'existait pas.** Les modules posent leurs routes avec `Route::middleware('api')`, mais `withRouting()` n'ayant pas de volet `api`, le groupe n'était jamais défini : l'authentification par jeton — une chaîne dans une colonne — pouvait être éprouvée sans compter |
| **`api/external/*`** | **La porte la plus précieuse de l'application** : aucune authentification, un secret partagé, et `list-companies` qui rend **toutes les entreprises de la plateforme avec leur administrateur**. Le secret est bien comparé en temps constant depuis le lot 5 — mais un secret se devine, et rien ne comptait les essais |
| **La réinitialisation de mot de passe** | Demander un lien envoie un courriel : on inonde une boîte, on épuise le quota d'envoi, et l'on apprend au passage quelles adresses existent |
| **L'import** | Un fichier de cinq mégaoctets, lu ligne à ligne, qui écrit articles, stock, immobilisations et comptes |
| **La normalisation par lot, les stickers, les tests de connexion** | Ils appellent la plateforme de la DGI et Comptaflow. Les marteler expose l'entreprise à voir **sa propre clé** ralentie ou coupée : la conséquence est chez elle, pas chez nous |

**Le principe des clés.** Une limite se compte par **acteur** — l'utilisateur
authentifié, l'adresse IP sinon — et non par route : compter par route
laisserait un même acteur épuiser trente portes voisines l'une après l'autre
sans jamais franchir une limite. La réinitialisation porte deux bornes : l'une
par acteur, l'autre par adresse IP, qui seule arrête le balayage d'adresses
électroniques.

Les limiteurs sont déclarés dans un fournisseur dédié, chargé **avant** celui
des modules : `throttle:api` sur une route dont le limiteur n'est pas déclaré
lève une erreur au premier appel, non au démarrage — le défaut ne se verrait
qu'en production, sur la première requête. Un test le vérifie pour chacun.

Les épreuves les plus importantes sont **de comportement, non de
configuration** : c'est la réponse du serveur qui fait foi. Soixante-dix appels
à une route d'API finissent bornés ; vingt-cinq tentatives de secret sur la
synchronisation externe aussi ; et un caissier qui enchaîne quarante ventes
n'est pas arrêté.

- `App\Providers\LimitesDeDebit`
- `tests/Feature/LimitesDeDebitTest.php` — 15 tests.

#### Lot 8.3 — L'oracle de volume, et les identifiants opaques — **TERMINÉ**

**Le cloisonnement tenait.** Les soixante-cinq gardes d'appartenance faisaient
leur travail, et aucune donnée d'autrui n'était rendue — l'audit statique n'a
relevé que des faux positifs, `AchatControleur` passant par un helper partagé
que la recherche ne reconnaissait pas, et les routes `superadmin.` agissant sur
toutes les entreprises par construction.

**Mais ces gardes répondaient 403 là où une pièce inexistante répond 404**, et
la différence se lit. Les identifiants étant séquentiels, il suffisait de
demander `/admin/ventes/facture/1`, `/2`, `/3` … et de compter les 403 pour
connaître **le nombre de factures de toute la plateforme** — puis, en
recommençant une semaine plus tard, son rythme de croissance. Ce n'était pas une
faille d'autorisation, c'était une **fuite de volume** ; et pour une plateforme
vendue à des entreprises concurrentes, le volume est une information
commerciale.

Soixante-cinq gardes converties, dans dix-huit contrôleurs. Une pièce qui n'est
pas la vôtre répond désormais comme tout ce qui n'existe pas.

**Le 404 vaut pour l'appartenance, non pour le droit.** Dire à un caissier
« introuvable » sur un écran qui existe et que son administrateur peut lui
ouvrir le laisserait chercher une panne là où il n'y a qu'un droit manquant :
les refus d'habilitation restent des 403, et un test le vérifie.

Un second test empêche la dérive : il relit les contrôleurs et échoue si une
garde d'appartenance neuve est écrite avec un 403.

##### Les identifiants opaques — faits

Le 404 supprimait l'oracle, non le besoin d'identifiants opaques : une adresse
qui porte `4213` dit encore quelque chose à qui la voit passer — dans un courriel
transféré, une capture d'écran, un billet d'assistance, l'historique d'un
navigateur partagé.

**Le propriétaire a tranché pour l'identité unique**, et signalé que le projet
est encore en développement : aucune donnée réelle, donc aucune période de
compatibilité à ménager. Le changement est donc net.

| Décision | Raison |
|---|---|
| **La clé primaire ne change pas** | L'entier reste ce qui porte les jointures, les index et les clés étrangères, où il est plus rapide et plus compact |
| **`getRouteKeyName()` vaut pour le web et l'API** | Deux identités — le numéro sur le mobile, l'`uuid` sur le web — auraient rendu impossible de rapprocher un journal du serveur, un billet d'assistance et une capture d'écran. Et la moitié du problème serait restée entière pour toujours |
| **L'identifiant n'est pas `fillable`** | Le trait le pose lui-même à la création : une requête ne peut donc pas choisir l'identifiant d'une ressource, ni le connaître avant de la créer |
| **L'API publie l'`uuid` à côté de l'`id`** | L'application mobile construit ses adresses depuis ce que l'API rend : sans l'identifiant dans la charge utile, elle n'a rien pour désigner une ressource |

Dix-neuf modèles, dix-neuf tables. Neuf adresses construites dans les vues avec
`$piece->id` et trois construites en JavaScript ont été reprises : elles
généraient l'entier là où la route attend l'identifiant, et **le défaut ne se
serait vu qu'au clic**.

**Un défaut trouvé en chemin.** `replicate()` recopiait l'`uuid` : la contrainte
d'unicité refusait l'enregistrement — un bon de commande ne pouvait plus naître
d'un devis — et si elle ne l'avait pas refusé, **l'adresse du devis aurait
désigné sa commande**, ce qui est exactement ce que l'identifiant sert à
empêcher. L'exclusion est posée dans le trait, non dans les appelants : un
`replicate()` écrit demain ailleurs hériterait sinon du même défaut.

Un test relit les vues et échoue si une adresse est construite avec un numéro de
ligne ; un autre vérifie que chaque modèle désigné dans une adresse porte bien
son identifiant et sa colonne.

- `App\Modules\Admin\Regles\Cloisonnement`,
  `App\Modules\Admin\Modeles\Concerns\IdentifiantOpaque`
- `2026_08_17_000001_identifiants_opaques.php`
- `tests/Feature/CloisonnementTest.php` — 11 tests ;
  `tests/Feature/IdentifiantOpaqueTest.php` — 31 tests.

#### Lot 8.3 bis — La page de panne — **TERMINÉ**

Laravel affichait sa propre page de trace. Sur un écran de caisse, un
utilisateur voyait le nom des fichiers du serveur, les versions des
bibliothèques et le contenu des variables : **illisible pour lui, et trop
lisible pour qui passait par là** — un chemin absolu dit le système
d'exploitation et l'arborescence du déploiement, une version de bibliothèque dit
quelles failles connues essayer.

La page porte désormais le message d'attente demandé, une **référence** à donner
au service informatique, et le détail technique **replié**, qu'on ouvre d'un
clic. Le repli est un `<details>` : il s'ouvre sans une ligne de script et
fonctionne même si le navigateur en refuse.

**La référence se calcule depuis l'endroit exact où la panne s'est produite** —
type, fichier, ligne. Deux occurrences du même défaut portent donc le même
numéro, ce qui permet de les regrouper, et un défaut nouveau se distingue tout
de suite d'un défaut connu. Elle tient en trois groupes courts (`SF-260816-A3F9C1`),
dictables au téléphone, et figure aussi dans le courriel d'alerte : c'est par
elle que les deux bouts se rejoignent.

**Le fond est dessiné dans la page**, en SVG et en dégradés, à la palette de
l'application — le bleu royal `#002B5C`, le gris-bleu `#F4F6F9`, la police
Inter. Aucun fichier à déployer, aucune requête à faire : le jour où le serveur
va mal est le pire moment pour dépendre d'une image qui doit se charger.

**La suite des appels est visible de tous** — décision du propriétaire. Les
chemins sont ramenés à la racine du projet : la pile dit quel fichier a appelé
quel autre, sans dire sous quel compte le serveur tourne ni où le projet est
installé. Un bouton copie tout le détail avec sa référence, puisque le recopier
à la main depuis un écran de caisse n'arrive jamais.

- `resources/views/errors/500.blade.php`, `App\Exceptions\Panne::reference()`
- `tests/Feature/PageDePanneTest.php` — 12 tests.

#### Le tableau de bord général — variable manquante, corrigé

`AdminControleur::tableauDeBordGeneral()` calculait `$totalVentesHTPeriode`
pour en tirer la marge brute et le taux de marge, mais oubliait de la
transmettre à la vue dans le `compact(...)` final. La carte « CA HT » de
`/admin/general` levait donc une erreur 500 (*Internal Server Error* —
erreur interne du serveur) dès le premier chargement, sur toute entreprise —
et rien ne l'avait couvert : aucun test ne touchait cet écran.

- `app/Modules/Admin/Controleurs/AdminControleur.php`
- `tests/Feature/TableauDeBordGeneralTest.php` — 1 test (nouveau : l'écran
  n'était couvert par rien avant).

#### Le déversement Comptaflow — du hook synchrone à la file d'attente

`EcritureComptable::booted()` appelait Comptaflow en HTTP direct dans son
hook `created()` : chaque vente, achat ou mouvement de stock qui produit une
écriture attendait la réponse de Comptaflow — jusqu'à 3 secondes de délai
réseau — avant de rendre la main à l'utilisateur. Un Comptaflow lent ou
indisponible ralentissait donc la caisse elle-même, sur toute entreprise
liée.

L'appel part désormais par `App\Jobs\DeverserEcritureComptaflow`
(`ShouldQueue`, 3 tentatives, 10 secondes de délai entre chacune) — la
clé d'idempotence `cle_selflow` rend le renvoi automatique sans risque de
doublon. Le rattrapage par lot (`php artisan selflow:sync-ecritures`) reste
le filet de sécurité pour ce qui échouerait encore après ces tentatives.
Format du payload et clé d'idempotence inchangés.

- `app/Jobs/DeverserEcritureComptaflow.php`
- `app/Modules/Admin/Modeles/EcritureComptable.php`
- `tests/Feature/PasserelleComptaflowTest.php` — 2 tests ajoutés (11 au
  total) : le déversement part en file, une entreprise non liée n'y met
  rien.

#### Lot 8.4 — Un scénario de bout en bout par combinaison de modules — **TERMINÉ pour les combinaisons atteignables**

`VerifierModulesActifs` ferme les écrans d'un module que l'entreprise n'a pas
souscrit — mais n'était couvert par **aucun test**, dans un sens comme dans
l'autre. Rien ne garantissait que le blindage marchait vraiment, ni qu'une
entreprise à modules réduits pouvait mener un cycle complet avec ce qu'elle
a réellement activé.

Deux choses désormais vérifiées :

- **le blindage** : un module absent de `modules_actifs` ferme ses écrans
  (403 — *Forbidden*, accès interdit), un module présent les ouvre, et le
  superadmin traverse le contrôle lui-même (c'est `role:admin`, en amont,
  qui lui ferme `/admin/*` — pas ce middleware) ;
- **la combinaison « Services »** — une entreprise sans module `stock` ni
  `achats`, qui vend une prestation de type `service`. `estStockable()`
  renvoie faux pour ce type : la vente ne crée aucun mouvement de stock,
  quel que soit l'état du module, et pose tout de même son écriture
  comptable puisque `comptabilite` reste actif.

La combinaison « Commerce » (vente + stock + achats + comptabilité
ensemble) était déjà couverte par `CycleStockTest` et `BalanceTest` — elle
n'est pas dupliquée ici.

**La combinaison « Production »** (production + stock + ventes, sans
achats) est vérifiée de bout en bout : un atelier reçoit sa matière première
sans passer par un achat (le module est éteint), la fabrication consomme la
matière et pose son écriture, le produit fini entre en stock, la vente le
sort et pose la sienne — et l'écran `achats` reste fermé pendant tout le
cycle.

**Une anomalie trouvée en écrivant le scénario B2B.** Contrairement à
`ventes`, `achats`, `stock`, `production` et `comptabilite`, le groupe de
routes `admin.b2b.*` ne porte **aucun** middleware `modules:b2b` : une
entreprise dont `modules_actifs` ne contient pas `b2b` atteint quand même
ces écrans, du moment qu'elle a `ventes` ou `achats`. Le menu fait la même
chose — les liens B2B sont rangés sous les sections Ventes et Achats, pas
sous un module propre. `b2b` figure pourtant dans
`Entreprise::TOUS_LES_MODULES`, comme s'il s'agissait d'un module
souscriptible à part. Non corrigé : c'est une décision de produit (le B2B
est-il un module à part, ou une fonctionnalité incluse dans ventes/achats ?),
pas un bogue technique évident. Un test verrouille le comportement actuel
pour qu'un futur changement soit délibéré — voir section 6, sous-section
B2B.

**Hors de portée pour l'instant** : la combinaison « BTP/chantiers ». Les
modules `chantiers` et `cycles` figurent dans `Entreprise::TOUS_LES_MODULES`
mais **aucune route, aucun contrôleur ne les implémente** — il n'y a rien à
scénariser tant que ces modules ne sortent pas du référentiel.

- `tests/Feature/ModulesActifsTest.php` — 6 tests.

---

### Lot 9 — Les relevés du portail FNE — **TERMINÉ (import), RAPPROCHEMENT EN ATTENTE**

Le portail de la DGI porte, par entreprise, des informations qu'aucune API ne
rend : l'adresse déclarée, la commune, le quartier, le solde d'alerte des
stickers, l'activation du timbre de quittance et du bordereau d'achat agricole,
et la liste des points de facturation ouverts. Elles se relèvent à l'écran. Le
relevé arrive sous forme de **deux fichiers par entreprise**, nommés
`<login>_<date>.json` (la fiche) et `<login>_<date>.xlsx` (les points de
facturation). Le login est le NCC.

| Élément | Détail |
|---|---|
| Migration | `2026_08_23_000001_portail_fne_scraping` — trois tables |
| `portail_fne_imports` | un fichier lu, une ligne : nom, date, empreinte SHA-256, contenu brut, statut |
| `portail_fne_fiches` | le JSON interprété, quatorze champs, plus un fourre-tout `champs_inconnus` |
| `portail_fne_points_facturation` | une ligne par point : nom, outil, terminal, statut, établissement, dates DGI |
| Service | `ImportPortailFneService` — `importerDossier()`, `importerFichier()` |
| Commande | `php artisan portail-fne:importer [--dossier=] [--fichier=]` |
| Dossier | `config('selflow.portail_fne.dossier_import')`, variable `PORTAIL_FNE_DOSSIER_IMPORT` |
| Tests | `tests/Feature/ImportPortailFneTest.php` — 7 tests |

**Ce que l'import ne fait pas, et c'est le point important.** Il n'écrit rien
dans `entreprises`. Trois des champs relevés — `timbre_quittance`, `bapa`,
`sticker_solde_alerte` — commandent le comportement fiscal de l'application :
les recopier automatiquement ferait changer une facture parce qu'un fichier a
été déposé dans un dossier, sans que personne ne l'ait décidé, et sur la foi
d'un relevé dont rien ne garantit la fraîcheur.
`PortailFneFiche::ecartsAvecEntreprise()` montre les différences ; **les
appliquer reste à faire, et doit se voir avant de s'appliquer.**

Trois décisions de lecture, prises contre des façons de faire plus simples :

- **le nom du fichier fait foi pour le rattachement.** Un fichier hors
  nomenclature est refusé et signalé plutôt que rattaché au hasard : ranger le
  relevé fiscal d'un client chez un autre ne se répare pas ;
- **l'empreinte du contenu tient lieu de marque de traitement**, pas le
  déplacement du fichier. Le dossier d'origine reste intact et la commande se
  relance sans précaution ;
- **la lecture du tableur suit les en-têtes, jamais les positions.** Le portail
  peut réordonner ses colonnes ; un import qui compte les colonnes rangerait
  alors un statut dans un identifiant d'établissement.

**Le ramassage est planifié**, dans `routes/console.php` :
`portail-fne:importer` toutes les heures, sans chevauchement, sortie dans
`storage/logs/portail-fne.log`. Toutes les heures et non toutes les minutes :
un relevé se produit au mieux une fois par jour. Le passage est sans effet
quand rien n'a changé, l'empreinte reconnaissant un fichier déjà lu — il n'y a
donc rien à préparer avant le premier passage, et rien à déplacer après.

Le scraper, lui, reste extérieur à Selflow : l'application ne va chercher les
relevés nulle part, elle lit un dossier. Ce qui dépose les fichiers dans ce
dossier — un script lancé à la main, une tâche planifiée, un dossier
synchronisé — ne la regarde pas.

Le premier relevé réel (`1864699A`, DC-KNOWING CGA, 21/08/2026) est en base. Le
rapprochement montre déjà sept écarts, dont le timbre de quittance et le
bordereau d'achat actifs au portail et inactifs dans Selflow, et un solde
d'alerte de 5 000 stickers contre 5. **À arbitrer avec le propriétaire** : c'est
Selflow qui a tort, ou c'est le portail qui porte une valeur par défaut.

---

### Lot 10 — Du rejet de la DGI au relèvement du portail — **POSÉ, MIGRATION À APPLIQUER**

Le lot 9 rangeait des relevés que rien ne consultait. Celui-ci relie les deux
bouts : une pièce refusée par la plateforme déclenche un relèvement, et le
relevé qui arrive sert à expliquer le refus.

**Le point de départ était un trou.** `FneService::messageRejet()` assemblait
déjà un message précis — champ fautif, valeur envoyée, raison de la DGI — et ce
message finissait dans un `Log::warning` (`NormaliserFactureFne.php:107`). Un
rejet survenu la nuit ne laissait, au matin, qu'une ligne dans un fichier que
personne ne relit. **On ne diagnostique pas ce qu'on n'a pas gardé.**

| Élément | Détail |
|---|---|
| Migration | `2026_08_24_000001_fne_rejets_et_demandes_releve` — deux tables |
| `fne_rejets` | une pièce refusée : pièce, login, message, champs mis en cause, statut, diagnostic |
| `portail_fne_demandes` | la file des relevés attendus : login, motif, statut, import qui l'a servie |
| Service | `DiagnosticFneService::diagnostiquer()` — lit, compare, n'écrit rien |
| Commandes | `portail-fne:demandes [--json]`, `fne:diagnostiquer-rejets [--rejet=] [--tous]` |
| Planification | `fne:diagnostiquer-rejets` à la 10e minute, juste après le ramassage |
| Tests | `tests/Feature/DiagnosticRejetFneTest.php` — 10 tests |

**Ce que le rapprochement produit.** À la place de la piste générique écrite en
dur dans `pisteDeCorrection()` :

> « Le nom du point de vente doit être déclaré à l'identique sur votre espace FNE. »

il rend un constat :

> « Vous avez envoyé « FACTURATION SIEGE ». Le portail, relevé le 21/08/2026,
> déclare « FACTURATION SIÈGE ». Le plus proche est « FACTURATION SIÈGE ». »

**Rien ne se corrige tout seul, et c'est le point du lot.** Le service lit et
compare ; il n'écrit ni dans la pièce, ni dans l'entreprise, ni dans le
paramétrage. Trois raisons, dont la première est bloquante :

1. la règle d'or l'interdit — `timbre_quittance`, `bapa` et
   `sticker_solde_alerte` commandent le comportement fiscal ;
2. **on ne sait pas qui a raison.** Les sept écarts de DC-KNOWING le montrent :
   un solde d'alerte à 5 000 au portail contre 5 dans Selflow, où 5 000
   ressemble fort à une valeur par défaut jamais touchée ;
3. **une facture certifiée à tort est pire qu'un rejet.** Un rejet se voit et se
   répare ; une correction automatique qui « fait passer » la pièce produit un
   document opposable, transmis à la DGI, différent de ce que l'entreprise croit
   avoir émis. C'est exactement ce que les six écarts de la règle d'or décrivent.

Quatre décisions de conception, prises contre des façons de faire plus simples :

- **`FneService` n'est pas touché.** Les champs rejetés se relisent dans le
  corps brut de la réponse, que le service conserve déjà sous `errors.api_error`
  — plutôt que de découper le message français, qui est fait pour être lu. Les
  quatre points de rejet (`NormaliserFactureFne`, `NormaliserAchatBapaJob`,
  `BatchNormalisationJob`, `FneDashboardControleur`) reçoivent une ligne chacun ;
- **la demande de relevé s'ouvre dans `FneRejet::consigner()`**, pas chez
  l'appelant. Confier cette ouverture aux quatre endroits qui normalisent une
  pièce, c'est s'assurer qu'un cinquième l'oubliera ;
- **une demande n'est fermée que par l'arrivée d'un fichier**, jamais par la
  parole du scraper. Un scraper qui échoue en silence laisse sa demande ouverte,
  et c'est le seul endroit où l'on verra qu'il ne fonctionne plus ;
- **un champ que le portail n'affiche pas est dit hors de portée**, pas passé
  sous silence : `clientNcc` met en cause le NCC du *client*, absent du portail
  de l'entreprise. Un diagnostic muet se lit comme un diagnostic favorable.

**Le contrat avec le scraper tient en une commande.**
`php artisan portail-fne:demandes --json` rend la liste des logins attendus, et
rien d'autre :

    ["1864699A", "2201455B"]

Selflow ne sait pas comment le portail est consulté — script lancé à la main,
tâche planifiée, navigateur piloté — et n'a pas à le savoir. Il dit ce qu'il
attend ; le scraper vient le lui demander et dépose ses fichiers dans le
dossier d'import. Le scraper lui-même reste à écrire, et arrive prochainement.

**Migration appliquée** le 24/08/2026 : `fne_rejets` et `portail_fne_demandes`
sont en base réelle, et les deux commandes répondent.

#### L'environnement du scraper — posé le 24/08/2026

- **`SCRAPER-PORTAIL-FNE/`** accueillera le script, sur le modèle de
  `PASSERELLE-COMPTAFLOW/`. Il porte déjà `CONSIGNES-POUR-LE-SCRAPER.md` : la
  file à lire, la nomenclature des fichiers, les quatorze clés du JSON avec un
  exemple réel, les en-têtes du tableur, et ce que le scraper n'a **pas** à
  faire — ne rien déplacer, ne fermer aucune demande, ne toucher à aucune table.
  Une exception `.gitignore` a été ajoutée : la règle `*.md` masquait le fichier,
  comme elle avait masqué `CLAUDE.md` en son temps.
- **Le dossier d'import a déménagé** de `Pictures/k` vers
  `storage/app/portail-fne/`, l'endroit que `config/selflow.php` désignait déjà
  par défaut. Les deux relevés réels de DC-KNOWING l'ont suivi ; l'import les a
  reconnus à leur empreinte et n'a rien redoublé — la preuve, au passage, que le
  mécanisme tient même quand les fichiers changent de place. `.env` pointe
  désormais dessus.
- **`deploy-production.sh` dit enfin les deux choses qui manquaient** : créer
  `storage/app/portail-fne` (son contenu n'est pas versionné, donc le dossier
  n'arrive pas avec le dépôt), et poser la ligne cron du planificateur. Sans
  elle, ni la reprise des écritures Comptaflow, ni le ramassage des relevés, ni
  le rapprochement des rejets ne tournent — et rien ne le signale.

**Reste à recevoir :** le script du scraper lui-même. Il n'a qu'à lire
`portail-fne:demandes --json` et déposer ses fichiers ; tout le reste est
branché. — **Reçu le 26/08/2026, voir le lot 12.**

### Lot 11 — L'écran des pièces refusées — **TERMINÉ**

Le rapprochement du lot 10 écrivait son constat chaque heure. **Personne ne
pouvait le lire.** Une facture refusée la nuit, un diagnostic posé à 1 h 10, et
rien à l'écran : tout le mécanisme était aveugle.

| Élément | Détail |
|---|---|
| Contrôleur | `RejetFneControleur` — `index`, `diagnostiquer`, `appliquer`, `resoudre` |
| Vue | `Vues/fne/rejets.blade.php` |
| Routes | `admin.fne.rejets*`, dans le groupe `modules:comptabilite` |
| Barre latérale | « Pièces refusées », avec le compte des rejets ouverts en pastille |
| Tests | `tests/Feature/EcranRejetsFneTest.php` — 8 tests |

**Le rejet se referme enfin tout seul.** `STATUT_RESOLU` était déclaré et rien ne
le posait : une facture qui repartait et passait laissait son refus ouvert pour
toujours. `FneRejet::resoudre()` est appelée aux six points de succès. Une file
qui ne se vide jamais cesse d'être lue, et c'est ainsi qu'on rate le rejet
suivant.

**Un seul geste de correction est offert, et il est descriptif.** Renommer un
point de vente pour l'aligner sur ce que le portail déclare — le même geste que
depuis l'écran des points de vente, à ceci près que la valeur du portail est
affichée en face. Il ne s'offre **que** si le portail ne déclare qu'un seul nom :
avec deux, la machine ne choisit pas à la place de qui a établi la pièce.

Appliquer la correction **rouvre le rejet et efface son diagnostic** : celui-ci
décrivait un écart qui n'existe plus, et un constat périmé affiché comme actuel
est pire que pas de constat.

**Les écarts de fiche restent montrés et non appliqués.** Ils ont leur tableau en
bas d'écran, avec la raison écrite en clair et un lien vers les paramètres de
l'entreprise. Aucune route ne permet de les recopier ; une épreuve le vérifie.

Deux prises au vol par des épreuves déjà en place, et c'est ce qu'elles valent :

- **`HabilitationsTest`** a refusé les quatre routes neuves, non classées. En les
  rangeant, un choix s'est imposé : `appliquer` va sous **`gestion_pdv`** et non
  `factures_vente` comme ses trois voisines. La ranger avec les factures
  ouvrirait à qui saisit des ventes une porte latérale vers le renommage des
  points de vente de l'entreprise.
- **`FIELD()` n'existe que chez MySQL.** L'ordre d'affichage — ouverts d'abord,
  classés en dernier — passe par un `CASE`. C'est le piège qui fait déjà tomber
  `TableauDeBordGeneralTest` avec `CONCAT` : une requète qui ne passe pas sur
  SQLite n'est jamais éprouvée.

Le cloisonnement répond **404 et non 403**, comme le lot 8.3 l'a établi : un 403
confirmerait que la pièce existe, et les identifiants sont séquentiels. Vérifié
par mutation — la garde retirée, l'épreuve tombe.

**Ce que les épreuves ne couvrent pas, et il faut le savoir :** `resoudre()` est
éprouvée comme méthode, pas comme branchement. Les six appels aux points de
succès ont été posés à la lecture ; les éprouver demanderait de simuler la
plateforme à chacun d'eux.

#### Deux fins de course — 24/08/2026

**Un diagnostic ne vieillit plus en silence.** Un rejet passé à `diagnostique`
n'était plus jamais repris : si le relevé du jour était périmé, le constat
restait périmé avec lui, et l'écran affichait comme actuel un rapprochement fait
sur des données mortes. Le diagnostic porte désormais **l'identité du relevé**
(`releve.fiche_id`) et non sa seule date — deux relevés du même jour existent, et
une date formatée se compare mal. La commande horaire reprend les rejets
diagnostiqués et écarte ceux qui décrivent déjà le dernier état connu, pour ne
pas réécrire la même chose toutes les heures. `--tous` force la réécriture.

Un diagnostic sans identité de relevé — ceux écrits avant que le champ n'existe —
est tenu pour dépassé et rejoué une fois.

**Une demande qui traîne se voit.** Une demande ouverte est un signal voulu :
c'est ainsi qu'on voit qu'un scraper ne répond plus. Encore fallait-il que
quelqu'un le voie — sans l'âge, une demande de mars ressemble à une demande de
ce matin, et la seule façon de s'en apercevoir était de remarquer un chiffre qui
ne bouge pas, ce qui suppose de l'avoir remarqué la veille.

| Où | Quoi |
|---|---|
| `config('selflow.portail_fne.delai_alerte_heures')` | 24 h par défaut — un relevé se produit au mieux une fois par jour |
| Écran des rejets | bandeau rouge, login par login, avec l'âge et les trois causes possibles |
| `portail-fne:demandes` | colonne « Attend depuis » et avertissement |
| `fne:diagnostiquer-rejets` | avertissement à l'écran **et** `Log::warning` dans `portail-fne.log` |

Les trois causes sont nommées à l'écran parce qu'aucune ne se corrige toute
seule : le relevé n'est pas lancé, il dépose ailleurs que dans le dossier
d'import, ou le NCC de l'entreprise ne correspond pas au login du portail.

**`STATUT_ABANDONNEE` a enfin un sens.** Une demande est ouverte par un rejet ;
quand tous les rejets de ce login sont refermés — les pièces sont passées — le
relevé n'a plus d'objet. La laisser ouverte ferait passer pour une panne du
scraper ce qui n'est qu'une demande devenue sans cause. L'abandon est
conditionnel : dix rejets ne partagent qu'une demande, et en refermer un seul ne
l'éteint pas.

Le signalement passe aussi quand il n'y a **aucun** rejet à rapprocher : une
demande peut traîner seule, le rejet qui l'avait ouverte ayant été classé
entre-temps. C'est même le cas où personne ne regarde.

Trois épreuves de plus, vérifiées par mutation : l'ancien comportement rétabli,
le rafraîchissement tombe.

### Lot 12 — Le scraper du portail — **TERMINÉ, ÉPROUVÉ SUR LE PORTAIL LE 27/08/2026**

Le script qui manquait depuis le lot 10 est arrivé. Il vient d'un script
d'automatisation écrit à part (`Documents/scraping/fne.js`), qui faisait déjà
l'essentiel : Playwright, connexion, page Paramétrage, export Excel, et la
bonne nomenclature `<login>_<AAAAMMJJ>`. Il vit désormais dans
`SCRAPER-PORTAIL-FNE/`, branché sur la file et sur le dossier d'import.

| Périmètre | Fichier |
|---|---|
| Le scraper | `SCRAPER-PORTAIL-FNE/fne.js` |
| Vérification hors portail | `SCRAPER-PORTAIL-FNE/verifier-extraction.js` — 18 contrôles |
| Accès aux portails | `SCRAPER-PORTAIL-FNE/identifiants.json` — **ignoré par git** |
| Configuration | `SCRAPER-PORTAIL-FNE/.env`, modèle dans `.env.exemple` |
| Mode d'emploi | `CONSIGNES-POUR-LE-SCRAPER.md`, section « Le scraper livré » |

**Six écarts corrigés entre le script d'origine et le contrat.**

- **Il ne lisait pas la file.** Il prenait un login en argument. Il appelle
  maintenant `php artisan portail-fne:demandes --json` depuis la racine du
  projet, et boucle. Une file vide n'ouvre même pas le navigateur.
- **Il déposait par HTTP vers `URL_SERVER`, qui n'existe pas.** Selflow n'a
  aucune route d'upload : il lit un dossier. Le dépôt local est devenu le mode
  normal, `PORTAIL_FNE_DOSSIER_IMPORT` étant lu dans le `.env` du projet —
  rien à recopier. L'envoi HTTP reste possible pour le jour où le scraper
  tournera ailleurs que sur le poste de Selflow.
- **La clé d'API partait dans le fichier.** Son en-tête promettait qu'elle
  n'était « jamais consignée », mais le filtre n'écartait que les champs de
  type `password` — or le portail la rend dans un champ texte ordinaire. Le
  fichier déposé est lu, archivé en `contenu_brut` et conservé : ce qui y entre
  y reste. Elle est désormais écartée par son libellé.
- **Les interrupteurs et les listes déroulantes n'étaient pas lus** :
  l'extraction ne regardait que `input` et `textarea`. « Timbre de quittance »
  et « Bordereau d'achat de produits agricoles » commandent le comportement
  fiscal ; les manquer revenait à relever une fiche muette sur l'essentiel.
  Piège rencontré à la vérification : un `<button role="switch">` porte
  `type="submit"` par défaut et se faisait écarter comme un bouton ordinaire.
  **Le rôle prime maintenant sur le type.**
- **Les libellés tombent sur les clés du référentiel.** Le portail écrit ce
  qu'il veut ; la comparaison se fait sans accents, sans casse et sans
  ponctuation, puis la clé canonique est réécrite au caractère près. Vérifié :
  les quatorze clés produites correspondent exactement, ordre compris, à
  `ImportPortailFneService::CHAMPS_FICHE`. Un libellé inconnu n'est pas perdu —
  il part tel quel et Selflow le range dans `champs_inconnus`.
- **Un échec ne se lit plus comme un succès.** En dessous de quatre champs
  reconnus, rien n'est déposé et la demande reste ouverte : sans ce garde-fou,
  une page changée aurait produit une fiche de quatorze `null`, servi la
  demande, et affiché quatorze écarts au rapprochement. Un login qui échoue
  n'arrête pas les autres et laisse une capture dans `erreurs/`. Une file
  illisible remonte sa cause — « base injoignable » — au lieu de passer pour
  une file vide.

**Un contexte de navigateur neuf par login.** Deux entreprises ne partagent
jamais une session : ranger le relevé de l'une chez l'autre ne se répare pas.

**Ce que le scraper ne fait toujours pas**, et c'est le contrat : il ne ferme
aucune demande, ne déplace rien, ne touche à aucune table. Une demande n'est
servie que lorsque `portail-fne:importer` range réellement un fichier portant
ce login.

**Les mots de passe vivent dans `identifiants.json`, côté scraper.** Selflow ne
transmet que des logins — c'est écrit dans les consignes et ce n'est pas une
commodité : mettre des mots de passe de portail en clair dans la base, ou les
faire sortir d'une commande artisan, aurait étendu la surface d'un cran pour
rien. Le fichier est ignoré par git, comme le `.env` du scraper.

#### Le lancement automatique — posé le 26/08/2026

Accroché au planificateur déjà en place (`routes/console.php`), et non à une
seconde tâche Windows que personne ne penserait à surveiller. Tout finit dans
`storage/logs/portail-fne.log`, avec le ramassage et le rapprochement.

| Quand | Quoi |
|---|---|
| Toutes les heures, minute 40 | `node fne.js` — sert la file |
| 02:30 chaque nuit | `node fne.js --tous` — tient les fiches à jour |

Le cycle : un rejet à 15:05 ouvre une demande, le scraper la sert à 15:40,
`portail-fne:importer` range à 16:00, `fne:diagnostiquer-rejets` rapproche à
16:10.

- **Toutes les heures et non toutes les dix minutes.** File vide — le cas
  ordinaire — le passage s'arrête sans ouvrir le navigateur et ne coûte rien.
  File pleine, il ouvre une session sur le portail de la DGI : y retourner six
  fois par heure avec un mot de passe éventuellement faux ferait bloquer le
  compte.
- **`runInBackground`**, sans quoi la minute du planificateur reste occupée
  pendant les dizaines de secondes d'un relevé. Verrou expirant à 30 min (2 h la
  nuit) : un navigateur planté ne doit pas condamner tous les passages suivants.
- **Allumé sur ce poste** (`PORTAIL_FNE_SCRAPER_ACTIF=true`), après vérification
  que le drapeau était trop prudent : sans `identifiants.json`, les deux
  passages sortent en code 0 sur une seule ligne — « rien à relever » — et non
  en erreur. Le journal ne se remplit donc pas pour rien, et le mot de passe
  manquant n'est signalé qu'au moment où une demande le réclame vraiment. Le
  drapeau reste à `false` dans la configuration par défaut, pour un poste où le
  scraper n'existe pas.
- **Deux chemins absolus, en barres obliques.** Le lanceur VBS de la tâche
  Windows portait déjà le chemin absolu de PHP : son environnement n'a pas le
  PATH d'un terminal. `PORTAIL_FNE_NODE` et `PHP_BINAIRE` suivent la même
  logique — `node` ou `php` tout court marche à l'essai et échoue une fois
  planifié, le pire des deux mondes. Barres obliques parce que phpdotenv
  interprète les échappements entre guillemets : `"C:\Program Files\nodejs\…"`
  y perdrait son `n`.

**Vérifié de bout en bout**, et pas seulement listé :

- avec un environnement entièrement vide (`env -i`), le scraper trouve PHP, lit
  la file et rend « rien à relever » — c'est exactement la commande que le
  planificateur exécute ;
- `schedule:list` montre les deux lignes quand le drapeau est levé, rien quand
  il ne l'est pas ;
- `schedule:test` a lancé la tâche **par le planificateur**, en arrière-plan, et
  sa sortie est arrivée dans `storage/logs/portail-fne.log`, à la suite du
  ramassage et du rapprochement. La chaîne entière tourne.

**Il n'est pas figé sur une entreprise.** `portail-fne:demandes` n'est filtré par
aucune entreprise : le NCC de n'importe laquelle entre dans la file dès qu'une
de ses pièces est refusée. Éprouvé sur quatre logins d'un coup — chacun avec son
contexte de navigateur, son échec propre et sa capture nommée à son login,
aucun n'ayant bloqué les autres.

**Une entreprise sans NCC ne peut pas être relevée — tranché, sans suite.**
`FneRejet::consigner()` pose `login = entreprise->ncc`, et
`PortailFneDemande::pour()` rend `null` sur un login vide : un rejet d'une
entreprise sans NCC n'ouvre aucune demande, et rien ne le signale. Six des douze
entreprises en base sont dans ce cas. **Le propriétaire a répondu le 26/08/2026 :
toutes les entreprises actuellement en base sont des jeux d'essai, supprimés
après le développement.** Le trou est donc sans objet sur les données réelles —
en production, une entreprise sans NCC ne peut de toute façon rien normaliser.
Aucune correction n'est faite. Si le cas devait se présenter un jour, deux
issues : signaler à l'écran des rejets, ou refuser la normalisation en amont
avec `CAUSE_LOCALE`.

**Conséquence pour `identifiants.json` :** il ne porte que `1864699A`, le seul
compte dont un relevé réel existe. Y inscrire les NCC d'essai aurait fait ouvrir
chaque nuit des sessions vouées à échouer sur des comptes qui n'existent pas au
portail — et disparaîtront de la base.

**Ce qui reste ouvert :** le scraper reste extérieur à Selflow. Ces deux lignes
de planification sont une commodité, pas une dépendance — les débrancher ne
casse rien, les fichiers arriveront autrement ou n'arriveront pas, et une
demande qui traîne le dira.

**Reste à faire :** un premier passage réel sur le portail. Playwright et
Chromium sont installés, `verifier-extraction.js` passe, les clés correspondent,
la file répond et la commande planifiée fonctionne à vide. Ce qui n'est pas
prouvé, c'est la navigation dans le portail lui-même depuis ce dossier — il y
faut `identifiants.json` et le mot de passe de `1864699A`.

### Lot 13 — Distinguer un refus de la DGI d'une coupure réseau — **POSÉ, MIGRATION À APPLIQUER**

Le propriétaire a décrit le parcours attendu : on lance la normalisation ; si
elle passe, la facture est normalisée ; sinon le scraper va vérifier au portail
— **mais avant cela, on regarde si ce n'est pas simplement un souci réseau.**
Cette dernière étape n'existait pas.

`FneRejet::consigner()` traitait **tout** `success: false` comme un refus de la
DGI et ouvrait une demande de relevé. Or `FneService` rend `success: false`
pour cinq causes sans rapport :

| Message rendu | La DGI a-t-elle examiné la pièce ? |
|---|---|
| `Exception lors de l'appel API FNE : …` (`FneService.php:276`, BAPA `:414`) | non — transport |
| `… a échoué (HTTP 5xx) : …` | non — panne de leur côté |
| `… la réponse de l'API est incomplète` (`:257`) | non — répondu sans verdict |
| `aucune clé API FNE active`, `Normalisation refusée`, `Impossible de normaliser` (`:50, :79, :630`) | non — rien n'est parti |
| `… a échoué (HTTP 4xx) : …` avec `errors` | **oui** |

Une coupure de trente secondes ouvrait donc une demande : le scraper partait
sur le portail, relevait quatorze champs, et le rapprochement comparait ce
qu'aucune DGI n'avait mis en cause. Une file d'alertes sans objet cesse d'être
lue — et c'est ainsi qu'on rate le vrai rejet.

**Second défaut, dans le même endroit.** Les jobs déclarent `tries = 3` et
`backoff = 30`, mais `FneService` attrape lui-même l'exception réseau et rend
`success: false` au lieu de la relancer. Le job n'y voyait qu'un refus métier,
ne relançait pas, et **la mécanique de réessai ne servait jamais** — précisément
dans le seul cas où elle aurait servi.

| Périmètre | Fichier |
|---|---|
| Classification | `FneRejet::classer()`, constantes `CAUSE_DGI` / `CAUSE_RESEAU` / `CAUSE_LOCALE` |
| Colonne | `2026_08_26_000001_cause_des_rejets_fne` — `fne_rejets.cause`, indexée |
| Réessai | `NormaliserFactureFne`, `NormaliserAchatBapaJob` |
| Tests | `tests/Unit/FneRejetCauseTest.php` — 10 tests, **sans base de données** |

**`FneService` n'est pas touché** — la règle d'or tient. La classification lit
ce que le service rend, elle ne change pas ce qu'il envoie. Le prix de ce choix
est qu'elle s'appuie sur le texte des messages : le test fige donc les six
formulations réelles, copiées depuis le service. Si l'une change, le test tombe
au lieu que la classification retombe en silence sur « la DGI a refusé ».

**En cas de doute, c'est `CAUSE_DGI`.** Un relevé de trop fait travailler le
scraper pour rien ; un relevé manquant laisse une facture refusée sans
explication, et c'est le plus cher des deux.

**Le réessai ne consigne rien tant qu'il reste des tentatives.** Trois rejets
pour une seule coupure rempliraient l'écran de trois refus qui n'en font qu'un ;
seule la dernière tentative laisse une trace, avec `cause = 'reseau'` et sans
demande de relevé.

**Les lignes déjà en base restent à `cause = NULL`.** Elles ont été consignées
avant que la distinction existe ; leur prêter une cause après coup serait
inventer un constat.

#### Le filtre par cause, à l'écran — ajouté le 26/08/2026

Un rejet réseau et un refus de la DGI se ressemblaient à l'écran : même
étiquette « À traiter », même bouton « Rapprocher ». On cherchait un écart de
paramétrage là où il n'y avait eu qu'une connexion perdue.

| Ce qui change | Où |
|---|---|
| Quatre onglets — Toutes, Refus DGI, Réseau, Bloqué ici, Cause inconnue | `RejetFneControleur::index()` |
| Une pastille de cause sur chaque rejet, avec son explication en infobulle | `Vues/fne/rejets.blade.php` |
| Un bandeau qui dit quoi faire, par cause | idem |
| Tests | `tests/Feature/FiltreCauseRejetsFneTest.php` — 9 tests |

Cinq choix qui viennent d'un défaut réel, et non d'un goût :

- **une cause inventée dans l'URL ne filtre rien**, au lieu de rendre une liste
  vide. Un écran vide se lit « aucun rejet », soit exactement le contraire de
  ce qu'il faut comprendre ;
- **le vide filtré et le vide réel ne disent pas la même chose.** « Aucun rejet
  pour cette cause — 12 au total » n'est pas « aucune pièce refusée » ;
- **la pagination emporte le filtre** (`withQueryString`), sans quoi la page 2
  revient sur la liste entière et l'on croit que le filtre a lâché ;
- **les rejets sans cause restent atteignables** par un onglet dédié. Les
  lignes d'avant la migration ont `cause = NULL` ; sans entrée qui les désigne,
  elles disparaissaient dès qu'on filtrait ;
- **le bouton « Rapprocher » est retiré des rejets réseau**, et la route les
  refuse aussi — un navigateur poste ce qu'il veut. Rapprocher une pièce que la
  DGI n'a jamais lue déguiserait un incident de transport en écart de données.

**Un mensonge d'interface corrigé au passage.** Quand aucun relevé n'était
disponible, l'écran répondait « Une demande a été déposée ; le rapprochement se
fera dès son arrivée ». **Rien ne la déposait sur ce chemin** : la demande naît
à la consignation du rejet, dans `FneRejet::consigner()`, et elle existe déjà ou
n'existera pas. Vrai par accident pour un refus DGI, faux pour tout le reste.
Une interface qui annonce un geste qu'elle n'a pas fait se paie au moment où
l'on attend le résultat.

**Épreuves :** 9 tests neufs, et les 115 tests du périmètre FNE — `FnePayloadTest`
compris — repassent. Sur le lot complet : 757 tests verts sur 758. L'échec
restant, `TableauDeBordGeneralTest`, est **antérieur et sans rapport** :
`AdminControleur.php:266` emploie `CONCAT`, que SQLite ne connaît pas, alors que
les épreuves tournent sur SQLite en mémoire. À corriger un jour, dans un autre
lot. Le lot complet demande aussi `-d memory_limit=512M` : à 128 Mo,
`ImportPortailFneTest` épuise la mémoire en écrivant son tableur.

**Migration appliquée** le 26/08/2026 : `fne_rejets.cause` est en base réelle.

Deux points laissés en l'état
et à trancher : `BatchNormalisationJob` consigne toujours sans classer (un
`throw` avorterait le lot entier), et le chemin synchrone de
`FneDashboardControleur` rend déjà l'erreur à l'écran. Ni l'un ni l'autre
n'ouvre de fausse demande — `consigner()` classe désormais dans tous les cas —
mais ni l'un ni l'autre ne réessaie.

### Lot 14 — Surveiller le portail, et non plus seulement le lire — **TERMINÉ**

Deux comparaisons existaient. `PortailFneFiche::ecartsAvecEntreprise()` répond à
« le portail et Selflow disent-ils la même chose ? ».
`fne:diagnostiquer-rejets` répond à « pourquoi cette pièce a-t-elle été
refusée ? ». **Aucune ne répondait à « quelqu'un a-t-il touché au portail depuis
hier ? »** — un timbre de quittance désactivé un mardi soir n'apparaissait donc
nulle part, jusqu'au jour où une facture était refusée, et l'on cherchait alors
ce qui avait bougé sans savoir quand.

| Périmètre | Fichier |
|---|---|
| Écarts d'un relevé au précédent | `PortailFneFiche::precedente()`, `::ecartsAvecPrecedente()`, `CHAMPS_SUIVIS` |
| Points apparus, disparus, modifiés | `PortailFnePointFacturation::changementsDepuisLePrecedent()` |
| Commande | `portail-fne:changements [--login=] [--silencieux]` |
| Planification | toutes les heures, minute 15, `--silencieux` |
| Tests | `tests/Feature/ChangementsPortailFneTest.php` — 11 tests |

**La comparaison porte sur le contenu, jamais sur l'empreinte du fichier.**
Le tableur du portail embarque un horodatage de génération — vérifié dans le
relevé réel : `dcterms:created` vaut `2026-08-20T09:26:26Z` pour un fichier
téléchargé le 21/08. Deux exports identiques peuvent donc différer octet pour
octet, et se fier aux octets annoncerait un changement chaque nuit. Un test
fige ce point : les empreintes des deux relevés y sont volontairement
différentes, et rien n'est signalé quand le contenu est le même.

Cinq décisions, chacune contre un faux signal :

- **un premier relevé n'annonce rien.** Sinon chaque entreprise afficherait
  quatorze changements le jour de son arrivée, et le signal deviendrait bruit ;
- **`null` et `''` ne se signalent pas l'un l'autre** — le portail dit « rien »
  de deux façons ;
- **un champ qui passe à vide compte pourtant comme un changement**, à la
  différence du rapprochement avec l'entreprise : ici les deux valeurs viennent
  du même portail lu par le même scraper, et une valeur qui disparaît est soit
  un changement réel, soit un défaut d'extraction — les deux méritent d'être
  vus ;
- **l'identité d'un point est son `etablissement_id`**, pas son nom : un point
  renommé reste le même point. Le voir comme une disparition suivie d'une
  apparition noierait le renommage — or c'est la cause du rejet le plus
  fréquent, « le nom du point de vente doit être déclaré à l'identique » ;
- **`--silencieux` au passage planifié.** Un journal qui répète chaque heure
  « aucun changement » cesse d'être lu, et c'est le jour où il dit quelque chose
  qu'on ne le lira pas.

**Les trois champs fiscaux sont nommés à part.** Quand `timbre_quittance`,
`bapa` ou `sticker_solde_alerte` bougent au portail, le rapport le dit
explicitement — et ne recopie rien. Un test vérifie que l'entreprise ressort
inchangée, octet pour octet, après le passage. C'est la règle d'or : **un
constat, pas une décision.**

Éprouvé sur un cas réel simulé — déménagement de commune, timbre de quittance
désactivé, point de facturation renommé — puis la base rendue à son état
d'origine (2 imports, 1 fiche, 1 point).

**Le cycle horaire complet :**

| Minute | Quoi |
|---|---|
| :00 | `portail-fne:importer` — range les fichiers déposés |
| :10 | `fne:diagnostiquer-rejets` — rapproche les pièces refusées |
| :15 | `portail-fne:changements --silencieux` — dit ce que le portail a changé |
| :40 | le scraper — sert la file |
| 02:30 | le scraper, passage complet |

### Lot 15 — Le relevé ne s'enregistre plus quand il ne dit rien — **TERMINÉ**

**Le premier relevé réel a eu lieu le 27/08/2026**, et il est passé du premier
coup : connexion, navigation, 14/14 champs reconnus, export du tableur. Le JSON
déposé est **identique octet pour octet** à celui posé à la main le 21/08 —
le scraper reproduit exactement le fichier de référence. Le lot 12 passe donc
de « premier relevé réel à faire » à éprouvé.

Ce relevé a mis en évidence ce que ce lot corrige.

#### Ce qui n'allait pas

Chaque passage écrivait une fiche et un jeu de points, **que le portail ait
bougé ou non**. L'empreinte SHA-256 du fichier ne pouvait rien y faire : le
tableur du portail embarque un horodatage de génération (`dcterms:created`), et
deux exports identiques diffèrent donc octet pour octet. Vérifié en
décompressant les deux : seul `docProps/core.xml` change, toutes les feuilles
de données sont identiques.

La conséquence coûteuse n'était pas la taille de la table. C'est que
`DiagnosticFneService::diagnosticEstAJour()` compare l'identifiant de la
dernière fiche : **une fiche neuve, fût-elle identique au mot près, périmait
chaque nuit tous les diagnostics de rejets**, rejoués pour aboutir au même
constat.

#### Ce qui a été fait

La comparaison porte sur le **contenu lu**, jamais sur les octets — le même
principe que `changementsDepuisLePrecedent()`, qui l'avait déjà tranché pour
les points.

Un relevé qui redit ce que la base sait déjà **ne crée plus aucune ligne, nulle
part** — pas même une ligne d'import. La ligne existante est *confirmée* :
`dernier_releve_le` avance, `releves` monte d'une unité.

| Table | Règle |
|---|---|
| `portail_fne_imports` | une ligne par **contenu**, pas par passage. Un relevé identique confirme la ligne existante |
| `portail_fne_fiches` | écrite seulement quand le contenu change |
| `portail_fne_points_facturation` | **tout le jeu ou rien**. N'écrire que le point modifié ferait répondre « le portail ne déclare qu'un point de vente » là où il y en a cinq |

**Trois dates à ne pas confondre** sur une ligne d'import :

| Colonne | Ce qu'elle dit |
|---|---|
| `date_scraping` | depuis quel relevé le portail affiche **ce** contenu |
| `dernier_releve_le` | quand on l'a vu pour la dernière fois |
| `created_at` | quand Selflow l'a rangé |

Qui veut savoir si le scraper tourne encore lit `dernier_releve_le`. Qui veut
savoir depuis quand un paramétrage est en place lit `date_scraping`. Écraser la
seconde effacerait l'ancienneté du paramétrage, qui est justement ce qu'on
cherche quand une pièce est refusée.

`releves` ne monte **qu'au changement de date de relevé** : le dossier d'import
est relu toutes les heures, et compter chaque relecture ferait dire à ce
compteur le nombre de passages du planificateur plutôt que le nombre de relevés.

La comparaison se fait sur l'empreinte du contenu **canonicalisé**
(`empreinteDuContenu()`), pas sur les octets : `"5000"` ou `5000`, `"*"` ou
`null`, `true` ou `"true"`, colonnes du tableur réordonnées, ligne vide en fin
de feuille — autant de libertés que le portail s'autorise et qui ne sont pas
des changements. Un champ **inédit**, lui, en est un.

Elle porte sur le **dernier** relevé du login, jamais sur n'importe lequel : un
portail qui passe de A à B puis revient à A a changé deux fois, et rattacher ce
troisième relevé à la ligne A d'origine laisserait B comme état le plus récent
en base — c'est-à-dire un état que le portail n'affiche plus.

Trois conséquences traitées avec :

- **La date d'une fiche n'est plus celle du dernier passage**, mais celle du
  dernier *changement*. L'écran des rejets lit désormais `portail_fne_imports`
  pour dater le relevé, sinon il afficherait « relevé du 15/08 » un 27/08 sur
  un scraper qui tourne parfaitement.
- **`portail-fne:changements --silencieux`** ne rapporte que si le dernier
  changement est celui du dernier passage. Sans ce filtre, le passage planifié
  annoncerait chaque heure une nouvelle vieille de trois semaines — et le
  drapeau, qui existe pour qu'un journal reste lisible, ne servirait plus à
  rien. Lancée à la main, la commande continue de montrer le dernier changement
  connu quelle que soit sa date.
- **Une fiche orpheline se rattache après coup.** Un relevé arrivé avant que
  l'entreprise n'existe portait un `entreprise_id` nul, et le rattachement se
  faisait tout seul au relevé suivant. Ne plus rien écrire l'aurait laissée
  orpheline pour toujours.

| Périmètre | Fichier |
|---|---|
| L'empreinte du contenu | `ImportPortailFneService::empreinteDuContenu()`, `ficheCanonique()`, `pointsCanoniques()` |
| La confirmation sans écriture | `ImportPortailFneService::dernierReleveDeMemeContenu()`, `confirmerLeReleve()` |
| Les colonnes et la reprise | `2026_08_27_000001_relever_sans_redire` — reprise des lignes déjà en base et repli des doublons |
| Les deux dates | `RejetFneControleur`, `Vues/fne/rejets.blade.php` |
| Le silence du planificateur | `ChangementsPortailFne::changementDuDernierPassage()` |
| Tests | `ImportPortailFneTest` — 6 nouveaux, 13 au total |

**Éprouvé sur le portail réel le 27/08.** Trois relevés d'affilée : la base
n'a pas gagné une ligne, et `releves` est passé à 3 sur la ligne du 21/08. La
migration a replié les doublons laissés par la version précédente — 4 lignes
d'import ramenées à 2, un seul jeu de points.

#### Ce que seul l'essai réel a trouvé

La première version comparait les points stockés via `attributesToArray()`, qui
rend les dates sous leur forme sérialisée (« 2026-07-30T10:38:40.000000Z ») là
où un relevé frais porte des objets `Carbon`. Les deux ne pouvaient jamais être
égaux : **un tableur identique revenait indéfiniment comme un changement.**

Les tests ne l'ont pas vu, parce que leurs tableurs n'avaient aucune colonne de
date — les deux côtés valaient `null`, et la comparaison croyait tout comparer.
C'est le relevé du 27/08 sur le vrai portail qui l'a montré, en ressortant
« importé » là où « inchangé » était attendu.

Les valeurs sont désormais relues par accesseur, et `Créé à` / `Mise à jour à`
figurent dans les tableurs de test — vérifié en réintroduisant le défaut : le
test échoue.

#### Trois constats à part, non corrigés

- **`php artisan test` s'arrête à 423 tests sur 775** — mémoire épuisée dans
  `zipstream-php` pendant `ImportPortailFneTest`, et le rapport annonce
  « passed » quand même. Reproduit avant comme après ce lot, donc antérieur.
  Contournement : `php -d memory_limit=1G vendor/bin/phpunit` passe la suite
  entière — 775 tests, 774 verts.
- **`TableauDeBordGeneralTest` échoue** : `CONCAT` n'existe pas en SQLite. Le
  tableau de bord marche sous MySQL, le test ne peut pas le vérifier. Sans
  rapport avec ce lot.
- **Les noms des points de vente s'affichent en double** dans le diagnostic
  quand deux relevés tombent le même jour : `DiagnosticFneService` déduplique
  les identifiants d'établissement (`array_unique`) mais pas les noms.
  Cosmétique.

### Lot 16 — Le relevé des factures reçues — **CHAÎNE COMPLÈTE, EN ATTENTE DE DONNÉES**

`SCRAPER-PORTAIL-FNE/achats.js`. `fne.js` n'a pas été touché : seule sa liste
d'exports a été complétée, pour que la connexion — qui porte l'attente
d'hydratation Next.js — ne soit pas recopiée. `verifier-extraction.js` continue
de passer ses 17 contrôles, ce qui le prouve.

#### Ce que la reconnaissance a trouvé, le 27/08/2026

Un mode `--reconnaissance` explore le portail et écrit un rapport, au lieu
d'exiger un aller-retour à la main dans les outils de développement. Il a rendu
tout ce qui manquait :

| | |
|---|---|
| Page | `/fr/invoice-management?type=received` (et `?type=issued` pour les pièces émises) |
| Liste | `GET /ws/invoices?page=&perPage=&fromDate=&toDate=&sortBy=-date&listing=received&complete=true` |
| Enveloppe | `{ data: [...], page, perPage, total }` |
| Totaux | `GET /ws/invoices/details?…&listing=received` — les KPI du tableau de bord, pas la liste |

**L'API est celle que Selflow connaît déjà** : `http://54.247.95.108/ws`, la
même base que `FNE_API_URL_SANDBOX`. À vérifier un jour : la clé d'API de
l'entreprise ouvre-t-elle `/ws/invoices` ? Si oui, le navigateur devient inutile
pour ce relevé.

#### Le moule d'un enregistrement

Relevé sur les pièces **émises** (112 en base) faute de pièce reçue :

| Champ | Ce qu'il porte |
|---|---|
| `reference` | le numéro FNE — `B1864699A26000000016` |
| `token` | le code de vérification, celui du QR |
| `type` / `subtype` | `invoice` / `purchase_slip`, `normal`, `refund`, `proforma` |
| `isRne`, `rne` | la distinction reçu / facture, que Selflow connaît déjà |
| `date` | ISO |
| `amount`, `vatAmount`, `fiscalStamp`, `discount`, `totalBeforeTaxes`, `totalTaxes`, `totalAfterTaxes`, `totalCustomTaxes`, `totalDue` | des **nombres**, pas des libellés formatés |
| `items[]` | **le détail des lignes** — un achat pourra donc mouvementer un stock |
| `company{}` | l'entreprise émettrice, avec son `declarantNumber` |
| `clientNcc`, `clientCompanyName` | la contrepartie |
| `customTaxes[]` | les taxes personnalisées |

#### Ce qui a été appris en chemin

- **Le clic sur un menu Next.js rend la main avant la navigation.** Le premier
  essai a relevé le tableau de bord en croyant relever les factures. La
  navigation se fait désormais par URL, le menu ne servant que de repli.
- **`page.request` ne porte pas le jeton.** Le portail l'envoie en en-tête
  `Authorization`, posé par son JavaScript ; rejouer un appel sans lui rend 401.
  Il est capté au vol sur un appel que la page fait elle-même, **vit en mémoire
  et n'est écrit nulle part** — ni journal, ni fichier déposé, ni rapport.

#### Le dépôt

`storage/app/portail-fne/achats/<login>_<AAAAMMJJ>.json` — un **sous-dossier**,
et non un suffixe : la découpe du nom se fait au dernier `_`, qu'un login peut
contenir. L'import actuel ne lit que la racine du dossier : il ignore ces
fichiers, et la chaîne éprouvée n'est pas perturbée.

Aucun horodatage de génération dans le fichier, conformément à la leçon du lot 15.

#### Où ça en est

**Le relevé fonctionne.** Mais `DC-KNOWING CGA` n'a **aucune facture reçue** —
`total: 0` sur toute la fenêtre 2024-2026. Le fichier déposé porte donc
`factures: []`. Ce n'est pas une panne : l'API répond 200 avec un total franc.

#### Le côté Selflow

| Périmètre | Fichier |
|---|---|
| Les tables | `2026_08_27_000002_factures_recues_du_portail` — `portail_fne_factures_recues` et ses lignes |
| Les modèles | `PortailFneFactureRecue`, `PortailFneFactureRecueLigne` |
| L'import | `ImportFacturesRecuesService` — à côté de `ImportPortailFneService`, jamais dedans |
| La commande | `portail-fne:importer-achats`, planifiée à la minute **05** |
| Tests | `ImportFacturesRecuesTest` — 10 cas, bâtis sur la forme **réelle** de l'API |

**Une facture est un fait, pas un état.** C'est la différence de fond avec les
fiches d'entreprise, que le même dossier historise à chaque relevé. Une fiche est
une photographie qu'on reprend pour voir ce qui a bougé ; une facture est émise,
certifiée, et ne change plus. L'unicité porte donc sur `(login, reference)` — le
numéro FNE — et un second relevé **met à jour** au lieu de dupliquer. La ligne
d'import, elle, suit la même règle qu'au lot 15 : contenu identique, aucune
ligne créée, la précédente est confirmée.

**Table à part, jamais les colonnes d'`achats`.** `achats.numero_fne`,
`achats.normalise`, `achats.fne_*` veulent dire « Selflow a émis cette pièce et
la DGI l'a certifiée » — le cas du bordereau agricole. Une facture reçue a été
certifiée par le fournisseur. Y écrire ferait mentir Selflow sur ce qu'il a émis,
devant un contrôle.

**Aucun achat n'est créé, aucune écriture produite, aucun fournisseur inventé.**
Le rapprochement se propose (`rapprochementPropose()`), il ne s'applique pas :
il rend le fournisseur probable — retrouvé par **NCC**, jamais par le nom —,
l'achat candidat, et l'**écart TTC** s'il y en a un. Cet écart est ce qui vaut
de l'argent. Un test le verrouille : `test_le_releve_ne_cree_aucun_achat`.

Trois précautions qui viennent de fautes déjà commises ailleurs dans le projet :

- `tvaDeductible()` rend **faux** pour un `purchase_slip` et pour un RNE. Un
  bordereau constate un achat auprès d'un non-assujetti : il ne facture aucune
  TVA, et la déduire serait l'erreur que `ventilationAchat` a déjà corrigée.
- Les taxes d'une ligne sont conservées **brutes**. Rien ne garantit que le
  portail parle le langage de `Produit::CODES_TVA` ; deviner un code à partir
  d'un montant reviendrait à inventer une information fiscale.
- Une pièce **sans numéro FNE** est écartée et journalisée : sans identité, elle
  ne pourrait ni être reconnue au relevé suivant, ni détecter un doublon.

#### Ce qu'il manque encore

- **Des données.** `DC-KNOWING CGA` n'a aucune facture reçue. La chaîne tourne
  à vide et le dit : `0 facture(s)`, aucune erreur.
- **Le scraper n'est pas planifié.** `achats.js` se lance à la main. L'accrocher
  au planificateur avant d'avoir vu une seule facture réelle ferait tourner un
  navigateur chaque heure pour rien.
#### L'écran

`admin/fne/factures-recues`, sixième onglet de la barre FNE et entrée du menu
latéral. Quatre filtres — **à rapprocher**, **rapprochées**, **fournisseur
inconnu**, **écartées** — et par pièce : l'émetteur avec son NCC, les montants,
la mention **déductible / non déductible**, et le détail des lignes.

Le rapprochement est **calculé à l'affichage**, jamais stocké : un fournisseur
créé ce matin doit être vu ce matin, sans attendre le relevé de la nuit.

Trois gestes, et aucun ne crée quoi que ce soit :

| Geste | Ce qu'il fait |
|---|---|
| **Rattacher** | pose `portail_fne_factures_recues.achat_id` vers un achat **déjà saisi**. Sans achat en face, il refuse et le dit — il n'en fabrique pas |
| **Détacher** | défait le lien, la pièce retourne à rapprocher |
| **Écarter** | range la pièce sans la supprimer : le portail la redéposera au prochain relevé, et une pièce écartée qui revient chaque jour finirait par masquer celles qui comptent |

**L'écart de montant est ce qui vaut de l'argent.** L'achat saisi dit 11 000, la
DGI détient 11 800 : l'écran le montre et le conserve dans
`note_rapprochement`. Personne ne le voyait avant.

Rien n'est écrit dans les colonnes gelées d'`achats` — un test le vérifie
explicitement (`assertNull($achat->numero_fne)`).

`EcranFacturesRecuesTest` — 7 cas, dont l'isolation entre entreprises : une
pièce fiscale lue par le mauvais client ne se répare pas.

#### Ce qu'il manque encore

- **Des données.** `DC-KNOWING CGA` n'a aucune facture reçue. La chaîne tourne
  à vide et le dit : `0 facture(s)`, aucune erreur.
- **Le scraper n'est pas planifié.** `achats.js` se lance à la main. L'accrocher
  au planificateur avant d'avoir vu une seule facture réelle ferait tourner un
  navigateur chaque heure pour rien.

#### Une trouvaille, laissée telle quelle

`FneControleur` existe depuis le lot I et porte exactement cette intention —
*« Recherche de documents fiscaux ENTRANTS »*. **Aucun écran ne l'appelle** :
`fne.rechercher` et `fne.attacher` sont des routes orphelines. Et son code porte
trois défauts, non corrigés parce qu'ils traversent le périmètre gelé :

1. l'URL `GET /api/v1/documents/{ref}` est **inventée** — le docblock l'avoue
   (« une supposition raisonnable »). La vraie est `/ws/invoices` ;
2. le repli d'URL en dur porte encore `https://fne-sandbox.dgi.gouv.ci`, l'hôte
   inexistant que le lot 9 avait corrigé dans `config/selflow.php` ;
3. `attacherFneAchat` écrit dans `achats.numero_fne` — colonne gelée signifiant
   « Selflow a émis et la DGI a certifié ». Y mettre la référence d'un
   fournisseur ferait mentir Selflow devant un contrôle.

Le nouvel écran ne passe par aucun de ces chemins.

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

### Le planificateur n'était déclenché par rien — **corrigé en développement**

`routes/console.php` planifie deux tâches — `selflow:sync-ecritures` toutes les
cinq minutes depuis le lot 5, `portail-fne:importer` toutes les heures depuis
le lot 9. **Aucune des deux ne tournait.** Un planificateur Laravel ne
s'auto-déclenche pas : il lui faut `php artisan schedule:run` appelé chaque
minute, par le cron du serveur ou par le planificateur de tâches de Windows.
Vérifié le 21/08/2026 : aucune tâche de ce nom n'existait sur le poste.

La conséquence dépassait l'import du portail : **la reprise des écritures
Comptaflow en échec n'avait jamais eu lieu**, et rien ne le signalait, puisqu'une
tâche jamais lancée ne produit ni journal ni erreur.

Posé sur le poste de développement le 21/08/2026 :

- tâche Windows « Selflow - planificateur », toutes les minutes, sans fin ;
- elle appelle `storage/app/planificateur-selflow.vbs`, qui lance
  `php artisan schedule:run` **fenêtre masquée**. Sans ce lanceur, une console
  noire s'ouvrirait soixante fois par heure sur l'écran de qui travaille. Le
  script porte les chemins de ce poste et vit dans `storage/app`, que git
  ignore : il n'est ni versionné ni déployé.

**Reste à poser en production** (Linux) :
`* * * * * cd /chemin/selflow && php artisan schedule:run >> /dev/null 2>&1`.
À ajouter à `deploy-production.sh`, qui n'en dit rien aujourd'hui.

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

### Passerelle Comptaflow — **LE CORRECTIF A ÉTÉ PERDU**

Le correctif du lot 5 avait bien été appliqué côté Comptaflow, commit
`b3bd59b` du 9 août 2026 (« Déversement Selflow : idempotence, tiers, exercice
comparé, et un secret qui n'est plus public »).

**Un `git push --force` sur `main` le 12 août l'a effacé de l'historique.**
Le commit existe encore dans le dépôt — `git cat-file -t b3bd59b` répond —
mais il n'est plus atteignable depuis `main` :
`git merge-base --is-ancestor b3bd59b HEAD` échoue. `ExternalSyncController`
est revenu à sa version d'avant, vérifié ligne à ligne :

| Vérification | État au 14/08/2026 |
|---|---|
| `cle_selflow` (idempotence) | absent de tout `app/` |
| `compte_tiers` | absent du contrôleur de synchronisation |
| `exercice_debut` / `exercice_fin` | absents |
| `n_saisie` | de nouveau `$refPiece ?: 'SELF_' . time() . '_' . $count` (ligne 486) |
| Exercice | de nouveau celui **actif** chez Comptaflow, sans comparaison (ligne 411) |

Or **Selflow envoie désormais ces cinq champs** : le `DeverserEcritureComptaflow`
les met dans chaque payload. Comptaflow les reçoit et les ignore. Conséquences
en l'état :

- rejouer une synchronisation **duplique tout**, et la balance double ;
- le relevé d'un client particulier reste impossible : tout retombe sur le
  compte collectif `411000` ;
- une pièce d'un exercice clos se range dans l'exercice courant.

**Le correctif a été refait — 15/08/2026.** `git cherry-pick b3bd59b` échoue
sur un poste de travail ordinaire (`fatal: bad revision`) : l'objet n'a jamais
été récupéré localement. Il a donc été reporté sur `a5da35d`, conflit résolu,
et livré sous deux formes dans `PASSERELLE-COMPTAFLOW/` :

| Fichier | Rôle |
|---|---|
| `0002-deversement-selflow-sur-a5da35d.patch` | le correctif, vérifié : `git apply --check` passe sur `a5da35d` |
| `CONSIGNES-POUR-COMPTAFLOW.md` | le dossier complet, autoportant : contexte, raison de chaque changement, application, vérification au `curl`, plan comptable |

Le conflit portait sur la résolution du compte : `main` avait retouché le bloc
en ligne là où le correctif le déplace dans `compteGeneral()` / `tiers()`.
**La version du correctif l'emporte** — sa logique remplace l'ancienne, elle
ne s'y ajoute pas.

Ces deux fichiers se donnent tels quels à une session Comptaflow, qui n'a
besoin de rien d'autre. Cette session-ci lit le dépôt Comptaflow mais ne peut
pas y pousser : `add_repo` refuse les ajouts entre propriétaires différents.

**Le correctif est poussé — vérifié le 15/08/2026.** Il vit sur la branche
`claude/passerelle-selflow-deversement` du dépôt Comptaflow, commit `0407335`.
**`main` est resté sur `a5da35d` : la fusion n'est pas faite.**

Contrôlé sur la branche, pas supposé : `cle_selflow`, `compte_tiers`,
`exercice_debut`, `hash_equals`, `desaccordDExercice()` et `compteGeneral()`
sont là ; `n_saisie` prend la clé ; la migration d'idempotence est présente.
Il ne reste aucune valeur de secret en dur — les douze appelants lisent la
même clé de configuration, sans repli, et les seules occurrences des anciennes
chaînes sont les commentaires qui expliquent leur retrait.

Un second commit y ajoute deux choses que je n'avais pas vues :

- **un septième point d'entrée restait ouvert.** `syncStatus` lisait
  `config('app.external_sync_secret', 'selflow-local-secret')` — une **autre**
  clé, absente de `config/app.php`, et une **autre** valeur de repli en clair.
  C'était donc toujours la chaîne publique qui faisait foi, quoi qu'on mette
  au `.env`, pendant que les six autres étaient refermés ;
- la route morte `POST /load-syscohada` vers une méthode `private` est
  supprimée, et aucune vue ne la référençait.

**À faire en même temps que la fusion :** poser `EXTERNAL_SYNC_SECRET`
dans les deux `.env`, même valeur. Sans elle la synchronisation refuse tout —
c'est le comportement voulu, mais il faut le savoir avant de conclure à une
régression. Et considérer `selflow-comptaflow-secret-2026` comme compromis :
il est dans l'historique public.

### Plan comptable de Comptaflow — le référentiel existe bien

Correction du constat précédent, qui portait sur le mauvais chemin.

Comptaflow **possède** son référentiel SYSCOHADA : `config/syscohada_complet.php`,
**1 259 comptes**, chargeable par entreprise depuis l'écran de configuration
(`AdminConfigController::loadSyscohadaPlan`), en trois formats — SYSCOHADA
(2-4), **COMPTES SAGE (6)**, DC-KNOWING (8). La table `plan_comptables` est
`company_id`, donc chaque entreprise en a sa copie et la modifie sans gêner
les autres.

Le chargement est **à la demande, jamais automatique** : une entreprise neuve
part d'un plan vide tant que personne n'a cliqué.

**Selflow émet des numéros à 6 chiffres** (`411000`, `701000`, `443100`,
`521000`…). Le format à charger est donc **« COMPTES SAGE (6) »** : c'est le
seul des trois où les comptes de Selflow tombent sur des comptes existants du
référentiel. En 2-4, `701000` ne correspond à rien — le déversement le crée à
la volée avec le libellé de l'écriture pour intitulé, et le plan se remplit de
comptes bâtards à côté des vrais.

Le classeur `modele_plan_comptable.xlsx` est un **modèle d'import** pour un
plan déjà existant venu d'ailleurs, pas la source du référentiel. Son contenu
d'exemple (« ELIKET MARKET ») ne dit rien de l'état réel des plans en base.

**Bug repéré au passage, hors passerelle :** `routes/web.php:510` route
`POST /load-syscohada` vers `loadSyscohadaPlan`, **déclarée `private`** →
500 (Internal Server Error — erreur interne du serveur). Les trois routes
`-4`, `-6`, `-8` passent par des méthodes publiques et fonctionnent.

### Passerelle Comptaflow — anomalies d'origine

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

### Importations — **CORRIGÉ**

Le constat d'origine — « cinq types seulement, colonnes incomplètes » — est
levé. Le lot 6.6 a porté l'import à **sept modules** : points de vente,
clients, fournisseurs, utilisateurs, produits (24 colonnes), stock
d'ouverture, immobilisations. Les sept sont branchés à un écran.

**Dernier manque, corrigé le 15/08/2026 :** la colonne `sous_categorie`
figurait au modèle des articles et **n'était jamais lue**. Un catalogue
arrivait rangé par famille seulement, et la sous-famille se ressaisissait
fiche par fiche. Elle est désormais résolue par son nom.

Un nom qui ne désigne rien — famille comme sous-famille — **refuse la ligne
avec son motif** au lieu de la ranger « sans famille » en la comptant pour un
succès. Ce silence-là faisait croire l'import complet, et l'administrateur ne
s'en apercevait qu'à la première recherche au catalogue.

La borne d'entreprise passe par la famille : `sous_categories` ne porte pas
d'`entreprise_id`, elle ne tient à son entreprise que par sa `categorie_id`.
Une recherche sur le seul nom aurait rattaché un article à la sous-famille
d'un concurrent — cinq épreuves couvrent le point, dont la simulation
d'attaque.

Restent hors import, par choix : le plan comptable et les codes journaux, que
Selflow livre par son propre référentiel, et les soldes d'ouverture
comptables, qui appartiennent à Comptaflow.

### Numéro de tiers et compte général — **CORRIGÉ**

Relevé par le propriétaire du projet le 15/08/2026. **Ce sont deux choses
différentes**, et il ne faut pas les confondre :

| Notion | Colonne | Exemple |
|---|---|---|
| Compte général de rattachement | `compte_comptable` | `411000`, `401000` |
| Numéro de tiers | `numero_tiers` | `411001`, `411KONE` |

La distinction existait déjà en base, et l'écriture les range dans deux
colonnes séparées — `compte_debit` / `compte_credit` pour le général,
`compte_tiers` pour le tiers, jamais l'un à la place de l'autre. Un seul
endroit du code écrit dans `ecritures_comptables`, précisément pour ça.

**Cinq défauts autour, tous corrigés :**

1. **La numérotation automatique démarrait à `411000`** — le compte collectif
   lui-même. Le premier client de chaque entreprise portait donc, comme numéro
   de tiers, le numéro du collectif : son relevé remontait le solde de tous les
   autres. La séquence part de `001`, et la migration renumérote les fiches
   déjà fautives sur la première place libre ;
2. **le numéro n'acceptait que des chiffres** (`^411[0-9]*$`). `411KONE` est
   une convention répandue, elle était refusée ;
3. **aucune cohérence n'était vérifiée** : rien n'empêchait de donner `411002`
   à un client et de le rattacher à `401000`. L'écriture partait alors sur le
   collectif fournisseurs avec un tiers client ;
4. **rien n'interdisait de saisir le collectif lui-même** comme numéro de
   tiers ;
5. **la vente de passage ne portait aucun tiers.** Sans client nommé,
   `compte_tiers` restait vide et tout retombait sur `411000` : le grand livre
   ne distinguait plus les ventes de comptoir des créances d'un client
   identifié.

**La règle vient de Comptaflow, et ce n'est pas un détail de forme.**
`ExternalSyncController::tiers()` retrouve un tiers **par égalité de chaîne**
sur `numero_de_tiers`. Une convention différente d'un côté et de l'autre, et
plus aucun tiers n'est reconnu : chaque écriture déversée retombe sur son
compte collectif, sans que rien ne le signale.
`NumerotationTiersService` reproduit donc
`AdminConfigController::getNextTierNumber()` à l'identique :

| Élément | Règle |
|---|---|
| Préfixe | **Deux** caractères — les deux premiers chiffres du collectif : `41`, `40` |
| Longueur totale | **Six**, séquence comprise. Doit valoir `companies.tier_digits` chez Comptaflow |
| `numeric` *(défaut)* | préfixe + séquence → `410001`, `400001` |
| `alphanumeric` | préfixe + **trois lettres** du nom + séquence → `41KON1`, `40KOF1` |

Le réglage vit sur l'entreprise, comme les comptes par défaut, et se change
depuis les paramètres — où l'écran rappelle qu'il doit être le même que dans
Comptaflow. Les deux conventions **cohabitent** : une entreprise qui change
d'avis ne voit pas sa séquence repartir de `0001` sur un numéro déjà pris. Un
nom sans lettre — « 123 » — retombe sur la base numérique ; Comptaflow y met
« XXX », ce qui créerait une famille de tiers tous nommés pareil.

**Le numéro ne se saisit plus, ni à la création ni à la modification.** Le
système le fabrique. Changer le numéro d'un tiers déjà déversé le rendrait
introuvable chez Comptaflow : ses écritures futures retomberaient sur le
collectif pendant que les anciennes resteraient sur l'ancien numéro.

**L'import filtre, comme Comptaflow le fait au déversement.** Un fichier vient
de partout — d'un autre logiciel, d'un tableur retouché à la main — et rien n'y
garantit la convention. Trois motifs de rejet, et dans les trois cas le système
renumérote : le numéro vaut le compte collectif ; son préfixe ne correspond pas
au rattachement ; sa longueur n'est pas la bonne. Un numéro déjà pris est
également écarté.

**Le client de passage** est une fiche unique par entreprise — `410000`
rattaché à `411000` — posée à la souscription, et créée à la demande pour les
entreprises déjà en service. `400000` en pendant pour les fournisseurs. C'est
la place zéro : elle précède la séquence, qui démarre à 1, et ne la fera donc
jamais avancer — au contraire d'un numéro haut comme `419999`, qui pousserait
le suivant hors de la longueur permise.

Vingt-deux épreuves dans `NumerotationTiersTest`.

### Libellés d'écriture — l'intitulé du compte tient lieu de libellé

Relevé par le propriétaire du projet le 15/08/2026, et vérifié dans le code.

**Ce sont deux choses différentes.** L'intitulé du compte appartient au plan
comptable : 701000 s'appelle « Ventes de marchandises », et cela ne change
jamais. Le libellé de l'écriture dit ce qui s'est passé — quelle pièce, avec
qui. Selflow met aujourd'hui le premier à la place du second.

Pour une facture FA-0042 de ciment et fer à béton, vendue à ABC SARL :

| Ligne | Ce qui s'écrit | Verdict |
|---|---|---|
| En-tête de l'opération | `Vente de marchandises` | **intitulé du compte**, déduit de la racine 701 |
| Client 411000 | `FA-0042 / Facturation Vente` | le tiers manque |
| Produit 701000, ≤ 3 articles | `FA-0042 / Ciment, Fer à béton` | correct |
| Produit 701000, > 3 articles | `FA-0042 / Vente de marchandises…` | **intitulé du compte** |
| TVA 443100 | `FA-0042 / TVA Collectée Vente` | correct |
| Règlement | `Rglt/FA-0042/Vente Ciment, Fer à béton` | le tiers manque |

Deux fois sur six, c'est l'intitulé du compte qui sert de libellé, et **le nom
du tiers n'apparaît jamais**. Or `grand_livre.blade.php:21` affiche déjà
« 701000 — Ventes de marchandises » en tête de colonne : le répéter dans
chaque ligne n'apprend rien et occupe la place de ce qui manque.

Le mécanisme n'est pas mauvais partout : la référence de pièce est là, les
articles remontent quand ils tiennent, et au-delà de trois la liste complète
part dans la colonne `description` que la vue déplie au clic — un libellé de
cinq cents caractères serait illisible. C'est déjà mieux que le texte fixe
d'origine, « Vente — Facture X » répété à l'identique partout.

**Deux manques, donc, et non un seul :**

1. l'intitulé du compte sert de libellé d'opération, et le tiers est absent ;
2. rien n'est paramétrable — l'assemblage est écrit dans
   `ComptabiliteService`.

Le correctif proposé traite les deux : une table `modeles_libelles` portée par
`entreprise_id`, six gabarits, des jetons `{piece}` `{tiers}` `{produits}`
`{point_de_vente}` `{date}`, et **`{piece} — {tiers}` par défaut**. Les
écritures déjà passées ne sont jamais réécrites : un libellé est ce qu'il
était au jour de la pièce.

### Souscription — les points de vente disparaissaient — **CORRIGÉ**

Signalé le 15/08/2026 : la section « Points de vente » de la barre latérale
s'évanouissait au cours du paramétrage, sans message.

La liste des modules livrés à tout le monde vivait **en double** —
`SouscriptionControleur::modulesProposes()` pour afficher les cases à cocher,
`SouscriptionProfilService::ouvrirLesModules()` pour écrire `modules_actifs`.
Les deux copies avaient dérivé de la même façon : `points_de_vente` manquait
aux deux. Tant que `modules_actifs` restait vide, la barre latérale affichait
tout par défaut ; l'étape 4 l'écrivait enfin, et la section disparaissait.

Aucune route ne porte `modules:points_de_vente` — les écrans restaient
joignables, seul le menu s'était fermé. C'est ce qui a rendu la panne muette.

Trois corrections :

- **une seule liste**, `Entreprise::MODULES_SOCLE`, lue aux deux endroits ;
  une épreuve refuse qu'on en recrée une copie ;
- **`Entreprise::MODULES_STRUCTURELS`** — `principal` et `points_de_vente` ne
  se décochent pas. Ce dernier ne porte pas que les sites : **le personnel et
  les habilitations vivent derrière lui**, et le retirer priverait
  l'administrateur de l'écran où il gère ses propres utilisateurs et leurs
  droits. Personne ne fait ce choix en connaissance de cause ;
- une case désactivée **n'est pas transmise** par le navigateur : le rattrapage
  se fait côté serveur, où un formulaire forgé le rencontre aussi.

Structurel ne veut pas dire hors de contrôle : **un superadmin qui ferme le
module le garde fermé.** Le parcours ne peut pas ouvrir ce que les droits
refusent — une épreuve le vérifie.

Six épreuves dans `ParcoursSouscriptionTest`. Trois d'entre elles échouent si
l'on retire la correction ; c'est vérifié, pas supposé.

### Modèles d'import de Comptaflow — les quatre fichiers ne s'importent pas

Vérifié fichier par fichier le 15/08/2026, en rejouant la logique des trois
importeurs (`MasterPlanImport`, `MasterTiersImport`, `MasterJournalImport`,
qui lisent par indice : `row[0]` numéro, `row[1]` intitulé, `row[2]` type).

Les quatre classeurs de `public/templates/import/` **ne sont pas des
modèles** : ce sont des exports Sage bruts d'ELIKET MARKET, tirage du
22/01/2026, bandeau de titre et cellules fusionnées compris.

| Fichier | Contient | Ce que l'import en tire |
|---|---|---|
| `modele_plan_comptable.xlsx` | 26 comptes | **0** — `row[0]` vaut « Détail », aucun chiffre |
| `modele_plan_tiers.xlsx` | 2 tiers | **2 lignes inversées** — un tiers numéroté « FOURNISSEUR », intitulé « 401000 » |
| `modele_codes_journaux.xlsx` | 1 journal | 1 ligne sans son type |
| `modele_ecritures.xlsx` | 6 feuilles d'impression | rien — **aucune route ne l'importe** |

S'y ajoutent deux défauts de code : `plan_tiers.compte_general` est `NOT NULL`
et l'importeur ne le renseigne pas (violation d'intégrité même avec un bon
fichier) ; et `AdminConfigController:520` construit `MasterPlanImport` sans le
chemin du fichier, donc `detectDelimiter()` retombe toujours sur `;`.

Et le dépôt **publie la comptabilité réelle d'un client**.

**Les quatre classeurs refaits sont dans
`PASSERELLE-COMPTAFLOW/modeles-import/`** — une feuille, une en-tête,
l'ordre que les importeurs lisent, exemples SYSCOHADA neutres. Vérifiés :
7 comptes, 4 tiers, 5 journaux retenus, seule l'en-tête écartée. La marche à
suivre est en section 7 de `CONSIGNES-POUR-COMPTAFLOW.md`.

### B2B

Le flux va de la demande de prix à la proposition, puis crée directement une
vente et un achat. **L'étape du devis n'existe pas** : ni pièce opposable, ni
validité, ni acceptation, ni renégociation.

- **Le B2B est une fonctionnalité incluse, non un module — tranché par le
  propriétaire du projet.** Le groupe de routes `admin.b2b.*` ne porte donc
  aucun `modules:b2b`, contrairement à `ventes`, `achats`, `stock`,
  `production` et `comptabilite` : une entreprise qui a `ventes` ou `achats`
  a le B2B avec. Ce n'est pas un oubli — le menu le disait déjà, en rangeant
  les liens B2B sous ces deux sections plutôt que sous une section propre.
  `b2b` figure encore dans `Entreprise::TOUS_LES_MODULES` : la valeur reste
  acceptée pour ne pas casser les entreprises qui la portent déjà, mais elle
  ne commande rien. Un test verrouille le comportement.

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

### Le plan de travail en PDF

`Plan-de-travail-Selflow.pdf`, à la racine, est la version lisible hors dépôt
de ce journal : l'état des lots, ce qui reste chez le propriétaire, ce qui
reste chez le mainteneur de Comptaflow, la règle d'or FNE, le choix
d'imprimante, la comptabilité. **Le mettre à jour fait partie du lot**, comme
ce journal — le propriétaire le demande à chaque fois.

Il est fabriqué par un script reportlab, `plan.py`, tenu dans le répertoire de
travail de la session et non versionné : c'est le PDF qui fait foi, le script
n'est qu'un outil.

| Édition | Date | Pages | Ce qu'elle ajoute |
|---|---|---|---|
| 1<sup>re</sup> | 15/08/2026 | 8 | l'état initial, TERNE, l'imprimante, la comptabilité |
| 2<sup>e</sup> | 15/08/2026 | 10 | la passerelle fusionnée, les modèles d'import, les libellés (section 7) |
| 3<sup>e</sup> | 16/08/2026 | 14 | la numérotation des tiers (section 8), la page d'accueil (section 9), le filtrage à l'import, `tier_digits` à vérifier chez Comptaflow |
