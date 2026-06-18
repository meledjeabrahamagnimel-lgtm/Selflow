@extends('admin::gabarits.application')
@section('titre', 'Facture ' . $vente->numero_facture)
@section('topbar_titre', 'Vente — Facture')

@section('styles')
<style>
    :root {
        --navy: #0D1B3E;
        --yellow: #F9CA24;
        --br: #E2E5EC;
        --tx: #1A1A2E;
        --mu: #6B7280;
        --white: #F5F6FA;
    }

    /* Styles pour la barre de contrôle */
    .controls-card {
        background: #fff;
        border: 0.5px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    
    .tabs {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    
    .tab {
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: 0.5px solid var(--border);
        color: var(--text-2);
        background: #fff;
        transition: all .15s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .tab:hover {
        background: var(--bg3);
        color: var(--text);
    }
    
    .tab.act {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    /* Styles pour l'affichage de la facture */
    .invoice-container {
        display: flex;
        justify-content: center;
        padding: 10px 0;
    }

    .invoice {
        background: #fff;
        border: 0.5px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        width: 100%;
        max-width: 800px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        position: relative;
    }

    .print-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: 0.5px solid var(--border);
        background: #fff;
        color: var(--text);
        transition: all 0.15s;
        text-decoration: none;
    }
    
    .print-btn:hover {
        background: var(--bg3);
    }
    
    .print-btn.main {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    
    .print-btn.main:hover {
        background: var(--primary-d);
    }

    /* Styles pour la table modèle 4 standard */
    .table-m4 {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        margin-bottom: 20px;
        border: 1px solid #000;
    }
    
    .table-m4 th, .table-m4 td {
        border: 1px solid #000;
        padding: 8px 10px;
    }

    /* QR Code styling */
    #qrcode img {
        display: inline-block;
    }

    /* CSS Impression */
    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .sidebar, header, .topbar, .no-print, .banner-alert, .user-dropdown-menu, .sidebar-logo, .sidebar-pdv {
            display: none !important;
        }
        body, .main-wrap, .main-content, main {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            width: 100% !important;
            max-width: 100% !important;
            position: static !important;
        }
        .invoice-container {
            padding: 0 !important;
        }
        .invoice {
            border: none !important;
            box-shadow: none !important;
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            border-radius: 0 !important;
        }
    }
</style>
@endsection

@section('contenu')
<div class="no-print controls-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        @php
            $routeRetour = request()->routeIs('caissier.*') ? route('caissier.ventes.factures') : route('admin.ventes.factures');
        @endphp
        <a href="{{ $routeRetour }}" class="print-btn">
            <i class="fas fa-arrow-left"></i> Retour aux factures
        </a>
        <div style="font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Options du document</div>
    </div>
    
    <div class="tabs" id="model-tabs" style="margin-bottom: 15px;">
        <div class="tab act" onclick="setModel(1, this)"><i class="fas fa-list"></i> Modèle 1 — Classique</div>
        <div class="tab" onclick="setModel(2, this)"><i class="fas fa-columns"></i> Modèle 2 — Élégant</div>
        <div class="tab" onclick="setModel(3, this)"><i class="fas fa-th-large"></i> Modèle 3 — Moderne</div>
        <div class="tab" onclick="setModel(4, this)"><i class="fas fa-file-invoice"></i> Modèle 4 — Standard</div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 0.5px solid var(--border); padding-top: 15px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <button class="tab" id="delivery-toggle-btn" onclick="toggleDeliveryMode()" style="border-color: var(--primary); color: var(--primary);">
                <i class="fas fa-truck-delivery"></i> Passer en Bon de livraison
            </button>
        </div>
        
        <div style="display: flex; gap: 8px; align-items: center;">
            <button class="print-btn main" onclick="telechargerPdf()">
                <i class="fas fa-download"></i> Télécharger PDF
            </button>
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimer
            </button>
            
            @if($vente->etape === 'Devis')
                <button class="print-btn" style="background:var(--warning); color:#fff; border-color:var(--warning);" onclick="executerAction('{{ route('admin.ventes.confirmer', $vente->id) }}')">
                    <i class="fas fa-check-circle"></i> Confirmer la commande
                </button>
            @elseif($vente->etape === 'Bon de commande')
                <button class="print-btn" style="background:#10b981; color:#fff; border-color:#10b981;" onclick="executerAction('{{ route('admin.ventes.facturer', $vente->id) }}')">
                    <i class="fas fa-file-invoice-dollar"></i> Valider & Facturer
                </button>
            @endif
        </div>
    </div>
</div>

<form id="action-form" method="POST" style="display:none;">
    @csrf
</form>

<div class="invoice-container">
    <div id="invoice-wrap"></div>
</div>
@endsection

@section('scripts')
<!-- CDNs pour html2pdf et qrcodejs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
var curModel = 1;
var isDeliveryMode = false;

@php
    $entreprise = $vente->pointDeVente->entreprise;
    $logoUrl = $entreprise->logo_path ? Storage::url($entreprise->logo_path) : null;
@endphp

var DATA = {
    vente: {
        num: {!! json_encode($vente->numero_facture) !!},
        date: {!! json_encode(\Carbon\Carbon::parse($vente->date_vente)->isoFormat('D MMMM YYYY')) !!},
        etape: {!! json_encode($vente->etape) !!},
        remise: {{ $vente->remise ?? 0 }},
        normalise: {{ $vente->normalise ? 'true' : 'false' }},
        qr_code_data: {!! json_encode($vente->qr_code_data) !!},
        client: {
            nom: {!! json_encode($vente->client?->nom ?? 'Client de passage') !!},
            adresse: {!! json_encode($vente->client?->adresse ?? '') !!},
            tel: {!! json_encode($vente->client?->telephone ?? '') !!},
            email: {!! json_encode($vente->client?->email ?? '') !!},
            ncc: {!! json_encode($vente->client?->ncc ?? '') !!},
            regime: {!! json_encode($vente->client?->regime_imposition ?? '') !!}
        },
        items: [
            @foreach($vente->details as $detail)
            {
                ref: {!! json_encode($detail->produit?->reference ?? 'REF-VIR-' . str_pad($detail->id, 3, '0', STR_PAD_LEFT)) !!},
                desc: {!! json_encode($detail->libelle_virtuel ?? ($detail->produit?->nom ?? 'Article')) !!},
                qty: {{ $detail->quantite }},
                unite: {!! json_encode($detail->unite ?? 'Unité') !!},
                pu: {{ $detail->prix_unitaire }},
                tva: {{ $detail->produit ? $detail->produit->taux_tva : ($detail->montant_tva > 0 ? 18 : 0) }}
            },
            @endforeach
        ],
        mode: {!! json_encode($vente->mode_paiement) !!},
        statut: {!! json_encode($vente->statut) !!},
        deja_paye: {{ $dejaPaye ?? 0 }}
    }
};

var COMPANY = {
    nom: {!! json_encode($entreprise->nom) !!},
    pdv: {!! json_encode($vente->pointDeVente->nom) !!},
    adresse: {!! json_encode($entreprise->adresse ?? '') !!},
    tel: {!! json_encode($entreprise->telephone ?? '') !!},
    email: {!! json_encode($entreprise->email ?? '') !!},
    rccm: {!! json_encode($entreprise->rccm ?? '') !!},
    ncc: {!! json_encode($entreprise->ncc ?? '') !!},
    cc: {!! json_encode($entreprise->compte_contribuable ?? '') !!},
    regime: {!! json_encode($entreprise->regime_imposition ?? '') !!},
    centre_impots: {!! json_encode($entreprise->centre_impots ?? '') !!},
    ref_bancaire: {!! json_encode($entreprise->ref_bancaire ?? '') !!},
    logo: {!! json_encode($logoUrl) !!},
    vendeur: {!! json_encode($vendeur->nom ?? '') !!}
};

function fmt(n) {
    return Math.round(n).toLocaleString('fr-FR') + ' F';
}

function fmtFcfa(n) {
    return Math.round(n).toLocaleString('fr-FR') + ' FCFA';
}

function calcItems(items, remiseGlobal) {
    remiseGlobal = parseFloat(remiseGlobal || 0);
    var rows = items.map(it => {
        var ht = it.qty * it.pu;
        var tva = ht * it.tva / 100;
        return { ...it, ht, tva_amt: tva, ttc: ht + tva };
    });
    var tot_ht = rows.reduce((s, r) => s + r.ht, 0);
    var tot_ht_net = Math.max(0, tot_ht - remiseGlobal);
    var hasTva = rows.some(r => r.tva > 0);
    var tot_tva = hasTva ? (tot_ht_net * 0.18) : 0;
    var tot_ttc = tot_ht_net + tot_tva;
    return { rows, tot_ht, tot_ht_net, tot_tva, tot_ttc, remiseGlobal };
}

function executerAction(url) {
    if (confirm("Confirmer cette action ?")) {
        var form = document.getElementById('action-form');
        form.action = url;
        form.submit();
    }
}

function toggleDeliveryMode() {
    isDeliveryMode = !isDeliveryMode;
    var btn = document.getElementById('delivery-toggle-btn');
    if (isDeliveryMode) {
        btn.classList.add('act');
        btn.innerHTML = '<i class="fas fa-truck-delivery"></i> Mode Facture / Commande';
    } else {
        btn.classList.remove('act');
        btn.innerHTML = '<i class="fas fa-truck-delivery"></i> Passer en Bon de livraison';
    }
    render();
}

function telechargerPdf() {
    // Hide controls card temporarily
    var controls = document.querySelector('.controls-card');
    if (controls) controls.style.display = 'none';

    var element = document.querySelector('.invoice');
    var opt = {
        margin:       [5, 5, 5, 5],
        filename:     (isDeliveryMode ? 'BL_' : 'Facture_') + DATA.vente.num + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2.5, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak:    { mode: 'avoid-all' }
    };
    
    html2pdf().set(opt).from(element).save().then(function() {
        if (controls) controls.style.display = '';
    }).catch(function(err) {
        if (controls) controls.style.display = '';
        console.error(err);
    });
}

function logoHtml(src, alt, maxH) {
    if (!src) return '';
    return `<img src="${src}" alt="${alt}" style="max-height:${maxH}px;max-width:140px;object-fit:contain;">`;
}

function fiscalLinesSeller(sep) {
    var lines = [];
    if (COMPANY.ncc)           lines.push('NCC : ' + COMPANY.ncc);
    if (COMPANY.regime)        lines.push('Régime : ' + COMPANY.regime);
    if (COMPANY.centre_impots) lines.push('Centre : ' + COMPANY.centre_impots);
    if (COMPANY.rccm)          lines.push('RCCM : ' + COMPANY.rccm);
    if (COMPANY.cc)            lines.push('CC : ' + COMPANY.cc);
    return lines.join(sep || ' · ');
}

function fiscalLinesClient(c, sep) {
    var lines = [];
    if (c.adresse) lines.push(c.adresse);
    if (c.tel)     lines.push('Tél : ' + c.tel);
    if (c.email)   lines.push(c.email);
    if (c.ncc)     lines.push('NCC : ' + c.ncc);
    if (c.regime)  lines.push('Régime : ' + c.regime);
    return lines.join(sep || '<br>');
}

function getDocTitle(d) {
    if (isDeliveryMode) return "BON DE LIVRAISON";
    if (d.etape === 'Devis') return "DEVIS ESTIMATIF";
    if (d.etape === 'Bon de commande') return "BON DE COMMANDE";
    return d.normalise ? "FACTURE NORMALISÉE" : "FACTURE";
}

function getBadgeText(d) {
    if (isDeliveryMode) return "LIVRAISON";
    if (d.etape === 'Devis') return "DEVIS";
    if (d.etape === 'Bon de commande') return "COMMANDE";
    return "VENTE";
}

function statusColor(statut, themeColor) {
    if (statut === 'Payé')   return '#059669';
    if (statut === 'Crédit') return '#dc2626';
    if (statut === 'Avance') return '#d97706';
    return themeColor;
}

function getThemeColors() {
    var themes = {
        1: { color: '#0F6E56', bg: '#E1F5EE', tx: '#085041' },
        2: { color: '#185FA5', bg: '#E6F1FB', tx: '#0C447C' },
        3: { color: '#4F46E5', bg: '#EEF2FF', tx: '#3730A3' },
        4: { color: '#1E293B', bg: '#F1F5F9', tx: '#0F172A' }
    };
    return themes[curModel];
}

function model1(d) {
    var c = calcItems(d.items, d.remise);
    var theme = getThemeColors();
    var sColor = statusColor(d.statut, theme.color);
    var title = getDocTitle(d);
    var badge = getBadgeText(d);
    
    return `
<div class="invoice">
    <div style="padding:28px 32px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                ${COMPANY.logo ? `<div style="flex-shrink:0;">${logoHtml(COMPANY.logo, COMPANY.nom, 52)}</div>` : `<div style="width:38px;height:38px;border-radius:9px;background:${theme.color};display:flex;align-items:center;justify-content:center"><i class="fas fa-bolt" style="color:#fff;font-size:17px"></i></div>`}
                <div>
                    <div style="font-size:15px;font-weight:700;color:var(--tx)">${COMPANY.nom}</div>
                    <div style="font-size:11px;color:var(--mu);margin-top:1px">${COMPANY.pdv}</div>
                    <div style="font-size:10.5px;color:var(--mu);line-height:1.7;margin-top:5px">
                        ${COMPANY.adresse ? COMPANY.adresse + '<br>' : ''}
                        ${COMPANY.tel ? 'Tél : ' + COMPANY.tel : ''}${COMPANY.email ? ' · ' + COMPANY.email : ''}<br>
                        ${fiscalLinesSeller('<br>')}
                    </div>
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;background:${theme.bg};color:${theme.tx};margin-bottom:6px">${badge}</div>
                <div style="font-size:22px;font-weight:800;color:var(--tx)">${title}</div>
                <div style="font-size:13px;color:var(--mu);margin-top:2px;font-weight:600;">${d.num}</div>
            </div>
        </div>
        <div style="height:0.5px;background:var(--border);margin-bottom:18px;"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px">
            <div style="background:var(--white);border-radius:8px;padding:12px 14px;border:0.5px solid var(--border)">
                <div style="font-size:10px;font-weight:700;color:${theme.color};text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px">Client</div>
                <div style="font-size:13px;font-weight:700;color:var(--tx)">${d.client.nom}</div>
                <div style="font-size:11px;color:var(--mu);margin-top:4px;line-height:1.7">${fiscalLinesClient(d.client, '<br>')}</div>
            </div>
            <div style="background:var(--white);border-radius:8px;padding:12px 14px;border:0.5px solid var(--border)">
                <div style="font-size:10px;font-weight:700;color:${theme.color};text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px">Informations</div>
                ${[
                    ["Date d'émission", d.date],
                    ["Mode de paiement", isDeliveryMode ? null : d.mode],
                    ["Vendeur", COMPANY.vendeur],
                    ["Point de vente", COMPANY.pdv]
                ].map(r => r[1] ? `<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">${r[0]}</span><span style="font-weight:600;color:var(--tx)">${r[1]}</span></div>` : '').join('')}
                ${isDeliveryMode ? '' : `
                <div style="display:flex;justify-content:space-between;font-size:11px;padding:5px 0">
                    <span style="color:var(--mu)">Statut</span>
                    <span style="font-weight:700;color:${sColor};background:${sColor}18;padding:2px 10px;border-radius:20px">${d.statut}</span>
                </div>
                `}
            </div>
        </div>
        
        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px">
            <thead>
                <tr style="background:${theme.color};color:#fff;">
                    <th style="padding:9px 12px;text-align:left;color:#fff;background:${theme.color};font-weight:600;width:15%;">Réf.</th>
                    <th style="padding:9px 12px;text-align:left;color:#fff;background:${theme.color};font-weight:600">Description</th>
                    <th style="padding:9px 12px;text-align:center;color:#fff;background:${theme.color};font-weight:600;width:12%">Unité</th>
                    <th style="padding:9px 12px;text-align:right;color:#fff;background:${theme.color};font-weight:600;width:8%">Qté</th>
                    ${isDeliveryMode ? '' : `
                    <th style="padding:9px 12px;text-align:right;color:#fff;background:${theme.color};font-weight:600;width:16%">P.U. HT</th>
                    <th style="padding:9px 12px;text-align:right;color:#fff;background:${theme.color};font-weight:600;width:10%">TVA</th>
                    <th style="padding:9px 12px;text-align:right;color:#fff;background:${theme.color};font-weight:600;width:18%">Total TTC</th>
                    `}
                </tr>
            </thead>
            <tbody>
                ${c.rows.map((r, i) => `<tr style="background:${i % 2 === 0 ? '#fff' : '#F9FAFB'}">
                    <td style="padding:9px 12px;color:var(--mu);font-weight:500;">${r.ref}</td>
                    <td style="padding:9px 12px;font-weight:600;color:var(--tx)">${r.desc}</td>
                    <td style="padding:9px 12px;text-align:center;color:var(--tx)">${r.unite || 'Unité'}</td>
                    <td style="padding:9px 12px;text-align:right;color:var(--tx)">${r.qty}</td>
                    ${isDeliveryMode ? '' : `
                    <td style="padding:9px 12px;text-align:right;color:var(--tx)">${fmt(r.pu)}</td>
                    <td style="padding:9px 12px;text-align:right;color:var(--mu)">${r.tva}%</td>
                    <td style="padding:9px 12px;text-align:right;font-weight:700;color:var(--tx)">${fmt(r.ttc)}</td>
                    `}
                </tr>`).join('')}
            </tbody>
        </table>
        
        ${isDeliveryMode ? `
        <div style="border-top:0.5px solid var(--border);padding-top:14px;margin-top:40px;display:flex;justify-content:space-between;align-items:flex-end">
            <div style="font-size:11px;color:var(--mu);line-height:1.7">Merci pour votre confiance.<br>Document généré par <strong>Selflow</strong> · selflow.app</div>
            <div style="display:flex; gap: 20px;">
                <div style="text-align:center;font-size:10px;color:var(--mu)">Signature Client (Décharge)<br><div style="width:120px;height:50px;border:0.5px dashed var(--br);border-radius:4px;margin-top:4px"></div></div>
                <div style="text-align:center;font-size:10px;color:var(--mu)">Livreur / Société<br><div style="width:120px;height:50px;border:0.5px dashed var(--br);border-radius:4px;margin-top:4px"></div></div>
            </div>
        </div>
        ` : `
        <div style="display:flex;justify-content:space-between;margin-bottom:20px;align-items:flex-start;">
            <div style="flex:1;">
                ${d.normalise && d.qr_code_data ? `
                <div style="display:inline-block;padding:5px;border:1px solid #e2e5ec;background:#fff;margin-top:10px;">
                    <div id="qrcode"></div>
                </div>` : ''}
            </div>
            <div style="width:240px">
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">Sous-total Brut HT</span><span>${fmtFcfa(c.tot_ht)}</span></div>
                ${c.remiseGlobal > 0 ? `<div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--border);color:#dc2626;font-weight:600;"><span>Remise</span><span>-${fmtFcfa(c.remiseGlobal)}</span></div>` : ''}
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">Total Net HT</span><span>${fmtFcfa(c.tot_ht_net)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">TVA (18%)</span><span>${fmtFcfa(c.tot_tva)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:10px 0 6px;font-size:15px;font-weight:800;color:var(--tx);border-top:1.5px solid ${theme.color};"><span>TOTAL TTC</span><span>${fmtFcfa(c.tot_ttc)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">Montant Réglé</span><span>${fmtFcfa(d.deja_paye)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;color:${d.deja_paye < c.tot_ttc ? '#dc2626' : '#059669'};font-weight:700;"><span style="text-transform:uppercase;">Reste à payer</span><span>${fmtFcfa(Math.max(0, c.tot_ttc - d.deja_paye))}</span></div>
            </div>
        </div>
        ${COMPANY.ref_bancaire ? `<div style="margin-bottom:14px;padding:10px 14px;background:var(--white);border-radius:7px;border:0.5px solid var(--border);font-size:11px;color:var(--mu);line-height:1.7;"><span style="font-weight:600;color:var(--tx)">Références bancaires : </span>${COMPANY.ref_bancaire}</div>` : ''}
        <div style="border-top:0.5px solid var(--border);padding-top:14px;display:flex;justify-content:space-between;align-items:flex-end">
            <div style="font-size:11px;color:var(--mu);line-height:1.7">Merci pour votre confiance.<br>Document généré par <strong>Selflow</strong> · selflow.app</div>
            <div style="text-align:right;font-size:11px;color:var(--mu)">Signature / Cachet<br><div style="width:100px;height:40px;border:0.5px dashed var(--border);border-radius:4px;margin-top:4px"></div></div>
        </div>
        `}
    </div>
</div>`;
}

function model2(d) {
    var c = calcItems(d.items, d.remise);
    var theme = getThemeColors();
    var sColor = statusColor(d.statut, theme.color);
    var title = getDocTitle(d);
    var badge = getBadgeText(d);
    
    return `
<div class="invoice">
    <div style="display:flex">
        <div style="width:210px;background:${theme.color};padding:28px 20px;flex-shrink:0;display:flex;flex-direction:column">
            <div style="margin-bottom:16px;text-align:center">
                ${COMPANY.logo ? logoHtml(COMPANY.logo, COMPANY.nom, 54) : `<div style="width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.2);display:inline-flex;align-items:center;justify-content:center"><i class="fas fa-bolt" style="color:#fff;font-size:16px"></i></div>`}
            </div>
            <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:4px">${COMPANY.nom}</div>
            <div style="font-size:10px;color:rgba(255,255,255,.6);margin-bottom:14px">${COMPANY.pdv}</div>
            <div style="font-size:10.5px;color:rgba(255,255,255,.75);line-height:1.8;font-weight:500;">
                ${COMPANY.adresse ? COMPANY.adresse + '<br>' : ''}
                ${COMPANY.tel ? 'Tél : ' + COMPANY.tel + '<br>' : ''}
                ${COMPANY.email ? COMPANY.email + '<br>' : ''}
                ${COMPANY.ncc ? '<br>NCC : ' + COMPANY.ncc : ''}
                ${COMPANY.regime ? '<br>Rég. : ' + COMPANY.regime : ''}
                ${COMPANY.centre_impots ? '<br>' + COMPANY.centre_impots : ''}
                ${COMPANY.rccm ? '<br>RCCM : ' + COMPANY.rccm : ''}
            </div>
            ${!isDeliveryMode && d.normalise && d.qr_code_data ? `
            <div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,.2);text-align:center;">
                <div style="display:inline-block;padding:5px;background:#fff;border-radius:4px;">
                    <div id="qrcode"></div>
                </div>
            </div>` : ''}
            <div style="margin-top:auto;padding-top:16px;border-top:1px solid rgba(255,255,255,.15)">
                <div style="font-size:9.5px;color:rgba(255,255,255,.5);margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px">Vendeur</div>
                <div style="font-size:11px;color:rgba(255,255,255,.85);font-weight:600;">${COMPANY.vendeur || '—'}</div>
            </div>
        </div>
        <div style="flex:1;padding:28px 24px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
                <div>
                    <div style="font-size:22px;font-weight:800;color:var(--tx)">${title}</div>
                    <div style="font-size:12px;color:var(--mu);margin-top:2px;font-weight:600;">${d.num}</div>
                </div>
                <div style="padding:5px 14px;border-radius:20px;font-size:11px;font-weight:600;background:${theme.bg};color:${theme.tx}">${badge}</div>
            </div>
            <div style="margin-bottom:16px;padding:12px 14px;background:var(--white);border-radius:8px;border:0.5px solid var(--border)">
                <div style="font-size:10px;color:${theme.color};text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;font-weight:700;">Client</div>
                <div style="font-size:13px;font-weight:700;color:var(--tx)">${d.client.nom}</div>
                <div style="font-size:11px;color:var(--mu);margin-top:4px;line-height:1.7;">${fiscalLinesClient(d.client, '<br>')}</div>
                <div style="display:flex;gap:14px;margin-top:10px;flex-wrap:wrap;">
                    <div style="font-size:11px"><span style="color:var(--mu)">Date : </span><span style="color:var(--tx);font-weight:600;">${d.date}</span></div>
                    ${isDeliveryMode ? '' : `
                    <div style="font-size:11px"><span style="color:var(--mu)">Paiement : </span><span style="color:var(--tx);font-weight:600;">${d.mode}</span></div>
                    <div style="font-size:11px"><span style="color:var(--mu)">Statut : </span><span style="font-weight:700;color:${sColor}">${d.statut}</span></div>
                    `}
                </div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px">
                <thead>
                    <tr style="border-bottom:1.5px solid ${theme.color}">
                        <th style="padding:7px 0;text-align:left;color:${theme.color};font-weight:700;width:18%">Réf.</th>
                        <th style="padding:7px 6px;text-align:left;color:${theme.color};font-weight:700">Article</th>
                        <th style="padding:7px 6px;text-align:center;color:${theme.color};font-weight:700;width:12%">Unité</th>
                        <th style="padding:7px 6px;text-align:right;color:${theme.color};font-weight:700;width:10%">Qté</th>
                        ${isDeliveryMode ? '' : `
                        <th style="padding:7px 6px;text-align:right;color:${theme.color};font-weight:700;width:18%">P.U. HT</th>
                        <th style="padding:7px 6px;text-align:right;color:${theme.color};font-weight:700;width:10%">TVA</th>
                        <th style="padding:7px 0;text-align:right;color:${theme.color};font-weight:700;width:20%">TTC</th>
                        `}
                    </tr>
                </thead>
                <tbody>
                    ${c.rows.map(r => `<tr style="border-bottom:0.5px solid var(--border)">
                        <td style="padding:8px 0;color:var(--mu);font-weight:500;">${r.ref}</td>
                        <td style="padding:8px 6px;color:var(--tx);font-weight:600;">${r.desc}</td>
                        <td style="padding:8px 6px;text-align:center;color:var(--mu)">${r.unite || 'Unité'}</td>
                        <td style="padding:8px 6px;text-align:right;color:var(--mu)">${r.qty}</td>
                        ${isDeliveryMode ? '' : `
                        <td style="padding:8px 6px;text-align:right;color:var(--mu)">${fmt(r.pu)}</td>
                        <td style="padding:8px 6px;text-align:right;color:var(--mu)">${r.tva}%</td>
                        <td style="padding:8px 0;text-align:right;font-weight:700;color:var(--tx)">${fmt(r.ttc)}</td>
                        `}
                    </tr>`).join('')}
                </tbody>
            </table>
            
            ${isDeliveryMode ? `
            <div style="border-top:0.5px solid var(--border);padding-top:14px;margin-top:40px;display:flex;justify-content:space-between;align-items:flex-end">
                <div style="font-size:10px;color:var(--mu)">Généré automatiquement par <strong>Selflow</strong></div>
                <div style="display:flex; gap: 15px;">
                    <div style="text-align:center;font-size:9px;color:var(--mu)">Signature Client (Décharge)<br><div style="width:100px;height:45px;border:0.5px dashed var(--br);border-radius:4px;margin-top:3px"></div></div>
                    <div style="text-align:center;font-size:9px;color:var(--mu)">Livreur / Société<br><div style="width:100px;height:45px;border:0.5px dashed var(--br);border-radius:4px;margin-top:3px"></div></div>
                </div>
            </div>
            ` : `
            <div style="display:flex;justify-content:flex-end;margin-bottom:14px">
                <div style="width:220px;background:var(--white);border-radius:8px;padding:10px 12px;border:0.5px solid var(--border)">
                    <div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0"><span style="color:var(--mu)">Brut HT</span><span>${fmtFcfa(c.tot_ht)}</span></div>
                    ${c.remiseGlobal > 0 ? `<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;color:#dc2626;font-weight:600;"><span>Remise</span><span>-${fmtFcfa(c.remiseGlobal)}</span></div>` : ''}
                    <div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0"><span style="color:var(--mu)">Net HT</span><span>${fmtFcfa(c.tot_ht_net)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0"><span style="color:var(--mu)">TVA 18%</span><span>${fmtFcfa(c.tot_tva)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:800;padding:6px 0 0;border-top:0.5px solid var(--border);margin-top:4px"><span style="color:var(--tx)">Total TTC</span><span style="color:${theme.color}">${fmtFcfa(c.tot_ttc)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;border-top:0.5px solid var(--border);margin-top:4px"><span style="color:var(--mu)">Montant Réglé</span><span>${fmtFcfa(d.deja_paye)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;font-weight:700;color:${d.deja_paye < c.tot_ttc ? '#dc2626' : '#059669'}"><span>Reste à payer</span><span>${fmtFcfa(Math.max(0, c.tot_ttc - d.deja_paye))}</span></div>
                </div>
            </div>
            ${COMPANY.ref_bancaire ? `<div style="font-size:10.5px;color:var(--mu);line-height:1.7;margin-bottom:10px;"><strong>Réf. bancaires : </strong>${COMPANY.ref_bancaire}</div>` : ''}
            <div style="font-size:11px;color:var(--mu)">Généré automatiquement par <strong>Selflow</strong></div>
            `}
        </div>
    </div>
</div>`;
}

function model3(d) {
    var c = calcItems(d.items, d.remise);
    var theme = getThemeColors();
    var sColor = statusColor(d.statut, theme.color);
    var title = getDocTitle(d);
    var badge = getBadgeText(d);
    
    return `
<div class="invoice">
    <div style="border-top:4px solid ${theme.color};padding:28px 32px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
            <div style="display:flex;align-items:flex-start;gap:12px">
                ${COMPANY.logo ? `<div style="flex-shrink:0;">${logoHtml(COMPANY.logo, COMPANY.nom, 48)}</div>` : `<div style="width:38px;height:38px;border-radius:10px;background:${theme.color};display:flex;align-items:center;justify-content:center"><i class="fas fa-bolt" style="color:#fff;font-size:18px"></i></div>`}
                <div>
                    <div style="font-size:15px;font-weight:700;color:var(--tx)">${COMPANY.nom}</div>
                    <div style="font-size:11px;color:var(--mu)">${COMPANY.pdv}</div>
                    <div style="font-size:10.5px;color:var(--mu);line-height:1.7;margin-top:4px">
                        ${COMPANY.adresse ? COMPANY.adresse + '<br>' : ''}
                        ${COMPANY.tel ? 'Tél : ' + COMPANY.tel : ''}${COMPANY.email ? ' · ' + COMPANY.email : ''}
                    </div>
                </div>
            </div>
            <div style="text-align:right">
                <div style="font-size:24px;font-weight:800;color:var(--tx);letter-spacing:-0.5px;">${title}</div>
                <div style="font-size:12px;margin-top:2px"><span style="color:var(--mu)">N° </span><span style="font-weight:700;color:${theme.color}">${d.num}</span></div>
                <div style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;background:${theme.bg};color:${theme.tx};margin-top:6px">${badge}</div>
            </div>
        </div>

        <div style="height:1px;background:var(--border);margin-bottom:18px"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
            <div style="padding:12px 14px;background:var(--white);border-radius:8px;border:0.5px solid var(--border)">
                <div style="font-size:10px;color:${theme.color};font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Client</div>
                <div style="font-size:13px;font-weight:700;color:var(--tx)">${d.client.nom}</div>
                <div style="font-size:11px;color:var(--mu);margin-top:4px;line-height:1.7">${fiscalLinesClient(d.client, '<br>')}</div>
            </div>
            <div style="padding:12px 14px;background:var(--white);border-radius:8px;border:0.5px solid var(--border)">
                <div style="font-size:10px;color:${theme.color};font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Détails</div>
                <div style="font-size:11px;line-height:1.9">
                    <span style="color:var(--mu)">Date : </span><span style="color:var(--tx);font-weight:600;">${d.date}</span><br>
                    ${isDeliveryMode ? '' : `<span style="color:var(--mu)">Paiement : </span><span style="color:var(--tx);font-weight:600;">${d.mode}</span><br>`}
                    <span style="color:var(--mu)">Vendeur : </span><span style="color:var(--tx);font-weight:600;">${COMPANY.vendeur || '—'}</span><br>
                    ${isDeliveryMode ? '' : `<span style="color:var(--mu)">Statut : </span><span style="font-weight:700;color:${sColor}">${d.statut}</span>`}
                </div>
            </div>
        </div>

        <div style="margin-bottom:14px;padding:10px 14px;background:var(--white);border-radius:8px;border:0.5px solid var(--border);font-size:10.5px;color:var(--mu)">
            <div style="display:flex;flex-wrap:wrap;gap:10px 20px;">
                ${COMPANY.ncc ? `<span>NCC : <strong style="color:var(--tx)">${COMPANY.ncc}</strong></span>` : ''}
                ${COMPANY.regime ? `<span>Rég. : <strong style="color:var(--tx)">${COMPANY.regime}</strong></span>` : ''}
                ${COMPANY.centre_impots ? `<span>Centre : <strong style="color:var(--tx)">${COMPANY.centre_impots}</strong></span>` : ''}
                ${COMPANY.rccm ? `<span>RCCM : <strong style="color:var(--tx)">${COMPANY.rccm}</strong></span>` : ''}
                ${COMPANY.cc ? `<span>CC : <strong style="color:var(--tx)">${COMPANY.cc}</strong></span>` : ''}
            </div>
        </div>

        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px;table-layout:fixed">
            <thead>
                <tr>
                    <th style="padding:9px 10px;text-align:left;border-bottom:2px solid ${theme.color};color:var(--tx);font-weight:700;width:15%">Réf.</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:2px solid ${theme.color};color:var(--tx);font-weight:700;width:30%">Article</th>
                    <th style="padding:9px 10px;text-align:center;border-bottom:2px solid ${theme.color};color:var(--tx);font-weight:700;width:12%">Unité</th>
                    <th style="padding:9px 10px;text-align:right;border-bottom:2px solid ${theme.color};color:var(--tx);font-weight:700;width:10%">Qté</th>
                    ${isDeliveryMode ? '' : `
                    <th style="padding:9px 10px;text-align:right;border-bottom:2px solid ${theme.color};color:var(--tx);font-weight:700;width:16%">P.U. HT</th>
                    <th style="padding:9px 10px;text-align:right;border-bottom:2px solid ${theme.color};color:var(--tx);font-weight:700;width:10%">TVA</th>
                    <th style="padding:9px 10px;text-align:right;border-bottom:2px solid ${theme.color};color:var(--tx);font-weight:700;width:17%">TTC</th>
                    `}
                </tr>
            </thead>
            <tbody>
                ${c.rows.map((r, i) => `<tr style="background:${i % 2 === 1 ? '#F9FAFB' : '#fff'}">
                    <td style="padding:9px 10px;color:var(--mu);font-size:11px;font-weight:500;">${r.ref}</td>
                    <td style="padding:9px 10px;font-weight:600;color:var(--tx)">${r.desc}</td>
                    <td style="padding:9px 10px;text-align:center;color:var(--tx)">${r.unite || 'Unité'}</td>
                    <td style="padding:9px 10px;text-align:right;color:var(--tx)">${r.qty}</td>
                    ${isDeliveryMode ? '' : `
                    <td style="padding:9px 10px;text-align:right;color:var(--mu)">${fmt(r.pu)}</td>
                    <td style="padding:9px 10px;text-align:right;color:var(--mu)">${r.tva}%</td>
                    <td style="padding:9px 10px;text-align:right;font-weight:700;color:var(--tx)">${fmt(r.ttc)}</td>
                    `}
                </tr>`).join('')}
            </tbody>
        </table>
        
        ${isDeliveryMode ? `
        <div style="border-top:0.5px solid var(--border);padding-top:12px;margin-top:40px;display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:11px;color:var(--mu)">Généré par <strong>Selflow</strong> · selflow.app · Document officiel</div>
            <div style="display:flex;gap:16px">
                <div style="text-align:center;font-size:10px;color:var(--mu)">Signature client<br><div style="width:110px;height:45px;border:0.5px dashed var(--br);border-radius:0;margin-top:3px"></div></div>
                <div style="text-align:center;font-size:10px;color:var(--mu)">Livreur / Cachet<br><div style="width:110px;height:45px;border:0.5px dashed var(--br);border-radius:0;margin-top:3px"></div></div>
            </div>
        </div>
        ` : `
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px">
            <div style="font-size:11px;color:var(--mu);max-width:320px;line-height:1.7">
                <span style="color:var(--tx);font-weight:700">Émetteur :</span><br>
                ${COMPANY.adresse ? COMPANY.adresse + '<br>' : ''}
                ${COMPANY.tel ? 'Tél : ' + COMPANY.tel : ''}${COMPANY.email ? ' · ' + COMPANY.email : ''}<br>
                ${COMPANY.ref_bancaire ? '<strong>Réf. bancaires : </strong>' + COMPANY.ref_bancaire : ''}
                ${d.normalise && d.qr_code_data ? `<div style="margin-top:10px;"><div id="qrcode"></div></div>` : ''}
            </div>
            <div style="width:235px">
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">Sous-total Brut HT</span><span>${fmtFcfa(c.tot_ht)}</span></div>
                ${c.remiseGlobal > 0 ? `<div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-bottom:0.5px solid var(--border);color:#dc2626;font-weight:600;"><span>Remise</span><span>-${fmtFcfa(c.remiseGlobal)}</span></div>` : ''}
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">Total Net HT</span><span>${fmtFcfa(c.tot_ht_net)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">TVA 18%</span><span>${fmtFcfa(c.tot_tva)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:8px;margin-top:4px;background:${theme.color};border-radius:6px">
                    <span style="font-size:13px;font-weight:700;color:#fff">Total TTC</span>
                    <span style="font-size:15px;font-weight:800;color:#fff">${fmtFcfa(c.tot_ttc)}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 8px;font-size:12px;border-bottom:0.5px solid var(--border)"><span style="color:var(--mu)">Montant Réglé</span><span style="font-weight:600;">${fmtFcfa(d.deja_paye)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:6px 8px;font-size:12px;color:${d.deja_paye < c.tot_ttc ? '#dc2626' : '#059669'};font-weight:700;"><span>Reste à payer</span><span>${fmtFcfa(Math.max(0, c.tot_ttc - d.deja_paye))}</span></div>
            </div>
        </div>
        <div style="border-top:0.5px solid var(--border);padding-top:12px;display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:11px;color:var(--mu)">Généré par <strong>Selflow</strong> · selflow.app · Document officiel</div>
            <div style="display:flex;gap:16px">
                <div style="text-align:center;font-size:10px;color:var(--mu)">Signature client<br><div style="width:80px;height:36px;border:0.5px dashed var(--border);border-radius:4px;margin-top:3px"></div></div>
                <div style="text-align:center;font-size:10px;color:var(--mu)">Cachet société<br><div style="width:80px;height:36px;border:0.5px dashed var(--border);border-radius:4px;margin-top:3px"></div></div>
            </div>
        </div>
        `}
    </div>
</div>`;
}

function modelStandard(d) {
    var c = calcItems(d.items, d.remise);
    var isNorm = d.normalise;
    var title = getDocTitle(d);
    var hasTva = d.items.some(r => r.tva > 0);
    
    // Contenu des lignes de la table
    var rowsHtml = '';
    if (isDeliveryMode) {
        rowsHtml = c.rows.map(r => `
            <tr style="background:#fff; color:#000;">
                <td style="padding:8px 10px; border:1px solid #000; font-weight:500;">${r.ref}</td>
                <td style="padding:8px 10px; border:1px solid #000; font-weight:700;">${r.desc}</td>
                <td style="padding:8px 10px; border:1px solid #000; text-align:right;">${r.qty}</td>
                <td style="padding:8px 10px; border:1px solid #000; text-align:center;">${r.unite || 'Unité'}</td>
            </tr>
        `).join('');
    } else {
        rowsHtml = c.rows.map(r => `
            <tr style="background:#fff; color:#000;">
                <td style="padding:8px 10px; border:1px solid #000; font-weight:500;">${r.ref}</td>
                <td style="padding:8px 10px; border:1px solid #000; font-weight:700;">${r.desc}</td>
                <td style="padding:8px 10px; border:1px solid #000; text-align:right;">${fmt(r.pu)}</td>
                <td style="padding:8px 10px; border:1px solid #000; text-align:right;">${r.qty}</td>
                <td style="padding:8px 10px; border:1px solid #000; text-align:center;">${r.unite || 'Unité'}</td>
                <td style="padding:8px 10px; border:1px solid #000; text-align:right; color:#000;">${r.tva > 0 ? 'TVA (18%)' : 'TVAD (0)'}</td>
                <td style="padding:8px 10px; border:1px solid #000; text-align:right; color:#000;">0%</td>
                <td style="padding:8px 10px; border:1px solid #000; text-align:right; font-weight:700;">${fmt(r.ht)}</td>
            </tr>
        `).join('');
        
        // Ajouter les totaux fusionnés au tableau principal
        rowsHtml += `
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700; text-transform:uppercase;">TOTAL HT</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700;">${fmt(c.tot_ht)}</td>
            </tr>
            ${c.remiseGlobal > 0 ? `
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700; text-transform:uppercase; color:#dc2626;">Remise</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700; color:#dc2626;">-${fmt(c.remiseGlobal)}</td>
            </tr>
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700; text-transform:uppercase;">TOTAL HT NET</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700;">${fmt(c.tot_ht_net)}</td>
            </tr>
            ` : ''}
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700; text-transform:uppercase;">TVA</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700;">${fmt(c.tot_tva)}</td>
            </tr>
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700; text-transform:uppercase;">TOTAL TTC</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700;">${fmt(c.tot_ttc)}</td>
            </tr>
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700; text-transform:uppercase;">AUTRES TAXES</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700;">0 F</td>
            </tr>
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:900; text-transform:uppercase;">TOTAL A PAYER</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:900;">${fmt(c.tot_ttc)}</td>
            </tr>
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700; text-transform:uppercase;">MONTANT REGLE</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:700;">${fmt(d.deja_paye)}</td>
            </tr>
            <tr style="background:#fff; color:#000;">
                <td colspan="7" style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:900; text-transform:uppercase; color:${d.deja_paye < c.tot_ttc ? '#dc2626' : '#000'};">RESTE A PAYER</td>
                <td style="padding:6px 10px; border:1px solid #000; text-align:right; font-weight:900; color:${d.deja_paye < c.tot_ttc ? '#dc2626' : '#000'};">${fmt(Math.max(0, c.tot_ttc - d.deja_paye))}</td>
            </tr>
        `;
    }

    return `
<div class="invoice" style="border: 1px solid #000; border-radius: 0; background: #fff; box-shadow: none;">
    <div style="padding:40px; color:#000; font-family: 'Inter', sans-serif;">
        <!-- En-tête -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
            <!-- Gauche : Infos Vendeur -->
            <div style="flex:1; font-size:11px; line-height:1.6; color:#000; padding-right: 20px;">
                <div style="border: 1.5px solid #000; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                    <div style="font-size:14px; font-weight:800; text-transform:uppercase; margin-bottom:4px;">${COMPANY.nom}</div>
                    <div>NCC : <strong>${COMPANY.ncc || '—'}</strong></div>
                    <div>Régime d'imposition : <strong>${COMPANY.regime || '—'}</strong></div>
                    <div>Centre des impôts : <strong>${COMPANY.centre_impots || '—'}</strong></div>
                </div>
                
                <div style="margin-top:10px;">
                    RCCM : <strong>${COMPANY.rccm || '—'}</strong><br>
                    <strong>Références bancaires :</strong><br>
                    ${COMPANY.ref_bancaire || '—'}<br>
                    Adresse : ${COMPANY.adresse || '—'}<br>
                    Nº Tel : ${COMPANY.tel || '—'}<br>
                    Mail : ${COMPANY.email || '—'}
                </div>
                
                <div style="margin-top:10px; border-top:1px dashed #000; padding-top:6px;">
                    Nom du vendeur : <strong>${COMPANY.vendeur || '—'}</strong><br>
                    Nom de PDV : <strong>${COMPANY.pdv}</strong><br>
                    Date et heure : <strong>${d.date}</strong><br>
                    ${isDeliveryMode ? '' : `Mode de paiement : <strong>${d.mode}</strong>`}
                </div>
            </div>
            
            <!-- Droite : Logo FNE, Logo Entreprise & Infos Document -->
            <div style="text-align:right; flex-shrink:0; width:280px; display:flex; flex-direction:column; align-items:flex-end;">
                <!-- Logo Entreprise -->
                <div style="height:60px; margin-bottom:15px; display:flex; align-items:center; justify-content:flex-end;">
                    ${COMPANY.logo ? `<img src="${COMPANY.logo}" alt="${COMPANY.nom}" style="max-height:100%; object-fit:contain;">` : `<div style="font-size:16px; font-weight:800; text-transform:uppercase; border:1px solid #000; padding:8px 12px;">${COMPANY.nom}</div>`}
                </div>
                
                <div style="font-size:16px; font-weight:800; letter-spacing:0.5px; color:#000; text-transform:uppercase; margin-bottom:4px;">${title}</div>
                <div style="font-size:12px; font-weight:700; color:#000;">N° ${d.num}</div>
                ${isDeliveryMode ? '' : `<div style="font-size:11px; color:#333; margin-top:2px;">Statut : <span style="font-weight:700; text-transform:uppercase;">${d.statut}</span></div>`}
                
                ${!isDeliveryMode && isNorm && d.qr_code_data ? `
                <div style="margin-top:15px; display:flex; gap: 10px; align-items: center; justify-content:flex-end;">
                    <div style="padding:6px; border:1px solid #000; background:#fff; text-align:center; display:flex; align-items:center; justify-content:center; width:80px; height:80px;">
                        <div id="qrcode"></div>
                    </div>
                    <div style="width:80px; height:80px; display:flex; align-items:center; justify-content:center;">
                        <img src="/dgi-stamp.png" style="max-height:100%; max-width:100%; object-fit:contain;">
                    </div>
                </div>` : ''}
            </div>
        </div>
        
        <div style="border-bottom:1.5px solid #000; margin-bottom:20px;"></div>
        
        <!-- Section Client (Sans bordures, comme le modèle original) -->
        <div style="font-size:11px; line-height:1.6; color:#000; margin-bottom:25px; border-top:1.5px solid #000; padding-top:10px;">
            <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#000; margin-bottom:5px; letter-spacing:0.5px;">CLIENT</div>
            <div>Nom : <strong>${d.client.nom}</strong></div>
            <div>Adresse : <strong>${d.client.adresse || '—'}</strong></div>
            <div>NCC : <strong>${d.client.ncc || '—'}</strong></div>
            <div>Régime d'imposition : <strong>${d.client.regime || '—'}</strong></div>
        </div>
        
        <!-- Tableau des Articles -->
        <table class="table-m4">
            <thead>
                ${isDeliveryMode ? `
                <tr style="background:#000; color:#fff; text-transform:uppercase;">
                    <th style="text-align:left; font-weight:700; width:15%;">Réf.</th>
                    <th style="text-align:left; font-weight:700;">Désignation</th>
                    <th style="text-align:right; font-weight:700; width:15%;">Qté</th>
                    <th style="text-align:center; font-weight:700; width:15%;">Unité</th>
                </tr>
                ` : `
                <tr style="background:#000; color:#fff; text-transform:uppercase;">
                    <th style="text-align:left; font-weight:700; width:12%;">Réf.</th>
                    <th style="text-align:left; font-weight:700;">Désignation</th>
                    <th style="text-align:right; font-weight:700; width:12%;">P.U. HT</th>
                    <th style="text-align:right; font-weight:700; width:8%;">Qté</th>
                    <th style="text-align:center; font-weight:700; width:10%;">Unité</th>
                    <th style="text-align:right; font-weight:700; width:15%;">Taxes (%)</th>
                    <th style="text-align:right; font-weight:700; width:10%;">Rem. (%)</th>
                    <th style="text-align:right; font-weight:700; width:15%;">Montant HT</th>
                </tr>
                `}
            </thead>
            <tbody>
                ${rowsHtml}
            </tbody>
        </table>
        
        ${isDeliveryMode ? `
        <!-- Pied de page pour Bon de livraison -->
        <div style="border-top:1px solid #000; padding-top:25px; margin-top:50px; display:flex; justify-content:space-between; align-items:flex-end;">
            <div style="font-size:9px; color:#444; line-height:1.6; font-weight:500; max-width:70%;">
                Document officiel généré par <strong>Selflow</strong>.
            </div>
            <div style="display:flex; gap: 30px;">
                <div style="text-align:center;">
                    <div style="font-size:9px; font-weight:700; text-transform:uppercase; color:#333; margin-bottom:5px;">Livreur / Société</div>
                    <div style="width:120px; height:50px; border:1px dashed #000; border-radius:0; background:#fff;"></div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:9px; font-weight:700; text-transform:uppercase; color:#333; margin-bottom:5px;">Décharge Client</div>
                    <div style="width:120px; height:50px; border:1px dashed #000; border-radius:0; background:#fff;"></div>
                </div>
            </div>
        </div>
        ` : `
        <!-- Résumé fiscal de la facture & Signature -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-top: 25px;">
            <!-- Résumé fiscal à gauche -->
            <div style="flex:1; margin-right:30px;">
                <div style="font-size:10px; font-weight:700; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">RÉSUMÉ DE LA FACTURE</div>
                <table style="width:100%; border-collapse:collapse; font-size:10px; border:1px solid #000;">
                    <thead>
                        <tr style="background:#fff; border-bottom:1px solid #000;">
                            <th style="padding:5px 8px; border:1px solid #000; text-align:left;">CATEGORIE</th>
                            <th style="padding:5px 8px; border:1px solid #000; text-align:right;">SOUS-TOTAL</th>
                            <th style="padding:5px 8px; border:1px solid #000; text-align:right;">TAUX (%)</th>
                            <th style="padding:5px 8px; border:1px solid #000; text-align:right;">TOTAL TAXES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding:5px 8px; border:1px solid #000;">${hasTva ? 'TVA normale' : 'TVA exo.lég - Pas de TVA sur HT'}</td>
                            <td style="padding:5px 8px; border:1px solid #000; text-align:right;">${fmtFcfa(c.tot_ht_net)}</td>
                            <td style="padding:5px 8px; border:1px solid #000; text-align:right;">${hasTva ? '18,00%' : '00,00% - D'}</td>
                            <td style="padding:5px 8px; border:1px solid #000; text-align:right;">${fmtFcfa(c.tot_tva)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Cachet et signature à droite -->
            <div style="text-align:center; width:200px;">
                <div style="font-size:9px; font-weight:700; text-transform:uppercase; color:#333; margin-bottom:5px;">Cachet & Signature</div>
                <div style="width:140px; height:60px; border:1px dashed #000; border-radius:0; background:#fff; display:inline-block;"></div>
            </div>
        </div>
        
        <!-- Pied de page -->
        <div style="border-top:1px solid #000; padding-top:15px; margin-top:20px; font-size:9px; color:#444; line-height:1.6; font-weight:500; text-align:center;">
            Société à responsabilité limitée au capital de 1.000.000 F, située à Riviera Bonoumin collège non loin du collège André Malraux, RCCM N° CI-ABJ-2018-B-31734, NCC : 1864699 A, Tel : 27 22 42 14 43 - 07 67 13 19 93, email : infosdcknowing@gmail.com
        </div>
        `}
    </div>
</div>
    `;
}

function render() {
    var d = DATA['vente'];
    var theme = getThemeColors();
    d.badge_color = theme.color;
    d.badge_bg = theme.bg;
    d.badge_tx = theme.tx;

    var html = '';
    if (curModel === 1) html = model1(d);
    else if (curModel === 2) html = model2(d);
    else if (curModel === 3) html = model3(d);
    else html = modelStandard(d);
    
    document.getElementById('invoice-wrap').innerHTML = html;

    // Générer le QR Code si normalisé et pas en BL
    if (!isDeliveryMode && d.normalise && d.qr_code_data) {
        var qrEl = document.getElementById('qrcode');
        if (qrEl) {
            qrEl.innerHTML = '';
            new QRCode(qrEl, {
                text: d.qr_code_data,
                width: 76,
                height: 76,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    }
}

function setModel(n, el) {
    curModel = n;
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('act'));
    el.classList.add('act');
    render();
}

// Initial render
render();
localStorage.removeItem('selflow_vente_panier');

// Auto-download if ?download=1 is in URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('download') === '1') {
    setTimeout(() => {
        telechargerPdf();
    }, 800);
}
</script>
@endsection
