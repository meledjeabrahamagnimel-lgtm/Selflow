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
                <div class="form-group">
                    <label class="form-label">Mode de paiement <span style="color:var(--danger)">*</span></label>
                    <select name="mode_paiement" class="form-control" required>
                        <option value="Espèces">Espèces</option>
                        <option value="Mobile Money">Mobile Money</option>
                        <option value="Virement">Virement</option>
                        <option value="Chèque">Chèque</option>
                        <option value="Crédit fournisseur">Crédit fournisseur</option>
                    </select>
                </div>

                <div class="total-box" style="border-top: 1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <div class="total-row" style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;color:var(--text-2);">
                        <span>Sous-total HT</span><span id="totHt">0 F</span>
                    </div>
                    <div class="total-row" style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;color:var(--text-2);">
                        <span>TVA (18%)</span><span id="totTva">0 F</span>
                    </div>
                    <div class="total-row" style="display:flex;justify-content:space-between;font-size:17px;font-weight:800;color:var(--text);padding:8px 0 4px;">
                        <span>Total TTC</span><span id="totTtc">0 F</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="btnValider" style="width:100%;justify-content:center;margin-top:12px;" disabled>
                    <i class="fas fa-check-circle"></i> Enregistrer l'achat
                </button>
            </div>
        </div>
    </div>
</div>
</form>

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

function ajouterLigne() {
    const container = document.getElementById('lignesAchat');
    const opts = produits.map(p => `<option value="${p.id}" data-prix="${p.prix}">${p.nom} (${p.ref})</option>`).join('');
    const div = document.createElement('div');
    div.className = 'ligne'; div.dataset.idx = idx;
    div.innerHTML = `
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Produit</label>
            <select name="articles[${idx}][produit_id]" class="form-control produit-sel" onchange="majPrix(this, ${idx})" required>
                <option value="">— Choisir —</option>${opts}
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Quantité</label>
            <input type="number" name="articles[${idx}][quantite]" class="form-control qte-inp" min="1" value="1" onchange="recalculer()" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Prix unitaire</label>
            <input type="number" name="articles[${idx}][prix_unitaire]" class="form-control prix-inp" min="0" step="1" value="0" onchange="recalculer()" required>
        </div>
        <button type="button" class="btn-remove-ligne" onclick="supprimerLigne(${idx})"><i class="fas fa-trash"></i></button>
    `;
    container.appendChild(div);
    idx++;
    recalculer();
}

function majPrix(sel, i) {
    const opt = sel.options[sel.selectedIndex];
    const prix = opt.dataset.prix || 0;
    const ligne = document.querySelector(`[data-idx="${i}"]`);
    ligne.querySelector('.prix-inp').value = prix;
    recalculer();
}

function supprimerLigne(i) {
    document.querySelector(`[data-idx="${i}"]`).remove();
    recalculer();
}

function recalculer() {
    let ht = 0;
    document.querySelectorAll('.ligne').forEach(l => {
        const q  = parseFloat(l.querySelector('.qte-inp').value) || 0;
        const p  = parseFloat(l.querySelector('.prix-inp').value) || 0;
        ht += q * p;
    });
    const tva = ht * 0.18;
    const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' F';
    document.getElementById('totHt').textContent  = fmt(ht);
    document.getElementById('totTva').textContent = fmt(tva);
    document.getElementById('totTtc').textContent = fmt(ht + tva);
    document.getElementById('btnValider').disabled = document.querySelectorAll('.ligne').length === 0;
}

// Ajouter une ligne au chargement
ajouterLigne();
</script>
@endsection
