{{--
    L'ouverture d'un dossier comptable, annoncée à son titulaire.

    ── Pourquoi des tableaux et des styles écrits dans les balises ──

    Un client de messagerie n'est pas un navigateur. Outlook rend le HTML avec
    le moteur de Word ; Gmail retire les feuilles de style et une bonne part de
    ce qu'on écrit dans `<head>`. La mise en page tient donc sur des tableaux
    imbriqués, et chaque style est porté par sa balise. Ce qui serait une faute
    sur une page l'est ici l'inverse.

    ── Ce que ce message ne contient pas ──

    **Le mot de passe.** On dit lequel c'est, jamais quel il est. Un courriel
    traverse des serveurs qu'on ne choisit pas et dort des années dans une
    sauvegarde.

    **La clé de liaison.** Elle ne concerne pas le client.
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
    style="background:#f4f6f9;margin:0;padding:24px 0;font-family:Arial,Helvetica,sans-serif;">
    <tr>
        <td align="center">

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">

                {{-- ══ EN-TÊTE ══ --}}
                <tr>
                    <td style="background:#002B5C;padding:26px 32px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:20px;font-weight:bold;color:#ffffff;letter-spacing:.5px;">
                                    Selflow
                                </td>
                                <td align="right" style="font-size:12px;color:#a8c3e5;">
                                    Comptabilité
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top:14px;font-size:17px;color:#ffffff;line-height:1.4;">
                            Votre dossier comptable est ouvert
                        </div>
                    </td>
                </tr>

                {{-- ══ CORPS ══ --}}
                <tr>
                    <td style="padding:30px 32px 8px;">

                        <p style="margin:0 0 16px;font-size:15px;color:#1e293b;line-height:1.6;">
                            Bonjour{{ $prenom ? ' ' . $prenom : '' }},
                        </p>

                        <p style="margin:0 0 20px;font-size:14px;color:#475569;line-height:1.7;">
                            Un dossier comptable vient d'être ouvert dans <strong>Comptaflow</strong> au nom de
                            <strong>{{ $nomEntreprise }}</strong>. Vos ventes, vos achats et vos règlements
                            enregistrés dans Selflow y sont désormais reportés au fil de l'eau.
                        </p>

                        {{-- Le point du message : il n'y a rien à faire. --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                            style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;margin:0 0 22px;">
                            <tr>
                                <td style="padding:16px 18px;">
                                    <div style="font-size:14px;font-weight:bold;color:#065f46;margin-bottom:8px;">
                                        Vos accès sont les mêmes qu'ici
                                    </div>
                                    <div style="font-size:14px;color:#166534;line-height:1.8;">
                                        Identifiant&nbsp;: <strong>{{ $identifiant }}</strong><br>
                                        Mot de passe&nbsp;: celui de votre compte Selflow, au jour de l'ouverture.
                                    </div>
                                    <div style="font-size:12.5px;color:#3f7a55;line-height:1.6;margin-top:10px;">
                                        Vous n'avez aucun compte à créer et aucun mot de passe à choisir.
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
                            <tr>
                                <td style="background:#002B5C;border-radius:8px;">
                                    <a href="{{ $adresse }}"
                                        style="display:inline-block;padding:13px 28px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">
                                        Ouvrir ma comptabilité
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 6px;font-size:12.5px;color:#64748b;line-height:1.6;">
                            Si le bouton ne fonctionne pas, recopiez cette adresse dans votre navigateur&nbsp;:<br>
                            <span style="color:#002B5C;">{{ $adresse }}</span>
                        </p>

                        @if($numeroDossier)
                            <p style="margin:14px 0 0;font-size:12.5px;color:#94a3b8;line-height:1.6;">
                                Référence du dossier&nbsp;: <strong>#{{ $numeroDossier }}</strong> — à rappeler si vous
                                écrivez au support.
                            </p>
                        @endif

                        {{-- Ce que le message ne dit pas, il faut le dire aussi :
                             une personne qui reçoit ce courriel sans rien avoir
                             demandé doit savoir à qui s'adresser. --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                            style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;margin:22px 0 0;">
                            <tr>
                                <td style="padding:14px 18px;font-size:12.5px;color:#92400e;line-height:1.7;">
                                    <strong>Vous n'attendiez pas ce message&nbsp;?</strong>
                                    Cela signifie qu'un dossier a été ouvert à votre nom sans votre demande.
                                    Répondez à ce courriel&nbsp;: nous le fermerons.
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                {{-- ══ PIED ══ --}}
                <tr>
                    <td style="padding:24px 32px 28px;">
                        <div style="border-top:1px solid #e2e8f0;padding-top:18px;">
                            <p style="margin:0 0 8px;font-size:12px;color:#94a3b8;line-height:1.7;">
                                Ce message vous est adressé parce qu'un dossier comptable a été ouvert pour
                                <strong>{{ $nomEntreprise }}</strong>. Il ne s'agit pas d'une lettre d'information&nbsp;:
                                vous ne pouvez pas vous en désabonner, et nous ne vous en enverrons pas d'autre à ce
                                titre.
                            </p>
                            <p style="margin:0 0 12px;font-size:12px;color:#94a3b8;line-height:1.7;">
                                <strong>Nous ne vous demanderons jamais votre mot de passe</strong>, ni par courriel,
                                ni par téléphone. Aucun mot de passe ne figure dans ce message.
                            </p>
                            <p style="margin:0;font-size:12px;color:#cbd5e1;">
                                Selflow — {{ now()->year }}
                            </p>
                        </div>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>
