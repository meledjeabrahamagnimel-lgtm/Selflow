@extends('admin::gabarits.application')
@section('titre', 'Factures — Ventes')
@section('topbar_titre', 'Ventes — Factures')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-file-invoice"></i> Factures de vente</h1>
        <p>{{ $ventes->total() }} facture(s) au total</p>
    </div>
    @php
        $routeNouvelle = request()->routeIs('caissier.*') ? route('caissier.ventes.nouvelle') : route('admin.ventes.nouvelle');
    @endphp
    <a href="{{ $routeNouvelle }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvelle vente
    </a>
</div>

@if(session('succes'))
<div class="alert alert-success" style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px; background: #e6fdf5; border: 1px solid #10b981; color: #0f766e; padding: 12px; border-radius: 8px;">
    <i class="fas fa-check-circle" style="color: #10b981;"></i>
    <div>{{ session('succes') }}</div>
</div>
@endif

@if(session('info'))
<div class="alert alert-info" style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px; background: #eff6ff; border: 1px solid #3b82f6; color: #1e3a8a; padding: 12px; border-radius: 8px;">
    <i class="fas fa-info-circle" style="color: #3b82f6;"></i>
    <div>{{ session('info') }}</div>
</div>
@endif

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
                    <th style="white-space: nowrap;">N° Facture</th>
                    <th style="white-space: nowrap;">Date</th>
                    <th style="white-space: nowrap;">Client</th>
                    <th style="white-space: nowrap;">TTC</th>
                    <th style="white-space: nowrap;">Mode paiement</th>
                    <th style="white-space: nowrap;">Étape</th>
                    <th style="white-space: nowrap;">Statut</th>
                    <th style="white-space: nowrap;">Facture Proformat</th>
                    <th style="white-space: nowrap;">Normalisée (DGI)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventes as $vente)
                @php
                    $isCaissier = request()->routeIs('caissier.*');
                    $routeImprimer = $isCaissier ? route('caissier.ventes.imprimer', $vente) : route('admin.ventes.imprimer', $vente);
                    $routeModifier = $isCaissier ? route('caissier.ventes.modifier', $vente) : route('admin.ventes.modifier', $vente);
                    $routeNormaliser = $isCaissier ? route('caissier.ventes.normaliser', $vente) : route('admin.ventes.normaliser', $vente);
                @endphp
                <tr>
                    <td style="font-weight:700; color:var(--primary); white-space: nowrap;">{{ $vente->numero_facture }}</td>
                    <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y') }}</td>
                    <td style="white-space: nowrap;">{{ $vente->client?->nom ?? '— Passage —' }}</td>
                    <td style="font-weight:700; color:var(--text); white-space: nowrap;">{{ number_format($vente->montant_ttc, 0, ',', ' ') }} F</td>
                    <td style="white-space: nowrap;">{{ $vente->mode_paiement }}</td>
                    <td style="white-space: nowrap;">
                        @if($vente->etape === 'Devis')
                            <span class="badge" style="background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:20px; font-weight:700;">Devis</span>
                        @elseif($vente->etape === 'Bon de commande')
                            <span class="badge" style="background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:20px; font-weight:700;">Bon de commande</span>
                        @else
                            <span class="badge" style="background:#e6fdf5; color:#059669; padding:4px 10px; border-radius:20px; font-weight:700;">Facture</span>
                        @endif
                    </td>
                    <td style="white-space: nowrap;">
                        @if($vente->statut === 'Payé')
                            <span class="badge badge-success" style="background:#e6fdf5; color:#0f766e; padding:4px 10px; border-radius:20px; font-weight:700;">Payé</span>
                        @elseif($vente->statut === 'Crédit')
                            <span class="badge badge-danger" style="background:#fef2f2; color:#991b1b; padding:4px 10px; border-radius:20px; font-weight:700;">Crédit</span>
                        @else
                            <span class="badge badge-warning" style="background:#fffbeb; color:#92400e; padding:4px 10px; border-radius:20px; font-weight:700;">Avance</span>
                        @endif
                    </td>
                    <td style="white-space: nowrap;">
                        <div style="display:flex; gap:6px; flex-wrap: nowrap; align-items: center; white-space: nowrap;">
                            <a href="{{ $routeImprimer }}" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size:11px;" title="Voir la facture proformat">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <button type="button" onclick="telechargerDirectement('{{ $routeImprimer }}?download=1')" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size:11px;" title="Télécharger le PDF">
                                <i class="fas fa-download"></i> Télécharger
                            </button>
                            <a href="{{ $routeModifier }}" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size:11px;" title="Modifier la facture">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                            @if(!$vente->normalise)
                            <form method="POST" action="{{ $routeNormaliser }}" style="display:inline;" onsubmit="return confirm('Confirmer la normalisation de la facture {{ $vente->numero_facture }} auprès de la DGI ? (Simulation)');">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm" style="padding: 4px 8px; font-size:11px; background:#10b981; color:#fff; border-color:#10b981;" title="Normaliser la facture">
                                    <i class="fas fa-check-circle"></i> Normaliser
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                    <td style="white-space: nowrap;">
                        @if($vente->normalise)
                        <div style="display:flex; gap:6px; flex-wrap: nowrap; align-items: center; white-space: nowrap;">
                            <a href="{{ $routeImprimer }}" class="btn btn-primary btn-sm" style="padding: 4px 8px; font-size:11px; background:#002B5C; color:#fff;" title="Voir la facture normalisée">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <button type="button" onclick="telechargerDirectement('{{ $routeImprimer }}?download=1')" class="btn btn-primary btn-sm" style="padding: 4px 8px; font-size:11px; background:#002B5C; color:#fff;" title="Télécharger le PDF">
                                <i class="fas fa-download"></i> Télécharger
                            </button>
                        </div>
                        @else
                        <span style="color:var(--text-3); font-size:11.5px; font-style:italic;">Non normalisée</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($ventes->hasPages())
        <div style="padding: 16px;">{{ $ventes->links() }}</div>
        @endif
        @endif
    </div>
</div>

<script>
function telechargerDirectement(url) {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'absolute';
    iframe.style.width = '1024px';
    iframe.style.height = '768px';
    iframe.style.top = '-9999px';
    iframe.style.left = '-9999px';
    iframe.style.border = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    
    // Remove the iframe after 15 seconds to keep DOM clean
    setTimeout(() => {
        iframe.remove();
    }, 15000);
}
</script>
@endsection
