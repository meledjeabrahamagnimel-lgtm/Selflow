@extends('admin::gabarits.application')
@section('titre', 'Détails du personnel')
@section('topbar_titre', 'Personnels & Accès — Fiche individuelle')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-gear"></i> Fiche de {{ $personnel->nom }} {{ $personnel->prenom }}</h1>
        <p>Consultez les informations de contrat, gérez les habilitations de pages et modifiez ses accès.</p>
    </div>
    <a href="{{ route('admin.personnel.index') }}" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Retour à la liste
    </a>
</div>

<div class="grid-3-1">
    {{-- COLONNE GAUCHE : FORMULAIRE ET HABILITATIONS --}}
    <div style="display:flex; flex-direction:column; gap:22px;">
        <form method="POST" action="{{ route('admin.personnel.modifier', $personnel) }}" class="card">
            @csrf
            @method('PUT')
            
            <div class="card-header">
                <h2>Informations de compte & Accès</h2>
            </div>
            
            <div class="card-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label class="form-label">Nom <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="nom" class="form-control" value="{{ old('nom', $personnel->nom) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prénom <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="prenom" class="form-control" value="{{ old('prenom', $personnel->prenom) }}" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label class="form-label">Email de connexion <span style="color:var(--danger)">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $personnel->email) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nouveau mot de passe <span style="font-size:10px; text-transform:none; color:var(--text-3)">(Laisser vide si inchangé)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Min 6 caractères">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label class="form-label">Rôle <span style="color:var(--danger)">*</span></label>
                        <select name="role" id="role-select" class="form-control" required onchange="verifierRoleChange()">
                            <option value="caissier" @if($personnel->role === 'caissier') selected @endif>Caissier (Restreint)</option>
                            <option value="admin" @if($personnel->role === 'admin') selected @endif>Administrateur (Tous les droits)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Point de Vente affecté</label>
                        <select name="point_de_vente_id" class="form-control">
                            <option value="">Aucun (Siège)</option>
                            @foreach($pointsDeVente as $pdv)
                                <option value="{{ $pdv->id }}" @if($personnel->point_de_vente_id == $pdv->id) selected @endif>{{ $pdv->nom }} ({{ $pdv->ville }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label class="form-label">Fonction professionnelle</label>
                        <input type="text" name="fonction" class="form-control" value="{{ old('fonction', $personnel->fonction) }}" placeholder="Ex: Caissière Senior">
                    </div>
                    <div class="form-group" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label class="form-label" style="font-size:10px;">Début Contrat</label>
                            <input type="date" name="date_debut_contrat" class="form-control" value="{{ old('date_debut_contrat', $personnel->date_debut_contrat) }}">
                        </div>
                        <div>
                            <label class="form-label" style="font-size:10px;">Fin Contrat</label>
                            <input type="date" name="date_fin_contrat" class="form-control" value="{{ old('date_fin_contrat', $personnel->date_fin_contrat) }}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes RH / Suivi interne</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Saisir des notes de suivi sur le personnel...">{{ old('notes', $personnel->notes) }}</textarea>
                </div>

                {{-- Habilitations matrix checkboxes --}}
                <div id="habilitations-container" style="border:1px solid var(--border); border-radius:8px; padding:18px; background:#F8FAFC; margin-bottom:16px;">
                    <div style="font-weight:700; font-size:13px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fas fa-shield-halved" style="color:var(--primary);"></i> Habilitations d'accès aux pages</span>
                        <label style="font-weight:500; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:6px;">
                            <input type="checkbox" id="check-all-habs" onchange="toggleToutCocher(this)"> Tout cocher
                        </label>
                    </div>
                    
                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px;" id="habs-checkboxes-grid">
                        @php
                            $userHabs = $personnel->habilitations ?? [];
                        @endphp
                        <div>
                            <div style="font-weight:600; font-size:11px; text-transform:uppercase; color:var(--text-2); margin-bottom:6px; border-bottom:1px solid var(--border); padding-bottom:3px;">Principal</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="tableau_de_bord" @if(in_array('tableau_de_bord', $userHabs)) checked @endif> Tableau de bord
                            </label>
                        </div>

                        <div>
                            <div style="font-weight:600; font-size:11px; text-transform:uppercase; color:var(--text-2); margin-bottom:6px; border-bottom:1px solid var(--border); padding-bottom:3px;">Ventes</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="nouvelle_vente" @if(in_array('nouvelle_vente', $userHabs)) checked @endif> Nouvelle vente
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="factures_vente" @if(in_array('factures_vente', $userHabs)) checked @endif> Factures vente
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="historique_ventes" @if(in_array('historique_ventes', $userHabs)) checked @endif> Historique ventes
                            </label>
                        </div>

                        <div>
                            <div style="font-weight:600; font-size:11px; text-transform:uppercase; color:var(--text-2); margin-bottom:6px; border-bottom:1px solid var(--border); padding-bottom:3px;">Achats</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="nouvel_achat" @if(in_array('nouvel_achat', $userHabs)) checked @endif> Nouvel achat
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="factures_achat" @if(in_array('factures_achat', $userHabs)) checked @endif> Factures achat
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="historique_achats" @if(in_array('historique_achats', $userHabs)) checked @endif> Historique achats
                            </label>
                        </div>

                        <div>
                            <div style="font-weight:600; font-size:11px; text-transform:uppercase; color:var(--text-2); margin-bottom:6px; border-bottom:1px solid var(--border); padding-bottom:3px;">Stock</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="stock_articles" @if(in_array('stock_articles', $userHabs)) checked @endif> Articles & stock
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="stock_mouvements" @if(in_array('stock_mouvements', $userHabs)) checked @endif> Mouvements stock
                            </label>
                        </div>

                        <div>
                            <div style="font-weight:600; font-size:11px; text-transform:uppercase; color:var(--text-2); margin-bottom:6px; border-bottom:1px solid var(--border); padding-bottom:3px;">Trésorerie</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="tresorerie_encaissements" @if(in_array('tresorerie_encaissements', $userHabs)) checked @endif> Encaissements
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="tresorerie_decaissements" @if(in_array('tresorerie_decaissements', $userHabs)) checked @endif> Décaissements
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="tresorerie_journal" @if(in_array('tresorerie_journal', $userHabs)) checked @endif> Solde & journal
                            </label>
                        </div>

                        <div>
                            <div style="font-weight:600; font-size:11px; text-transform:uppercase; color:var(--text-2); margin-bottom:6px; border-bottom:1px solid var(--border); padding-bottom:3px;">Configuration</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="gestion_pdv" @if(in_array('gestion_pdv', $userHabs)) checked @endif> Points de vente
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="gestion_personnel" @if(in_array('gestion_personnel', $userHabs)) checked @endif> Personnel & accès
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="gestion_habilitations" @if(in_array('gestion_habilitations', $userHabs)) checked @endif> Habilitations
                            </label>
                        </div>

                        <div>
                            <div style="font-weight:600; font-size:11px; text-transform:uppercase; color:var(--text-2); margin-bottom:6px; border-bottom:1px solid var(--border); padding-bottom:3px;">Catalogue & Tiers</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="catalogue_produits" @if(in_array('catalogue_produits', $userHabs)) checked @endif> Produits
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="tiers_clients" @if(in_array('tiers_clients', $userHabs)) checked @endif> Clients
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="tiers_fournisseurs" @if(in_array('tiers_fournisseurs', $userHabs)) checked @endif> Fournisseurs
                            </label>
                        </div>

                        <div>
                            <div style="font-weight:600; font-size:11px; text-transform:uppercase; color:var(--text-2); margin-bottom:6px; border-bottom:1px solid var(--border); padding-bottom:3px;">Analyses</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:5px; cursor:pointer;">
                                <input type="checkbox" name="habilitations[]" value="rapports_analyse" @if(in_array('rapports_analyse', $userHabs)) checked @endif> Analyse d'activité
                            </label>
                        </div>
                    </div>
                    
                    <div id="admin-notice" style="display:none; color:var(--info); font-size:12.5px; font-weight:500;">
                        <i class="fas fa-circle-info"></i> Les administrateurs disposent de tous les privilèges et accès par défaut. Il n'est pas nécessaire de configurer individuellement leurs habilitations.
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- COLONNE DROITE : CARD STATUT & RESUMÉ --}}
    <div style="display:flex; flex-direction:column; gap:22px;">
        <div class="card">
            <div class="card-header">
                <h2>Statut d'accès</h2>
            </div>
            <div class="card-body" style="text-align:center;">
                <div style="font-size:42px; margin-bottom:10px;">
                    @if($personnel->statut === 'actif')
                        <i class="fas fa-circle-check" style="color:var(--success);"></i>
                    @else
                        <i class="fas fa-user-slash" style="color:var(--danger);"></i>
                    @endif
                </div>
                <div style="font-size:16px; font-weight:700; text-transform:uppercase; margin-bottom:16px;">
                    Accès {{ $personnel->statut === 'actif' ? 'Actif' : 'Bloqué' }}
                </div>
                
                <form method="POST" action="{{ route('admin.personnel.statut', $personnel) }}" style="width:100%;">
                    @csrf
                    @if($personnel->statut === 'actif')
                        <button type="submit" class="btn btn-danger" style="width:100%; justify-content:center;">
                            <i class="fas fa-ban"></i> Suspendre le compte
                        </button>
                    @else
                        <button type="submit" class="btn btn-success" style="width:100%; justify-content:center;">
                            <i class="fas fa-circle-check"></i> Activer le compte
                        </button>
                    @endif
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Synthèse contrat</h2>
            </div>
            <div class="card-body" style="font-size:13px; display:flex; flex-direction:column; gap:10px;">
                <div>
                    <span style="font-weight:600; color:var(--text-2);">Poste :</span>
                    <span>{{ $personnel->fonction ?? 'Non spécifié' }}</span>
                </div>
                <div>
                    <span style="font-weight:600; color:var(--text-2);">Début Contrat :</span>
                    <span>
                        @if($personnel->date_debut_contrat)
                            {{ \Carbon\Carbon::parse($personnel->date_debut_contrat)->format('d F Y') }}
                        @else
                            <em style="color:var(--text-3);">Non défini</em>
                        @endif
                    </span>
                </div>
                <div>
                    <span style="font-weight:600; color:var(--text-2);">Fin Contrat :</span>
                    <span>
                        @if($personnel->date_fin_contrat)
                            {{ \Carbon\Carbon::parse($personnel->date_fin_contrat)->format('d F Y') }}
                        @else
                            <span style="color:var(--success);">Indéterminé (CDI)</span>
                        @endif
                    </span>
                </div>
                <div style="border-top:1px solid var(--border); padding-top:10px; margin-top:5px;">
                    <span style="font-weight:600; color:var(--text-2);">Date de création :</span>
                    <div style="font-size:11px; color:var(--text-3); margin-top:3px;">
                        Créé le {{ $personnel->created_at->format('d/m/Y \à H:i') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function toggleToutCocher(source) {
        const checkboxes = document.querySelectorAll('#habs-checkboxes-grid input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = source.checked);
    }

    function verifierRoleChange() {
        const role = document.getElementById('role-select').value;
        const grid = document.getElementById('habs-checkboxes-grid');
        const notice = document.getElementById('admin-notice');
        const selectAllLabel = document.querySelector('label[for="check-all-habs"]') || document.getElementById('check-all-habs').closest('label');

        if (role === 'admin') {
            grid.style.opacity = '0.4';
            grid.style.pointerEvents = 'none';
            notice.style.display = 'block';
            if (selectAllLabel) selectAllLabel.style.display = 'none';
        } else {
            grid.style.opacity = '1';
            grid.style.pointerEvents = 'auto';
            notice.style.display = 'none';
            if (selectAllLabel) selectAllLabel.style.display = 'flex';
        }
    }

    // Appel initial au chargement
    document.addEventListener('DOMContentLoaded', () => {
        verifierRoleChange();
    });
</script>
@endsection
