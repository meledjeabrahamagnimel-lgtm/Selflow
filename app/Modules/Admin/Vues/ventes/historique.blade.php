@extends('admin::gabarits.application')
@section('titre', 'Historique des ventes')
@section('topbar_titre', 'Ventes — Historique')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-history"></i> Historique des ventes</h1>
        <p>Consultez toutes les ventes enregistrées</p>
    </div>
    <a href="{{ route('admin.ventes.nouvelle') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvelle vente
    </a>
</div>

<div class="card">
    <div class="table-wrap">
        @if($ventes->isEmpty())
        <div style="padding: 48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-receipt" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucune vente enregistrée pour le moment.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>N° Facture</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Point de vente</th>
                    <th>Paiement</th>
                    <th>Montant TTC</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventes as $vente)
                <tr>
                    <td style="font-weight:700; color:var(--primary);">{{ $vente->numero_facture }}</td>
                    <td>{{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y') }}</td>
                    <td>{{ $vente->client?->nom ?? 'Client de passage' }}</td>
                    <td><span class="badge badge-purple">{{ $vente->pointDeVente->nom }}</span></td>
                    <td>{{ $vente->mode_paiement }}</td>
                    <td style="font-weight:700; color:var(--success);">{{ number_format($vente->montant_ttc, 0, ',', ' ') }} F</td>
                    <td><span class="badge badge-success">{{ $vente->statut }}</span></td>
                    <td>
                        <a href="{{ route('admin.ventes.imprimer', $vente) }}" class="btn btn-outline btn-sm">
                            <i class="fas fa-print"></i> Facture
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding: 0 16px;">
            {{ $ventes->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
