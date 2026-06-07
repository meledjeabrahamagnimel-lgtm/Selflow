@extends('admin::gabarits.application')
@section('titre', 'Gestion clients')
@section('topbar_titre', 'Catalogue — Clients')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-users"></i> Liste des clients</h1>
        <p>{{ $clients->total() }} client(s) enregistré(s)</p>
    </div>
    <button class="btn btn-primary" data-modal-open="modalNouveauClient">
        <i class="fas fa-plus"></i> Ajouter un client
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom & Prénom</th>
                    <th>Téléphone</th>
                    <th>E-mail</th>
                    <th>Adresse</th>
                    <th>Nombre d'achats</th>
                    <th>Date d'ajout</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $c)
                <tr>
                    <td style="font-weight:600; color:var(--text);">{{ $c->nom }}</td>
                    <td>{{ $c->telephone ?? '—' }}</td>
                    <td>{{ $c->email ?? '—' }}</td>
                    <td>{{ $c->adresse ?? '—' }}</td>
                    <td>
                        <span class="badge badge-purple">{{ $c->ventes_count }} achat(s)</span>
                    </td>
                    <td style="color:var(--text-3);">{{ $c->created_at->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:var(--text-3); padding:30px;">
                        Aucun client enregistré pour le moment.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($clients->hasPages())
        <div style="padding: 16px;">
            {{ $clients->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Modal Nouveau Client --}}
<div class="modal-overlay" id="modalNouveauClient">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Nouveau client</h3>
            <button class="modal-close" data-modal-close>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.clients.creer') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Nom & Prénom <span style="color:var(--danger)">*</span></label>
                <input type="text" name="nom" class="form-control" placeholder="Ex: Koffi Amos" required>
            </div>
            <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input type="text" name="telephone" class="form-control" placeholder="Ex: +225 07 00 00 00">
            </div>
            <div class="form-group">
                <label class="form-label">Adresse E-mail</label>
                <input type="email" name="email" class="form-control" placeholder="Ex: koffi@mail.com">
            </div>
            <div class="form-group">
                <label class="form-label">Adresse physique</label>
                <input type="text" name="adresse" class="form-control" placeholder="Ex: Cocody, Abidjan">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Créer le client</button>
            </div>
        </form>
    </div>
</div>
@endsection
