j'ai pas demader de retirer le contenu de cette page  , garde toujour ce contenu de la page   < 
Gestion FNE : <!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion FNE — Selflow</title>
  <!-- Tailwind CSS CDN pour un style fluide et moderne -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- FontAwesome pour les icônes -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f3f4f6; }
    .fne-card { transition: transform 0.2s, box-shadow 0.2s; }
    .fne-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
  </style>
</head>
<body class="bg-gray-100 text-gray-800">

  <div class="flex h-screen overflow-hidden">
    
    <!-- SIDEBAR SELFLOW (Rappel du menu existant) -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between hidden md:flex">
      <div>
        <div class="p-4 border-b border-slate-800 flex items-center space-x-3">
          <div class="bg-blue-600 text-white font-bold p-2 rounded-lg text-lg">S</div>
          <div>
            <h1 class="font-bold text-white leading-none">Selflow</h1>
            <span class="text-xs text-slate-400">Gestion Commerciale</span>
          </div>
        </div>

        <nav class="p-4 space-y-6 text-sm">
          <div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Tableau de bord</div>
            <a href="#" class="flex items-center space-x-2 p-2 rounded hover:bg-slate-800"><i class="fa-solid fa-chart-line w-5"></i><span>TDB Général</span></a>
          </div>

          <div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Fiscalité & DGI</div>
            <a href="#" class="flex items-center space-x-2 p-2 rounded bg-blue-600 text-white font-medium">
              <i class="fa-solid fa-file-invoice-dollar w-5"></i>
              <span>Gestion FNE</span>
            </a>
          </div>

          <div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Ventes & Achats</div>
            <a href="#" class="flex items-center space-x-2 p-2 rounded hover:bg-slate-800"><i class="fa-solid fa-cart-shopping w-5"></i><span>Factures vente</span></a>
            <a href="#" class="flex items-center space-x-2 p-2 rounded hover:bg-slate-800"><i class="fa-solid fa-bag-shopping w-5"></i><span>Factures achat</span></a>
          </div>
        </nav>
      </div>

      <div class="p-4 border-t border-slate-800 text-xs text-slate-500">
        Connecté à l'API FNE v1.0.2
      </div>
    </aside>

    <!-- CONTENU PRINCIPAL -->
    <main class="flex-1 overflow-y-auto p-6 space-y-6">

      <!-- EN-TÊTE PAGE & IDENTITÉ FISCALE FNE -->
      <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
          <div class="flex items-center space-x-3">
            <h1 class="text-2xl font-bold text-gray-900">Module &lt;GESTION FNE&gt;</h1>
            <span class="bg-emerald-100 text-emerald-800 text-xs font-semibold px-2.5 py-0.5 rounded-full border border-emerald-300">
              <i class="fa-solid fa-circle-check mr-1"></i> API DGI Connectée
            </span>
          </div>
          <p class="text-sm text-gray-500 mt-1">Données informatives et suivi de conformité issus de la plateforme FNE DGI</p>
        </div>

        <!-- Fiche d'identification DGI rapide -->
        <div class="bg-slate-800 text-white p-3 rounded-lg text-xs grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-1 border-l-4 border-amber-500">
          <div><span class="text-slate-400">Raison Sociale:</span> <strong class="block">MANDIOBA N'GONDIÉ</strong></div>
          <div><span class="text-slate-400">NCC:</span> <strong class="block">4272107A</strong></div>
          <div><span class="text-slate-400">Régime:</span> <strong class="block">TEE</strong></div>
          <div><span class="text-slate-400">Centre Impôts:</span> <strong class="block truncate">964 - Deux-Plateaux I</strong></div>
          <div><span class="text-slate-400">Secteur:</span> <strong class="block truncate">Commerce Général</strong></div>
          <div><span class="text-slate-400">IDU:</span> <strong class="block">0</strong></div>
        </div>
      </div>

      <!-- BARRE DE FILTRES GLOBALE (Temporel & Point de Vente) -->
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200 flex flex-wrap items-center justify-between gap-4">
        
        <div class="flex flex-wrap items-center gap-3">
          <span class="text-sm font-semibold text-gray-700"><i class="fa-solid fa-filter text-blue-600 mr-1"></i> Filtres :</span>
          
          <!-- Select Période -->
          <div class="flex items-center space-x-1 bg-gray-50 border border-gray-300 rounded-lg p-1">
            <input type="date" value="2026-07-23" class="bg-transparent text-sm font-medium text-gray-700 px-2 py-1 outline-none">
          </div>

          <!-- Filtre rapide -->
          <select class="bg-gray-50 border border-gray-300 text-gray-700 text-sm rounded-lg p-2 font-medium">
            <option value="jour">Jour spécifique</option>
            <option value="semaine" selected>Semaine du mois (Sem. 3)</option>
            <option value="mois">Mois (Juillet 2026)</option>
            <option value="annee">Année (2026)</option>
          </select>

          <!-- Filtre Point de Vente -->
          <select class="bg-gray-50 border border-gray-300 text-gray-700 text-sm rounded-lg p-2 font-medium">
            <option value="tous">Tous les Points de Vente</option>
            <option value="siege" selected>Siège</option>
            <option value="magasin_a">Magasin A</option>
          </select>
        </div>

        <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg flex items-center space-x-2 transition">
          <i class="fa-solid fa-rotate"></i>
          <span>Synchroniser DGI</span>
        </button>
      </div>

      <!-- BOUTONS-SECTIONS (Onglets de sous-navigation) -->
      <div class="flex space-x-2 border-b border-gray-200 pb-1">
        <button class="px-4 py-2 bg-blue-600 text-white font-medium text-sm rounded-t-lg shadow-sm">
          <i class="fa-solid fa-chart-pie mr-2"></i>Vue Synthétique DGI
        </button>
        <button class="px-4 py-2 bg-white text-gray-600 hover:bg-gray-50 font-medium text-sm rounded-t-lg border-t border-x border-gray-200">
          <i class="fa-solid fa-arrow-down-left mr-2 text-emerald-600"></i>Entrées (Ventes FNE)
        </button>
        <button class="px-4 py-2 bg-white text-gray-600 hover:bg-gray-50 font-medium text-sm rounded-t-lg border-t border-x border-gray-200">
          <i class="fa-solid fa-arrow-up-right mr-2 text-rose-600"></i>Sorties (Achats FNE)
        </button>
        <button class="px-4 py-2 bg-white text-gray-600 hover:bg-gray-50 font-medium text-sm rounded-t-lg border-t border-x border-gray-200">
          <i class="fa-solid fa-ticket mr-2 text-amber-500"></i>Stickers & Timbres
        </button>
      </div>

      <!-- BLOC 1: SUIVI DES STICKERS & CRÉDITS (Spécifique FNE) -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm fne-card border-l-4 border-emerald-500">
          <div class="text-xs font-semibold text-gray-500 uppercase">Solde Stickers Disponible</div>
          <div class="text-2xl font-bold text-gray-900 mt-1">700 CFA</div>
          <div class="text-xs text-emerald-600 mt-2"><i class="fa-solid fa-shield-halved"></i> Réserve valide</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm fne-card border-l-4 border-blue-500">
          <div class="text-xs font-semibold text-gray-500 uppercase">Achats de Stickers</div>
          <div class="text-2xl font-bold text-gray-900 mt-1">0 CFA</div>
          <div class="text-xs text-gray-400 mt-2">Cumul sur la période</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm fne-card border-l-4 border-amber-500">
          <div class="text-xs font-semibold text-gray-500 uppercase">Stickers Consommés</div>
          <div class="text-2xl font-bold text-gray-900 mt-1">20 CFA</div>
          <div class="text-xs text-gray-500 mt-2">1 sticker utilisé (Facture)</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm fne-card border-l-4 border-purple-500">
          <div class="text-xs font-semibold text-gray-500 uppercase">Timbre de Quittance</div>
          <div class="text-2xl font-bold text-gray-900 mt-1">0 CFA</div>
          <div class="text-xs text-gray-400 mt-2">Quantité: 0</div>
        </div>

      </div>

      <!-- BLOC 2: KPIS VENTES & ENTRÉES CERTIFIÉES DGI -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center">
          <i class="fa-solid fa-file-invoice text-blue-600 mr-2"></i> Indicateurs des Entrées (Ventes certifiées DGI)
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
            <span class="text-xs text-gray-500 block">Factures de vente</span>
            <span class="text-lg font-bold text-gray-900">1</span>
            <span class="text-xs block text-blue-600 font-semibold mt-1">800 000 CFA</span>
          </div>

          <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
            <span class="text-xs text-gray-500 block">Factures d'avoir</span>
            <span class="text-lg font-bold text-gray-900">0</span>
            <span class="text-xs block text-gray-400 mt-1">0 CFA</span>
          </div>

          <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
            <span class="text-xs text-gray-500 block">Reçus de vente</span>
            <span class="text-lg font-bold text-gray-900">0</span>
            <span class="text-xs block text-gray-400 mt-1">0 CFA</span>
          </div>

          <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
            <span class="text-xs text-gray-500 block">Proforma</span>
            <span class="text-lg font-bold text-gray-900">0</span>
            <span class="text-xs block text-gray-400 mt-1">0 CFA</span>
          </div>

          <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
            <span class="text-xs text-gray-500 block">Bordereaux d'achat</span>
            <span class="text-lg font-bold text-gray-900">0</span>
            <span class="text-xs block text-gray-400 mt-1">0 CFA</span>
          </div>
        </div>

        <!-- Barre de totaux DGI -->
        <div class="mt-5 pt-4 border-t border-gray-200 grid grid-cols-2 md:grid-cols-6 gap-4 text-center">
          <div class="bg-blue-50 p-2 rounded-lg">
            <span class="text-xs text-blue-700 block">Total HT</span>
            <strong class="text-sm text-blue-900">800 000 CFA</strong>
          </div>
          <div class="bg-blue-50 p-2 rounded-lg">
            <span class="text-xs text-blue-700 block">Total VAT (TVA)</span>
            <strong class="text-sm text-blue-900">0 CFA</strong>
          </div>
          <div class="bg-blue-50 p-2 rounded-lg">
            <span class="text-xs text-blue-700 block">Autres Taxes</span>
            <strong class="text-sm text-blue-900">0 CFA</strong>
          </div>
          <div class="bg-blue-50 p-2 rounded-lg">
            <span class="text-xs text-blue-700 block">Remises</span>
            <strong class="text-sm text-blue-900">0 CFA</strong>
          </div>
          <div class="bg-blue-50 p-2 rounded-lg">
            <span class="text-xs text-blue-700 block">Timbre Fiscal</span>
            <strong class="text-sm text-blue-900">0 CFA</strong>
          </div>
          <div class="bg-emerald-600 text-white p-2 rounded-lg">
            <span class="text-xs block opacity-90">Total TTC Certifié</span>
            <strong class="text-base">800 000 CFA</strong>
          </div>
        </div>
      </div>

      <!-- BLOC 3: ACHATS & SORTIES RÉCEPTIONNÉES -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center">
          <i class="fa-solid fa-boxes-packing text-rose-600 mr-2"></i> Factures & Reçus Réceptionnés (Achats DGI)
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-4 border border-gray-200 rounded-lg flex justify-between items-center">
            <div>
              <span class="text-xs text-gray-500 block">Factures/Reçus Réceptionnés</span>
              <span class="text-xl font-bold text-gray-900">0</span>
            </div>
            <i class="fa-solid fa-file-arrow-down text-2xl text-gray-300"></i>
          </div>

          <div class="p-4 border border-gray-200 rounded-lg flex justify-between items-center">
            <div>
              <span class="text-xs text-gray-500 block">Total Achats TTC</span>
              <span class="text-xl font-bold text-gray-900">0 CFA</span>
            </div>
            <i class="fa-solid fa-coins text-2xl text-gray-300"></i>
          </div>

          <div class="p-4 border border-gray-200 rounded-lg flex justify-between items-center">
            <div>
              <span class="text-xs text-gray-500 block">TVA Déductible Estimée</span>
              <span class="text-xl font-bold text-emerald-600">0 CFA</span>
            </div>
            <i class="fa-solid fa-percent text-2xl text-emerald-200"></i>
          </div>
        </div>
      </div>

    </main>
  </div>

