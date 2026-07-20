@extends('admin::gabarits.application')
@section('titre', 'Factures — Achats')
@section('topbar_titre', $type === 'avoir' ? 'Achats — Avoirs' : 'Achats — Factures & Commandes')

@section('contenu')
<div class="page-header">
    <div>
        @if($type === 'avoir')
            <h1><i class="fas fa-file-circle-minus" style="color:#e17055;"></i> Avoirs Fournisseurs</h1>
            <p>{{ $achats->total() }} avoir(s) fournisseur(s) au total</p>
        @else
            <h1><i class="fas fa-file-invoice-dollar"></i> Cycles d'achat</h1>
            <p>Suivi complet des demandes de prix, bons de commande et factures</p>
        @endif
    </div>
    @if($type === 'avoir')
        <button type="button" class="btn" style="background:#e17055; color:#fff;" onclick="ouvrirModalNouveauAvoir()">
            <i class="fas fa-plus"></i> Créer une facture d'avoir
        </button>
    @else
        <a href="{{ route('admin.achats.nouveau') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouvel achat
        </a>
    @endif
</div>

{{-- Si ce n'est pas un Avoir, on affiche les onglets cliquables du Workflow Achat --}}
@if($type !== 'avoir')
<div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:1.5px solid var(--border); padding-bottom:12px; flex-wrap:wrap;">
    <a href="{{ route('admin.achats.factures', ['etape' => 'Demande de prix']) }}" 
       style="display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; transition:all 0.2s; 
              {{ $etapeActive === 'Demande de prix' ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
        <i class="fas fa-file-invoice" style="font-size:14px; {{ $etapeActive === 'Demande de prix' ? 'color:#fff;' : 'color:#0D1B3E;' }}"></i>
        Demande de prix
        <span style="font-size:11px; padding:2px 8px; border-radius:20px; font-weight:800;
                     {{ $etapeActive === 'Demande de prix' ? 'background:rgba(255,255,255,0.2); color:#fff;' : 'background:var(--bg3); color:var(--primary);' }}">
            {{ $nbDP }}
        </span>
    </a>

    <a href="{{ route('admin.achats.factures', ['etape' => 'Bon de commande']) }}" 
       style="display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; transition:all 0.2s; 
              {{ $etapeActive === 'Bon de commande' ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
        <i class="fas fa-shopping-basket" style="font-size:14px; {{ $etapeActive === 'Bon de commande' ? 'color:#fff;' : 'color:#0D1B3E;' }}"></i>
        Bon de commande
        <span style="font-size:11px; padding:2px 8px; border-radius:20px; font-weight:800;
                     {{ $etapeActive === 'Bon de commande' ? 'background:rgba(255,255,255,0.2); color:#fff;' : 'background:var(--bg3); color:var(--primary);' }}">
            {{ $nbBC }}
        </span>
    </a>

    <a href="{{ route('admin.achats.factures', ['etape' => 'Facture']) }}" 
       style="display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; transition:all 0.2s; 
              {{ $etapeActive === 'Facture' ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
        <i class="fas fa-check-double" style="font-size:14px; {{ $etapeActive === 'Facture' ? 'color:#fff;' : 'color:#0D1B3E;' }}"></i>
        Facture
        <span style="font-size:11px; padding:2px 8px; border-radius:20px; font-weight:800;
                     {{ $etapeActive === 'Facture' ? 'background:rgba(255,255,255,0.2); color:#fff;' : 'background:var(--bg3); color:var(--primary);' }}">
            {{ $nbFacture }}
        </span>
    </a>
</div>
@endif

<div class="card">
    <div class="table-wrap">
        @if($achats->isEmpty())
        <div style="padding:48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-file" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucun élément disponible pour cette étape.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    {{-- Colonne dynamique en fonction de l'étape active --}}
                    <th>
                        @if($type === 'avoir')
                            N° Avoir
                        @elseif($etapeActive === 'Demande de prix')
                            N° Demande
                        @elseif($etapeActive === 'Bon de commande')
                            N° Bon
                        @else
                            N° Facture
                        @endif
                    </th>
                    <th>Date</th>
                    <th>Fournisseur</th>
                    <th>Point de vente</th> {{-- Tâche 1 : Ajout de la colonne Point de vente --}}
                    <th>Articles</th>
                    <th>HT</th>
                    <th>TVA</th>
                    <th>TTC</th>
                    <th>Mode</th>
                    <th>Étape</th>
                    <th style="text-align: center;">Normalisé (DGI)</th> {{-- Tâche 11 : colonne Normalisé --}}
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($achats as $achat)
                <tr>
                    <td style="font-weight:700; color:var(--info);">{{ $achat->numero_facture }}</td>
                    <td>{{ \Carbon\Carbon::parse($achat->date_achat)->format('d/m/Y') }}</td>
                    <td style="font-weight:600;">{{ $achat->fournisseur->nom }}</td>
                    <td style="font-weight:500; color:var(--text-2);"><i class="fas fa-store" style="font-size:11px; margin-right:4px;"></i>{{ $achat->pointDeVente->nom }}</td>
                    <td style="color:var(--text-2);">{{ $achat->details->count() }}</td>
                    <td>{{ number_format($achat->montant_ht, 0, ',', ' ') }} F</td>
                    <td>{{ number_format($achat->montant_tva, 0, ',', ' ') }} F</td>
                    <td style="font-weight:700; color:var(--danger);">{{ number_format($achat->montant_ttc, 0, ',', ' ') }} F</td>
                    <td>{{ $achat->mode_paiement }}</td>
                    <td>
                        @if($achat->statut === 'En attente B2B')
                            <span class="badge" style="background:#fff7ed; color:#ea580c; padding:4px 10px; border-radius:20px; font-weight:700; border:1px solid rgba(234,88,12,0.2);">Attente B2B</span>
                        @elseif($achat->etape === 'Demande de prix')
                            <span class="badge" style="background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:20px; font-weight:700;">Demande de prix</span>
                        @elseif($achat->etape === 'Bon de commande')
                            <span class="badge" style="background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:20px; font-weight:700;">Bon de commande</span>
                        @else
                            <span class="badge" style="background:#e6fdf5; color:#059669; padding:4px 10px; border-radius:20px; font-weight:700;">Facture</span>
                        @endif
                    </td>
                    <td style="text-align: center;">
                        @if($achat->normalise)
                            <span style="color:#059669; font-weight:800; font-size:13px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fas fa-check-circle"></i> Oui
                            </span>
                        @else
                            <span style="color:var(--text-3); font-size:12px;">Non</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <a href="{{ route('admin.achats.imprimer', $achat) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> Voir
                            </a>

                            {{-- Normalisation manuelle (BAPA ou Facture) (Tâche 11) --}}
                            @if(!$achat->normalise && $achat->etape === 'Facture')
                                <form method="POST" action="{{ route('admin.achats.normaliser', $achat) }}" style="display:inline; margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" style="font-weight:700; font-size:11px; padding:4px 8px;" title="Normaliser manuellement auprès de la DGI">
                                        <i class="fas fa-share-nodes"></i> Normaliser
                                    </button>
                                </form>
                            @endif

                            @if(empty($achat->fournisseur->ncc) && $achat->etape === 'Facture')
                                <a href="{{ route('admin.achats.bapa', $achat) }}" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:var(--danger); font-size:11px; padding:4px 8px;" title="Générer le Bordereau d'Achat BAPA">
                                    <i class="fas fa-file-invoice"></i> BAPA
                                </a>
                            @endif

                            @if($achat->statut === 'En attente B2B')
                                <form method="POST" action="{{ route('admin.b2b.achat.accepter', $achat) }}" style="display:inline; margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" style="font-weight:700; font-size:11px; padding:4px 8px;" title="Accepter la livraison et comptabiliser l'achat">
                                        <i class="fas fa-check-double"></i> Accepter B2B
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding: 0 16px;">{{ $achats->appends(request()->query())->links() }}</div>
        @endif
    </div>
</div>
@if($type === 'avoir')
<div class="modal-overlay" id="modalNouveauAvoir" style="display:none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div style="background: #fff; border-radius: 16px; max-width: 800px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: #f8fafc;">
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-file-circle-minus" style="color:#e17055;"></i> Nouvelle Facture d'Avoir Fournisseur
            </h3>
            <button type="button" onclick="fermerModalNouveauAvoir()" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.achats.avoir.creer_nouveau') }}" style="margin:0; display:flex; flex-direction:column; overflow:hidden;">
            @csrf
            <div style="padding: 24px; overflow-y: auto; flex-grow: 1; max-height: 60vh;">
                <!-- Choix de la facture d'origine -->
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 6px; color: #334155;">Choisir la facture de doit d'origine *</label>
                    <select id="selectFactureAvoir" onchange="if(this.value) { selectionnerFacturePourAvoir(this.value); } else { masquerDetailsFactureAvoir(); }" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #0f172a; background: #fff;">
                        <option value="">-- Sélectionner une facture --</option>
                        @foreach($facturesDispo as $f)
                            <option value="{{ $f->id }}">{{ $f->numero_facture }} - {{ $f->fournisseur?->nom ?? 'Fournisseur inconnu' }} ({{ number_format($f->montant_ttc, 0, ',', ' ') }} F)</option>
                        @endforeach
                    </select>
                </div>

                <div id="factureDetailsAvoir" style="display: none;">
                    <input type="hidden" name="parent_id" id="avoir_parent_id">
                    
                    <div style="background: #f1f5f9; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid #e17055;">
                        <div>
                            <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Facture Sélectionnée</div>
                            <div id="avoir_facture_ref" style="font-size: 16px; font-weight: 800; color: #0f172a;">FAC-0000</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Fournisseur d'origine</div>
                            <div id="avoir_fournisseur_nom" style="font-size: 15px; font-weight: 700; color: #0f172a;">—</div>
                        </div>
                    </div>

                    <!-- Raison / Motif -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 6px; color: #334155;">Motif / Raison de l'avoir *</label>
                        <input type="text" name="raison" class="form-control" required placeholder="Ex: Retour de marchandise abîmée, erreur de tarif..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                    </div>

                    <!-- Articles -->
                    <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 10px; color: #334155;">Sélectionner les articles et ajuster les quantités à créditer</label>
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom: 1.5px solid #cbd5e1; text-align: left;">
                                <th style="padding: 10px;">Désignation</th>
                                <th style="padding: 10px; text-align: center; width: 100px;">Qté Initiale</th>
                                <th style="padding: 10px; text-align: center; width: 120px;">Qté Avoir</th>
                                <th style="padding: 10px; text-align: right; width: 120px;">Prix Unit.</th>
                                <th style="padding: 10px; width: 180px;">Action sur Stock</th>
                            </tr>
                        </thead>
                        <tbody id="avoirItemsTableBody">
                            <!-- Rempli dynamiquement -->
                        </tbody>
                    </table>

                    <!-- Ajout d'autres articles / Saisies libres -->
                    <div style="margin-top: 24px; border-top: 1.5px solid #cbd5e1; padding-top: 16px;">
                        <h4 style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; text-align: left;">
                            <i class="fas fa-plus-circle" style="color:#e17055;"></i> Ajouter d'autres articles
                        </h4>
                        
                        <div style="display: flex; gap: 8px; margin-bottom: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                            <button type="button" id="tab_catalog_btn" onclick="switchAddMode('catalog')" style="padding: 6px 12px; font-size: 11px; font-weight:700; border-radius: 6px; border: none; cursor: pointer; background: #e17055; color: #fff;">Catalogue Produits</button>
                            <button type="button" id="tab_free_btn" onclick="switchAddMode('free')" style="padding: 6px 12px; font-size: 11px; font-weight:700; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer; background: #fff; color: #475569;">Saisie Libre (Hors Catalogue)</button>
                        </div>

                        <!-- Panel Catalogue -->
                        <div id="panel_catalog" style="display: block; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; align-items: end;">
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Sélectionner le produit *</label>
                                    <select id="catalog_product_id" onchange="onCatalogProductChange()" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background:#fff; font-size: 12px;">
                                        <option value="">-- Choisir un produit --</option>
                                    </select>
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Quantité *</label>
                                    <input type="number" id="catalog_qty" value="1" min="1" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-align: center;">
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Prix U. Avoir *</label>
                                    <input type="number" id="catalog_price" value="0" min="0" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-align: right;">
                                </div>
                                <div>
                                    <button type="button" onclick="ajouterArticleDuCatalogue()" style="width: 100%; padding: 8px 12px; background: #e17055; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; height: 35px;">
                                        <i class="fas fa-plus"></i> Ajouter
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Saisie Libre -->
                        <div id="panel_free" style="display: none; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 10px; align-items: end;">
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Description / Libellé *</label>
                                    <input type="text" id="free_label" placeholder="Ex: Remise ou correction" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px;">
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Quantité *</label>
                                    <input type="number" id="free_qty" value="1" min="1" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-align: center;">
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Prix U. Avoir *</label>
                                    <input type="number" id="free_price" value="0" min="0" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-align: right;">
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Taux TVA</label>
                                    <select id="free_tva_rate" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background:#fff; font-size: 12px;">
                                        <option value="18">TVA 18%</option>
                                        <option value="0">TVA 0%</option>
                                    </select>
                                </div>
                                <div>
                                    <button type="button" onclick="ajouterSaisieLibre()" style="width: 100%; padding: 8px 12px; background: #e17055; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; height: 35px;">
                                        <i class="fas fa-plus"></i> Ajouter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc;">
                <button type="button" class="btn btn-outline" onclick="fermerModalNouveauAvoir()">Annuler</button>
                <button type="submit" id="btnValiderAvoir" class="btn" style="background:#e17055; color:#fff;" disabled>Créer la Facture d'Avoir</button>
            </div>
        </form>
    </div>
</div>

<script>
let searchTimeout = null;
let catalogProducts = {};
let customItemCounter = 0;

function switchAddMode(mode) {
    const tabCatalog = document.getElementById('tab_catalog_btn');
    const tabFree = document.getElementById('tab_free_btn');
    const panelCatalog = document.getElementById('panel_catalog');
    const panelFree = document.getElementById('panel_free');
    
    if (mode === 'catalog') {
        tabCatalog.style.background = '#e17055';
        tabCatalog.style.color = '#fff';
        tabCatalog.style.border = 'none';
        
        tabFree.style.background = '#fff';
        tabFree.style.color = '#475569';
        tabFree.style.border = '1px solid #cbd5e1';
        
        panelCatalog.style.display = 'block';
        panelFree.style.display = 'none';
    } else {
        tabFree.style.background = '#e17055';
        tabFree.style.color = '#fff';
        tabFree.style.border = 'none';
        
        tabCatalog.style.background = '#fff';
        tabCatalog.style.color = '#475569';
        tabCatalog.style.border = '1px solid #cbd5e1';
        
        panelCatalog.style.display = 'none';
        panelFree.style.display = 'block';
    }
}

function chargerProduitsParCategorie() {
    const select = document.getElementById('catalog_product_id');
    const url = "{{ route('admin.achats.factures.produits_categories') }}";
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            catalogProducts = data;
            select.innerHTML = '<option value="">-- Choisir un produit --</option>';
            
            for (const [catName, list] of Object.entries(data)) {
                const group = document.createElement('optgroup');
                group.label = catName;
                
                list.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.dataset.price = p.prix_achat;
                    opt.dataset.unit = p.unite;
                    opt.dataset.stockable = p.est_stockable ? 1 : 0;
                    opt.dataset.tva = p.taux_tva;
                    opt.dataset.name = p.nom;
                    opt.textContent = `${p.nom} (${p.prix_achat} F CFA / ${p.unite})`;
                    group.appendChild(opt);
                });
                
                select.appendChild(group);
            }
        });
}

function onCatalogProductChange() {
    const select = document.getElementById('catalog_product_id');
    const selectedOpt = select.options[select.selectedIndex];
    if (!selectedOpt || selectedOpt.value === "") {
        document.getElementById('catalog_price').value = 0;
        return;
    }
    document.getElementById('catalog_price').value = selectedOpt.dataset.price;
}

function ajouterArticleDuCatalogue() {
    const select = document.getElementById('catalog_product_id');
    const selectedOpt = select.options[select.selectedIndex];
    if (!selectedOpt || selectedOpt.value === "") {
        alert("Veuillez sélectionner un produit du catalogue.");
        return;
    }
    
    const productId = selectedOpt.value;
    const price = parseFloat(document.getElementById('catalog_price').value) || 0;
    const qty = parseFloat(document.getElementById('catalog_qty').value) || 1;
    const isStockable = parseInt(selectedOpt.dataset.stockable);
    const tva = selectedOpt.dataset.tva;
    const name = selectedOpt.dataset.name;
    
    customItemCounter++;
    const rowId = `new_catalog_${customItemCounter}`;
    
    const tbody = document.getElementById('avoirItemsTableBody');
    const tr = document.createElement('tr');
    tr.style.borderBottom = '0.5px solid #e2e8f0';
    
    // Designation
    const tdNom = document.createElement('td');
    tdNom.style.padding = '10px';
    tdNom.innerHTML = `
        <strong>${name}</strong> <span style="font-size: 10px; background:#dcfce7; color:#15803d; padding:2px 6px; border-radius:4px; margin-left:4px;">Catalogue</span>
        <input type="hidden" name="items[${rowId}][est_nouveau]" value="1">
        <input type="hidden" name="items[${rowId}][produit_id]" value="${productId}">
        <input type="hidden" name="items[${rowId}][libelle_virtuel]" value="${name}">
        <input type="hidden" name="items[${rowId}][taux_tva]" value="${tva}">
    `;
    tr.appendChild(tdNom);
    
    // Original Qty
    const tdOriginalQty = document.createElement('td');
    tdOriginalQty.style.padding = '10px';
    tdOriginalQty.style.textAlign = 'center';
    tdOriginalQty.style.fontWeight = '600';
    tdOriginalQty.textContent = `—`;
    tr.appendChild(tdOriginalQty);
    
    // Return Qty
    const tdReturnQty = document.createElement('td');
    tdReturnQty.style.padding = '10px';
    tdReturnQty.innerHTML = `<input type="number" name="items[${rowId}][quantite]" class="form-control" value="${qty}" min="0.01" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center;">`;
    tr.appendChild(tdReturnQty);
    
    // Price
    const tdPrice = document.createElement('td');
    tdPrice.style.padding = '10px';
    tdPrice.style.textAlign = 'right';
    tdPrice.innerHTML = `<input type="number" name="items[${rowId}][prix_unitaire]" class="form-control" value="${price}" min="0" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: right;">`;
    tr.appendChild(tdPrice);
    
    // Stock Action
    const tdStockAction = document.createElement('td');
    tdStockAction.style.padding = '10px';
    if (isStockable) {
        tdStockAction.innerHTML = `
            <select name="items[${rowId}][stock_action]" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                <option value="reinject">Retour physique (Déduire du stock)</option>
                <option value="none">Aucun impact de stock</option>
            </select>
        `;
    } else {
        tdStockAction.innerHTML = `<span style="color:#64748b; font-style:italic;">Non stockable</span><input type="hidden" name="items[${rowId}][stock_action]" value="none">`;
    }
    tr.appendChild(tdStockAction);
    
    tbody.appendChild(tr);
    
    // Reset selections
    select.value = "";
    document.getElementById('catalog_price').value = 0;
    document.getElementById('catalog_qty').value = 1;
}

function ajouterSaisieLibre() {
    const labelInput = document.getElementById('free_label');
    const label = labelInput.value.trim();
    if (label === "") {
        alert("Veuillez saisir un libellé pour la ligne libre.");
        return;
    }
    
    const price = parseFloat(document.getElementById('free_price').value) || 0;
    const qty = parseFloat(document.getElementById('free_qty').value) || 1;
    const tva = document.getElementById('free_tva_rate').value;
    
    customItemCounter++;
    const rowId = `new_free_${customItemCounter}`;
    
    const tbody = document.getElementById('avoirItemsTableBody');
    const tr = document.createElement('tr');
    tr.style.borderBottom = '0.5px solid #e2e8f0';
    
    // Designation
    const tdNom = document.createElement('td');
    tdNom.style.padding = '10px';
    tdNom.innerHTML = `
        <strong>${label}</strong> <span style="font-size: 10px; background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; margin-left:4px;">Saisie libre</span>
        <input type="hidden" name="items[${rowId}][est_nouveau]" value="1">
        <input type="hidden" name="items[${rowId}][libelle_virtuel]" value="${label}">
        <input type="hidden" name="items[${rowId}][taux_tva]" value="${tva}">
    `;
    tr.appendChild(tdNom);
    
    // Original Qty
    const tdOriginalQty = document.createElement('td');
    tdOriginalQty.style.padding = '10px';
    tdOriginalQty.style.textAlign = 'center';
    tdOriginalQty.style.fontWeight = '600';
    tdOriginalQty.textContent = `—`;
    tr.appendChild(tdOriginalQty);
    
    // Return Qty
    const tdReturnQty = document.createElement('td');
    tdReturnQty.style.padding = '10px';
    tdReturnQty.innerHTML = `<input type="number" name="items[${rowId}][quantite]" class="form-control" value="${qty}" min="0.01" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center;">`;
    tr.appendChild(tdReturnQty);
    
    // Price
    const tdPrice = document.createElement('td');
    tdPrice.style.padding = '10px';
    tdPrice.style.textAlign = 'right';
    tdPrice.innerHTML = `<input type="number" name="items[${rowId}][prix_unitaire]" class="form-control" value="${price}" min="0" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: right;">`;
    tr.appendChild(tdPrice);
    
    // Stock Action
    const tdStockAction = document.createElement('td');
    tdStockAction.style.padding = '10px';
    tdStockAction.innerHTML = `<span style="color:#64748b; font-style:italic;">Non stockable</span><input type="hidden" name="items[${rowId}][stock_action]" value="none">`;
    tr.appendChild(tdStockAction);
    
    tbody.appendChild(tr);
    
    // Reset selections
    labelInput.value = "";
    document.getElementById('free_price').value = 0;
    document.getElementById('free_qty').value = 1;
}

function ouvrirModalNouveauAvoir() {
    document.getElementById('modalNouveauAvoir').style.display = 'flex';
    chargerProduitsParCategorie();
}

function fermerModalNouveauAvoir() {
    document.getElementById('modalNouveauAvoir').style.display = 'none';
    const selectEl = document.getElementById('selectFactureAvoir');
    if (selectEl) selectEl.value = '';
    document.getElementById('factureDetailsAvoir').style.display = 'none';
    document.getElementById('btnValiderAvoir').disabled = true;
}

function masquerDetailsFactureAvoir() {
    document.getElementById('factureDetailsAvoir').style.display = 'none';
    document.getElementById('btnValiderAvoir').disabled = true;
}

function chercherFactures(query) {
    clearTimeout(searchTimeout);
    if (query.length < 2) {
        document.getElementById('autocompleteResults').style.display = 'none';
        return;
    }

    searchTimeout = setTimeout(() => {
        const url = "{{ route('admin.achats.factures.rechercher') }}?q=" + encodeURIComponent(query);
        fetch(url)
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('autocompleteResults');
                container.innerHTML = '';
                if (data.length === 0) {
                    container.innerHTML = '<div style="padding: 10px; color: #64748b; font-style: italic;">Aucune facture trouvée</div>';
                } else {
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.style.padding = '10px 14px';
                        div.style.cursor = 'pointer';
                        div.style.borderBottom = '0.5px solid #f1f5f9';
                        div.style.fontSize = '13px';
                        div.style.fontWeight = '600';
                        div.style.color = '#0f172a';
                        div.innerHTML = item.text;
                        div.onclick = () => selectionnerFacturePourAvoir(item.id);
                        div.onmouseover = () => div.style.background = '#f1f5f9';
                        div.onmouseout = () => div.style.background = '#fff';
                        container.appendChild(div);
                    });
                }
                container.style.display = 'block';
            });
    }, 300);
}

function selectionnerFacturePourAvoir(id) {
    const baseUrl = "{{ route('admin.achats.factures.details', ':id') }}";
    const url = baseUrl.replace(':id', id);

    fetch(url)
        .then(res => res.json())
        .then(data => {
            document.getElementById('avoir_parent_id').value = data.id;
            document.getElementById('avoir_facture_ref').textContent = data.numero_facture;
            document.getElementById('avoir_fournisseur_nom').textContent = data.fournisseur_nom;
            
            const tbody = document.getElementById('avoirItemsTableBody');
            tbody.innerHTML = '';
            
            data.details.forEach(item => {
                const tr = document.createElement('tr');
                tr.style.borderBottom = '0.5px solid #e2e8f0';
                
                // Designation
                const tdNom = document.createElement('td');
                tdNom.style.padding = '10px';
                tdNom.innerHTML = `<strong>${item.libelle}</strong><input type="hidden" name="items[${item.id}][id]" value="${item.id}">`;
                tr.appendChild(tdNom);
                
                // Original Qty
                const tdOriginalQty = document.createElement('td');
                tdOriginalQty.style.padding = '10px';
                tdOriginalQty.style.textAlign = 'center';
                tdOriginalQty.style.fontWeight = '600';
                tdOriginalQty.textContent = `${item.quantite} ${item.unite}`;
                tr.appendChild(tdOriginalQty);
                
                // Return Qty
                const tdReturnQty = document.createElement('td');
                tdReturnQty.style.padding = '10px';
                tdReturnQty.innerHTML = `<input type="number" name="items[${item.id}][quantite]" class="form-control" value="${item.quantite}" min="0" max="${item.quantite}" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center;">`;
                tr.appendChild(tdReturnQty);
                
                // Price
                const tdPrice = document.createElement('td');
                tdPrice.style.padding = '10px';
                tdPrice.style.textAlign = 'right';
                tdPrice.innerHTML = `<input type="number" name="items[${item.id}][prix_unitaire]" class="form-control" value="${item.prix_unitaire}" min="0" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: right;">`;
                tr.appendChild(tdPrice);
                
                // Stock Action
                const tdStockAction = document.createElement('td');
                tdStockAction.style.padding = '10px';
                if (item.est_stockable) {
                    tdStockAction.innerHTML = `
                        <select name="items[${item.id}][stock_action]" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                            <option value="reinject">Sortie de stock (Retour fournisseur)</option>
                            <option value="none">Aucun retour physique</option>
                        </select>
                    `;
                } else {
                    tdStockAction.innerHTML = `<span style="color:#64748b; font-style:italic;">Non stockable</span><input type="hidden" name="items[${item.id}][stock_action]" value="none">`;
                }
                tr.appendChild(tdStockAction);
                
                tbody.appendChild(tr);
            });
            
            document.getElementById('factureDetailsAvoir').style.display = 'block';
            document.getElementById('btnValiderAvoir').disabled = false;
        });
}
</script>
@endif
@endsection
