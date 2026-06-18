@extends('admin::gabarits.application')

@section('titre', 'Nouvelle vente')
@section('topbar_titre', 'Nouvelle vente')

@section('styles')
<style>
    .pos-grid { display: grid; grid-template-columns: 1fr 360px; gap: 22px; align-items: start; }

    .produit-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
    .produit-card {
        background: var(--bg3); border: 2px solid var(--border);
        border-radius: 10px; padding: 14px; cursor: pointer;
        transition: all .15s; text-align: center;
        user-select: none;
    }
    .produit-card:hover { border-color: var(--primary); background: rgba(99,102,241,.08); transform: translateY(-2px); }
    .produit-card.out-of-stock { opacity: .65; border-color: rgba(239,68,68,.3); }
    .produit-card.out-of-stock:hover { border-color: var(--warning); background: rgba(245,158,11,.08); }
    .produit-card .produit-nom { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
    .produit-card .produit-prix { color: var(--success); font-weight: 700; font-size: 15px; }
    .produit-card .produit-stock { font-size: 11px; color: var(--text-3); margin-top: 4px; }
    .produit-card .produit-cat { font-size: 10px; color: var(--primary); text-transform: uppercase; margin-bottom: 6px; }

    .panier-item {
        display: flex; align-items: center; gap: 10px;
        background: var(--bg3); border-radius: 8px; padding: 10px 12px;
        margin-bottom: 8px;
    }
    .panier-item .item-nom { flex: 1; font-weight: 600; font-size: 13px; }
    .panier-item .item-prix { color: var(--text-3); font-size: 12px; }
    .qte-ctrl { display: flex; align-items: center; gap: 4px; }
    .qte-btn {
        width: 26px; height: 26px; border-radius: 6px;
        border: none; cursor: pointer; font-size: 14px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        background: var(--border); color: var(--text); transition: background .12s;
    }
    .qte-btn:hover { background: var(--primary); color: #fff; }
    .qte-input {
        width: 44px; height: 26px; text-align: center; font-weight: 700;
        border: 1px solid var(--border); border-radius: 6px; background: #fff;
        outline: none; font-size: 13px;
    }
    .qte-input:focus { border-color: var(--primary); }
    .remove-btn {
        background: none; border: none; color: var(--text-3);
        cursor: pointer; font-size: 14px; transition: color .12s;
    }
    .remove-btn:hover { color: var(--danger); }

    .total-box {
        border-top: 1px solid var(--border); margin-top: 12px; padding-top: 12px;
    }
    .total-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; color: var(--text-2); }
    .total-row.grand { font-size: 17px; font-weight: 800; color: var(--text); }

    .categorie-filter { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .cat-btn {
        padding: 5px 14px; border-radius: 20px; border: 1px solid var(--border);
        background: var(--bg3); color: var(--text-2); font-size: 12px;
        font-weight: 600; cursor: pointer; transition: all .12s;
    }
    .cat-btn.active, .cat-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

    .search-produit {
        width: 100%; margin-bottom: 14px;
    }

    .payment-toggle-btn {
        border: 1px solid var(--border);
        background: #fff;
        color: var(--text-2);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }
    .payment-toggle-btn.active {
        background: #002B5C !important;
        color: #ffffff !important;
        border-color: #002B5C !important;
    }

    .btn-info { background: var(--info); color: #fff; }
    .btn-info:hover { background: #2563eb; }

    @keyframes slideIn {
        from { transform: translateX(-100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
</style>
@endsection

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-cash-register"></i> Nouvelle vente</h1>
        <p>Sélectionnez les articles, ajustez les quantités et finalisez la vente</p>
    </div>
    @php
        $routeHistorique = request()->routeIs('caissier.*') ? route('caissier.ventes.historique') : route('admin.ventes.historique');
        $routeEnregistrer = request()->routeIs('caissier.*') ? route('caissier.ventes.enregistrer') : route('admin.ventes.enregistrer');
    @endphp
    <a href="{{ $routeHistorique }}" class="btn btn-outline">
        <i class="fas fa-history"></i> Historique
    </a>
</div>

<form method="POST" action="{{ $routeEnregistrer }}" id="formVente">
@csrf
<div class="pos-grid">

    {{-- ── COLONNE GAUCHE : Catalogue produits ── --}}
    <div>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-barcode"></i> Catalogue produits</h2>
                <span style="color:var(--text-3); font-size:12px;">{{ $produits->count() }} article(s) disponibles</span>
            </div>
            <div class="card-body">
                {{-- Filtre catégorie --}}
                <div class="categorie-filter">
                    <button type="button" class="cat-btn active" data-cat="all">Tous</button>
                    @foreach($categories as $cat)
                    <button type="button" class="cat-btn" data-cat="{{ $cat }}">{{ $cat }}</button>
                    @endforeach
                </div>

                {{-- Recherche --}}
                <input type="text" id="rechercheInput" class="form-control search-produit" placeholder="🔍 Rechercher un produit…">

                {{-- Grille produits --}}
                <div class="produit-grid" id="grilleProduits">
                    @foreach($produits as $produit)
                    <div class="produit-card {{ $produit->stock_actuel <= 0 ? 'out-of-stock' : '' }}"
                         data-id="{{ $produit->id }}"
                         data-nom="{{ $produit->nom }}"
                         data-prix="{{ $produit->prix_vente }}"
                         data-stock="{{ $produit->stock_actuel }}"
                         data-stock-min="{{ $produit->stock_minimum }}"
                         data-cat="{{ $produit->categorie }}"
                         data-unite="{{ $produit->unite ?? 'Unité' }}"
                         data-tva="{{ $produit->taux_tva }}"
                         onclick="ajouterAuPanier(this)">
                        <div class="produit-cat">{{ $produit->categorie }}</div>
                        <div class="produit-nom">{{ $produit->nom }}</div>
                        <div class="produit-prix">{{ number_format($produit->prix_vente, 0, ',', ' ') }} F</div>
                        <div class="produit-stock" style="{{ $produit->stock_actuel <= 0 ? 'color:var(--danger);font-weight:700;' : '' }}">
                            @if($produit->stock_actuel <= 0)
                                Rupture de stock
                            @else
                                Stock : {{ $produit->stock_actuel }} {{ $produit->unite ?? 'unités' }}
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ── COLONNE DROITE : Panier ── --}}
    <div>
        <div class="card" style="position: sticky; top: calc(var(--topbar-h) + 16px);">
            <div class="card-header">
                <h2><i class="fas fa-shopping-cart"></i> Panier</h2>
                <span id="nbArticles" style="color:var(--text-3); font-size:12px;">0 article(s)</span>
            </div>
            <div class="card-body">

                {{-- Articles du panier --}}
                <div id="panierVide" style="text-align:center; padding:24px 0; color:var(--text-3);">
                    <i class="fas fa-cart-plus" style="font-size:28px; display:block; margin-bottom:8px; opacity:.3;"></i>
                    Cliquez sur un produit pour l'ajouter
                </div>
                <div id="panierItems"></div>

                {{-- Bouton Saisie Libre --}}
                <button type="button" class="btn btn-outline btn-sm" onclick="ouvrirSaisieLibre()" style="width:100%; justify-content:center; margin-top:10px; margin-bottom:15px; border-style:dashed;">
                    <i class="fas fa-plus"></i> Saisie libre / Service
                </button>

                {{-- Totaux --}}
                <div class="total-box">
                    <div class="total-row"><span>Sous-total HT</span><span id="totalHt">0 F</span></div>
                    <div class="total-row" style="align-items:center;">
                        <span>Remise (F)</span>
                        <input type="number" id="remiseInput" name="remise" class="form-control" value="0" min="0" oninput="calculerTotaux()" style="width: 100px; height: 28px; text-align: right; font-weight: 700; padding: 2px 8px; font-size: 13px; margin: 0;">
                    </div>
                    <div class="total-row" style="align-items:center;">
                        <span>TVA</span>
                        <span id="totalTva">0 F</span>
                    </div>
                    <div class="total-row grand"><span>Total TTC</span><span id="totalTtc">0 F</span></div>
                </div>

                {{-- Client & paiement --}}
                <div style="margin-top: 18px;">
                    <div class="form-group">
                        <label class="form-label">Client (optionnel)</label>
                        <select name="client_id" class="form-control">
                            <option value="">— Client de passage —</option>
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->nom }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Mode de paiement style buttons --}}
                    <input type="hidden" name="mode_paiement" id="modePaiementInput" value="Caisse">
                    <input type="hidden" name="etape" id="etapeInput" value="Facture">
                    <div class="form-group">
                        <label class="form-label">Mode de paiement <span style="color:var(--danger)">*</span></label>
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:12px;">
                            <button type="button" class="btn payment-toggle-btn active" data-mode="Caisse" onclick="selectionnerModePaiement(this)" style="justify-content:center;">Caisse</button>
                            <button type="button" class="btn payment-toggle-btn" data-mode="Banque" onclick="selectionnerModePaiement(this)" style="justify-content:center;">Banque</button>
                            <button type="button" class="btn payment-toggle-btn" data-mode="Crédit" onclick="selectionnerModePaiement(this)" style="justify-content:center;">Crédit</button>
                        </div>
                    </div>

                    {{-- Sélection de la banque --}}
                    <div id="selectionBanqueContainer" style="display:none; margin-bottom:16px;">
                        <label class="form-label">Sélectionner la Banque <span style="color:var(--danger)">*</span></label>
                        <div style="display:flex; gap:8px;">
                            <select name="banque_id" id="banqueSelect" class="form-control" style="flex:1;">
                                <option value="">— Choisir un compte banque —</option>
                                @foreach($banques as $b)
                                <option value="{{ $b->id }}">{{ $b->intitule }} ({{ $b->code }} - {{ $b->compte }})</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-primary" onclick="ouvrirModalNouvelleBanque()" style="padding:0 14px;"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>

                    {{-- Montant reçu --}}
                    <div class="form-group">
                        <label class="form-label" id="labelMontantPaye">Montant à encaisser / reçu <span style="color:var(--danger)">*</span></label>
                        <input type="number" name="montant_paye" id="montantPayeInput" class="form-control" placeholder="Saisir le montant reçu / payé" required>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <button type="submit" name="etape" value="Facture" class="btn btn-success" id="btnValider" style="width:100%; justify-content:center;" onclick="document.getElementById('etapeInput').value = 'Facture'" disabled>
                        <i class="fas fa-check-circle"></i> Valider et facturer
                    </button>
                    <button type="submit" name="etape" value="Devis" class="btn btn-outline" id="btnDevis" style="width:100%; justify-content:center; border-color:var(--warning); color:var(--warning);" onclick="document.getElementById('etapeInput').value = 'Devis'" disabled>
                        <i class="fas fa-file-invoice"></i> Enregistrer comme Devis
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<!-- Modal Saisie Libre -->
<div class="modal-overlay" id="modalSaisieLibre">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-pen-to-square"></i> Saisie libre / Service</h3>
            <button type="button" class="modal-close" onclick="fermerSaisieLibre()">&times;</button>
        </div>
        <form onsubmit="ajouterSaisieLibre(event, true)">
            <div class="form-group">
                <label class="form-label">Désignation / Service <span style="color:var(--danger)">*</span></label>
                <input type="text" id="saisieNomInput" class="form-control" placeholder="Ex: Prestation de service, Produit hors stock" required>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Prix unitaire (F) <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="saisiePrixInput" class="form-control" min="0" placeholder="Ex: 5000" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantité <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="saisieQteInput" class="form-control" min="1" value="1" required>
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Unité</label>
                    <input type="text" id="saisieUniteInput" class="form-control" value="Unité" placeholder="Ex: Kg, Heure">
                </div>
                <div class="form-group">
                    <label class="form-label">TVA (%)</label>
                    <select id="saisieTvaInput" class="form-control">
                        <option value="18">18%</option>
                        <option value="0">0%</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                <button type="button" class="btn btn-outline" onclick="fermerSaisieLibre()">Annuler</button>
                <button type="button" class="btn btn-info" onclick="ajouterSaisieLibre(null, false)">Ajouter et continuer</button>
                <button type="submit" class="btn btn-primary">Ajouter au panier</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Banque -->
<div class="modal-overlay" id="modalNouvelleBanque">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-building-columns"></i> Nouveau code journal banque</h3>
            <button type="button" class="modal-close" onclick="fermerModalNouvelleBanque()">&times;</button>
        </div>
        <form id="formNouvelleBanque" onsubmit="soumettreNouvelleBanque(event)">
            <div class="form-group">
                <label class="form-label">Code <span style="color:var(--danger)">*</span></label>
                <input type="text" id="banqueCodeInput" class="form-control" placeholder="Ex: BQE, SGCI" required>
            </div>
            <div class="form-group">
                <label class="form-label">Intitulé <span style="color:var(--danger)">*</span></label>
                <input type="text" id="banqueIntituleInput" class="form-control" placeholder="Ex: Journal Société Générale" required>
            </div>
            <div class="form-group">
                <label class="form-label">Compte comptable <span style="color:var(--danger)">*</span></label>
                <input type="text" id="banqueCompteInput" class="form-control" placeholder="Ex: 521100" required>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                <button type="button" class="btn btn-outline" onclick="fermerModalNouvelleBanque()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Rupture de Stock -->
<div class="modal-overlay" id="modalRuptureStock">
    <div class="modal" style="max-width: 480px; text-align: center;">
        <div style="font-size: 48px; color: var(--warning); margin-bottom: 16px;">
            <i class="fas fa-triangle-exclamation"></i>
        </div>
        <h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Stock local épuisé</h3>
        <p style="color:var(--text-2); font-size:13.5px; line-height:1.6; margin-bottom:20px;">
            Le produit <strong id="ruptureNomProduit" style="color:var(--primary);">—</strong> est en rupture de stock en local (Stock dispo : <span id="ruptureStockDispo">0</span>, Demandé : <span id="ruptureQteDemandee">0</span>).<br>
            Voulez-vous tout de même continuer et autoriser la vente ?
        </p>
        <div style="display:flex; justify-content:center; gap:12px;">
            <button type="button" class="btn btn-outline" onclick="fermerModalRupture()">Annuler</button>
            <button type="button" class="btn btn-primary" id="btnConfirmerRupture">Oui, continuer</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const panier = {};
const stocks = {};

function savePanier() {
    localStorage.setItem('selflow_vente_panier', JSON.stringify(panier));
}

function loadPanier() {
    const stored = localStorage.getItem('selflow_vente_panier');
    if (stored) {
        try {
            const parsed = JSON.parse(stored);
            Object.keys(parsed).forEach(k => {
                panier[k] = parsed[k];
            });
            renderPanier();
        } catch(e) {
            console.error("Erreur chargement panier local:", e);
        }
    }
}

// Initialisation des stocks
@foreach($produits as $p)
stocks[{{ $p->id }}] = {{ $p->stock_actuel }};
@endforeach

document.addEventListener('DOMContentLoaded', () => {
    loadPanier();
});

function formatFcfa(n) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' F';
}

function selectionnerModePaiement(btn) {
    document.querySelectorAll('.payment-toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const mode = btn.dataset.mode;
    document.getElementById('modePaiementInput').value = mode;
    
    const banqueContainer = document.getElementById('selectionBanqueContainer');
    const banqueSelect = document.getElementById('banqueSelect');
    const montantInput = document.getElementById('montantPayeInput');
    const labelMontant = document.getElementById('labelMontantPaye');
    
    if (mode === 'Banque') {
        banqueContainer.style.display = 'block';
        banqueSelect.required = true;
    } else {
        banqueContainer.style.display = 'none';
        banqueSelect.required = false;
        banqueSelect.value = '';
    }
    
    if (mode === 'Crédit') {
        montantInput.required = false;
        montantInput.placeholder = "Laisser vide (Crédit)";
        montantInput.value = "";
        labelMontant.innerHTML = 'Montant à encaisser / reçu';
    } else {
        montantInput.required = true;
        montantInput.placeholder = "Saisir le montant reçu / payé";
        labelMontant.innerHTML = 'Montant à encaisser / reçu <span style="color:var(--danger)">*</span>';
    }
}

function ouvrirSaisieLibre() {
    document.getElementById('modalSaisieLibre').classList.add('open');
}

function fermerSaisieLibre() {
    document.getElementById('modalSaisieLibre').classList.remove('open');
    document.getElementById('saisieNomInput').value = '';
    document.getElementById('saisiePrixInput').value = '';
    document.getElementById('saisieQteInput').value = '1';
    document.getElementById('saisieUniteInput').value = 'Unité';
    document.getElementById('saisieTvaInput').value = '18';
}

function ajouterSaisieLibre(e, fermer = true) {
    if (e) e.preventDefault();
    
    const form = document.querySelector('#modalSaisieLibre form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const nomInput = document.getElementById('saisieNomInput');
    const prixInput = document.getElementById('saisiePrixInput');
    const qteInput = document.getElementById('saisieQteInput');
    const uniteInput = document.getElementById('saisieUniteInput');
    const tvaInput = document.getElementById('saisieTvaInput');
    
    const nom = nomInput.value.trim();
    const prix = parseFloat(prixInput.value);
    const qte = parseInt(qteInput.value);
    const unite = uniteInput.value.trim() || 'Unité';
    const tva = parseFloat(tvaInput.value || 18);
    
    const id = 'v_' + Date.now();
    panier[id] = { nom, prix, qte, stock: 99999, stock_minimum: 0, unite, tva, isVirtual: true };
    
    if (fermer) {
        fermerSaisieLibre();
    } else {
        nomInput.value = '';
        prixInput.value = '';
        qteInput.value = '1';
        uniteInput.value = 'Unité';
        tvaInput.value = '18';
        nomInput.focus();
    }
    savePanier();
    renderPanier();
}

function ajouterAuPanier(card) {
    const id        = parseInt(card.dataset.id);
    const nom       = card.dataset.nom;
    const prix      = parseFloat(card.dataset.prix);
    const stock     = parseInt(card.dataset.stock);
    const stock_min = parseInt(card.dataset.stockMin || 5);
    const unite     = card.dataset.unite || 'Unité';
    const tva       = parseFloat(card.dataset.tva || 18);

    if (panier[id]) {
        const nouvelleQte = panier[id].qte + 1;
        if (nouvelleQte > stock) {
            ouvrirModalRupture(id, nouvelleQte);
        } else {
            panier[id].qte++;
            verifierLimiteMinimale(panier[id]);
            savePanier();
            renderPanier();
        }
    } else {
        if (stock <= 0) {
            ouvrirModalRuptureVirtual(id, nom, prix, stock, stock_min, unite, tva);
        } else {
            panier[id] = { nom, prix, qte: 1, stock, stock_minimum: stock_min, unite: unite, tva, isVirtual: false };
            verifierLimiteMinimale(panier[id]);
            savePanier();
            renderPanier();
        }
    }
}

function changerQte(id, delta) {
    if (!panier[id]) return;
    const item = panier[id];

    if (item.isVirtual) {
        item.qte += delta;
        if (item.qte <= 0) {
            delete panier[id];
        }
        savePanier();
        renderPanier();
        return;
    }

    const nouvelleQte = item.qte + delta;

    if (nouvelleQte <= 0) {
        delete panier[id];
        savePanier();
        renderPanier();
        return;
    }

    if (nouvelleQte > item.stock) {
        ouvrirModalRupture(id, nouvelleQte);
    } else {
        item.qte = nouvelleQte;
        verifierLimiteMinimale(item);
        savePanier();
        renderPanier();
    }
}

function saisirQte(id, val) {
    let q = parseInt(val);
    if (isNaN(q) || q <= 0) q = 1;

    const item = panier[id];
    if (!item) return;

    if (item.isVirtual) {
        item.qte = q;
        savePanier();
        renderPanier();
        return;
    }

    if (q > item.stock) {
        ouvrirModalRupture(id, q);
    } else {
        item.qte = q;
        verifierLimiteMinimale(item);
        savePanier();
        renderPanier();
    }
}

function supprimerItem(id) {
    delete panier[id];
    savePanier();
    renderPanier();
}

// Ouvrir modal rupture standard
function ouvrirModalRupture(id, qte) {
    const item = panier[id];
    document.getElementById('ruptureNomProduit').textContent = item.nom;
    document.getElementById('ruptureQteDemandee').textContent = qte;
    document.getElementById('ruptureStockDispo').textContent = item.stock;
    
    document.getElementById('btnConfirmerRupture').onclick = function() {
        item.qte = qte;
        fermerModalRupture();
        verifierLimiteMinimale(item);
        savePanier();
        renderPanier();
    };
    
    document.getElementById('modalRuptureStock').classList.add('open');
}

// Ouvrir modal rupture virtuel
function ouvrirModalRuptureVirtual(id, nom, prix, stock, stock_min, unite, tva) {
    document.getElementById('ruptureNomProduit').textContent = nom;
    document.getElementById('ruptureQteDemandee').textContent = 1;
    document.getElementById('ruptureStockDispo').textContent = stock;
    
    document.getElementById('btnConfirmerRupture').onclick = function() {
        panier[id] = { nom, prix, qte: 1, stock, stock_minimum: stock_min, unite: unite, tva, isVirtual: false };
        fermerModalRupture();
        savePanier();
        renderPanier();
    };
    
    document.getElementById('modalRuptureStock').classList.add('open');
}

function fermerModalRupture() {
    document.getElementById('modalRuptureStock').classList.remove('open');
}

function verifierLimiteMinimale(item) {
    const stockRestant = item.stock - item.qte;
    if (stockRestant <= item.stock_minimum) {
        afficherAlerteStockMin(item.nom, item.stock_minimum);
    }
}

function afficherAlerteStockMin(nom, limite) {
    let alertContainer = document.getElementById('alertesStocksContainer');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alertesStocksContainer';
        alertContainer.style.position = 'fixed';
        alertContainer.style.bottom = '20px';
        alertContainer.style.left = '20px';
        alertContainer.style.zIndex = '9999';
        alertContainer.style.display = 'flex';
        alertContainer.style.flexDirection = 'column';
        alertContainer.style.gap = '10px';
        document.body.appendChild(alertContainer);
    }
    
    const existing = document.querySelector(`[data-alert-prod="${nom}"]`);
    if (existing) return;

    const toast = document.createElement('div');
    toast.className = 'alert alert-warning';
    toast.setAttribute('data-alert-prod', nom);
    toast.style.margin = '0';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    toast.style.animation = 'slideIn 0.3s ease';
    toast.innerHTML = `<i class="fas fa-triangle-exclamation"></i> <div><strong>${nom}</strong> : Stock minimum atteint (${limite}) !</div>`;
    
    alertContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transition = 'opacity 0.4s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

function renderPanier() {
    const container = document.getElementById('panierItems');
    const vide      = document.getElementById('panierVide');
    const nbArt     = document.getElementById('nbArticles');
    const btnVal    = document.getElementById('btnValider');
    const btnDevis  = document.getElementById('btnDevis');

    const ids = Object.keys(panier);
    if (ids.length === 0) {
        container.innerHTML = '';
        vide.style.display = 'block';
        nbArt.textContent = '0 article(s)';
        btnVal.disabled = true;
        if (btnDevis) btnDevis.disabled = true;
        
        document.querySelectorAll('.article-input').forEach(e => e.remove());
        calculerTotaux();
        return;
    }

    vide.style.display = 'none';
    container.innerHTML = '';
    document.querySelectorAll('.article-input').forEach(e => e.remove());

    ids.forEach((id, idx) => {
        const item = panier[id];
        const sousTotal = item.prix * item.qte;

        const div = document.createElement('div');
        div.className = 'panier-item';
        div.innerHTML = `
            <div style="flex:1;">
                <div class="item-nom">${item.nom}</div>
                <div class="item-prix">${formatFcfa(item.prix)} × ${item.qte} = <strong>${formatFcfa(sousTotal)}</strong></div>
                <div style="margin-top: 5px; display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 11px; color: var(--text-3);">Unité:</span>
                    <input type="text" class="form-control form-control-sm" value="${item.unite || 'Unité'}" onchange="saisirUnite('${id}', this.value)" style="width: 80px; height: 22px; font-size: 11px; padding: 2px 6px; display: inline-block;">
                </div>
            </div>
            <div class="qte-ctrl">
                <button type="button" class="qte-btn" onclick="changerQte('${id}', -1)">−</button>
                <input type="number" class="qte-input" value="${item.qte}" min="1" onchange="saisirQte('${id}', this.value)">
                <button type="button" class="qte-btn" onclick="changerQte('${id}', 1)">+</button>
            </div>
            <button type="button" class="remove-btn" onclick="supprimerItem('${id}')">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(div);

        const form = document.getElementById('formVente');
        ['produit_id', 'quantite', 'libelle_virtuel', 'prix_unitaire', 'unite', 'tva'].forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `articles[${idx}][${field}]`;
            
            if (field === 'produit_id') {
                input.value = item.isVirtual ? '' : id;
            } else if (field === 'quantite') {
                input.value = item.qte;
            } else if (field === 'libelle_virtuel') {
                input.value = item.isVirtual ? item.nom : '';
            } else if (field === 'prix_unitaire') {
                input.value = item.isVirtual ? item.prix : '';
            } else if (field === 'unite') {
                input.value = item.unite || 'Unité';
            } else if (field === 'tva') {
                input.value = item.tva || 0;
            }
            
            input.className = 'article-input';
            form.appendChild(input);
        });
    });

    nbArt.textContent = ids.length + ' article(s)';
    btnVal.disabled = false;
    if (btnDevis) btnDevis.disabled = false;
    calculerTotaux();
}

function saisirUnite(id, val) {
    if (panier[id]) {
        panier[id].unite = val;
        savePanier();
        renderPanier();
    }
}

function calculerTotaux() {
    let totalHt = 0;
    let totalTva = 0;
    
    Object.keys(panier).forEach(id => {
        const item = panier[id];
        totalHt += item.prix * item.qte;
    });
    
    const remise = parseFloat(document.getElementById('remiseInput')?.value || 0);
    const totalHtNet = Math.max(0, totalHt - remise);
    
    const ratio = totalHt > 0 ? totalHtNet / totalHt : 0;
    
    Object.keys(panier).forEach(id => {
        const item = panier[id];
        const itemHtNet = item.prix * item.qte * ratio;
        totalTva += itemHtNet * ((item.tva || 0) / 100);
    });
    
    const totalTtc = totalHtNet + totalTva;
    
    document.getElementById('totalHt').textContent  = formatFcfa(totalHt);
    document.getElementById('totalTva').textContent = formatFcfa(totalTva);
    document.getElementById('totalTtc').textContent = formatFcfa(totalTtc);
    
    const inputMontant = document.getElementById('montantPayeInput');
    if (inputMontant) {
        inputMontant.placeholder = `${Math.round(totalTtc)}`;
    }
}

function ouvrirModalNouvelleBanque() {
    document.getElementById('modalNouvelleBanque').classList.add('open');
}

function fermerModalNouvelleBanque() {
    document.getElementById('modalNouvelleBanque').classList.remove('open');
    document.getElementById('formNouvelleBanque').reset();
}

function soumettreNouvelleBanque(e) {
    e.preventDefault();
    const code = document.getElementById('banqueCodeInput').value;
    const intitule = document.getElementById('banqueIntituleInput').value;
    const compte = document.getElementById('banqueCompteInput').value;
    
    const routeCreation = "{{ request()->routeIs('caissier.*') ? route('caissier.banques.creer') : route('admin.banques.creer') }}";
    
    fetch(routeCreation, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ code, intitule, compte })
    })
    .then(res => res.json())
    .then(data => {
        if (data.succes) {
            const select = document.getElementById('banqueSelect');
            const opt = document.createElement('option');
            opt.value = data.banque.id;
            opt.textContent = `${data.banque.nom} (${data.banque.numero_compte})`;
            opt.selected = true;
            select.appendChild(opt);
            
            fermerModalNouvelleBanque();
        } else {
            alert("Erreur lors de la création du code journal banque.");
        }
    })
    .catch(err => {
        console.error(err);
        alert("Une erreur est survenue.");
    });
}

// Filtre catégorie
document.querySelectorAll('.cat-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        document.querySelectorAll('.produit-card').forEach(card => {
            card.style.display = (cat === 'all' || card.dataset.cat === cat) ? '' : 'none';
        });
    });
});

// Recherche en temps réel
document.getElementById('rechercheInput').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.produit-card').forEach(card => {
        card.style.display = card.dataset.nom.toLowerCase().includes(q) ? '' : 'none';
    });
});

// Validation du montant payé avant soumission pour ne pas vider le panier
document.getElementById('formVente').addEventListener('submit', function(e) {
    const mode = document.getElementById('modePaiementInput').value;
    if (mode !== 'Crédit') {
        const montantInput = document.getElementById('montantPayeInput');
        const montant = parseFloat(montantInput.value);
        if (isNaN(montant) || montant <= 0) {
            e.preventDefault();
            alert("Le montant payé est obligatoire et doit être strictement supérieur à 0 pour ce mode de paiement (Caisse / Banque).");
            montantInput.focus();
            return false;
        }
    }
});
</script>
@endsection
