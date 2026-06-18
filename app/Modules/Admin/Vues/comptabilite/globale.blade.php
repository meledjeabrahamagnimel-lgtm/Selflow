@extends('admin::gabarits.application')
@section('titre', 'Opération & Écriture Globale')
@section('topbar_titre', 'Comptabilité — Grand Livre & Opérations')

@section('styles')
<style>
    .comp-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .mode-selector {
        display: flex;
        background: var(--border);
        padding: 4px;
        border-radius: 8px;
    }
    
    .mode-btn {
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        font-weight: 600;
        font-size: 13.5px;
        cursor: pointer;
        transition: all 0.15s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .mode-btn.active {
        background: #fff;
        color: var(--primary);
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }
    
    .mode-btn:not(.active) {
        background: transparent;
        color: var(--text-2);
    }
    
    .mode-btn:not(.active):hover {
        color: var(--text);
    }

    .filter-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
        display: flex;
        gap: 16px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filter-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-3);
    }
</style>
@endsection

@section('contenu')
<div class="comp-header">
    <div>
        <h1><i class="fas fa-book-open"></i> Opération & Écriture Globale</h1>
        <p>Consultez le journal des opérations de trésorerie ou le Grand Livre des écritures comptables.</p>
    </div>
    
    <div class="mode-selector">
        <a href="{{ route('admin.comptabilite.globale', ['mode' => 'operations', 'point_de_vente_id' => $pdvFilter]) }}" 
           class="mode-btn {{ $mode === 'operations' ? 'active' : '' }}">
            <i class="fas fa-wallet"></i> Afficher les opérations
        </a>
        <a href="{{ route('admin.comptabilite.globale', ['mode' => 'ecritures', 'point_de_vente_id' => $pdvFilter]) }}" 
           class="mode-btn {{ $mode === 'ecritures' ? 'active' : '' }}">
            <i class="fas fa-file-invoice-dollar"></i> Afficher les écritures
        </a>
    </div>
</div>

{{-- FILTRE --}}
<form method="GET" action="{{ route('admin.comptabilite.globale') }}" class="filter-card">
    <input type="hidden" name="mode" value="{{ $mode }}">
    
    @if($isAdmin)
    <div class="filter-group" style="min-width: 240px;">
        <span class="filter-label">Point de vente</span>
        <select name="point_de_vente_id" class="form-control" onchange="this.form.submit()">
            <option value="">— Tous les points de vente —</option>
            @foreach($pointsDeVente as $pdv)
            <option value="{{ $pdv->id }}" {{ $pdvFilter == $pdv->id ? 'selected' : '' }}>
                {{ $pdv->nom }} ({{ $pdv->ville }})
            </option>
            @endforeach
        </select>
    </div>
    @else
    <div class="filter-group">
        <span class="filter-label">Point de vente</span>
        <input type="text" class="form-control" value="{{ Auth::user()->pointDeVente?->nom ?? 'Siège' }}" readonly style="background:var(--bg); max-width: 240px;">
    </div>
    @endif

    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
        <i class="fas fa-filter"></i> Filtrer
    </button>
</form>

