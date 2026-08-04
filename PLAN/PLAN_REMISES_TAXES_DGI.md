# PLAN — Remises multi-niveaux, taxes personnalisées & champs DGI manquants

> Audit réalisé sur la branche `claude/selflow-remises-taxes-dgi-a5je2b`
> Sources de vérité : `CONFIGURATION FNE/Procedure_technique_integration_API.txt`,
> `TABLEAU_CHAMPS_FACTURE_VENTE.md`, `TABLEAU_CHAMPS_FACTURE_AVOIR.md`,
> `exemple de json vente.txt`, `BAPA( déclaration des bordereau d'achat).txt`,
> `json avoir.txt`, `INFO EN PLUS-URL.txt`.

---

## 0. Ce qui fonctionne aujourd'hui — À NE PAS CASSER

| Élément | Fichier | État |
|---|---|---|
| Appel de signature vente | `FneService::normaliserFacture()` | ✅ opérationnel |
| Appel de remboursement (avoir) | `FneService::normaliserFacture()` branche `refund` | ✅ opérationnel |
| Appel BAPA | `FneService::normaliserAchatBapa()` | ✅ opérationnel |
| Récupération `invoice.id` + `items[].id` FNE | `mapperFneItemIdsToDetails()` | ✅ (indispensable pour l'avoir) |
| Persistance `fne_invoice_id` / `fne_invoice_item_id` | migrations `2026_07_30_165321`, `2026_07_31_000001` | ✅ |
| Solde stickers, token/QR, PDF | Jobs `NormaliserFactureFne`, `NormaliserAchatBapaJob` | ✅ |

**Règle du chantier : toutes les évolutions ci-dessous sont additives.**
Aucune signature de méthode publique de `FneService` n'est modifiée, aucun champ
déjà envoyé n'est supprimé du payload — sauf le champ `items[].id` (voir §2.7,
non documenté par la DGI sur `/sign`).

---

## 1. AUDIT DE MAPPING — FACTURE DE VENTE (`POST /external/invoices/sign`)

Payload construit dans `app/Modules/Admin/Services/FneService.php:122-142`.

| Champ FNE | Oblig. | Mappé ? | Source actuelle | Verdict |
|---|---|---|---|---|
| `invoiceType` | O | ✅ | `'sale'` en dur | OK |
| `paymentMethod` | O | ✅ | `mapperModePaiement($vente->mode_paiement)` | OK |
| `template` | O | ⚠️ | `client->type_facturation`, défaut `B2B` | **Risque** : client de passage (`client_id` vide) ⇒ `B2B` alors que `clientNcc` est vide. `B2B` exige un NCC ⇒ HTTP 400. Doit basculer sur `B2C`. |
| `isRne` | O | ❌ | `empty($request->client_id)` (`VenteControleur:285`) | **Faux sens.** `isRne` DGI = « cette facture est rattachée à un reçu déjà émis », **pas** « client de passage » ni « régime RNE ». → case à cocher UI. |
| `rne` | O si isRne | ❌ | `$vente->parent?->numero_fne` | Doit être le **n° de reçu saisi par l'utilisateur** quand la case est cochée. |
| `clientNcc` | O si B2B | ✅ | `client->ncc` nettoyé | OK |
| `clientCompanyName` | O | ✅ | `client->nom` / « Client de passage » | OK |
| `clientPhone` | O | ✅ | `client->telephone` | OK |
| `clientEmail` | O | ✅ | `client->email` | OK |
| `clientSellerName` | N | ✅ | `pointDeVente->responsable` | OK |
| `pointOfSale` | O | ✅ | `pointDeVente->code_fne` | OK |
| `establishment` | O | ✅ | `entreprise->nom` | OK |
| `commercialMessage` | N | ⚠️ | `entreprise->facture_autres_mentions` | Mappé, **mais** : pas de saisie par facture, pas de limite de longueur alignée, **jamais imprimé sur nos PDF**. |
| `footer` | N | ⚠️ | `entreprise->pied_de_page_facture` | Idem : mappé mais **jamais imprimé sur nos PDF**. |
| `foreignCurrency` | N | ✅ | `vente->devise` | OK |
| `foreignCurrencyRate` | O si devise | ✅ | `vente->taux_change` | OK |
| `items[].reference` | N | ✅ | `produit->reference` | OK (vide sur ligne libre) |
| `items[].description` | O | ✅ | `produit->nom` / `libelle_virtuel` | OK |
| `items[].quantity` | O | ✅ | `quantite` | OK |
| `items[].amount` | O | ✅ | `prix_unitaire` | OK |
| `items[].measurementUnit` | N | ✅ | `unite` | OK |
| `items[].taxes` | O | ⚠️ | `devinerCodeTaxe($produit->taux_tva)` | **Table de correspondance fausse** — voir §2.3 |
| **`items[].discount`** | N | ❌ | `floatval($d->discount ?? 0)` | **La colonne `discount` n'existe pas sur `vente_details`.** Envoie toujours `0`. |
| **`items[].customTaxes`** | N | ❌ | `[]` en dur | **Jamais alimenté.** |
| **`customTaxes` (facture)** | N | ❌ | `[]` en dur | **Jamais alimenté** (= « Taxes sur total TTC »). |
| **`discount` (total HT)** | N | ⚠️ | `floatval($vente->remise)` | **Unité fausse** — voir §2.1 |

