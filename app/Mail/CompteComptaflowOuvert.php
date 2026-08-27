<?php

namespace App\Mail;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * « Votre dossier comptable est ouvert. »
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Pourquoi ce message existe
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Un compte s'ouvrait au nom du client, chez une autre application, **sans
 * qu'il en soit informé**. Ce n'est pas une question de confort : une personne
 * doit apprendre qu'un compte existe à son nom, où il se trouve, et avec quoi
 * il s'ouvre. Le superadministrateur le voyait, l'écran des paramètres le
 * disait à qui allait le lire — le titulaire du compte, lui, ne savait rien.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce que le message ne contient pas, et ne contiendra jamais
 * ─────────────────────────────────────────────────────────────────────────
 *
 * **Le mot de passe.** On dit *lequel* c'est — celui de Selflow — jamais
 * *quel* il est. Un courriel traverse des serveurs qu'on ne choisit pas, se
 * range dans une boîte qui peut être partagée, et se retrouve dans une
 * sauvegarde pour des années. Le mot de passe n'y a pas sa place, même quand
 * c'est nous qui l'avons transmis sous forme d'empreinte.
 *
 * **Ni la clé de liaison.** Elle ne concerne pas le client : il n'a rien à en
 * faire, et la lui montrer ferait d'un secret d'infrastructure une donnée qui
 * traîne.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * La réserve à connaître
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Les deux mots de passe sont identiques **le jour de la création**. Si le
 * client change celui de Selflow ensuite, celui de Comptaflow ne suit pas :
 * ils divergent en silence. Le message le dit — « au jour de l'ouverture » —
 * plutôt que de promettre ce qui ne sera plus vrai dans six mois. La
 * propagation du changement par la passerelle reste à faire.
 */
class CompteComptaflowOuvert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Entreprise $entreprise,
        public readonly Utilisateur $destinataire,
        public readonly string $adresseComptaflow,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre dossier comptable Comptaflow est ouvert',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comptaflow.compte-ouvert',
            with: [
                'nomEntreprise' => $this->entreprise->nom,
                'identifiant'   => $this->destinataire->email,
                'prenom'        => trim($this->destinataire->prenom ?: $this->destinataire->nom),
                'adresse'       => $this->adresseComptaflow,
                'numeroDossier' => $this->entreprise->comptaflow_company_id,
            ],
        );
    }
}
