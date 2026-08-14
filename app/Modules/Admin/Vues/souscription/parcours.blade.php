@extends('admin::gabarits.application')

@section('titre', 'Configurer mon entreprise')
@section('topbar_titre', 'Configurer mon entreprise')

@section('styles')
<style>
    .sous-fil { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:24px; }
    .sous-fil .pas { display:flex; align-items:center; gap:8px; padding:8px 14px; border-radius:8px;
                     font-size:12.5px; font-weight:600; background:#f1f5f9; color:var(--text-3); }
    .sous-fil .pas.faite { background:rgba(5,150,105,.08); color:#059669; }
    .sous-fil .pas.ici   { background:var(--primary); color:#fff; }
    .sous-fil .pas .num  { width:20px; height:20px; border-radius:50%; background:rgba(0,0,0,.08);
                           display:inline-flex; align-items:center; justify-content:center; font-size:11px; }
    .sous-fil .pas.ici .num { background:rgba(255,255,255,.2); }

    .sous-tete { margin-bottom:20px; }
    .sous-tete h2 { font-size:21px; font-weight:800; margin:0 0 6px; }
    .sous-tete p  { font-size:14px; color:var(--text-2); margin:0; max-width:58ch; line-height:1.55; }

    .tuiles { display:grid; grid-template-columns:repeat(auto-fill, minmax(210px,1fr)); gap:14px; margin-bottom:24px; }

    /* Le référentiel manquant : le dire, plutôt que laisser la page muette. */
    .referentiel-absent {
        display:flex; gap:14px; align-items:flex-start;
        padding:18px 20px; margin-bottom:24px;
        background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b;
        border-radius:10px; color:#78350f;
    }
    .referentiel-absent i { font-size:18px; color:#b45309; margin-top:2px; }
    .referentiel-absent strong { display:block; margin-bottom:6px; font-size:14px; }
    .referentiel-absent p { margin:0; font-size:13px; line-height:1.6; }
    .referentiel-absent code {
        font-family:ui-monospace, Menlo, monospace; font-size:12.5px;
        background:#fff; border:1px solid #fde68a; border-radius:5px; padding:2px 6px;
    }
    .tuile { position:relative; background:#fff; border:2px solid var(--border); border-radius:12px;
             padding:18px; cursor:pointer; transition:border-color .12s, box-shadow .12s; }
    .tuile:hover { border-color:#cbd5e1; }
    .tuile input { position:absolute; opacity:0; pointer-events:none; }
    .tuile input:checked ~ .corps { color:var(--primary); }
    .tuile:has(input:checked) { border-color:var(--primary); box-shadow:0 0 0 3px rgba(0,43,92,.06); }
    .tuile .titre { font-weight:700; font-size:14.5px; margin-bottom:4px; }
    .tuile .detail { font-size:12px; color:var(--text-3); line-height:1.5; }

    .liste { background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:22px; }
    .ligne { display:flex; align-items:flex-start; gap:12px; padding:13px 18px; border-bottom:1px solid var(--border); }
    .ligne:last-child { border-bottom:none; }
    .ligne:hover { background:#fafbfc; }
    .ligne input[type=checkbox] { margin-top:3px; width:16px; height:16px; flex-shrink:0; }
    .ligne .nom { font-weight:600; font-size:14px; }
    .ligne .sous { font-size:12px; color:var(--text-3); margin-top:2px; }
    .ligne .droite { margin-left:auto; font-size:12px; color:var(--text-3); white-space:nowrap; }

    .autre { background:#fffbeb; border:1px solid #fcd34d; border-radius:10px; padding:16px 18px; margin-bottom:22px; }
    .autre label { font-weight:700; font-size:13px; color:#92400e; display:block; margin-bottom:6px; }
    .autre p { font-size:12.5px; color:#b45309; margin:0 0 10px; line-height:1.5; }

    .tableau-prix { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--border);
                    border-radius:12px; overflow:hidden; }
    .tableau-prix th { text-align:left; padding:10px 14px; font-size:10.5px; text-transform:uppercase;
                       letter-spacing:.5px; color:var(--text-3); background:#f8fafc; border-bottom:1px solid var(--border); }
    .tableau-prix td { padding:8px 14px; border-bottom:1px solid var(--border); font-size:13px; }
    .tableau-prix tr:last-child td { border-bottom:none; }
    .tableau-prix input { width:100%; padding:6px 9px; border:1px solid var(--border); border-radius:6px; font-size:13px; }
    .tableau-prix input[type=number] { text-align:right; font-variant-numeric:tabular-nums; }
    .cadre-defilant { overflow-x:auto; margin-bottom:22px; }

    .pieds { display:flex; gap:10px; align-items:center; padding-top:6px; }
    .cpt { font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace; font-size:11.5px; color:var(--primary); }
    .vide { color:var(--text-3); }
</style>
@endsection

@section('contenu')

{{-- ── Le fil du parcours ── --}}
@php
    $titres = [1 => 'Domaine', 2 => 'Métier', 3 => 'Modules', 4 => 'Rayons', 5 => 'Prix'];
@endphp
<div class="sous-fil">
    @foreach($titres as $numero => $titre)
        @php
            $classe = $numero === $etape ? 'ici' : ($numero <= $entreprise->souscription_etape ? 'faite' : '');
        @endphp
        @if($numero <= $entreprise->souscription_etape + 1 && $numero !== $etape)
            <a href="{{ route('admin.souscription.index', ['etape' => $numero]) }}"
               class="pas {{ $classe }}" style="text-decoration:none;">
                <span class="num">{{ $numero }}</span> {{ $titre }}
            </a>
        @else
            <span class="pas {{ $classe }}"><span class="num">{{ $numero }}</span> {{ $titre }}</span>
        @endif
    @endforeach
</div>

@if(session('succes'))
    <div class="alert alert-success" style="margin-bottom:18px;">{{ session('succes') }}</div>
@endif
@if(session('erreur') || $errors->any())
    <div class="alert alert-danger" style="margin-bottom:18px;">
        {{ session('erreur') ?? $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ route('admin.souscription.enregistrer', $etape) }}">
    @csrf

    {{-- ══ 1. Le domaine ══ --}}
    @if($etape === 1)
        <div class="sous-tete">
            <h2>Dans quel domaine travaillez-vous ?</h2>
            <p>Ce choix ne fait que restreindre la liste suivante. Vous pourrez revenir
               ici et en changer tant que la configuration n'est pas terminée.</p>
        </div>

        {{-- Le référentiel absent laissait cette page muette : la question, le
             bouton, et rien entre les deux. L'utilisateur n'avait aucun moyen
             de savoir que le catalogue des domaines n'était pas chargé, ni que
             continuer était impossible — le formulaire exige un domaine que
             rien ne proposait. --}}
        @if($categories->isEmpty())
            <div class="referentiel-absent">
                <i class="fas fa-triangle-exclamation"></i>
                <div>
                    <strong>Le catalogue des domaines d'activité n'est pas chargé.</strong>
                    <p>La configuration ne peut pas commencer sans lui. Demandez à votre
                       administrateur de charger le référentiel de préparamétrage
                       (<code>php artisan db:seed --class=ReferentielSeeder</code>),
                       puis revenez sur cette page.</p>
                </div>
            </div>
        @else
        <div class="tuiles">
            @foreach($categories as $categorie)
            <label class="tuile">
                <input type="radio" name="categorie_id" value="{{ $categorie->id }}"
                       {{ ($choix['categorie_id'] ?? null) == $categorie->id ? 'checked' : '' }}>
                <div class="corps">
                    <div class="titre">{{ $categorie->nom }}</div>
                    <div class="detail">{{ $categorie->profils_count }} métier{{ $categorie->profils_count > 1 ? 's' : '' }}</div>
                </div>
            </label>
            @endforeach
        </div>
        @endif

    {{-- ══ 2. Le métier ══ --}}
    @elseif($etape === 2)
        <div class="sous-tete">
            <h2>Quel est votre métier ?</h2>
            <p>Plusieurs réponses sont possibles : une quincaillerie qui livre des chantiers
               coche les deux. Chaque métier apporte ses rayons, ses articles et ses comptes.</p>
        </div>

        <div class="liste">
            @foreach($profils as $profil)
            <label class="ligne">
                <input type="checkbox" name="profils[]" value="{{ $profil->code }}"
                       {{ in_array($profil->code, $choix['profils'] ?? [], true) ? 'checked' : '' }}>
                <div>
                    <div class="nom">{{ $profil->nom }}</div>
                    @if($profil->description)
                        <div class="sous">{{ $profil->description }}</div>
                    @endif
                </div>
                <div class="droite">
                    {{ $profil->familles_count }} rayons · {{ $profil->articles_count }} articles
                </div>
            </label>
            @endforeach
        </div>

        <div class="autre">
            <label for="activite_autre">Aucun ne correspond ?</label>
            <p>Décrivez votre activité en quelques mots. Nous la prendrons en compte pour
               enrichir la liste ; en attendant, vous partirez d'un catalogue vide que vous
               remplirez à votre main.</p>
            <input type="text" id="activite_autre" name="activite_autre" class="form-control"
                   value="{{ old('activite_autre', $entreprise->activite_autre) }}"
                   placeholder="Ex. : atelier de reliure et dorure" maxlength="150">
        </div>

    {{-- ══ 3. Les modules ══ --}}
    @elseif($etape === 3)
        <div class="sous-tete">
            <h2>Ce que votre métier demande</h2>
            <p>Voici les modules que vos choix ouvrent. Décochez ce dont vous n'avez pas
               l'usage — vous pourrez les rouvrir à tout moment depuis vos paramètres.</p>
        </div>

        <div class="liste">
            @foreach($modulesProposes as $module)
            <label class="ligne">
                <input type="checkbox" name="modules[]" value="{{ $module }}"
                       {{ in_array($module, $choix['modules'] ?? $modulesProposes, true) ? 'checked' : '' }}
                       {{ $module === 'principal' ? 'disabled' : '' }}>
                <div>
                    <div class="nom">{{ ucfirst(str_replace('_', ' ', $module)) }}</div>
                    @if($module === 'principal')
                        <div class="sous">Le socle de l'application — il reste toujours ouvert.</div>
                    @endif
                </div>
            </label>
            @if($module === 'principal')
                <input type="hidden" name="modules[]" value="principal">
            @endif
            @endforeach
        </div>

    {{-- ══ 4. Les rayons ══ --}}
    @elseif($etape === 4)
        <div class="sous-tete">
            <h2>Vos rayons</h2>
            <p>Tout est coché : décochez ce que vous ne vendez pas. Chaque rayon apporte ses
               articles et ses comptes comptables. Rien n'est définitif — vous pourrez en
               ajouter et en archiver ensuite.</p>
        </div>

        <div class="liste">
            @foreach($familles as $famille)
            <label class="ligne">
                <input type="checkbox" name="familles[]" value="{{ $famille->code }}" checked>
                <div>
                    <div class="nom">{{ $famille->nom }}</div>
                    <div class="sous">
                        {{ $famille->profil->nom }} ·
                        <span class="cpt">{{ $famille->compte_vente ?? $famille->compte_achat ?? '—' }}</span>
                        {{ $famille->intituleCompte('compte_vente') ?? $famille->typeArticle->libelle }}
                    </div>
                </div>
                <div class="droite">{{ $famille->articles_count }} articles</div>
            </label>
            @endforeach
        </div>

    {{-- ══ 5. Prix et stock ══ --}}
    @elseif($etape === 5)
        @php
            // La colonne de stock n'a de sens que si le module est ouvert, qu'un
            // site existe pour l'accueillir, et qu'au moins un article se compte :
            // pour un cabinet comptable, dont tous les articles sont des
            // missions, elle serait une colonne de tirets.
            $avecStock = $siteDuStock && $articles->contains(fn ($a) => $a->estStockable());
            $colonnes  = $avecStock ? 6 : 5;
        @endphp

        <div class="sous-tete">
            <h2>Vos prix{{ $avecStock ? ' et votre stock de départ' : '' }}</h2>
            <p>Le catalogue livré ne porte aucun prix : ils varient selon la zone et la
               période. Renommez ce qui doit l'être et saisissez vos montants — vous pouvez
               aussi passer et les compléter article par article plus tard.
               @if($avecStock)
                   La dernière colonne reçoit les quantités comptées aujourd'hui
                   à « {{ $siteDuStock->nom }} » : c'est votre inventaire d'ouverture.
               @endif
            </p>
        </div>

        <div class="cadre-defilant">
            <table class="tableau-prix">
                <thead>
                    <tr><th>Référence</th><th>Article</th><th>Rayon</th>
                        <th style="width:130px;">Prix d'achat</th><th style="width:130px;">Prix de vente</th>
                        @if($avecStock)<th style="width:130px;">Stock de départ</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($articles as $i => $article)
                    <tr>
                        <td><span class="cpt">{{ $article->reference }}</span></td>
                        <td>
                            <input type="hidden" name="articles[{{ $i }}][id]" value="{{ $article->id }}">
                            <input type="text" name="articles[{{ $i }}][nom]" value="{{ $article->nom }}" maxlength="255">
                        </td>
                        <td>{{ $article->categorieRelation->nom ?? '—' }}</td>
                        <td><input type="number" name="articles[{{ $i }}][prix_achat]" min="0" step="1"
                                   value="{{ $article->prix_achat > 0 ? (int) $article->prix_achat : '' }}"></td>
                        <td><input type="number" name="articles[{{ $i }}][prix_vente]" min="0" step="1"
                                   value="{{ $article->prix_vente > 0 ? (int) $article->prix_vente : '' }}"></td>
                        @if($avecStock)
                        <td>
                            @if($article->estStockable())
                                <input type="number" name="articles[{{ $i }}][stock_initial]" min="0" step="1"
                                       placeholder="0" value="{{ $article->stockSur($siteDuStock->id) ?: '' }}">
                            @else
                                {{-- Une prestation ne s'épuise pas : rien à compter. --}}
                                <span class="vide" title="Cet article ne se stocke pas">—</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $colonnes }}" style="text-align:center; padding:26px; color:var(--text-3);">
                        Votre catalogue est vide — vous le remplirez depuis l'écran des produits.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="pieds">
        {{-- Un bouton qui ne peut mener nulle part vaut mieux caché : sans
             domaine à choisir, le formulaire revient sur lui-même. --}}
        @unless($etape === 1 && $categories->isEmpty())
        <button type="submit" class="btn btn-primary">
            @if($etape === $derniere)
                <i class="fas fa-check"></i> Terminer
            @else
                Continuer <i class="fas fa-arrow-right"></i>
            @endif
        </button>
        @endunless

        @if($etape > 1)
            <a href="{{ route('admin.souscription.index', ['etape' => $etape - 1]) }}" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        @endif

        <span class="vide" style="margin-left:auto; font-size:12.5px;">
            Étape {{ $etape }} sur {{ $derniere }} — vous pouvez quitter et reprendre.
        </span>
    </div>
</form>

@endsection
