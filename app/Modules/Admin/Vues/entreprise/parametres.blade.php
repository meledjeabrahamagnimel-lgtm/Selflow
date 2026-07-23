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
                        <div class="form-group">
                            <label class="form-label">Nom de l'entreprise <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="nom" class="form-control" value="{{ old('nom', $entreprise->nom) }}"
                                required>
                        </div>

                        {{-- Informations Gérant --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Nom du Gérant / Représentant</label>
                                <input type="text" name="gerant_nom" class="form-control"
                                    value="{{ old('gerant_nom', $entreprise->gerant_nom) }}" placeholder="Ex: Dupont">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Prénom du Gérant</label>
                                <input type="text" name="gerant_prenom" class="form-control"
                                    value="{{ old('gerant_prenom', $entreprise->gerant_prenom) }}" placeholder="Ex: Jean">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fonction du Gérant</label>
                            <input type="text" name="gerant_fonction" class="form-control"
                                value="{{ old('gerant_fonction', $entreprise->gerant_fonction) }}"
                                placeholder="Ex: Directeur Général / Gérant">
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
                            <label class="form-label">RCCM (Lecture seule · Contacter le support pour modifier)</label>
                            <input type="text" class="form-control" value="{{ $entreprise->rccm }}"
                                placeholder="Ex: CI-ABJ-03-2021-B13-05438" disabled
                                style="background:var(--bg2); cursor:not-allowed;">
                            <input type="hidden" name="rccm" value="{{ $entreprise->rccm }}">
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
                                <label class="form-label">NCC (Nº Compte Contribuable)</label>
                                <input type="text" class="form-control" value="{{ $entreprise->ncc }}"
                                    placeholder="Ex: 2169728N" disabled style="background:var(--bg2); cursor:not-allowed;">
                                <input type="hidden" name="ncc" value="{{ $entreprise->ncc }}">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Régime d'imposition</label>
                                <input type="text" class="form-control"
                                    value="{{ $entreprise->regime_imposition ?: 'Non renseigné' }}" disabled
                                    style="background:var(--bg2); cursor:not-allowed;">
                                <input type="hidden" name="regime_imposition" value="{{ $entreprise->regime_imposition }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Centre des impôts</label>
                            <input type="text" class="form-control" value="{{ $entreprise->centre_impots }}"
                                placeholder="Ex: 807 Impôts de Cocody" disabled
                                style="background:var(--bg2); cursor:not-allowed;">
                            <input type="hidden" name="centre_impots" value="{{ $entreprise->centre_impots }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Références bancaires</label>
                            <textarea name="ref_bancaire" class="form-control" rows="3"
                                placeholder="Ex: Établissement : SGBCI — N° compte : 00123456789">{{ old('ref_bancaire', $entreprise->ref_bancaire) }}</textarea>
                            <small style="color:var(--text-3);font-size:11px;">Ces informations apparaîtront en bas de vos
                                factures.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">N° Compte Contribuable (CC)</label>
                            <input type="text" class="form-control" value="{{ $entreprise->compte_contribuable }}"
                                placeholder="Ex: 1234567890123" disabled style="background:var(--bg2); cursor:not-allowed;">
                            <input type="hidden" name="compte_contribuable" value="{{ $entreprise->compte_contribuable }}">
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
            </div>

            {{-- Colonne droite --}}
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
                                ['CC', $entreprise->compte_contribuable],
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