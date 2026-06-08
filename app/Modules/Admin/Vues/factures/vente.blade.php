<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {{ $vente->numero_facture }} — {{ $vente->pointDeVente->entreprise->nom }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    
    <!-- CDNs pour html2pdf et qrcodejs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font-sans); }
        :root {
            --navy: #0D1B3E;
            --yellow: #F9CA24;
            --br: #E2E5EC;
            --tx: #1A1A2E;
            --mu: #6B7280;
            --white: #F5F6FA;
            --font-sans: 'Inter', sans-serif;
        }

        body {
            background: #F4F6F9;
            color: var(--tx);
            padding: 20px;
        }

        /* Tabs styling */
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
            border: 0.5px solid var(--br);
            color: var(--mu);
            background: #fff;
            transition: all .15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .tab:hover {
            background: var(--white);
            color: var(--tx);
        }
        .tab.act {
            background: var(--navy);
            color: #fff;
            border-color: var(--navy);
        }

        .invoice {
            background: #fff;
            border: 0.5px solid var(--br);
            border-radius: 12px;
            overflow: hidden;
            max-width: 760px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        }

        .print-bar {
            display: flex;
            justify-content: flex-end;
            padding: 10px 18px;
            background: var(--white);
            border-bottom: 0.5px solid var(--br);
            gap: 8px;
        }
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 0.5px solid var(--br);
            background: #fff;
            color: var(--tx);
            transition: all 0.15s;
            text-decoration: none;
        }
        .print-btn:hover { background: var(--white); }
        .print-btn.main { background: var(--navy); color: #fff; border-color: var(--navy); }
        .print-btn.main:hover { opacity: 0.9; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print, .print-bar { display: none !important; }
            .invoice {
                border: none !important;
                box-shadow: none !important;
                max-width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>

{{-- NO PRINT HEADER FOR CONTROLS --}}
<div class="no-print" style="max-width: 760px; margin: 0 auto 20px; display: flex; flex-direction: column; gap: 14px; background: #fff; padding: 20px; border-radius: 12px; border: 0.5px solid var(--br); box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        @php
            $routeRetour = request()->routeIs('caissier.*') ? route('caissier.ventes.factures') : route('admin.ventes.factures');
        @endphp
        <a href="{{ $routeRetour }}" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #fff; color: var(--tx); border: 0.5px solid var(--br); border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background 0.15s;">
            <i class="ti ti-arrow-left"></i> Liste des factures
        </a>
        <div style="font-weight: 700; font-size: 14px; color: var(--tx); text-transform: uppercase; letter-spacing: 0.5px;">Choix du modèle de facture</div>
    </div>
    
    <div class="tabs" id="model-tabs">
        <div class="tab act" onclick="setModel(1, this)"><i class="ti ti-layout-list" style="font-size:14px;"></i>Modèle 1 — Classique</div>
        <div class="tab" onclick="setModel(2, this)"><i class="ti ti-layout-columns" style="font-size:14px;"></i>Modèle 2 — Élégant</div>
        <div class="tab" onclick="setModel(3, this)"><i class="ti ti-layout-board" style="font-size:14px;"></i>Modèle 3 — Moderne</div>
        <div class="tab" onclick="setModel(4, this)"><i class="ti ti-layout" style="font-size:14px;"></i>Modèle 4 — Standard</div>
    </div>
</div>

<div id="invoice-wrap"></div>

<script>
var curModel = 1;

@php
    $entreprise = $vente->pointDeVente->entreprise;
    $logoUrl = $entreprise->logo_path ? Storage::url($entreprise->logo_path) : null;
    $logoFneUrl = $entreprise->logo_fne_path ? Storage::url($entreprise->logo_fne_path) : '/logo-FNE.png';
    $routeListe = request()->routeIs('caissier.*') ? route('caissier.ventes.factures') : route('admin.ventes.factures');
@endphp

var URL_CONFIRMER = {!! json_encode($routeListe) !!};

var DATA = {
    vente: {
        num: {!! json_encode($vente->numero_facture) !!},
        date: {!! json_encode(\Carbon\Carbon::parse($vente->date_vente)->isoFormat('D MMMM YYYY')) !!},
        echeance: {!! json_encode(\Carbon\Carbon::parse($vente->date_vente)->isoFormat('D MMMM YYYY')) !!},
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
        tiers_label: 'Client',
        type_label: 'Facture de vente',
        type_badge: 'VENTE',
        themes: {
            1: { color: '#0F6E56', bg: '#E1F5EE', tx: '#085041' },
            2: { color: '#185FA5', bg: '#E6F1FB', tx: '#0C447C' },
            3: { color: '#4F46E5', bg: '#EEF2FF', tx: '#3730A3' },
            4: { color: '#1E293B', bg: '#F1F5F9', tx: '#0F172A' }
        },
        items: [
            @foreach($vente->details as $detail)
            {
                ref: {!! json_encode($detail->produit?->reference ?? 'REF-VIR-' . str_pad($detail->id, 3, '0', STR_PAD_LEFT)) !!},
                desc: {!! json_encode($detail->libelle_virtuel ?? ($detail->produit?->nom ?? 'Article')) !!},
                qty: {{ $detail->quantite }},
                unite: {!! json_encode($detail->unite ?? 'Unité') !!},
                pu: {{ $detail->prix_unitaire }},
                tva: {{ $detail->montant_tva > 0 ? 18 : 0 }}
            },
            @endforeach
        ],
        mode: {!! json_encode($vente->mode_paiement) !!},
        statut: {!! json_encode($vente->statut) !!}
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
    logo_fne: {!! json_encode($logoFneUrl) !!},
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

function telechargerPdf() {
    var element = document.querySelector('.invoice');
    
    // Configuration de html2pdf
    var opt = {
        margin:       [10, 10, 10, 10], // marges en mm
        filename:     'Facture_' + DATA.vente.num + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    
    // Lancer la génération
    html2pdf().set(opt).from(element).save().then(function() {
        // Afficher le bouton Confirmer
        var btnConfirmer = document.getElementById('btn-confirmer');
        if (btnConfirmer) {
            btnConfirmer.style.setProperty('display', 'inline-flex', 'important');
        }
    });
}

function logoHtml(src, alt, maxH) {
    if (!src) return '';
    return `<img src="${src}" alt="${alt}" style="max-height:${maxH}px;max-width:120px;object-fit:contain;">`;
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

function statusColor(statut, themeColor) {
    if (statut === 'Payé')   return '#059669';
    if (statut === 'Crédit') return '#dc2626';
    if (statut === 'Avance') return '#d97706';
    return themeColor;
}

function model1(d) {
    var c = calcItems(d.items, d.remise);
    var sColor = statusColor(d.statut, d.badge_color);
    var isNorm = d.normalise;
    return `
<div class="invoice">
    <div class="print-bar no-print" data-html2pdf-ignore="true">
        <div class="print-btn main" onclick="telechargerPdf()"><i class="ti ti-download" style="font-size:13px"></i> Télécharger PDF</div>
        <a id="btn-confirmer" href="${URL_CONFIRMER}" class="print-btn" style="background:#059669;color:#fff;border-color:#059669;display:none;text-decoration:none;align-items:center;gap:6px;"><i class="ti ti-check" style="font-size:13px"></i> Confirmer</a>
    </div>
    <div style="padding:28px 32px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                ${COMPANY.logo ? `<div style="flex-shrink:0;">${logoHtml(COMPANY.logo, COMPANY.nom, 52)}</div>` : `<div style="width:38px;height:38px;border-radius:9px;background:${d.badge_color};display:flex;align-items:center;justify-content:center"><i class="ti ti-bolt" style="color:#fff;font-size:17px"></i></div>`}
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
                ${COMPANY.logo_fne ? `<div style="margin-bottom:8px;">${logoHtml(COMPANY.logo_fne, 'Logo FNE', 36)}</div>` : ''}
                <div style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;background:${d.badge_bg};color:${d.badge_tx};margin-bottom:6px">${isNorm ? 'NORMALISÉE' : d.type_badge}</div>
                <div style="font-size:22px;font-weight:800;color:var(--tx)">${isNorm ? 'Facture Normalisée' : 'Facture'}</div>
                <div style="font-size:13px;color:var(--mu);margin-top:2px;font-weight:600;">${d.num}</div>
            </div>
        </div>
        <div style="height:0.5px;background:var(--br);margin-bottom:18px;"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px">
            <div style="background:var(--white);border-radius:8px;padding:12px 14px;border:0.5px solid var(--br)">
                <div style="font-size:10px;font-weight:600;color:var(--mu);text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px">${d.tiers_label}</div>
                <div style="font-size:13px;font-weight:700;color:var(--tx)">${d.client.nom}</div>
                <div style="font-size:11px;color:var(--mu);margin-top:4px;line-height:1.7">${fiscalLinesClient(d.client, '<br>')}</div>
            </div>
            <div style="background:var(--white);border-radius:8px;padding:12px 14px;border:0.5px solid var(--br)">
                <div style="font-size:10px;font-weight:600;color:var(--mu);text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px">Informations</div>
                ${[
                    ["Date d'émission", d.date],
                    ["Mode de paiement", d.mode],
                    ["Vendeur", COMPANY.vendeur],
                    ["Point de vente", COMPANY.pdv]
                ].map(r => r[1] ? `<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;border-bottom:0.5px solid var(--br)"><span style="color:var(--mu)">${r[0]}</span><span style="font-weight:600;color:var(--tx)">${r[1]}</span></div>` : '').join('')}
                <div style="display:flex;justify-content:space-between;font-size:11px;padding:5px 0">
                    <span style="color:var(--mu)">Statut</span>
                    <span style="font-weight:700;color:${sColor};background:${sColor}18;padding:2px 10px;border-radius:20px">${d.statut}</span>
                </div>
            </div>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px">
            <thead>
                <tr style="background:${d.badge_color}">
                    <th style="padding:9px 12px;text-align:left;color:#fff;font-weight:600;width:15%;">Réf.</th>
                    <th style="padding:9px 12px;text-align:left;color:#fff;font-weight:600">Description</th>
                    <th style="padding:9px 12px;text-align:center;color:#fff;font-weight:600;width:12%">Unité</th>
                    <th style="padding:9px 12px;text-align:right;color:#fff;font-weight:600;width:8%">Qté</th>
                    <th style="padding:9px 12px;text-align:right;color:#fff;font-weight:600;width:16%">P.U. HT</th>
                    <th style="padding:9px 12px;text-align:right;color:#fff;font-weight:600;width:10%">TVA</th>
                    <th style="padding:9px 12px;text-align:right;color:#fff;font-weight:600;width:18%">Total TTC</th>
                </tr>
            </thead>
            <tbody>
                ${c.rows.map((r, i) => `<tr style="background:${i % 2 === 0 ? '#fff' : '#F9FAFB'}">
                    <td style="padding:9px 12px;color:var(--mu);font-weight:500;">${r.ref}</td>
                    <td style="padding:9px 12px;font-weight:600;color:var(--tx)">${r.desc}</td>
                    <td style="padding:9px 12px;text-align:center;color:var(--tx)">${r.unite || 'Unité'}</td>
                    <td style="padding:9px 12px;text-align:right;color:var(--tx)">${r.qty}</td>
                    <td style="padding:9px 12px;text-align:right;color:var(--tx)">${fmt(r.pu)}</td>
                    <td style="padding:9px 12px;text-align:right;color:var(--mu)">${r.tva}%</td>
                    <td style="padding:9px 12px;text-align:right;font-weight:700;color:var(--tx)">${fmt(r.ttc)}</td>
                </tr>`).join('')}
            </tbody>
        </table>
        <div style="display:flex;justify-content:space-between;margin-bottom:20px;align-items:flex-start;">
            <div style="flex:1;">
                ${isNorm ? `
                <div style="display:inline-block;padding:5px;border:1px solid #e2e5ec;background:#fff;margin-top:10px;">
                    <div id="qrcode"></div>
                </div>` : ''}
            </div>
            <div style="width:240px">
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--br)"><span style="color:var(--mu)">Sous-total Brut HT</span><span>${fmtFcfa(c.tot_ht)}</span></div>
                ${c.remiseGlobal > 0 ? `<div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--br);color:#dc2626;font-weight:600;"><span>Remise</span><span>-${fmtFcfa(c.remiseGlobal)}</span></div>` : ''}
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--br)"><span style="color:var(--mu)">Total Net HT</span><span>${fmtFcfa(c.tot_ht_net)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:0.5px solid var(--br)"><span style="color:var(--mu)">TVA (18%)</span><span>${fmtFcfa(c.tot_tva)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:10px 0 6px;font-size:15px;font-weight:800;color:var(--tx);border-top:1.5px solid var(--navy);"><span>TOTAL TTC</span><span>${fmtFcfa(c.tot_ttc)}</span></div>
            </div>
        </div>
        ${COMPANY.ref_bancaire ? `<div style="margin-bottom:14px;padding:10px 14px;background:var(--white);border-radius:7px;border:0.5px solid var(--br);font-size:11px;color:var(--mu);line-height:1.7;"><span style="font-weight:600;color:var(--tx)">Références bancaires : </span>${COMPANY.ref_bancaire}</div>` : ''}
        <div style="border-top:0.5px solid var(--br);padding-top:14px;display:flex;justify-content:space-between;align-items:flex-end">
            <div style="font-size:11px;color:var(--mu);line-height:1.7">Merci pour votre confiance.<br>Document généré par <strong>Selflow</strong> · selflow.app</div>
            <div style="text-align:right;font-size:11px;color:var(--mu)">Signature / Cachet<br><div style="width:100px;height:40px;border:0.5px dashed var(--br);border-radius:4px;margin-top:4px"></div></div>
        </div>
    </div>
</div>`;
}

function model2(d) {
    var c = calcItems(d.items, d.remise);
    var sColor = statusColor(d.statut, d.badge_color);
    var isNorm = d.normalise;
    return `
<div class="invoice">
    <div class="print-bar no-print" data-html2pdf-ignore="true">
        <div class="print-btn main" onclick="telechargerPdf()"><i class="ti ti-download" style="font-size:13px"></i> Télécharger PDF</div>
        <a id="btn-confirmer" href="${URL_CONFIRMER}" class="print-btn" style="background:#059669;color:#fff;border-color:#059669;display:none;text-decoration:none;align-items:center;gap:6px;"><i class="ti ti-check" style="font-size:13px"></i> Confirmer</a>
    </div>
    <div style="display:flex">
        <div style="width:210px;background:${d.badge_color};padding:28px 20px;flex-shrink:0;display:flex;flex-direction:column">
            <div style="margin-bottom:16px;text-align:center">
                ${COMPANY.logo ? logoHtml(COMPANY.logo, COMPANY.nom, 54) : `<div style="width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.2);display:inline-flex;align-items:center;justify-content:center"><i class="ti ti-bolt" style="color:#fff;font-size:16px"></i></div>`}
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
            ${COMPANY.logo_fne ? `<div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,.2);">${logoHtml(COMPANY.logo_fne, 'Logo FNE', 34)}</div>` : ''}
            ${isNorm ? `
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
                    <div style="font-size:22px;font-weight:800;color:var(--tx)">${isNorm ? 'Facture Normalisée' : 'Facture'}</div>
                    <div style="font-size:12px;color:var(--mu);margin-top:2px;font-weight:600;">${d.num}</div>
                </div>
                <div style="padding:5px 14px;border-radius:20px;font-size:11px;font-weight:600;background:${d.badge_bg};color:${d.badge_tx}">${isNorm ? 'NORMALISÉE' : d.type_badge}</div>
            </div>
            <div style="margin-bottom:16px;padding:12px 14px;background:var(--white);border-radius:8px;border:0.5px solid var(--br)">
                <div style="font-size:10px;color:var(--mu);text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;font-weight:600;">${d.tiers_label}</div>
                <div style="font-size:13px;font-weight:700;color:var(--tx)">${d.client.nom}</div>
                <div style="font-size:11px;color:var(--mu);margin-top:4px;line-height:1.7;">${fiscalLinesClient(d.client, '<br>')}</div>
                <div style="display:flex;gap:14px;margin-top:10px;flex-wrap:wrap;">
                    <div style="font-size:11px"><span style="color:var(--mu)">Date : </span><span style="color:var(--tx);font-weight:600;">${d.date}</span></div>
                    <div style="font-size:11px"><span style="color:var(--mu)">Paiement : </span><span style="color:var(--tx);font-weight:600;">${d.mode}</span></div>
                    <div style="font-size:11px"><span style="color:var(--mu)">Statut : </span><span style="font-weight:700;color:${sColor}">${d.statut}</span></div>
                </div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px">
                <thead>
                    <tr style="border-bottom:1.5px solid ${d.badge_color}">
                        <th style="padding:7px 0;text-align:left;color:${d.badge_color};font-weight:700;width:18%">Réf.</th>
                        <th style="padding:7px 6px;text-align:left;color:${d.badge_color};font-weight:700">Article</th>
                        <th style="padding:7px 6px;text-align:center;color:${d.badge_color};font-weight:700;width:12%">Unité</th>
                        <th style="padding:7px 6px;text-align:right;color:${d.badge_color};font-weight:700;width:10%">Qté</th>
                        <th style="padding:7px 6px;text-align:right;color:${d.badge_color};font-weight:700;width:18%">P.U. HT</th>
                        <th style="padding:7px 0;text-align:right;color:${d.badge_color};font-weight:700;width:20%">TTC</th>
                    </tr>
                </thead>
                <tbody>
                    ${c.rows.map(r => `<tr style="border-bottom:0.5px solid var(--br)">
                        <td style="padding:8px 0;color:var(--mu);font-weight:500;">${r.ref}</td>
                        <td style="padding:8px 6px;color:var(--tx);font-weight:600;">${r.desc}</td>
                        <td style="padding:8px 6px;text-align:center;color:var(--mu)">${r.unite || 'Unité'}</td>
                        <td style="padding:8px 6px;text-align:right;color:var(--mu)">${r.qty}</td>
                        <td style="padding:8px 6px;text-align:right;color:var(--mu)">${fmt(r.pu)}</td>
                        <td style="padding:8px 0;text-align:right;font-weight:700;color:var(--tx)">${fmt(r.ttc)}</td>
                    </tr>`).join('')}
                </tbody>
            </table>
            <div style="display:flex;justify-content:flex-end;margin-bottom:14px">
                <div style="width:220px;background:var(--white);border-radius:8px;padding:10px 12px;border:0.5px solid var(--br)">
                    <div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0"><span style="color:var(--mu)">Brut HT</span><span>${fmtFcfa(c.tot_ht)}</span></div>
                    ${c.remiseGlobal > 0 ? `<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;color:#dc2626;font-weight:600;"><span>Remise</span><span>-${fmtFcfa(c.remiseGlobal)}</span></div>` : ''}
                    <div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0"><span style="color:var(--mu)">Net HT</span><span>${fmtFcfa(c.tot_ht_net)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0"><span style="color:var(--mu)">TVA 18%</span><span>${fmtFcfa(c.tot_tva)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:800;padding:6px 0 0;border-top:0.5px solid var(--br);margin-top:4px"><span style="color:var(--tx)">Total TTC</span><span style="color:${d.badge_color}">${fmtFcfa(c.tot_ttc)}</span></div>
                </div>
            </div>
            ${COMPANY.ref_bancaire ? `<div style="font-size:10.5px;color:var(--mu);line-height:1.7;margin-bottom:10px;"><strong>Réf. bancaires : </strong>${COMPANY.ref_bancaire}</div>` : ''}
            <div style="font-size:11px;color:var(--mu)">Généré automatiquement par <strong>Selflow</strong></div>
        </div>
    </div>
</div>`;
}

function model3(d) {
    var c = calcItems(d.items, d.remise);
    var sColor = statusColor(d.statut, d.badge_color);
    var isNorm = d.normalise;
    return `
<div class="invoice">
    <div class="print-bar no-print" data-html2pdf-ignore="true">
        <div class="print-btn main" onclick="telechargerPdf()"><i class="ti ti-download" style="font-size:13px"></i> Télécharger PDF</div>
        <a id="btn-confirmer" href="${URL_CONFIRMER}" class="print-btn" style="background:#059669;color:#fff;border-color:#059669;display:none;text-decoration:none;align-items:center;gap:6px;"><i class="ti ti-check" style="font-size:13px"></i> Confirmer</a>
    </div>
    <div style="border-top:4px solid ${d.badge_color};padding:28px 32px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
            <div style="display:flex;align-items:flex-start;gap:12px">
                ${COMPANY.logo ? `<div style="flex-shrink:0;">${logoHtml(COMPANY.logo, COMPANY.nom, 48)}</div>` : `<div style="width:38px;height:38px;border-radius:10px;background:${d.badge_color};display:flex;align-items:center;justify-content:center"><i class="ti ti-bolt" style="color:#fff;font-size:18px"></i></div>`}
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
                ${COMPANY.logo_fne ? `<div style="margin-bottom:8px;text-align:right;">${logoHtml(COMPANY.logo_fne, 'Logo FNE', 32)}</div>` : ''}
                <div style="font-size:24px;font-weight:800;color:var(--tx);letter-spacing:-0.5px;">${isNorm ? 'FACTURE NORMALISÉE' : 'FACTURE'}</div>
                <div style="font-size:12px;margin-top:2px"><span style="color:var(--mu)">N° </span><span style="font-weight:700;color:${d.badge_color}">${d.num}</span></div>
                <div style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;background:${d.badge_bg};color:${d.badge_tx};margin-top:6px">${isNorm ? 'NORMALISÉE' : d.type_badge}</div>
            </div>
        </div>

        <div style="height:1px;background:var(--br);margin-bottom:18px"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
            <div style="padding:12px 14px;background:var(--white);border-radius:8px;border:0.5px solid var(--br)">
                <div style="font-size:10px;color:${d.badge_color};font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">${d.tiers_label}</div>
                <div style="font-size:13px;font-weight:700;color:var(--tx)">${d.client.nom}</div>
                <div style="font-size:11px;color:var(--mu);margin-top:4px;line-height:1.7">${fiscalLinesClient(d.client, '<br>')}</div>
            </div>
            <div style="padding:12px 14px;background:var(--white);border-radius:8px;border:0.5px solid var(--br)">
                <div style="font-size:10px;color:${d.badge_color};font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Détails</div>
                <div style="font-size:11px;line-height:1.9">
                    <span style="color:var(--mu)">Date : </span><span style="color:var(--tx);font-weight:600;">${d.date}</span><br>
                    <span style="color:var(--mu)">Paiement : </span><span style="color:var(--tx);font-weight:600;">${d.mode}</span><br>
                    <span style="color:var(--mu)">Vendeur : </span><span style="color:var(--tx);font-weight:600;">${COMPANY.vendeur || '—'}</span><br>
                    <span style="color:var(--mu)">Statut : </span><span style="font-weight:700;color:${sColor}">${d.statut}</span>
                </div>
            </div>
        </div>

        <div style="margin-bottom:14px;padding:10px 14px;background:var(--white);border-radius:8px;border:0.5px solid var(--br);font-size:10.5px;color:var(--mu)">
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
                    <th style="padding:9px 10px;text-align:left;border-bottom:2px solid ${d.badge_color};color:var(--tx);font-weight:700;width:15%">Réf.</th>
                    <th style="padding:9px 10px;text-align:left;border-bottom:2px solid ${d.badge_color};color:var(--tx);font-weight:700;width:30%">Article</th>
                    <th style="padding:9px 10px;text-align:center;border-bottom:2px solid ${d.badge_color};color:var(--tx);font-weight:700;width:12%">Unité</th>
                    <th style="padding:9px 10px;text-align:right;border-bottom:2px solid ${d.badge_color};color:var(--tx);font-weight:700;width:10%">Qté</th>
                    <th style="padding:9px 10px;text-align:right;border-bottom:2px solid ${d.badge_color};color:var(--tx);font-weight:700;width:16%">P.U. HT</th>
                    <th style="padding:9px 10px;text-align:right;border-bottom:2px solid ${d.badge_color};color:var(--tx);font-weight:700;width:10%">TVA</th>
                    <th style="padding:9px 10px;text-align:right;border-bottom:2px solid ${d.badge_color};color:var(--tx);font-weight:700;width:17%">TTC</th>
                </tr>
            </thead>
            <tbody>
                ${c.rows.map((r, i) => `<tr style="background:${i % 2 === 1 ? '#F9FAFB' : '#fff'}">
                    <td style="padding:9px 10px;color:var(--mu);font-size:11px;font-weight:500;">${r.ref}</td>
                    <td style="padding:9px 10px;font-weight:600;color:var(--tx)">${r.desc}</td>
                    <td style="padding:9px 10px;text-align:center;color:var(--tx)">${r.unite || 'Unité'}</td>
                    <td style="padding:9px 10px;text-align:right;color:var(--tx)">${r.qty}</td>
                    <td style="padding:9px 10px;text-align:right;color:var(--mu)">${fmt(r.pu)}</td>
                    <td style="padding:9px 10px;text-align:right;color:var(--mu)">${r.tva}%</td>
                    <td style="padding:9px 10px;text-align:right;font-weight:700;color:var(--tx)">${fmt(r.ttc)}</td>
                </tr>`).join('')}
            </tbody>
        </table>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px">
            <div style="font-size:11px;color:var(--mu);max-width:320px;line-height:1.7">
                <span style="color:var(--tx);font-weight:700">Émetteur :</span><br>
                ${COMPANY.adresse ? COMPANY.adresse + '<br>' : ''}
                ${COMPANY.tel ? 'Tél : ' + COMPANY.tel : ''}${COMPANY.email ? ' · ' + COMPANY.email : ''}<br>
                ${COMPANY.ref_bancaire ? '<strong>Réf. bancaires : </strong>' + COMPANY.ref_bancaire : ''}
                ${isNorm ? `<div style="margin-top:10px;"><div id="qrcode"></div></div>` : ''}
            </div>
            <div style="width:235px">
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-bottom:0.5px solid var(--br)"><span style="color:var(--mu)">Sous-total Brut HT</span><span>${fmtFcfa(c.tot_ht)}</span></div>
                ${c.remiseGlobal > 0 ? `<div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-bottom:0.5px solid var(--br);color:#dc2626;font-weight:600;"><span>Remise</span><span>-${fmtFcfa(c.remiseGlobal)}</span></div>` : ''}
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-bottom:0.5px solid var(--br)"><span style="color:var(--mu)">Total Net HT</span><span>${fmtFcfa(c.tot_ht_net)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-bottom:0.5px solid var(--br)"><span style="color:var(--mu)">TVA 18%</span><span>${fmtFcfa(c.tot_tva)}</span></div>
                <div style="display:flex;justify-content:space-between;padding:8px;margin-top:4px;background:${d.badge_color};border-radius:6px">
                    <span style="font-size:13px;font-weight:700;color:#fff">Total TTC</span>
                    <span style="font-size:15px;font-weight:800;color:#fff">${fmtFcfa(c.tot_ttc)}</span>
                </div>
            </div>
        </div>
        <div style="border-top:0.5px solid var(--br);padding-top:12px;display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:11px;color:var(--mu)">Généré par <strong>Selflow</strong> · selflow.app · Document officiel</div>
            <div style="display:flex;gap:16px">
                <div style="text-align:center;font-size:10px;color:var(--mu)">Signature client<br><div style="width:80px;height:36px;border:0.5px dashed var(--br);border-radius:4px;margin-top:3px"></div></div>
                <div style="text-align:center;font-size:10px;color:var(--mu)">Cachet société<br><div style="width:80px;height:36px;border:0.5px dashed var(--br);border-radius:4px;margin-top:3px"></div></div>
            </div>
        </div>
    </div>
</div>`;
}

function modelStandard(d) {
    var c = calcItems(d.items, d.remise);
    var sColor = statusColor(d.statut, '#1E293B');
    var isNorm = d.normalise;
    var title = isNorm ? 'FACTURE NORMALISÉE' : 'FACTURE PRO-FORMA';
    
    // Check if any item has TVA active
    var hasTva = d.items.some(r => r.tva > 0);
    
    return `
<div class="invoice" style="border: 1px solid #000; border-radius: 0; background: #fff; box-shadow: none;">
    <div class="print-bar no-print" data-html2pdf-ignore="true">
        <div class="print-btn main" onclick="telechargerPdf()"><i class="ti ti-download" style="font-size:13px"></i> Télécharger PDF</div>
        <a id="btn-confirmer" href="${URL_CONFIRMER}" class="print-btn" style="background:#059669;color:#fff;border-color:#059669;display:none;text-decoration:none;align-items:center;gap:6px;"><i class="ti ti-check" style="font-size:13px"></i> Confirmer</a>
    </div>
    
    <div style="padding:40px; color:#000; font-family: 'Inter', sans-serif;">
        <!-- En-tête -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
            <!-- Gauche : Infos Vendeur -->
            <div style="flex:1; font-size:11px; line-height:1.6; color:#000;">
                <div style="font-size:16px; font-weight:800; text-transform:uppercase; margin-bottom:4px;">${COMPANY.nom}</div>
                <div>NCC : <strong>${COMPANY.ncc || '—'}</strong></div>
                <div>Régime d'imposition : <strong>${COMPANY.regime || '—'}</strong></div>
                <div>Centre des impôts : <strong>${COMPANY.centre_impots || '—'}</strong></div>
                <div>RCCM : <strong>${COMPANY.rccm || '—'}</strong></div>
                
                <div style="margin-top:10px; border-top:1px dashed #ccc; padding-top:6px;">
                    <strong>Références bancaires :</strong><br>
                    ${COMPANY.ref_bancaire || '—'}<br>
                    Adresse : ${COMPANY.adresse || '—'}<br>
                    Nº Tel : ${COMPANY.tel || '—'}<br>
                    Mail : ${COMPANY.email || '—'}
                </div>
                
                <div style="margin-top:10px; border-top:1px dashed #ccc; padding-top:6px;">
                    Nom du vendeur : <strong>${COMPANY.vendeur || 'Gestionnaire Principal'}</strong><br>
                    Nom de PDV : <strong>${COMPANY.pdv}</strong><br>
                    Date et heure : <strong>${d.date}</strong><br>
                    Mode de paiement : <strong>${d.mode}</strong>
                </div>
            </div>
            
            <!-- Droite : Logo FNE, Logo Entreprise & Infos Document -->
            <div style="text-align:right; flex-shrink:0; width:260px; display:flex; flex-direction:column; align-items:flex-end;">
                <!-- Logos Row -->
                <div style="display:flex; gap:10px; align-items:center; margin-bottom:15px; justify-content:flex-end; width:100%;">
                    <!-- Place Logo Entreprise -->
                    <div style="width:110px; height:60px; border:1.5px dashed #000; display:flex; align-items:center; justify-content:center; background:#fff; font-size:9px; font-weight:700; color:#000; text-align:center; text-transform:uppercase;">
                        ${COMPANY.logo ? `<img src="${COMPANY.logo}" alt="${COMPANY.nom}" style="max-height:100%; max-width:100%; object-fit:contain;">` : 'LOGO ENTREPRISE'}
                    </div>
                    <!-- Place Logo FNE -->
                    <div style="width:110px; height:60px; border:1.5px dashed #000; display:flex; align-items:center; justify-content:center; background:#fff; font-size:9px; font-weight:700; color:#000; text-align:center; text-transform:uppercase;">
                        ${COMPANY.logo_fne ? `<img src="${COMPANY.logo_fne}" alt="Logo FNE" style="max-height:100%; max-width:100%; object-fit:contain;">` : 'LOGO FNE'}
                    </div>
                </div>
                
                <div style="font-size:16px; font-weight:800; letter-spacing:0.5px; color:#000; text-transform:uppercase; margin-bottom:4px;">${title}</div>
                <div style="font-size:12px; font-weight:700; color:#000;">N° ${d.num}</div>
                <div style="font-size:11px; color:#333; margin-top:2px;">Statut : <span style="font-weight:700; text-transform:uppercase;">${d.statut}</span></div>
                
                ${isNorm ? `
                <div style="margin-top:15px; display:inline-block; padding:6px; border:1px solid #000; background:#fff; text-align:center;">
                    <div id="qrcode"></div>
                </div>` : ''}
            </div>
        </div>
        
        <div style="border-bottom:1.5px solid #000; margin-bottom:20px;"></div>
        
        <!-- Section Client -->
        <div style="border:1px solid #000; padding:12px 15px; background:#fff; margin-bottom:20px;">
            <div style="font-size:9.5px; font-weight:700; text-transform:uppercase; color:#000; margin-bottom:5px; letter-spacing:0.5px; border-bottom:1px solid #000; padding-bottom:3px;">CLIENT</div>
            <div style="font-size:13px; font-weight:800; color:#000;">${d.client.nom}</div>
            <div style="font-size:10.5px; color:#000; line-height:1.6; margin-top:5px; font-weight:500;">
                ${d.client.adresse ? 'Adresse : ' + d.client.adresse + '<br>' : ''}
                ${d.client.tel ? 'Tél : ' + d.client.tel + '<br>' : ''}
                ${d.client.email ? 'Email : ' + d.client.email + '<br>' : ''}
                <span style="font-weight:700; color:#000;">NCC : ${d.client.ncc || '—'}</span> · Régime : ${d.client.regime || '—'}
            </div>
        </div>
        
        <!-- Tableau des Articles -->
        <table style="width:100%; border-collapse:collapse; font-size:11px; margin-bottom:20px; border:1px solid #000;">
            <thead>
                <tr style="background:#000; color:#fff; text-transform:uppercase;">
                    <th style="padding:8px 10px; text-align:left; border:1px solid #000; font-weight:700; width:12%;">Réf.</th>
                    <th style="padding:8px 10px; text-align:left; border:1px solid #000; font-weight:700;">Désignation</th>
                    <th style="padding:8px 10px; text-align:right; border:1px solid #000; font-weight:700; width:12%;">P.U. HT</th>
                    <th style="padding:8px 10px; text-align:right; border:1px solid #000; font-weight:700; width:8%;">Qté</th>
                    <th style="padding:8px 10px; text-align:center; border:1px solid #000; font-weight:700; width:10%;">Unité</th>
                    <th style="padding:8px 10px; text-align:right; border:1px solid #000; font-weight:700; width:10%;">Taxes (%)</th>
                    <th style="padding:8px 10px; text-align:right; border:1px solid #000; font-weight:700; width:10%;">Rem. (%)</th>
                    <th style="padding:8px 10px; text-align:right; border:1px solid #000; font-weight:700; width:15%;">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                ${c.rows.map((r, i) => `
                <tr style="background:#fff; color:#000;">
                    <td style="padding:8px 10px; border:1px solid #000; font-weight:500;">${r.ref}</td>
                    <td style="padding:8px 10px; border:1px solid #000; font-weight:700;">${r.desc}</td>
                    <td style="padding:8px 10px; border:1px solid #000; text-align:right;">${fmt(r.pu)}</td>
                    <td style="padding:8px 10px; border:1px solid #000; text-align:right;">${r.qty}</td>
                    <td style="padding:8px 10px; border:1px solid #000; text-align:center;">${r.unite || 'Unité'}</td>
                    <td style="padding:8px 10px; border:1px solid #000; text-align:right; color:#000;">${r.tva > 0 ? 'TVA (18%)' : 'TVAD (0)'}</td>
                    <td style="padding:8px 10px; border:1px solid #000; text-align:right; color:#000;">0%</td>
                    <td style="padding:8px 10px; border:1px solid #000; text-align:right; font-weight:700;">${fmt(r.ht)}</td>
                </tr>`).join('')}
            </tbody>
        </table>
        
        <!-- Totaux & Résumé -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px;">
            <!-- Résumé fiscal de la facture à gauche -->
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
            
            <!-- Boîte de totaux à droite -->
            <div style="width:250px; border:1px solid #000; padding:12px; background:#fff; font-size:11px;">
                <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #ddd; color:#000;">
                    <span>TOTAL HT</span>
                    <span>${fmtFcfa(c.tot_ht)}</span>
                </div>
                ${c.remiseGlobal > 0 ? `
                <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #ddd; color:#dc2626; font-weight:700;">
                    <span>Remise</span>
                    <span>-${fmtFcfa(c.remiseGlobal)}</span>
                </div>` : ''}
                <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #ddd; color:#000; font-weight:700;">
                    <span>TOTAL HT NET</span>
                    <span>${fmtFcfa(c.tot_ht_net)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #ddd; color:#000;">
                    <span>TVA</span>
                    <span>${fmtFcfa(c.tot_tva)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #ddd; color:#000;">
                    <span>AUTRES TAXES</span>
                    <span>0 FCFA</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:8px 0 0; font-size:13px; font-weight:900; color:#000; border-top:1px solid #000;">
                    <span>TOTAL A PAYER</span>
                    <span>${fmtFcfa(c.tot_ttc)}</span>
                </div>
            </div>
        </div>
        
        <!-- Pied de page -->
        <div style="border-top:1px solid #000; padding-top:15px; display:flex; justify-content:space-between; align-items:flex-end;">
            <div style="font-size:9px; color:#444; line-height:1.6; font-weight:500; max-width:70%;">
                Société à responsabilité limitée au capital de 1.000.000 F, située à Riviera Bonoumin collège non loin du collège André Malraux, RCCM N° CI-ABJ-2018-B-31734, NCC : 1864699 A, Tel : 27 22 42 14 43 - 07 67 13 19 93, email : infosdcknowing@gmail.com
            </div>
            <div style="text-align:center;">
                <div style="font-size:9px; font-weight:700; text-transform:uppercase; color:#333; margin-bottom:5px;">Cachet & Signature</div>
                <div style="width:120px; height:50px; border:1px dashed #000; border-radius:0; background:#fff;"></div>
            </div>
        </div>
    </div>
</div>
    `;
}

function render() {
    var d = DATA['vente'];
    var theme = d.themes[curModel];
    d.badge_color = theme.color;
    d.badge_bg = theme.bg;
    d.badge_tx = theme.tx;

    var html = '';
    if (curModel === 1) html = model1(d);
    else if (curModel === 2) html = model2(d);
    else if (curModel === 3) html = model3(d);
    else html = modelStandard(d);
    
    document.getElementById('invoice-wrap').innerHTML = html;

    // Générer le QR Code si normalisé
    if (d.normalise && d.qr_code_data) {
        var qrEl = document.getElementById('qrcode');
        if (qrEl) {
            qrEl.innerHTML = '';
            new QRCode(qrEl, {
                text: d.qr_code_data,
                width: 90,
                height: 90,
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

// Auto-download if ?download=1 is in URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('download') === '1') {
    setTimeout(() => {
        telechargerPdf();
    }, 800);
}
</script>
</body>
</html>
