@extends('admin::gabarits.application')
@section('titre', 'Gestion fournisseurs')
@section('topbar_titre', 'Catalogue — Fournisseurs')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-handshake"></i> Liste des fournisseurs</h1>
        <p>{{ $fournisseurs->total() + $fournisseursComptaflow->total() }} fournisseur(s) au total</p>
    </div>
    <div style="display:flex; gap:12px; align-items:center;">
        {{-- Switch bouton --}}
        <div class="vue-toggle" style="display:flex; background:var(--bg2); border:1px solid var(--border); border-radius:8px; overflow:hidden; padding:2px;">
            <button type="button" id="btn-vue-local" onclick="switchVue('local')" class="btn btn-sm btn-primary"
                style="padding:6px 12px; font-size:12px; font-weight:700; border-radius:6px; border:none; cursor:pointer;">
                <i class="fas fa-database"></i> Local ({{ $fournisseurs->total() }})
            </button>
            <button type="button" id="btn-vue-comptaflow" onclick="switchVue('comptaflow')" class="btn btn-sm btn-outline"
                style="padding:6px 12px; font-size:12px; font-weight:700; border-radius:6px; border:none; cursor:pointer; background:transparent; color:var(--text-2);">
                <i class="fas fa-sync"></i> COMPTAFLOW ({{ $fournisseursComptaflow->total() }})
            </button>
        </div>
        <button id="btn-nouveau-fournisseur" class="btn btn-primary" data-modal-open="modalNouveauFournisseur">
            <i class="fas fa-plus"></i> Ajouter un fournisseur
        </button>
    </div>
</div>

<div id="section-local">
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
                        <th>Achats</th>
                        <th>Date d'ajout</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fournisseurs as $f)
                    <tr>
                        <td style="font-weight:600; color:var(--text);">{{ $f->nom }}</td>
                        <td>
                            <span class="badge badge-purple">{{ $f->secteur ?? 'Général' }}</span>
                        </td>
                        <td style="font-family: monospace; font-weight: 700; color: var(--primary);">
                            {{ $f->numero_tiers ?? '—' }}
                            @if(!empty($f->numero_original))
                                <div style="font-size: 10px; color: var(--text-3); font-weight: normal; margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-file-invoice" style="font-size: 9px; opacity: 0.7;"></i> Original: {{ $f->numero_original }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $f->ncc ?? '—' }}</td>
                        <td>{{ $f->rccm ?? '—' }}</td>
                        <td style="font-family: monospace; font-weight: 700;">
                            {{ $f->compte_comptable ?? '401100' }}
                            @php
                                $compteObj = $comptes->firstWhere('numero', $f->compte_comptable ?? '401100');
                            @endphp
                            @if($compteObj && !empty($compteObj->numero_original))
                                <div style="font-size: 10px; color: var(--text-3); font-weight: normal; margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-file-invoice" style="font-size: 9px; opacity: 0.7;"></i> Original: {{ $compteObj->numero_original }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $f->regime_imposition ?? '—' }}</td>
                        <td>{{ $f->telephone ?? '—' }}</td>
                        <td>{{ $f->email ?? '—' }}</td>
                        <td>{{ $f->adresse ?? '—' }}</td>
                        <td>
                            <span class="badge badge-success">{{ $f->achats_count }} bon(s) d'achat</span>
                        </td>
                        <td style="color:var(--text-3);">{{ $f->created_at->format('d/m/Y') }}</td>
                        <td>
                            <button class="btn btn-outline btn-sm btn-modifier-fournisseur"
                                data-id="{{ $f->id }}"
                                data-nom="{{ $f->nom }}"
                                data-secteur="{{ $f->secteur }}"
                                data-telephone="{{ $f->telephone }}"
                                data-email="{{ $f->email }}"
                                data-adresse="{{ $f->adresse }}"
                                data-ncc="{{ $f->ncc }}"
                                data-rccm="{{ $f->rccm }}"
                                data-regime="{{ $f->regime_imposition }}"
                                data-compte="{{ $f->compte_comptable ?? '401100' }}"
                                data-numero="{{ $f->numero_tiers }}"
                                data-source="{{ $f->source }}"
                                style="padding: 5px 10px;">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" style="text-align:center; color:var(--text-3); padding:30px;">
                            Aucun fournisseur local enregistré pour le moment.
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
</div>

