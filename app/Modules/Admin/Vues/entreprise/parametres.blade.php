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
<div class="alert alert-success" style="display:flex;align-items:center;gap:10px;padding:14px 18px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;margin-bottom:20px;color:#065f46;font-weight:500;">
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
                <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-info-circle" style="color:var(--primary);"></i> Informations générales
                </div>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div class="form-group">
                        <label class="form-label">Nom de l'entreprise <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="nom" class="form-control" value="{{ old('nom', $entreprise->nom) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Adresse physique</label>
                        <input type="text" name="adresse" class="form-control" value="{{ old('adresse', $entreprise->adresse) }}" placeholder="Ex: Cocody, Abidjan, Côte d'Ivoire">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" value="{{ old('telephone', $entreprise->telephone) }}" placeholder="Ex: +225 07 00 00 00">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $entreprise->email) }}" placeholder="Ex: contact@monentreprise.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">RCCM</label>
                        <input type="text" name="rccm" class="form-control" value="{{ old('rccm', $entreprise->rccm) }}" placeholder="Ex: CI-ABJ-03-2021-B13-05438">
                    </div>
                </div>
            </div>

            {{-- Informations fiscales --}}
            <div class="card" style="padding:24px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-file-invoice" style="color:var(--primary);"></i> Informations fiscales
                </div>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">NCC (Nº Compte Contribuable)</label>
                            <input type="text" name="ncc" class="form-control" value="{{ old('ncc', $entreprise->ncc) }}" placeholder="Ex: 2169728N">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Régime d'imposition</label>
                            <select name="regime_imposition" class="form-control">
                                <option value="">— Non renseigné —</option>
                                @foreach(['TEE', 'RS', 'RSI', 'RNI', 'Exonéré'] as $regime)
                                <option value="{{ $regime }}" {{ old('regime_imposition', $entreprise->regime_imposition) === $regime ? 'selected' : '' }}>
                                    {{ $regime }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Centre des impôts</label>
                        <input type="text" name="centre_impots" class="form-control" value="{{ old('centre_impots', $entreprise->centre_impots) }}" placeholder="Ex: 807 Impôts de Cocody">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Références bancaires</label>
                        <textarea name="ref_bancaire" class="form-control" rows="3" placeholder="Ex: Établissement : SGBCI — N° compte : 00123456789">{{ old('ref_bancaire', $entreprise->ref_bancaire) }}</textarea>
                        <small style="color:var(--text-3);font-size:11px;">Ces informations apparaîtront en bas de vos factures.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">N° Compte Contribuable (CC)</label>
                        <input type="text" name="compte_contribuable" class="form-control" value="{{ old('compte_contribuable', $entreprise->compte_contribuable) }}" placeholder="Ex: 1234567890123">
                    </div>
                </div>
            </div>
        </div>

        {{-- Colonne droite --}}
        <div style="display:flex;flex-direction:column;gap:20px;">

            {{-- Logos --}}
            <div class="card" style="padding:24px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-image" style="color:var(--primary);"></i> Logos (affichés sur les factures)
                </div>

                {{-- Logo principal --}}
                <div style="margin-bottom:20px;">
                    <label class="form-label" style="margin-bottom:10px;display:block;">Logo principal de l'entreprise</label>
                    @if($entreprise->logo_path)
                        <div style="margin-bottom:10px;padding:12px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;gap:12px;">
                            <img src="{{ Storage::disk('public')->url($entreprise->logo_path) }}" alt="Logo entreprise" style="max-height:60px;max-width:140px;object-fit:contain;border-radius:4px;">
                            <span style="font-size:12px;color:var(--text-2);">Logo actuel</span>
                        </div>
                    @else
                        <div style="margin-bottom:10px;padding:16px;background:var(--bg3);border-radius:8px;text-align:center;border:1.5px dashed var(--border);">
                            <i class="fas fa-image" style="font-size:28px;color:var(--text-3);margin-bottom:6px;display:block;"></i>
                            <span style="font-size:12px;color:var(--text-3);">Aucun logo défini</span>
                        </div>
                    @endif
                    <input type="file" name="logo" id="logo" class="form-control" accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" style="margin-top:8px;">
                    <small style="color:var(--text-3);font-size:11px;">PNG, JPG ou SVG · Max 2 Mo. Ce logo apparaît en haut à gauche des factures.</small>
                </div>

                {{-- Logo FNE / secondaire --}}
                <div>
                    <label class="form-label" style="margin-bottom:10px;display:block;">Logo secondaire (FNE, certification, etc.)</label>
                    @if($entreprise->logo_fne_path)
                        <div style="margin-bottom:10px;padding:12px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;gap:12px;">
                            <img src="{{ Storage::disk('public')->url($entreprise->logo_fne_path) }}" alt="Logo FNE" style="max-height:60px;max-width:140px;object-fit:contain;border-radius:4px;">
                            <span style="font-size:12px;color:var(--text-2);">Logo actuel</span>
                        </div>
                    @else
                        <div style="margin-bottom:10px;padding:16px;background:var(--bg3);border-radius:8px;text-align:center;border:1.5px dashed var(--border);">
                            <i class="fas fa-award" style="font-size:28px;color:var(--text-3);margin-bottom:6px;display:block;"></i>
                            <span style="font-size:12px;color:var(--text-3);">Aucun logo secondaire défini</span>
                        </div>
                    @endif
                    <input type="file" name="logo_fne" id="logo_fne" class="form-control" accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" style="margin-top:8px;">
                    <small style="color:var(--text-3);font-size:11px;">PNG, JPG ou SVG · Max 2 Mo. Peut être un label qualité, logo FNE, etc.</small>
                </div>
            </div>

            {{-- Prévisualisation résumé --}}
            <div class="card" style="padding:24px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
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
                    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:0.5px solid var(--border);">
                        <span style="color:var(--text-2);">{{ $label }}</span>
                        <span style="font-weight:600;color:{{ $valeur ? 'var(--text)' : 'var(--text-3)' }};">
                            {{ $valeur ?? '— Non renseigné —' }}
                        </span>
                    </div>
                    @endforeach
                    @if($entreprise->ref_bancaire)
                    <div style="padding:8px;background:var(--bg3);border-radius:6px;font-size:12px;color:var(--text-2);margin-top:4px;">
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
    <div style="font-size:14px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:20px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;">
        <i class="far fa-calendar-alt" style="color:var(--primary); font-size:16px;"></i> Gestion des Exercices Comptables (Périodes)
    </div>
    
    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:32px; align-items:start;">
        {{-- Formulaire de création d'un exercice --}}
        <div style="background:var(--bg3); border-radius:10px; padding:20px; border:1px solid var(--border);">
            <h3 style="font-size:13px; font-weight:700; margin-bottom:14px; color:var(--primary);"><i class="fas fa-plus"></i> Nouvel Exercice</h3>
            
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
            <h3 style="font-size:13px; font-weight:700; margin-bottom:14px; color:var(--text-2);"><i class="fas fa-list-ul"></i> Exercices enregistrés</h3>
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
                                @if(session('active_periode_id') == $p->id)
                                    <span class="badge badge-success"><i class="fas fa-circle-check"></i> Sélectionné</span>
                                @elseif($p->est_active)
                                    <span class="badge badge-info">Actif</span>
                                @else
                                    <span class="badge badge-gray">Inactif</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if(session('active_periode_id') != $p->id)
                                    <form method="POST" action="{{ route('admin.periods.switch') }}" style="margin:0;">
                                        @csrf
                                        <input type="hidden" name="periode_id" value="{{ $p->id }}">
                                        <button type="submit" class="btn btn-outline btn-sm">
                                            <i class="fas fa-right-from-bracket"></i> Basculer
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size:11px; font-weight:600; color:var(--success);">Actif en session</span>
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
@endsection
