@extends('admin::gabarits.application')
@section('titre', 'Factures — Ventes')
@section('topbar_titre', $type === 'avoir' ? 'Ventes — Avoirs' : 'Ventes — Factures & Commandes')

@section('contenu')
@php
    $isCaissier   = request()->routeIs('caissier.*');
    $routeBase    = $isCaissier ? 'caissier.ventes.factures' : 'admin.ventes.factures';
    $estDevisOuBC = in_array($etapeActive, ['Devis', 'Bon de commande']);
    $estBL        = $etapeActive === 'Bon de livraison';
    $nbBL         = $nbBL ?? 0;
@endphp

<div class="page-header">
    <div>
        @if($type === 'avoir')
            <h1><i class="fas fa-file-circle-minus" style="color:#e17055;"></i> Avoirs Clients</h1>
            <p>{{ $ventes->total() }} avoir(s) client(s) au total</p>
        @elseif($voirArchives)
            <h1><i class="fas fa-archive"></i> Archives — {{ $etapeActive === 'Devis' ? 'Devis' : 'Bons de commande' }}</h1>
            <p>Documents traités et archivés</p>
        @else
            <h1><i class="fas fa-file-invoice"></i> Cycles de vente</h1>
            <p>Suivi complet des devis, bons de commande et factures</p>
        @endif
    </div>
    @if($type === 'avoir')
        <button type="button" class="btn" style="background:#e17055; color:#fff;" onclick="ouvrirModalNouveauAvoir()">
            <i class="fas fa-plus"></i> Créer une facture d'avoir
        </button>
    @else
        @php $routeNouvelle = $isCaissier ? route('caissier.ventes.nouvelle') : route('admin.ventes.nouvelle'); @endphp
        <a href="{{ $routeNouvelle }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouvelle vente
        </a>
    @endif
</div>

