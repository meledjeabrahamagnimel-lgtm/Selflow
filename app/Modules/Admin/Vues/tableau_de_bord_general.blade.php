@extends('admin::gabarits.application')

@section('titre', 'Tableau de bord général')
@section('topbar_titre', 'Tableau de bord général')

@section('contenu')

{{-- ── EN-TÊTE ─────────────────────────────────────────────────────────── --}}
<div class="page-header" style="margin-bottom:20px;">
    <div>
        <h1 style="font-size:22px; font-weight:800; display:flex; align-items:center; gap:10px;">
            <span style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#002B5C,#0ea5e9);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:18px;">
                <i class="fas fa-building"></i>
            </span>
            Tableau de bord général 🏢
        </h1>
        <p style="color:var(--text-2); margin-top:6px; font-size:13px;">
            <i class="fas fa-calendar-day"></i> {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
            &nbsp;·&nbsp; Période : <strong>{{ session('active_periode_nom', 'Non définie') }}</strong>
            &nbsp;·&nbsp; <strong>{{ $entreprise->nom }}</strong>
            &nbsp;·&nbsp; {{ $pointsDeVente->count() }} point{{ $pointsDeVente->count() > 1 ? 's' : '' }} de vente
        </p>
    </div>
    <div style="display:flex; gap:8px;">
        @if(auth()->user()->aHabilitation('rapports_analyse'))
        <a href="{{ route('admin.rapports.analyse_activite') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-chart-mixed"></i> Analyse complète
        </a>
        @endif
        @if(auth()->user()->aHabilitation('nouvelle_vente'))
        <a href="{{ route('admin.ventes.nouvelle') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Nouvelle vente
        </a>
        @endif
    </div>
</div>

{{-- ── KPI LIGNE 1 : AUJOURD'HUI ──────────────────────────────────────── --}}
<div style="margin-bottom:8px; font-size:10.5px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:1px;">
    <i class="fas fa-sun"></i> Aujourd'hui
</div>
<div style="display:grid; grid-template-columns: repeat(4,1fr); gap:14px; margin-bottom:20px;">
    <div class="tdb-kpi tdb-kpi-blue">
        <div class="tdb-kpi-icon"><i class="fas fa-arrow-trend-up"></i></div>
        <div>
            <div class="tdb-kpi-val">{{ number_format($montantVentesJour, 0, ',', ' ') }} <span>FCFA</span></div>
            <div class="tdb-kpi-lbl">CA global du jour</div>
            <div class="tdb-kpi-sub">{{ $nbVentesJour }} vente{{ $nbVentesJour > 1 ? 's' : '' }}</div>
        </div>
    </div>
    <div class="tdb-kpi tdb-kpi-red">
        <div class="tdb-kpi-icon"><i class="fas fa-cart-shopping"></i></div>
        <div>
            <div class="tdb-kpi-val">{{ number_format($montantAchatsJour, 0, ',', ' ') }} <span>FCFA</span></div>
            <div class="tdb-kpi-lbl">Achats du jour</div>
        </div>
    </div>
    <div class="tdb-kpi {{ $solde >= 0 ? 'tdb-kpi-green' : 'tdb-kpi-danger' }}">
        <div class="tdb-kpi-icon"><i class="fas fa-vault"></i></div>
        <div>
            <div class="tdb-kpi-val">{{ number_format($solde, 0, ',', ' ') }} <span>FCFA</span></div>
            <div class="tdb-kpi-lbl">Solde trésorerie global</div>
            <div class="tdb-kpi-sub">Enc. {{ number_format($totalEncaissements,0,',',' ') }} / Déc. {{ number_format($totalDecaissements,0,',',' ') }}</div>
        </div>
    </div>
    <div class="tdb-kpi {{ $produitsEnAlerte->count() > 0 ? 'tdb-kpi-warning' : 'tdb-kpi-green' }}">
        <div class="tdb-kpi-icon"><i class="fas fa-boxes-stacked"></i></div>
        <div>
            <div class="tdb-kpi-val">{{ $produitsEnAlerte->count() }}</div>
            <div class="tdb-kpi-lbl">Alertes stock</div>
        </div>
    </div>
</div>

{{-- ── KPI LIGNE 2 : PÉRIODE ───────────────────────────────────────────── --}}
<div style="margin-bottom:8px; font-size:10.5px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:1px;">
    <i class="fas fa-calendar-range"></i> Période · {{ session('active_periode_nom', '—') }}
</div>
<div style="display:grid; grid-template-columns: repeat(4,1fr); gap:14px; margin-bottom:24px;">
    <div class="tdb-kpi tdb-kpi-blue">
        <div class="tdb-kpi-icon"><i class="fas fa-chart-line"></i></div>
        <div>
            <div class="tdb-kpi-val">{{ number_format($totalVentesPeriode, 0, ',', ' ') }} <span>FCFA</span></div>
            <div class="tdb-kpi-lbl">CA TTC période</div>
            <div class="tdb-kpi-sub">{{ $nbVentesPeriode }} ventes facturées</div>
        </div>
    </div>
    <div class="tdb-kpi tdb-kpi-red">
        <div class="tdb-kpi-icon"><i class="fas fa-receipt"></i></div>
        <div>
            <div class="tdb-kpi-val">{{ number_format($totalAchatsPeriode, 0, ',', ' ') }} <span>FCFA</span></div>
            <div class="tdb-kpi-lbl">Achats TTC période</div>
        </div>
    </div>
    <div class="tdb-kpi {{ $margeBrutePeriode >= 0 ? 'tdb-kpi-green' : 'tdb-kpi-danger' }}">
        <div class="tdb-kpi-icon"><i class="fas fa-scale-balanced"></i></div>
        <div>
            <div class="tdb-kpi-val">{{ number_format($margeBrutePeriode, 0, ',', ' ') }} <span>FCFA</span></div>
            <div class="tdb-kpi-lbl">Marge brute</div>
            <div class="tdb-kpi-sub">Taux : {{ $tauxMargePeriode }}%</div>
        </div>
    </div>
    <div class="tdb-kpi tdb-kpi-purple">
        <div class="tdb-kpi-icon"><i class="fas fa-percent"></i></div>
        <div>
            @php $caParVente = $nbVentesPeriode > 0 ? $totalVentesPeriode / $nbVentesPeriode : 0; @endphp
            <div class="tdb-kpi-val">{{ number_format($caParVente, 0, ',', ' ') }} <span>FCFA</span></div>
            <div class="tdb-kpi-lbl">Panier moyen</div>
            <div class="tdb-kpi-sub">Par vente facturée</div>
        </div>
    </div>
</div>

{{-- ── GRAPHIQUES LIGNE ────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1.4fr 1fr; gap:16px; margin-bottom:20px;">

    {{-- Évolution 7 jours --}}
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-area" style="color:#3b82f6"></i> CA global — 7 derniers jours</h2>
        </div>
        <div class="card-body" style="padding:16px;">
            <div style="position:relative; height:210px;">
                <canvas id="chartEvolution7j"></canvas>
            </div>
        </div>
    </div>

    {{-- CA par PDV (Période) --}}
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-store" style="color:var(--primary)"></i> CA par point de vente</h2>
        </div>
        <div class="card-body" style="padding:16px;">
            <div style="position:relative; height:210px;">
                <canvas id="chartCaPdv"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── TOP VENDEURS + DERNIÈRES VENTES ───────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1fr 1.8fr; gap:16px; margin-bottom:20px;">

    {{-- Top vendeurs du jour --}}
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-ranking-star" style="color:#f59e0b"></i> Top vendeurs — aujourd'hui</h2>
        </div>
        <div class="card-body" style="padding:14px;">
            @if($topVendeurs->isEmpty())
            <div style="text-align:center; padding:24px; color:var(--text-3);">
                <i class="fas fa-users" style="font-size:24px; opacity:.3;"></i>
                <div style="margin-top:8px; font-size:13px;">Aucune vente aujourd'hui</div>
            </div>
            @else
            @php $maxV = $topVendeurs->max('total') ?: 1; @endphp
            <ul style="list-style:none; display:flex; flex-direction:column; gap:10px;">
                @foreach($topVendeurs as $i => $v)
                <li style="display:flex; align-items:center; gap:10px;">
                    <div style="width:24px; height:24px; border-radius:8px; background:{{ ['#f59e0b','#3b82f6','#8b5cf6','#10b981','#94a3b8'][$i] }}22; display:flex;align-items:center;justify-content:center; font-size:11px; font-weight:800; color:{{ ['#f59e0b','#3b82f6','#8b5cf6','#10b981','#94a3b8'][$i] }}; flex-shrink:0;">
                        {{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : $i+1)) }}
                    </div>
                    <div style="flex:1; overflow:hidden;">
                        <div style="font-size:12.5px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $v->nom_employe }}</div>
                        <div style="height:4px; background:var(--border); border-radius:2px; margin-top:4px;">
                            <div style="width:{{ round(($v->total/$maxV)*100) }}%; height:100%; border-radius:2px; background:{{ ['#f59e0b','#3b82f6','#8b5cf6','#10b981','#94a3b8'][$i] }};"></div>
                        </div>
                    </div>
                    <div style="font-size:12px; font-weight:700; color:var(--text); flex-shrink:0;">
                        {{ number_format($v->total, 0, ',', ' ') }}<br>
                        <span style="font-size:10px; color:var(--text-3); font-weight:400;">{{ $v->nb_ventes }} vente{{ $v->nb_ventes > 1 ? 's' : '' }}</span>
                    </div>
                </li>
                @endforeach
            </ul>
            @endif
        </div>
    </div>

    {{-- Dernières ventes globales --}}
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-receipt" style="color:var(--success)"></i> Dernières ventes globales</h2>
            @if(auth()->user()->aHabilitation('factures_vente'))
            <a href="{{ route('admin.ventes.factures') }}" class="btn btn-outline btn-sm">Tout voir</a>
            @endif
        </div>
        <div class="table-wrap" style="max-height:280px; overflow-y:auto;">
            @if($dernieresVentes->isEmpty())
            <div style="padding:32px; text-align:center; color:var(--text-3);">
                <i class="fas fa-receipt" style="font-size:28px; margin-bottom:8px; opacity:.3; display:block;"></i>
                Aucune vente enregistrée
            </div>
            @else
            <table>
                <thead>
                    <tr>
                        <th>Facture</th>
                        <th>Client</th>
                        <th>PDV</th>
                        <th>Montant TTC</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dernieresVentes as $vente)
                    <tr>
                        <td>
                            <a href="{{ route('admin.ventes.imprimer', $vente) }}" style="color:var(--primary); text-decoration:none; font-weight:600;">
                                {{ $vente->numero_facture }}
                            </a>
                            <div style="font-size:11px; color:var(--text-3);">{{ $vente->created_at->diffForHumans() }}</div>
                        </td>
                        <td>{{ $vente->client?->nom ?? 'Client de passage' }}</td>
                        <td style="font-size:12px; color:var(--text-3);">{{ $vente->pointDeVente?->nom ?? '—' }}</td>
                        <td style="font-weight:700; color:var(--success);">{{ number_format($vente->montant_ttc, 0, ',', ' ') }} F</td>
                        <td>
                            @php
                                $badgeCls = match($vente->statut) {
                                    'Facturée','Payé' => 'badge-success',
                                    'Avance'          => 'badge-warning',
                                    'Crédit'          => 'badge-danger',
                                    default           => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $badgeCls }}">{{ $vente->statut }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

{{-- ── TABLEAU POINTS DE VENTE ─────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h2><i class="fas fa-store" style="color:var(--primary)"></i> Points de vente — Activité du jour</h2>
        @if(auth()->user()->aHabilitation('gestion_pdv'))
        <a href="{{ route('admin.pdv.index') }}" class="btn btn-outline btn-sm">Gérer</a>
        @endif
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Point de vente</th>
                    <th>Ville</th>
                    <th>Responsable</th>
                    <th class="text-right">Ventes du jour</th>
                    <th class="text-right">CA du jour</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pointsDeVente as $pdv)
                <tr>
                    <td>
                        <div style="font-weight:600; display:flex; align-items:center; gap:8px;">
                            @if($pointDeVenteId == $pdv->id)
                                <span style="width:8px;height:8px;border-radius:50%;background:var(--success);display:inline-block;"></span>
                            @else
                                <span style="width:8px;height:8px;border-radius:50%;background:var(--text-3);display:inline-block;"></span>
                            @endif
                            {{ $pdv->nom }}
                        </div>
                    </td>
                    <td style="color:var(--text-2);">{{ $pdv->commune }}, {{ $pdv->ville }}</td>
                    <td style="color:var(--text-2);">{{ $pdv->responsable ?? '—' }}</td>
                    <td style="font-weight:600; text-align:right;">{{ $pdv->ventes_jour ?? 0 }}</td>
                    <td style="font-weight:700; color:var(--success); text-align:right;">{{ number_format($pdv->montant_ventes_jour ?? 0, 0, ',', ' ') }} F</td>
                    <td>
                        @if($pdv->statut === 'Ouvert')
                            <span class="badge badge-success">Ouvert</span>
                        @else
                            <span class="badge badge-gray">Fermé</span>
                        @endif
                    </td>
                    <td>
                        @if($pdv->nom === 'Siège')
                            <span style="color:var(--primary); font-size:12px; font-weight:600;"><i class="fas fa-building"></i> Siège</span>
                        @elseif($pointDeVenteId != $pdv->id && auth()->user()->aHabilitation('gestion_pdv'))
                        <form method="POST" action="{{ route('admin.pdv.activer', $pdv) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm">
                                <i class="fas fa-toggle-on"></i> Activer
                            </button>
                        </form>
                        @else
                            <span style="color:var(--success); font-size:12px; font-weight:600;"><i class="fas fa-check"></i> Actif</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── ALERTES STOCK ─────────────────────────────────────────────────── --}}
