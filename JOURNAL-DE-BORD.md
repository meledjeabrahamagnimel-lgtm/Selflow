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

**Ce qui reste : la saisie.** Textes, tarifs, images, mentions légales,
présentation de DC-Knowing. Rien de tout cela ne peut être inventé ici.

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

**Deux conventions, au choix de l'entreprise** — décision du propriétaire :

| Réglage | Ce que ça donne |
|---|---|
| `sequence` *(défaut)* | `411001`, `411002`, `401001` — jamais d'homonyme |
| `nom` | `411KONE`, `401KOFFI` — lisible en grand livre ; les homonymes reçoivent `411KONE2` |

Le réglage vit sur l'entreprise, comme les comptes par défaut, et se change
depuis les paramètres. Les deux conventions **cohabitent** : une entreprise qui
change d'avis ne voit pas sa séquence repartir de `001` et heurter un numéro
déjà pris. Un nom qui ne laisse aucune lettre — « 123 » — retombe sur la
séquence, faute de quoi le radical numérique se confondrait avec un rang.

**Le client de passage** est une fiche unique par entreprise —
`411DIVERS` rattaché à `411000` — posée à la souscription, et créée à la
demande pour les entreprises déjà en service. `401DIVERS` en pendant pour les
fournisseurs. Une fiche par ticket de caisse aurait fait gonfler le plan de
tiers d'une ligne par vente de comptoir.

Dix-huit épreuves dans `NumerotationTiersTest`.

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
