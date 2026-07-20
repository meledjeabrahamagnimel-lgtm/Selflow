@extends('admin::gabarits.application')
@section('titre', 'Négociations B2B (Achats)')
@section('topbar_titre', 'B2B — Négociations Client')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-comments-dollar" style="color:var(--primary); margin-right:8px;"></i> Négociations B2B</h1>
        <p>Suivez vos demandes de prix (RFQ) et vos échanges tarifaires inter-entreprises.</p>
    </div>
    <a href="{{ route('admin.achats.nouveau') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvelle demande (RFQ)
    </a>
</div>

{{-- Zone d'alertes --}}
@if(session('succes'))
    <div class="alert alert-success" style="margin-bottom:20px;">
        <i class="fas fa-check-circle"></i> {{ session('succes') }}
    </div>
@endif

<div class="card">
    <div class="table-wrap">
        @if($negociations->isEmpty())
            <div style="padding:60px; text-align:center; color:var(--text-3);">
                <i class="fas fa-comments-dollar" style="font-size:48px; display:block; margin-bottom:16px; opacity:.3;"></i>
                Aucune négociation B2B en cours.<br>
                Lancez une demande de prix (RFQ) depuis le module <strong>Nouvel Achat</strong>.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">Fournisseur B2B</th>
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
                                    <div style="width:30px; height:30px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800;">
                                        {{ substr($neg->entrepriseFournisseur->nom, 0, 2) }}
                                    </div>
                                    <div>
                                        {{ $neg->entrepriseFournisseur->nom }}
                                        <div style="font-size:10px; color:var(--text-3); margin-top:2px;">NCC: {{ $neg->entrepriseFournisseur->ncc }}</div>
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
                                    <span class="badge" style="background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:20px; font-weight:700;">RFQ Envoyé</span>
                                @elseif($neg->statut === 'Negociation_Fournisseur')
                                    <span class="badge" style="background:#fff7ed; color:#ea580c; padding:4px 10px; border-radius:20px; font-weight:700; border: 1px solid rgba(234,88,12,0.2);">Proposition Reçue</span>
                                @elseif($neg->statut === 'Negociation_Client')
                                    <span class="badge" style="background:#f8fafc; color:#64748b; padding:4px 10px; border-radius:20px; font-weight:700;">En attente Fournisseur</span>
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
                                    <button type="button" class="btn btn-primary btn-sm" onclick="ouvrirModalNegociation({{ json_encode($neg) }})">
                                        <i class="fas fa-comments-dollar"></i> Négocier
                                    </button>
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

{{-- Modal Négociation --}}
<div class="modal-overlay" id="modalNegociation">
    <div class="modal" style="max-width: 680px;">
        <div class="modal-header">
            <h3><i class="fas fa-comments-dollar"></i> Espace de Négociation</h3>
            <button type="button" class="modal-close" onclick="fermerModalNegociation()">&times;</button>
        </div>
        <form method="POST" id="formProposer">
            @csrf
            
            <div class="grid-2" style="margin-bottom: 20px;">
                <div>
                    <h4 style="font-size:12px; text-transform:uppercase; color:var(--text-3); margin-bottom:10px;">Articles & Prix proposés</h4>
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
                <button type="submit" name="statut_action" value="Refuse" class="btn btn-danger" style="background:#ef4444; color:#fff;" onclick="setStatutAction('Refuse')">Refuser</button>
                <button type="submit" name="statut_action" value="Negociation_Client" class="btn btn-primary" onclick="setStatutAction('Negociation_Client')">Envoyer Proposition</button>
            </div>
            
            <input type="hidden" name="statut_action" id="statut_action_input" value="Negociation_Client">
        </form>
    </div>
</div>

<script>
    function ouvrirModalNegociation(neg) {
        document.getElementById('formProposer').action = `/admin/b2b/negociation/${neg.id}/proposer`;
        
        // 1. Remplir les produits
        const pContainer = document.getElementById('modal-produits-container');
        pContainer.innerHTML = '';
        
        neg.produits_demandes.forEach((p, idx) => {
            const div = document.createElement('div');
            div.style.marginBottom = '12px';
            div.innerHTML = `
                <div style="font-weight:700; font-size:12.5px; color:var(--text);">${p.nom}</div>
                <div style="font-size:11px; color:var(--text-2); margin-top:2px; margin-bottom:6px;">Quantité : ${p.quantite} ${p.unite}</div>
                <div class="form-group" style="margin-bottom:0;">
                    <input type="number" name="prix[${idx}]" value="${p.prix_propose}" class="form-control" style="height:36px; padding:6px 10px;" required>
                </div>
            `;
            pContainer.appendChild(div);
        });

        // 2. Remplir le chat
        const chatContainer = document.getElementById('modal-chat-container');
        chatContainer.innerHTML = '';
        
        const hist = neg.historique_discussions || [];
        if (hist.length === 0) {
            chatContainer.innerHTML = '<div style="color:var(--text-3); text-align:center;">Aucune discussion commencée.</div>';
        } else {
            hist.forEach(h => {
                const isMe = h.role === 'Client';
                const div = document.createElement('div');
                div.style.alignSelf = isMe ? 'flex-end' : 'flex-start';
                div.style.background = isMe ? 'rgba(0,43,92,0.1)' : '#ffffff';
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
        
        // Auto scroll to bottom
        chatContainer.scrollTop = chatContainer.scrollHeight;

        document.getElementById('modalNegociation').classList.add('open');
    }

    function fermerModalNegociation() {
        document.getElementById('modalNegociation').classList.remove('open');
        document.getElementById('formProposer').reset();
    }

    function setStatutAction(val) {
        document.getElementById('statut_action_input').value = val;
    }
</script>
@endsection
