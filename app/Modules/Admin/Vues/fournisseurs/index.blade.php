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
                    <th>N° tiers</th>
                    <th>NCC</th>
                    <th>RCCM</th>
                    <th>Compte général</th>
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
                    <td style="font-family: monospace; font-weight: 700; color: var(--primary);">{{ $f->numero_tiers ?? '—' }}</td>
                    <td>{{ $f->ncc ?? '—' }}</td>
                    <td>{{ $f->rccm ?? '—' }}</td>
                    <td style="font-family: monospace; font-weight: 700;">{{ $f->compte_comptable ?? '401100' }}</td>
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
                    <td colspan="12" style="text-align:center; color:var(--text-3); padding:30px;">
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
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Adresse physique</label>
                    <input type="text" name="adresse" class="form-control" placeholder="Ex: Zone 4, Marcory, Abidjan">
                </div>
                {{-- Informations fiscales & Comptables --}}
                <div style="grid-column:1/-1; padding:12px 14px; background:var(--bg3); border-radius:8px; border:1px solid var(--border); margin-top:4px;">
                    <div style="font-size:11px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
                        <i class="fas fa-file-invoice" style="margin-right:6px;"></i>Informations fiscales &amp; comptables
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">NCC (Nº Compte Contribuable)</label>
                            <input type="text" name="ncc" class="form-control" placeholder="Ex: 2169728N">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">RCCM (Registre de commerce)</label>
                            <input type="text" name="rccm" class="form-control" placeholder="Ex: CI-ABJ-03-2021-B13-05438">
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
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Compte comptable général collective</label>
                            <select name="compte_comptable" class="form-control" required>
                                @foreach($comptes as $compte)
                                    <option value="{{ $compte->numero }}" {{ $compte->numero == '401100' ? 'selected' : '' }}>
                                        {{ $compte->numero }} - {{ $compte->libelle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0; grid-column: 1/-1;">
                            <label class="form-label" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                                <span>N° tiers auxiliaire (ex: 401001)</span>
                                <label style="font-weight:normal; display:inline-flex; align-items:center; gap:6px; font-size:11px; cursor:pointer; margin: 0;">
                                    <input type="checkbox" name="auto_numero_tiers" id="auto_numero_fournisseur" value="1" checked onchange="toggleFournisseurNumeroTiers()">
                                    Générer automatiquement
                                </label>
                            </label>
                            <input type="text" name="numero_tiers" id="numero_fournisseur_input" class="form-control" placeholder="Ex: 401001" disabled>
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

<script>
function toggleFournisseurNumeroTiers() {
    const checkbox = document.getElementById('auto_numero_fournisseur');
    const input = document.getElementById('numero_fournisseur_input');
    if (checkbox.checked) {
        input.disabled = true;
        input.value = '';
        input.required = false;
    } else {
        input.disabled = false;
        input.required = true;
        input.focus();
    }
}
</script>
@endsection
