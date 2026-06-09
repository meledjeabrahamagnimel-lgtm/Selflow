<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Connexion à Selflow — Plateforme intelligente de gestion des ventes et stocks">
    <title>Connexion — Selflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #F4F6F9;
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            margin: 0;
            padding: 0;
        }

        .conteneur {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            min-height: 100vh;
        }

        /* ── Côté gauche ── */
        .gauche {
            background: #002B5C;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px;
            text-align: center;
            color: #ffffff;
        }

        .gauche-interieur {
            max-width: 480px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .marque {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .marque-icone {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .marque-icone i {
            color: #ffffff;
            font-size: 22px;
        }

        .marque-nom {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .badge-promo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FFC107;
            color: #1A1A2E;
            font-size: 12.5px;
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 20px;
            margin-bottom: 32px;
        }

        .slogan-titre {
            font-size: 32px;
            font-weight: 700;
            line-height: 1.35;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .slogan-sous {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
        }

        /* ── Côté droit ── */
        .droite {
            background: #F8F9FA;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .carte-form {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.06);
            padding: 40px;
            width: 100%;
            max-width: 440px;
        }

        .form-titre {
            font-size: 24px;
            font-weight: 700;
            color: #1A1A2E;
            margin-bottom: 8px;
            text-align: center;
        }

        .form-sous {
            font-size: 13.5px;
            color: #8A8FA8;
            margin-bottom: 32px;
            text-align: center;
        }

        .alerte-erreur {
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 12.5px;
            color: #991B1B;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .champ {
            margin-bottom: 20px;
        }

        .champ label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4A506B;
            margin-bottom: 8px;
        }

        .champ input {
            width: 100%;
            border: 1px solid #D1DBEC;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            background: #EBF2FC;
            color: #1A1A2E;
            outline: none;
            transition: all 0.15s;
            font-family: inherit;
        }

        .champ input:focus {
            border-color: #002B5C;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 43, 92, 0.15);
        }

        .champ input.erreur {
            border-color: #DC2626;
            background: #FEE2E2;
        }

        .champ-erreur {
            font-size: 12px;
            color: #DC2626;
            margin-top: 6px;
        }

        .options-rangee {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 26px;
        }

        .checkbox-rangee {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox-rangee input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 6px;
            accent-color: #002B5C;
            cursor: pointer;
        }

        .checkbox-label {
            font-size: 13px;
            color: #6B7280;
        }

        .lien-oubli {
            font-size: 13px;
            color: #002B5C;
            text-decoration: none;
            font-weight: 600;
        }

        .lien-oubli:hover {
            text-decoration: underline;
        }

        .btn-connexion {
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            background: #002B5C;
            color: #ffffff;
            font-size: 14.5px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
        }

        .btn-connexion:hover {
            background: #003B73;
        }

        .btn-connexion:active {
            transform: scale(0.99);
        }

        .form-pied {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: #9CA3AF;
            line-height: 1.6;
        }

        .form-pied a {
            color: #6B7280;
            text-decoration: none;
        }

        .form-pied a:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            .conteneur {
                grid-template-columns: 1fr;
            }

            .gauche {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="conteneur">

        {{-- ── Côté gauche ── --}}
        <div class="gauche">
            <div class="gauche-interieur">
                <div class="marque">
                    <div class="marque-icone">
                        <i class="ti ti-cloud" aria-hidden="true"></i>
                    </div>
                    <span class="marque-nom">Selflow</span>
                </div>

                <div class="badge-promo">
                    <i class="ti ti-star-filled" aria-hidden="true"></i>
                    La solution pour mieux gérer
                </div>

                <div class="slogan-titre">
                    Pilotez votre entreprise avec intelligence
                </div>

                <div class="slogan-sous">
                    La plateforme intelligente pour vos achats,
                    ventes , facturations et stocks en temps réel.
                </div>
            </div>
        </div>

        {{-- ── Côté droit ── --}}
        <div class="droite">
            <div class="carte-form">

                <h1 class="form-titre">Connexion</h1>
                <p class="form-sous">Connectez-vous pour accéder à votre dashboard</p>

                {{-- Message d'erreur global --}}
                @if ($errors->any())
                    <div class="alerte-erreur" role="alert">
                        <i class="ti ti-alert-circle" style="font-size:16px;"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('connexion.traitement') }}" id="form-connexion" novalidate>
                    @csrf

                    {{-- Email --}}
                    <div class="champ">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="vous@entreprise.com"
                            value="{{ old('email') }}" autocomplete="email"
                            class="{{ $errors->has('email') ? 'erreur' : '' }}" required autofocus>
                        @error('email')
                            <div class="champ-erreur">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Mot de passe --}}
                    <div class="champ">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" placeholder="••••••••"
                            autocomplete="current-password" class="{{ $errors->has('password') ? 'erreur' : '' }}"
                            required>
                        @error('password')
                            <div class="champ-erreur">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Options --}}
                    <div class="options-rangee">
                        <label class="checkbox-rangee">
                            <input type="checkbox" name="se_souvenir" id="se_souvenir" value="1" {{ old('se_souvenir') ? 'checked' : '' }}>
                            <span class="checkbox-label">Se souvenir de moi</span>
                        </label>
                        <a href="#" class="lien-oubli">Mot de passe oublié ?</a>
                    </div>

                    <button type="submit" class="btn-connexion" id="btn-soumettre">
                        Se connecter
                    </button>
                </form>

                <div style="text-align:center; margin-top:22px; font-size:13.5px;">
                    <span style="color:#6B7280;">Pas encore de compte ?</span>
                    <a href="#" style="color:#002B5C; text-decoration:none; font-weight:600; margin-left:4px;">Créer un
                        compte gratuitement</a>
                </div>

                <div class="form-pied">
                    En vous connectant, vous acceptez nos
                    <a href="#">Conditions</a> et notre
                    <a href="#">Politique de confidentialité</a>
                </div>

            </div>
        </div>

    </div>

</body>

</html>