@extends('admin::gabarits.application')
@section('titre', 'Gestion FNE')
@section('topbar_titre', 'Gestion FNE — Documents normalisés DGI')

@section('styles')
<style>
    .fne-filtres { display:flex; gap:12px; align-items:end; flex-wrap:wrap; background:#fff; border:1px solid var(--border); border-radius:14px; padding:16px 20px; margin-bottom:22px; }
    .fne-filtres .form-group { margin-bottom:0; }
    .fne-filtres label { font-size:11px; text-transform:uppercase; font-weight:700; color:var(--text-3); display:block; margin-bottom:4px; }

    .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:26px; }
    .kpi-card { background:#fff; border:1px solid var(--border); border-radius:14px; padding:18px 20px; }
    .kpi-card .lbl { font-size:11px; text-transform:uppercase; font-weight:700; color:var(--text-3); letter-spacing:.4px; margin-bottom:8px; }
    .kpi-card .val { font-size:24px; font-weight:800; color:var(--text-1); }
    .kpi-card .sub { font-size:12px; color:var(--text-3); margin-top:4px; }

    .section-titre { font-size:14px; font-weight:700; color:var(--text-1); text-transform:uppercase; letter-spacing:.4px; margin:26px 0 14px; display:flex; align-items:center; gap:8px; }

    .doc-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .doc-table th { text-align:left; padding:10px 14px; font-size:11px; text-transform:uppercase; color:var(--text-3); background:#f8fafc; border-bottom:1px solid var(--border); }
    .doc-table td { padding:12px 14px; border-bottom:1px solid var(--border); font-size:13px; }
    .doc-table tr:last-child td { border-bottom:none; }
</style>
@endsection

@section('contenu')

<div class="fne-filtres">
    <div class="form-group">
        <label>Période</label>
        <select id="f-periode-type" class="form-control" onchange="rafraichirFneGestion()">
            <option value="jour">Jour</option>
            <option value="semaine">Semaine</option>
            <option value="mois" selected>Mois</option>
            <option value="annee">Année</option>
        </select>
    </div>
    <div class="form-group">
        <label>Date de référence</label>
        <input type="date" id="f-date" class="form-control" value="{{ date('Y-m-d') }}" onchange="rafraichirFneGestion()">
    </div>
    <div class="form-group">
        <label>Point de vente</label>
        <select id="f-pdv" class="form-control" onchange="rafraichirFneGestion()">
            <option value="tous">Tous les sites</option>
            @foreach($pointsDeVente as $pdv)
                <option value="{{ $pdv->id }}">{{ $pdv->nom }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary" onclick="rafraichirFneGestion()"><i class="fas fa-rotate"></i> Actualiser</button>
    <div style="margin-left:auto; font-size:12px; color:var(--text-3); align-self:center;">
        <i class="fas fa-circle-info"></i> Cette page ne montre QUE les documents déjà normalisés par la DGI. Pour voir tout le registre (normalisé ou non), utilisez « Factures &amp; Reçus ».
    </div>
</div>

{{-- ── Cartes 100% DGI (non disponibles sans appel API dédié — affichées à 0) ── --}}
<div class="section-titre"><i class="fas fa-stamp"></i> Stickers &amp; Timbres (plateforme DGI)</div>
<div class="kpi-grid">
    <div class="kpi-card"><div class="lbl">Solde Stickers</div><div class="val" id="k-stickers-solde">0</div><div class="sub">Non disponible — API DGI non branchée</div></div>
    <div class="kpi-card"><div class="lbl">Stickers achetés</div><div class="val" id="k-stickers-achats">0</div><div class="sub">Non disponible — API DGI non branchée</div></div>
    <div class="kpi-card"><div class="lbl">Stickers consommés</div><div class="val" id="k-stickers-consommes">0</div><div class="sub">Non disponible — API DGI non branchée</div></div>
    <div class="kpi-card"><div class="lbl">Timbre de quittance</div><div class="val" id="k-timbre">0 F</div><div class="sub">Non disponible — API DGI non branchée</div></div>
</div>

{{-- ── Ventes normalisées ── --}}
<div class="section-titre"><i class="fas fa-arrow-down" style="color:#10b981;"></i> Entrées — Ventes normalisées</div>
<div class="kpi-grid">
    <div class="kpi-card"><div class="lbl">Factures</div><div class="val" id="k-v-factures-n">0</div><div class="sub" id="k-v-factures-m">0 F</div></div>
    <div class="kpi-card"><div class="lbl">Reçus</div><div class="val" id="k-v-recus-n">0</div><div class="sub" id="k-v-recus-m">0 F</div></div>
    <div class="kpi-card"><div class="lbl">Avoirs clients</div><div class="val" id="k-v-avoirs-n">0</div><div class="sub" id="k-v-avoirs-m">0 F</div></div>
    <div class="kpi-card"><div class="lbl">Proforma</div><div class="val">0</div><div class="sub">Non applicable dans Selflow</div></div>
</div>
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="lbl">Total HT</div><div class="val" id="k-v-ht">0 F</div></div>
    <div class="kpi-card"><div class="lbl">TVA collectée</div><div class="val" id="k-v-tva">0 F</div></div>
    <div class="kpi-card"><div class="lbl">Total TTC</div><div class="val" id="k-v-ttc">0 F</div></div>
    <div class="kpi-card"><div class="lbl">Remises accordées</div><div class="val" id="k-v-remises">0 F</div></div>
</div>

{{-- ── Achats normalisés ── --}}
<div class="section-titre"><i class="fas fa-arrow-up" style="color:#ef4444;"></i> Sorties — Achats normalisés (BAPA inclus)</div>
<div class="kpi-grid">
    <div class="kpi-card"><div class="lbl">Factures reçues</div><div class="val" id="k-a-factures-n">0</div><div class="sub" id="k-a-factures-m">0 F</div></div>
    <div class="kpi-card"><div class="lbl">Avoirs fournisseurs</div><div class="val" id="k-a-avoirs-n">0</div><div class="sub" id="k-a-avoirs-m">0 F</div></div>
    <div class="kpi-card"><div class="lbl">TVA déductible</div><div class="val" id="k-a-tva">0 F</div></div>
    <div class="kpi-card"><div class="lbl">Total TTC</div><div class="val" id="k-a-ttc">0 F</div></div>
</div>

<script>
function formatF(n) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(n || 0)) + ' F';
}

function rafraichirFneGestion() {
    const params = new URLSearchParams({
        periode_type: document.getElementById('f-periode-type').value,
        date: document.getElementById('f-date').value,
        pdv_id: document.getElementById('f-pdv').value,
    });

    fetch("{{ route('admin.fne.gestion.donnees') }}?" + params.toString())
        .then(r => r.json())
        .then(d => {
            document.getElementById('k-stickers-solde').textContent = d.stickers_solde;
            document.getElementById('k-stickers-achats').textContent = d.stickers_achats;
            document.getElementById('k-stickers-consommes').textContent = d.stickers_consommes;
            document.getElementById('k-timbre').textContent = formatF(d.timbre_quittance);

            document.getElementById('k-v-factures-n').textContent = d.ventes.factures.nombre;
            document.getElementById('k-v-factures-m').textContent = formatF(d.ventes.factures.montant);
            document.getElementById('k-v-recus-n').textContent = d.ventes.recus.nombre;
            document.getElementById('k-v-recus-m').textContent = formatF(d.ventes.recus.montant);
            document.getElementById('k-v-avoirs-n').textContent = d.ventes.avoirs.nombre;
            document.getElementById('k-v-avoirs-m').textContent = formatF(d.ventes.avoirs.montant);
            document.getElementById('k-v-ht').textContent = formatF(d.ventes.total_ht);
            document.getElementById('k-v-tva').textContent = formatF(d.ventes.total_tva);
            document.getElementById('k-v-ttc').textContent = formatF(d.ventes.total_ttc);
            document.getElementById('k-v-remises').textContent = formatF(d.ventes.total_remises);

            document.getElementById('k-a-factures-n').textContent = d.achats.factures.nombre;
            document.getElementById('k-a-factures-m').textContent = formatF(d.achats.factures.montant);
            document.getElementById('k-a-avoirs-n').textContent = d.achats.avoirs.nombre;
            document.getElementById('k-a-avoirs-m').textContent = formatF(d.achats.avoirs.montant);
            document.getElementById('k-a-tva').textContent = formatF(d.achats.total_tva_deductible);
            document.getElementById('k-a-ttc').textContent = formatF(d.achats.total_ttc);
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    // Injection des valeurs calculées côté serveur au premier chargement (pas d'attente réseau)
    const initial = @json($kpis);
    document.getElementById('k-stickers-solde').textContent = initial.stickers_solde;
    document.getElementById('k-stickers-achats').textContent = initial.stickers_achats;
    document.getElementById('k-stickers-consommes').textContent = initial.stickers_consommes;
    document.getElementById('k-timbre').textContent = formatF(initial.timbre_quittance);
    document.getElementById('k-v-factures-n').textContent = initial.ventes.factures.nombre;
    document.getElementById('k-v-factures-m').textContent = formatF(initial.ventes.factures.montant);
    document.getElementById('k-v-recus-n').textContent = initial.ventes.recus.nombre;
    document.getElementById('k-v-recus-m').textContent = formatF(initial.ventes.recus.montant);
    document.getElementById('k-v-avoirs-n').textContent = initial.ventes.avoirs.nombre;
    document.getElementById('k-v-avoirs-m').textContent = formatF(initial.ventes.avoirs.montant);
    document.getElementById('k-v-ht').textContent = formatF(initial.ventes.total_ht);
    document.getElementById('k-v-tva').textContent = formatF(initial.ventes.total_tva);
    document.getElementById('k-v-ttc').textContent = formatF(initial.ventes.total_ttc);
    document.getElementById('k-v-remises').textContent = formatF(initial.ventes.total_remises);
    document.getElementById('k-a-factures-n').textContent = initial.achats.factures.nombre;
    document.getElementById('k-a-factures-m').textContent = formatF(initial.achats.factures.montant);
    document.getElementById('k-a-avoirs-n').textContent = initial.achats.avoirs.nombre;
    document.getElementById('k-a-avoirs-m').textContent = formatF(initial.achats.avoirs.montant);
    document.getElementById('k-a-tva').textContent = formatF(initial.achats.total_tva_deductible);
    document.getElementById('k-a-ttc').textContent = formatF(initial.achats.total_ttc);
});
</script>
@endsection
