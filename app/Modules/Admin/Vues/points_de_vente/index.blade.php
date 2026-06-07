@extends('admin::gabarits.application')
@section('titre', 'Points de vente')
@section('topbar_titre', 'Infrastructure — Points de vente')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-store"></i> Points de vente</h1>
        <p>{{ $pointsDeVente->count() }} / {{ $quotaMax }} points de vente utilisés</p>
    </div>
    @if($pointsDeVente->count() < $quotaMax)
    <button class="btn btn-primary" data-modal-open="modalNouveauPdv">
        <i class="fas fa-plus"></i> Nouveau point de vente
    </button>
    @endif
</div>

{{-- Barre de progression quota --}}
<div style="margin-bottom: 22px; background: var(--surface); border:1px solid var(--border); border-radius: var(--radius); padding: 18px 22px;">
    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
        <span style="font-weight:600; font-size:13px;">Quota d'abonnement</span>
        <span style="font-size:13px; color:var(--text-2);">{{ $pointsDeVente->count() }} / {{ $quotaMax }} utilisés</span>
    </div>
    <div style="background:var(--bg3); border-radius:99px; height:8px; overflow:hidden;">
        @php $pct = $quotaMax > 0 ? ($pointsDeVente->count() / $quotaMax) * 100 : 0; @endphp
        <div style="height:100%; width:{{ $pct }}%; background: linear-gradient(90deg, var(--primary), #818cf8); border-radius:99px; transition: width .4s;"></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px;">
    @foreach($pointsDeVente as $pdv)
    <div class="card" style="transition: transform .2s;">
        <div class="card-body">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:14px;">
                <div>
                    <div style="font-size:16px; font-weight:800;">{{ $pdv->nom }}</div>
                    <div style="font-size:12px; color:var(--text-3); margin-top:3px;">
                        <i class="fas fa-location-dot"></i> {{ $pdv->commune }}, {{ $pdv->ville }}
                    </div>
                </div>
                @if($pdv->statut === 'Ouvert')
                    <span class="badge badge-success">Ouvert</span>
                @else
                    <span class="badge badge-gray">Fermé</span>
                @endif
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div style="background:var(--bg3); border-radius:8px; padding:12px; text-align:center;">
                    <div style="font-size:20px; font-weight:800; color:var(--primary);">{{ $pdv->ventes_count }}</div>
                    <div style="font-size:11px; color:var(--text-3);">Ventes totales</div>
                </div>
                <div style="background:var(--bg3); border-radius:8px; padding:12px; text-align:center;">
                    <div style="font-size:20px; font-weight:800; color:var(--success);">{{ $pdv->utilisateurs_count }}</div>
                    <div style="font-size:11px; color:var(--text-3);">Utilisateurs</div>
                </div>
            </div>

            <div style="font-size:13px; color:var(--text-2); margin-bottom:16px;">
                @if($pdv->responsable)
                <div><i class="fas fa-user" style="width:16px;"></i> {{ $pdv->responsable }}</div>
                @endif
                @if($pdv->telephone)
                <div style="margin-top:4px;"><i class="fas fa-phone" style="width:16px;"></i> {{ $pdv->telephone }}</div>
                @endif
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <form method="POST" action="{{ route('admin.pdv.activer', $pdv) }}">
                    @csrf
                    @if(session('point_de_vente_actif_id') == $pdv->id && !session()->has('apercu_pdv_id'))
                        <button type="button" class="btn btn-success" style="width:100%; justify-content:center;" disabled>
                            <i class="fas fa-check-circle"></i> Point de vente actif
                        </button>
                    @else
                        <button type="submit" class="btn btn-outline" style="width:100%; justify-content:center;">
                            <i class="fas fa-toggle-on"></i> Activer pour cette session
                        </button>
                    @endif
                </form>

                <form method="POST" action="{{ route('admin.pdv.activer_apercu', $pdv) }}">
                    @csrf
                    @if(session('apercu_pdv_id') == $pdv->id)
                        <button type="button" class="btn btn-success" style="width:100%; justify-content:center; background:#D97706; border-color:#D97706; color:#fff;" disabled>
                            <i class="fas fa-eye"></i> Aperçu actif
                        </button>
                    @else
                        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; background:#6C5CE7; border-color:#6C5CE7; color:#fff;">
                            <i class="fas fa-eye"></i> Apercevoir l'interface caissier
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Modal Nouveau PDV --}}
<div class="modal-overlay" id="modalNouveauPdv">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-store"></i> Nouveau point de vente</h3>
            <button class="modal-close" data-modal-close>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.pdv.creer') }}">
            @csrf
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Nom <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="nom" class="form-control" placeholder="Ex: Agence Nord" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Ville <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="ville" class="form-control" placeholder="Ex: Abidjan" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Commune</label>
                    <input type="text" name="commune" class="form-control" placeholder="Ex: Cocody">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control" placeholder="+225 07 ...">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Responsable</label>
                <input type="text" name="responsable" class="form-control" placeholder="Nom du responsable">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Créer</button>
            </div>
        </form>
    </div>
</div>
@endsection