{{-- Onglets du workflow vente --}}
@if($type !== 'avoir')
<div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:1.5px solid var(--border); padding-bottom:12px; flex-wrap:wrap; align-items:center;">
    <a href="{{ route($routeBase, ['etape' => 'Devis']) }}"
       style="display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; transition:all 0.2s;
              {{ $etapeActive === 'Devis' && !$voirArchives ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
        <i class="fas fa-file-invoice" style="font-size:14px; {{ $etapeActive === 'Devis' && !$voirArchives ? 'color:#fff;' : 'color:#0D1B3E;' }}"></i>
        Devis
        <span style="font-size:11px; padding:2px 8px; border-radius:20px; font-weight:800;
                     {{ $etapeActive === 'Devis' && !$voirArchives ? 'background:rgba(255,255,255,0.2); color:#fff;' : 'background:var(--bg3); color:var(--primary);' }}">
            {{ $nbDV }}
        </span>
    </a>

    <a href="{{ route($routeBase, ['etape' => 'Bon de commande']) }}"
       style="display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; transition:all 0.2s;
              {{ $etapeActive === 'Bon de commande' && !$voirArchives ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
        <i class="fas fa-shopping-basket" style="font-size:14px; {{ $etapeActive === 'Bon de commande' && !$voirArchives ? 'color:#fff;' : 'color:#0D1B3E;' }}"></i>
        Bon de commande
        <span style="font-size:11px; padding:2px 8px; border-radius:20px; font-weight:800;
                     {{ $etapeActive === 'Bon de commande' && !$voirArchives ? 'background:rgba(255,255,255,0.2); color:#fff;' : 'background:var(--bg3); color:var(--primary);' }}">
            {{ $nbBC }}
        </span>
    </a>

    {{-- Onglet Bon de Livraison --}}
    <a href="{{ route($routeBase, ['etape' => 'Bon de livraison']) }}"
       style="display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; transition:all 0.2s;
              {{ $estBL && !$voirArchives ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
        <i class="fas fa-truck" style="font-size:14px; {{ $estBL && !$voirArchives ? 'color:#fff;' : 'color:#0D1B3E;' }}"></i>
        Bon de Livraison
        <span style="font-size:11px; padding:2px 8px; border-radius:20px; font-weight:800;
                     {{ $estBL && !$voirArchives ? 'background:rgba(255,255,255,0.2); color:#fff;' : 'background:var(--bg3); color:var(--primary);' }}">
            {{ $nbBL }}
        </span>
    </a>

    <a href="{{ route($routeBase, ['etape' => 'Facture']) }}"
       style="display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; transition:all 0.2s;
              {{ $etapeActive === 'Facture' && !$voirArchives ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
        <i class="fas fa-check-double" style="font-size:14px; {{ $etapeActive === 'Facture' && !$voirArchives ? 'color:#fff;' : 'color:#0D1B3E;' }}"></i>
        Facture
        <span style="font-size:11px; padding:2px 8px; border-radius:20px; font-weight:800;
                     {{ $etapeActive === 'Facture' && !$voirArchives ? 'background:rgba(255,255,255,0.2); color:#fff;' : 'background:var(--bg3); color:var(--primary);' }}">
            {{ $nbFacture }}
        </span>
    </a>

    {{-- Bouton Archives (visible seulement pour Devis et BC) --}}
    @if($estDevisOuBC)
    <a href="{{ route($routeBase, ['etape' => $etapeActive, 'archives' => '1']) }}"
       style="display:flex; align-items:center; gap:8px; padding:10px 16px; border-radius:8px; text-decoration:none; font-weight:700; font-size:12.5px; margin-left:auto; transition:all 0.2s;
              {{ $voirArchives ? 'background:#7c3aed; color:#fff;' : 'background:#f5f3ff; color:#7c3aed; border:0.5px solid #ddd6fe;' }}">
        <i class="fas fa-box-archive"></i>
        {{ $voirArchives ? 'Voir actifs' : 'Archives' }}
    </a>
    @endif
</div>
@endif

{{-- Bandeau retour si vue archives --}}
@if($voirArchives)
<div style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:8px; padding:12px 16px; margin-bottom:16px; display:flex; align-items:center; gap:10px; color:#7c3aed;">
    <i class="fas fa-box-archive"></i>
    <span style="font-weight:600;">Archives — {{ $etapeActive === 'Devis' ? 'Devis traités' : 'Bons de commande traités' }}</span>
    <a href="{{ route($routeBase, ['etape' => $etapeActive]) }}" style="margin-left:auto; color:#7c3aed; font-size:12px; font-weight:700;">
        <i class="fas fa-arrow-left"></i> Retour aux actifs
    </a>
</div>
@endif

@if($estBL)
    @php
        $subStatut = request('statut');
        $statutsFiltre = [
            ''               => ['label' => 'Tous',          'icon' => 'fa-list'],
            'en_preparation' => ['label' => 'En cours de livraison','icon' => 'fa-clock'],
            'partiel'        => ['label' => 'Partiel',       'icon' => 'fa-triangle-exclamation'],
            'livre'          => ['label' => 'Livré',         'icon' => 'fa-check-circle'],
            'facture'        => ['label' => 'Facturé',       'icon' => 'fa-file-invoice'],
        ];
    @endphp
    <div style="display:flex; gap:8px; margin-bottom:15px; flex-wrap:wrap;">
        @foreach($statutsFiltre as $val => $info)
        <a href="{{ route($routeBase, ['etape' => 'Bon de livraison'] + ($val ? ['statut' => $val] : [])) }}"
           style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13px; transition:all .15s;
                  {{ $subStatut === ($val ?: null) || (!$subStatut && !$val) ? 'background:#0D1B3E; color:#fff;' : 'background:#fff; color:var(--text-2); border:0.5px solid var(--border);' }}">
            <i class="fas {{ $info['icon'] }}"></i> {{ $info['label'] }}
        </a>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="table-wrap">
        @if($estBL)
        {{-- =========== TABLE BONS DE LIVRAISON =========== --}}
        @if($ventes->isEmpty())
        <div style="padding:48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-truck" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucun bon de livraison actif.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th style="white-space:nowrap;">N° BL</th>
                    <th style="white-space:nowrap;">Date livraison</th>
                    <th style="white-space:nowrap;">Client</th>
                    <th style="white-space:nowrap;">Réf. BC</th>
                    <th style="white-space:nowrap;">Statut</th>
                    <th style="white-space:nowrap; text-align:center;">Partiel</th>
                    <th style="white-space:nowrap;">Facture liée</th>
                    <th style="white-space:nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventes as $bl)
                @php
                    $routeVoirBL   = $isCaissier ? route('caissier.ventes.livraison.voir', $bl) : route('admin.ventes.livraison.voir', $bl);
                    $routeLivrerBL = $isCaissier ? route('caissier.ventes.livraison.livrer', $bl) : route('admin.ventes.livraison.livrer', $bl);
                    $blBadgeStyle  = match($bl->statut) {
                        'en_preparation' => 'background:#f3f4f6; color:#374151;',
                        'partiel'        => 'background:#fffbeb; color:#b45309;',
                        'livre'          => 'background:#e0f2fe; color:#0369a1;',
                        'facture'        => 'background:#e6fdf5; color:#047857;',
                        default          => 'background:#f3f4f6; color:#374151;',
                    };
                @endphp
                <tr>
                    <td style="font-weight:700; color:var(--primary); white-space:nowrap;">{{ $bl->numero_bl }}</td>
                    <td style="white-space:nowrap;">{{ $bl->date_livraison->format('d/m/Y') }}</td>
                    <td style="white-space:nowrap;">{{ $bl->client?->nom ?? '— Passage —' }}</td>
                    <td style="white-space:nowrap; font-weight:600; color:var(--text-2);">{{ $bl->bonDeCommande->numero_facture }}</td>
                    <td style="white-space:nowrap;">
                        <span style="padding:4px 10px; border-radius:20px; font-weight:700; font-size:12px; {{ $blBadgeStyle }}">
                            {{ $bl->statut_label }}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        @if($bl->livraison_partielle)
                            <span style="color:#d97706;"><i class="fas fa-triangle-exclamation"></i></span>
                        @else
                            <span style="color:#059669;"><i class="fas fa-check-circle"></i></span>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        @if($bl->facture)
                            <span style="color:#047857; font-weight:600;">{{ $bl->facture->numero_facture }}</span>
                        @else
                            <span style="color:var(--text-3); font-size:12px;">—</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="display:flex; gap:6px;">
                            <a href="{{ $routeVoirBL }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            @if($bl->statut !== 'facture')
                            @php
                                $actionUrl = $isCaissier ? route('caissier.ventes.livraison.facturer', $bl) : route('admin.ventes.livraison.facturer', $bl);
                                $totalTtc  = $bl->bonDeCommande->montant_ttc;
                                $dejaLivre = $bl->statut === 'livre' ? 1 : 0;
                            @endphp
                            <button type="button" class="btn btn-success btn-sm btn-facturer-bl" style="font-weight:700; font-size:11px; padding:4px 8px; color: #fff;" data-action="{{ $actionUrl }}" data-total="{{ $totalTtc }}" data-deja-livre="{{ $dejaLivre }}">
                                <i class="fas fa-file-invoice-dollar"></i> Facturer
                            </button>
                            @endif
                            @if(!in_array($bl->statut, ['livre', 'facture']))
                            <form method="POST" action="{{ $routeLivrerBL }}" style="display:inline; margin:0;">
                                @csrf
                                <button type="submit" class="btn btn-sm" style="background:#e0f2fe; color:#0369a1; border:0.5px solid #7dd3fc; font-weight:700; font-size:11px; padding:4px 8px;">
                                    <i class="fas fa-check"></i> Livré
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($ventes->hasPages())
        <div style="padding:16px;">{{ $ventes->appends(request()->query())->links() }}</div>
        @endif
        @endif
        {{-- ================================================ --}}

        @else
        {{-- =========== TABLE VENTES (Devis / BC / Facture) =========== --}}
        @if($ventes->isEmpty())
        <div style="padding: 48px; text-align:center; color:var(--text-3);">
            <i class="fas {{ $voirArchives ? 'fa-box-archive' : 'fa-file-invoice' }}" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            {{ $voirArchives ? 'Aucun document archivé pour cette étape.' : 'Aucun élément disponible pour cette étape.' }}
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th style="white-space: nowrap;">
                        @if($type === 'avoir') N° Avoir
                        @elseif($etapeActive === 'Devis') N° Devis
                        @elseif($etapeActive === 'Bon de commande') N° Bon
                        @else N° Facture
                        @endif
                    </th>
                    <th style="white-space: nowrap;">Date</th>
                    <th style="white-space: nowrap;">Client</th>
                    <th style="white-space: nowrap;">Point de vente</th>
                    <th style="white-space: nowrap;">TTC</th>
                    {{-- Mode paiement seulement pour les factures --}}
                    @if(!$estDevisOuBC)
                    <th style="white-space: nowrap;">Mode paiement</th>
                    @endif
                    <th style="white-space: nowrap;">Statut</th>
                    {{-- Normalisé seulement pour les factures --}}
                    @if(!$estDevisOuBC)
                    <th style="white-space: nowrap; text-align: center;">Normalisée (DGI)</th>
                    @endif
                    <th style="white-space: nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventes as $vente)
                @php
                    $routeImprimer = $isCaissier ? route('caissier.ventes.imprimer', $vente) : route('admin.ventes.imprimer', $vente);
                    $routeModifier = $isCaissier ? route('caissier.ventes.modifier', $vente) : route('admin.ventes.modifier', $vente);
                    $routeEnvoyer  = $isCaissier ? route('caissier.ventes.envoyer', $vente) : route('admin.ventes.envoyer', $vente);
                    $routeConvertirCommande = $isCaissier ? route('caissier.ventes.convertir.commande', $vente) : route('admin.ventes.convertir.commande', $vente);
                    $routeConvertirFacture  = $isCaissier ? route('caissier.ventes.convertir.facture', $vente) : route('admin.ventes.convertir.facture', $vente);
                    $routeSupprimer = $isCaissier ? route('caissier.ventes.supprimer', $vente) : route('admin.ventes.supprimer', $vente);
                @endphp
                <tr>
                    <td style="font-weight:700; color:var(--primary); white-space: nowrap;">{{ $vente->numero_facture }}</td>
                    <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y') }}</td>
                    <td style="white-space: nowrap;">{{ $vente->client?->nom ?? '— Passage —' }}</td>
                    <td style="font-weight:500; color:var(--text-2); white-space: nowrap;"><i class="fas fa-store" style="font-size:11px; margin-right:4px;"></i>{{ $vente->pointDeVente->nom }}</td>
                    <td style="font-weight:700; color:var(--text); white-space: nowrap;">{{ number_format($vente->montant_ttc, 0, ',', ' ') }} F</td>

                    {{-- Mode paiement : seulement pour les factures --}}
                    @if(!$estDevisOuBC)
                    <td style="white-space: nowrap;">{{ $vente->mode_paiement }}</td>
                    @endif

                    {{-- Statut : workflow pour Devis/BC, paiement pour Facture --}}
                    <td style="white-space: nowrap;">
                        @if($estDevisOuBC)
                            @if($vente->statut === 'Envoyé')
                                <span style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:20px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                                    <i class="fas fa-paper-plane" style="font-size:10px;"></i> Envoyé
                                </span>
                            @else
                                <span style="background:#f3f4f6; color:#374151; padding:4px 10px; border-radius:20px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                                    <i class="fas fa-pencil" style="font-size:10px;"></i> Brouillon
                                </span>
                            @endif
                        @else
                            @if($vente->statut === 'Payé')
                                <span style="background:#e6fdf5; color:#0f766e; padding:4px 10px; border-radius:20px; font-weight:700;">Payé</span>
                            @elseif($vente->statut === 'Crédit')
                                <span style="background:#fef2f2; color:#991b1b; padding:4px 10px; border-radius:20px; font-weight:700;">Crédit</span>
                            @else
                                <span style="background:#fffbeb; color:#92400e; padding:4px 10px; border-radius:20px; font-weight:700;">Avance</span>
                            @endif
                        @endif
                    </td>

                    {{-- Normalisé : seulement pour les factures --}}
                    @if(!$estDevisOuBC)
                    <td style="text-align: center; white-space: nowrap;">
                        @if($vente->normalise)
                            <span style="color:#059669; font-weight:800; font-size:13px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="fas fa-check-circle"></i> Oui
                            </span>
                        @else
                            <span style="color:var(--text-3); font-size:12px;">Non</span>
                        @endif
                    </td>
                    @endif

                    {{-- Colonne Actions --}}
                    <td style="white-space: nowrap;">
                        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                            {{-- Voir le document --}}
                            <a href="{{ $routeImprimer }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> Voir
                            </a>

                            @if($voirArchives)
                                {{-- Mode archives : seul bouton Supprimer --}}
                                <form method="POST" action="{{ $routeSupprimer }}" onsubmit="return confirm('Supprimer définitivement ce document ? Cette action est irréversible.')" style="display:inline; margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm" style="background:#fef2f2; color:#dc2626; border:0.5px solid #fca5a5; font-weight:700; font-size:11px; padding:4px 8px;">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </form>
                            @elseif($estDevisOuBC)
                                {{-- Mode Devis / Bon de commande : actions de workflow --}}

                                {{-- Modifier (si non normalisé, toujours pour devis/BC) --}}
                                <a href="{{ $routeModifier }}" class="btn btn-outline btn-sm" style="padding:4px 8px; font-size:11px;" title="Modifier">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>

                                {{-- Envoyer (si statut Brouillon) --}}
                                @if($vente->statut !== 'Envoyé')
                                <form method="POST" action="{{ $routeEnvoyer }}" style="display:inline; margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm" style="background:#e0f2fe; color:#0369a1; border:0.5px solid #7dd3fc; font-weight:700; font-size:11px; padding:4px 8px;" title="Marquer comme envoyé">
                                        <i class="fas fa-paper-plane"></i> Envoyer
                                    </button>
                                </form>
                                @endif

                                {{-- Passer en commande (Devis → BC) --}}
                                @if($etapeActive === 'Devis')
                                <form method="POST" action="{{ $routeConvertirCommande }}" style="display:inline; margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm" style="background:#fefce8; color:#854d0e; border:0.5px solid #fde68a; font-weight:700; font-size:11px; padding:4px 8px;" title="Convertir en bon de commande">
                                        <i class="fas fa-arrow-right"></i> &rarr; Commande
                                    </button>
                                </form>
                                @endif

                                {{-- Passer en BL (BC → BL) --}}
                                @if($etapeActive === 'Bon de commande')
                                    @if(!$vente->bonLivraison)
                                    {{-- Pas encore de BL : proposer de créer le BL --}}
                                    <a href="{{ $isCaissier ? route('caissier.ventes.livraison.creer', $vente) : route('admin.ventes.livraison.creer', $vente) }}"
                                       class="btn btn-sm" style="background:#e0f2fe; color:#0369a1; border:0.5px solid #7dd3fc; font-weight:700; font-size:11px; padding:4px 8px;" title="Créer le bon de livraison">
                                        <i class="fas fa-truck"></i> &rarr; BL
                                    </a>
                                    @else
                                    {{-- BL existant : lien vers le BL (le paiement se fait sur la page BL) --}}
                                    <a href="{{ $isCaissier ? route('caissier.ventes.livraison.voir', $vente->bonLivraison) : route('admin.ventes.livraison.voir', $vente->bonLivraison) }}"
                                       class="btn btn-sm" style="background:#e0f2fe; color:#0369a1; border:0.5px solid #7dd3fc; font-weight:700; font-size:11px; padding:4px 8px;">
                                        <i class="fas fa-truck"></i> Voir BL
                                    </a>
                                    @endif
                                @endif

                            @else
                                {{-- Mode Facture : actions habituelles --}}
                                <a href="{{ $isCaissier ? route('caissier.ventes.ticket', $vente) : route('admin.ventes.ticket', $vente) }}"
                                   class="btn btn-outline btn-sm" style="padding:4px 8px; font-size:11px; border-color:var(--success); color:var(--success);" title="Imprimer le Ticket RNE">
                                    <i class="fas fa-print"></i> Ticket
                                </a>
                                <button type="button" onclick="telechargerDirectement('{{ $routeImprimer }}?download=1')"
                                        class="btn btn-outline btn-sm" style="padding:4px 8px; font-size:11px;" title="Télécharger le PDF">
                                    <i class="fas fa-download"></i>
                                </button>

                                @if(!$vente->normalise)
                                <a href="{{ $routeModifier }}" class="btn btn-outline btn-sm" style="padding:4px 8px; font-size:11px;" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endif

                                {{-- Normalisation DGI --}}
                                @if(!$vente->normalise)
                                <form method="POST" action="{{ $isCaissier ? route('caissier.ventes.normaliser', $vente) : route('admin.ventes.normaliser', $vente) }}" style="display:inline; margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" style="font-weight:700; font-size:11px; padding:4px 8px;" title="Normaliser manuellement">
                                        <i class="fas fa-share-nodes"></i> Normaliser
                                    </button>
                                </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($ventes->hasPages())
        <div style="padding: 16px;">{{ $ventes->appends(request()->query())->links() }}</div>
        @endif
        @endif
        {{-- Fin de @else : section Ventes (Devis / BC / Facture) --}}
        @endif
    </div>
</div>

<script>
function telechargerDirectement(url) {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'absolute';
    iframe.style.width = '1024px';
    iframe.style.height = '768px';
    iframe.style.top = '-9999px';
    iframe.style.left = '-9999px';
    iframe.style.border = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    setTimeout(() => { iframe.remove(); }, 15000);
}
</script>

@if($type === 'avoir')
<div class="modal-overlay" id="modalNouveauAvoir" style="display:none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div style="background: #fff; border-radius: 16px; max-width: 800px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: #f8fafc;">
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-file-circle-minus" style="color:#e17055;"></i> Nouvelle Facture d'Avoir
            </h3>
            <button type="button" onclick="fermerModalNouveauAvoir()" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route($isCaissier ? 'caissier.ventes.avoir.creer_nouveau' : 'admin.ventes.avoir.creer_nouveau') }}" style="margin:0; display:flex; flex-direction:column; overflow:hidden;">
            @csrf
            <div style="padding: 24px; overflow-y: auto; flex-grow: 1; max-height: 60vh;">
                <!-- Choix de la facture d'origine -->
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 6px; color: #334155;">Choisir la facture de doit d'origine *</label>
                    <select id="selectFactureAvoir" onchange="if(this.value) { selectionnerFacturePourAvoir(this.value); } else { masquerDetailsFactureAvoir(); }" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #0f172a; background: #fff;">
                        <option value="">-- Sélectionner une facture --</option>
                        @foreach($facturesDispo as $f)
                            <option value="{{ $f->id }}">{{ $f->numero_facture }} - {{ $f->client?->nom ?? 'Client de passage' }} ({{ number_format($f->montant_ttc, 0, ',', ' ') }} F)</option>
                        @endforeach
                    </select>
                </div>

                <div id="factureDetailsAvoir" style="display: none;">
                    <input type="hidden" name="parent_id" id="avoir_parent_id">
                    
                    <div style="background: #f1f5f9; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid #e17055;">
                        <div>
                            <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Facture Sélectionnée</div>
                            <div id="avoir_facture_ref" style="font-size: 16px; font-weight: 800; color: #0f172a;">FAC-0000</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Client d'origine</div>
                            <div id="avoir_client_nom" style="font-size: 15px; font-weight: 700; color: #0f172a;">—</div>
                        </div>
                    </div>

                    <!-- Raison / Motif -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 6px; color: #334155;">Motif / Raison de l'avoir *</label>
                        <input type="text" name="raison" class="form-control" required placeholder="Ex: Retour produit défectueux, remise commerciale..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                    </div>

                    <!-- Articles -->
                    <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 10px; color: #334155;">Sélectionner les articles et ajuster les quantités à créditer</label>
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom: 1.5px solid #cbd5e1; text-align: left;">
                                <th style="padding: 10px;">Désignation</th>
                                <th style="padding: 10px; text-align: center; width: 100px;">Qté Initiale</th>
                                <th style="padding: 10px; text-align: center; width: 120px;">Qté Avoir</th>
                                <th style="padding: 10px; text-align: right; width: 120px;">Prix Unit.</th>
                                <th style="padding: 10px; width: 180px;">Action sur Stock</th>
                            </tr>
                        </thead>
                        <tbody id="avoirItemsTableBody">
                            <!-- Rempli dynamiquement -->
                        </tbody>
                    </table>

                    <!-- Ajout d'autres articles / Saisies libres -->
                    <div style="margin-top: 24px; border-top: 1.5px solid #cbd5e1; padding-top: 16px;">
                        <h4 style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; text-align: left;">
                            <i class="fas fa-plus-circle" style="color:#e17055;"></i> Ajouter d'autres articles
                        </h4>
                        
                        <div style="display: flex; gap: 8px; margin-bottom: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                            <button type="button" id="tab_catalog_btn" onclick="switchAddMode('catalog')" style="padding: 6px 12px; font-size: 11px; font-weight:700; border-radius: 6px; border: none; cursor: pointer; background: #e17055; color: #fff;">Catalogue Produits</button>
                            <button type="button" id="tab_free_btn" onclick="switchAddMode('free')" style="padding: 6px 12px; font-size: 11px; font-weight:700; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer; background: #fff; color: #475569;">Saisie Libre (Hors Catalogue)</button>
                        </div>

                        <!-- Panel Catalogue -->
                        <div id="panel_catalog" style="display: block; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; align-items: end;">
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Sélectionner le produit *</label>
                                    <select id="catalog_product_id" onchange="onCatalogProductChange()" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background:#fff; font-size: 12px;">
                                        <option value="">-- Choisir un produit --</option>
                                    </select>
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Quantité *</label>
                                    <input type="number" id="catalog_qty" value="1" min="1" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-align: center;">
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Prix U. Avoir *</label>
                                    <input type="number" id="catalog_price" value="0" min="0" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-align: right;">
                                </div>
                                <div>
                                    <button type="button" onclick="ajouterArticleDuCatalogue()" style="width: 100%; padding: 8px 12px; background: #e17055; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; height: 35px;">
                                        <i class="fas fa-plus"></i> Ajouter
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Saisie Libre -->
                        <div id="panel_free" style="display: none; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 10px; align-items: end;">
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Description / Libellé *</label>
                                    <input type="text" id="free_label" placeholder="Ex: Remise ou correction" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px;">
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Quantité *</label>
                                    <input type="number" id="free_qty" value="1" min="1" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-align: center;">
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Prix U. Avoir *</label>
                                    <input type="number" id="free_price" value="0" min="0" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-align: right;">
                                </div>
                                <div style="text-align: left;">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Taux TVA</label>
                                    <select id="free_tva_rate" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background:#fff; font-size: 12px;">
                                        <option value="18">TVA 18%</option>
                                        <option value="0">TVA 0%</option>
                                    </select>
                                </div>
                                <div>
                                    <button type="button" onclick="ajouterSaisieLibre()" style="width: 100%; padding: 8px 12px; background: #e17055; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; height: 35px;">
                                        <i class="fas fa-plus"></i> Ajouter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc;">
                <button type="button" class="btn btn-outline" onclick="fermerModalNouveauAvoir()">Annuler</button>
                <button type="submit" id="btnValiderAvoir" class="btn" style="background:#e17055; color:#fff;" disabled>Créer la Facture d'Avoir</button>
            </div>
        </form>
    </div>
</div>


<script>
let searchTimeout = null;
let catalogProducts = {};
let customItemCounter = 0;

function switchAddMode(mode) {
    const tabCatalog = document.getElementById('tab_catalog_btn');
    const tabFree = document.getElementById('tab_free_btn');
    const panelCatalog = document.getElementById('panel_catalog');
    const panelFree = document.getElementById('panel_free');
    
    if (mode === 'catalog') {
        tabCatalog.style.background = '#e17055';
        tabCatalog.style.color = '#fff';
        tabCatalog.style.border = 'none';
        
        tabFree.style.background = '#fff';
        tabFree.style.color = '#475569';
        tabFree.style.border = '1px solid #cbd5e1';
        
        panelCatalog.style.display = 'block';
        panelFree.style.display = 'none';
    } else {
        tabFree.style.background = '#e17055';
        tabFree.style.color = '#fff';
        tabFree.style.border = 'none';
        
        tabCatalog.style.background = '#fff';
        tabCatalog.style.color = '#475569';
        tabCatalog.style.border = '1px solid #cbd5e1';
        
        panelCatalog.style.display = 'none';
        panelFree.style.display = 'block';
    }
}

function chargerProduitsParCategorie() {
    const select = document.getElementById('catalog_product_id');
    const url = "{{ route($isCaissier ? 'caissier.ventes.factures.produits_categories' : 'admin.ventes.factures.produits_categories') }}";
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            catalogProducts = data;
            select.innerHTML = '<option value="">-- Choisir un produit --</option>';
            
            for (const [catName, list] of Object.entries(data)) {
                const group = document.createElement('optgroup');
                group.label = catName;
                
                list.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.dataset.price = p.prix_vente;
                    opt.dataset.unit = p.unite;
                    opt.dataset.stockable = p.est_stockable ? 1 : 0;
                    opt.dataset.tva = p.taux_tva;
                    opt.dataset.name = p.nom;
                    opt.textContent = `${p.nom} (${p.prix_vente} F CFA / ${p.unite})`;
                    group.appendChild(opt);
                });
                
                select.appendChild(group);
            }
        });
}

function onCatalogProductChange() {
    const select = document.getElementById('catalog_product_id');
    const selectedOpt = select.options[select.selectedIndex];
    if (!selectedOpt || selectedOpt.value === "") {
        document.getElementById('catalog_price').value = 0;
        return;
    }
    document.getElementById('catalog_price').value = selectedOpt.dataset.price;
}

function ajouterArticleDuCatalogue() {
    const select = document.getElementById('catalog_product_id');
    const selectedOpt = select.options[select.selectedIndex];
    if (!selectedOpt || selectedOpt.value === "") {
        alert("Veuillez sélectionner un produit du catalogue.");
        return;
    }
    
    const productId = selectedOpt.value;
    const price = parseFloat(document.getElementById('catalog_price').value) || 0;
    const qty = parseFloat(document.getElementById('catalog_qty').value) || 1;
    const isStockable = parseInt(selectedOpt.dataset.stockable);
    const tva = selectedOpt.dataset.tva;
    const name = selectedOpt.dataset.name;
    
    customItemCounter++;
    const rowId = `new_catalog_${customItemCounter}`;
    
    const tbody = document.getElementById('avoirItemsTableBody');
    const tr = document.createElement('tr');
    tr.style.borderBottom = '0.5px solid #e2e8f0';
    
    // Designation
    const tdNom = document.createElement('td');
    tdNom.style.padding = '10px';
    tdNom.innerHTML = `
        <strong>${name}</strong> <span style="font-size: 10px; background:#dcfce7; color:#15803d; padding:2px 6px; border-radius:4px; margin-left:4px;">Catalogue</span>
        <input type="hidden" name="items[${rowId}][est_nouveau]" value="1">
        <input type="hidden" name="items[${rowId}][produit_id]" value="${productId}">
        <input type="hidden" name="items[${rowId}][libelle_virtuel]" value="${name}">
        <input type="hidden" name="items[${rowId}][taux_tva]" value="${tva}">
    `;
    tr.appendChild(tdNom);
    
    // Original Qty
    const tdOriginalQty = document.createElement('td');
    tdOriginalQty.style.padding = '10px';
    tdOriginalQty.style.textAlign = 'center';
    tdOriginalQty.style.fontWeight = '600';
    tdOriginalQty.textContent = `—`;
    tr.appendChild(tdOriginalQty);
    
    // Return Qty
    const tdReturnQty = document.createElement('td');
    tdReturnQty.style.padding = '10px';
    tdReturnQty.innerHTML = `<input type="number" name="items[${rowId}][quantite]" class="form-control" value="${qty}" min="0.01" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center;">`;
    tr.appendChild(tdReturnQty);
    
    // Price
    const tdPrice = document.createElement('td');
    tdPrice.style.padding = '10px';
    tdPrice.style.textAlign = 'right';
    tdPrice.innerHTML = `<input type="number" name="items[${rowId}][prix_unitaire]" class="form-control" value="${price}" min="0" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: right;">`;
    tr.appendChild(tdPrice);
    
    // Stock Action
    const tdStockAction = document.createElement('td');
    tdStockAction.style.padding = '10px';
    if (isStockable) {
        tdStockAction.innerHTML = `
            <select name="items[${rowId}][stock_action]" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                <option value="reinject">Réinjecter en stock (Bon état)</option>
                <option value="scrap">Mettre au rebut (Défectueux)</option>
                <option value="none">Aucun retour physique</option>
            </select>
        `;
    } else {
        tdStockAction.innerHTML = `<span style="color:#64748b; font-style:italic;">Non stockable</span><input type="hidden" name="items[${rowId}][stock_action]" value="none">`;
    }
    tr.appendChild(tdStockAction);
    
    tbody.appendChild(tr);
    
    // Reset selections
    select.value = "";
    document.getElementById('catalog_price').value = 0;
    document.getElementById('catalog_qty').value = 1;
}

function ajouterSaisieLibre() {
    const labelInput = document.getElementById('free_label');
    const label = labelInput.value.trim();
    if (label === "") {
        alert("Veuillez saisir un libellé pour la ligne libre.");
        return;
    }
    
    const price = parseFloat(document.getElementById('free_price').value) || 0;
    const qty = parseFloat(document.getElementById('free_qty').value) || 1;
    const tva = document.getElementById('free_tva_rate').value;
    
    customItemCounter++;
    const rowId = `new_free_${customItemCounter}`;
    
    const tbody = document.getElementById('avoirItemsTableBody');
    const tr = document.createElement('tr');
    tr.style.borderBottom = '0.5px solid #e2e8f0';
    
    // Designation
    const tdNom = document.createElement('td');
    tdNom.style.padding = '10px';
    tdNom.innerHTML = `
        <strong>${label}</strong> <span style="font-size: 10px; background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; margin-left:4px;">Saisie libre</span>
        <input type="hidden" name="items[${rowId}][est_nouveau]" value="1">
        <input type="hidden" name="items[${rowId}][libelle_virtuel]" value="${label}">
        <input type="hidden" name="items[${rowId}][taux_tva]" value="${tva}">
    `;
    tr.appendChild(tdNom);
    
    // Original Qty
    const tdOriginalQty = document.createElement('td');
    tdOriginalQty.style.padding = '10px';
    tdOriginalQty.style.textAlign = 'center';
    tdOriginalQty.style.fontWeight = '600';
    tdOriginalQty.textContent = `—`;
    tr.appendChild(tdOriginalQty);
    
    // Return Qty
    const tdReturnQty = document.createElement('td');
    tdReturnQty.style.padding = '10px';
    tdReturnQty.innerHTML = `<input type="number" name="items[${rowId}][quantite]" class="form-control" value="${qty}" min="0.01" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center;">`;
    tr.appendChild(tdReturnQty);
    
    // Price
    const tdPrice = document.createElement('td');
    tdPrice.style.padding = '10px';
    tdPrice.style.textAlign = 'right';
    tdPrice.innerHTML = `<input type="number" name="items[${rowId}][prix_unitaire]" class="form-control" value="${price}" min="0" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: right;">`;
    tr.appendChild(tdPrice);
    
    // Stock Action
    const tdStockAction = document.createElement('td');
    tdStockAction.style.padding = '10px';
    tdStockAction.innerHTML = `<span style="color:#64748b; font-style:italic;">Non stockable</span><input type="hidden" name="items[${rowId}][stock_action]" value="none">`;
    tr.appendChild(tdStockAction);
    
    tbody.appendChild(tr);
    
    // Reset selections
    labelInput.value = "";
    document.getElementById('free_price').value = 0;
    document.getElementById('free_qty').value = 1;
}

function ouvrirModalNouveauAvoir() {
    document.getElementById('modalNouveauAvoir').style.display = 'flex';
    chargerProduitsParCategorie();
}

function fermerModalNouveauAvoir() {
    document.getElementById('modalNouveauAvoir').style.display = 'none';
    const selectEl = document.getElementById('selectFactureAvoir');
    if (selectEl) selectEl.value = '';
    document.getElementById('factureDetailsAvoir').style.display = 'none';
    document.getElementById('btnValiderAvoir').disabled = true;
}

function masquerDetailsFactureAvoir() {
    document.getElementById('factureDetailsAvoir').style.display = 'none';
    document.getElementById('btnValiderAvoir').disabled = true;
}

function chercherFactures(query) {
    clearTimeout(searchTimeout);
    if (query.length < 2) {
        document.getElementById('autocompleteResults').style.display = 'none';
        return;
    }

    searchTimeout = setTimeout(() => {
        const url = "{{ route($isCaissier ? 'caissier.ventes.factures.rechercher' : 'admin.ventes.factures.rechercher') }}?q=" + encodeURIComponent(query);
        fetch(url)
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('autocompleteResults');
                container.innerHTML = '';
                if (data.length === 0) {
                    container.innerHTML = '<div style="padding: 10px; color: #64748b; font-style: italic;">Aucune facture trouvée</div>';
                } else {
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.style.padding = '10px 14px';
                        div.style.cursor = 'pointer';
                        div.style.borderBottom = '0.5px solid #f1f5f9';
                        div.style.fontSize = '13px';
                        div.style.fontWeight = '600';
                        div.style.color = '#0f172a';
                        div.innerHTML = item.text;
                        div.onclick = () => selectionnerFacturePourAvoir(item.id);
                        div.onmouseover = () => div.style.background = '#f1f5f9';
                        div.onmouseout = () => div.style.background = '#fff';
                        container.appendChild(div);
                    });
                }
                container.style.display = 'block';
            });
    }, 300);
}