---

## 2. BUGS BLOQUANTS IDENTIFIÉS

### 2.1 — La remise globale est envoyée en FCFA alors que la DGI attend un POURCENTAGE

`FneService.php:140` → `'discount' => floatval($vente->remise ?? 0)`

Or `ventes.remise` est un **montant en francs** (migration `2026_06_08_000003`,
champ UI `Remise (F)` dans `nouvelle.blade.php:187` et `modifier.blade.php:197`,
calcul `montantHtNet = montantHt - remise` dans `VenteControleur:152`).

La FNE attend `discount: 10` = **10 %** (`Procedure_technique…:276`, et l'interface
FNE affiche bien « Remise (%) »).

**Conséquence :** une remise de 5 000 F part comme `discount: 5000` → 5 000 % de
remise, ou rejet HTTP 400. Le problème est invisible aujourd'hui uniquement parce
que les factures de test sont passées **sans remise**.

**Correction :** stocker le taux (`remise_taux`, %) **en plus** du montant existant
(`remise`, F) et envoyer le taux. Le champ montant reste pour la compta/PDF.

### 2.2 — Aucune remise par article

`vente_details` n'a **ni** colonne `discount` **ni** `remise`.
Idem `achat_details`. Le formulaire de vente n'a qu'une remise globale.
L'interface FNE, elle, a une `Remise (%)` **sur chaque ligne d'article**.

### 2.3 — Table de correspondance TVA → code DGI incorrecte

`FneService::devinerCodeTaxe()` (ligne 368) :

| Code | Valeur codée | Valeur DGI (`Procedure_technique…:808`) |
|---|---|---|
| `TVA` | 18 % | 18 % ✅ |
| `TVAB` | **10 %** | **9 %** ❌ |
| `TVAC` | 0 % | 0 % (exo. conventionnelle) ✅ |
| `TVAD` | **20 %** | **0 %** (exo. légale, TEE / RNE) ❌ |

Un produit à 9 % tombe aujourd'hui dans le `default => 'TVA'` (18 %).
`TVAD` est inatteignable. **La distinction TVAC / TVAD ne peut pas se déduire
d'un taux** (les deux valent 0 %) : elle doit venir du produit (choix explicite)
ou du régime de l'entreprise.

### 2.4 — BAPA : le tiers est inversé

Doc BAPA (`Procedure_technique…:497-500`) :
`clientCompanyName` = **nom du fournisseur**, `clientPhone`/`clientEmail` = ceux du
**fournisseur**. Exemple DGI : `"COOPERATION DU GRAND OUEST"` = le producteur.

`FneService::normaliserAchatBapa()` (lignes 271-275) envoie :
- `clientCompanyName` = `$entreprise->nom` ← **nous**, au lieu du fournisseur
- `clientPhone` / `clientEmail` = ceux de **notre** entreprise
- `clientSellerName` = `$achat->fournisseur?->nom` ← inversé

