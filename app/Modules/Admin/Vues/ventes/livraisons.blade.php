@extends('admin::gabarits.application')
@section('titre', 'Bons de Livraison')
@section('topbar_titre', 'Ventes — Bons de Livraison')

@section('contenu')
@php $isCaissier = request()->routeIs('caissier.*'); @endphp

<div class="page-header">
    <div>
        <h1><i class="fas fa-truck"></i> Bons de Livraison</h1>
        <p>Suivi des livraisons clients</p>
    </div>
    <a href="{{ $isCaissier ? route('caissier.ventes.factures', ['etape'=>'Bon de commande']) : route('admin.ventes.factures', ['etape'=>'Bon de commande']) }}"
       class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Retour aux commandes
    </a>
</div>

{{-- Filtres statut --}}
<div style="display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap;">
    @php
        $routeBL = $isCaissier ? 'caissier.ventes.livraisons' : 'admin.ventes.livraisons';
        $statuts = [
            ''               => ['label' => 'Tous',          'icon' => 'fa-list'],
            'en_preparation' => ['label' => 'En préparation','icon' => 'fa-clock'],
            'partiel'        => ['label' => 'Partiel',       'icon' => 'fa-triangle-exclamation'],
            'livre'          => ['label' => 'Livré',         'icon' => 'fa-check-circle'],
            'facture'        => ['label' => 'Facturé',       'icon' => 'fa-file-invoice'],
        ];
    @endphp
    @foreach($statuts as $val => $info)
    <a href="{{ route($routeBL, $val ? ['statut' => $val] : []) }}"
       style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13px; transition:all .15s;
              {{ $statut === ($val ?: null) || (!$statut && !$val) ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
        <i class="fas {{ $info['icon'] }}"></i> {{ $info['label'] }}
    </a>
    @endforeach
</div>

<div class="card">
    <div class="table-wrap">
        @if($bls->isEmpty())
        <div style="padding:48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-truck" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucun bon de livraison pour ce filtre.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th style="white-space:nowrap;">N° BL</th>
                    <th style="white-space:nowrap;">Date livraison</th>
                    <th style="white-space:nowrap;">Client</th>
                    <th style="white-space:nowrap;">Réf. BC</th>
                    <th style="white-space:nowrap;">Statut</th>
                    <th style="white-space:nowrap; text-align:center;">Partiel</th>
                    <th style="white-space:nowrap;">Réf. Facture</th>
                    <th style="white-space:nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bls as $bl)
                @php
                    $routeVoir    = $isCaissier ? route('caissier.ventes.livraison.voir', $bl) : route('admin.ventes.livraison.voir', $bl);
                    $routeLivrer  = $isCaissier ? route('caissier.ventes.livraison.livrer', $bl) : route('admin.ventes.livraison.livrer', $bl);
                    $badgeStyles  = [
                        'en_preparation' => 'background:#f3f4f6; color:#374151;',
                        'partiel'        => 'background:#fffbeb; color:#b45309;',
                        'livre'          => 'background:#e0f2fe; color:#0369a1;',
                        'facture'        => 'background:#e6fdf5; color:#047857;',
                    ];
                    $style = $badgeStyles[$bl->statut] ?? 'background:#f3f4f6; color:#374151;';
                @endphp
                <tr>
                    <td style="font-weight:700; color:var(--primary); white-space:nowrap;">{{ $bl->numero_bl }}</td>
                    <td style="white-space:nowrap;">{{ $bl->date_livraison->format('d/m/Y') }}</td>
                    <td style="white-space:nowrap;">{{ $bl->client?->nom ?? '— Passage —' }}</td>
                    <td style="white-space:nowrap; font-weight:600; color:var(--text-2);">{{ $bl->bonDeCommande->numero_facture }}</td>
                    <td style="white-space:nowrap;">
                        <span style="padding:4px 10px; border-radius:20px; font-weight:700; font-size:12px; {{ $style }}">
                            {{ $bl->statut_label }}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        @if($bl->livraison_partielle)
                            <span style="color:#d97706; font-size:14px;"><i class="fas fa-triangle-exclamation"></i></span>
                        @else
                            <span style="color:#059669; font-size:14px;"><i class="fas fa-check-circle"></i></span>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        @if($bl->facture)
                            <span style="color:#047857; font-weight:600;">{{ $bl->facture->numero_facture }}</span>
                        @else
                            <span style="color:var(--text-3); font-size:12px;">—</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="display:flex; gap:6px;">
                            <a href="{{ $routeVoir }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            @if(!in_array($bl->statut, ['livre', 'facture']))
                            <form method="POST" action="{{ $routeLivrer }}" style="display:inline; margin:0;">
                                @csrf
                                <button type="submit" class="btn btn-sm" style="background:#e0f2fe; color:#0369a1; border:0.5px solid #7dd3fc; font-weight:700; font-size:11px; padding:4px 8px;">
                                    <i class="fas fa-check"></i> Livré
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($bls->hasPages())
        <div style="padding:16px;">{{ $bls->appends(request()->query())->links() }}</div>
        @endif
        @endif
    </div>
</div>
@endsection
