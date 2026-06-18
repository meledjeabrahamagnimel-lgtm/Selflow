@extends('admin::gabarits.application')
@section('titre', 'Créances & Règlements')
@section('topbar_titre', 'Comptabilité — Créances & Dettes')

@section('styles')
<style>
    .creances-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .tabs-nav {
        display: flex;
        border-bottom: 2px solid var(--border);
        margin-bottom: 20px;
        gap: 24px;
    }

    .tab-nav-btn {
        padding: 12px 4px;
        font-weight: 700;
        font-size: 14.5px;
        color: var(--text-2);
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .tab-nav-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .badge-invoice {
        display: inline-block;
        background: var(--bg3);
        color: var(--primary);
        font-size: 11px;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 4px;
        margin-right: 4px;
        margin-bottom: 4px;
    }
</style>
@endsection

@section('contenu')
<div class="creances-header">
    <div>
        <h1><i class="fas fa-file-invoice-dollar"></i> Créances & Règlements</h1>
        <p>Suivez les factures impayées de vos clients (créances) et vos factures d'achats non réglées (dettes).</p>
    </div>
</div>

<div class="tabs-nav">
    <button class="tab-nav-btn active" onclick="switchTab('clients', this)">
        <i class="fas fa-user-tag"></i> Créances Clients ({{ count($creancesClients) }})
    </button>
    <button class="tab-nav-btn" onclick="switchTab('fournisseurs', this)">
        <i class="fas fa-truck"></i> Dettes Fournisseurs ({{ count($dettesFournisseurs) }})
    </button>
</div>

{{-- ONGLER CLIENTS --}}
<div id="tab-clients" class="tab-content active">
    <div class="card">
        <div class="table-wrap">
            @if(empty($creancesClients))
            <div style="padding: 48px; text-align:center; color:var(--text-3);">
                <i class="fas fa-smile" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
                Aucune créance client active. Félicitations !
            </div>
            @else
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Factures en attente</th>
                        <th style="text-align: right;">Total Dû (TTC)</th>
                        <th style="text-align: right;">Montant Réglé</th>
                        <th style="text-align: right;">Reste à payer</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($creancesClients as $cc)
                    <tr>
                        <td style="font-weight: 700; color: var(--text);">{{ $cc['nom'] }}</td>
                        <td>
                            @foreach($cc['invoices'] as $inv)
                            <span class="badge-invoice" title="Émise le {{ $inv['date'] }}">
                                {{ $inv['numero'] }} (Reste: {{ number_format($inv['reste'], 0, ',', ' ') }} F)
                            </span>
                            @endforeach
                        </td>
                        <td style="text-align: right; font-weight: 600;">{{ number_format($cc['total_ttc'], 0, ',', ' ') }} F</td>
                        <td style="text-align: right; color: var(--success); font-weight: 600;">{{ number_format($cc['total_regle'], 0, ',', ' ') }} F</td>
                        <td style="text-align: right; color: var(--danger); font-weight: 700; font-size: 15px;">{{ number_format($cc['solde'], 0, ',', ' ') }} F</td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <a href="{{ route('admin.comptabilite.releve_tiers', ['type' => 'client', 'id' => $cc['id']]) }}" class="btn btn-outline btn-sm">
                                    <i class="fas fa-folder-open"></i> Relevé
                                </a>
                                <button type="button" class="btn btn-primary btn-sm" data-tier="{{ json_encode($cc) }}" onclick="ouvrirModalReglement('client', this)">
                                    <i class="fas fa-hand-holding-dollar"></i> Enregistrer Règlement
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

{{-- ONGLER FOURNISSEURS --}}
<div id="tab-fournisseurs" class="tab-content">
    <div class="card">
        <div class="table-wrap">
            @if(empty($dettesFournisseurs))
            <div style="padding: 48px; text-align:center; color:var(--text-3);">
                <i class="fas fa-check-circle" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
                Aucune dette fournisseur active. Tout est réglé !
            </div>
            @else
            <table>
                <thead>
                    <tr>
                        <th>Fournisseur</th>
                        <th>Factures concernées</th>
                        <th style="text-align: right;">Montant Facturé (TTC)</th>
                        <th style="text-align: right;">Montant Réglé</th>
                        <th style="text-align: right;">Reste à payer</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dettesFournisseurs as $df)
                    <tr>
                        <td style="font-weight: 700; color: var(--text);">{{ $df['nom'] }}</td>
                        <td>
                            @foreach($df['invoices'] as $inv)
                            <span class="badge-invoice" title="Émise le {{ $inv['date'] }}">
                                {{ $inv['numero'] }} (Reste: {{ number_format($inv['reste'], 0, ',', ' ') }} F)
                            </span>
                            @endforeach
                        </td>
                        <td style="text-align: right; font-weight: 600;">{{ number_format($df['total_ttc'], 0, ',', ' ') }} F</td>
                        <td style="text-align: right; color: var(--success); font-weight: 600;">{{ number_format($df['total_regle'], 0, ',', ' ') }} F</td>
                        <td style="text-align: right; color: var(--danger); font-weight: 700; font-size: 15px;">{{ number_format($df['solde'], 0, ',', ' ') }} F</td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <a href="{{ route('admin.comptabilite.releve_tiers', ['type' => 'fournisseur', 'id' => $df['id']]) }}" class="btn btn-outline btn-sm">
                                    <i class="fas fa-folder-open"></i> Relevé
                                </a>
                                <button type="button" class="btn btn-primary btn-sm" data-tier="{{ json_encode($df) }}" onclick="ouvrirModalReglement('fournisseur', this)">
                                    <i class="fas fa-wallet"></i> Enregistrer Règlement
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