function selectionnerFacturePourAvoir(id) {
    const baseUrl = "{{ route($isCaissier ? 'caissier.ventes.factures.details' : 'admin.ventes.factures.details', ':id') }}";
    const url = baseUrl.replace(':id', id);

    fetch(url)
        .then(res => res.json())
        .then(data => {
            document.getElementById('avoir_parent_id').value = data.id;
            document.getElementById('avoir_facture_ref').textContent = data.numero_facture;
            document.getElementById('avoir_client_nom').textContent = data.client_nom;
            
            const tbody = document.getElementById('avoirItemsTableBody');
            tbody.innerHTML = '';
            
            data.details.forEach(item => {
                const tr = document.createElement('tr');
                tr.style.borderBottom = '0.5px solid #e2e8f0';
                
                // Designation
                const tdNom = document.createElement('td');
                tdNom.style.padding = '10px';
                tdNom.innerHTML = `<strong>${item.libelle}</strong><input type="hidden" name="items[${item.id}][id]" value="${item.id}">`;
                tr.appendChild(tdNom);
                
                // Original Qty
                const tdOriginalQty = document.createElement('td');
                tdOriginalQty.style.padding = '10px';
                tdOriginalQty.style.textAlign = 'center';
                tdOriginalQty.style.fontWeight = '600';
                tdOriginalQty.textContent = `${item.quantite} ${item.unite}`;
                tr.appendChild(tdOriginalQty);
                
                // Return Qty
                const tdReturnQty = document.createElement('td');
                tdReturnQty.style.padding = '10px';
                tdReturnQty.innerHTML = `<input type="number" name="items[${item.id}][quantite]" class="form-control" value="${item.quantite}" min="0" max="${item.quantite}" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center;">`;
                tr.appendChild(tdReturnQty);
                
                // Price
                const tdPrice = document.createElement('td');
                tdPrice.style.padding = '10px';
                tdPrice.style.textAlign = 'right';
                tdPrice.innerHTML = `<input type="number" name="items[${item.id}][prix_unitaire]" class="form-control" value="${item.prix_unitaire}" min="0" required style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: right;">`;
                tr.appendChild(tdPrice);
                
                // Stock Action
                const tdStockAction = document.createElement('td');
                tdStockAction.style.padding = '10px';
                if (item.est_stockable) {
                    tdStockAction.innerHTML = `
                        <select name="items[${item.id}][stock_action]" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                            <option value="reinject">Réinjecter en stock (Bon état)</option>
                            <option value="scrap">Mettre au rebut (Défectueux)</option>
                            <option value="none">Aucun retour physique</option>
                        </select>
                    `;
                } else {
                    tdStockAction.innerHTML = `<span style="color:#64748b; font-style:italic;">Non stockable</span><input type="hidden" name="items[${item.id}][stock_action]" value="none">`;
                }
                tr.appendChild(tdStockAction);
                
                tbody.appendChild(tr);
            });
            
            document.getElementById('factureDetailsAvoir').style.display = 'block';
            document.getElementById('btnValiderAvoir').disabled = false;
        });
}

