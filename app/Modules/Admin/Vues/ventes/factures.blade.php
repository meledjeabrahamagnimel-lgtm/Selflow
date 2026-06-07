@extends('admin::gabarits.application')
@section('titre', 'Factures — Ventes')
@section('topbar_titre', 'Ventes — Factures')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-file-invoice"></i> Factures de vente</h1>
        <p>{{ $ventes->total() }} facture(s) au total</p>
    </div>
    <a href="{{ route('admin.ventes.nouvelle') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvelle vente
    </a>
</div>

<div class="card">
    <div class="table-wrap">
        @if($ventes->isEmpty())
        <div style="padding: 48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-file-invoice" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucune facture disponible.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>N° Facture</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Articles</th>
                    <th>HT</th>
                    <th>TVA</th>
                    <th>TTC</th>
                    <th>Mode paiement</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventes as $vente)
                <tr>
                    <td style="font-weight:700; color:var(--primary);">{{ $vente->numero_facture }}</td>
                    <td>{{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y') }}</td>
                    <td>{{ $vente->client?->nom ?? '— Passage —' }}</td>
                    <td style="color:var(--text-2);">{{ $vente->details->count() }} article(s)</td>
                    <td>{{ number_format($vente->montant_ht, 0, ',', ' ') }} F</td>
                    <td>{{ number_format($vente->montant_tva, 0, ',', ' ') }} F</td>
                    <td style="font-weight:700; color:var(--success);">{{ number_format($vente->montant_ttc, 0, ',', ' ') }} F</td>
                    <td>{{ $vente->mode_paiement }}</td>
                    <td>
                        <a href="{{ route('admin.ventes.imprimer', $vente) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-print"></i> Imprimer
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding: 0 16px;">{{ $ventes->links() }}</div>
        @endif
    </div>
</div>
@endsection