<div id="section-comptaflow" style="display:none;">
    @if(empty($entreprise->comptaflow_sync_key))
        <div class="card" style="padding: 30px; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: var(--danger); margin-bottom: 20px; opacity: 0.8;"></i>
            <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 10px;">Liaison COMPTAFLOW non active</h2>
            <div style="max-width: 500px; margin: 0 auto; padding: 20px; border-radius: 8px; background: #fff5f5; border: 1px solid #feb2b2; margin-top: 15px;">
                <p style="font-size: 13px; color: #4a5568; line-height: 1.5;">
                    Veuillez configurer votre clé de synchronisation COMPTAFLOW dans les paramètres de l'entreprise pour activer la liaison et importer les comptes fournisseurs.
                </p>
                <a href="{{ route('admin.entreprise.parametres') }}" class="btn btn-primary" style="margin-top: 15px; display: inline-flex;">
                    <i class="fas fa-cog"></i> Configurer la liaison
                </a>
            </div>
        </div>
    @else
        <div style="background: linear-gradient(135deg, #eff6ff, #f0fdf4); border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px; margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <p style="font-weight:700; color:#1e40af; margin-bottom:4px;"><i class="fas fa-link"></i> Fournisseurs synchronisés depuis COMPTAFLOW</p>
                <p style="font-size: 13px; color: #64748b; margin-bottom:0;">
                    Leurs informations COMPTAFLOW (nom, numéro de tiers, compte collectif) sont protégées en écriture, mais vous pouvez compléter leurs données Selflow manquantes ci-dessous.
                </p>
            </div>
            <button type="button" id="btn-sync-fournisseurs" onclick="lancerSyncComptaflow()" class="btn btn-primary" style="font-weight:700; white-space:nowrap;">
                <i class="fas fa-sync-alt" id="sync-icon-fournisseurs"></i> Synchroniser les tiers
            </button>
        </div>
        <div id="sync-feedback-fournisseurs" style="display:none; margin-bottom: 16px; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 13px;"></div>

        <div class="card">
            <div class="table-wrap">
                @if($fournisseursComptaflow->isEmpty())
                <div style="padding:48px; text-align:center; color:var(--text-3);">
                    <i class="fas fa-handshake" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
                    Aucun fournisseur COMPTAFLOW synchronisé. Cliquez sur <strong>Synchroniser les tiers</strong>.
                </div>
                @else
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
                            <th>Achats</th>
                            <th>Date d'ajout</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fournisseursComptaflow as $f)
                        <tr>
                            <td style="font-weight:600; color:var(--text);">{{ $f->nom }}</td>
                            <td>
                                <span class="badge badge-purple">{{ $f->secteur ?? 'Général' }}</span>
                            </td>
                            <td style="font-family: monospace; font-weight: 700; color: var(--primary);">
                                {{ $f->numero_tiers ?? '—' }}
                                @if(!empty($f->numero_original))
                                    <div style="font-size: 10px; color: var(--text-3); font-weight: normal; margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-file-invoice" style="font-size: 9px; opacity: 0.7;"></i> Original: {{ $f->numero_original }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $f->ncc ?? '—' }}</td>
                            <td>{{ $f->rccm ?? '—' }}</td>
                            <td style="font-family: monospace; font-weight: 700;">
                                {{ $f->compte_comptable ?? '401100' }}
                                @php
                                    $compteObj = $comptes->firstWhere('numero', $f->compte_comptable ?? '401100');
                                @endphp
                                @if($compteObj && !empty($compteObj->numero_original))
                                    <div style="font-size: 10px; color: var(--text-3); font-weight: normal; margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-file-invoice" style="font-size: 9px; opacity: 0.7;"></i> Original: {{ $compteObj->numero_original }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $f->regime_imposition ?? '—' }}</td>
                            <td>{{ $f->telephone ?? '—' }}</td>
                            <td>{{ $f->email ?? '—' }}</td>
                            <td>{{ $f->adresse ?? '—' }}</td>
                            <td>
                                <span class="badge badge-success">{{ $f->achats_count }} bon(s) d'achat</span>
                            </td>
                            <td style="color:var(--text-3);">{{ $f->created_at->format('d/m/Y') }}</td>
                            <td>
                                <button class="btn btn-outline btn-sm btn-modifier-fournisseur"
                                    data-id="{{ $f->id }}"
                                    data-nom="{{ $f->nom }}"
                                    data-secteur="{{ $f->secteur }}"
                                    data-telephone="{{ $f->telephone }}"
                                    data-email="{{ $f->email }}"
                                    data-adresse="{{ $f->adresse }}"
                                    data-ncc="{{ $f->ncc }}"
                                    data-rccm="{{ $f->rccm }}"
                                    data-regime="{{ $f->regime_imposition }}"
                                    data-compte="{{ $f->compte_comptable ?? '401100' }}"
                                    data-numero="{{ $f->numero_tiers }}"
                                    data-source="{{ $f->source }}"
                                    style="padding: 5px 10px;">
                                    <i class="fas fa-edit"></i> Modifier
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($fournisseursComptaflow->hasPages())
                <div style="padding: 16px;">
                    {{ $fournisseursComptaflow->links() }}
                </div>
                @endif
                @endif
            </div>
        </div>
    @endif
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