@if($produitsEnAlerte->count())
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-triangle-exclamation" style="color:var(--warning)"></i> Alertes stock ({{ $produitsEnAlerte->count() }})</h2>
        @if(auth()->user()->aHabilitation('stock_articles'))
        <a href="{{ route('admin.stock.index') }}" class="btn btn-outline btn-sm">Gérer le stock</a>
        @endif
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Produit</th><th>Référence</th><th>Stock actuel</th><th>Minimum</th><th>Statut</th></tr>
            </thead>
            <tbody>
                @foreach($produitsEnAlerte as $p)
                <tr>
                    <td style="font-weight:600;">{{ $p->nom }}</td>
                    <td style="color:var(--text-3);">{{ $p->reference ?? '—' }}</td>
                    <td class="{{ $p->stock_actuel <= 0 ? 'stock-alerte' : 'stock-warning' }}" style="font-weight:700;">{{ $p->stock_actuel }}</td>
                    <td style="color:var(--text-3);">{{ $p->stock_minimum }}</td>
                    <td>
                        @if($p->stock_actuel <= 0)
                            <span class="badge badge-danger">Rupture</span>
                        @else
                            <span class="badge badge-warning">Faible</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── STYLES ─────────────────────────────────────────────────────────── --}}
<style>
.tdb-kpi {
    display:flex; align-items:flex-start; gap:14px;
    padding:18px 20px; border-radius:14px;
    background: var(--surface);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    transition:transform .2s, box-shadow .2s;
}
.tdb-kpi:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.08); }
.tdb-kpi-icon {
    width:46px; height:46px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:18px;
}
.tdb-kpi-val { font-size:20px; font-weight:800; line-height:1.1; color: var(--text); }
.tdb-kpi-val span { font-size:12px; font-weight:500; color: var(--text-2); }
.tdb-kpi-lbl { font-size:11.5px; font-weight:600; color: var(--text-2); text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
.tdb-kpi-sub { font-size:11px; color: var(--text-3); margin-top:3px; }

/* Icônes colorées sur fond pastel — arrière-plan de la carte toujours blanc */
.tdb-kpi-blue    .tdb-kpi-icon { background:rgba(59,130,246,.12);  color:#3b82f6; }
.tdb-kpi-red     .tdb-kpi-icon { background:rgba(239,68,68,.12);   color:#ef4444; }
.tdb-kpi-green   .tdb-kpi-icon { background:rgba(16,185,129,.12);  color:#10b981; }
.tdb-kpi-danger  .tdb-kpi-icon { background:rgba(239,68,68,.12);   color:#ef4444; }
.tdb-kpi-warning .tdb-kpi-icon { background:rgba(245,158,11,.12);  color:#f59e0b; }
.tdb-kpi-purple  .tdb-kpi-icon { background:rgba(139,92,246,.12);  color:#8b5cf6; }

/* Valeur colorée seulement pour les montants négatifs */
.tdb-kpi-danger  .tdb-kpi-val { color: #ef4444; }
.text-right { text-align:right; }
</style>

{{-- ── CHART.JS ─────────────────────────────────────────────────────── --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Évolution 7 jours ──
const jours7 = @json($jours7);
new Chart(document.getElementById('chartEvolution7j'), {
    type: 'bar',
    data: {
        labels: jours7.map(j => { const d = new Date(j.jour); return d.toLocaleDateString('fr-FR',{weekday:'short',day:'numeric'}); }),
        datasets: [{
            label: 'CA (FCFA)',
            data: jours7.map(j => parseFloat(j.total)||0),
            backgroundColor: jours7.map((j,i) => i===jours7.length-1 ? '#002B5C' : 'rgba(59,130,246,0.65)'),
            borderRadius: 8,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('fr') } }
        }
    }
});

// ── CA par PDV (période) ──
const caPdv = @json($caPdvPeriode);
if (caPdv.length) {
    const COLORS = ['#002B5C','#3b82f6','#10b981','#f59e0b','#8b5cf6','#ef4444'];
    new Chart(document.getElementById('chartCaPdv'), {
        type: 'doughnut',
        data: {
            labels: caPdv.map(p => p.pdv_nom),
            datasets: [{
                data: caPdv.map(p => parseFloat(p.ca)||0),
                backgroundColor: COLORS,
                borderWidth: 2, borderColor: '#fff',
            }]
        },
        options: {
            cutout: '60%',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { font: { size: 11 }, boxWidth: 12 } }
            }
        }
    });
}
</script>

@endsection
