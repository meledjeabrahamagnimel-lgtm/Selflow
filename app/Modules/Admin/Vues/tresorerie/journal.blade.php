@extends('admin::gabarits.application')
@section('titre', 'Journal de trésorerie')
@section('topbar_titre', 'Trésorerie — Journal')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-wallet"></i> Journal de trésorerie</h1>
        <p>Toutes les opérations financières</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="{{ route('admin.tresorerie.encaissements') }}" class="btn btn-outline">
            <i class="fas fa-arrow-down" style="color:var(--success)"></i> Encaissements
        </a>
        <a href="{{ route('admin.tresorerie.decaissements') }}" class="btn btn-outline">
            <i class="fas fa-arrow-up" style="color:var(--danger)"></i> Décaissements
        </a>
    </div>
</div>

{{-- Résumé solde --}}
<div class="stats-grid" style="grid-template-columns: repeat(3,1fr); margin-bottom:22px;">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-arrow-down"></i></div>
        <div>
            <div class="stat-value" style="color:var(--success)">{{ number_format($totalEntrees, 0, ',', ' ') }} F</div>
            <div class="stat-label">Total encaissements</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-arrow-up"></i></div>
        <div>
            <div class="stat-value" style="color:var(--danger)">{{ number_format($totalSorties, 0, ',', ' ') }} F</div>
            <div class="stat-label">Total décaissements</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-scale-balanced"></i></div>
        <div>
            <div class="stat-value" style="{{ $soldeFinal >= 0 ? 'color:var(--success)' : 'color:var(--danger)' }}">{{ number_format($soldeFinal, 0, ',', ' ') }} F</div>
            <div class="stat-label">Solde net</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        @if($operations->isEmpty())
        <div style="padding:48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-wallet" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucune opération enregistrée.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Libellé</th>
                    <th>Point de vente</th>
                    <th>Mode</th>
                    <th>Entrée</th>
                    <th>Sortie</th>
                    <th>Solde cumulé</th>
                    <th>Référence</th>
                </tr>
            </thead>
            <tbody>
                @foreach($operations as $op)
                <tr>
                    <td style="font-size:12px; color:var(--text-3);">{{ \Carbon\Carbon::parse($op->date_operation)->format('d/m/Y') }}</td>
                    <td>
                        @if($op->type_operation === 'Encaissement')
                            <span class="badge badge-success"><i class="fas fa-arrow-down"></i> Encaissement</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-arrow-up"></i> Décaissement</span>
                        @endif
                    </td>
                    <td style="font-weight:500;">{{ $op->libelle }}</td>
                    <td style="font-size:12px; color:var(--text-3);">{{ $op->pointDeVente->nom }}</td>
                    <td style="font-size:12px;">{{ $op->mode_paiement }}</td>
                    <td style="font-weight:700; color:var(--success);">
                        {{ $op->montant_entree > 0 ? number_format($op->montant_entree, 0, ',', ' ') . ' F' : '—' }}
                    </td>
                    <td style="font-weight:700; color:var(--danger);">
                        {{ $op->montant_sortie > 0 ? number_format($op->montant_sortie, 0, ',', ' ') . ' F' : '—' }}
                    </td>
                    <td style="font-weight:800; {{ $op->solde_resultat >= 0 ? 'color:var(--success)' : 'color:var(--danger)' }}">
                        {{ number_format($op->solde_resultat, 0, ',', ' ') }} F
                    </td>
                    <td style="font-family:monospace; font-size:11px; color:var(--primary);">{{ $op->reference_document ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding: 0 16px;">{{ $operations->links() }}</div>
        @endif
    </div>
</div>
@endsection
