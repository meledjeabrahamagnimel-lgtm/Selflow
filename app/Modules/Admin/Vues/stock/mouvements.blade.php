@extends('admin::gabarits.application')
@section('titre', 'Mouvements de stock')
@section('topbar_titre', 'Stock — Mouvements')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-arrows-up-down"></i> Mouvements de stock</h1>
        <p>Historique de toutes les entrées et sorties</p>
    </div>
    <a href="{{ route('admin.stock.index') }}" class="btn btn-outline">
        <i class="fas fa-boxes-stacked"></i> Inventaire
    </a>
</div>

<div class="card">
    <div class="table-wrap">
        @if($mouvements->isEmpty())
        <div style="padding:48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-arrows-up-down" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucun mouvement de stock enregistré.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Produit</th>
                    <th>Point de vente</th>
                    <th>Type</th>
                    <th>Quantité</th>
                    <th>Stock avant</th>
                    <th>Stock après</th>
                    <th>Référence doc.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mouvements as $m)
                <tr>
                    <td style="color:var(--text-3); font-size:12px;">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        <div style="font-weight:600;">{{ $m->produit->nom }}</div>
                        <div style="font-size:11px; color:var(--text-3);">{{ $m->produit->reference }}</div>
                    </td>
                    <td><span class="badge badge-purple">{{ $m->pointDeVente->nom }}</span></td>
                    <td>
                        @if($m->type_mouvement === 'Entrée')
                            <span class="badge badge-success"><i class="fas fa-arrow-down"></i> Entrée</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-arrow-up"></i> Sortie</span>
                        @endif
                    </td>
                    <td style="font-weight:700; {{ $m->type_mouvement === 'Entrée' ? 'color:var(--success)' : 'color:var(--danger)' }}">
                        {{ $m->type_mouvement === 'Entrée' ? '+' : '-' }}{{ $m->quantite }}
                    </td>
                    <td style="color:var(--text-3);">{{ $m->stock_avant }}</td>
                    <td style="font-weight:600;">{{ $m->stock_apres }}</td>
                    <td style="font-family:monospace; font-size:12px; color:var(--primary);">{{ $m->reference_document ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding: 0 16px;">{{ $mouvements->links() }}</div>
        @endif
    </div>
</div>
@endsection
