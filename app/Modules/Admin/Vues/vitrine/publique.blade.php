{{--
    La vitrine, telle qu'un visiteur la voit.

    Le contenu vient de la base, saisi par le superadmin : cette page n'écrit
    aucun texte de présentation. Elle sait dessiner cinq dispositions, et
    s'arrête là.

    Une vitrine vide affiche une page d'attente honnête plutôt que du faux
    texte — celui-ci finirait en production le jour où quelqu'un oublierait de
    le remplacer.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selflow — DC-Knowing</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:   #002B5C;
            --primary-d: #001F42;
            --bg:        #F4F6F9;
            --surface:   #ffffff;
            --border:    #E2E8F0;
            --text:      #1E293B;
            --text-2:    #475569;
            --text-3:    #94a3b8;
            --accent:    #f59e0b;
            --radius:    12px;
            --shadow:    0 10px 30px rgba(0, 0, 0, 0.05);
        }

        html, body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 15px;
            line-height: 1.6;
            scroll-behavior: smooth;
        }

        /* ── La barre du haut ── */
        .entete {
            position: sticky; top: 0; z-index: 10;
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
        }
        .entete .dedans {
            max-width: 1120px; margin: 0 auto; padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
        }
        .marque { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .marque .pastille {
            width: 34px; height: 34px; border-radius: 9px;
            background: var(--primary); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 16px;
        }
        .marque .nom { font-weight: 800; color: var(--primary); font-size: 17px; letter-spacing: -.01em; }
        .marque .sous { font-size: 11px; color: var(--text-3); }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; border-radius: 9px;
            border: 1px solid var(--border); background: var(--surface); color: var(--text-2);
            font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer;
            font-family: inherit;
        }
        .btn:hover { background: var(--bg); color: var(--primary); }
        .btn-principal { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn-principal:hover { background: var(--primary-d); color: #fff; }

        /* ── Les sections ── */
        .section { padding: 62px 24px; }
        .section:nth-child(even) { background: var(--surface); }
        .dedans { max-width: 1120px; margin: 0 auto; }

        .section-tete { max-width: 720px; margin-bottom: 34px; }
        .section-tete h2 {
            font-size: 30px; font-weight: 800; color: var(--primary);
            letter-spacing: -.02em; line-height: 1.25;
        }
        .section-tete .sous { font-size: 16px; color: var(--text-2); margin-top: 8px; }
        .section-tete .texte { font-size: 15px; color: var(--text-2); margin-top: 14px; white-space: pre-line; }

        /* ── Bandeau d'ouverture ── */
        .bandeau {
            background:
                radial-gradient(circle at 15% 20%, rgba(0,43,92,.10) 0%, transparent 46%),
                radial-gradient(circle at 85% 80%, rgba(245,158,11,.10) 0%, transparent 46%),
                var(--bg);
            text-align: center;
            padding: 84px 24px;
        }
        .bandeau h1 {
            font-size: 44px; font-weight: 800; color: var(--primary);
            letter-spacing: -.03em; line-height: 1.15; max-width: 860px; margin: 0 auto;
        }
        .bandeau .sous { font-size: 18px; color: var(--text-2); margin: 16px auto 0; max-width: 660px; }
        .bandeau .texte { font-size: 15px; color: var(--text-2); margin: 14px auto 0; max-width: 660px; white-space: pre-line; }
        .bandeau .actions { margin-top: 30px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

        /* ── Grilles ── */
        .colonnes { display: grid; grid-template-columns: repeat(auto-fit, minmax(268px, 1fr)); gap: 20px; }
        .liste { display: flex; flex-direction: column; gap: 14px; }
        .tarifs { display: grid; grid-template-columns: repeat(auto-fit, minmax(272px, 1fr)); gap: 20px; }

        .carte {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 26px; box-shadow: var(--shadow);
        }
        .section:nth-child(even) .carte { background: var(--bg); }

        .carte .icone {
            width: 42px; height: 42px; border-radius: 10px;
            background: #EBF2FC; color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; margin-bottom: 14px;
        }
        .carte img.illustration {
            width: 100%; max-height: 180px; object-fit: cover;
            border-radius: 9px; margin-bottom: 14px;
        }
        .carte h3 { font-size: 17px; font-weight: 700; color: var(--text); margin-bottom: 7px; }
        .carte p { font-size: 14px; color: var(--text-2); white-space: pre-line; }
        .carte .valeur {
            font-size: 30px; font-weight: 800; color: var(--primary);
            letter-spacing: -.02em; margin: 6px 0 2px;
        }
        .carte .mention { font-size: 12.5px; color: var(--text-3); }
        .carte .lien { margin-top: 16px; display: inline-flex; }

        .liste .carte { display: flex; gap: 18px; align-items: flex-start; padding: 20px 24px; }
        .liste .carte .icone { flex: none; margin-bottom: 0; }

        /* ── Le pied ── */
        .pied {
            background: var(--primary); color: #cbd5e1;
            padding: 34px 24px; text-align: center; font-size: 13.5px;
        }
        .pied a { color: #fff; text-decoration: none; font-weight: 600; }

        /* ── La vitrine vide ── */
        .attente {
            max-width: 620px; margin: 90px auto; padding: 40px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow); text-align: center;
        }
        .attente i { font-size: 30px; color: var(--text-3); margin-bottom: 16px; }
        .attente h1 { font-size: 22px; font-weight: 800; color: var(--primary); margin-bottom: 10px; }
        .attente p { font-size: 14.5px; color: var(--text-2); }

        @media (max-width: 700px) {
            .bandeau h1 { font-size: 32px; }
            .section-tete h2 { font-size: 25px; }
            .section, .bandeau { padding-left: 18px; padding-right: 18px; }
            .liste .carte { flex-direction: column; gap: 12px; }
        }
    </style>
</head>
<body>

<header class="entete">
    <div class="dedans">
        <a href="{{ route('vitrine') }}" class="marque">
            <div class="pastille">S</div>
            <div>
                <div class="nom">Selflow</div>
                <div class="sous">DC-Knowing</div>
            </div>
        </a>
        <a href="{{ route('connexion') }}" class="btn btn-principal">
            <i class="fas fa-arrow-right-to-bracket"></i> Se connecter
        </a>
    </div>
</header>

@forelse($sections as $section)
    @php $gabarit = $section->gabaritSur(); @endphp

    @if($gabarit === 'bandeau')
        <section class="bandeau" id="{{ $section->cle }}">
            <h1>{{ $section->titre }}</h1>
            @if($section->sous_titre)<p class="sous">{{ $section->sous_titre }}</p>@endif
            @if($section->texte)<p class="texte">{{ $section->texte }}</p>@endif

            @if($section->cartesPubliees->isNotEmpty())
                <div class="actions">
                    @foreach($section->cartesPubliees as $carte)
                        @if($carte->lien_url)
                            <a href="{{ $carte->lien_url }}" class="btn {{ $loop->first ? 'btn-principal' : '' }}">
                                @if($carte->icone)<i class="{{ $carte->icone }}"></i>@endif
                                {{ $carte->lien_libelle ?: $carte->titre }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <section class="section" id="{{ $section->cle }}">
            <div class="dedans">
                <div class="section-tete">
                    <h2>{{ $section->titre }}</h2>
                    @if($section->sous_titre)<p class="sous">{{ $section->sous_titre }}</p>@endif
                    @if($section->texte)<p class="texte">{{ $section->texte }}</p>@endif
                </div>

                @if($gabarit !== 'texte' && $section->cartesPubliees->isNotEmpty())
                    <div class="{{ $gabarit }}">
                        @foreach($section->cartesPubliees as $carte)
                            <article class="carte">
                                @if($carte->imageUrl())
                                    <img class="illustration" src="{{ $carte->imageUrl() }}" alt="{{ $carte->titre }}">
                                @elseif($carte->icone)
                                    <div class="icone"><i class="{{ $carte->icone }}"></i></div>
                                @endif

                                <div>
                                    <h3>{{ $carte->titre }}</h3>

                                    @if($carte->valeur)
                                        <div class="valeur">{{ $carte->valeur }}</div>
                                    @endif
                                    @if($carte->mention)
                                        <div class="mention">{{ $carte->mention }}</div>
                                    @endif

                                    @if($carte->texte)<p>{{ $carte->texte }}</p>@endif

                                    @if($carte->lien_url)
                                        <a href="{{ $carte->lien_url }}" class="btn lien">
                                            {{ $carte->lien_libelle ?: 'En savoir plus' }}
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif
@empty
    {{-- Aucune section publiée. On le dit, plutôt que d'inventer une page. --}}
    <div class="attente">
        <i class="fas fa-pen-ruler"></i>
        <h1>Cette page est en préparation</h1>
        <p>Le contenu de la présentation n'a pas encore été publié.
           En attendant, vous pouvez vous connecter à votre espace.</p>
    </div>
@endforelse

<footer class="pied">
    Selflow — une solution <a href="{{ route('vitrine') }}">DC-Knowing</a> ·
    {{ now()->year }}
</footer>

</body>
</html>
