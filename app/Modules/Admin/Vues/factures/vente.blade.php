<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {{ $vente->numero_facture }} — {{ $vente->pointDeVente->entreprise->nom }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; font-size: 13px; color: #1e293b; background: #f8fafc; }

        .facture-page {
            max-width: 800px; margin: 30px auto; background: #fff;
            border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.12);
            overflow: hidden;
        }

        .facture-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff; padding: 36px 40px; display: flex;
            justify-content: space-between; align-items: flex-start;
        }
        .logo-block .logo-name { font-size: 28px; font-weight: 800; letter-spacing: -1px; }
        .logo-block .logo-sub  { font-size: 12px; opacity: .75; margin-top: 3px; }
        .facture-title .fn { font-size: 11px; opacity: .7; text-transform: uppercase; letter-spacing: 1px; }
        .facture-title .numero { font-size: 26px; font-weight: 800; margin-top: 4px; }
        .facture-title .date { font-size: 12px; opacity: .8; margin-top: 6px; }

        .facture-body { padding: 36px 40px; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px; }
        .info-block h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 8px; }
        .info-block p  { font-size: 13px; color: #1e293b; line-height: 1.7; }
        .info-block strong { font-weight: 700; font-size: 15px; display: block; margin-bottom: 2px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th {
            background: #f1f5f9; padding: 10px 14px;
            text-align: left; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px; color: #64748b;
        }
        thead th:last-child { text-align: right; }
        tbody td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        tbody td:last-child { text-align: right; font-weight: 600; }
        tbody tr:last-child td { border-bottom: none; }

        .totaux { margin-left: auto; width: 280px; }
        .totaux-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #64748b; }
        .totaux-row.grand {
            font-size: 16px; font-weight: 800; color: #1e293b;
            border-top: 2px solid #e2e8f0; margin-top: 8px; padding-top: 12px;
        }

        .facture-footer {
            background: #f8fafc; border-top: 1px solid #e2e8f0;
            padding: 20px 40px; display: flex; justify-content: space-between;
            align-items: center; color: #94a3b8; font-size: 12px;
        }
        .badge-paye {
            display: inline-block; background: #dcfce7; color: #15803d;
            border-radius: 20px; padding: 4px 14px; font-size: 12px; font-weight: 700;
        }

        .no-print { display: flex; gap: 12px; justify-content: center; padding: 24px; background: #f1f5f9; }
        @media print {
            body { background: #fff; }
            .facture-page { box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding:10px 24px; background:#6366f1; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;">
        🖨️ Imprimer / PDF
    </button>
    <a href="{{ route('admin.ventes.historique') }}" style="padding:10px 24px; background:#fff; color:#475569; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none;">
        ← Retour
    </a>
</div>

<div class="facture-page">
    <div class="facture-header">
        <div class="logo-block">
            <div class="logo-name">{{ $vente->pointDeVente->entreprise->nom }}</div>
            <div class="logo-sub">{{ $vente->pointDeVente->entreprise->adresse }}</div>
            <div class="logo-sub" style="margin-top:4px;">Tél : {{ $vente->pointDeVente->entreprise->telephone }}</div>
            @if($vente->pointDeVente->entreprise->rccm)
            <div class="logo-sub">RCCM : {{ $vente->pointDeVente->entreprise->rccm }}</div>
            @endif
        </div>
        <div class="facture-title" style="text-align:right;">
            <div class="fn">Facture de vente</div>
            <div class="numero">{{ $vente->numero_facture }}</div>
            <div class="date">{{ \Carbon\Carbon::parse($vente->date_vente)->isoFormat('D MMMM YYYY') }}</div>
            <div style="margin-top:10px;">
                <span class="badge-paye" style="background:rgba(255,255,255,.2); color:#fff;">{{ $vente->statut }}</span>
            </div>
        </div>
    </div>

    <div class="facture-body">
        <div class="info-grid">
            <div class="info-block">
                <h4>Vendu par</h4>
                <p>
                    <strong>{{ $vente->pointDeVente->nom }}</strong>
                    {{ $vente->pointDeVente->commune }}, {{ $vente->pointDeVente->ville }}<br>
                    Tél : {{ $vente->pointDeVente->telephone }}
                </p>
            </div>
            <div class="info-block">
                <h4>Facturé à</h4>
                <p>
                    <strong>{{ $vente->client?->nom ?? 'Client de passage' }}</strong>
                    @if($vente->client)
                        {{ $vente->client->telephone }}<br>
                        {{ $vente->client->adresse }}
                    @endif
                </p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Désignation</th>
                    <th>Référence</th>
                    <th>Qté</th>
                    <th>Prix unitaire</th>
                    <th>Total HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vente->details as $i => $detail)
                <tr>
                    <td style="color:#94a3b8;">{{ $i + 1 }}</td>
                    <td style="font-weight:600;">{{ $detail->produit->nom }}</td>
                    <td style="color:#94a3b8;">{{ $detail->produit->reference }}</td>
                    <td style="text-align:center;">{{ $detail->quantite }}</td>
                    <td>{{ number_format($detail->prix_unitaire, 0, ',', ' ') }} F</td>
                    <td>{{ number_format($detail->quantite * $detail->prix_unitaire, 0, ',', ' ') }} F</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totaux">
            <div class="totaux-row"><span>Montant HT</span><span>{{ number_format($vente->montant_ht, 0, ',', ' ') }} FCFA</span></div>
            <div class="totaux-row"><span>TVA (18%)</span><span>{{ number_format($vente->montant_tva, 0, ',', ' ') }} FCFA</span></div>
            <div class="totaux-row grand"><span>TOTAL TTC</span><span>{{ number_format($vente->montant_ttc, 0, ',', ' ') }} FCFA</span></div>
        </div>

        <div style="margin-top: 28px; display:flex; justify-content: space-between; align-items:center;">
            <div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px;">Mode de paiement</div>
                <div style="font-weight:700; margin-top:4px;">{{ $vente->mode_paiement }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px; color:#94a3b8; margin-bottom:8px;">Signature & Cachet</div>
                <div style="width:120px; height:60px; border-bottom:1px solid #e2e8f0;"></div>
            </div>
        </div>
    </div>

    <div class="facture-footer">
        <span>{{ $vente->pointDeVente->entreprise->email }}</span>
        <span>Merci pour votre confiance !</span>
        <span>Généré le {{ now()->format('d/m/Y à H:i') }}</span>
    </div>
</div>
</body>
</html>
