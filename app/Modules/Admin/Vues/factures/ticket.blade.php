<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket RNE — {{ $vente->numero_facture }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            width: 80mm;
            margin: 0;
            padding: 10px 5mm;
            box-sizing: border-box;
            background: #fff;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .header {
            margin-bottom: 12px;
        }
        .logo-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 1px solid #000;
            padding: 4px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        .logo-svg {
            width: 28px;
            height: 28px;
        }
        .rne-label {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            text-align: left;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .double-divider {
            border-top: 3px double #000;
            margin: 8px 0;
        }
        .bold {
            font-weight: bold;
        }
        .uppercase {
            text-transform: uppercase;
        }
        .grid-info {
            font-size: 10px;
            line-height: 1.3;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 8px;
        }
        .item-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .total-box {
            font-size: 15px;
            font-weight: bold;
            margin: 12px 0;
            text-align: center;
            border: 1px solid #000;
            padding: 6px;
        }
        .qr-container {
            margin-top: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 4px;
        }
        .qr-code {
            width: 100px;
            height: 100px;
        }
        .footer-text {
            font-size: 9px;
            text-align: center;
            color: #444;
            margin-top: 10px;
        }
        .no-print-bar {
            background: #f3f4f6;
            padding: 10px;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .btn-print {
            background: #1e293b;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        @media print {
            .no-print-bar {
                display: none !important;
            }
            body {
                padding: 4px 2mm;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <button class="btn-print" onclick="window.print()">Imprimer le Ticket (RNE)</button>
    <button class="btn-print" style="background:#4b5563; margin-left:8px;" onclick="window.history.back()">Retour</button>
</div>

<div class="header">
    <div class="logo-box">
        <svg class="logo-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="45" fill="none" stroke="black" stroke-width="4"/>
            <path d="M 30,50 Q 50,20 70,50 T 30,50" fill="none" stroke="black" stroke-width="4"/>
            <text x="50" y="80" font-family="sans-serif" font-size="20" font-weight="bold" text-anchor="middle">CI</text>
        </svg>
        <div class="rne-label">
            {{ isset($bl) ? 'BON DE LIVRAISON' : 'REÇU NORMALISÉ' . "\n" . 'ÉLECTRONIQUE' }}
        </div>
    </div>
    <div class="text-center bold" style="font-size: 11px;">
        {{ isset($bl) ? 'BL No: ' . $bl->numero_bl : 'RECU No: ' . ($vente->numero_fne ?? 'Simulation-FNE-DGI') }}
    </div>
</div>

<div class="divider"></div>

<div class="grid-info uppercase">
    <span class="bold">{{ $vente->pointDeVente->entreprise->nom }}</span><br>
    TERMINAL : {{ str_pad($vente->point_de_vente_id, 7, '0', STR_PAD_LEFT) }}<br>
    ADRESSE : {{ $vente->pointDeVente->commune }}, {{ $vente->pointDeVente->ville }}<br>
    REGIME : {{ $vente->pointDeVente->entreprise->regime_imposition ?? 'RNI' }}<br>
    NCC : {{ $vente->pointDeVente->entreprise->ncc ?? '—' }}
</div>

<div class="divider"></div>

<div class="grid-info">
    DATE : {{ $vente->date_vente->format('d/m/Y H:i:s') }}
</div>

<div class="divider"></div>

<table class="item-table">
    <tbody>
        @foreach($vente->details as $d)
            @php
                $qty = $d->quantite;
                if (isset($bl)) {
                    $blDetail = $bl->details->firstWhere('produit_id', $d->produit_id);
                    $qty = $blDetail ? $blDetail->qte_livree : 0;
                }
            @endphp
            <tr>
                <td>
                    <span class="bold">{{ $d->produit ? $d->produit->nom : $d->libelle_virtuel }}</span>
                    <br>
                    @if(isset($bl))
                        <span style="font-size:10px;">Quantité : {{ $qty }} {{ $d->unite }}</span>
                    @else
                        <span style="font-size:10px;">{{ $qty }} x {{ number_format($d->prix_unitaire, 0, ',', ' ') }}</span>
                    @endif
                </td>
                <td class="text-right bold">
                    {{ isset($bl) ? '' : number_format($d->montant_ttc, 0, ',', ' ') }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="divider"></div>

@if(!isset($bl))
<div class="total-box uppercase">
    MONTANT : {{ number_format($vente->montant_ttc, 0, ',', ' ') }} XOF
</div>
@endif

<div class="grid-info uppercase">
    @if(isset($bl))
        N° BL : {{ $bl->numero_bl }}<br>
        RÉF. COMMANDE : {{ $vente->numero_facture }}
    @else
        ID TRANSACTION : {{ $vente->numero_facture }}<br>
        MODE DE PAIEMENT : {{ $vente->mode_paiement }}
    @endif
</div>

<div class="divider"></div>

@if(!isset($bl))
<div class="qr-container">
    @if($vente->qr_code_data)
        <img class="qr-code" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($vente->qr_code_data) }}" alt="QR Code DGI">
    @endif
    <span style="font-size: 8px; font-weight: bold;">SIGNATURE DGI</span>
    <span style="font-size: 8px; word-break: break-all;" class="text-center">{{ substr($vente->signature_dgi, 0, 16) }}</span>
</div>
@endif

<div class="footer-text">
    {{ isset($bl) ? 'Merci pour votre confiance !' : 'Merci pour votre visite !' }}<br>
    Selflow — Solution de gestion commerciale.
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('print') === '1') {
    window.onload = function() {
        window.print();
    }
}
</script>
</body>
</html>
