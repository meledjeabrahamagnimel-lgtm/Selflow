@extends('admin::gabarits.application')
@section('titre', 'Paramètres de l\'entreprise')
@section('topbar_titre', 'Mon entreprise — Paramètres')

@section('contenu')
    <div class="page-header">
        <div>
            <h1><i class="fas fa-building"></i> Paramètres de l'entreprise</h1>
            <p>Informations légales, fiscales et logos qui apparaissent sur vos factures</p>
        </div>
    </div>

    @if(session('succes'))
        <div class="alert alert-success"
            style="display:flex;align-items:center;gap:10px;padding:14px 18px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;margin-bottom:20px;color:#065f46;font-weight:500;">
            <i class="fas fa-check-circle" style="font-size:16px;color:#10b981;"></i>
            {{ session('succes') }}
        </div>
    @endif

    @if(!$entreprise->estInscriptionComplete())
        <div
            style="display:flex;align-items:center;justify-content:space-between;gap:16px;background:#FFFBEB;border:1px solid #FCD34D;border-radius:12px;padding:16px 20px;margin-bottom:22px;color:#92400E;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:18px;color:#D97706;"><i class="fas fa-triangle-exclamation"></i></span>
                <div>
                    <h4 style="font-weight:700;font-size:14px;margin-bottom:2px;">Inscription incomplète</h4>
                    <p style="font-size:12px;color:#B45309;">Remplissez les champs marqués <span
                            style="color:#DC2626;font-weight:700;">*</span> pour finaliser l'inscription et débloquer toutes les
                        fonctionnalités.</p>
                </div>
            </div>
            <span
                style="background:#FCD34D;color:#92400E;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;">{{ $entreprise->nom === '[PENDING_ONBOARDING]' ? 'Démarrage' : 'En cours' }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.entreprise.parametres.enregistrer') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

            {{-- Colonne gauche --}}
            <div style="display:flex;flex-direction:column;gap:20px;">

                {{-- Informations générales --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-info-circle" style="color:var(--primary);"></i> Informations générales
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        @php
                            $user = Auth::user();
                            // Si l'entreprise vient d'être créée via Google, vider le nom temporaire
                            $nomEntreprise = ($entreprise->nom === '[PENDING_ONBOARDING]') ? '' : $entreprise->nom;
                        @endphp
                        <div class="form-group">
                            <label class="form-label">Nom de l'entreprise <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="nom" class="form-control" value="{{ old('nom', $nomEntreprise) }}"
                                required placeholder="Ex: Commerce Général Ivoirien SARL">
                        </div>

                        {{-- Informations Gérant — pré-remplies depuis les infos de connexion --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Nom du Gérant / Représentant <span
                                        style="color:var(--danger)">*</span></label>
                                <input type="text" name="gerant_nom" class="form-control"
                                    value="{{ old('gerant_nom', $entreprise->gerant_nom ?: $user->nom) }}"
                                    placeholder="Ex: Koné">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Prénom du Gérant <span
                                        style="color:var(--danger)">*</span></label>
                                <input type="text" name="gerant_prenom" class="form-control"
                                    value="{{ old('gerant_prenom', $entreprise->gerant_prenom ?: $user->prenom) }}"
                                    placeholder="Ex: Mamadou">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Fonction du Gérant <span
                                        style="color:var(--danger)">*</span></label>
                                <input type="text" name="gerant_fonction" class="form-control"
                                    value="{{ old('gerant_fonction', $entreprise->gerant_fonction) }}"
                                    placeholder="Ex: Directeur Général / Gérant">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">E-mail du gérant</label>
                                <input type="email" class="form-control" value="{{ $user->email }}" disabled
                                    style="background:var(--bg3);cursor:not-allowed;" title="Email du compte connecté">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Adresse physique</label>
                            <input type="text" name="adresse" class="form-control"
                                value="{{ old('adresse', $entreprise->adresse) }}"
                                placeholder="Ex: Cocody, Abidjan, Côte d'Ivoire">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Téléphone</label>
                                <input type="text" name="telephone" class="form-control"
                                    value="{{ old('telephone', $entreprise->telephone) }}"
                                    placeholder="Ex: +225 07 00 00 00">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $entreprise->email) }}"
                                    placeholder="Ex: contact@monentreprise.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                RCCM <span style="color:var(--danger)">*</span>
                                <span style="font-size:10px;color:var(--text-3);font-weight:400;"> — Registre du Commerce et
                                    du Crédit Mobilier</span>
                            </label>
                            <input type="text" name="rccm" class="form-control" value="{{ old('rccm', $entreprise->rccm) }}"
                                placeholder="Ex: CI-ABJ-03-2021-B13-05438">
                        </div>
                    </div>
                </div>

                {{-- Informations fiscales --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-file-invoice" style="color:var(--primary);"></i> Informations fiscales (Lecture
                        seule)
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">NCC — N° Compte Contribuable <span
                                        style="color:var(--danger)">*</span></label>
                                <input type="text" name="ncc" class="form-control"
                                    value="{{ old('ncc', $entreprise->ncc) }}" placeholder="Ex: 2169728N">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Régime d'imposition <span
                                        style="color:var(--danger)">*</span></label>
                                <select name="regime_imposition" class="form-control">
                                    <option value="">— Choisir un régime —</option>
                                    <option value="TEE" {{ old('regime_imposition', $entreprise->regime_imposition) === 'TEE' ? 'selected' : '' }}>TEE — Taxe sur Entreprise Existante</option>
                                    <option value="RNE" {{ old('regime_imposition', $entreprise->regime_imposition) === 'RNE' ? 'selected' : '' }}>RNE — Régime du Négoce et de l'Exportation</option>
                                    <option value="RSI" {{ old('regime_imposition', $entreprise->regime_imposition) === 'RSI' ? 'selected' : '' }}>RSI — Régime Simplifié d'Imposition</option>
                                    <option value="RNI" {{ old('regime_imposition', $entreprise->regime_imposition) === 'RNI' ? 'selected' : '' }}>RNI — Régime Normal d'Imposition</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Centre des impôts <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="centre_impots" class="form-control"
                                value="{{ old('centre_impots', $entreprise->centre_impots) }}"
                                placeholder="Ex: 2 PLATEAUX 3">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Références bancaires</label>
                            <textarea name="ref_bancaire" class="form-control" rows="3"
                                placeholder="Ex: Établissement : SGBCI — N° compte : 00123456789">{{ old('ref_bancaire', $entreprise->ref_bancaire) }}</textarea>
                            <small style="color:var(--text-3);font-size:11px;">Ces informations apparaîtront en bas de vos
                                factures.</small>
                        </div>
                        {{-- === LIAISON COMPTAFLOW === --}}
                        <div
                            style="border:2px solid {{ $entreprise->comptaflow_sync_status === 'active' ? '#10b981' : 'var(--border)' }};border-radius:12px;padding:18px;background:{{ $entreprise->comptaflow_sync_status === 'active' ? '#f0fdf4' : 'var(--bg3)' }};margin-top:4px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                                <div
                                    style="font-size:12px;font-weight:700;color:{{ $entreprise->comptaflow_sync_status === 'active' ? '#065f46' : 'var(--text-2)' }};text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:8px;">
                                    <i class="fas fa-link"
                                        style="color:{{ $entreprise->comptaflow_sync_status === 'active' ? '#10b981' : 'var(--text-3)' }};"></i>
                                    Liaison COMPTAFLOW
                                </div>
                                @if($entreprise->comptaflow_sync_status === 'active')
                                    <span
                                        style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:30px;font-size:11px;font-weight:700;display:flex;align-items:center;gap:5px;">
                                        <span
                                            style="width:7px;height:7px;background:#10b981;border-radius:50%;display:inline-block;animation:pulse 2s infinite;"></span>
                                        Active
                                    </span>
                                @else
                                    <span
                                        style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:30px;font-size:11px;font-weight:700;">
                                        Non configurée
                                    </span>
                                @endif
                            </div>

                            @if($entreprise->comptaflow_sync_status === 'active')
                                <div
                                    style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;font-size:12px;">
                                    <div style="background:white;border-radius:8px;padding:8px 12px;border:1px solid #d1fae5;">
                                        <div style="color:#6b7280;font-size:10px;text-transform:uppercase;letter-spacing:.5px;">
                                            Dernière sync</div>
                                        <div style="font-weight:700;color:#065f46;">
                                            {{ $entreprise->comptaflow_last_sync_at ? \Carbon\Carbon::parse($entreprise->comptaflow_last_sync_at)->format('d/m/Y H:i') : '—' }}
                                        </div>
                                    </div>
                                    <div style="background:white;border-radius:8px;padding:8px 12px;border:1px solid #d1fae5;">
                                        <div style="color:#6b7280;font-size:10px;text-transform:uppercase;letter-spacing:.5px;">
                                            ID COMPTAFLOW</div>
                                        <div style="font-weight:700;color:#065f46;">
                                            #{{ $entreprise->comptaflow_company_id ?? '—' }}</div>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="font-size:12px;margin-bottom:6px;">
                                    Clé de synchronisation COMPTAFLOW
                                    <span style="font-size:10px;color:var(--text-3);font-weight:400;"> — Obtenir depuis
                                        COMPTAFLOW → Configuration → Liaison SELFLOW</span>
                                </label>
                                <input type="text" name="comptaflow_sync_key" class="form-control"
                                    value="{{ old('comptaflow_sync_key', $entreprise->comptaflow_sync_key) }}"
                                    placeholder="Collez ici la clé copiée depuis COMPTAFLOW…"
                                    style="font-family:monospace;font-size:12px;">
                                @if($entreprise->comptaflow_sync_status === 'active')
                                    <small style="color:#10b981;font-size:11px;margin-top:4px;display:block;">
                                        <i class="fas fa-check-circle"></i> Liaison active. Modifier la clé relancera une
                                        re-synchronisation complète.
                                    </small>
                                @else
                                    <small style="color:var(--text-3);font-size:11px;margin-top:4px;display:block;">
                                        <i class="fas fa-info-circle"></i> Copiez la clé depuis COMPTAFLOW pour synchroniser
                                        automatiquement vos tiers, plan comptable et écritures.
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Section DGI & Local ── --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> DGI & Local professionnel
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px;">

                        <div class="form-group">
                            {{-- L'IDU n'est pas exige par l'API FNE : aucun champ du
                            referentiel ne le reprend. L'asterisque laissait croire
                            le contraire et bloquait des entreprises qui n'en ont
                            pas. --}}
                            <label class="form-label">
                                IDU — Identifiant Unique DGI
                            </label>
                            <input type="text" name="idu" class="form-control" value="{{ old('idu', $entreprise->idu) }}"
                                placeholder="Ex: CI-001-2025-A123456">
                            <small style="color:var(--text-3);font-size:11px;">Cet identifiant apparaît sur chaque facture
                                normalisée FNE.</small>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Commune</label>
                                <input type="text" name="commune" class="form-control"
                                    value="{{ old('commune', $entreprise->commune) }}" placeholder="Ex: COCODY">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Quartier</label>
                                <input type="text" name="quartier" class="form-control"
                                    value="{{ old('quartier', $entreprise->quartier) }}"
                                    placeholder="Ex: Angré 8ème Tranche">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Référence Cadastrale</label>
                            <input type="text" name="reference_cadastrale" class="form-control"
                                value="{{ old('reference_cadastrale', $entreprise->reference_cadastrale) }}"
                                placeholder="Ex: Section B, Parcelle 042">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Propriétaire du local professionnel</label>
                            <input type="text" name="proprietaire_local" class="form-control"
                                value="{{ old('proprietaire_local', $entreprise->proprietaire_local) }}"
                                placeholder="Ex: SCI IMMOBILIERE COCODY">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Seuil d'alerte stickers <span style="color:#E53E3E">*</span>
                            </label>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <input type="number" name="sticker_solde_alerte" class="form-control"
                                    value="{{ old('sticker_solde_alerte', $entreprise->sticker_solde_alerte ?? 5) }}"
                                    min="1" max="9999" style="max-width:120px;">
                                <small style="color:var(--text-3);font-size:12px;">sticker(s) restants → notification
                                    d'alerte</small>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ── Compte sur la plateforme FNE ──
                     Pose la question une fois, puis affiche ce qui manque selon
                     la reponse : reporter les informations d'un espace existant,
                     ou rassembler celles qu'il faut pour l'ouvrir. --}}
                @php
                    $aCompteFne = $entreprise->possede_compte_fne;

                    // Informations que la plateforme exige, et qui doivent
                    // concorder avec celles de l'espace FNE : ce sont elles que
                    // le payload de certification transporte.
                    $infosFne = [
                        ['champ' => 'Raison sociale', 'valeur' => $entreprise->nom !== '[PENDING_ONBOARDING]' ? $entreprise->nom : null,
                         'note'  => 'Transmise comme « établissement » à chaque certification.'],
                        ['champ' => 'NCC — Numéro de Compte Contribuable', 'valeur' => $entreprise->ncc,
                         'note'  => 'Identifie l\'entreprise auprès de la plateforme. Sans lui, rien n\'est certifié.'],
                        ['champ' => 'Régime d\'imposition', 'valeur' => $entreprise->regime_imposition,
                         'note'  => 'Détermine le code de TVA appliqué aux articles exonérés (TVAC ou TVAD).'],
                        ['champ' => 'RCCM', 'valeur' => $entreprise->rccm,
                         'note'  => 'Registre du Commerce et du Crédit Mobilier, exigé à l\'inscription.'],
                        ['champ' => 'Centre des impôts', 'valeur' => $entreprise->centre_impots,
                         'note'  => 'Celui dont dépend l\'entreprise ; figure sur vos documents fiscaux.'],
                        ['champ' => 'Adresse de l\'établissement', 'valeur' => $entreprise->adresse,
                         'note'  => 'Adresse physique du siège, telle que déclarée à la DGI.'],
                        ['champ' => 'Téléphone', 'valeur' => $entreprise->telephone,
                         'note'  => 'Contact de l\'entreprise.'],
                        ['champ' => 'Adresse e-mail', 'valeur' => $entreprise->email,
                         'note'  => 'Reçoit les notifications de la plateforme à chaque facture émise.'],
                        ['champ' => 'Gérant : nom, prénom et fonction', 'valeur' => trim(($entreprise->gerant_nom ?? '') . ' ' . ($entreprise->gerant_prenom ?? '')) ?: null,
                         'note'  => 'Représentant légal déclaré.'],
                        ['champ' => 'Points de vente', 'valeur' => $entreprise->pointsDeVente()->count() > 0 ? $entreprise->pointsDeVente()->count() . ' déclaré(s)' : null,
                         'note'  => 'Leur nom doit être identique des deux côtés : la FNE refuse une facture dont le point de vente lui est inconnu.'],
                    ];
                    $manquants = collect($infosFne)->filter(fn ($i) => blank($i['valeur']))->count();
                @endphp

                <div class="card" style="padding:24px;">
                    <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-id-card-clip" style="color:var(--primary);"></i> Compte sur la plateforme FNE
                    </div>

                    <p style="font-size:12px;color:var(--text-3);line-height:1.6;margin-bottom:14px;">
                        Selflow établit vos factures, la plateforme FNE les certifie. Les deux
                        doivent connaître la même entreprise, sous les mêmes noms.
                    </p>

                    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
                        <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:8px;border:1px solid {{ $aCompteFne === true ? 'var(--primary)' : 'var(--border)' }};background:{{ $aCompteFne === true ? '#eff6ff' : 'var(--bg3)' }};cursor:pointer;font-size:13px;font-weight:600;">
                            <input type="radio" name="possede_compte_fne" value="1" {{ $aCompteFne === true ? 'checked' : '' }} onchange="this.form.requestSubmit ? null : null">
                            J'ai déjà un compte FNE
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:8px;border:1px solid {{ $aCompteFne === false ? 'var(--primary)' : 'var(--border)' }};background:{{ $aCompteFne === false ? '#eff6ff' : 'var(--bg3)' }};cursor:pointer;font-size:13px;font-weight:600;">
                            <input type="radio" name="possede_compte_fne" value="0" {{ $aCompteFne === false ? 'checked' : '' }}>
                            Je n'en ai pas encore
                        </label>
                    </div>

                    @if($aCompteFne === false)
                        <div style="padding:12px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:12.5px;color:#1e40af;line-height:1.7;margin-bottom:14px;">
                            <strong>Nous nous chargeons de l'inscription pour vous.</strong>
                            Renseignez simplement les informations ci-dessous : ce sont celles
                            que la DGI exige pour ouvrir un compte, et elles serviront à créer
                            votre espace FNE.
                            <br>
                            Les éléments qui n'existent pas encore — clé API, numéro de compte
                            attribué par la plateforme — seront complétés une fois le compte
                            ouvert. Vous n'avez aucune démarche à faire de votre côté.
                        </div>
                    @elseif($aCompteFne === true)
                        <div style="padding:12px 14px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;font-size:12.5px;color:#92400e;line-height:1.7;margin-bottom:14px;">
                            <strong>Reportez ici les informations de votre espace FNE, à l'identique.</strong>
                            Un écart — une raison sociale abrégée, un point de vente nommé
                            autrement — et la plateforme rejette la facture ou la certifie sous
                            un autre libellé que le vôtre.
                        </div>
                    @endif

                    @if($aCompteFne !== null)
                        <div style="font-size:12px;font-weight:700;color:var(--text-2);margin-bottom:10px;">
                            Informations requises
                            @if($manquants > 0)
                                <span style="font-weight:600;color:#b45309;">— {{ $manquants }} à compléter</span>
                            @else
                                <span style="font-weight:600;color:#047857;">— toutes renseignées</span>
                            @endif
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            @foreach($infosFne as $info)
                                @php $renseigne = filled($info['valeur']); @endphp
                                <div style="display:flex;gap:10px;align-items:flex-start;padding:9px 11px;border-radius:8px;background:{{ $renseigne ? '#f8fafc' : '#fffbeb' }};border:1px solid {{ $renseigne ? 'var(--border)' : '#fde68a' }};">
                                    <i class="fas {{ $renseigne ? 'fa-circle-check' : 'fa-circle-exclamation' }}" style="color:{{ $renseigne ? '#10b981' : '#d97706' }};font-size:13px;margin-top:2px;"></i>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-size:12.5px;font-weight:600;color:var(--text);">
                                            {{ $info['champ'] }}
                                            @if($renseigne)
                                                <span style="font-weight:500;color:var(--text-3);"> · {{ \Illuminate\Support\Str::limit($info['valeur'], 40) }}</span>
                                            @endif
                                        </div>
                                        <div style="font-size:11.5px;color:var(--text-3);line-height:1.5;margin-top:1px;">{{ $info['note'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p style="font-size:11.5px;color:var(--text-3);line-height:1.6;margin-top:12px;">
                            La clé API n'est pas saisie ici : elle est enregistrée par
                            l'administrateur Selflow une fois délivrée par la DGI.
                        </p>
                    @endif
                </div>

                {{-- ── Procédure de conformité FNE ──
                Ce qui suit n'est pas un rappel decoratif : chaque point
                correspond a un cas ou une facture part chez la DGI avec
                des montants ou des mentions differents de ceux etablis
                ici. On les a tous rencontres a la mise au point. --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-clipboard-check" style="color:var(--primary);"></i> Procédure à suivre — conformité
                        FNE
                    </div>
                    <p style="font-size:12px;color:var(--text-3);line-height:1.6;margin-bottom:16px;">
                        Selflow établit vos factures et les certifient directement aupres de la DGI . Les deux doivent
                        dire la même chose. Ces six points sont ceux qui, s'ils sont négligés,
                        font diverger la facture certifiée de la vôtre.
                    </p>

                    @php
                        // Chaque etape porte son motif : sans lui, l'utilisateur
                        // n'a aucun moyen de juger de ce qu'il risque a la sauter.
                        $etapesConformite = [
                            [
                                'titre' => 'Renseigner l\'identité fiscale complète',
                                'texte' => 'NCC, régime d\'imposition, RCCM et centre des impôts. Le NCC identifie votre entreprise auprès de la plateforme : sans lui, aucune facture n\'est certifiée.',
                                'fait' => !empty($entreprise->ncc) && !empty($entreprise->regime_imposition) && !empty($entreprise->rccm),
                            ],
                            [
                                'titre' => 'Nommer les points de vente à l\'identique',
                                'texte' => 'Le nom saisi dans Selflow est transmis tel quel à la FNE, qui refuse la facture s\'il ne correspond à aucun point de vente déclaré sur votre espace.',
                                'fait' => null,
                            ],
                            [
                                'titre' => 'N\'utiliser que les taux de TVA du barème DGI',
                                'texte' => '18 %, 9 % ou 0 %. La FNE ne reçoit pas un pourcentage mais un code, et applique le taux attaché à ce code : un taux intermédiaire serait taxé à 18 % sur la facture certifiée.',
                                'fait' => null,
                            ],
                            [
                                'titre' => 'Saisir le NCC des clients professionnels',
                                'texte' => 'Une facture B2B sans NCC client est rejetée par la plateforme. Sans NCC, la vente relève du B2C.',
                                'fait' => null,
                            ],
                            [
                                'titre' => 'Reporter ici les options cochées sur votre espace FNE',
                                'texte' => 'Timbre de quittance et BAPA se règlent sur la plateforme, et l\'API ne permet pas de les lire. Si la case ci-dessous est cochée alors qu\'elle ne l\'est pas chez la DGI, vous encaisserez un timbre que la plateforme ne retiendra pas.',
                                'fait' => null,
                            ],
                            [
                                'titre' => 'Surveiller le solde de stickers',
                                'texte' => 'La certification consomme un sticker par pièce. À zéro, plus rien n\'est normalisé. Le solde figure sur la page Gestion FNE, tel que la plateforme l\'a renvoyé à la dernière certification.',
                                'fait' => null,
                            ],
                        ];
                    @endphp

                    <ol
                        style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:12px;counter-reset:etape;">
                        @foreach($etapesConformite as $etape)
                            <li style="display:flex;gap:12px;align-items:flex-start;">
                                <span
                                    style="flex-shrink:0;width:22px;height:22px;border-radius:50%;background:{{ $etape['fait'] === true ? '#d1fae5' : 'var(--bg3)' }};border:1px solid {{ $etape['fait'] === true ? '#6ee7b7' : 'var(--border)' }};display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:{{ $etape['fait'] === true ? '#047857' : 'var(--text-3)' }};margin-top:1px;">
                                    @if($etape['fait'] === true)<i class="fas fa-check"
                                    style="font-size:9px;"></i>@else{{ $loop->iteration }}@endif
                                </span>
                                <div>
                                    <div style="font-weight:600;font-size:13px;color:var(--text);">{{ $etape['titre'] }}</div>
                                    <div style="font-size:12px;color:var(--text-3);line-height:1.6;margin-top:2px;">
                                        {{ $etape['texte'] }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <div
                        style="margin-top:16px;padding:12px 14px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;font-size:12px;color:#92400e;line-height:1.6;">
                        <strong><i class="fas fa-scale-balanced"></i> Droit de timbre de quittance</strong> —
                        barème de l'article 873 du Code général des impôts, appliqué aux règlements
                        en espèces. Il est dû par le client (article 875) : vous le collectez pour l'État.
                        <div
                            style="margin-top:8px;display:grid;grid-template-columns:1fr auto;gap:2px 16px;font-family:ui-monospace,monospace;font-size:11px;">
                            <span>0 – 5 000</span><span style="text-align:right;">0 F</span>
                            <span>5 001 – 100 000</span><span style="text-align:right;">100 F</span>
                            <span>100 001 – 500 000</span><span style="text-align:right;">500 F</span>
                            <span>500 001 – 1 000 000</span><span style="text-align:right;">1 000 F</span>
                            <span>1 000 001 – 5 000 000</span><span style="text-align:right;">2 000 F</span>
                            <span>au-delà de 5 000 000</span><span style="text-align:right;">5 000 F</span>
                        </div>
                        <div style="margin-top:8px;">
                            Ni les avoirs ni les bordereaux d'achat n'en relèvent : le timbre frappe
                            la quittance, l'acte qui constate un encaissement.
                        </div>
                    </div>
                </div>

                {{-- ── Options fiscales ──
                Ces deux cases existent aussi dans les paramètres de la
                plateforme FNE, et c'est là qu'elles font foi. L'API ne les
                expose pas : Selflow ne peut ni les lire ni les modifier.
                Elles servent donc à noter ici l'état constaté chez la DGI,
                en attendant que l'API le communique. --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-check-square" style="color:var(--primary);"></i> Options fiscales
                    </div>

                    <div
                        style="font-size:12px;color:var(--text-3);line-height:1.6;margin-bottom:14px;padding:10px 12px;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;">
                        <i class="fas fa-circle-info" style="color:#2563eb;"></i>
                        Ces options se règlent sur la <strong>plateforme FNE</strong>, et c'est
                        là qu'elles s'appliquent. Reportez ici l'état que vous y avez
                        constaté : l'API ne le communique pas encore.
                    </div>

                    <div style="display:flex;flex-direction:column;gap:14px;">

                        <label
                            style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:14px;background:var(--bg3);border-radius:10px;border:1px solid var(--border);">
                            <input type="checkbox" name="timbre_quittance" value="1" {{ old('timbre_quittance', $entreprise->timbre_quittance) ? 'checked' : '' }}
                                style="margin-top:3px;width:16px;height:16px;cursor:pointer;">
                            <div>
                                <div style="font-weight:600;font-size:13px;color:var(--text);">
                                    Timbre de quittance
                                    <span
                                        style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#1d4ed8;background:#dbeafe;border-radius:4px;padding:1px 6px;margin-left:6px;">Informatif</span>
                                </div>
                                <div style="font-size:12px;color:var(--text-3);margin-top:2px;">
                                    Rappel de l'option cochée sur votre espace FNE. Le timbre est
                                    calculé et appliqué par la DGI seule, à la normalisation :
                                    cocher ou décocher ici ne change aucun montant.
                                </div>
                            </div>
                        </label>

                        <label
                            style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:14px;background:var(--bg3);border-radius:10px;border:1px solid var(--border);">
                            <input type="checkbox" name="bapa" value="1" {{ old('bapa', $entreprise->bapa) ? 'checked' : '' }} style="margin-top:3px;width:16px;height:16px;cursor:pointer;">
                            <div>
                                <div style="font-weight:600;font-size:13px;color:var(--text);">Bordereau d'Achat de Produits
                                    Agricoles (BAPA)</div>
                                <div style="font-size:12px;color:var(--text-3);margin-top:2px;">
                                    Ouvre les bordereaux d'achat auprès de producteurs locaux.
                                    Vérifiez que l'option est également cochée sur votre espace
                                    FNE, sans quoi la plateforme refusera les bordereaux.
                                </div>
                            </div>
                        </label>

                    </div>
                </div>

                {{-- ── Impression des factures ── --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-print" style="color:var(--primary);"></i> Impression des factures
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        <div class="form-group">
                            <label class="form-label">Pied de page des factures</label>
                            <textarea name="pied_de_page_facture" id="piedDePageFactureInput" class="form-control" rows="3"
                                maxlength="248" oninput="majCompteurParametre('piedDePageFacture')"
                                placeholder="Ex: Merci pour votre confiance. Paiement à 30 jours. Pénalités de retard : 1,5% / mois.">{{ old('pied_de_page_facture', $entreprise->pied_de_page_facture) }}</textarea>
                            <small style="color:var(--text-3);font-size:11px;">
                                Ce texte apparaît en bas de chaque facture imprimée et est transmis à la DGI.
                                <span id="piedDePageFactureCompteur">0</span>/248 caractères.
                            </small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Autres mentions légales</label>
                            <textarea name="facture_autres_mentions" id="factureAutresMentionsInput" class="form-control"
                                rows="3" maxlength="248" oninput="majCompteurParametre('factureAutresMentions')"
                                placeholder="Ex: Capital social : 1 000 000 FCFA — Forme juridique : SARL">{{ old('facture_autres_mentions', $entreprise->facture_autres_mentions) }}</textarea>
                            <small style="color:var(--text-3);font-size:11px;">
                                Mentions additionnelles : capital social, forme juridique, etc. Transmises à la DGI.
                                <span id="factureAutresMentionsCompteur">0</span>/248 caractères.
                            </small>
                            <script>
                                function majCompteurParametre(prefixe) {
                                    const champ = document.getElementById(prefixe + 'Input');
                                    const compteur = document.getElementById(prefixe + 'Compteur');
                                    if (champ && compteur) compteur.textContent = champ.value.length;
                                }
                                document.addEventListener('DOMContentLoaded', function () {
                                    majCompteurParametre('piedDePageFacture');
                                    majCompteurParametre('factureAutresMentions');
                                });
                            </script>
                        </div>
                    </div>
                </div>

                {{-- ── Secteurs d'activité ── --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-briefcase" style="color:var(--primary);"></i> Secteurs d'activité <span
                            style="color:var(--danger);">*</span>
                    </div>
                    <p style="font-size:12px;color:var(--text-3);margin-bottom:14px;">Sélectionnez tous les secteurs qui
                        correspondent à votre activité principale.</p>
                    @php
                        $secteursDispo = ['Commercial', 'Industriel', 'Services', 'Agricole', 'Artisanat', 'BTP / Construction', 'Restauration / Hôtellerie', 'Santé', 'Transport / Logistique', 'Technologies / Numérique', 'Éducation / Formation', 'Autre'];
                        $secteursActifs = $entreprise->secteur_activite ?? [];
                    @endphp
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        @foreach($secteursDispo as $secteur)
                            <label
                                style="display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--bg3);border-radius:8px;cursor:pointer;font-size:13px;border:1px solid var(--border);transition:all .15s;"
                                onmouseover="this.style.borderColor='var(--primary)';this.style.background='#EBF2FC'"
                                onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--bg3)'">
                                <input type="checkbox" name="secteurs_activite[]" value="{{ $secteur }}" {{ in_array($secteur, old('secteurs_activite', $secteursActifs)) ? 'checked' : '' }}
                                    style="width:15px;height:15px;cursor:pointer;accent-color:var(--primary);">
                                <span>{{ $secteur }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('secteurs_activite') <small
                    style="color:var(--danger);margin-top:6px;display:block;">{{ $message }}</small> @enderror
                </div>

            </div>{{-- /colonne gauche --}}

            <div style="display:flex;flex-direction:column;gap:20px;">

                {{-- Logos --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-image" style="color:var(--primary);"></i> Logos (affichés sur les factures)
                    </div>

                    {{-- Logo principal --}}
                    <div style="margin-bottom:20px;">
                        <label class="form-label" style="margin-bottom:10px;display:block;">Logo principal de
                            l'entreprise</label>
                        @if($entreprise->logo_path)
                            <div
                                style="margin-bottom:10px;padding:12px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;gap:12px;">
                                <img src="{{ (str_starts_with($entreprise->logo_path, 'http://') || str_starts_with($entreprise->logo_path, 'https://')) ? $entreprise->logo_path : Storage::disk('public')->url($entreprise->logo_path) }}"
                                    alt="Logo entreprise"
                                    style="max-height:60px;max-width:140px;object-fit:contain;border-radius:4px;">
                                <span style="font-size:12px;color:var(--text-2);">Logo actuel</span>
                            </div>
                        @else
                            <div
                                style="margin-bottom:10px;padding:16px;background:var(--bg3);border-radius:8px;text-align:center;border:1.5px dashed var(--border);">
                                <i class="fas fa-image"
                                    style="font-size:28px;color:var(--text-3);margin-bottom:6px;display:block;"></i>
                                <span style="font-size:12px;color:var(--text-3);">Aucun logo défini</span>
                            </div>
                        @endif
                        <input type="file" name="logo" id="logo" class="form-control"
                            accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" style="margin-top:8px;">
                        <small style="color:var(--text-3);font-size:11px;">PNG, JPG ou SVG · Max 2 Mo. Ce logo apparaît en
                            haut à gauche des factures.</small>
                    </div>

                    {{-- Logo FNE / secondaire --}}
                    <div>
                        <label class="form-label" style="margin-bottom:10px;display:block;">Logo secondaire (FNE,
                            certification, etc.)</label>
                        @if($entreprise->logo_fne_path)
                            <div
                                style="margin-bottom:10px;padding:12px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;gap:12px;">
                                <img src="{{ (str_starts_with($entreprise->logo_fne_path, 'http://') || str_starts_with($entreprise->logo_fne_path, 'https://')) ? $entreprise->logo_fne_path : Storage::disk('public')->url($entreprise->logo_fne_path) }}"
                                    alt="Logo FNE"
                                    style="max-height:60px;max-width:140px;object-fit:contain;border-radius:4px;">
                                <span style="font-size:12px;color:var(--text-2);">Logo actuel</span>
                            </div>
                        @else
                            <div
                                style="margin-bottom:10px;padding:16px;background:var(--bg3);border-radius:8px;text-align:center;border:1.5px dashed var(--border);">
                                <i class="fas fa-award"
                                    style="font-size:28px;color:var(--text-3);margin-bottom:6px;display:block;"></i>
                                <span style="font-size:12px;color:var(--text-3);">Aucun logo secondaire défini</span>
                            </div>
                        @endif
                        <input type="file" name="logo_fne" id="logo_fne" class="form-control"
                            accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" style="margin-top:8px;">
                        <small style="color:var(--text-3);font-size:11px;">PNG, JPG ou SVG · Max 2 Mo. Peut être un label
                            qualité, logo FNE, etc.</small>
                    </div>
                </div>

                {{-- Prévisualisation résumé --}}
                <div class="card" style="padding:24px;">
                    <div
                        style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-eye" style="color:var(--primary);"></i> Récapitulatif actuel
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                        @php
                            $infos = [
                                ['NCC', $entreprise->ncc],
                                ['Régime', $entreprise->regime_imposition],
                                ['Centre des impôts', $entreprise->centre_impots],
                                ['RCCM', $entreprise->rccm],
                                ['Téléphone', $entreprise->telephone],
                                ['E-mail', $entreprise->email],
                            ];
                        @endphp
                        @foreach($infos as [$label, $valeur])
                            <div
                                style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:0.5px solid var(--border);">
                                <span style="color:var(--text-2);">{{ $label }}</span>
                                <span style="font-weight:600;color:{{ $valeur ? 'var(--text)' : 'var(--text-3)' }};">
                                    {{ $valeur ?? '— Non renseigné —' }}
                                </span>
                            </div>
                        @endforeach
                        @if($entreprise->ref_bancaire)
                            <div
                                style="padding:8px;background:var(--bg3);border-radius:6px;font-size:12px;color:var(--text-2);margin-top:4px;">
                                <strong>Références bancaires :</strong><br>{{ $entreprise->ref_bancaire }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:20px;gap:10px;">
            <a href="{{ route('admin.tableau_de_bord') }}" class="btn btn-outline">Annuler</a>
            <button type="submit" class="btn btn-primary" style="padding:12px 28px;">
                <i class="fas fa-save"></i> Enregistrer les paramètres
            </button>
        </div>
    </form>

    <div class="card" style="margin-top:24px; padding:24px;">
        <div
            style="font-size:14px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:20px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <i class="far fa-calendar-alt" style="color:var(--primary); font-size:16px;"></i> Gestion des Exercices
            Comptables (Périodes)
        </div>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:32px; align-items:start;">
            {{-- Formulaire de création d'un exercice --}}
            <div style="background:var(--bg3); border-radius:10px; padding:20px; border:1px solid var(--border);">
                <h3 style="font-size:13px; font-weight:700; margin-bottom:14px; color:var(--primary);"><i
                        class="fas fa-plus"></i> Nouvel Exercice</h3>

                <form method="POST" action="{{ route('admin.entreprise.periodes.creer') }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:14px;">
                        <label class="form-label">Date de début <span style="color:var(--danger)">*</span></label>
                        <input type="date" name="date_debut" class="form-control" required value="{{ date('Y-01-01') }}">
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label class="form-label">Date de fin <span style="color:var(--danger)">*</span></label>
                        <input type="date" name="date_fin" class="form-control" required value="{{ date('Y-12-31') }}">
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;">
                        <i class="fas fa-circle-check"></i> Créer l'exercice
                    </button>
                </form>
            </div>

            {{-- Tableau des exercices existants --}}
            <div>
                <h3 style="font-size:13px; font-weight:700; margin-bottom:14px; color:var(--text-2);"><i
                        class="fas fa-list-ul"></i> Exercices enregistrés</h3>
                <div class="table-wrap" style="border:1px solid var(--border);">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Période</th>
                                <th style="text-align: center;">Statut</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($periodes as $p)
                                <tr>
                                    <td><strong>{{ $p->nom }}</strong></td>
                                    <td>
                                        Du {{ \Carbon\Carbon::parse($p->date_debut)->format('d/m/Y') }}
                                        au {{ \Carbon\Carbon::parse($p->date_fin)->format('d/m/Y') }}
                                    </td>
                                    <td style="text-align: center;">
                                        @if($p->estCloture())
                                            <span class="badge badge-danger"
                                                style="background:#fef2f2; color:#991b1b; border:1px solid #fca5a5;"><i
                                                    class="fas fa-lock"></i> Clôturé</span>
                                        @elseif(session('active_periode_id') == $p->id)
                                            <span class="badge badge-success"><i class="fas fa-circle-check"></i> Sélectionné</span>
                                        @elseif($p->est_active)
                                            <span class="badge badge-info">Actif</span>
                                        @else
                                            <span class="badge badge-gray">Inactif</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        @if($p->estCloture())
                                            <span style="font-size:11px; font-weight:600; color:var(--text-3);">Aucune action</span>
                                        @elseif(session('active_periode_id') == $p->id)
                                            <span style="font-size:11px; font-weight:600; color:var(--success);">Actif en
                                                session</span>
                                        @else
                                            <div style="display:flex; gap:6px; justify-content:center; align-items:center;">
                                                <form method="POST" action="{{ route('admin.periods.switch') }}" style="margin:0;">
                                                    @csrf
                                                    <input type="hidden" name="periode_id" value="{{ $p->id }}">
                                                    <button type="submit" class="btn btn-outline btn-sm"
                                                        style="padding: 4px 8px; font-size:11px;">
                                                        <i class="fas fa-right-from-bracket"></i> Basculer
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.entreprise.periodes.cloturer', $p) }}"
                                                    style="margin:0;"
                                                    onsubmit="return confirm('Êtes-vous sûr de vouloir clôturer DEFINITIVEMENT cet exercice ? Toutes les écritures de cette période seront verrouillées et non modifiables.')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline btn-sm"
                                                        style="padding: 4px 8px; font-size:11px; color:var(--danger); border-color:var(--danger);">
                                                        <i class="fas fa-lock"></i> Clôturer
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-3); padding: 20px 0;">
                                        Aucun exercice enregistré.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── INTÉGRATION COMPTAFLOW ─────────────────────────────────────────── --}}
    <div class="card" style="margin-top:24px; padding:24px;">
        <div
            style="font-size:14px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:20px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <i class="fas fa-sync" style="color:var(--primary); font-size:16px;"></i> Intégration COMPTAFLOW &
            Synchronisation bidirectionnelle
        </div>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:32px; align-items:center;">
            <div>
                <div style="font-size:13px; color:var(--text-2); margin-bottom:14px; line-height:1.5;">
                    COMPTAFLOW est la solution comptable connectée à Selflow. Activez la liaison pour synchroniser en temps
                    réel vos factures d'achat, de vente, les encaissements, décaissements et générer automatiquement vos
                    écritures de journal.
                </div>

                <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
                    <div>
                        Statut de liaison :
                        <span id="sync-status-badge"
                            class="badge {{ $entreprise->comptaflow_sync_status === 'Actif' ? 'badge-success' : 'badge-danger' }}">
                            {{ $entreprise->comptaflow_sync_status }}
                        </span>
                    </div>
                    <div>
                        Dernière synchronisation :
                        <strong
                            id="sync-last-time">{{ $entreprise->comptaflow_last_sync_at ? \Carbon\Carbon::parse($entreprise->comptaflow_last_sync_at)->format('d/m/Y \à H:i:s') : 'Jamais synchronisé' }}</strong>
                    </div>
                </div>
            </div>

            <div
                style="background:var(--bg3); border-radius:10px; padding:24px; border:1px solid var(--border); text-align:center;">
                <p style="font-size:13px; font-weight:600; margin-bottom:16px; color:var(--text-1);">Simuler la
                    communication d'API bidirectionnelle</p>

                <div id="sync-feedback"
                    style="display:none; padding:12px; border-radius:8px; font-size:13px; margin-bottom:16px; text-align:left; font-weight:500;">
                </div>

                <button type="button" id="btn-sync-simulation" onclick="lancerSyncSimulation()" class="btn btn-primary"
                    style="margin:0 auto; padding:10px 24px; font-weight:700; gap:8px;">
                    <i class="fas fa-rotate"></i> Lancer la synchronisation test
                </button>
                <span id="sync-loader" style="display:none; font-size:13px; color:var(--text-3); font-weight:600;">
                    <i class="fas fa-spinner fa-spin" style="color:var(--primary); margin-right:8px;"></i> Communication
                    avec COMPTAFLOW en cours...
                </span>
            </div>
        </div>
    </div>

    <script>
        function lancerSyncSimulation() {
            const btn = document.getElementById('btn-sync-simulation');
            const loader = document.getElementById('sync-loader');
            const feedback = document.getElementById('sync-feedback');
            const badge = document.getElementById('sync-status-badge');
            const lastTime = document.getElementById('sync-last-time');

            btn.style.display = 'none';
            loader.style.display = 'inline-flex';
            feedback.style.display = 'none';

            fetch("{{ route('admin.entreprise.comptaflow.sync') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                }
            })
                .then(response => response.json())
                .then(data => {
                    btn.style.display = 'inline-flex';
                    loader.style.display = 'none';

                    if (data.success) {
                        feedback.style.display = 'block';
                        feedback.style.background = '#d1fae5';
                        feedback.style.border = '1px solid #6ee7b7';
                        feedback.style.color = '#065f46';
                        feedback.innerHTML = `<i class="fas fa-circle-check"></i> ${data.message}`;

                        badge.className = 'badge badge-success';
                        badge.textContent = 'Actif';
                        lastTime.textContent = data.last_sync;
                    } else {
                        feedback.style.display = 'block';
                        feedback.style.background = '#fee2e2';
                        feedback.style.border = '1px solid #fca5a5';
                        feedback.style.color = '#991b1b';
                        feedback.innerHTML = `<i class="fas fa-circle-exclamation"></i> ${data.message}`;
                    }
                })
                .catch(error => {
                    btn.style.display = 'inline-flex';
                    loader.style.display = 'none';
                    feedback.style.display = 'block';
                    feedback.style.background = '#fee2e2';
                    feedback.style.border = '1px solid #fca5a5';
                    feedback.style.color = '#991b1b';
                    feedback.innerHTML = `<i class="fas fa-circle-xmark"></i> Une erreur réseau s'est produite lors de la synchronisation.`;
                });
        }
    </script>

    {{-- ── STATUT FNE (DGI) — Lecture seule, la clé n'est jamais affichée ici ── --}}
    <div class="card" style="margin-top:24px; padding:24px;">
        <div
            style="font-size:14px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:20px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <i class="fas fa-key" style="color:var(--primary); font-size:16px;"></i> FNE — Facture Normalisée Électronique
            (DGI)
        </div>

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:32px; align-items:center;">
            <div>
                <div style="font-size:13px; color:var(--text-2); margin-bottom:14px; line-height:1.5;">
                    La normalisation électronique des factures auprès de la DGI nécessite une clé propre à votre entreprise.
                    Pour des raisons de sécurité, cette clé n'est ni affichée ni modifiable ici — seul le support Selflow
                    peut la configurer, sur la base de la clé que vous recevez de la DGI.
                </div>

                <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
                    <div>
                        Statut :
                        @php
                            $fneBadgeClasse = match ($fneStatut['statut']) {
                                'validee' => 'badge-success',
                                'test' => 'badge-warning',
                                default => 'badge-danger',
                            };
                        @endphp
                        <span id="fne-status-badge" class="badge {{ $fneBadgeClasse }}">{{ $fneStatut['label'] }}</span>
                    </div>
                    <div>
                        Dernière vérification :
                        <strong
                            id="fne-last-check">{{ $fneStatut['derniere_verification'] ? \Carbon\Carbon::parse($fneStatut['derniere_verification'])->format('d/m/Y \à H:i:s') : 'Jamais vérifié' }}</strong>
                    </div>
                    @if($fneStatut['statut'] === 'non_configure')
                        <div
                            style="color:#92400e; background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:10px 12px; margin-top:4px;">
                            <i class="fas fa-circle-info"></i> Aucune clé FNE n'est encore configurée. Contactez le support
                            Selflow pour lancer la demande auprès de la DGI.
                        </div>
                    @elseif($fneStatut['statut'] === 'test')
                        <div
                            style="color:#92400e; background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:10px 12px; margin-top:4px;">
                            <i class="fas fa-flask"></i> Vous êtes en phase de test DGI. Les factures normalisées ne sont pas
                            encore fiscalement définitives.
                        </div>
                    @endif
                </div>
            </div>

            <div
                style="background:var(--bg3); border-radius:10px; padding:24px; border:1px solid var(--border); text-align:center;">
                <p style="font-size:13px; font-weight:600; margin-bottom:16px; color:var(--text-1);">Vérifier la
                    joignabilité du serveur DGI</p>

                <div id="fne-feedback"
                    style="display:none; padding:12px; border-radius:8px; font-size:13px; margin-bottom:16px; text-align:left; font-weight:500;">
                </div>

                <button type="button" id="btn-fne-test" onclick="testerConnexionFne()" class="btn btn-primary"
                    style="margin:0 auto; padding:10px 24px; font-weight:700; gap:8px;" {{ $fneStatut['statut'] === 'non_configure' ? 'disabled' : '' }}>
                    <i class="fas fa-satellite-dish"></i> Tester la connexion
                </button>
                <span id="fne-loader" style="display:none; font-size:13px; color:var(--text-3); font-weight:600;">
                    <i class="fas fa-spinner fa-spin" style="color:var(--primary); margin-right:8px;"></i> Test en cours...
                </span>
            </div>
        </div>
    </div>

    <script>
        function testerConnexionFne() {
            const btn = document.getElementById('btn-fne-test');
            const loader = document.getElementById('fne-loader');
            const feedback = document.getElementById('fne-feedback');
            const lastCheck = document.getElementById('fne-last-check');

            btn.style.display = 'none';
            loader.style.display = 'inline-flex';
            feedback.style.display = 'none';

            fetch("{{ route('admin.entreprise.fne.tester_connexion') }}", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" }
            })
                .then(response => response.json())
                .then(data => {
                    btn.style.display = 'inline-flex';
                    loader.style.display = 'none';
                    feedback.style.display = 'block';

                    if (data.success) {
                        feedback.style.background = '#d1fae5';
                        feedback.style.border = '1px solid #6ee7b7';
                        feedback.style.color = '#065f46';
                    } else {
                        feedback.style.background = '#fee2e2';
                        feedback.style.border = '1px solid #fca5a5';
                        feedback.style.color = '#991b1b';
                    }
                    feedback.innerHTML = data.message;
                    lastCheck.textContent = new Date().toLocaleString('fr-FR');
                })
                .catch(() => {
                    btn.style.display = 'inline-flex';
                    loader.style.display = 'none';
                    feedback.style.display = 'block';
                    feedback.style.background = '#fee2e2';
                    feedback.style.border = '1px solid #fca5a5';
                    feedback.style.color = '#991b1b';
                    feedback.innerHTML = "Une erreur réseau s'est produite lors du test.";
                });
        }
    </script>
@endsection