{{-- MODAL RÈGLEMENT --}}
<div class="modal-overlay" id="modalReglement">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h3 id="modalReglementTitle"><i class="fas fa-hand-holding-dollar"></i> Enregistrer un Règlement</h3>
            <button type="button" class="modal-close" onclick="fermerModalReglement()">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.comptabilite.enregistrer_reglement') }}">
            @csrf
            <input type="hidden" name="type" id="reglementType">
            <input type="hidden" name="tier_id" id="reglementTierId">
            
            <div class="form-group">
                <label class="form-label" id="labelTierNom">Tiers</label>
                <input type="text" id="reglementTierNom" class="form-control" readonly style="background:var(--bg)">
            </div>
            
            <div class="form-group">
                <label class="form-label">Facture concernée <span style="color:var(--danger)">*</span></label>
                <select name="numero_facture" id="reglementFactureSelect" class="form-control" onchange="changerFactureReglement(this)" required>
                    <!-- Rempli dynamiquement -->
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Reste à payer sur cette facture</label>
                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg3); padding: 8px 12px; border-radius: 8px; font-weight: 700; color: var(--primary);">
                    <span>Montant dû :</span>
                    <span id="reglementMontantDu">0 F</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Montant du règlement <span style="color:var(--danger)">*</span></label>
                <input type="number" name="montant" id="reglementMontantInput" class="form-control" min="1" required placeholder="Ex: 5000">
            </div>

            <div class="form-group">
                <label class="form-label">Mode de paiement <span style="color:var(--danger)">*</span></label>
                <select name="mode_paiement" class="form-control" required>
                    <option value="Espèces">Espèces</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Virement">Virement</option>
                    <option value="Chèque">Chèque</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Date du règlement <span style="color:var(--danger)">*</span></label>
                <input type="date" name="date_operation" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                <button type="button" class="btn btn-outline" onclick="fermerModalReglement()">Annuler</button>
                <button type="submit" class="btn btn-success">Enregistrer le règlement</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabName, btn) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-nav-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById('tab-' + tabName).classList.add('active');
    btn.classList.add('active');
}

function ouvrirModalReglement(type, btn) {
    const tierData = JSON.parse(btn.getAttribute('data-tier'));
    document.getElementById('reglementType').value = type;
    document.getElementById('reglementTierId').value = tierData.id;
    document.getElementById('reglementTierNom').value = tierData.nom;
    
    const label = document.getElementById('labelTierNom');
    label.textContent = type === 'client' ? 'Client' : 'Fournisseur';

    const title = document.getElementById('modalReglementTitle');
    title.innerHTML = type === 'client' 
        ? '<i class="fas fa-hand-holding-dollar"></i> Enregistrer un Règlement Client' 
        : '<i class="fas fa-wallet"></i> Enregistrer un Règlement Fournisseur';

    // Remplir le sélecteur de factures
    const select = document.getElementById('reglementFactureSelect');
    select.innerHTML = '<option value="">— Choisir une facture —</option>';
    
    tierData.invoices.forEach(inv => {
        const opt = document.createElement('option');
        opt.value = inv.numero;
        opt.textContent = `${inv.numero} (Restant: ${inv.reste.toLocaleString('fr-FR')} F)`;
        opt.dataset.reste = inv.reste;
        select.appendChild(opt);
    });

    document.getElementById('reglementMontantDu').textContent = '0 F';
    document.getElementById('reglementMontantInput').value = '';
    document.getElementById('reglementMontantInput').max = '';

    document.getElementById('modalReglement').classList.add('open');
}

function changerFactureReglement(select) {
    const opt = select.options[select.selectedIndex];
    const reste = opt.dataset.reste ? parseFloat(opt.dataset.reste) : 0;
    
    document.getElementById('reglementMontantDu').textContent = reste.toLocaleString('fr-FR') + ' F';
    
    const inp = document.getElementById('reglementMontantInput');
    inp.value = reste;
    inp.max = reste;
}

function fermerModalReglement() {
    document.getElementById('modalReglement').classList.remove('open');
}
</script>
@endsection
