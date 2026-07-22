@extends('admin::gabarits.application')
@section('titre', 'Liaisons SELFLOW ↔ COMPTAFLOW')
@section('topbar_titre', 'SuperAdmin — Liaisons inter-applications')

@section('styles')
<style>
    .liaison-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 32px;
    }
    @media (max-width: 900px) { .liaison-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .stat-icon {
        width: 52px; height: 52px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; flex-shrink: 0;
    }
    .stat-val { font-size: 28px; font-weight: 800; color: var(--text-1); }
    .stat-lbl { font-size: 12px; color: var(--text-3); text-transform: uppercase; font-weight: 700; letter-spacing: .5px; }

    .liaison-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
    }
    .badge-active   { background: #ecfdf5; color: #065f46; }
    .badge-inactive { background: #f1f5f9; color: #64748b; }
    .badge-error    { background: #fef2f2; color: #991b1b; }

    .direction-arrow {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 11px; color: var(--text-3); font-weight: 600;
        margin-left: 8px;
    }
    .arrow-icon { font-size: 10px; }

    .ent-row td { vertical-align: middle; padding: 12px 14px; }

    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center; }
    .modal-box     { background:#fff; border-radius:16px; max-width:540px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.15); overflow:hidden; }
    .modal-header  { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
    .modal-body    { padding:22px; }
    .modal-footer  { padding:14px 22px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }
</style>
@endsection

@section('contenu')

{{-- Alertes --}}
@if(session('success'))
<div style="background:#ecfdf5; border:1px solid #6ee7b7; border-radius:10px; padding:14px 18px; margin-bottom:20px; color:#065f46; font-weight:600; display:flex; align-items:center; gap:10px;">
    <i class="fas fa-check-circle" style="font-size:18px;"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:10px; padding:14px 18px; margin-bottom:20px; color:#991b1b; font-weight:600; display:flex; align-items:center; gap:10px;">
    <i class="fas fa-exclamation-circle" style="font-size:18px;"></i> {{ session('error') }}
</div>
@endif

{{-- En-tête --}}
<div class="page-header">
    <div>
        <h1><i class="fas fa-link"></i> Liaisons SELFLOW ↔ COMPTAFLOW</h1>
        <p>Gérez les connexions inter-applications entre Selflow et COMPTAFLOW.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-primary" onclick="document.getElementById('modalLierManuellement').style.display='flex'">
            <i class="fas fa-plug"></i> Lier manuellement
        </button>
        <button class="btn btn-outline" onclick="document.getElementById('modalCreerComptaflow').style.display='flex'">
            <i class="fas fa-plus-circle"></i> Créer compte COMPTAFLOW
        </button>
    </div>
</div>

{{-- KPIs --}}
@php
    $liees    = $entreprises->whereNotNull('comptaflow_company_id')->count();
    $nonLiees = $entreprises->whereNull('comptaflow_company_id')->count();
@endphp
<div class="liaison-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#ecfdf5; color:#10b981;"><i class="fas fa-link"></i></div>
        <div><div class="stat-val">{{ $liees }}</div><div class="stat-lbl">Entreprises liées à COMPTAFLOW</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f1f5f9; color:#64748b;"><i class="fas fa-unlink"></i></div>
        <div><div class="stat-val">{{ $nonLiees }}</div><div class="stat-lbl">Entreprises sans liaison</div></div>
    </div>
</div>

{{-- Tableau des liaisons --}}
<div class="card">
    <div style="padding:18px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
        <h3 style="font-size:15px; font-weight:700; margin:0;"><i class="fas fa-table"></i> Tableau croisé des liaisons</h3>
        <span style="font-size:12px; color:var(--text-3);">{{ $entreprises->count() }} entreprise(s) Selflow</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Entreprise Selflow</th>
                    <th>ID Selflow</th>
                    <th>ID COMPTAFLOW</th>
                    <th>Statut liaison</th>
                    <th>Dernière sync</th>
                    <th>Admin</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entreprises as $ent)
                @php
                    $admin = $ent->utilisateurs->first();
                    $estLiee = !empty($ent->comptaflow_company_id);
                    $syncStatus = $ent->comptaflow_sync_status;
                @endphp
                <tr class="ent-row">
                    <td>
                        <div style="font-weight:700; color:var(--text-1);">{{ $ent->nom }}</div>
                        <div style="font-size:11px; color:var(--text-3);">{{ $ent->forme_juridique }} · {{ is_array($ent->secteur_activite) ? implode(', ', $ent->secteur_activite) : ($ent->secteur_activite ?? '—') }}</div>
                        @if($ent->rccm)
                            <div style="font-size:10px; color:var(--text-3); font-family:monospace;">RCCM: {{ $ent->rccm }}</div>
                        @endif
                    </td>
                    <td>
                        <span style="background:var(--bg3); color:var(--primary); padding:3px 8px; border-radius:6px; font-family:monospace; font-weight:700; font-size:12px;">
                            #{{ $ent->id }}
                        </span>
                    </td>
                    <td>
                        @if($estLiee)
                            <span style="background:#dbeafe; color:#1d4ed8; padding:3px 8px; border-radius:6px; font-family:monospace; font-weight:700; font-size:12px;">
                                #{{ $ent->comptaflow_company_id }}
                            </span>
                        @else
                            <span style="color:var(--text-3); font-size:12px;">—</span>
                        @endif
                    </td>
                    <td>
                        @if($estLiee)
                            @if($syncStatus === 'active')
                                <span class="liaison-badge badge-active">
                                    <i class="fas fa-circle" style="font-size:7px;"></i> Active
                                </span>
                                <span class="direction-arrow">
                                    <i class="fas fa-arrows-left-right arrow-icon"></i> selflow ↔ comptaflow
                                </span>
                            @elseif($syncStatus === 'error')
                                <span class="liaison-badge badge-error">
                                    <i class="fas fa-exclamation-triangle" style="font-size:9px;"></i> Erreur
                                </span>
                            @else
                                <span class="liaison-badge badge-inactive">
                                    <i class="fas fa-pause-circle" style="font-size:9px;"></i> {{ $syncStatus ?? 'Inconnue' }}
                                </span>
                            @endif
                        @else
                            <span class="liaison-badge badge-inactive">
                                <i class="fas fa-unlink" style="font-size:9px;"></i> Non liée
                            </span>
                        @endif
                    </td>
                    <td style="font-size:12px; color:var(--text-2);">
                        @if($ent->comptaflow_last_sync_at)
                            {{ \Carbon\Carbon::parse($ent->comptaflow_last_sync_at)->format('d/m/Y H:i') }}
                        @else
                            <span style="color:var(--text-3);">—</span>
                        @endif
                    </td>
                    <td>
                        @if($admin)
                            <div style="font-weight:600; font-size:13px;">{{ $admin->prenom }} {{ $admin->nom }}</div>
                            <div style="font-size:11px; color:var(--text-3);">{{ $admin->email }}</div>
                        @else
                            <span style="color:var(--text-3); font-size:12px;">—</span>
                        @endif
                    </td>
                    <td style="text-align:center; white-space:nowrap;">
                        <div style="display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
                            @if($estLiee)
                                {{-- Vérifier --}}
                                <form method="POST" action="{{ route('superadmin.liaisons.verifier', $ent) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm" title="Vérifier la liaison" style="padding:5px 8px;">
                                        <i class="fas fa-satellite-dish"></i>
                                    </button>
                                </form>
                                {{-- Délier --}}
                                <form method="POST" action="{{ route('superadmin.liaisons.delierEntreprise', $ent) }}"
                                    onsubmit="return confirm('Délier «{{ $ent->nom }}» de COMPTAFLOW ?')" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Supprimer la liaison" style="padding:5px 8px;">
                                        <i class="fas fa-unlink"></i>
                                    </button>
                                </form>
                            @else
                                <button type="button" class="btn btn-primary btn-sm"
                                    onclick="ouvrirLierPour({{ $ent->id }}, '{{ addslashes($ent->nom) }}')"
                                    style="padding:5px 10px; font-size:11px;">
                                    <i class="fas fa-plug"></i> Lier
                                </button>
                                <button type="button" class="btn btn-outline btn-sm"
                                    onclick="ouvrirCreerPour({{ $ent->id }}, '{{ addslashes($ent->nom) }}')"
                                    style="padding:5px 10px; font-size:11px;">
                                    <i class="fas fa-plus"></i> Créer CPTF
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- MODAL : Lier manuellement                                    --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modalLierManuellement">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="font-size:15px; font-weight:700; margin:0;"><i class="fas fa-plug" style="color:var(--primary)"></i> Lier une entreprise à COMPTAFLOW</h3>
            <button onclick="document.getElementById('modalLierManuellement').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-3);">&times;</button>
        </div>
        <form method="POST" action="{{ route('superadmin.liaisons.lier') }}" style="margin:0;">
            @csrf
            <div class="modal-body">
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 14px; margin-bottom:18px; font-size:13px; color:#1d4ed8;">
                    <i class="fas fa-info-circle"></i>
                    Entrez l'<strong>ID COMPTAFLOW</strong> et la <strong>clé de synchronisation</strong> générée côté COMPTAFLOW.
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label">Entreprise Selflow <span style="color:var(--danger)">*</span></label>
                    <select name="entreprise_id" id="select-entreprise-lier" class="form-control" required>
                        <option value="">— Sélectionner une entreprise —</option>
                        @foreach($entreprises as $ent)
                            <option value="{{ $ent->id }}" {{ $ent->comptaflow_company_id ? 'disabled' : '' }}>
                                #{{ $ent->id }} — {{ $ent->nom }} {{ $ent->comptaflow_company_id ? '(déjà liée)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px;">
                    <div class="form-group">
                        <label class="form-label">ID COMPTAFLOW <span style="color:var(--danger)">*</span></label>
                        <input type="number" name="comptaflow_company_id" class="form-control" required min="1" placeholder="Ex: 5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Clé de synchronisation <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="comptaflow_sync_key" class="form-control" required placeholder="Ex: sf_abc123xyz">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalLierManuellement').style.display='none'">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer la liaison</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- MODAL : Créer compte COMPTAFLOW depuis Selflow               --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modalCreerComptaflow">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <h3 style="font-size:15px; font-weight:700; margin:0;"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Créer un compte COMPTAFLOW</h3>
            <button onclick="document.getElementById('modalCreerComptaflow').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-3);">&times;</button>
        </div>
        <form method="POST" action="{{ route('superadmin.liaisons.creerComptaflow') }}" style="margin:0;">
            @csrf
            <div class="modal-body">
                <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:12px 14px; margin-bottom:18px; font-size:13px; color:#166534;">
                    <i class="fas fa-magic"></i>
                    Les informations de l'entreprise (nom, RCCM, NCC, adresse, admin) seront automatiquement envoyées à COMPTAFLOW.
                    Seul le mot de passe est à définir.
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label">Entreprise Selflow <span style="color:var(--danger)">*</span></label>
                    <select name="entreprise_id" id="select-entreprise-creer" class="form-control" required onchange="afficherInfosEntreprise(this)">
                        <option value="">— Sélectionner une entreprise non liée —</option>
                        @foreach($entreprises->whereNull('comptaflow_company_id') as $ent)
                            <option value="{{ $ent->id }}"
                                data-nom="{{ $ent->nom }}"
                                data-rccm="{{ $ent->rccm ?? '—' }}"
                                data-ncc="{{ $ent->ncc ?? '—' }}"
                                data-email="{{ optional($ent->utilisateurs->first())->email ?? '—' }}"
                                data-admin="{{ optional($ent->utilisateurs->first())->prenom }} {{ optional($ent->utilisateurs->first())->nom }}">
                                #{{ $ent->id }} — {{ $ent->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Infos auto-affichées --}}
                <div id="infos-entreprise-creer" style="display:none; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:14px; margin-bottom:16px; font-size:13px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <div><span style="color:var(--text-3); font-size:11px; font-weight:700;">ENTREPRISE</span><br><strong id="iev-nom">—</strong></div>
                        <div><span style="color:var(--text-3); font-size:11px; font-weight:700;">ADMIN</span><br><strong id="iev-admin">—</strong></div>
                        <div><span style="color:var(--text-3); font-size:11px; font-weight:700;">RCCM</span><br><span id="iev-rccm" style="font-family:monospace;">—</span></div>
                        <div><span style="color:var(--text-3); font-size:11px; font-weight:700;">NCC</span><br><span id="iev-ncc" style="font-family:monospace;">—</span></div>
                        <div style="grid-column:1/-1;"><span style="color:var(--text-3); font-size:11px; font-weight:700;">EMAIL ADMIN</span><br><span id="iev-email">—</span></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mot de passe COMPTAFLOW <span style="color:var(--danger)">*</span></label>
                    <input type="password" name="mot_de_passe" class="form-control" required minlength="8" placeholder="Min. 8 caractères">
                    <small style="color:var(--text-3); font-size:11px;">Ce mot de passe sera utilisé pour la connexion à COMPTAFLOW.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalCreerComptaflow').style.display='none'">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-rocket"></i> Créer et lier</button>
            </div>
        </form>
    </div>
</div>

<script>
function ouvrirLierPour(id, nom) {
    const sel = document.getElementById('select-entreprise-lier');
    sel.value = id;
    document.getElementById('modalLierManuellement').style.display = 'flex';
}

function ouvrirCreerPour(id, nom) {
    const sel = document.getElementById('select-entreprise-creer');
    sel.value = id;
    afficherInfosEntreprise(sel);
    document.getElementById('modalCreerComptaflow').style.display = 'flex';
}

function afficherInfosEntreprise(sel) {
    const opt = sel.selectedOptions[0];
    const box = document.getElementById('infos-entreprise-creer');
    if (!opt || !opt.value) { box.style.display = 'none'; return; }

    document.getElementById('iev-nom').textContent   = opt.dataset.nom   || '—';
    document.getElementById('iev-admin').textContent = opt.dataset.admin || '—';
    document.getElementById('iev-rccm').textContent  = opt.dataset.rccm  || '—';
    document.getElementById('iev-ncc').textContent   = opt.dataset.ncc   || '—';
    document.getElementById('iev-email').textContent = opt.dataset.email  || '—';
    box.style.display = 'block';
}

// Fermer modal en cliquant dehors
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});
</script>
@endsection