<div class="card">
    <div class="table-wrap">
        @if($mode === 'operations')
            {{-- TABLEAU DES OPÉRATIONS DE TRÉSORERIE --}}
            @if($operations->isEmpty())
                <div style="padding: 48px; text-align:center; color:var(--text-3);">
                    <i class="fas fa-wallet" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
                    Aucune opération enregistrée pour le moment.
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>N° pièce</th>
                            <th>Référence</th>
                            <th>Libellé</th>
                            <th>Mode paiement</th>
                            <th style="text-align: right;">Montant</th>
                            <th>Client</th>
                            <th>Statut</th>
                            <th>Point de Vente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($operations as $op)
                        <tr>
                            <td style="font-weight: 500;">{{ \Carbon\Carbon::parse($op->date_operation)->format('d/m/Y') }}</td>
                            <td style="font-weight: 600;">{{ $op->id }}</td>
                            <td style="font-weight: 700; color: var(--primary);">{{ $op->reference_document ?? '—' }}</td>
                            <td>{{ $op->libelle }}</td>
                            <td>{{ $op->mode_paiement }}</td>
                            <td style="text-align: right; font-weight: 700;">
                                @if($op->montant_entree > 0)
                                    <span style="color: var(--success);">+{{ number_format($op->montant_entree, 0, ',', ' ') }} F</span>
                                @elseif($op->montant_sortie > 0)
                                    <span style="color: var(--danger);">-{{ number_format($op->montant_sortie, 0, ',', ' ') }} F</span>
                                @else
                                    <span>0 F</span>
                                @endif
                            </td>
                            <td style="font-weight: 600;">{{ $op->tier_nom }}</td>
                            <td>
                                @if($op->statut === 'Payé')
                                    <span class="badge badge-success" style="background:#e6fdf5; color:#0f766e; padding:3px 8px; border-radius:20px;">Payé</span>
                                @elseif($op->statut === 'Crédit')
                                    <span class="badge badge-danger" style="background:#fef2f2; color:#991b1b; padding:3px 8px; border-radius:20px;">Crédit</span>
                                @elseif($op->statut === 'Avance')
                                    <span class="badge badge-warning" style="background:#fffbeb; color:#92400e; padding:3px 8px; border-radius:20px;">Avance</span>
                                @else
                                    <span class="badge badge-gray" style="background:#f1f5f9; color:#475569; padding:3px 8px; border-radius:20px;">—</span>
                                @endif
                            </td>
                            <td><span class="badge badge-purple" style="background:var(--bg3); color:var(--primary); padding:3px 8px; border-radius:6px; font-weight:600;">{{ $op->pointDeVente->nom }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="padding: 16px;">
                    {{ $operations->appends(request()->query())->links() }}
                </div>
            @endif
        @else
            {{-- GRAND LIVRE DES ÉCRITURES DOUBLE-ENTRÉE --}}
            @if($ecritures->isEmpty())
                <div style="padding: 48px; text-align:center; color:var(--text-3);">
                    <i class="fas fa-file-invoice-dollar" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
                    Aucune écriture comptable enregistrée.
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Date de pièce (JJMMAA)</th>
                            <th>N° pièce</th>
                            <th>Code journal</th>
                            <th>Numéro facture</th>
                            <th>N° compte général</th>
                            <th>Libellé écriture</th>
                            <th style="text-align: right;">Montant débit</th>
                            <th style="text-align: right;">Montant crédit</th>
                            <th>N° compte tiers</th>
                            <th>Point de vente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ecritures as $ecr)
                        @php
                            $compte = $ecr->compte_debit ?? $ecr->compte_credit;
                            $isTiers = str_starts_with($compte, '411') || str_starts_with($compte, '401');
                            $saisieKey = $ecr->code_journal . '_' . $ecr->reference_document;
                            
                            $saisieNum = null;
                            if ($ecr->reference_document) {
                                if (isset($tresoMap[$ecr->reference_document])) {
                                    $saisieNum = $tresoMap[$ecr->reference_document];
                                } elseif (isset($venteMap[$ecr->reference_document])) {
                                    $saisieNum = $venteMap[$ecr->reference_document];
                                } elseif (isset($achatMap[$ecr->reference_document])) {
                                    $saisieNum = $achatMap[$ecr->reference_document];
                                }
                            }
                            
                            if (is_null($saisieNum)) {
                                $saisieNum = $minIds[$saisieKey] ?? $ecr->id;
                            }
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($ecr->date_ecriture)->format('dmy') }}</td>
                            <td style="font-weight: 600;">{{ $saisieNum }}</td>
                            <td><span class="badge" style="background: var(--bg3); color: var(--primary); font-weight:700;">{{ $ecr->code_journal }}</span></td>
                            <td style="font-weight: 700; color: var(--primary);">{{ $ecr->reference_document }}</td>
                            <td style="font-weight: 600; color: var(--text-2);">
                                {{ !$isTiers ? $compte : '—' }}
                            </td>
                            <td>{{ $ecr->libelle }}</td>
                            <td style="text-align: right; font-weight: 700; color: #1e3a8a;">
                                {{ $ecr->debit > 0 ? number_format($ecr->debit, 0, ',', ' ') . ' F' : '—' }}
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #7f1d1d;">
                                {{ $ecr->credit > 0 ? number_format($ecr->credit, 0, ',', ' ') . ' F' : '—' }}
                            </td>
                            <td style="font-weight: 600; color: var(--text-2);">
                                {{ $isTiers ? $compte : '—' }}
                            </td>
                            <td>{{ $ecr->pointDeVente->nom }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="padding: 16px;">
                    {{ $ecritures->appends(request()->query())->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