### 2.5 — BAPA : `isRne` / `rne` absents du payload

Champs déclarés **obligatoires** pour l'API #3 (`Procedure_technique…:495-496`).
Le payload BAPA ne les envoie pas du tout.

### 2.6 — BAPA : remises câblées à zéro

`'discount' => 0` en dur (article ligne 262, total ligne 283).
Aucun champ remise n'existe dans `achats/nouveau.blade.php`.

### 2.7 — Champ `items[].id` envoyé à la certification

`FneService.php:89` ajoute `'id' => $d->produit?->reference ?? 'item-'.$d->id`.
Ce champ n'existe **dans aucune** spec de `/sign` (ni vente, ni BAPA) : `id` n'est
attendu que dans le payload **refund**. À retirer du payload de signature.

### 2.8 — Pied de page et Autres mentions jamais imprimés

Les deux champs existent en base et **sont** envoyés à la FNE, mais aucun des
4 modèles de `factures/vente.blade.php`, ni `factures/bapa.blade.php`, ni
`factures/achat.blade.php` ne les affiche. Sur les spécimens envoyés à la DGI pour
validation, ces mentions manqueront.

---

## 3. AUDIT — BAPA (`POST /external/invoices/sign`, `invoiceType: purchase`)

| Champ FNE | Oblig. | Mappé ? | Verdict |
|---|---|---|---|
| `invoiceType` | O | ✅ | `'purchase'` |
| `paymentMethod` | O | ✅ | OK |
| `template` | O | ✅ | `'B2B'` en dur — acceptable pour un BAPA |
| `isRne` | O | ❌ | **absent du payload** (§2.5) |
| `rne` | O si isRne | ❌ | **absent du payload** |
| `clientCompanyName` | O | ❌ | **inversé** (§2.4) |
| `clientPhone` | O | ❌ | **inversé** |
| `clientEmail` | O | ❌ | **inversé** |
| `clientSellerName` | N | ❌ | **inversé** |
| `clientNcc` | — | ⚠️ | envoyé alors que non listé par la spec BAPA ; contient notre NCC |
| `pointOfSale` | O | ✅ | OK |
| `establishment` | O | ✅ | OK |
| `commercialMessage` / `footer` | N | ⚠️ | mappés, non imprimés |
| `items[].reference` / `description` / `quantity` / `amount` / `measurementUnit` | | ✅ | OK |
| `items[].discount` | N | ❌ | `0` en dur (§2.6) |
| `discount` (total) | N | ❌ | `0` en dur (§2.6) |

---

## 4. AUDIT — AVOIR (`POST /external/invoices/{id}/refund`)

