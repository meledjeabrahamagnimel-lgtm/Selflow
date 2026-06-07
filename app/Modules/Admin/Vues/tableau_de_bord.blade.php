@extends('admin::gabarits.application')

@section('titre', 'Tableau de bord')
@section('topbar_titre', 'Tableau de bord')

@section('contenu')

{{-- ── EN-TÊTE PAGE ── --}}
<div class="page-header">
    <div>
        <h1>Bonjour, {{ auth()->user()->nom }} 👋</h1>
        <p>{{ now()->isoFormat('dddd D MMMM YYYY') }} — Vue d'ensemble de votre activité</p>
    </div>
    <a href="{{ route('admin.ventes.nouvelle') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvelle vente
    </a>
</div>

{{-- ── KPI ── --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-arrow-trend-up"></i></div>
        <div>
            <div class="stat-value">{{ number_format($montantVentesJour, 0, ',', ' ') }} FCFA</div>
            <div class="stat-label">Ventes du jour</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-arrow-trend-down"></i></div>
        <div>
            <div class="stat-value">{{ number_format($montantAchatsJour, 0, ',', ' ') }} FCFA</div>
            <div class="stat-label">Achats du jour</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
        <div>
            <div class="stat-value" style="color: {{ $solde >= 0 ? '#10b981' : '#ef4444' }}">
                {{ number_format($solde, 0, ',', ' ') }} FCFA
            </div>
            <div class="stat-label">Solde trésorerie</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-triangle-exclamation"></i></div>
        <div>
            <div class="stat-value" style="color: {{ $produitsEnAlerte->count() > 0 ? '#ef4444' : '#10b981' }}">
                {{ $produitsEnAlerte->count() }}
            </div>
            <div class="stat-label">Alertes stock</div>
        </div>
    </div>
</div>

{{-- ── GRILLE PRINCIPALE ── --}}
<div class="grid-2" style="margin-bottom: 22px;">

    {{-- Dernières ventes --}}
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-receipt" style="color:var(--success)"></i> Dernières ventes</h2>
            <a href="{{ route('admin.ventes.historique') }}" class="btn btn-outline btn-sm">Tout voir</a>
        </div>
        <div class="table-wrap">
            @if($dernieresVentes->isEmpty())
            <div style="padding: 32px; text-align: center; color: var(--text-3);">
                <i class="fas fa-receipt" style="font-size:32px; margin-bottom:8px; opacity:.3; display:block;"></i>
                Aucune vente enregistrée
            </div>
            @else
            <table>
                <thead>
                    <tr>
                        <th>Facture</th>
                        <th>Client</th>
                        <th>Montant TTC</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dernieresVentes as $vente)
                    <tr>
                        <td>
                            <a href="{{ route('admin.ventes.imprimer', $vente) }}" style="color: var(--primary); text-decoration:none; font-weight:600;">
                                {{ $vente->numero_facture }}
                            </a>
                            <div style="font-size:11px; color:var(--text-3);">{{ $vente->created_at->diffForHumans() }}</div>
                        </td>
                        <td>{{ $vente->client?->nom ?? 'Client de passage' }}</td>
                        <td style="font-weight:700; color:var(--success);">{{ number_format($vente->montant_ttc, 0, ',', ' ') }} F</td>
                        <td><span class="badge badge-success">{{ $vente->statut }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Alertes stock --}}
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-boxes-stacked" style="color:var(--warning)"></i> Alertes stock</h2>
            <a href="{{ route('admin.stock.index') }}" class="btn btn-outline btn-sm">Gérer le stock</a>
        </div>
        <div class="table-wrap">
            @if($produitsEnAlerte->isEmpty())
            <div style="padding: 32px; text-align: center; color: var(--text-3);">
                <i class="fas fa-check-circle" style="font-size:32px; margin-bottom:8px; color:var(--success); display:block;"></i>
                Tous les stocks sont suffisants !
            </div>
            @else
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Stock actuel</th>
                        <th>Minimum</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($produitsEnAlerte as $p)
                    <tr>
                        <td>
                            <div style="font-weight:600;">{{ $p->nom }}</div>
                            <div style="font-size:11px; color:var(--text-3);">{{ $p->reference }}</div>
                        </td>
                        <td class="stock-alerte">{{ $p->stock_actuel }}</td>
                        <td style="color:var(--text-3);">{{ $p->stock_minimum }}</td>
                        <td>
                            @if($p->stock_actuel == 0)
                                <span class="badge badge-danger">Rupture</span>
                            @else
                                <span class="badge badge-warning">Faible</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

{{-- ── POINTS DE VENTE ── --}}
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-store" style="color:var(--primary)"></i> Points de vente — Activité du jour</h2>
        <a href="{{ route('admin.pdv.index') }}" class="btn btn-outline btn-sm">Gérer</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Point de vente</th>
                    <th>Ville</th>
                    <th>Responsable</th>
                    <th>Ventes du jour</th>
                    <th>CA du jour</th>
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
                    <td style="font-weight:600;">{{ $pdv->ventes_jour ?? 0 }}</td>
                    <td style="font-weight:700; color:var(--success);">{{ number_format($pdv->montant_ventes_jour ?? 0, 0, ',', ' ') }} F</td>
                    <td>
                        @if($pdv->statut === 'Ouvert')
                            <span class="badge badge-success">Ouvert</span>
                        @else
                            <span class="badge badge-gray">Fermé</span>
                        @endif
                    </td>
                    <td>
                        @if($pointDeVenteId != $pdv->id)
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

@endsection