{{-- Modal Modifier Fournisseur --}}
<div class="modal-overlay" id="modalModifierFournisseur">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Modifier le fournisseur</h3>
            <button class="modal-close" data-modal-close>✕</button>
        </div>
        <form method="POST" id="formModifierFournisseur">
            @csrf
            @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Nom du Fournisseur <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="nom" id="edit_nom" class="form-control" placeholder="Ex: CDCI Distribution" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Secteur d'activité</label>
                    <input type="text" name="secteur" id="edit_secteur" class="form-control" placeholder="Ex: Alimentation, Électronique…">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" id="edit_telephone" class="form-control" placeholder="Ex: +225 27 00 00 00">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse E-mail</label>
                    <input type="email" name="email" id="edit_email" class="form-control" placeholder="Ex: contact@fournisseur.com">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Adresse physique</label>
                    <input type="text" name="adresse" id="edit_adresse" class="form-control" placeholder="Ex: Zone 4, Marcory, Abidjan">
                </div>
                {{-- Informations fiscales & Comptables --}}
                <div style="grid-column:1/-1; padding:12px 14px; background:var(--bg3); border-radius:8px; border:1px solid var(--border); margin-top:4px;">
                    <div style="font-size:11px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
                        <i class="fas fa-file-invoice" style="margin-right:6px;"></i>Informations fiscales &amp; comptables
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">NCC (Nº Compte Contribuable)</label>
                            <input type="text" name="ncc" id="edit_ncc" class="form-control" placeholder="Ex: 2169728N">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">RCCM (Registre de commerce)</label>
                            <input type="text" name="rccm" id="edit_rccm" class="form-control" placeholder="Ex: CI-ABJ-03-2021-B13-05438">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Régime d'imposition</label>
                            <select name="regime_imposition" id="edit_regime_imposition" class="form-control">
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
                            <select name="compte_comptable" id="edit_compte_comptable" class="form-control" required>
                                @foreach($comptes as $compte)
                                    <option value="{{ $compte->numero }}">
                                        {{ $compte->numero }} - {{ $compte->libelle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0; grid-column: 1/-1;">
                            <label class="form-label">N° tiers auxiliaire (ex: 401001)</label>
                            <input type="text" name="numero_tiers" id="edit_numero_tiers" class="form-control" placeholder="Ex: 401001" required>
                        </div>
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
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

function switchVue(mode) {
    const btnLocal      = document.getElementById('btn-vue-local');
    const btnComptaflow = document.getElementById('btn-vue-comptaflow');
    const secLocal      = document.getElementById('section-local');
    const secComptaflow = document.getElementById('section-comptaflow');
    const btnNouveau    = document.getElementById('btn-nouveau-fournisseur');

    if (mode === 'local') {
        btnLocal.className = "btn btn-sm btn-primary";
        btnLocal.style.color = "#fff";
        btnLocal.style.background = "";
        btnComptaflow.className = "btn btn-sm btn-outline";
        btnComptaflow.style.color = "var(--text-2)";
        secLocal.style.display = "block";
        secComptaflow.style.display = "none";
        if(btnNouveau) btnNouveau.style.display = "inline-flex";
    } else {
        btnComptaflow.className = "btn btn-sm btn-primary";
        btnComptaflow.style.color = "#fff";
        btnComptaflow.style.background = "";
        btnLocal.className = "btn btn-sm btn-outline";
        btnLocal.style.color = "var(--text-2)";
        secLocal.style.display = "none";
        secComptaflow.style.display = "block";
        if(btnNouveau) btnNouveau.style.display = "none";
    }
}

// Boutons Modifier via data-* attributes (évite les erreurs de guillemets avec json_encode dans onclick)
document.querySelectorAll('.btn-modifier-fournisseur').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const data  = this.dataset;
        const modal = document.getElementById('modalModifierFournisseur');
        const form  = document.getElementById('formModifierFournisseur');

        if (!modal || !form) return;

        // URL d'action du formulaire
        form.action = '/admin/fournisseurs/' + data.id;

        // Remplir les champs
        document.getElementById('edit_nom').value             = data.nom      || '';
        document.getElementById('edit_secteur').value         = data.secteur  || '';
        document.getElementById('edit_telephone').value       = data.telephone || '';
        document.getElementById('edit_email').value           = data.email    || '';
        document.getElementById('edit_adresse').value         = data.adresse  || '';
        document.getElementById('edit_ncc').value             = data.ncc      || '';
        document.getElementById('edit_rccm').value            = data.rccm     || '';
        document.getElementById('edit_numero_tiers').value    = data.numero   || '';

        const selectRegime = document.getElementById('edit_regime_imposition');
        if (selectRegime) selectRegime.value = data.regime || '';

        const selectCompte = document.getElementById('edit_compte_comptable');
        if (selectCompte) selectCompte.value = data.compte || '401100';

        // Champs COMPTAFLOW → lecture seule
        const lockFields = ['edit_nom', 'edit_numero_tiers'];
        const isCompta   = data.source === 'comptaflow';
        lockFields.forEach(function(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.readOnly         = isCompta;
            el.disabled         = isCompta;
            el.style.background = isCompta ? '#e2e8f0' : '';
            el.style.cursor     = isCompta ? 'not-allowed' : '';
            el.title            = isCompta ? 'Ce champ provient de COMPTAFLOW et ne peut pas être modifié.' : '';
        });
        if (selectCompte) {
            selectCompte.disabled         = isCompta;
            selectCompte.style.background = isCompta ? '#e2e8f0' : '';
            selectCompte.style.cursor     = isCompta ? 'not-allowed' : '';
        }

        // Ouvrir le modal via la classe 'open' (système gabarit Selflow)
        modal.classList.add('open');
    });
});

function lancerSyncComptaflow() {
    const btn      = document.getElementById('btn-sync-fournisseurs');
    const feedback = document.getElementById('sync-feedback-fournisseurs');

    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Synchronisation...';

    fetch("{{ route('admin.entreprise.comptaflow.sync_real') }}", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" }
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Synchroniser les tiers';
        feedback.style.display = "block";
        if (data.success) {
            feedback.style.cssText = "background:#e6fffa; color:#319795; border:1px solid #b2f5ea; padding:12px; border-radius:6px; font-weight:600; font-size:13px;";
            feedback.innerText = data.message;
            setTimeout(() => { window.location.reload(); }, 1200);
        } else {
            feedback.style.cssText = "background:#fff5f5; color:#e53e3e; border:1px solid #fed7d7; padding:12px; border-radius:6px; font-weight:600; font-size:13px;";
            feedback.innerText = "Erreur : " + data.message;
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Synchroniser les tiers';
        feedback.style.display = "block";
        feedback.style.cssText = "background:#fff5f5; color:#e53e3e; border:1px solid #fed7d7; padding:12px; border-radius:6px; font-weight:600; font-size:13px;";
        feedback.innerText = "Erreur de connexion.";
    });
}
</script>
@endsection