| Champ FNE | Oblig. | Mappé ? | Verdict |
|---|---|---|---|
| `{id}` (facture d'origine) | O | ✅ | `vente->parent->fne_invoice_id` |
| `items[].id` | O | ✅ | `venteDetail->fne_invoice_item_id` |
| `items[].quantity` | O | ✅ | OK |

**Le payload d'avoir est conforme et complet.**

> ⚠️ **Point important sur la demande « ajouter RNE + pied de page sur la page AVOIR » :**
> l'API `refund` **n'accepte que `items[]`**. Un `isRne`, un `rne`, un `footer` ou une
> remise envoyés sur cet endpoint seront ignorés (au mieux) ou provoqueront un 400.
> Sur la page Avoir, ces champs ne peuvent donc servir **qu'à l'affichage / au PDF
> local**. C'est ainsi qu'ils seront implémentés (§6, Lot 5).

---

## 5. TABLEAU DE SYNTHÈSE — « quoi mapper avec quoi »

| Champ FNE manquant | À mapper avec (à créer) | Où le saisir |
|---|---|---|
| `items[].discount` | `vente_details.remise_taux` / `achat_details.remise_taux` (décimal 5,2 %) | Ligne d'article + valeur par défaut héritée de `produits.remise_taux` |
| `discount` (total, vente) | `ventes.remise_taux` (%) | Bloc « Remise » de la vente |
| `discount` (total, BAPA) | `achats.remise_taux` (%) | Bloc « Remise » du BAPA |
| `items[].customTaxes[]` | table `produit_taxes` (produit_id, nom, taux) recopiée dans `vente_detail_taxes` | **Fiche produit** (« Ajouter d'autres taxes ») |
| `customTaxes[]` (facture) | table `vente_taxes` / `achat_taxes` (nom, taux, montant) | **Vente & BAPA** (« Taxes sur total TTC ») |
| `isRne` | `ventes.est_rne` / `achats.est_rne` (bool) | Case à cocher en tête de formulaire |
| `rne` | `ventes.numero_rne` / `achats.numero_rne` (string) | Champ révélé par la case cochée |
| `footer` (par pièce) | `ventes.pied_de_page` / `achats.pied_de_page` (préremplis depuis l'entreprise) | Formulaire vente / BAPA |
| `commercialMessage` (par pièce) | `ventes.autres_mentions` / `achats.autres_mentions` | Formulaire vente / BAPA |
| `taxes[]` (code TVA) | `produits.code_tva` (enum TVA/TVAB/TVAC/TVAD) | Fiche produit, à côté du taux |

---

## 6. PLAN D'IMPLÉMENTATION

### Lot 1 — Base de données (additif, aucune colonne supprimée)

Migration `2026_08_04_000001_ajouter_remises_et_taxes_fne.php` :

```
produits          + remise_taux      DECIMAL(5,2) DEFAULT 0   -- remise par défaut du produit
                  + code_tva         VARCHAR(8)   DEFAULT 'TVA'

ventes            + remise_taux      DECIMAL(5,2) DEFAULT 0   -- % envoyé en `discount`
                  + est_rne          BOOLEAN      DEFAULT 0
                  + numero_rne       VARCHAR(64)  NULL
                  + pied_de_page     TEXT         NULL
                  + autres_mentions  TEXT         NULL
vente_details     + remise_taux      DECIMAL(5,2) DEFAULT 0   -- `items[].discount`

achats            + remise_taux, est_rne, numero_rne, pied_de_page, autres_mentions
achat_details     + remise_taux
```

Migration `2026_08_04_000002_creer_tables_taxes_personnalisees.php` :

```
produit_taxes         (id, produit_id,        nom, taux DECIMAL(5,2), timestamps)
vente_detail_taxes    (id, vente_detail_id,   nom, taux, timestamps)   -- snapshot à la vente
vente_taxes           (id, vente_id,          nom, taux, montant, timestamps)
achat_detail_taxes    (id, achat_detail_id,   nom, taux, timestamps)
achat_taxes           (id, achat_id,          nom, taux, montant, timestamps)
```

> Les tables `*_detail_taxes` sont des **snapshots** : une taxe modifiée sur la
> fiche produit ne doit jamais réécrire une facture déjà émise.

`ventes.remise` (montant F) **est conservé** — la compta et les 4 modèles PDF en
dépendent. `remise_taux` devient la source du champ DGI ; `remise` reste le montant
calculé (`remise = HT × remise_taux / 100`) pour ne rien casser en aval.

### Lot 2 — Modèles Eloquent

- `Produit` : `+ remise_taux`, `+ code_tva` en `fillable` ; relation `taxes()`.
- `Vente` / `Achat` : `+ remise_taux, est_rne, numero_rne, pied_de_page, autres_mentions` ;
  relation `taxesPersonnalisees()`.
- `VenteDetail` / `AchatDetail` : `+ remise_taux` ; relation `taxes()`.
- Nouveaux modèles : `ProduitTaxe`, `VenteTaxe`, `VenteDetailTaxe`, `AchatTaxe`, `AchatDetailTaxe`.

### Lot 3 — `FneService` (cœur du mapping)

**Vente** :
1. `items[].discount` ← `$d->remise_taux`
2. `items[].customTaxes` ← `$d->taxes->map(['name'=>nom, 'amount'=>taux])`
3. `customTaxes` (racine) ← `$vente->taxesPersonnalisees`
4. `discount` (racine) ← `$vente->remise_taux` (**%**, plus le montant en F)
5. `isRne` ← `$vente->est_rne` ; `rne` ← `$vente->numero_rne`
   *(le paramètre `$estRne` de la signature est conservé en fallback pour ne pas
   casser les appels existants des Jobs)*
6. `footer` ← `$vente->pied_de_page ?: $entreprise->pied_de_page_facture`
7. `commercialMessage` ← `$vente->autres_mentions ?: $entreprise->facture_autres_mentions`
8. `template` : `B2C` forcé si aucun client / NCC vide
9. Retrait de `items[].id` du payload `/sign`
10. `devinerCodeTaxe()` → `codeTaxe($produit)` : lit `produits.code_tva`, retombe sur
    la table corrigée (18→TVA, 9→TVAB, 0→TVAC) si vide

**BAPA** : mêmes points 1-7, plus la correction du tiers (§2.4) :
`clientCompanyName/Phone/Email` ← `$achat->fournisseur`, `clientSellerName` ←
responsable du point de vente ; suppression de `clientNcc`.

**Avoir** : inchangé (déjà conforme).

### Lot 4 — Fiche produit (`produits/index.blade.php` + `ProduitControleur`)

- Champ **`Remise (%)`** (0 → 100) à côté du prix de vente.
- Sélecteur **code TVA DGI** (TVA 18 % / TVAB 9 % / TVAC 0 % exo. conv. / TVAD 0 % exo. lég.).
- Bloc **« Ajouter d'autres taxes »** : bouton qui ajoute une paire
  `[nom] [taux %]`, répétable, supprimable.
- Validation : `remise_taux` et chaque `taux` → `numeric, min:0.01, max:100`
  (règle demandée : *« toutes les taxes et remises : max 100 % et > 0 »*).

### Lot 5 — Formulaires Vente / BAPA / Avoir

**Vente (`ventes/nouvelle.blade.php` + `modifier.blade.php`) :**
- Case à cocher **RNE** en tête ⇒ révèle le champ *N° de reçu* (requis si cochée).
- Champs **Autres mentions** (max 248 car.) et **Pied de page**, préremplis depuis
  les paramètres de l'entreprise, surchargeables par facture.
- Colonne **Remise (%)** sur chaque ligne du panier, **préremplie** depuis
  `produits.remise_taux`, modifiable.
- Bloc **Remise globale** : passe de `Remise (F)` à `Remise (%)` + affichage du
  *Montant de la remise sur le total HT* calculé (comme l'interface FNE).
- Bloc **Taxes sur total TTC** : bouton « Ajouter une taxe » ⇒ trois champs
  `Nom` / `Taxe (%)` / `Montant` (le montant se calcule automatiquement sur le TTC).
- Récapitulatif aligné sur l'interface FNE : Total HT → Remise → Total HT après
  remise → Total TVA → Total TTC → Autres taxes → **Net à payer**.

**BAPA (`achats/nouveau.blade.php`) :** case RNE + n° reçu, remise par ligne,
remise globale %, taxes sur total TTC, pied de page / autres mentions.

**Avoir (modale de `ventes/factures.blade.php`) :** case RNE + n° reçu + pied de
page + autres mentions **pour le document local uniquement** — non transmis à
`/refund` (§4). Un libellé le précisera dans l'UI.

### Lot 6 — Contrôleurs

`VenteControleur::enregistrer()` / `enregistrerModification()` / `creerAvoir*()`,
`AchatControleur::enregistrer()` :
- validation des nouveaux champs (`est_rne`, `numero_rne` requis si `est_rne`,
  `remise_taux` 0-100, taxes `nom` requis + `taux` 0,01-100) ;
- `remise` (F) recalculée depuis `remise_taux` pour préserver la compta existante ;
- remise ligne appliquée avant la remise globale (ordre FNE : ligne → global) ;
- persistance des snapshots de taxes.

### Lot 7 — Paramètres entreprise (`entreprise/parametres.blade.php`)

- Le champ **Autres mentions** existe déjà (`facture_autres_mentions`) : ajout du
  compteur de caractères et de la limite **248**.
- Champ **Pied de page** : idem (limite à confirmer, §7).
- `EntrepriseControleur` : `max:1000` → `max:248` sur ces deux champs.

### Lot 8 — Modèles d'impression

Ajout dans les 4 modèles de `factures/vente.blade.php`, dans `factures/bapa.blade.php`
et `factures/achat.blade.php` :
- bloc **Autres mentions** (haut de facture, comme `commercialMessage`) ;
- bloc **Pied de page** (bas de facture) ;
- colonne **Remise (%)** par ligne ;
- lignes **Autres taxes** et **Net à payer** dans le récapitulatif ;
- mention **« Rattachée au reçu n° … »** quand `est_rne`.

### Lot 9 — Tests

- Test unitaire du payload : une vente avec 2 articles (remise ligne + taxes
  personnalisées), remise globale %, 1 taxe sur TTC, RNE coché ⇒ JSON **identique**
  à `exemple de json vente.txt`.
- Test BAPA ⇒ JSON identique à `BAPA( déclaration des bordereau d'achat).txt`.
- Test avoir ⇒ JSON identique à `json avoir.txt`.
- Test de non-régression : facture sans remise ni taxe ⇒ payload inchangé par
  rapport à l'existant (hors retrait de `items[].id`).

---

## 7. DÉCISIONS RETENUES

1. **Limite des mentions : 248 caractères**, pour « Autres mentions » comme pour
   « Pied de page » (validé).
2. **TVAC vs TVAD : les deux modes cohabitent** (validé) — le code est déduit
   automatiquement du taux et du régime (0 % + TEE/RNE ⇒ TVAD, sinon TVAC), et une
   case « Choisir le code manuellement » sur la fiche produit rend la main à
   l'utilisateur.
3. **Ordre des remises** : remise de ligne d'abord, remise globale ensuite sur le
   total HT obtenu — conforme au récapitulatif de la FNE.
4. **`ventes.remise` (F) conservée** en parallèle de `remise_taux` (%) : le taux
   alimente la DGI, le montant reste la base des écritures comptables.

---

## 8. ÉTAT DE LIVRAISON

Les 9 lots sont implémentés. Ce qui a été livré au-delà du plan initial :

- **Suppression du champ « Code FNE » des points de vente** — la DGI attend le
  *nom* du point de vente dans `pointOfSale` ; le code technique n'avait pas lieu
  d'être (migration de retrait + nettoyage du formulaire et du contrôleur).
- **Calcul de TVA corrigé à l'impression** : les modèles appliquaient 18 % au
  total dès qu'un article était taxé, y compris aux lignes exonérées. La TVA est
  désormais calculée ligne par ligne au taux réel.
- **Modèle 4** : le code TVA affiché était figé (`TVA (18%)` / `TVAD (0)`), il
  reflète maintenant le code réel de chaque ligne.
- **Pied de page du modèle standard** : un bloc codé en dur portait les
  coordonnées d'une autre société ; il affiche désormais celles de l'entreprise
  émettrice, suivies des mentions et du pied de page paramétrés.
- **Migrations rendues exécutables hors MySQL** (retrait de contrainte par nom,
  `SHOW INDEX`, `ALTER TABLE … MODIFY COLUMN`, index à supprimer avant sa
  colonne) et `APP_KEY` de test ajoutée : la suite de tests ne pouvait pas
  s'exécuter du tout auparavant.

**Tests** : `tests/Feature/FnePayloadTest.php` — 16 tests, 61 assertions, tous au
vert (suite complète : 18 tests).

### Limites assumées

- Sur la page **Avoir**, la case RNE, les mentions et le pied de page ne servent
  qu'au document imprimé : l'endpoint `/refund` n'accepte que `items[]` (§4).
- Les **taxes personnalisées ne sont pas ajoutées à `montant_ttc`** : ce montant
  reste la base des écritures comptables et de la trésorerie existantes. Elles
  sont stockées avec leur montant, affichées dans le « Net à payer » et
  transmises à la FNE, qui les recalcule de son côté.
