@extends('admin::gabarits.application')

@section('titre', 'Supervision - Créer une entreprise')
@section('topbar_titre', 'Supervision — Créer')

@section('contenu')
<div class="page-header" style="margin-bottom: 20px;">
    <div>
        <h1><i class="fas fa-plus"></i> Nouvelle Entreprise</h1>
        <p>Inscrire une nouvelle structure sur la plateforme</p>
    </div>
    <a href="{{ route('superadmin.entreprises') }}" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
</div>

<form method="POST" action="{{ route('superadmin.entreprises.creer.enregistrer') }}">
    @csrf
    <div style="display:grid; grid-template-columns: 1.6fr 1fr; gap:20px; align-items:start;">
        
        {{-- Fiche d'informations générales --}}
        <div style="display:flex; flex-direction:column; gap:20px;">
            <div class="card" style="padding: 24px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-info-circle" style="color:var(--primary);"></i> Informations générales
                </div>
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <div class="form-group">
                        <label class="form-label">Nom de l'entreprise <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="nom" class="form-control" placeholder="Ex: Maison Dupont SARL" required value="{{ old('nom') }}">
                        @error('nom') <small style="color:var(--danger)">{{ $message }}</small> @enderror
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Forme Juridique</label>
                            <select name="forme_juridique" class="form-control">
                                <option value="">Sélectionner...</option>
                                @foreach(['SARL','SA','SAS','SCI','EI','SASU','Association','GIE','Autre'] as $fj)
                                <option value="{{ $fj }}" {{ old('forme_juridique') == $fj ? 'selected' : '' }}>{{ $fj }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Régime d'Imposition</label>
                            <select name="regime_imposition" class="form-control">
                                <option value="">Sélectionner...</option>
                                @foreach(['Réel Normal','Réel Simplifié','Bénéfice Forfaitaire','Micro-Entreprise','Exonéré'] as $reg)
                                <option value="{{ $reg }}" {{ old('regime_imposition') == $reg ? 'selected' : '' }}>{{ $reg }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Informations Gérant --}}
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Nom du Gérant / Représentant</label>
                            <input type="text" name="gerant_nom" class="form-control" placeholder="Ex: Dupont" value="{{ old('gerant_nom') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prénom du Gérant</label>
                            <input type="text" name="gerant_prenom" class="form-control" placeholder="Ex: Jean" value="{{ old('gerant_prenom') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fonction du Gérant</label>
                        <input type="text" name="gerant_fonction" class="form-control" placeholder="Ex: Directeur Général / Gérant" value="{{ old('gerant_fonction') }}">
                    </div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">E-mail de contact</label>
                            <input type="email" name="email" class="form-control" placeholder="Ex: contact@entreprise.ci" value="{{ old('email') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" placeholder="Ex: +225 07 00 00 00" value="{{ old('telephone') }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adresse physique / Siège social</label>
                        <input type="text" name="adresse" class="form-control" placeholder="Ex: Plateau, Abidjan" value="{{ old('adresse') }}">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">RCCM</label>
                            <input type="text" name="rccm" class="form-control" placeholder="Ex: CI-ABJ-..." value="{{ old('rccm') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">NCC (N° Contribuable)</label>
                            <input type="text" name="ncc" class="form-control" placeholder="Ex: 123456..." value="{{ old('ncc') }}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">N° Compte Contribuable CC</label>
                        <input type="text" name="compte_contribuable" class="form-control" placeholder="Ex: CC-1234..." value="{{ old('compte_contribuable') }}">                    
                    </div>
                </div>
            </div>

            {{-- Fiche de Plan et Quotas --}}
            <div class="card" style="padding: 24px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-money-bill-wave" style="color:var(--primary);"></i> Abonnement & Quotas
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Plan d'abonnement <span style="color:var(--danger)">*</span></label>
                        <select name="plan_abonnement" class="form-control" required>
                            <option value="Starter">Starter</option>
                            <option value="Pro" selected>Pro</option>
                            <option value="Enterprise">Enterprise</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quota Points de Vente max <span style="color:var(--danger)">*</span></label>
                        <input type="number" name="quota_points_de_vente" class="form-control" required min="1" value="5">
                    </div>
                </div>
            </div>
        </div>

        {{-- Configuration du Secteur & Modules --}}
        <div style="display:flex; flex-direction:column; gap:20px;">
            <div class="card" style="padding: 24px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-gears" style="color:var(--primary);"></i> Secteur & Modules Actifs
                </div>
                
                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label">Secteurs d'activité <span style="color:var(--danger)">*</span></label>
                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:var(--text);">
                            <input type="checkbox" name="secteur_activite[]" value="Commercial" checked onchange="ajusterModulesParDefaut()">
                            <span>Commercial / Négoce</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:var(--text);">
                            <input type="checkbox" name="secteur_activite[]" value="Industriel" onchange="ajusterModulesParDefaut()">
                            <span>Industriel / Production</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:var(--text);">
                            <input type="checkbox" name="secteur_activite[]" value="Services" onchange="ajusterModulesParDefaut()">
                            <span>Services pures (Pas de Stock)</span>
                        </label>
                    </div>
                </div>
                
                <details style="margin-top: 15px; border: 1px solid var(--border); border-radius: 12px; background: var(--bg3); overflow: hidden;">
                    <summary style="font-weight: 700; color: var(--primary); cursor: pointer; padding: 14px 16px; background: var(--bg2); display: flex; align-items: center; justify-content: space-between; outline: none; list-style: none; user-select: none;">
                        <span><i class="fas fa-cubes"></i> Configuration des modules (cliquer pour déplier)</span>
                        <i class="fas fa-chevron-down" style="font-size: 11px;"></i>
                    </summary>
                    <div style="padding: 16px; display:flex; flex-direction:column; gap:10px; border-top: 1px solid var(--border);">
                        @php
                            $modules = [
                                'principal'       => ['Tableaux de bord', 'Indicateurs d\'activité consolidés et personnels'],
                                'ventes'          => ['Ventes & POS', 'Caisse tactile et factures clients'],
                                'achats'          => ['Achats', 'Commandes et factures fournisseurs'],
                                'stock'           => ['Stock avancé', 'Inventaire multi-sites, rebuts et transferts'],
                                'production'      => ['Production / Usine', 'Fiches techniques et ordres de fabrication (Industriel)'],
                                'comptabilite'    => ['Comptabilité', 'SYSCOHADA, trésorerie, encaissements/décaissements et écritures automatiques'],
                                'points_de_vente' => ['Points de Vente', 'Gestion de l\'infrastructure multi-boutiques, personnels et habilitations'],
                                'produits'        => ['Produits / Catalogue', 'Catalogue des articles, unités, prix et codes-barres'],
                                'tiers'           => ['Tiers (Clients / Fournisseurs)', 'Répertoire complet des tiers'],
                                'rapports'        => ['Rapports & Analyses', 'Graphiques et statistiques d\'activité'],
                            ];
                        @endphp
                        @foreach($modules as $key => [$title, $desc])
                        <label style="display:flex; align-items:flex-start; gap:10px; padding:10px; background:var(--bg2); border:1px solid var(--border); border-radius:8px; cursor:pointer;">
                            <input type="checkbox" name="modules_actifs[]" value="{{ $key }}" id="mod_{{ $key }}" checked style="margin-top: 3px;">
                            <div>
                                <div style="font-weight:600; font-size:12.5px; color:var(--text);">{{ $title }}</div>
                                <div style="font-size:11px; color:var(--text-3); margin-top:2px;">{{ $desc }}</div>
                            </div>
                        </label>
                        @endforeach

                        {{-- Services système intégrés d'office (B2B & FNE) --}}
                        <input type="hidden" name="modules_actifs[]" value="b2b">
                        <input type="hidden" name="modules_actifs[]" value="fne">
                        
                        <div style="margin-top:10px; padding:12px; background:rgba(0, 43, 92, 0.05); border:1px solid rgba(0, 43, 92, 0.15); border-radius:8px;">
                            <div style="font-size:11.5px; font-weight:700; color:var(--navy); display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-check-double"></i> Services système inclus
                            </div>
                            <p style="font-size:10.5px; color:var(--text-2); margin-top:4px; line-height:1.4;">
                                Les modules de communication inter-entreprises (B2B) et de facturation normalisée (FNE / DGI) sont automatiquement activés par défaut comme services d'infrastructure système.
                            </p>
                        </div>
                    </div>
                </details>
            </div>

            {{-- Section : Liaison COMPTAFLOW --}}
            <div class="card" id="card-comptaflow" style="padding: 24px; border: 2px solid #e8f0fe;">
                <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-link" style="color:#1a73e8;"></i> Liaison COMPTAFLOW
                </div>
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:12px; background:#f8fbff; border:1px solid #c8deff; border-radius:10px; margin-bottom:14px;">
                    <input type="checkbox" id="cb-comptaflow" name="creer_compte_comptaflow" value="1" {{ old('creer_compte_comptaflow') ? 'checked' : '' }} onchange="toggleComptaflow(this)" style="width:18px;height:18px;">
                    <span>
                        <span style="font-weight:700; color:#1a73e8;">Créer simultanément un compte COMPTAFLOW</span><br>
                        <small style="color:var(--text-3);">Le compte comptable de l'entreprise sera créé automatiquement dans COMPTAFLOW.</small>
                    </span>
                </label>

                <div id="comptaflow-fields" style="display:none; flex-direction:column; gap:12px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Mot de passe admin COMPTAFLOW <span style="color:var(--danger)">*</span></label>
                            <input type="password" name="comptaflow_password" id="comptaflow_password" class="form-control" placeholder="Min. 8 caractères" value="">
                            @error('comptaflow_password') <small style="color:var(--danger)">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirmer le mot de passe <span style="color:var(--danger)">*</span></label>
                            <input type="password" name="comptaflow_password_confirmation" class="form-control" placeholder="Répéter le mot de passe">
                        </div>
                    </div>
                    <div style="padding:10px; background:rgba(26,115,232,0.05); border:1px solid rgba(26,115,232,0.2); border-radius:8px;">
                        <div style="font-size:11.5px; color:#1a73e8; display:flex; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> 
                            <span>Les informations de l'entreprise (nom, adresse, contacts, RCCM, NCC) seront transmises automatiquement à COMPTAFLOW.</span>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-weight:700; justify-content:center; gap:8px; border-radius:12px;">
                <i class="fas fa-check-circle" style="font-size:16px;"></i> Créer la structure
            </button>
        </div>
    </div>
</form>

<script>
function ajusterModulesParDefaut() {
    const sectors = Array.from(document.querySelectorAll('input[name="secteur_activite[]"]:checked')).map(el => el.value);
    const checkStock = document.getElementById('mod_stock');
    const checkProd = document.getElementById('mod_production');
    
    if (sectors.includes('Commercial') || sectors.includes('Industriel')) {
        checkStock.checked = true;
    } else {
        checkStock.checked = false;
    }
    
    if (sectors.includes('Industriel')) {
        checkProd.checked = true;
    } else {
        checkProd.checked = false;
    }
}
document.addEventListener('DOMContentLoaded', ajusterModulesParDefaut);

function toggleComptaflow(cb) {
    const fields = document.getElementById('comptaflow-fields');
    fields.style.display = cb.checked ? 'flex' : 'none';
    const pwdInput = document.getElementById('comptaflow_password');
    if (pwdInput) pwdInput.required = cb.checked;
}
// Si la validation a échoué, ré-afficher les champs
document.addEventListener('DOMContentLoaded', function() {
    const cb = document.getElementById('cb-comptaflow');
    if (cb && cb.checked) toggleComptaflow(cb);
});
</script>
@endsection
