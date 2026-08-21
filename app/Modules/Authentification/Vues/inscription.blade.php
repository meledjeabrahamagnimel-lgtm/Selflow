<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Créez votre compte Selflow — Plateforme intelligente de gestion des ventes et stocks">
    <title>Inscription — Selflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #F4F6F9; min-height: 100vh; display: flex; align-items: stretch; }

        .conteneur { display: grid; grid-template-columns: 1fr 1.3fr; width: 100%; min-height: 100vh; }

        /* ── Gauche ── */
        .gauche {
            background: linear-gradient(160deg, #002B5C 0%, #00408A 55%, #0056B8 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 48px; text-align: center; color: #fff; position: relative; overflow: hidden;
        }
        .gauche::before { content:''; position:absolute; width:500px; height:500px; border-radius:50%; background:rgba(255,255,255,.04); top:-150px; left:-150px; }
        .gauche::after  { content:''; position:absolute; width:300px; height:300px; border-radius:50%; background:rgba(255,255,255,.04); bottom:-80px; right:-80px; }
        .gauche-interieur { max-width:460px; position:relative; z-index:1; }
        .marque { display:flex; align-items:center; gap:12px; margin-bottom:28px; justify-content:center; }
        .marque-icone { width:44px; height:44px; border-radius:12px; background:rgba(255,255,255,.12); display:flex; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,.2); }
        .marque-icone i { color:#fff; font-size:22px; }
        .marque-nom { font-size:26px; font-weight:800; letter-spacing:-.5px; }
        .badge-promo { display:inline-flex; align-items:center; gap:6px; background:#FFC107; color:#1A1A2E; font-size:12px; font-weight:700; padding:6px 16px; border-radius:20px; margin-bottom:24px; }
        .slogan-titre { font-size:28px; font-weight:800; line-height:1.25; margin-bottom:12px; }
        .slogan-sous { font-size:14px; color:rgba(255,255,255,.75); line-height:1.65; margin-bottom:28px; }
        .etapes { display:flex; flex-direction:column; gap:12px; text-align:left; }
        .etape { display:flex; align-items:flex-start; gap:12px; }
        .etape-num { width:28px; height:28px; border-radius:50%; flex-shrink:0; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; }
        .etape-texte { font-size:13px; color:rgba(255,255,255,.85); line-height:1.5; }
        .etape-texte strong { color:#fff; }

        /* ── Droite ── */
        .droite { display:flex; align-items:flex-start; justify-content:center; padding:32px 24px; overflow-y:auto; }
        .carte-form { background:#fff; border-radius:20px; box-shadow:0 4px 32px rgba(0,0,0,.08); padding:32px 36px; width:100%; max-width:560px; }
        .form-titre { font-size:22px; font-weight:800; color:#111827; margin-bottom:4px; }
        .form-sous { font-size:13px; color:#6B7280; margin-bottom:20px; }

        /* Section labels */
        .section-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#9CA3AF; margin:18px 0 10px; display:flex; align-items:center; gap:8px; }
        .section-label::after { content:''; flex:1; height:1px; background:#F3F4F6; }

        /* Champs */
        .champ { margin-bottom:12px; }
        .champ label { display:block; font-size:12.5px; font-weight:600; color:#374151; margin-bottom:5px; }
        .champ label span.req { color:#E53E3E; }
        .champ label small { font-size:10px; color:#9CA3AF; font-weight:400; margin-left:4px; }
        input[type=text], input[type=email], input[type=tel], input[type=password], select, textarea {
            width:100%; padding:10px 13px; border:1.5px solid #E5E7EB; border-radius:9px;
            font-size:13.5px; font-family:inherit; color:#111827; background:#fff;
            transition:border-color .15s, box-shadow .15s; outline:none;
        }
        input:focus, select:focus, textarea:focus { border-color:#002B5C; box-shadow:0 0 0 3px rgba(0,43,92,.09); }
        input.erreur, select.erreur { border-color:#EF4444; }
        select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239CA3AF' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:16px; padding-right:36px; }
        .rangee-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .rangee-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }

        /* Régime tooltip */
        .regime-option-wrap { position:relative; }
        .regime-info { display:none; position:absolute; top:100%; left:0; right:0; z-index:10; background:#1e293b; color:#e2e8f0; font-size:11px; line-height:1.5; padding:10px 12px; border-radius:8px; margin-top:4px; box-shadow:0 8px 24px rgba(0,0,0,.2); }
        select:focus + .regime-info { display:block; }

        /* Secteurs d'activité */
        .secteurs-grille { display:grid; grid-template-columns:repeat(2, 1fr); gap:8px; }
        .secteur-case { display:flex; align-items:center; gap:8px; padding:9px 12px; border:1.5px solid #E5E7EB; border-radius:8px; cursor:pointer; transition:all .12s; user-select:none; }
        .secteur-case:hover { border-color:#93c5fd; background:#eff6ff; }
        .secteur-case input[type=checkbox] { width:15px; height:15px; accent-color:#002B5C; flex-shrink:0; }
        .secteur-case span { font-size:12.5px; color:#374151; font-weight:500; }
        .secteur-case input:checked ~ span { color:#002B5C; font-weight:600; }
        .secteur-case:has(input:checked) { border-color:#002B5C; background:#eff6ff; }

        /* Force mot de passe */
        .force-mdp { margin-top:5px; }
        .force-barres { display:flex; gap:4px; margin-bottom:3px; }
        .force-barre { height:3px; flex:1; border-radius:2px; background:#E5E7EB; transition:background .2s; }
        .force-label { font-size:10px; color:#9CA3AF; }

        /* Conditions */
        .conditions-rangee { display:flex; align-items:flex-start; gap:10px; margin:14px 0 18px; cursor:pointer; }
        .conditions-rangee input[type=checkbox] { width:17px; height:17px; accent-color:#002B5C; flex-shrink:0; margin-top:2px; }
        .conditions-rangee span { font-size:12.5px; color:#6B7280; line-height:1.5; }
        .conditions-rangee a { color:#002B5C; font-weight:600; text-decoration:none; }

        /* Boutons */
        .btn-inscription { width:100%; padding:13px; border-radius:10px; background:#002B5C; color:#fff; font-size:14px; font-weight:700; border:none; cursor:pointer; transition:all .15s; font-family:inherit; display:flex; align-items:center; justify-content:center; gap:8px; }
        .btn-inscription:hover { background:#003B73; }
        .btn-inscription:active { transform:scale(.99); }

        /* Séparateur */
        .separateur { display:flex; align-items:center; gap:12px; margin:18px 0 14px; font-size:11.5px; color:#9CA3AF; }
        .separateur::before, .separateur::after { content:''; flex:1; height:1px; background:#E5E7EB; }

        /* Google */
        .btn-google { width:100%; padding:10px; border-radius:9px; background:#fff; color:#374151; font-size:13.5px; font-weight:600; border:1.5px solid #E5E7EB; cursor:pointer; transition:all .15s; font-family:inherit; display:flex; align-items:center; justify-content:center; gap:10px; text-decoration:none; }
        .btn-google:hover { background:#F9FAFB; border-color:#D1D5DB; }

        .alerte-erreur { display:flex; align-items:center; gap:10px; background:#FEF2F2; border:1px solid #FECACA; border-radius:10px; padding:12px 16px; margin-bottom:16px; color:#B91C1C; font-size:13px; font-weight:600; }
        .alerte-info { display:flex; align-items:center; gap:10px; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:10px; padding:12px 16px; margin-bottom:16px; color:#1D4ED8; font-size:13px; font-weight:600; }

        .lien-connexion { text-align:center; margin-top:16px; font-size:13px; color:#6B7280; }
        .lien-connexion a { color:#002B5C; font-weight:700; text-decoration:none; margin-left:4px; }
        .form-pied { margin-top:14px; text-align:center; font-size:11px; color:#9CA3AF; line-height:1.6; }
        .form-pied a { color:#6B7280; text-decoration:none; }

        @media (max-width:900px) { .conteneur { grid-template-columns:1fr; } .gauche { display:none; } .carte-form { padding:24px 18px; } }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>
<div class="conteneur">

    {{-- ── Côté gauche ── --}}
    <div class="gauche">
        <div class="gauche-interieur">
            <div class="marque">
                <div class="marque-icone"><i class="ti ti-cloud"></i></div>
                <span class="marque-nom">Selflow</span>
            </div>
            <div class="badge-promo"><i class="ti ti-star-filled"></i> Inscription gratuite</div>
            <div class="slogan-titre">Commencez à piloter votre entreprise dès aujourd'hui</div>
            <div class="slogan-sous">Rejoignez des entreprises ivoiriennes qui gèrent leurs ventes, achats, stocks et factures FNE avec Selflow.</div>
            <div class="etapes">
                <div class="etape"><div class="etape-num">1</div><div class="etape-texte"><strong>Créez votre compte</strong> en moins de 3 minutes</div></div>
                <div class="etape"><div class="etape-num">2</div><div class="etape-texte"><strong>Configurez votre entreprise</strong> — infos légales, PDV, équipe</div></div>
                <div class="etape"><div class="etape-num">3</div><div class="etape-texte"><strong>Facturez & déclarez</strong> — factures normalisées DGI / FNE incluses</div></div>
            </div>
        </div>
    </div>

    {{-- ── Côté droit ── --}}
    <div class="droite">
        <div class="carte-form">
            <h1 class="form-titre">Créer votre compte</h1>
            <p class="form-sous">Remplissez ce formulaire pour démarrer gratuitement</p>

            @if(session('info'))
                <div class="alerte-info"><i class="ti ti-info-circle"></i> {{ session('info') }}</div>
            @endif
            @if ($errors->has('inscription_erreur') || $errors->any())
                <div class="alerte-erreur" role="alert">
                    <i class="ti ti-alert-circle" style="font-size:16px;flex-shrink:0;"></i>
                    {{ $errors->first('inscription_erreur') ?: $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('inscription.traitement') }}" id="form-inscription" novalidate>
                @csrf

                {{-- ──── Section ENTREPRISE ──── --}}
                <div class="section-label"><i class="ti ti-building"></i> Votre entreprise</div>

                <div class="champ">
                    <label for="nom_entreprise">Nom de l'entreprise <span class="req">*</span></label>
                    <input type="text" id="nom_entreprise" name="nom_entreprise"
                        placeholder="Ex: SARL Mon Commerce CI"
                        value="{{ old('nom_entreprise') }}" required autofocus
                        class="{{ $errors->has('nom_entreprise') ? 'erreur' : '' }}">
                </div>

                <div class="rangee-2">
                    <div class="champ" style="margin-bottom:0">
                        <label for="forme_juridique">Forme juridique</label>
                        <select id="forme_juridique" name="forme_juridique">
                            <option value="">— Choisir —</option>
                            @foreach(['Entreprise individuelle','SARL','SA','SAS','SNC','GIE','Coopérative','Association'] as $fj)
                                <option value="{{ $fj }}" {{ old('forme_juridique') == $fj ? 'selected' : '' }}>{{ $fj }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="champ" style="margin-bottom:0">
                        <label for="regime_imposition">Régime d'imposition <span class="req">*</span></label>
                        <div class="regime-option-wrap">
                            <select id="regime_imposition" name="regime_imposition" required
                                class="{{ $errors->has('regime_imposition') ? 'erreur' : '' }}">
                                <option value="">— Choisir —</option>
                                <option value="TEE" {{ old('regime_imposition') == 'TEE' ? 'selected' : '' }}>TEE — Taxe de l'Entreprenant</option>
                                <option value="RNE" {{ old('regime_imposition') == 'RNE' ? 'selected' : '' }}>RNE — Régime Normal de l'Entreprenant</option>
                                <option value="RSI" {{ old('regime_imposition') == 'RSI' ? 'selected' : '' }}>RSI — Régime Simplifié d'Imposition</option>
                                <option value="RNI" {{ old('regime_imposition') == 'RNI' ? 'selected' : '' }}>RNI — Régime Normal d'Imposition</option>
                            </select>
                            <div class="regime-info" id="regime-info-box">
                                Sélectionnez votre régime pour voir sa définition.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="champ" style="margin-top:12px;">
                    <label for="adresse">Adresse physique / Siège social</label>
                    <input type="text" id="adresse" name="adresse"
                        placeholder="Ex: Cocody Cité des Cadres, Abidjan"
                        value="{{ old('adresse') }}">
                </div>

                <div class="rangee-2">
                    <div class="champ" style="margin-bottom:0">
                        <label for="rccm">N° RCCM <small>(ex: CI-ABJ-2021-B-12345)</small></label>
                        <input type="text" id="rccm" name="rccm"
                            placeholder="CI-ABJ-2021-B-12345"
                            value="{{ old('rccm') }}">
                    </div>
                    <div class="champ" style="margin-bottom:0">
                        <label for="compte_contribuable">N° CC / NCC <small>(Compte Contribuable)</small></label>
                        <input type="text" id="compte_contribuable" name="compte_contribuable"
                            placeholder="Ex: 1864699 A"
                            value="{{ old('compte_contribuable') }}">
                    </div>
                </div>

                <div class="rangee-2" style="margin-top:12px;">
                    <div class="champ" style="margin-bottom:0">
                        <label for="email">Email professionnel <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                            placeholder="vous@entreprise.com"
                            value="{{ old('email') }}" required autocomplete="email"
                            class="{{ $errors->has('email') ? 'erreur' : '' }}">
                    </div>
                    <div class="champ" style="margin-bottom:0">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone"
                            placeholder="+225 07 00 00 00"
                            value="{{ old('telephone') }}">
                    </div>
                </div>

                {{-- Secteurs d'activité --}}
                <div class="section-label" style="margin-top:16px;"><i class="ti ti-category"></i> Secteurs d'activité <small style="font-size:10px;font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px;">(cochez tous ceux qui s'appliquent)</small></div>
                {{-- Les douze domaines du référentiel, et rien d'autre : c'est ce
                     même vocabulaire que la première étape de la souscription
                     propose. Dix valeurs écrites en dur vivaient ici, qui n'en
                     recoupaient aucune — l'entreprise cochait « Commercial »
                     pour choisir « Commerce » à l'écran suivant. --}}
                <div class="secteurs-grille">
                    @php
                        $oldSecteurs = old('secteurs_activite', []);
                    @endphp
                    @foreach($domaines as $nom)
                        <label class="secteur-case">
                            <input type="checkbox" name="secteurs_activite[]" value="{{ $nom }}"
                                {{ in_array($nom, $oldSecteurs) ? 'checked' : '' }}>
                            <i class="ti ti-point-filled" style="font-size:14px;color:#002B5C;flex-shrink:0;"></i>
                            <span>{{ $nom }}</span>
                        </label>
                    @endforeach
                </div>

                {{-- ──── Section IDENTITÉ ──── --}}
                <div class="section-label" style="margin-top:18px;"><i class="ti ti-user"></i> Votre identité (Gérant / Admin)</div>

                <div class="rangee-2">
                    <div class="champ" style="margin-bottom:0">
                        <label for="nom">Nom <span class="req">*</span></label>
                        <input type="text" id="nom" name="nom"
                            placeholder="Agnimel"
                            value="{{ old('nom') }}" required
                            class="{{ $errors->has('nom') ? 'erreur' : '' }}">
                    </div>
                    <div class="champ" style="margin-bottom:0">
                        <label for="prenom">Prénom <span class="req">*</span></label>
                        <input type="text" id="prenom" name="prenom"
                            placeholder="Meledje Abraham"
                            value="{{ old('prenom') }}" required
                            class="{{ $errors->has('prenom') ? 'erreur' : '' }}">
                    </div>
                </div>

                <div class="rangee-2" style="margin-top:12px;">
                    <div class="champ" style="margin-bottom:0">
                        <label for="fonction_gerant">Fonction / Titre</label>
                        <input type="text" id="fonction_gerant" name="fonction_gerant"
                            placeholder="Ex: Gérant, DG, PDG, Directeur"
                            value="{{ old('fonction_gerant') }}">
                    </div>
                    <div class="champ" style="margin-bottom:0">
                        <label for="telephone_gerant">Téléphone personnel</label>
                        <input type="tel" id="telephone_gerant" name="telephone_gerant"
                            placeholder="+225 07 00 00 00"
                            value="{{ old('telephone_gerant') }}">
                    </div>
                </div>

                {{-- ──── Section SÉCURITÉ ──── --}}
                <div class="section-label" style="margin-top:18px;"><i class="ti ti-lock"></i> Sécurité du compte</div>

                <div class="champ">
                    <label for="password">Mot de passe <span class="req">*</span></label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password"
                            placeholder="Au moins 8 caractères"
                            autocomplete="new-password" required
                            oninput="evaluerForce(this.value)"
                            style="padding-right:44px;"
                            class="{{ $errors->has('password') ? 'erreur' : '' }}">
                        <button type="button" id="toggle-password"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6B7280;padding:4px;">
                            <i class="ti ti-eye" id="icone-oeil" style="font-size:18px;"></i>
                        </button>
                    </div>
                    <div class="force-mdp" id="force-mdp" style="display:none;">
                        <div class="force-barres">
                            <div class="force-barre" id="b1"></div>
                            <div class="force-barre" id="b2"></div>
                            <div class="force-barre" id="b3"></div>
                            <div class="force-barre" id="b4"></div>
                        </div>
                        <span class="force-label" id="force-texte">Faible</span>
                    </div>
                </div>
                <div class="champ">
                    <label for="password_confirmation">Confirmer le mot de passe <span class="req">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        placeholder="Répétez le mot de passe"
                        autocomplete="new-password" required>
                </div>

                {{-- Conditions --}}
                <label class="conditions-rangee">
                    <input type="checkbox" name="conditions" id="conditions" value="1" {{ old('conditions') ? 'checked' : '' }}>
                    <span>J'accepte les <a href="{{ route('contact.info') }}#conditions" target="_blank">Conditions d'utilisation</a> et la <a href="{{ route('contact.info') }}#politique" target="_blank">Politique de confidentialité</a> de Selflow.</span>
                </label>

                <button type="submit" class="btn-inscription" id="btn-soumettre">
                    <i class="ti ti-user-plus"></i>
                    Créer mon compte gratuitement
                </button>
            </form>

            <div class="separateur">ou</div>

            <a href="{{ route('auth.google') }}" class="btn-google" id="btn-google-inscription">
                <svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                S'inscrire avec Google
            </a>

            <div class="lien-connexion">
                Déjà un compte ?
                <a href="{{ route('connexion') }}">Se connecter</a>
            </div>
            <div class="form-pied">© {{ date('Y') }} Selflow — DC KNOWING · Tous droits réservés</div>
        </div>
    </div>

</div>

<script>
// ── Régimes ivoiriens — définitions ──
const regimesDefs = {
    TEE: "Taxe de l'Entreprenant (TEE) : pour les auto-entrepreneurs et très petites entreprises. CA annuel < 50 millions FCFA. Taux fixe annuel. Pas d'obligation TVA.",
    RNE: "Régime Normal de l'Entreprenant (RNE) : pour les PME. CA entre 50M et 200M FCFA. Impôt sur le bénéfice. Comptabilité simplifiée.",
    RSI: "Régime Simplifié d'Imposition (RSI) : pour les entreprises moyennes. CA entre 200M et 500M FCFA. TVA sur option. Comptabilité standard.",
    RNI: "Régime Normal d'Imposition (RNI) : pour les grandes entreprises. CA > 500M FCFA. TVA obligatoire (18%). Comptabilité complète SYSCOHADA.",
};

document.getElementById('regime_imposition').addEventListener('change', function() {
    const box = document.getElementById('regime-info-box');
    box.textContent = regimesDefs[this.value] || 'Sélectionnez votre régime pour voir sa définition.';
    box.style.display = this.value ? 'block' : 'none';
});

// ── Afficher/masquer mot de passe ──
document.getElementById('toggle-password').addEventListener('click', function() {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('icone-oeil');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'ti ti-eye-off'; }
    else { inp.type = 'password'; icon.className = 'ti ti-eye'; }
});

// ── Force du mot de passe ──
function evaluerForce(val) {
    const bloc = document.getElementById('force-mdp');
    if (!val) { bloc.style.display = 'none'; return; }
    bloc.style.display = 'block';
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const couleurs = ['#EF4444','#F59E0B','#10B981','#059669'];
    const labels   = ['Faible','Moyen','Fort','Très fort'];
    ['b1','b2','b3','b4'].forEach((id, i) => {
        document.getElementById(id).style.background = i < score ? couleurs[score-1] : '#E5E7EB';
    });
    document.getElementById('force-texte').textContent = labels[score-1] || 'Faible';
    document.getElementById('force-texte').style.color = couleurs[score-1] || '#EF4444';
}

// ── Désactiver bouton soumission ──
document.getElementById('form-inscription').addEventListener('submit', function() {
    const btn = document.getElementById('btn-soumettre');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2" style="animation:spin 1s linear infinite;"></i> Création en cours…';
});
</script>
</body>
</html>
