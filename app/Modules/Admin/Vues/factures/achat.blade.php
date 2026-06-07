<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon d'achat {{ $achat->numero_facture }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; font-size: 13px; color: #1e293b; background: #f8fafc; }
        .page { max-width: 800px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.12); overflow: hidden; }
        .header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 36px 40px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-name { font-size: 26px; font-weight: 800; letter-spacing: -1px; }
        .logo-sub  { font-size: 12px; opacity: .65; margin-top: 3px; }
        .doc-type  { font-size: 10px; opacity: .6; text-transform: uppercase; letter-spacing: 1px; }
        .doc-num   { font-size: 24px; font-weight: 800; margin-top: 4px; color: #60a5fa; }
        .doc-date  { font-size: 12px; opacity: .75; margin-top: 6px; }
        .body { padding: 36px 40px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px; }
        .info-block h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 8px; }
        .info-block strong { display: block; font-size: 15px; font-weight: 700; margin-bottom: 3px; }
        .info-block p { font-size: 13px; color: #475569; line-height: 1.7; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th { background: #f1f5f9; padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
        thead th:last-child { text-align: right; }
        tbody td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        tbody td:last-child { text-align: right; font-weight: 600; }
        .totaux { margin-left: auto; width: 280px; }
        .totaux-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; color: #64748b; }
        .totaux-row.grand { font-size: 16px; font-weight: 800; color: #1e293b; border-top: 2px solid #e2e8f0; margin-top: 8px; padding-top: 12px; }
        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 40px; display: flex; justify-content: space-between; color: #94a3b8; font-size: 12px; }
        .no-print { display: flex; gap: 12px; justify-content: center; padding: 24px; background: #f1f5f9; }
        @media print { body { background: #fff; } .page { box-shadow: none; margin: 0; border-radius: 0; max-width: 100%; } .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()" style="padding:10px 24px; background:#0f172a; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;">🖨️ Imprimer / PDF</button>
    <a href="{{ route('admin.achats.historique') }}" style="padding:10px 24px; background:#fff; color:#475569; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none;">← Retour</a>
</div>
<div class="page">
    <div class="header">
        <div>
            <div class="logo-name">{{ $achat->pointDeVente->entreprise->nom }}</div>
            <div class="logo-sub">{{ $achat->pointDeVente->entreprise->adresse }}</div>
            <div class="logo-sub">Tél : {{ $achat->pointDeVente->entreprise->telephone }}</div>
        </div>
        <div style="text-align:right;">
            <div class="doc-type">Bon d'achat</div>
            <div class="doc-num">{{ $achat->numero_facture }}</div>
            <div class="doc-date">{{ \Carbon\Carbon::parse($achat->date_achat)->isoFormat('D MMMM YYYY') }}</div>
        </div>
    </div>
    <div class="body">
        <div class="info-grid">
            <div class="info-block">
                <h4>Acheteur</h4>
                <strong>{{ $achat->pointDeVente->nom }}</strong>
                <p>{{ $achat->pointDeVente->commune }}, {{ $achat->pointDeVente->ville }}<br>Tél : {{ $achat->pointDeVente->telephone }}</p>
            </div>
            <div class="info-block">
                <h4>Fournisseur</h4>
                <strong>{{ $achat->fournisseur->nom }}</strong>
                <p>{{ $achat->fournisseur->telephone }}<br>{{ $achat->fournisseur->email }}<br>{{ $achat->fournisseur->secteur }}</p>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Désignation</th><th>Référence</th><th>Qté</th><th>Prix unitaire</th><th>Total HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($achat->details as $i => $detail)
                <tr>
                    <td style="color:#94a3b8;">{{ $i+1 }}</td>
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
            <div class="totaux-row"><span>Montant HT</span><span>{{ number_format($achat->montant_ht, 0, ',', ' ') }} FCFA</span></div>
            <div class="totaux-row"><span>TVA (18%)</span><span>{{ number_format($achat->montant_tva, 0, ',', ' ') }} FCFA</span></div>
            <div class="totaux-row grand"><span>TOTAL TTC</span><span>{{ number_format($achat->montant_ttc, 0, ',', ' ') }} FCFA</span></div>
        </div>
        <div style="margin-top:28px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px;">Mode de paiement</div>
                <div style="font-weight:700; margin-top:4px;">{{ $achat->mode_paiement }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px; color:#94a3b8; margin-bottom:8px;">Signature fournisseur</div>
                <div style="width:120px; height:60px; border-bottom:1px solid #e2e8f0;"></div>
            </div>
        </div>
    </div>
    <div class="footer">
        <span>{{ $achat->pointDeVente->entreprise->email }}</span>
        <span>Statut : {{ $achat->statut }}</span>
        <span>Généré le {{ now()->format('d/m/Y à H:i') }}</span>
    </div>
</div>
</body>
</html>
