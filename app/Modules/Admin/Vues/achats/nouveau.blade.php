@extends('admin::gabarits.application')
@section('titre', 'Nouveau bon d\'achat')
@section('topbar_titre', 'Achats — Nouveau')

@section('styles')
<style>
    .achat-grid { display: grid; grid-template-columns: 1fr 360px; gap: 22px; align-items: start; }
    #lignesAchat .ligne { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end; margin-bottom: 10px; }
    .btn-remove-ligne {
        background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3);
        color: #fca5a5; border-radius: 8px; padding: 10px 14px; cursor: pointer;
        transition: all .12s; font-size: 14px;
    }
    .btn-remove-ligne:hover { background: var(--danger); color: #fff; }
    .sous-total-row { text-align: right; font-size: 13px; color: var(--text-3); margin-top: 4px; }
    
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
</style>
@endsection

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-truck-loading"></i> Nouvel achat fournisseur</h1>
        <p>Enregistrez un bon de commande / réception de marchandises</p>
    </div>
    <a href="{{ route('admin.achats.historique') }}" class="btn btn-outline">
        <i class="fas fa-history"></i> Historique
    </a>
</div>

<form method="POST" action="{{ route('admin.achats.enregistrer') }}" id="formAchat">
@csrf
<div class="achat-grid">

    <div>
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-list-check"></i> Articles achetés</h2></div>
            <div class="card-body">
                <div id="lignesAchat"></div>
                <button type="button" class="btn btn-outline" onclick="ajouterLigne()" style="margin-top:8px;">
                    <i class="fas fa-plus"></i> Ajouter une ligne
                </button>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="position:sticky; top:calc(var(--topbar-h) + 16px);">
            <div class="card-header"><h2><i class="fas fa-file-invoice"></i> Informations</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Fournisseur <span style="color:var(--danger)">*</span></label>
                    <select name="fournisseur_id" class="form-control" required>
                        <option value="">— Choisir un fournisseur —</option>
                        @foreach($fournisseurs as $f)
                        <option value="{{ $f->id }}">{{ $f->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date d'achat <span style="color:var(--danger)">*</span></label>
                    <input type="date" name="date_achat" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>

                {{-- Mode de paiement style buttons --}}
                <input type="hidden" name="mode_paiement" id="modePaiementInput" value="Caisse">
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
                    <div class="form-group" style="margin-bottom: 12px;">
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
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label">Moyen de paiement bancaire <span style="color:var(--danger)">*</span></label>
                        <select name="moyen_bancaire" id="moyenBancaireSelect" class="form-control">
                            <option value="">— Moyen de paiement —</option>
                            <option value="carte">Carte bancaire</option>
                            <option value="virement">Virement</option>
                            <option value="cheque">Chèque</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Référence / Numéro <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="reference_paiement" id="refPaiementInput" class="form-control" placeholder="Numéro de carte, virement ou chèque">
                    </div>
                </div>

                <div class="total-box" style="border-top: 1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <div class="total-row" style="display:flex;justify-content:space-between;font-size:17px;font-weight:800;color:var(--text);padding:8px 0 4px;">
                        <span>Total</span><span id="totTtc">0 F</span>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:8px; margin-top:12px;">
                    <button type="submit" name="etape" value="Facture" class="btn btn-primary" id="btnValider" style="width:100%;justify-content:center;" disabled>
                        <i class="fas fa-check-circle"></i> Valider et facturer
                    </button>
                    <button type="submit" name="etape" value="Demande de prix" class="btn btn-outline" id="btnDemande" style="width:100%;justify-content:center;border-color:var(--warning);color:var(--warning);" disabled>
                        <i class="fas fa-file-invoice"></i> Enregistrer comme Demande de prix
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

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

{{-- Options produits pour JS --}}
<script>
const produits = {!! json_encode($produits->map(function($p) {
    return [
        'id' => $p->id,
        'nom' => $p->nom,
        'prix' => $p->prix_achat,
        'ref' => $p->reference
    ];
})->toArray()) !!};
let idx = 0;

function savePanier() {
    const panierItems = [];
    document.querySelectorAll('.ligne').forEach(l => {
        const chk = l.querySelector('.libre-chk');
        const pSel = l.querySelector('.produit-sel');
        const nameInp = l.querySelector('.name-inp');
        const unitInp = l.querySelector('.unit-inp');
        const qInp = l.querySelector('.qte-inp');
        const prInp = l.querySelector('.prix-inp');
        
        const isLibre = chk && chk.checked;
        if (isLibre || pSel.value) {
            panierItems.push({
                produit_id: isLibre ? '' : pSel.value,
                libelle_virtuel: isLibre ? nameInp.value : '',
                unite: isLibre ? unitInp.value : 'Unité',
                quantite: qInp.value,
                prix_unitaire: prInp.value
            });
        }
    });
    localStorage.setItem('selflow_achat_panier', JSON.stringify(panierItems));
}

function loadPanier() {
    const saved = localStorage.getItem('selflow_achat_panier');
    if (saved) {
        try {
            const items = JSON.parse(saved);
            if (items && items.length > 0) {
                document.getElementById('lignesAchat').innerHTML = '';
                items.forEach(item => {
                    ajouterLigne(item);
                });
                return;
            }
        } catch (e) {
            console.error("Erreur chargement panier d'achat", e);
        }
    }
    ajouterLigne();
}

function ajouterLigne(prefill = null) {
    const container = document.getElementById('lignesAchat');
    const opts = produits.map(p => `<option value="${p.id}" data-prix="${p.prix}">${p.nom} (${p.ref})</option>`).join('');
    const div = document.createElement('div');
    div.className = 'ligne'; div.dataset.idx = idx;
    
    const isLibre = prefill && (prefill.libelle_virtuel || !prefill.produit_id && prefill.produit_id === '');
    
    div.innerHTML = `
        <div class="form-group" style="margin-bottom:0; min-width: 250px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <label class="form-label" style="margin-bottom:0;">Produit</label>
                <label style="font-size:11px; margin-bottom:0; font-weight:normal; cursor:pointer; color:var(--primary);">
                    <input type="checkbox" class="libre-chk" onchange="toggleLibre(${idx})" ${isLibre ? 'checked' : ''}> Saisie libre
                </label>
            </div>
            
            <div class="sel-container" style="${isLibre ? 'display:none;' : 'display:block;'}">
                <select name="articles[${idx}][produit_id]" class="form-control produit-sel" onchange="majPrix(this, ${idx}); savePanier();" ${isLibre ? '' : 'required'}>
                    <option value="">— Choisir —</option>${opts}
                </select>
            </div>
            
            <div class="libre-container" style="${isLibre ? 'display:flex;' : 'display:none;'} gap: 6px;">
                <input type="text" name="articles[${idx}][libelle_virtuel]" class="form-control name-inp" placeholder="Nom du produit" value="${prefill ? (prefill.libelle_virtuel || '') : ''}" ${isLibre ? 'required' : ''} oninput="savePanier();">
                <input type="text" name="articles[${idx}][unite]" class="form-control unit-inp" placeholder="Unité" value="${prefill ? (prefill.unite || 'Unité') : 'Unité'}" oninput="savePanier();" style="width: 80px;">
            </div>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Quantité</label>
            <input type="number" name="articles[${idx}][quantite]" class="form-control qte-inp" min="1" value="${prefill ? prefill.quantite : 1}" oninput="recalculer(); savePanier();" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Prix unitaire</label>
            <input type="number" name="articles[${idx}][prix_unitaire]" class="form-control prix-inp" min="0" step="1" value="${prefill ? prefill.prix_unitaire : 0}" oninput="recalculer(); savePanier();" required>
        </div>
        <button type="button" class="btn-remove-ligne" onclick="supprimerLigne(${idx})"><i class="fas fa-trash"></i></button>
    `;
    container.appendChild(div);
    
    if (prefill) {
        if (prefill.produit_id) {
            div.querySelector('.produit-sel').value = prefill.produit_id;
        }
    }

    idx++;
    recalculer();
}

function toggleLibre(i) {
    const row = document.querySelector(`[data-idx="${i}"]`);
    const chk = row.querySelector('.libre-chk');
    const selContainer = row.querySelector('.sel-container');
    const libreContainer = row.querySelector('.libre-container');
    const sel = row.querySelector('.produit-sel');
    const nameInp = row.querySelector('.name-inp');
    
    if (chk.checked) {
        selContainer.style.display = 'none';
        sel.required = false;
        sel.value = '';
        
        libreContainer.style.display = 'flex';
        nameInp.required = true;
    } else {
        selContainer.style.display = 'block';
        sel.required = true;
        
        libreContainer.style.display = 'none';
        nameInp.required = false;
        nameInp.value = '';
    }
    recalculer();
    savePanier();
}

function majPrix(sel, i) {
    const opt = sel.options[sel.selectedIndex];
    const prix = opt.dataset.prix || 0;
    const ligne = document.querySelector(`[data-idx="${i}"]`);
    ligne.querySelector('.prix-inp').value = prix;
    recalculer();
}

function supprimerLigne(i) {
    const ligne = document.querySelector(`[data-idx="${i}"]`);
    if (ligne) {
        ligne.remove();
    }
    recalculer();
    savePanier();
}

function recalculer() {
    let ht = 0;
    document.querySelectorAll('.ligne').forEach(l => {
        const q  = parseFloat(l.querySelector('.qte-inp').value) || 0;
        const p  = parseFloat(l.querySelector('.prix-inp').value) || 0;
        ht += q * p;
    });
    const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' F';
    document.getElementById('totTtc').textContent = fmt(ht);
    const noItems = document.querySelectorAll('.ligne').length === 0;
    document.getElementById('btnValider').disabled = noItems;
    document.getElementById('btnDemande').disabled = noItems;
}

function selectionnerModePaiement(btn) {
    document.querySelectorAll('.payment-toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const mode = btn.dataset.mode;
    document.getElementById('modePaiementInput').value = mode;
    
    const banqueContainer = document.getElementById('selectionBanqueContainer');
    const banqueSelect = document.getElementById('banqueSelect');
    const moyenBancaireSelect = document.getElementById('moyenBancaireSelect');
    const refPaiementInput = document.getElementById('refPaiementInput');
    
    if (mode === 'Banque') {
        banqueContainer.style.display = 'block';
        banqueSelect.required = true;
        moyenBancaireSelect.required = true;
        refPaiementInput.required = true;
    } else {
        banqueContainer.style.display = 'none';
        banqueSelect.required = false;
        banqueSelect.value = '';
        moyenBancaireSelect.required = false;
        moyenBancaireSelect.value = '';
        refPaiementInput.required = false;
        refPaiementInput.value = '';
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
    
    const routeCreation = "{{ route('admin.banques.creer') }}";
    
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

document.getElementById('formAchat').addEventListener('submit', function() {
    setTimeout(() => {
        localStorage.removeItem('selflow_achat_panier');
    }, 100);
});

// Charger le panier au chargement
loadPanier();
</script>
@endsection
