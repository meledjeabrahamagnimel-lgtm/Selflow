@extends('admin::gabarits.application')

@section('titre', 'Supervision - Vitrine')
@section('topbar_titre', 'Supervision &mdash; Vitrine publique')

@section('styles')
<style>
    .vitrine-intro {
        display:flex; gap:14px; align-items:flex-start;
        padding:16px 18px; margin-bottom:22px;
        background:#EBF2FC; border:1px solid #bfdbfe; border-left:4px solid var(--primary);
        border-radius:10px;
    }
    .vitrine-intro i { color:var(--primary); font-size:17px; margin-top:2px; }
    .vitrine-intro p { margin:0; font-size:13px; color:var(--text-2); line-height:1.6; }

    .section-bloc {
        background:var(--bg2); border:1px solid var(--border);
        border-radius:12px; margin-bottom:18px; overflow:hidden;
    }
    .section-entete {
        display:flex; align-items:center; gap:14px; flex-wrap:wrap;
        padding:16px 18px; background:var(--bg3); border-bottom:1px solid var(--border);
    }
    .section-entete .titre { font-weight:700; font-size:15px; color:var(--text); }
    .section-entete .cle {
        font-family:ui-monospace, Menlo, monospace; font-size:11.5px;
        background:var(--bg2); border:1px solid var(--border);
        border-radius:5px; padding:2px 7px; color:var(--text-3);
    }
    .section-entete .pousse { margin-left:auto; display:flex; gap:8px; flex-wrap:wrap; }

    .etat {
        font-size:10.5px; font-weight:700; text-transform:uppercase;
        letter-spacing:.04em; border-radius:4px; padding:2px 7px;
    }
    .etat-ligne { background:#dcfce7; color:#15803d; }
    .etat-brouillon { background:#fef3c7; color:#b45309; }

    .section-corps { padding:18px; }

    .cartes-grille {
        display:grid; grid-template-columns:repeat(auto-fill, minmax(250px,1fr)); gap:12px;
    }
    .carte-bloc {
        background:var(--bg); border:1px solid var(--border);
        border-radius:9px; padding:14px;
    }
    .carte-bloc .nom { font-weight:600; font-size:13.5px; color:var(--text); margin-bottom:4px; }
    .carte-bloc .txt { font-size:12px; color:var(--text-3); line-height:1.5; }
    .carte-bloc .pied { margin-top:10px; display:flex; gap:6px; flex-wrap:wrap; }

    .vide {
        padding:26px; text-align:center; color:var(--text-3); font-size:13px;
        border:1px dashed var(--border); border-radius:9px;
    }

    .champ { margin-bottom:12px; }
    .champ label { display:block; font-size:12px; font-weight:600; color:var(--text-2); margin-bottom:4px; }
    .champ input, .champ select, .champ textarea {
        width:100%; padding:9px 11px; border:1px solid var(--border);
        border-radius:7px; font-size:13px; font-family:inherit; background:var(--bg);
        color:var(--text);
    }
    .champ textarea { min-height:70px; resize:vertical; }
    .champ .aide { font-size:11px; color:var(--text-3); margin-top:3px; }

    .duo { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

    details.pliant > summary {
        cursor:pointer; list-style:none; font-size:12.5px; font-weight:600;
        color:var(--text-2); padding:8px 0; user-select:none;
    }
    details.pliant > summary::-webkit-details-marker { display:none; }
    details.pliant > summary:hover { color:var(--primary); }

    @media (max-width: 640px) { .duo { grid-template-columns:1fr; } }
</style>
@endsection

@section('contenu')

<div class="vitrine-intro">
    <i class="fas fa-circle-info"></i>
    <p>
        La page publique se compose ici. Elle est visible à l'adresse
        <a href="{{ route('vitrine') }}" target="_blank" rel="noopener"><strong>/presentation</strong></a>.
        Une section reste hors ligne tant que vous ne l'avez pas publiée : vous pouvez
        donc la préparer tranquillement, puis l'ouvrir d'un clic.
        <strong>Aucun texte n'est pré-rempli</strong> — le contenu d'une vitrine engage
        l'entreprise qu'elle présente.
    </p>
</div>

@if(session('succes'))
    <div style="padding:12px 16px;margin-bottom:18px;background:#dcfce7;border:1px solid #86efac;border-radius:9px;color:#15803d;font-size:13px;">
        <i class="fas fa-check"></i> {{ session('succes') }}
    </div>
@endif

@if($errors->any())
    <div style="padding:12px 16px;margin-bottom:18px;background:#fef2f2;border:1px solid #fecaca;border-radius:9px;color:#b91c1c;font-size:13px;">
        @foreach($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

{{-- ══ Les sections existantes ══ --}}
@forelse($sections as $section)
    <div class="section-bloc">
        <div class="section-entete">
            <span class="titre">{{ $section->titre }}</span>
            <span class="cle">#{{ $section->cle }}</span>
            <span class="etat {{ $section->publiee ? 'etat-ligne' : 'etat-brouillon' }}">
                {{ $section->publiee ? 'En ligne' : 'Brouillon' }}
            </span>
            <span style="font-size:11.5px;color:var(--text-3);">
                {{ $gabarits[$section->gabarit] ?? $section->gabarit }}
            </span>

            <div class="pousse">
                <form method="POST" action="{{ route('superadmin.vitrine.sections.basculer', $section) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm {{ $section->publiee ? 'btn-outline' : 'btn-primary' }}">
                        <i class="fas {{ $section->publiee ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                        {{ $section->publiee ? 'Retirer' : 'Publier' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('superadmin.vitrine.sections.supprimer', $section) }}"
                      onsubmit="return confirm('Supprimer la section « {{ $section->titre }} » et toutes ses cartes ?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline" style="color:#b91c1c;">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="section-corps">

            {{-- Les cartes de la section --}}
            @if($section->cartes->isEmpty())
                <div class="vide">Aucune carte dans cette section.</div>
            @else
                <div class="cartes-grille">
                    @foreach($section->cartes as $carte)
                        <div class="carte-bloc">
                            <div class="nom">
                                @if($carte->icone)<i class="{{ $carte->icone }}"></i>@endif
                                {{ $carte->titre }}
                                @unless($carte->publiee)
                                    <span class="etat etat-brouillon" style="margin-left:4px;">Masquée</span>
                                @endunless
                            </div>
                            @if($carte->valeur)
                                <div style="font-weight:700;color:var(--primary);font-size:15px;">{{ $carte->valeur }}</div>
                            @endif
                            @if($carte->texte)
                                <div class="txt">{{ \Illuminate\Support\Str::limit($carte->texte, 110) }}</div>
                            @endif

                            <div class="pied">
                                <form method="POST" action="{{ route('superadmin.vitrine.cartes.basculer', $carte) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline">
                                        <i class="fas {{ $carte->publiee ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.vitrine.cartes.supprimer', $carte) }}"
                                      onsubmit="return confirm('Supprimer cette carte ?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline" style="color:#b91c1c;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Ajouter une carte --}}
            <details class="pliant" style="margin-top:14px;">
                <summary><i class="fas fa-plus"></i> Ajouter une carte à cette section</summary>

                <form method="POST" action="{{ route('superadmin.vitrine.cartes.creer', $section) }}"
                      enctype="multipart/form-data" style="margin-top:10px;">
                    @csrf
                    <div class="duo">
                        <div class="champ">
                            <label>Titre *</label>
                            <input type="text" name="titre" required maxlength="255">
                        </div>
                        <div class="champ">
                            <label>Icône</label>
                            <input type="text" name="icone" maxlength="64" placeholder="fas fa-chart-line">
                            <div class="aide">Une icône Font&nbsp;Awesome, telle qu'elle s'écrit.</div>
                        </div>
                    </div>

                    <div class="champ">
                        <label>Texte</label>
                        <textarea name="texte" maxlength="2000"></textarea>
                    </div>

                    <div class="duo">
                        <div class="champ">
                            <label>Valeur mise en avant</label>
                            <input type="text" name="valeur" maxlength="64" placeholder="Un prix, un chiffre…">
                        </div>
                        <div class="champ">
                            <label>Mention</label>
                            <input type="text" name="mention" maxlength="255" placeholder="par mois, hors taxes…">
                        </div>
                    </div>

                    <div class="duo">
                        <div class="champ">
                            <label>Rôle</label>
                            <input type="text" name="role" maxlength="64" placeholder="Comptabilité, Développeur…">
                            <div class="aide">Ce que la carte est, en un mot. En étiquette sur un produit,
                                en fonction sous un nom dans la disposition « Équipe ».</div>
                        </div>
                        <div class="champ">
                            <label>Ordre</label>
                            <input type="number" name="ordre" min="0" max="999" placeholder="à la suite">
                        </div>
                    </div>

                    <div class="duo">
                        <div class="champ">
                            <label>Libellé du lien</label>
                            <input type="text" name="lien_libelle" maxlength="64">
                        </div>
                        <div class="champ">
                            <label>Adresse du lien</label>
                            <input type="text" name="lien_url" maxlength="255" placeholder="https://…, /inscription ou #produits">
                        </div>
                    </div>

                    <div class="duo">
                        <div class="champ">
                            <label>Libellé du second lien</label>
                            <input type="text" name="lien_secondaire_libelle" maxlength="64" placeholder="Documentation">
                        </div>
                        <div class="champ">
                            <label>Adresse du second lien</label>
                            <input type="text" name="lien_secondaire_url" maxlength="255">
                        </div>
                    </div>

                    <div class="champ">
                        <label>Illustration ou portrait</label>
                        <input type="file" name="image" accept="image/*">
                        <div class="aide">Sans photo, la disposition « Équipe » affiche les initiales
                            dans un rond plutôt qu'une case grise.</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Ajouter la carte
                    </button>
                </form>
            </details>

            {{-- Modifier la section --}}
            <details class="pliant">
                <summary><i class="fas fa-pen"></i> Modifier cette section</summary>

                <form method="POST" action="{{ route('superadmin.vitrine.sections.modifier', $section) }}"
                      enctype="multipart/form-data" style="margin-top:10px;">
                    @csrf @method('PUT')
                    <div class="duo">
                        <div class="champ">
                            <label>Titre *</label>
                            <input type="text" name="titre" required maxlength="255" value="{{ $section->titre }}">
                        </div>
                        <div class="champ">
                            <label>Sous-titre</label>
                            <input type="text" name="sous_titre" maxlength="255" value="{{ $section->sous_titre }}">
                        </div>
                    </div>

                    <div class="champ">
                        <label>Texte</label>
                        <textarea name="texte" maxlength="5000">{{ $section->texte }}</textarea>
                    </div>

                    <div class="duo">
                        <div class="champ">
                            <label>Disposition *</label>
                            <select name="gabarit" required>
                                @foreach($gabarits as $code => $libelle)
                                    <option value="{{ $code }}" {{ $section->gabarit === $code ? 'selected' : '' }}>
                                        {{ $libelle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="champ">
                            <label>Ordre *</label>
                            <input type="number" name="ordre" required min="0" max="999" value="{{ $section->ordre }}">
                        </div>
                    </div>

                    <div class="duo">
                        <div class="champ">
                            <label>Fond</label>
                            <select name="fond">
                                @foreach($fonds as $code => $libelle)
                                    <option value="{{ $code }}" {{ $section->fondSur() === $code ? 'selected' : '' }}>
                                        {{ $libelle }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="aide">C'est l'alternance des fonds qui découpe la page à l'œil
                                quand on la parcourt.</div>
                        </div>
                        <div class="champ">
                            <label>Type de média</label>
                            <select name="media_type">
                                <option value="">Aucun</option>
                                @foreach($medias as $code => $libelle)
                                    <option value="{{ $code }}" {{ $section->media_type === $code ? 'selected' : '' }}>
                                        {{ $libelle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="duo">
                        <div class="champ">
                            <label>Déposer le média</label>
                            <input type="file" name="media" accept="image/*,video/mp4,video/webm">
                            <div class="aide">Image ou vidéo, 20 Mo au plus. Au-delà, hébergez la vidéo
                                ailleurs et collez son adresse ci-contre.
                                @if($section->media_path)<br><strong>Un fichier est déjà déposé.</strong>@endif
                            </div>
                        </div>
                        <div class="champ">
                            <label>…ou son adresse</label>
                            <input type="text" name="media_url" maxlength="255" value="{{ $section->media_url }}"
                                   placeholder="https://…">
                            <div class="aide">Le fichier déposé l'emporte sur l'adresse.</div>
                        </div>
                    </div>

                    <div class="champ">
                        <label>Légende du média</label>
                        <input type="text" name="media_legende" maxlength="255" value="{{ $section->media_legende }}">
                    </div>

                    <div class="duo">
                        <div class="champ">
                            <label>Libellé du bouton</label>
                            <input type="text" name="action_libelle" maxlength="64" value="{{ $section->action_libelle }}"
                                   placeholder="Créer un compte">
                        </div>
                        <div class="champ">
                            <label>Adresse du bouton</label>
                            <input type="text" name="action_url" maxlength="255" value="{{ $section->action_url }}"
                                   placeholder="/inscription ou #produits">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-check"></i> Enregistrer
                    </button>
                </form>
            </details>

        </div>
    </div>
@empty
    <div class="vide" style="margin-bottom:22px;">
        Aucune section pour l'instant. La page publique affiche un message d'attente.
    </div>
@endforelse

{{-- ══ Créer une section ══ --}}
<div class="section-bloc">
    <div class="section-entete">
        <span class="titre"><i class="fas fa-plus"></i> Nouvelle section</span>
    </div>
    <div class="section-corps">
        <form method="POST" action="{{ route('superadmin.vitrine.sections.creer') }}">
            @csrf
            <div class="duo">
                <div class="champ">
                    <label>Titre *</label>
                    <input type="text" name="titre" required maxlength="255" value="{{ old('titre') }}">
                </div>
                <div class="champ">
                    <label>Clé *</label>
                    <input type="text" name="cle" required maxlength="64" value="{{ old('cle') }}"
                           placeholder="tarifs">
                    <div class="aide">
                        Minuscules, chiffres et tirets. Elle sert d'ancre dans l'adresse
                        (<code>/presentation#tarifs</code>) et ne change plus ensuite.
                    </div>
                </div>
            </div>

            <div class="champ">
                <label>Sous-titre</label>
                <input type="text" name="sous_titre" maxlength="255" value="{{ old('sous_titre') }}">
            </div>

            <div class="champ">
                <label>Texte</label>
                <textarea name="texte" maxlength="5000">{{ old('texte') }}</textarea>
            </div>

            <div class="duo">
                <div class="champ">
                    <label>Disposition *</label>
                    <select name="gabarit" required>
                        @foreach($gabarits as $code => $libelle)
                            <option value="{{ $code }}" {{ old('gabarit') === $code ? 'selected' : '' }}>
                                {{ $libelle }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="champ">
                    <label>Ordre</label>
                    <input type="number" name="ordre" min="0" max="999" value="{{ old('ordre') }}"
                           placeholder="à la suite">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Créer la section
            </button>
        </form>
    </div>
</div>

@endsection
