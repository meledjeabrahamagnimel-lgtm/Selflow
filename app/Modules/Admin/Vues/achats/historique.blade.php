@extends('admin::gabarits.application')
@section('titre', 'Historique des achats')
@section('topbar_titre', 'Achats — Historique')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-truck-loading"></i> Historique des achats</h1>
        <p>Toutes les réceptions de marchandises</p>
    </div>
    <a href="{{ route('admin.achats.nouveau') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvel achat
    </a>
</div>

<div class="card">
    <div class="table-wrap">
        @if($achats->isEmpty())
        <div style="padding:48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-truck" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucun achat enregistré.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>N° Facture</th>
                    <th>Date</th>
                    <th>Fournisseur</th>
                    <th>Point de vente</th>
                    <th>Paiement</th>
                    <th>Montant TTC</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($achats as $achat)
                <tr>
                    <td style="font-weight:700; color:var(--info);">{{ $achat->numero_facture }}</td>
                    <td>{{ \Carbon\Carbon::parse($achat->date_achat)->format('d/m/Y') }}</td>
                    <td style="font-weight:600;">{{ $achat->fournisseur->nom }}</td>
                    <td><span class="badge badge-purple">{{ $achat->pointDeVente->nom }}</span></td>
                    <td>{{ $achat->mode_paiement }}</td>
                    <td style="font-weight:700; color:var(--danger);">{{ number_format($achat->montant_ttc, 0, ',', ' ') }} F</td>
                    <td><span class="badge badge-success">{{ $achat->statut }}</span></td>
                    <td>
                        <a href="{{ route('admin.achats.imprimer', $achat) }}" class="btn btn-outline btn-sm">
                            <i class="fas fa-print"></i> Bon
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding: 0 16px;">{{ $achats->links() }}</div>
        @endif
    </div>
</div>
@endsection
