@extends('admin::gabarits.application')
@section('titre', 'Gestion fournisseurs')
@section('topbar_titre', 'Catalogue — Fournisseurs')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-handshake"></i> Liste des fournisseurs</h1>
        <p>{{ $fournisseurs->total() }} fournisseur(s) enregistré(s)</p>
    </div>
    <button class="btn btn-primary" data-modal-open="modalNouveauFournisseur">
        <i class="fas fa-plus"></i> Ajouter un fournisseur
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom du fournisseur</th>
                    <th>Secteur d'activité</th>
                    <th>NCC</th>
                    <th>Régime</th>
                    <th>Téléphone</th>
                    <th>E-mail</th>
                    <th>Adresse</th>
                    <th>Nombre d'achats</th>
                    <th>Date d'ajout</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fournisseurs as $f)
                <tr>
                    <td style="font-weight:600; color:var(--text);">{{ $f->nom }}</td>
                    <td>
                        <span class="badge badge-purple">{{ $f->secteur ?? 'Général' }}</span>
                    </td>
                    <td>{{ $f->ncc ?? '—' }}</td>
                    <td>{{ $f->regime_imposition ?? '—' }}</td>
                    <td>{{ $f->telephone ?? '—' }}</td>
                    <td>{{ $f->email ?? '—' }}</td>
                    <td>{{ $f->adresse ?? '—' }}</td>
                    <td>
                        <span class="badge badge-success">{{ $f->achats_count }} bon(s) d'achat</span>
                    </td>
                    <td style="color:var(--text-3);">{{ $f->created_at->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center; color:var(--text-3); padding:30px;">
                        Aucun fournisseur enregistré pour le moment.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($fournisseurs->hasPages())
        <div style="padding: 16px;">
            {{ $fournisseurs->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Modal Nouveau Fournisseur --}}
<div class="modal-overlay" id="modalNouveauFournisseur">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Nouveau fournisseur</h3>
            <button class="modal-close" data-modal-close>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.fournisseurs.creer') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Nom du Fournisseur <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="nom" class="form-control" placeholder="Ex: CDCI Distribution" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Secteur d'activité</label>
                    <input type="text" name="secteur" class="form-control" placeholder="Ex: Alimentation, Électronique…">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control" placeholder="Ex: +225 27 00 00 00">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse E-mail</label>
                    <input type="email" name="email" class="form-control" placeholder="Ex: contact@fournisseur.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse physique</label>
                    <input type="text" name="adresse" class="form-control" placeholder="Ex: Zone 4, Marcory, Abidjan">
                </div>
                {{-- Informations fiscales --}}
                <div style="grid-column:1/-1; padding:12px 14px; background:var(--bg3); border-radius:8px; border:1px solid var(--border); margin-top:4px;">
                    <div style="font-size:11px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
                        <i class="fas fa-file-invoice" style="margin-right:6px;"></i>Informations fiscales (optionnel)
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">NCC (Nº Compte Contribuable)</label>
                            <input type="text" name="ncc" class="form-control" placeholder="Ex: 2169728N">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Régime d'imposition</label>
                            <select name="regime_imposition" class="form-control">
                                <option value="">— Non renseigné —</option>
                                <option value="TEE">TEE (Taxe sur l'Entreprise Employeuse)</option>
                                <option value="RS">RS (Régime Simplifié)</option>
                                <option value="RSI">RSI (Régime Simplifié d'Imposition)</option>
                                <option value="RNI">RNI (Régime Normal d'Imposition)</option>
                                <option value="Exonéré">Exonéré</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Créer le fournisseur</button>
            </div>
        </form>
    </div>
</div>
@endsection
