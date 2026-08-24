@extends('admin::gabarits.application')
@section('titre', 'Pièces refusées par la DGI')
@section('topbar_titre', 'Fiscalité & DGI — Pièces refusées')

@section('styles')
<style>
    .kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:26px; }
    .kpi-card {
        background:#fff; border:1px solid var(--border); border-radius:14px;
        padding:20px 24px; position:relative; overflow:hidden;
    }
    .kpi-card::before {
        content:''; position:absolute; top:0; left:0; width:4px; height:100%;
        background:var(--primary);
    }
    .kpi-card.alerte::before { background:#E53E3E; }
    .kpi-card.ok::before     { background:#10b981; }
    .kpi-card .lbl { font-size:11px; text-transform:uppercase; font-weight:700; color:var(--text-3); letter-spacing:.4px; margin-bottom:8px; }
    .kpi-card .val { font-size:32px; font-weight:800; color:var(--text-1); }
    .kpi-card .sub { font-size:12px; color:var(--text-3); margin-top:6px; }

    .fne-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:24px; }
    .fne-tab  { padding:10px 20px; font-size:13px; font-weight:600; color:var(--text-3); cursor:pointer; text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s; }
    .fne-tab:hover  { color:var(--primary); }
    .fne-tab.active { color:var(--primary); border-bottom-color:var(--primary); }

    .rejet { border:1px solid var(--border); border-radius:14px; background:#fff; margin-bottom:16px; overflow:hidden; }
    .rejet-tete {
        display:flex; align-items:center; gap:14px; flex-wrap:wrap;
        padding:16px 20px; border-bottom:1px solid var(--border); background:#fbfcfe;
    }
    .rejet-num { font-family:monospace; font-weight:800; font-size:15px; color:var(--text-1); }
    .rejet-corps { padding:18px 20px; }

    .etiquette { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; white-space:nowrap; }
    .et-ouvert       { background:#fff5f5; color:#c53030; }
    .et-diagnostique { background:#fffbeb; color:#92400e; }
    .et-resolu       { background:#ecfdf5; color:#065f46; }

    .champ { border-left:3px solid var(--border); padding:2px 0 2px 14px; margin-bottom:14px; }
    .champ.ecart        { border-left-color:#E53E3E; }
    .champ.concordant   { border-left-color:#10b981; }
    .champ.hors_portee  { border-left-color:#cbd5e1; }
    .champ.a_verifier, .champ.sans_releve { border-left-color:#f59e0b; }
    .champ-nom { font-family:monospace; font-weight:700; font-size:12px; color:var(--text-1); }
    .champ-txt { font-size:13px; color:var(--text-2); margin-top:4px; line-height:1.55; }

    .valeurs { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:8px; font-size:12px; }
    .valeur  { padding:4px 10px; border-radius:8px; font-family:monospace; font-weight:700; }
    .v-envoye  { background:#fff5f5; color:#c53030; }
    .v-portail { background:#eff6ff; color:#1d4ed8; }

    .msg-dgi {
        background:#f8fafc; border:1px solid var(--border); border-radius:10px;
        padding:12px 14px; font-size:12px; color:var(--text-2); line-height:1.55;
        margin-bottom:16px; word-break:break-word;
    }
    .actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:4px; }
    .actions form { display:inline; }

    .ecarts-fiche { border:1px solid #fde68a; background:#fffbeb; border-radius:14px; padding:18px 20px; margin-top:28px; }
    .ecarts-table { width:100%; border-collapse:collapse; margin-top:12px; }
    .ecarts-table th { text-align:left; padding:8px 12px; font-size:11px; text-transform:uppercase; color:#92400e; border-bottom:1px solid #fde68a; }
    .ecarts-table td { padding:9px 12px; border-bottom:1px solid #fef3c7; font-size:13px; }
    .ecarts-table tr:last-child td { border-bottom:none; }

    @media (max-width:860px) { .kpi-grid { grid-template-columns:1fr; } }
</style>
@endsection

@section('contenu')

@if(session('succes'))
<div style="display:flex;align-items:center;gap:10px;padding:14px 18px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;margin-bottom:20px;color:#065f46;font-weight:500;">
    <i class="fas fa-check-circle" style="font-size:16px;color:#10b981;"></i> {{ session('succes') }}
</div>
@endif

@if(session('erreur'))
<div style="display:flex;align-items:center;gap:10px;padding:14px 18px;background:#fff5f5;border:1px solid #fed7d7;border-radius:10px;margin-bottom:20px;color:#c53030;font-weight:500;">
    <i class="fas fa-exclamation-triangle" style="font-size:16px;"></i> {{ session('erreur') }}
</div>
@endif

{{-- Onglets FNE --}}
<div class="fne-tabs">
    <a href="{{ route('admin.fne.gestion') }}"   class="fne-tab"><i class="fas fa-chart-bar"></i> Gestion</a>
    <a href="{{ route('admin.fne.situation') }}" class="fne-tab"><i class="fas fa-balance-scale"></i> Situation</a>
    <a href="{{ route('admin.fne.factures') }}"  class="fne-tab"><i class="fas fa-file-invoice"></i> Factures</a>
    <a href="{{ route('admin.fne.stickers') }}"  class="fne-tab"><i class="fas fa-stamp"></i> Stickers</a>
    <a href="{{ route('admin.fne.rejets') }}"    class="fne-tab active"><i class="fas fa-triangle-exclamation"></i> Rejets
        @if($kpis['ouverts'] > 0)
        <span style="background:#E53E3E;color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;margin-left:4px;">{{ $kpis['ouverts'] }}</span>
        @endif
    </a>
</div>

{{-- KPIs --}}
<div class="kpi-grid">
    <div class="kpi-card {{ $kpis['ouverts'] > 0 ? 'alerte' : 'ok' }}">
        <div class="lbl"><i class="fas fa-circle-exclamation"></i> À traiter</div>
        <div class="val" style="{{ $kpis['ouverts'] > 0 ? 'color:#E53E3E;' : '' }}">{{ $kpis['ouverts'] }}</div>
        <div class="sub">pièce(s) refusée(s), pas encore rapprochée(s)</div>
    </div>
    <div class="kpi-card">
        <div class="lbl"><i class="fas fa-magnifying-glass"></i> Rapprochées</div>
        <div class="val">{{ $kpis['diagnostiques'] }}</div>
        <div class="sub">comparées au relevé du portail</div>
    </div>
    <div class="kpi-card ok">
        <div class="lbl"><i class="fas fa-check"></i> Classées</div>
        <div class="val">{{ $kpis['resolus'] }}</div>
        <div class="sub">la pièce est passée depuis</div>
    </div>
</div>

{{-- État du relevé --}}
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:12px;color:var(--text-3);margin-bottom:20px;">
    <i class="fas fa-cloud-arrow-down"></i>
    @if($fiche)
        Dernier relevé du portail : <strong style="color:var(--text-1);">{{ $fiche->date_scraping?->format('d/m/Y') ?? '—' }}</strong>
    @else
        <span style="color:#92400e;">Aucun relevé du portail pour cette entreprise — le rapprochement ne peut rien comparer.</span>
    @endif
    @if($demandes->isNotEmpty())
        <span style="padding:2px 9px;border-radius:20px;background:#fffbeb;color:#92400e;font-weight:700;">
            {{ $demandes->count() }} relèvement(s) en attente
        </span>
    @endif
</div>

{{-- Une demande ouverte est un signal voulu : c'est ainsi qu'on voit qu'un
     scraper ne répond plus. Encore faut-il que quelqu'un le voie — sans l'âge,
     une demande de mars ressemble à une demande de ce matin. --}}
@php $enRetard = $demandes->filter(fn ($d) => $d->estEnRetard()); @endphp
@if($enRetard->isNotEmpty())
<div style="display:flex;gap:12px;padding:14px 18px;background:#fff5f5;border:1px solid #fed7d7;border-radius:10px;margin-bottom:20px;color:#c53030;">
    <i class="fas fa-plug-circle-exclamation" style="font-size:18px;margin-top:2px;"></i>
    <div style="line-height:1.6;">
        <strong>{{ $enRetard->count() }} relevé(s) demandé(s) sans réponse.</strong>
        @foreach($enRetard as $demande)
            <div style="font-size:12px;">
                <span style="font-family:monospace;font-weight:700;">{{ $demande->login }}</span>
                — attend depuis <strong>{{ $demande->attenteLisible() }}</strong>
                @if($demande->motif) <span style="opacity:.75;">({{ $demande->motif }})</span> @endif
            </div>
        @endforeach
        <div style="font-size:12px;margin-top:6px;">
            Trois causes possibles, et aucune ne se corrige toute seule : le relevé du
            portail n'est pas lancé, il dépose ses fichiers ailleurs que dans le
            dossier d'import, ou le NCC de l'entreprise ne correspond pas au login du
            portail.
        </div>
    </div>
</div>
@endif

{{-- Liste des rejets --}}
@forelse($rejets as $rejet)
<div class="rejet">
    <div class="rejet-tete">
        <span class="rejet-num">{{ $rejet->numero_piece ?? '#'.$rejet->piece_id }}</span>
        <span class="etiquette et-{{ $rejet->statut }}">
            {{ ['ouvert' => 'À traiter', 'diagnostique' => 'Rapprochée', 'resolu' => 'Classée'][$rejet->statut] ?? $rejet->statut }}
        </span>
        <span style="font-size:12px;color:var(--text-3);">
            {{ $rejet->piece_type === 'achat' ? 'Bordereau d\'achat' : 'Facture' }}
            · refusée le {{ $rejet->created_at?->format('d/m/Y à H:i') }}
        </span>
        <div style="margin-left:auto;" class="actions">
            <form method="POST" action="{{ route('admin.fne.rejets.diagnostiquer', $rejet) }}">
                @csrf
                <button type="submit" class="btn" style="font-size:12px;">
                    <i class="fas fa-rotate"></i> Rapprocher
                </button>
            </form>
            @if($rejet->statut !== 'resolu')
            <form method="POST" action="{{ route('admin.fne.rejets.resoudre', $rejet) }}">
                @csrf
                <button type="submit" class="btn" style="font-size:12px;">
                    <i class="fas fa-check"></i> Classer
                </button>
            </form>
            @endif
        </div>
    </div>

    <div class="rejet-corps">
        {{-- Le message de la plateforme, mot pour mot --}}
        <div class="msg-dgi">
            <strong style="color:var(--text-1);">Réponse de la plateforme :</strong><br>
            {{ $rejet->message ?? 'Aucun message conservé.' }}
        </div>

        @if($rejet->diagnostic)
            @foreach($rejet->diagnostic['champs'] ?? [] as $champ)
            <div class="champ {{ $champ['verdict'] ?? '' }}">
                <div class="champ-nom">{{ $champ['champ'] }}</div>
                <div class="champ-txt">{{ $champ['explication'] ?? '' }}</div>

                @if(!empty($champ['envoye']) || !empty($champ['portail']))
                <div class="valeurs">
                    @if(!empty($champ['envoye']))
                        <span style="color:var(--text-3);">envoyé</span>
                        <span class="valeur v-envoye">{{ $champ['envoye'] }}</span>
                    @endif
                    @foreach($champ['portail'] ?? [] as $declare)
                        <span style="color:var(--text-3);">portail</span>
                        <span class="valeur v-portail">{{ $declare }}</span>
                    @endforeach
                </div>
                @endif

                {{-- La correction ne s'offre que sur un écart de nom, et
                     seulement quand le portail n'en déclare qu'un : la machine
                     ne choisit pas le point de vente à la place de qui a
                     établi la pièce. --}}
                @if(($champ['champ'] ?? null) === 'pointOfSale'
                    && ($champ['verdict'] ?? null) === 'ecart'
                    && count($champ['portail'] ?? []) === 1)
                <div class="actions" style="margin-top:10px;">
                    <form method="POST" action="{{ route('admin.fne.rejets.appliquer', $rejet) }}"
                          onsubmit="return confirm('Renommer le point de vente « {{ $champ['envoye'] }} » en « {{ $champ['portail'][0] }} » ? Toutes les pièces de ce point de vente porteront ce nom.');">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="font-size:12px;">
                            <i class="fas fa-wand-magic-sparkles"></i>
                            Renommer le point de vente en « {{ $champ['portail'][0] }} »
                        </button>
                    </form>
                </div>
                @endif
            </div>
            @endforeach

            <div style="font-size:12px;color:var(--text-3);border-top:1px dashed var(--border);padding-top:12px;margin-top:4px;">
                <i class="fas fa-circle-info"></i>
                {{ $rejet->diagnostic['conclusion'] ?? '' }}
                @if(!empty($rejet->diagnostic['releve']['date']))
                    <span style="margin-left:4px;">(relevé du {{ $rejet->diagnostic['releve']['date'] }})</span>
                @endif
            </div>
        @else
            <div style="font-size:13px;color:var(--text-3);">
                <i class="fas fa-hourglass-half"></i>
                Pas encore rapprochée d'un relevé du portail. Le rapprochement passe chaque heure ;
                <em>Rapprocher</em> le déclenche tout de suite.
            </div>
        @endif
    </div>
</div>
@empty
<div class="card" style="text-align:center;padding:56px;color:var(--text-3);">
    <i class="fas fa-circle-check" style="font-size:44px;display:block;margin-bottom:14px;opacity:.2;"></i>
    Aucune pièce refusée par la plateforme.<br>
    <small>Les refus apparaissent ici dès qu'ils surviennent, avec ce que le portail déclare en face.</small>
</div>
@endforelse

@if($rejets->hasPages())
<div style="padding:16px 0;">{{ $rejets->links() }}</div>
@endif

{{-- Écarts de fiche : montrés, jamais appliqués --}}
@if($ecartsFiche)
<div class="ecarts-fiche">
    <div style="font-size:14px;font-weight:700;color:#92400e;">
        <i class="fas fa-scale-unbalanced"></i>
        Le portail et votre paramétrage divergent sur {{ count($ecartsFiche) }} point(s)
    </div>
    <p style="font-size:12px;color:#92400e;margin:8px 0 0;line-height:1.6;">
        Ces écarts ne sont la cause d'aucun refus, mais ils méritent d'être tranchés.
        <strong>Selflow ne les applique pas tout seul :</strong> le timbre de quittance,
        le bordereau d'achat agricole et le solde d'alerte commandent ce qui part à la DGI,
        et un relevé ne dit pas qui, du portail ou de votre paramétrage, a raison.
        La correction se fait dans
        <a href="{{ route('admin.entreprise.parametres') }}" style="color:#92400e;text-decoration:underline;">les paramètres de l'entreprise</a>,
        en connaissance de cause.
    </p>

    <table class="ecarts-table">
        <thead>
            <tr><th>Champ</th><th>Au portail</th><th>Dans Selflow</th></tr>
        </thead>
        <tbody>
            @foreach($ecartsFiche as $nom => $ecart)
            <tr>
                <td style="font-family:monospace;font-weight:700;">{{ $nom }}</td>
                <td style="font-weight:700;color:#1d4ed8;">
                    {{ is_bool($ecart['portail']) ? ($ecart['portail'] ? 'oui' : 'non') : $ecart['portail'] }}
                </td>
                <td style="color:var(--text-2);">
                    @if($ecart['selflow'] === null || $ecart['selflow'] === '')
                        <em style="color:var(--text-3);">vide</em>
                    @else
                        {{ is_bool($ecart['selflow']) ? ($ecart['selflow'] ? 'oui' : 'non') : $ecart['selflow'] }}
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection
