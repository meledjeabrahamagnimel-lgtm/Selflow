@extends('admin::gabarits.application')
@section('titre', 'Plan Comptable')
@section('topbar_titre', 'Comptabilité — Plan Comptable')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-book-open"></i> Plan Comptable SYSCOHADA</h1>
        <p>Gérez le plan de comptes généraux de l'entreprise.</p>
    </div>
    <button class="btn btn-primary" data-modal-open="modalNouveauCompte">
        <i class="fas fa-plus"></i> Nouveau compte
    </button>
</div>

{{-- Filtres --}}
<div class="card" style="margin-bottom: 20px; padding: 16px;">
    <form method="GET" action="{{ route('admin.comptabilite.plan_comptable') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 100px; gap: 14px; align-items: flex-end;">
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Classe (Chiffre 1-9)</label>
            <input type="text" name="classe" class="form-control" value="{{ request('classe') }}" placeholder="Ex: 4">
        </div>
        <div class="form-group" style="margin: 0;">
            <label class="form-label">N° de compte</label>
            <input type="text" name="numero" class="form-control" value="{{ request('numero') }}" placeholder="Ex: 411">
        </div>
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Libellé</label>
            <input type="text" name="libelle" class="form-control" value="{{ request('libelle') }}" placeholder="Ex: Client">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px; justify-content: center; flex: 1;"><i class="fas fa-search"></i></button>
            <a href="{{ route('admin.comptabilite.plan_comptable') }}" class="btn btn-outline" style="padding: 10px; justify-content: center;"><i class="fas fa-rotate"></i></a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width: 150px;">Numéro</th>
                    <th>Libellé du compte</th>
                    <th style="width: 120px;">Classe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($comptes as $compte)
                <tr>
                    <td style="font-family: monospace; font-weight: 700; color: var(--primary); font-size: 14px;">{{ $compte->numero }}</td>
                    <td style="font-weight: 600;">{{ $compte->libelle }}</td>
                    <td>
                        <span class="badge badge-purple">Classe {{ substr($compte->numero, 0, 1) }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="text-align: center; color: var(--text-3); padding: 32px;">
                        Aucun compte trouvé correspondant aux critères.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($comptes->hasPages())
        <div style="padding: 16px;">
            {{ $comptes->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Modal Nouveau Compte --}}
<div class="modal-overlay" id="modalNouveauCompte">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Nouveau compte général</h3>
            <button class="modal-close" data-modal-close>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.comptabilite.creer_compte_comptable') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Numéro de compte <span style="color: var(--danger)">*</span></label>
                <input type="text" name="numero" class="form-control" placeholder="Ex: 411100" required>
            </div>
            <div class="form-group">
                <label class="form-label">Libellé du compte <span style="color: var(--danger)">*</span></label>
                <input type="text" name="libelle" class="form-control" placeholder="Ex: Client - Ventes locales" required>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Créer le compte</button>
            </div>
        </form>
    </div>
</div>
@endsection
