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
    .produit-card.out-of-stock { opacity: .4; cursor: not-allowed; }
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
    .qte-ctrl { display: flex; align-items: center; gap: 6px; }
    .qte-btn {
        width: 26px; height: 26px; border-radius: 6px;
        border: none; cursor: pointer; font-size: 14px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        background: var(--border); color: var(--text); transition: background .12s;
    }
    .qte-btn:hover { background: var(--primary); }
    .qte-val { min-width: 28px; text-align: center; font-weight: 700; }
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
</style>
@endsection

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-cash-register"></i> Nouvelle vente</h1>
        <p>Sélectionnez les articles, ajustez les quantités et finalisez la vente</p>
    </div>
    <a href="{{ route('admin.ventes.historique') }}" class="btn btn-outline">
        <i class="fas fa-history"></i> Historique
    </a>
</div>

@if(!$pointDeVenteId)
<div class="alert alert-warning">
    <i class="fas fa-triangle-exclamation"></i>
    Aucun point de vente actif ! Veuillez en sélectionner un depuis le tableau de bord avant d'enregistrer une vente.
</div>
@endif

<form method="POST" action="{{ route('admin.ventes.enregistrer') }}" id="formVente">
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
                         data-cat="{{ $produit->categorie }}"
                         onclick="ajouterAuPanier(this)">
                        <div class="produit-cat">{{ $produit->categorie }}</div>
                        <div class="produit-nom">{{ $produit->nom }}</div>
                        <div class="produit-prix">{{ number_format($produit->prix_vente, 0, ',', ' ') }} F</div>
                        <div class="produit-stock">Stock : {{ $produit->stock_actuel }} {{ $produit->unite }}</div>
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
                <div id="panierItems">
                    <div id="panierVide" style="text-align:center; padding:24px 0; color:var(--text-3);">
                        <i class="fas fa-cart-plus" style="font-size:28px; display:block; margin-bottom:8px; opacity:.3;"></i>
                        Cliquez sur un produit pour l'ajouter
                    </div>
                </div>

                {{-- Totaux --}}
                <div class="total-box">
                    <div class="total-row"><span>Sous-total HT</span><span id="totalHt">0 F</span></div>
                    <div class="total-row"><span>TVA (18%)</span><span id="totalTva">0 F</span></div>
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
                    <div class="form-group">
                        <label class="form-label">Mode de paiement <span style="color:var(--danger)">*</span></label>
                        <select name="mode_paiement" class="form-control" required>
                            <option value="Espèces">Espèces</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Carte bancaire">Carte bancaire</option>
                            <option value="Chèque">Chèque</option>
                            <option value="Virement">Virement</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-success" id="btnValider" style="width:100%; justify-content:center; margin-top:4px;" disabled>
                    <i class="fas fa-check-circle"></i> Valider et générer la facture
                </button>
            </div>
        </div>
    </div>
</div>
</form>
@endsection

@section('scripts')
<script>
const panier = {};
const stocks = {};

// Initialisation des stocks
@foreach($produits as $p)
stocks[{{ $p->id }}] = {{ $p->stock_actuel }};
@endforeach

function formatFcfa(n) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' F';
}

function ajouterAuPanier(card) {
    const id    = parseInt(card.dataset.id);
    const nom   = card.dataset.nom;
    const prix  = parseFloat(card.dataset.prix);
    const stock = parseInt(card.dataset.stock);

    if (stock <= 0) return;

    if (panier[id]) {
        if (panier[id].qte >= stock) {
            alert('Stock insuffisant pour ' + nom);
            return;
        }
        panier[id].qte++;
    } else {
        panier[id] = { nom, prix, qte: 1, stock };
    }
    renderPanier();
}

function changerQte(id, delta) {
    if (!panier[id]) return;
    panier[id].qte += delta;
    if (panier[id].qte <= 0) {
        delete panier[id];
    } else if (panier[id].qte > panier[id].stock) {
        panier[id].qte = panier[id].stock;
        alert('Stock maximum atteint');
    }
    renderPanier();
}

function supprimerItem(id) {
    delete panier[id];
    renderPanier();
}

function renderPanier() {
    const container = document.getElementById('panierItems');
    const vide      = document.getElementById('panierVide');
    const nbArt     = document.getElementById('nbArticles');
    const btnVal    = document.getElementById('btnValider');

    const ids = Object.keys(panier);
    if (ids.length === 0) {
        container.innerHTML = '';
        container.appendChild(vide);
        vide.style.display = 'block';
        nbArt.textContent = '0 article(s)';
        btnVal.disabled = true;
        updateTotaux(0, 0);
        // Supprimer les inputs cachés
        document.querySelectorAll('.article-input').forEach(e => e.remove());
        return;
    }

    vide.style.display = 'none';
    container.innerHTML = '';
    // Supprimer anciens inputs
    document.querySelectorAll('.article-input').forEach(e => e.remove());

    let totalHt = 0;
    ids.forEach((id, idx) => {
        const item = panier[id];
        const sousTotal = item.prix * item.qte;
        totalHt += sousTotal;

        const div = document.createElement('div');
        div.className = 'panier-item';
        div.innerHTML = `
            <div style="flex:1;">
                <div class="item-nom">${item.nom}</div>
                <div class="item-prix">${formatFcfa(item.prix)} × ${item.qte} = <strong>${formatFcfa(sousTotal)}</strong></div>
            </div>
            <div class="qte-ctrl">
                <button type="button" class="qte-btn" onclick="changerQte(${id}, -1)">−</button>
                <span class="qte-val">${item.qte}</span>
                <button type="button" class="qte-btn" onclick="changerQte(${id}, 1)">+</button>
            </div>
            <button type="button" class="remove-btn" onclick="supprimerItem(${id})">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(div);

        // Inputs cachés pour le formulaire
        const form = document.getElementById('formVente');
        ['produit_id', 'quantite'].forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `articles[${idx}][${field}]`;
            input.value = field === 'produit_id' ? id : item.qte;
            input.className = 'article-input';
            form.appendChild(input);
        });
    });

    nbArt.textContent = ids.length + ' article(s)';
    btnVal.disabled = false;
    updateTotaux(totalHt, totalHt * 0.18);
}

function updateTotaux(ht, tva) {
    document.getElementById('totalHt').textContent  = formatFcfa(ht);
    document.getElementById('totalTva').textContent = formatFcfa(tva);
    document.getElementById('totalTtc').textContent = formatFcfa(ht + tva);
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
</script>
@endsection
