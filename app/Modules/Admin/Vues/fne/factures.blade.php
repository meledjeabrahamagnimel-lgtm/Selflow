@extends('admin::gabarits.application')
@section('titre', 'Factures & Reçus')
@section('topbar_titre', 'Factures & Reçus émis / reçus')

@section('styles')
<style>
    .fne-filtres { display:flex; gap:12px; align-items:end; flex-wrap:wrap; background:#fff; border:1px solid var(--border); border-radius:14px; padding:16px 20px; margin-bottom:18px; }
    .fne-filtres .form-group { margin-bottom:0; }
    .fne-filtres label { font-size:11px; text-transform:uppercase; font-weight:700; color:var(--text-3); display:block; margin-bottom:4px; }

    .flux-toggle { display:flex; background:var(--bg3); border-radius:10px; padding:4px; gap:4px; margin-bottom:14px; width:fit-content; }
    .flux-toggle button { border:none; background:transparent; padding:8px 20px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; color:var(--text-2); }
    .flux-toggle button.active { background:#fff; color:var(--primary); box-shadow:0 1px 3px rgba(0,0,0,.08); }

    .categorie-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
    .categorie-tabs button { border:1px solid var(--border); background:#fff; padding:7px 16px; border-radius:20px; font-size:12px; font-weight:600; cursor:pointer; color:var(--text-2); }
    .categorie-tabs button.active { background:var(--primary); color:#fff; border-color:var(--primary); }

    .doc-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .doc-table th { text-align:left; padding:10px 14px; font-size:11px; text-transform:uppercase; color:var(--text-3); background:#f8fafc; border-bottom:1px solid var(--border); white-space:nowrap; }
    .doc-table td { padding:11px 14px; border-bottom:1px solid var(--border); font-size:13px; white-space:nowrap; }
    .doc-table tr:last-child td { border-bottom:none; }
    .doc-table tr:hover td { background:#fafbfc; }

    .statut-fne { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .statut-oui { background:#ecfdf5; color:#065f46; }
    .statut-non { background:#fef2f2; color:#991b1b; }

    .pagination-bar { display:flex; justify-content:space-between; align-items:center; padding:14px 4px; font-size:13px; color:var(--text-3); }
    .pagination-bar button { border:1px solid var(--border); background:#fff; padding:6px 14px; border-radius:8px; cursor:pointer; font-weight:600; }
    .pagination-bar button:disabled { opacity:.4; cursor:not-allowed; }
</style>
@endsection

@section('contenu')

<div class="fne-filtres">
    <div class="form-group">
        <label>Période</label>
        <select id="f-periode-type" class="form-control" onchange="page=1;rafraichirFactures();">
            <option value="jour">Jour</option>
            <option value="semaine">Semaine</option>
            <option value="mois" selected>Mois</option>
            <option value="annee">Année</option>
        </select>
    </div>
    <div class="form-group">
        <label>Date de référence</label>
        <input type="date" id="f-date" class="form-control" value="{{ date('Y-m-d') }}" onchange="page=1;rafraichirFactures();">
    </div>
    <div class="form-group">
        <label>Point de vente</label>
        <select id="f-pdv" class="form-control" onchange="page=1;rafraichirFactures();">
            <option value="tous">Tous les sites</option>
            @foreach($pointsDeVente as $pdv)
                <option value="{{ $pdv->id }}">{{ $pdv->nom }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group" style="flex:1; min-width:220px;">
        <label>Recherche (n° pièce, n° FNE, tiers)</label>
        <input type="text" id="f-recherche" class="form-control" placeholder="Rechercher..." oninput="page=1;debouncedRafraichir();">
    </div>
    <button class="btn btn-primary" onclick="page=1;rafraichirFactures();"><i class="fas fa-rotate"></i> Actualiser</button>
</div>

<div class="flux-toggle">
    <button id="btn-flux-ventes" class="active" onclick="changerFlux('ventes')"><i class="fas fa-arrow-down" style="color:#10b981;"></i> Ventes</button>
    <button id="btn-flux-achats" onclick="changerFlux('achats')"><i class="fas fa-arrow-up" style="color:#ef4444;"></i> Achats</button>
</div>

<div class="categorie-tabs" id="onglets-ventes">
    <button class="active" data-cat="emis" onclick="changerCategorie('emis')">Factures Émises</button>
    <button data-cat="recu_recu" onclick="changerCategorie('recu_recu')">Reçus (comptant)</button>
    <button data-cat="avoir_client" onclick="changerCategorie('avoir_client')">Avoirs Clients</button>
</div>
<div class="categorie-tabs" id="onglets-achats" style="display:none;">
    <button class="active" data-cat="recu" onclick="changerCategorie('recu')">Factures Reçues</button>
    <button data-cat="emis" onclick="changerCategorie('emis')">BAPA (émis par nous)</button>
    <button data-cat="avoir_fournisseur" onclick="changerCategorie('avoir_fournisseur')">Avoirs Fournisseurs</button>
</div>

<table class="doc-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>N° Pièce</th>
            <th>N° FNE (DGI)</th>
            <th id="th-tiers">Client</th>
            <th style="text-align:right;">HT</th>
            <th style="text-align:right;">TVA</th>
            <th style="text-align:right;">TTC</th>
            <th>Statut DGI</th>
            <th>Site</th>
            <th style="text-align:center;">Fichier</th>
        </tr>
    </thead>
    <tbody id="corps-table">
        <tr><td colspan="11" style="text-align:center; padding:30px; color:var(--text-3);">Chargement...</td></tr>
    </tbody>
</table>

<div class="pagination-bar">
    <div id="pagination-info">—</div>
    <div style="display:flex; gap:8px;">
        <button id="btn-precedent" onclick="pagePrecedente()"><i class="fas fa-chevron-left"></i> Précédent</button>
        <button id="btn-suivant" onclick="pageSuivante()">Suivant <i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<script>
let flux = 'ventes';
let categorie = 'emis';
let page = 1;
let dernierePagination = { derniere_page: 1 };
let debounceTimer = null;

function formatF(n) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(n || 0)) + ' F';
}

function debouncedRafraichir() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(rafraichirFactures, 400);
}

function changerFlux(f) {
    flux = f;
    document.getElementById('btn-flux-ventes').classList.toggle('active', f === 'ventes');
    document.getElementById('btn-flux-achats').classList.toggle('active', f === 'achats');
    document.getElementById('onglets-ventes').style.display = f === 'ventes' ? 'flex' : 'none';
    document.getElementById('onglets-achats').style.display = f === 'achats' ? 'flex' : 'none';
    document.getElementById('th-tiers').textContent = f === 'ventes' ? 'Client' : 'Fournisseur';
    categorie = f === 'ventes' ? 'emis' : 'recu';
    document.querySelectorAll(`#onglets-${f} button`).forEach((b, i) => b.classList.toggle('active', i === 0));
    page = 1;
    rafraichirFactures();
}

function changerCategorie(cat) {
    categorie = cat;
    const conteneurId = flux === 'ventes' ? 'onglets-ventes' : 'onglets-achats';
    document.querySelectorAll(`#${conteneurId} button`).forEach(b => b.classList.toggle('active', b.dataset.cat === cat));
    page = 1;
    rafraichirFactures();
}

function pagePrecedente() { if (page > 1) { page--; rafraichirFactures(); } }
function pageSuivante() { if (page < dernierePagination.derniere_page) { page++; rafraichirFactures(); } }

function rafraichirFactures() {
    const params = new URLSearchParams({
        flux: flux,
        categorie: categorie,
        periode_type: document.getElementById('f-periode-type').value,
        date: document.getElementById('f-date').value,
        pdv_id: document.getElementById('f-pdv').value,
        recherche: document.getElementById('f-recherche').value,
        page: page,
    });

    document.getElementById('corps-table').innerHTML = '<tr><td colspan="11" style="text-align:center; padding:30px; color:var(--text-3);"><i class="fas fa-spinner fa-spin"></i> Chargement...</td></tr>';

    fetch("{{ route('admin.fne.factures.donnees') }}?" + params.toString())
        .then(r => r.json())
        .then(d => {
            dernierePagination = d.pagination;
            const corps = document.getElementById('corps-table');

            if (d.documents.length === 0) {
                corps.innerHTML = '<tr><td colspan="11" style="text-align:center; padding:30px; color:var(--text-3);">Aucun document pour cette période/catégorie.</td></tr>';
            } else {
                corps.innerHTML = d.documents.map(doc => `
                    <tr>
                        <td>${doc.date ? new Date(doc.date).toLocaleDateString('fr-FR') : '—'}</td>
                        <td>${doc.type_doc}</td>
                        <td style="font-weight:700; color:var(--primary);">${doc.num_piece}</td>
                        <td>${doc.num_fne ?? '<span style="color:var(--text-3);">—</span>'}</td>
                        <td>${doc.tiers}</td>
                        <td style="text-align:right;">${formatF(doc.ht)}</td>
                        <td style="text-align:right;">${formatF(doc.tva)}</td>
                        <td style="text-align:right; font-weight:700;">${formatF(doc.ttc)}</td>
                        <td>
                            <span class="statut-fne ${doc.normalise ? 'statut-oui' : 'statut-non'}">
                                <i class="fas ${doc.normalise ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>
                                ${doc.normalise ? 'Normalisée' : 'Non normalisée'}
                            </span>
                        </td>
                        <td>${doc.pdv ?? '—'}</td>
                        <td style="text-align:center;">
                            <a href="${doc.telechargement_url}" target="_blank" class="btn btn-outline" style="padding:5px 10px; font-size:12px;" title="${doc.normalise ? 'Télécharger le PDF officiel DGI' : 'Voir la facture d\\'origine Selflow'}">
                                <i class="fas fa-download"></i>
                            </a>
                        </td>
                    </tr>
                `).join('');
            }

            document.getElementById('pagination-info').textContent =
                d.pagination.total > 0 ? `${d.pagination.de}–${d.pagination.a} sur ${d.pagination.total} document(s) — Total TTC affiché : ${formatF(d.totaux.ttc)}` : 'Aucun résultat';
            document.getElementById('btn-precedent').disabled = d.pagination.page_courante <= 1;
            document.getElementById('btn-suivant').disabled = d.pagination.page_courante >= d.pagination.derniere_page;
        })
        .catch(() => {
            document.getElementById('corps-table').innerHTML = '<tr><td colspan="11" style="text-align:center; padding:30px; color:#991b1b;">Erreur de chargement. Réessayez.</td></tr>';
        });
}

document.addEventListener('DOMContentLoaded', rafraichirFactures);
</script>
@endsection
