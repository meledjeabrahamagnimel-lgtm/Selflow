@extends('admin::gabarits.application')
@section('titre', 'Factures — Achats')
@section('topbar_titre', 'Achats — Factures')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-file-invoice"></i> Bons d'achat</h1>
        <p>{{ $achats->total() }} bon(s) au total</p>
    </div>
    <a href="{{ route('admin.achats.nouveau') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvel achat
    </a>
</div>

<div class="card">
    <div class="table-wrap">
        @if($achats->isEmpty())
        <div style="padding:48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-file" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucun bon d'achat disponible.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>N° Bon</th>
                    <th>Date</th>
                    <th>Fournisseur</th>
                    <th>Articles</th>
                    <th>HT</th>
                    <th>TVA</th>
                    <th>TTC</th>
                    <th>Mode</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($achats as $achat)
                <tr>
                    <td style="font-weight:700; color:var(--info);">{{ $achat->numero_facture }}</td>
                    <td>{{ \Carbon\Carbon::parse($achat->date_achat)->format('d/m/Y') }}</td>
                    <td style="font-weight:600;">{{ $achat->fournisseur->nom }}</td>
                    <td style="color:var(--text-2);">{{ $achat->details->count() }}</td>
                    <td>{{ number_format($achat->montant_ht, 0, ',', ' ') }} F</td>
                    <td>{{ number_format($achat->montant_tva, 0, ',', ' ') }} F</td>
                    <td style="font-weight:700; color:var(--danger);">{{ number_format($achat->montant_ttc, 0, ',', ' ') }} F</td>
                    <td>{{ $achat->mode_paiement }}</td>
                    <td>
                        <a href="{{ route('admin.achats.imprimer', $achat) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-print"></i> Imprimer
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
