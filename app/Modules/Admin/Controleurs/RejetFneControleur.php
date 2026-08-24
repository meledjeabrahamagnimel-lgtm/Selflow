<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\FneRejet;
use App\Modules\Admin\Modeles\PortailFneDemande;
use App\Modules\Admin\Modeles\PortailFneFiche;
use App\Modules\Admin\Services\DiagnosticFneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * L'écran des pièces que la DGI a refusées.
 *
 * ## Pourquoi il existe
 *
 * Le rapprochement écrivait déjà, chaque heure, la comparaison entre la pièce
 * refusée et le dernier relevé du portail. **Personne ne pouvait la lire.** Une
 * facture refusée la nuit, un diagnostic posé à 1 h 10, et rien à l'écran :
 * tout le mécanisme était aveugle.
 *
 * ## Ce que l'écran permet, et ce qu'il refuse
 *
 * Il permet d'appliquer une correction **descriptive** — le nom d'un point de
 * vente mal orthographié — après l'avoir lue, en un clic. C'est le même geste
 * qu'un renommage depuis l'écran des points de vente, à ceci près qu'ici la
 * valeur du portail est affichée en face.
 *
 * Il refuse d'appliquer les écarts de fiche. `timbre_quittance`, `bapa` et
 * `sticker_solde_alerte` commandent le comportement fiscal : ils sont montrés,
 * jamais recopiés. La règle d'or du projet tient précisément à ce que six
 * écarts de ce genre ont produit, par le passé, des pièces certifiées
 * différentes de celles établies dans Selflow.
 */
class RejetFneControleur
{
    public function index(): View
    {
        $entreprise = Auth::user()->entreprise;

        // Les rejets ouverts d'abord, les classés en dernier. Un `CASE` et non
        // `FIELD()`, qui n'existe que chez MySQL : les épreuves tournent sur
        // SQLite, et une requête qui n'y passe pas n'est jamais éprouvée.
        $rejets = FneRejet::where('entreprise_id', $entreprise->id)
            ->orderByRaw("CASE statut WHEN 'ouvert' THEN 0 WHEN 'diagnostique' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(25);

        $compte = fn (string $statut) => FneRejet::where('entreprise_id', $entreprise->id)
            ->where('statut', $statut)
            ->count();

        // Le dernier relevé sert deux fois : à dater ce que l'écran montre, et
        // à porter les écarts de fiche, qui ne sont la cause d'aucun rejet mais
        // que celui qui répare une facture a intérêt à voir.
        $fiche = PortailFneFiche::where('entreprise_id', $entreprise->id)
            ->orderByDesc('date_scraping')
            ->orderByDesc('id')
            ->first();

        return view('admin::fne.rejets', [
            'entreprise'  => $entreprise,
            'rejets'      => $rejets,
            'fiche'       => $fiche,
            'ecartsFiche' => $fiche?->ecartsAvecEntreprise() ?? [],
            'demandes'    => PortailFneDemande::where('entreprise_id', $entreprise->id)
                ->where('statut', PortailFneDemande::STATUT_EN_ATTENTE)
                ->orderBy('created_at')
                ->get(),
            'kpis' => [
                'ouverts'      => $compte(FneRejet::STATUT_OUVERT),
                'diagnostiques' => $compte(FneRejet::STATUT_DIAGNOSTIQUE),
                'resolus'      => $compte(FneRejet::STATUT_RESOLU),
            ],
        ]);
    }

    /**
     * Rejoue le rapprochement sur un rejet, sans attendre le passage horaire.
     */
    public function diagnostiquer(FneRejet $rejet, DiagnosticFneService $service): RedirectResponse
    {
        $this->verifierAppartenance($rejet);

        $diagnostic = $service->diagnostiquer($rejet);

        if ($diagnostic['releve'] === null) {
            return back()->with('erreur', "Aucun relevé du portail n'est disponible pour cette entreprise. "
                . 'Une demande a été déposée ; le rapprochement se fera dès son arrivée.');
        }

        $rejet->update([
            'diagnostic' => $diagnostic,
            'statut'     => $rejet->statut === FneRejet::STATUT_RESOLU
                ? FneRejet::STATUT_RESOLU
                : FneRejet::STATUT_DIAGNOSTIQUE,
        ]);

        return back()->with('succes', 'Rapprochement effectué. ' . $diagnostic['conclusion']);
    }

    /**
     * Applique une correction descriptive, et une seule à la fois.
     *
     * Le champ à corriger est repris du diagnostic déjà écrit, pas du
     * formulaire : un navigateur peut poster ce qu'il veut, et la liste des
     * champs corrigeables ne se négocie pas côté client.
     */
    public function appliquer(FneRejet $rejet): RedirectResponse
    {
        $this->verifierAppartenance($rejet);

        $correction = $this->correctionApplicable($rejet);

        if ($correction === null) {
            return back()->with('erreur', "Aucune correction applicable sur ce rejet. "
                . 'Rapprochez-le d\'un relevé du portail d\'abord.');
        }

        $piece = $rejet->piece();
        $pdv   = $piece?->pointDeVente;

        if (!$pdv || $pdv->entreprise_id !== Auth::user()->entreprise->id) {
            return back()->with('erreur', 'Le point de vente de cette pièce est introuvable.');
        }

        $ancien = $pdv->nom;
        $pdv->update(['nom' => $correction]);

        // Le diagnostic devient faux dès que la correction est appliquée : il
        // décrivait un écart qui n'existe plus. Le rejet retourne en file
        // d'attente d'un nouveau rapprochement plutôt que d'afficher un
        // constat périmé.
        $rejet->update(['statut' => FneRejet::STATUT_OUVERT, 'diagnostic' => null]);

        return back()->with(
            'succes',
            "Le point de vente « {$ancien} » a été renommé « {$correction} », "
            . 'comme il est déclaré au portail. La pièce peut être renvoyée à la DGI.'
        );
    }

    /**
     * Referme un rejet à la main.
     *
     * Un rejet se referme normalement tout seul quand la pièce finit par
     * passer. Celui-ci sert aux cas que la machine ne voit pas : une pièce
     * annulée, un refus qui n'appelait aucune correction.
     */
    public function resoudre(FneRejet $rejet): RedirectResponse
    {
        $this->verifierAppartenance($rejet);

        $rejet->update(['statut' => FneRejet::STATUT_RESOLU]);

        return back()->with('succes', 'Rejet classé.');
    }

    /**
     * La valeur du portail à appliquer, s'il y en a une.
     *
     * Un seul champ est corrigeable depuis cet écran : le nom du point de
     * vente. Les autres sont soit hors de portée du portail, soit gelés.
     */
    private function correctionApplicable(FneRejet $rejet): ?string
    {
        foreach ($rejet->diagnostic['champs'] ?? [] as $champ) {
            if (($champ['champ'] ?? null) !== 'pointOfSale' || ($champ['verdict'] ?? null) !== 'ecart') {
                continue;
            }

            // Un seul nom déclaré au portail : il n'y a pas d'ambiguïté.
            // Plusieurs : la machine ne choisit pas à la place de l'utilisateur,
            // qui seul sait dans quel point de vente la pièce a été établie.
            $declares = $champ['portail'] ?? [];

            return count($declares) === 1 ? (string) $declares[0] : null;
        }

        return null;
    }

    /**
     * Une entreprise ne voit et ne touche que ses propres rejets.
     */
    private function verifierAppartenance(FneRejet $rejet): void
    {
        abort_if($rejet->entreprise_id !== Auth::user()->entreprise->id, 404);
    }
}
