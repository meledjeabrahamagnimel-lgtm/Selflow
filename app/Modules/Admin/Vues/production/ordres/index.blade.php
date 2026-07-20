@extends('admin::gabarits.application')
@section('titre', 'Ordres de Production')
@section('topbar_titre', 'Production — Ordres')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-industry" style="color:var(--primary); margin-right:8px;"></i> Ordres de Production</h1>
        <p>Suivez et validez le flux de fabrication industrielle de vos produits.</p>
    </div>
    <a href="{{ route('admin.production.ordres.creer') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Lancer une Production
    </a>
</div>

{{-- Zone d'alertes --}}
@if(session('succes'))
    <div class="alert alert-success" style="margin-bottom:20px;">
        <i class="fas fa-check-circle"></i> {{ session('succes') }}
    </div>
@endif
@if(session('info'))
    <div class="alert alert-warning" style="margin-bottom:20px;">
        <i class="fas fa-info-circle"></i> {{ session('info') }}
    </div>
@endif
@if(session('erreur'))
    <div class="alert alert-danger" style="margin-bottom:20px;">
        <i class="fas fa-exclamation-circle"></i> {{ session('erreur') }}
    </div>
@endif

{{-- Liste d'erreurs de validation serveur (stock insuffisant) --}}
@if(session('erreurs_validation'))
    <div class="alert alert-danger" style="margin-bottom:20px; border-left: 4px solid var(--danger);">
        <h4 style="margin: 0 0 8px 0; font-weight:800; text-transform:uppercase; font-size:12px;">
            <i class="fas fa-exclamation-triangle"></i> Rupture de matières premières détectée :
        </h4>
        <ul style="margin:0; padding-left:20px; font-size:12.5px; line-height:1.6;">
            @foreach(session('erreurs_validation') as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Filtres --}}
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="{{ route('admin.production.ordres.index') }}" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <div style="flex:1; min-width:200px;">
                <select name="statut" class="form-control" style="height:40px;">
                    <option value="">Tous les statuts...</option>
                    <option value="Brouillon" {{ request('statut') === 'Brouillon' ? 'selected' : '' }}>Brouillon</option>
                    <option value="Terminé" {{ request('statut') === 'Terminé' ? 'selected' : '' }}>Terminé</option>
                </select>
            </div>
            <button type="submit" class="btn btn-outline" style="height:40px;">
                <i class="fas fa-filter"></i> Filtrer
            </button>
            @if(request('statut'))
                <a href="{{ route('admin.production.ordres.index') }}" class="btn btn-outline" style="height:40px; color:var(--danger); border-color:var(--danger);">
                    Effacer
                </a>
            @endif
        </form>
    </div>
</div>

{{-- Tableau des OP --}}
<div class="card">
    <div class="table-wrap">
        @if($ordres->isEmpty())
            <div style="padding:60px; text-align:center; color:var(--text-3);">
                <i class="fas fa-industry" style="font-size:48px; display:block; margin-bottom:16px; opacity:.3;"></i>
                Aucun ordre de production en cours.<br>
                Cliquez sur <strong>Lancer une Production</strong> pour démarrer.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Code OP</th>
                        <th style="width: 25%;">Produit Fini</th>
                        <th style="width: 15%;">Point de Vente / Site</th>
                        <th style="width: 15%; text-align: center;">Qté à produire</th>
                        <th style="width: 12%;">Date Fab.</th>
                        <th style="width: 10%; text-align: center;">Statut</th>
                        <th style="width: 13%; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ordres as $ordre)
                        <tr>
                            <td style="font-weight:700; color:var(--primary);">{{ $ordre->code_ordre }}</td>
                            <td style="font-weight:600;">{{ $ordre->produitFini->nom }}</td>
                            <td style="color:var(--text-2);">{{ $ordre->pointDeVente->nom }}</td>
                            <td style="text-align: center; font-weight:700;">
                                {{ number_format($ordre->quantite_cible, 0, ',', ' ') }} {{ $ordre->produitFini->unite ?? 'Unité' }}
                            </td>
                            <td>{{ $ordre->date_production->format('d/m/Y') }}</td>
                            <td style="text-align: center;">
                                @if($ordre->statut === 'Brouillon')
                                    <span class="badge" style="background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:20px; font-weight:700;">Brouillon</span>
                                @elseif($ordre->statut === 'Terminé')
                                    <span class="badge" style="background:#e6fdf5; color:#059669; padding:4px 10px; border-radius:20px; font-weight:700;">Terminé</span>
                                @else
                                    <span class="badge badge-gray">{{ $ordre->statut }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                @if($ordre->statut === 'Brouillon')
                                    <form method="POST" action="{{ route('admin.production.ordres.valider', $ordre) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm" style="font-weight:700; font-size:11px; padding:4px 10px;">
                                            <i class="fas fa-check-circle"></i> Produire & Valider
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size:11px; color:var(--success); font-weight:700;"><i class="fas fa-check"></i> Complété</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding: 10px 16px;">
                {{ $ordres->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
