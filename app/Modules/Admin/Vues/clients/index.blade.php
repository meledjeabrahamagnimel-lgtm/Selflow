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
                    <th>Nom &amp; Prénom</th>
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
                @forelse($clients as $c)
                <tr>
                    <td style="font-weight:600; color:var(--text);">{{ $c->nom }}</td>
                    <td style="font-family: monospace; font-weight: 700; color: var(--primary);">{{ $c->numero_tiers ?? '—' }}</td>
                    <td>{{ $c->ncc ?? '—' }}</td>
                    <td>{{ $c->rccm ?? '—' }}</td>
                    <td style="font-family: monospace; font-weight: 700;">{{ $c->compte_comptable ?? '411100' }}</td>
                    <td>{{ $c->regime_imposition ?? '—' }}</td>
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
                    <td colspan="11" style="text-align:center; color:var(--text-3); padding:30px;">
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
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Nouveau client</h3>
            <button class="modal-close" data-modal-close>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.clients.creer') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Nom &amp; Prénom <span style="color:var(--danger)">*</span></label>
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
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Adresse physique</label>
                    <input type="text" name="adresse" class="form-control" placeholder="Ex: Cocody, Abidjan">
                </div>
                {{-- Informations fiscales & Comptables --}}
                <div style="grid-column:1/-1; padding:12px 14px; background:var(--bg3); border-radius:8px; border:1px solid var(--border); margin-top:4px;">
                    <div style="font-size:11px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
                        <i class="fas fa-file-invoice" style="margin-right:6px;"></i>Informations fiscales &amp; comptables
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">NCC (Nº Compte Contribuable)</label>
                            <input type="text" name="ncc" class="form-control" placeholder="Ex: 2302178R">
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
                                    <option value="{{ $compte->numero }}" {{ $compte->numero == '411100' ? 'selected' : '' }}>
                                        {{ $compte->numero }} - {{ $compte->libelle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0; grid-column: 1/-1;">
                            <label class="form-label" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                                <span>N° tiers auxiliaire (ex: 411001)</span>
                                <label style="font-weight:normal; display:inline-flex; align-items:center; gap:6px; font-size:11px; cursor:pointer; margin: 0;">
                                    <input type="checkbox" name="auto_numero_tiers" id="auto_numero_client" value="1" checked onchange="toggleClientNumeroTiers()">
                                    Générer automatiquement
                                </label>
                            </label>
                            <input type="text" name="numero_tiers" id="numero_client_input" class="form-control" placeholder="Ex: 411001" disabled>
                        </div>
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Créer le client</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleClientNumeroTiers() {
    const checkbox = document.getElementById('auto_numero_client');
    const input = document.getElementById('numero_client_input');
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
