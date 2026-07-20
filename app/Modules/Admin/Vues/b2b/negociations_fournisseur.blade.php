@extends('admin::gabarits.application')
@section('titre', 'Négociations B2B (Ventes)')
@section('topbar_titre', 'B2B — Négociations Fournisseur')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-store-slash" style="color:var(--primary); margin-right:8px;"></i> Demandes de Prix Reçues</h1>
        <p>Gérez et négociez les offres tarifaires avec vos clients B2B.</p>
    </div>
</div>

{{-- Zone d'alertes --}}
@if(session('succes'))
    <div class="alert alert-success" style="margin-bottom:20px;">
        <i class="fas fa-check-circle"></i> {{ session('succes') }}
    </div>
@endif
@if(session('erreur'))
    <div class="alert alert-danger" style="margin-bottom:20px;">
        <i class="fas fa-exclamation-circle"></i> {{ session('erreur') }}
    </div>
@endif

<div class="card">
    <div class="table-wrap">
        @if($negociations->isEmpty())
            <div style="padding:60px; text-align:center; color:var(--text-3);">
                <i class="fas fa-comments-dollar" style="font-size:48px; display:block; margin-bottom:16px; opacity:.3;"></i>
                Aucune demande de prix reçue pour le moment.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">Client B2B</th>
                        <th style="width: 25%;">Produits demandés</th>
                        <th style="width: 15%; text-align: center;">Statut</th>
                        <th style="width: 15%; text-align: right;">Prix final</th>
                        <th style="width: 20%; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($negociations as $neg)
                        <tr>
                            <td style="font-weight:600;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="width:30px; height:30px; border-radius:50%; background:var(--success); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800;">
                                        {{ substr($neg->entrepriseClient->nom, 0, 2) }}
                                    </div>
                                    <div>
                                        {{ $neg->entrepriseClient->nom }}
                                        <div style="font-size:10px; color:var(--text-3); margin-top:2px;">NCC: {{ $neg->entrepriseClient->ncc }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:12.5px; color:var(--text-2);">
                                @php
                                    $noms = collect($neg->produits_demandes)->pluck('nom')->join(', ');
                                @endphp
                                {{ Str::limit($noms, 50) }}
                            </td>
                            <td style="text-align: center;">
                                @if($neg->statut === 'RFQ')
                                    <span class="badge" style="background:#fff7ed; color:#ea580c; padding:4px 10px; border-radius:20px; font-weight:700; border:1px solid rgba(234,88,12,0.2);">Nouveau RFQ</span>
                                @elseif($neg->statut === 'Negociation_Client')
                                    <span class="badge" style="background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:20px; font-weight:700;">Proposition Client</span>
                                @elseif($neg->statut === 'Negociation_Fournisseur')
                                    <span class="badge" style="background:#f8fafc; color:#64748b; padding:4px 10px; border-radius:20px; font-weight:700;">En attente Client</span>
                                @elseif($neg->statut === 'Termine')
                                    <span class="badge" style="background:#e6fdf5; color:#059669; padding:4px 10px; border-radius:20px; font-weight:700;">Terminé / Facturé</span>
                                @elseif($neg->statut === 'Refuse')
                                    <span class="badge badge-danger">Refusé</span>
                                @else
                                    <span class="badge badge-gray">{{ $neg->statut }}</span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight:800; color:var(--text);">
                                {{ $neg->prix_final ? number_format($neg->prix_final, 0, ',', ' ') . ' F' : '—' }}
                            </td>
                            <td style="text-align: right;">
                                @if($neg->statut === 'Termine')
                                    <span style="font-size:12px; color:var(--success); font-weight:700;"><i class="fas fa-check-circle"></i> Complété</span>
                                @elseif($neg->statut === 'Refuse')
                                    <span style="font-size:12px; color:var(--danger); font-weight:700;"><i class="fas fa-ban"></i> Annulé</span>
                                @else
                                    <div style="display:inline-flex; gap:6px;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="ouvrirModalNegociation({{ json_encode($neg) }})">
                                            <i class="fas fa-comments-dollar"></i> Négocier
                                        </button>
                                        <button type="button" class="btn btn-success btn-sm" style="font-weight:700; font-size:11px;" onclick="ouvrirModalFinalisation({{ json_encode($neg) }})">
                                            <i class="fas fa-file-invoice"></i> Finaliser
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding: 10px 16px;">
                {{ $negociations->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal Négociation Fournisseur --}}
<div class="modal-overlay" id="modalNegociation">
    <div class="modal" style="max-width: 720px;">
        <div class="modal-header">
            <h3><i class="fas fa-comments-dollar"></i> Négocier l'offre</h3>
            <button type="button" class="modal-close" onclick="fermerModalNegociation()">&times;</button>
        </div>
        <form method="POST" id="formProposer">
            @csrf
            
            <div class="grid-2" style="margin-bottom: 20px;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h4 style="font-size:12px; text-transform:uppercase; color:var(--text-3); margin:0;">Composition & Prix</h4>
                        <button type="button" class="btn btn-outline btn-sm" onclick="lancerVerificationStock()" style="padding:4px 8px; font-size:10px;">
                            <i class="fas fa-boxes-stacked"></i> Vérifier le Stock
                        </button>
                    </div>
                    
                    {{-- Alertes de stock dynamique --}}
                    <div id="stock-feedback" style="display:none; margin-bottom:12px; font-size:11.5px; padding:8px 12px; border-radius:6px;"></div>

                    <div id="modal-produits-container"></div>
                </div>
                <div>
                    <h4 style="font-size:12px; text-transform:uppercase; color:var(--text-3); margin-bottom:10px;">Fil des discussions</h4>
                    <div id="modal-chat-container" style="max-height:220px; overflow-y:auto; border:1px solid var(--border); border-radius:8px; padding:10px; background:#f8fafc; font-size:12px; display:flex; flex-direction:column; gap:8px; margin-bottom:12px;"></div>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-size:10px;">Votre message</label>
                        <textarea name="message" id="modal-message" class="form-control" rows="2" placeholder="Ajouter un commentaire..."></textarea>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); padding-top:16px;">
                <button type="button" class="btn btn-outline" onclick="fermerModalNegociation()">Fermer</button>
                <button type="submit" name="statut_action" value="Refuse" class="btn btn-danger" onclick="setStatutAction('Refuse')">Annuler / Refuser</button>
                <button type="submit" name="statut_action" value="Negociation_Fournisseur" class="btn btn-primary" onclick="setStatutAction('Negociation_Fournisseur')">Envoyer Proposition</button>
            </div>
            
            <input type="hidden" name="statut_action" id="statut_action_input" value="Negociation_Fournisseur">
        </form>
    </div>
</div>

{{-- Modal Finalisation Vente/Livraison B2B --}}
<div class="modal-overlay" id="modalFinalisation">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice"></i> Finaliser et Facturer (B2B)</h3>
            <button type="button" class="modal-close" onclick="fermerModalFinalisation()">&times;</button>
        </div>
        <form method="POST" id="formFinaliser">
            @csrf
            
            @php
                $pdvs = \App\Modules\Admin\Modeles\PointDeVente::where('entreprise_id', auth()->user()->entreprise_id)->get();
            @endphp

            <div class="form-group">
                <label class="form-label">Point de Vente Expéditeur <span style="color:var(--danger)">*</span></label>
                <select name="point_de_vente_id" class="form-control" required>
                    @foreach($pdvs as $pdv)
                        <option value="{{ $pdv->id }}" {{ session('point_de_vente_actif_id') == $pdv->id ? 'selected' : '' }}>{{ $pdv->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">Type de Facturation <span style="color:var(--danger)">*</span></label>
                <select name="type_facturation" class="form-control" required>
                    <option value="commande" selected>Facturer sur Quantité Commandée</option>
                    <option value="disponible">Facturer uniquement sur Quantité en Stock</option>
                </select>
            </div>

            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">Mode de Paiement convenu <span style="color:var(--danger)">*</span></label>
                <select name="mode_paiement" class="form-control" required>
                    <option value="Crédit">Crédit (Facture à terme)</option>
                    <option value="Espèces">Espèces (Au comptoir)</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Virement">Virement bancaire</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); padding-top:16px; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="fermerModalFinalisation()">Fermer</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check-double"></i> Émettre la facture & Livrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    let activeNegId = null;

    function ouvrirModalNegociation(neg) {
        activeNegId = neg.id;
        document.getElementById('formProposer').action = `/admin/b2b/negociation/${neg.id}/proposer`;
        document.getElementById('stock-feedback').style.display = 'none';

        // 1. Produits
        const pContainer = document.getElementById('modal-produits-container');
        pContainer.innerHTML = '';
        
        neg.produits_demandes.forEach((p, idx) => {
            const div = document.createElement('div');
            div.style.marginBottom = '12px';
            div.innerHTML = `
                <div style="font-weight:700; font-size:12.5px; color:var(--text);">${p.nom}</div>
                <div style="font-size:11px; color:var(--text-2); margin-top:2px; margin-bottom:6px;">Demande : ${p.quantite} ${p.unite}</div>
                <div class="form-group" style="margin-bottom:0;">
                    <input type="number" name="prix[${idx}]" value="${p.prix_propose}" class="form-control" style="height:36px; padding:6px 10px;" required>
                </div>
            `;
            pContainer.appendChild(div);
        });

        // 2. Chat
        const chatContainer = document.getElementById('modal-chat-container');
        chatContainer.innerHTML = '';
        
        const hist = neg.historique_discussions || [];
        if (hist.length === 0) {
            chatContainer.innerHTML = '<div style="color:var(--text-3); text-align:center;">Aucune discussion commencée.</div>';
        } else {
            hist.forEach(h => {
                const isMe = h.role === 'Fournisseur';
                const div = document.createElement('div');
                div.style.alignSelf = isMe ? 'flex-end' : 'flex-start';
                div.style.background = isMe ? 'rgba(16,185,129,0.1)' : '#ffffff';
                div.style.border = isMe ? 'none' : '1px solid var(--border)';
                div.style.borderRadius = '8px';
                div.style.padding = '8px 10px';
                div.style.maxWidth = '85%';
                div.innerHTML = `
                    <div style="font-weight:700; font-size:11px; color:var(--text);">${h.auteur} (${h.role})</div>
                    <div style="margin-top:4px;">${h.message}</div>
                    <div style="font-size:9px; color:var(--text-3); text-align:right; margin-top:4px;">${h.date}</div>
                `;
                chatContainer.appendChild(div);
            });
        }
        
        chatContainer.scrollTop = chatContainer.scrollHeight;
        document.getElementById('modalNegociation').classList.add('open');
    }

    function fermerModalNegociation() {
        document.getElementById('modalNegociation').classList.remove('open');
        document.getElementById('formProposer').reset();
        activeNegId = null;
    }

    function setStatutAction(val) {
        document.getElementById('statut_action_input').value = val;
    }

    function lancerVerificationStock() {
        if (!activeNegId) return;
        
        const feedback = document.getElementById('stock-feedback');
        feedback.style.display = 'block';
        feedback.className = '';
        feedback.style.background = '#f8fafc';
        feedback.style.color = 'var(--text)';
        feedback.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyse des stocks en cours...';

        fetch(`/admin/b2b/negociation/${activeNegId}/stock`)
            .then(res => res.json())
            .then(data => {
                let html = `<strong>Site de contrôle : ${data.point_de_vente}</strong><br><ul style="margin:5px 0 0 15px; padding:0;">`;
                let stockOk = true;

                data.verifications.forEach(v => {
                    const isOk = v.statut === 'OK';
                    if (!isOk) stockOk = false;
                    html += `<li style="color:${isOk ? 'var(--success)' : 'var(--danger)'};">
                        ${v.nom} : Requis ${v.requis} · Dispo ${v.dispo} (${v.statut})
                    </li>`;
                });
                html += '</ul>';

                feedback.style.background = stockOk ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)';
                feedback.style.color = stockOk ? 'var(--success)' : 'var(--danger)';
                feedback.innerHTML = html;
            })
            .catch(err => {
                feedback.innerHTML = 'Erreur lors de la vérification des stocks.';
            });
    }

    function ouvrirModalFinalisation(neg) {
        document.getElementById('formFinaliser').action = `/admin/b2b/negociation/${neg.id}/finaliser`;
        document.getElementById('modalFinalisation').classList.add('open');
    }

    function fermerModalFinalisation() {
        document.getElementById('modalFinalisation').classList.remove('open');
    }
</script>
@endsection