</script>
@endif

{{-- Modal choix facturation & règlement pour les BL depuis la liste --}}
<div class="modal-overlay" id="modal-facturer-bl" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#fff; border-radius:14px; max-width:480px; width:100%; padding:28px; box-shadow:0 20px 60px rgba(0,0,0,.2); margin:auto; display:flex; flex-direction:column;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="font-size:18px; font-weight:800; margin:0; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-file-invoice-dollar" style="color:#047857;"></i> Valider & Facturer
            </h3>
            <button type="button" onclick="document.getElementById('modal-facturer-bl').style.display='none'" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <p style="font-size:13px; color:#6b7280; margin-bottom:20px;">Veuillez configurer les options de facturation et de règlement :</p>

        <form method="POST" action="" id="form-convert-bl-facture-list" style="margin:0;">
            @csrf

            {{-- 1. Base de facturation --}}
            <div style="margin-bottom:18px;">
                <label style="font-weight:700; font-size:12px; text-transform:uppercase; color:#475569; display:block; margin-bottom:6px;">Base de facturation</label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <label style="border:1px solid #047857; background:#f0fdf4; border-radius:8px; padding:10px; cursor:pointer; display:flex; gap:8px; align-items:center;" id="label-livree-list">
                        <input type="radio" name="base_facturation" id="r-livree-list" value="livree" checked onchange="majBaseFacturationList()">
                        <span style="font-size:12.5px; font-weight:600;">Qtés livrées (BL)</span>
                    </label>
                    <label style="border:1px solid #e2e5ec; border-radius:8px; padding:10px; cursor:pointer; display:flex; gap:8px; align-items:center;" id="label-commandee-list">
                        <input type="radio" name="base_facturation" id="r-commandee-list" value="commandee" onchange="majBaseFacturationList()">
                        <span style="font-size:12.5px; font-weight:600;">Qtés BC d'origine</span>
                    </label>
                </div>
            </div>

            {{-- 2. Mode de paiement --}}
            <div style="margin-bottom:18px;">
                <label style="font-weight:700; font-size:12px; text-transform:uppercase; color:#475569; display:block; margin-bottom:6px;">Mode de paiement <span style="color:#dc2626">*</span></label>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                    <label style="border:1px solid #047857; background:#f0fdf4; border-radius:8px; padding:10px; cursor:pointer; text-align:center;" id="label-mode-caisse-list">
                        <input type="radio" name="mode_paiement" value="Caisse" checked style="display:none;" onchange="selectionnerModeList('Caisse')">
                        <span style="font-size:12.5px; font-weight:700; color:#166534;">Caisse</span>
                    </label>
                    <label style="border:1px solid #e2e5ec; border-radius:8px; padding:10px; cursor:pointer; text-align:center;" id="label-mode-banque-list">
                        <input type="radio" name="mode_paiement" value="Banque" style="display:none;" onchange="selectionnerModeList('Banque')">
                        <span style="font-size:12.5px; font-weight:700; color:#475569;">Banque</span>
                    </label>
                    <label style="border:1px solid #e2e5ec; border-radius:8px; padding:10px; cursor:pointer; text-align:center;" id="label-mode-credit-list">
                        <input type="radio" name="mode_paiement" value="Crédit" style="display:none;" onchange="selectionnerModeList('Crédit')">
                        <span style="font-size:12.5px; font-weight:700; color:#475569;">Crédit</span>
                    </label>
                </div>
            </div>

            {{-- Bloc Banque (caché par défaut) --}}
            <div id="selection-banque-bl-list" style="display:none; background:#f8fafc; border:1px solid #e2e5ec; border-radius:10px; padding:16px; margin-bottom:18px;">
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600; font-size:12px; display:block; margin-bottom:4px;">Sélectionner la banque *</label>
                    <select name="banque_id" id="bl-banque-select-list" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                        <option value="">— Choisir la banque —</option>
                        @if(isset($banques))
                        @foreach($banques as $b)
                            <option value="{{ $b->id }}">{{ $b->intitule }} ({{ $b->code }})</option>
                        @endforeach
                        @endif
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600; font-size:12px; display:block; margin-bottom:4px;">Moyen de paiement bancaire *</label>
                    <select name="moyen_bancaire" id="bl-moyen-select-list" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                        <option value="">— Choisir le moyen —</option>
                        <option value="carte">Carte bancaire</option>
                        <option value="virement">Virement</option>
                        <option value="cheque">Chèque</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:600; font-size:12px; display:block; margin-bottom:4px;">Référence *</label>
                    <input type="text" name="reference_paiement" id="bl-ref-input-list" placeholder="Numéro..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
            </div>

            {{-- 3. Montant payé --}}
            <div style="margin-bottom:18px;" id="bloc-montant-paye-list">
                <label style="font-weight:700; font-size:12px; text-transform:uppercase; color:#475569; display:block; margin-bottom:6px;" id="label-montant-paye-list">Montant reçu / réglé *</label>
                <input type="number" name="montant_paye" id="bl-montant-input-list" value="0" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; font-weight:700;">
            </div>

            {{-- 4. Case à cocher Livraison Immédiate --}}
            <div id="container-livraison-immediate-list" style="margin-bottom:20px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:12px 14px; display:none;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; font-size:13px; color:#166534;">
                    <input type="checkbox" name="livraison_immediate" value="1" checked style="width:16px; height:16px; cursor:pointer;">
                    Marquer la livraison comme immédiate et finale ?
                </label>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-facturer-bl').style.display='none'" style="padding:9px 18px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; font-weight:600; cursor:pointer;">
                    Annuler
                </button>
                <button type="submit" style="padding:9px 18px; border-radius:8px; background:#047857; color:#fff; border:none; font-weight:700; cursor:pointer;">
                    <i class="fas fa-check"></i> Créer la Facture
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function selectionnerModeList(mode) {
    const modes = {
        'Caisse': { label: 'label-mode-caisse-list', border: '#047857', bg: '#f0fdf4', tx: '#166534' },
        'Banque': { label: 'label-mode-banque-list', border: '#0284c7', bg: '#f0f9ff', tx: '#0369a1' },
        'Crédit': { label: 'label-mode-credit-list', border: '#dc2626', bg: '#fef2f2', tx: '#991b1b' }
    };

    // Reset all labels
    Object.keys(modes).forEach(m => {
        const el = document.getElementById(modes[m].label);
        if (el) {
            el.style.border = '1px solid #e2e5ec';
            el.style.background = '#fff';
            el.querySelector('span').style.color = '#475569';
            el.querySelector('input').checked = (m === mode);
        }
    });

    // Style the active label
    const active = modes[mode];
    const activeEl = document.getElementById(active.label);
    if (activeEl) {
        activeEl.style.border = '1px solid ' + active.border;
        activeEl.style.background = active.bg;
        activeEl.querySelector('span').style.color = active.tx;
    }

    // Toggle blocks
    const banqueBlock = document.getElementById('selection-banque-bl-list');
    const montantBlock = document.getElementById('bloc-montant-paye-list');
    const banqueSelect = document.getElementById('bl-banque-select-list');
    const moyenSelect = document.getElementById('bl-moyen-select-list');
    const refInput = document.getElementById('bl-ref-input-list');
    const montantInput = document.getElementById('bl-montant-input-list');

    if (mode === 'Banque') {
        banqueBlock.style.display = 'block';
        montantBlock.style.display = 'block';
        if (banqueSelect) banqueSelect.required = true;
        if (moyenSelect) moyenSelect.required = true;
        if (refInput) refInput.required = true;
        if (montantInput) montantInput.disabled = false;
    } else if (mode === 'Caisse') {
        banqueBlock.style.display = 'none';
        montantBlock.style.display = 'block';
        if (banqueSelect) banqueSelect.required = false;
        if (moyenSelect) moyenSelect.required = false;
        if (refInput) refInput.required = false;
        if (montantInput) montantInput.disabled = false;
    } else { // Crédit
        banqueBlock.style.display = 'none';
        montantBlock.style.display = 'none';
        if (banqueSelect) banqueSelect.required = false;
        if (moyenSelect) moyenSelect.required = false;
        if (refInput) refInput.required = false;
        if (montantInput) {
            montantInput.value = 0;
            montantInput.disabled = true;
        }
    }
}