</body>
</html>      
 > mais fais fonctionner ce que j'ai demader   et ajoute ce que j'ai demander , lis correctement ce que j'ai dis , et du vera : 
ok c'est bien , ne change rien , dévéloppe les autre pages des boutons <Entrées (Ventes FNE) , 
Sorties (Achats FNE)  , Stickers & Timbres >  rend les  fonctionnel  , rend  aussi les filtres fonctionnel , 
et retire les autres élément de la side barre  dévéloppe juste  ce module   

et pour le style , utilise celui de mon application uniquement , cherche et trouve mes fichier de style utilisé 

cette page  < Gestion FNE> faite concerne uniquement les information des factures normalisé mais ne reflete pas la situation complete de l'entreprise car il y aussi des factures qui ne sont pas normaliséer et de tous type ( avoir ( client flounissauer ) , achat , vente .... , et aussi des mouvent de stock comme des transformation de matiere premiere en  produit fini et aussi des transfere interne : a prendre en compte dans le KPI   ) alors , crer un bouton appeler situation generale  ( lui n'affichera pas comme titre < Module <GESTION FNE> >,, cette page devra faire changer les information on dois retrouver de nouvelle données sur la page ,  de nouvelle KPI montrant la situation rélle , les entrées (chiffre d'affire ) lers sortie , la tréso , ajoutes des filtres aussi pour savoir le jours , semaine , mois , années) avoir une section qui détail le nombre de facture déclarer sur combien , le nombre de facture non déclarer sur combien , détaillé par type aussi , donné kleurs montant globalement et aussi par type de facture , ajoute aussi des sous boutons qui vont affiché des détailles 


maintenant fais une deuxieme page appelé     <Facture /recu émis et recu > dans cette page , fais jutes 2 a trois KPI sur la meme ligne et fais un tableau qui va stocker les facutres / recu  qui ont été recu  , 
 un autre tableau qui va stocker ceux émis  aec les champs de la DGI ( FNE ) fais une recherche sur les champ utlisé : le tableau de dois pas rammer  , tres fuide et instantanné , ajoute une pagination .
n'oublie pas qu'il dois y avoir une colonne ou les recu / facture seron stocker et une colone action en plus pour télécharger 

on dois avoir  un bouton pour swichter sur les achats et un autre pour swichter sur les ventes

achat dois avoir des  sous  bouton (   recu/facture recu , recu/facture émit , avoirfournisseur , avoir client   ) 

pareille pour vente 

Dans le tableau quand c'est un recu il faut mettre une colonne pour dire que c'est un recu et quand c'est une facture pareille  

n'oublie pas d'ajouter des filitre pour tous ces tableau  .

-----------------------------------------------------------------------------------------------------------------------------------------------------------------------

voila tu as tous ; nalyse et propose moi quelque chose 

j'ai le contenu  , j'aime les KPI  de la page  : Gestion FNE du fichier C:/BUREAU/SELFLOW/maquette_MODULE_FNE.htm  , utilise le , garde ca ,  et n'oublie pas reste dans le style de l'application Selflow 

pour le fichier < autre page-situation-général et facture,recu émit .html> utilise seulement les deux autre page  < Situation Générale et  Factures / Reçus Émis & Recus > 



ce qui a été rater au niveau du  de la page < Factures / Reçus Émis & Recus > c'est qu'on ne vois pas les les factures totales de l'entrerpise en vois , uniquement ceux normalisé , donc revois cela 

ces pages sont juste des maquette donc , travail fais moi quelquechose de tres fonctionnele et cohérent ,  et les information des factures recu et émis dois etre mis a jour normalement des a recu une facture soir elle dois siresctement apparaitre ou a travers un bouton on va réactualiser pour avoir les information et pour mettre a jour les KPI 


-----------------------------------------------------------------------------------------------------------------------------------------------------------------------
A TITRE INFORMATIF :


avant le tous debut de ce fichier , voici le debut  de la conversation : 

moi : regarde les pages de mon application et fais une analyse de chaque section , nous allons faire un module  <GESTION FNE> dans cette partie , le but sera d'avoir les kpi de la DGI comme sur leur plateforme FNE car nous allons intégrer leur API dans notre logiciel  , ca sera a titre informatif , aucun traiement laba , il y aura des filtre , des , des bouton sous forme de fitre aussi ( comme des section ) 

lis les données fais ton analyse des que tu termine fais signe et on ira au niveau de la FNE 

releve les information qui peuvent servir comme indicateur pour faitre une analyse des entrés , des sorties , de bon kpi financier pour un responsable et ajoutes un filtre pour filtré sur un jour , semaine d'un moi  , un mois , une année et aussi par point de vente 

cela sera destiné a une page 

reponse : Voici l'analyse de l'application **Selflow** à partir des éléments de votre interface actuelle, suivie d'une proposition complète pour la conception du nouveau module ****.

---

## 1. Analyse des sections existantes de l'application

L'application dispose déjà d'un découpage modulaire très complet :

* **Ventes & Achats :** Gestion du cycle complet (Devis, Commandes, Factures, Avoirs). Dans la table des factures ventes, un champ **« NORMALISÉE (DGI) »** (Actuellement à *Non*) et un bouton **« Normaliser »** sont déjà prévus.
* **Comptabilité & Trésorerie :** Suivi fin des encaissements, décaissements, solde/journal, créances et règlements.
* **Stock & Production :** Suivi des mouvements, recettes et ordres de production.
* **Multi-Points de vente & Tiers :** Gestion par point de vente (ex: *Siège*) et par client/fournisseur (*B-HOME SARL*, *SOTRA SA*, etc.).

---

## 2. Structure proposée pour la page `<GESTION FNE>`

Ce module sera une vue **dashboard analytique et informative** (sans action de traitement direct) permettant à la direction et aux responsables de suivre la conformité fiscale et la performance financière issue des factures normalisées.

```
+-----------------------------------------------------------------------------------+
|  [Filtres Temporels]  | Jour | Semaine du mois | Mois | Année | [Sélection PDV]   |
+-----------------------------------------------------------------------------------+
|  [Boutons-Onglets]   | [ Vue Globale FNE ]  | [ Ventes FNE ]  | [ Achats FNE ]    |
+-----------------------------------------------------------------------------------+
|  KPIs Clés (Cartes synthétiques)                                                 |
|  Graphs (Entrées vs Sorties, Taux de normalisation, TVA collectée/déductible)      |
+-----------------------------------------------------------------------------------+

```

---

## 3. Relevé des indicateurs clés (KPIs) pour le responsable

### A. Analyse des Ventes (Entrées) & Normalisation

* **Volume Ventes Normalisées :** Chiffre d'affaires TTC & HT total certifié FNE.
* **Taux de Normalisation Ventes (%) :** Nombre de factures ventes normalisées / Nombre total de factures émises.
* **TVA Collectée (DGI) :** Total de la TVA issue des factures de vente certifiées DGI.
* **Avoirs Ventes Normalisés :** Total des avoirs émis et enregistrés côté DGI.

### B. Analyse des Achats (Sorties)

* **Volume Achats Normalisés :** Total TTC & HT des factures fournisseurs validées FNE.
* **TVA Déductible (DGI) :** Total TVA récupérable sur les achats certifiés.
* **Taux de Conformité Fournisseurs (%) :** Proportion de factures d'achat reçues avec un sticker/QR Code FNE valide.

### C. Indicateurs Financiers & Solde Fiscaux (Balance DGI)

* **TVA Nette à Payer :** $\text{TVA Collectée} - \text{TVA Déductible}$.
* **Ratio d'Encaissement sur Factures Normalisées :** Montant total encaissé vs Montant restant à recouvrer sur factures certifiées.
* **Ventilation par Mode de Paiement :** Part du CA normalisé réalisé en Caisse / Espèces, Virement, Mobile Money ou Crédit.

---

## 4. Système de Filtres et Navigation (Boutons-Sections)

### Filtres Globaux (Barre supérieure)

1. **Filtre Période :**
* *Jour* (Sélection d'une date spécifique)
* *Semaine du mois* (Ex: Semaine 1, Semaine 2 du mois sélectionné)
* *Mois* (Ex: Juillet 2026)
* *Année* (Ex: 2026)


2. **Filtre Structure :**
* *Point de Vente* (Tous, Siège, Magasin A, Magasin B, etc.)



### Boutons-Filtres / Onglets de Section (Navigation rapide)

* **[ 📊 Vue Synthétique DGI ] :** Balance TVA, CA certifié global, statut global des transmissions FNE.
* **[ 📥 Entrées (Ventes) ] :** Détail des KPIs ventes, répartition par type de client (B2B vs passage).
* **[ 📤 Sorties (Achats) ] :** Détail des KPIs achats et déductibilité TVA.
* **[ ⚠️ Anomalies & Non-Conformes ] :** Suivi des factures en attente d'API FNE, échecs de normalisation ou rejets DGI.

---

Je suis prêt pour l'étape suivante. Pouvez-vous me partager les spécifications ou la structure exacte des données de la plateforme FNE (ou de leur API) pour que nous alignions ces KPIs ?


-----------------------------------------------------------

moi : voici le FNE regarde les pages de mon application et fais une analyse de chaque section , nous allons faire un module  <GESTION FNE> dans cette partie , le but sera d'avoir les kpi de la DGI comme sur leur plateforme FNE car nous allons intégrer leur API dans notre logiciel  , ca sera a titre informatif , aucun traiement laba , il y aura des filtre , des , des bouton sous forme de fitre aussi ( comme des section ) 

lis les données fais ton analyse des que tu termine fais signe et on ira au niveau de la FNE 

releve les information qui peuvent servir comme indicateur pour faitre une analyse des entrés , des sorties , de bon kpi financier pour un responsable et ajoutes un filtre pour filtré sur un jour , semaine d'un moi  , un mois , une année et aussi par point de vente 

cela sera destiné a une page  : 

reponse :  Voici l'analyse complète de vos deux interfaces (Selflow et la Plateforme FNE DGI) afin d'harmoniser les indicateurs pour votre futur module <GESTION FNE>.1. Analyse des éléments clés de la plateforme FNE DGIEn examinant le tableau de bord officiel de la FNE, voici les blocs d'informations et métriques restitués par la DGI :A. Identification & Statut FiscalEntreprise & Régime : Raison sociale, Régime d'imposition (ex: TEE), Secteur d'activité, IDU, NCC, Direction de rattachement, Centre des impôts.B. Vue d'Ensemble des Flux (Factures & Reçus)Reçus et factures réceptionnés : Quantité.Reçus et factures émis : Quantité.Détail des Factures :Factures de vente : Quantité & Montant (CFA).Factures d'avoir : Quantité & Montant (CFA).Proforma & Bordereaux d'achat : Quantité & Montant (CFA).Totaux financiers : Total HT, Total TVA (VAT), Autres taxes, Remises, Timbre fiscal, Total TTC.Détail des Reçus de Vente : Reçus de vente, Reçus d'avoir, Total autres taxes, Timbre fiscal.C. Suivi des Stickers et TimbresCrédit de Stickers : Solde de stickers (CFA), Achat de stickers, Stickers consommés.Consommation par canal : Factures, Reçus de vente, Espèces.Timbres : Timbre de Quittance (Quantité & Montant).2. Relevé des Indicateurs (KPIs) pour la page <GESTION FNE> dans SelflowLe module servira de Dashboard de pilotage et de conformité fiscale pour le responsable, articulé autour des Entrées, des Sorties et de la Santé financière/Stickers.A. Indicateurs d'Entrées (Ventes / Chiffre d'Affaires certifié)CA Total Ventes Normalisées (TTC & HT) : Montant cumulé des factures de vente certifiées DGI.Nombre de Factures & Reçus Ventes Émis : Volume d'actes de vente certifiés.TVA Collectée (DGI) : Total de la TVA issue des ventes certifiées FNE.Avoirs Ventes (Quantité & Montant) : Impact des annulations/crédits sur le CA certifié.Part des Reçus vs Factures : Ventilation des ventes au comptant (reçus) vs factures à terme.B. Indicateurs de Sorties (Achats & Déductibilité)Total Achats Réceptionnés (TTC & HT) : Volume des factures fournisseurs reçues et enregistrées DGI.TVA Déductible (DGI) : TVA récupérable déclarée via les factures d'achat reçues.Avoirs Achats Réceptionnés : Réductions ou annulations obtenues des fournisseurs.C. KPIs Financiers & Fiscaux pour le ResponsableBalance Nette TVA (DGI) : $\text{TVA Collectée} - \text{TVA Déductible}$ (Estimation de la taxe à reverser).Solde du Crédit de Stickers (CFA) : Niveau de réserve disponible pour continuer la normalisation automatique via l'API.Consommation de Stickers : Taux d'utilisation par période (Factures vs Reçus vs Espèces).Total Timbres Fiscaux & Autres Taxes : Taxes annexes collectées pour le compte de l'État.3. Structure de la Page & Filtres proposed pour SelflowBarre de Filtres Globale (Haut de page)Filtre Période Temporelle :Jour (Sélection d'une date spécifique)Semaine du mois (Semaine 1, 2, 3 ou 4 du mois choisi)Mois (Mois spécifique, ex: Juillet 2026)Année (Année spécifique, ex: 2026)Filtre Point de Vente :Sélection multi-site (Tous, Siège, Magasin A, etc.).Boutons de Navigation / Sections (Style sous-onglets)[ 📊 Vue Globale & Synthèse DGI ] : Informations entreprise, solde du crédit de stickers, balance TVA et résumé général.[ 📥 Entrées (Ventes & Reçus FNE) ] : Détail des factures/reçus émis, TVA collectée, remises et timbres fiscaux.[ 📤 Sorties (Achats & Réceptions) ] : Détail des factures fournisseurs reçues et TVA déductible.[ 🏷️ Stickers & Timbres ] : Historique des achats, consommation par canal et alertes de seuil bas.Je suis prêt ! Tout est analysé et prêt à être décliné selon vos besoins pour la FNE.


-----------------------------------------------------------
moi : fais moi un fichier txt et met moi un plan de route 


reponse : Voici la structure détaillée du fichier texte d'un plan de route complet pour le développement et l'intégration du module <GESTION FNE> dans votre logiciel Selflow.

Vous pouvez copier-coller l'encadré ci-dessous directement dans un fichier .txt (par exemple PLAN_DE_ROUTE_MODULE_FNE.txt).

================================================================================
           PLAN DE ROUTE : MODULE <GESTION FNE> - SELFLOW
================================================================================
Objectif : Créer une page de tableau de bord informative et analytique 
           restituant les KPI fiscaux de la DGI (FNE) après intégration API.
Nature du module : Consultation / Pilotage (aucun traitement direct).
================================================================================

--------------------------------------------------------------------------------
1. ARCHITECTURE ET MAQUET TAGE DE LA PAGE
--------------------------------------------------------------------------------
• Emplacement : /admin/fne ou /admin/gestion-fne
• Disposition globale :
  [ BARRE DE FILTRES GLOBALE ]
  [ BOUTONS-SECTIONS / ONGLETS DE NAVIGATION ]
  [ CARTE IDENTITÉ FISCALE & RAPPEL CREDITS ]
  [ BLOCS KPI & GRAPHIQUES ANALYTIQUES ]

--------------------------------------------------------------------------------
2. SPÉCIFICATION DES FILTRES DE NAVIGATION
--------------------------------------------------------------------------------
A. Filtres Temporels & Structurels (Barre supérieure) :
   - Jour : Sélection d'une date spécifique (ex: 23/07/2026)
   - Semaine du mois : Choix de la semaine (S1, S2, S3, S4, S5) + Mois/Année
   - Mois : Sélection du mois (ex: Juillet 2026)
   - Année : Sélection de l'année (ex: 2026)
   - Point de Vente : Dropdown multi-sites (Tous les PDV, Siège, etc.)

B. Boutons Sous-Sections (Onglets filtrants) :
   - [ 📊 Vue Synthétique DGI ]  : TDB exécutif, balance globale, état des crédits.
   - [ 📥 Entrées (Ventes FNE) ] : Détail des KPI de ventes et reçus émis.
   - [ 📤 Sorties (Achats FNE) ] : Détail des KPI d'achats et réceptions.
   - [ 🏷️ Stickers & Timbres ]  : Suivi de consommation et stock de stickers DGI.

--------------------------------------------------------------------------------
3. RELEVÉ DES INDICATEURS CLÉS (KPI) À AFFICHER
--------------------------------------------------------------------------------
A. FICHE IDENTITÉ FISCALE DGI (Entête) :
   - Raison Sociale, Régime d'imposition (TEE, etc.), Secteur d'activité, IDU, NCC
   - Direction de rattachement & Centre des Impôts / Poste comptable

B. INDICATEURS D'ENTRÉES (VENTES & REÇUS ÉMIS) :
   - Total Ventes Normalisées (TTC, HT, TVA/VAT)
   - Nombre de Factures de Vente Normalisées
   - Volume et Montant des Factures d'Avoir Ventes
   - Nombre et Montant des Reçus de Vente Normalisés
   - Volume des Proformas & Bordereaux émis
   - Montant Total Remises & Autres Taxes collectées

C. INDICATEURS DE SORTIES (ACHATS & RÉCEPTIONS) :
   - Total Factures & Reçus Réceptionnés (Achats certifiés)
   - Montant Total HT / TTC des Achats DGI
   - TVA Déductible Récupérée (VAT Achats)
   - Volume et Montant des Avoirs Achats Réceptionnés

D. INDICATEURS FINANCIERS ET RECOUUVREMENT FISCAL :
   - Balance Nette TVA DGI : (TVA Collectée Ventes) - (TVA Déductible Achats)
   - Montant Total Timbres Fiscaux (Quittance)
   - Taux de Normalisation Interne (%) : Factures Selflow certifiées vs total

E. SUIVI DE LA RÉSERVE ET CONSOMMATION DES STICKERS :
   - Solde de Stickers disponible (en CFA)
   - Achats cumulés de Stickers (CFA)
   - Stickers consommés (CFA & Quantité)
   - Ventilation de la consommation :
     * Nombre de stickers consommés sur Factures
     * Nombre de stickers consommés sur Reçus de vente
     * Nombre de stickers consommés sur transactions Espèces

--------------------------------------------------------------------------------
4. PHASES DE DÉVELOPPEMENT LOGICIEL
--------------------------------------------------------------------------------
PHASE 1 : Modélisation des Données & API FNE
• Création des tables de stockage local pour l'archivage des réponses API DGI :
  - `fne_identite` (Infos entreprise & centre d'impôts)
  - `fne_stickers_log` (Suivi solde et consommations)
  - `fne_transactions_kpi` (Agrégation des factures/reçus validés)
• Définition des webhooks / endpoints API pour synchroniser les données DGI.

PHASE 2 : Développement du Frontend / Interface Selflow
• Intégration du composant de filtres (Jour, Semaine du mois, Mois, Année, PDV).
• Implémentation des boutons-onglets dynamiques sans rechargement de page.
• Création des cartes KPI (Widgets) et graphiques comparatifs (Chart.js / ApexCharts).

PHASE 3 : Connectivité API & Calculs Automatiques
• Implémentation de la logique de calcul automatique des KPI (Balance TVA, etc.).
• Gestion du rafraîchissement des données informatives depuis la plateforme FNE.

PHASE 4 : Recette & Validation Responsable
• Test d'affichage multi-points de vente.
• Vérification de la correspondance parfaite entre les chiffres Selflow et DGI.
================================================================================


----------------------------------------------------------------------------
moi : fais moi une marquette , un exemple de ctte page en html  avec le style de mon application et celui de la FNE  , un stymle cohérent 

reponse : fichier C:/BUREAU/SELFLOW/maquette_MODULE_FNE.htm



================================================================================================================================================================
                                                                FIN DE L'INFORMATION

================================================================================================================================================================
Maitenant tu as tous , ne soit pas perdu , c'est juste a titre informatif pour que tu es tous , maintenant lis les fichier propose quelquechose 

il sont dans le meme dossier NOUVEAU MODULE 