function majBaseFacturationList() {
    const isLivree = document.getElementById('r-livree-list').checked;
    const labelLivree = document.getElementById('label-livree-list');
    const labelCommandee = document.getElementById('label-commandee-list');

    if (isLivree) {
        labelLivree.style.border = '1px solid #047857';
        labelLivree.style.background = '#f0fdf4';
        labelCommandee.style.border = '1px solid #e2e5ec';
        labelCommandee.style.background = '#fff';
    } else {
        labelCommandee.style.border = '1px solid #047857';
        labelCommandee.style.background = '#f0fdf4';
        labelLivree.style.border = '1px solid #e2e5ec';
        labelLivree.style.background = '#fff';
    }
}

function ouvrirModalFacturerBL(actionUrl, totalTtc, dejaLivre) {
    const modal = document.getElementById('modal-facturer-bl');
    const form = document.getElementById('form-convert-bl-facture-list');
    
    form.action = actionUrl;
    
    const montantInput = document.getElementById('bl-montant-input-list');
    montantInput.value = Math.round(totalTtc);
    
    const checkContainer = document.getElementById('container-livraison-immediate-list');
    if (dejaLivre) {
        checkContainer.style.display = 'none';
    } else {
        checkContainer.style.display = 'block';
    }
    
    selectionnerModeList('Caisse');
    document.getElementById('r-livree-list').checked = true;
    majBaseFacturationList();
    
    modal.style.display = 'flex';
}

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-facturer-bl');
        if (btn) {
            e.preventDefault();
            const actionUrl = btn.getAttribute('data-action');
            const totalTtc  = parseFloat(btn.getAttribute('data-total')) || 0;
            const dejaLivre = parseInt(btn.getAttribute('data-deja-livre')) || 0;
            ouvrirModalFacturerBL(actionUrl, totalTtc, dejaLivre);
        }
    });
});
</script>
@endsection
