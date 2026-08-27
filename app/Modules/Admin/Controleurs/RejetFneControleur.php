<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\FneRejet;
use App\Modules\Admin\Modeles\PortailFneDemande;
use App\Modules\Admin\Modeles\PortailFneFiche;
use App\Modules\Admin\Modeles\PortailFneImport;
use App\Modules\Admin\Services\DiagnosticFneService;
use Carbon\CarbonImmutable;
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
    /**
     * Le filtre par cause, et la valeur qui désigne les rejets sans cause.
     *
     * `NULL` ne se passe pas dans une URL ; les lignes consignées avant que la
     * colonne existe ont pourtant besoin d'être atteignables, faute de quoi
     * elles disparaissent de l'écran dès qu'on filtre.
     */
    private const CAUSE_NON_CLASSES = 'non-classes';

    public function index(): View
    {
        $entreprise = Auth::user()->entreprise;

        $causesConnues = [
            FneRejet::CAUSE_DGI,
            FneRejet::CAUSE_RESEAU,
            FneRejet::CAUSE_LOCALE,
            self::CAUSE_NON_CLASSES,
        ];

        // Une valeur inventée dans l'URL ne filtre rien plutôt que de rendre
        // une liste vide : un écran vide se lit « aucun rejet », ce qui est
        // exactement le contraire de ce qu'il faut comprendre.
        $causeActive = in_array(request('cause'), $causesConnues, true)
            ? request('cause')
            : null;

        $pourEntreprise = fn () => FneRejet::where('entreprise_id', $entreprise->id);

        // Les rejets ouverts d'abord, les classés en dernier. Un `CASE` et non
        // `FIELD()`, qui n'existe que chez MySQL : les épreuves tournent sur
        // SQLite, et une requête qui n'y passe pas n'est jamais éprouvée.
        $rejets = $pourEntreprise()
            ->when($causeActive === self::CAUSE_NON_CLASSES, fn ($q) => $q->whereNull('cause'))
            ->when(
                $causeActive !== null && $causeActive !== self::CAUSE_NON_CLASSES,
                fn ($q) => $q->where('cause', $causeActive)
            )
            ->orderByRaw("CASE statut WHEN 'ouvert' THEN 0 WHEN 'diagnostique' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(25)
            // Sans quoi la page 2 revient sur la liste entière, et l'on croit
            // que le filtre a lâché.
            ->withQueryString();

        // Un seul passage plutôt qu'une requête par cause. `COALESCE` et non
        // une clé nulle : en PHP, un index `null` devient `''` et la ligne des
        // non-classés se perdrait en silence.
        $parCause = $pourEntreprise()
            ->selectRaw('COALESCE(cause, ?) as cause, COUNT(*) as total', [self::CAUSE_NON_CLASSES])
            ->groupBy('cause')
            ->pluck('total', 'cause');

        $compte = fn (string $statut) => FneRejet::where('entreprise_id', $entreprise->id)
            ->where('statut', $statut)
            ->count();

        // La dernière fiche porte les écarts de paramétrage, qui ne sont la
        // cause d'aucun rejet mais que celui qui répare une facture a intérêt
        // à voir.
        $fiche = PortailFneFiche::where('entreprise_id', $entreprise->id)
            ->orderByDesc('date_scraping')
            ->orderByDesc('id')
            ->first();

        // Elle ne date plus l'écran pour autant. Depuis qu'un relevé identique
        // au précédent n'écrit plus de fiche, sa date est celle du dernier
        // *changement* du portail, pas celle du dernier passage. Les confondre
        // afficherait « relevé du 15/08 » un 27/08 sur un scraper qui tourne
        // parfaitement — et ferait chercher une panne là où il n'y en a pas.
        $dernierPassage = PortailFneImport::where('entreprise_id', $entreprise->id)
            ->where('statut', PortailFneImport::STATUT_IMPORTE)
            ->max('dernier_releve_le');

        return view('admin::fne.rejets', [
            'entreprise'  => $entreprise,
            'rejets'      => $rejets,
            'causeActive' => $causeActive,
            // L'ordre des onglets est celui de l'urgence : ce que la DGI a
            // refusé d'abord, ce qui n'est jamais parti ensuite, ce qui n'a
            // même pas quitté Selflow en dernier.
            'filtresCause' => [
                FneRejet::CAUSE_DGI      => ['libelle' => 'Refus DGI',        'total' => $parCause[FneRejet::CAUSE_DGI] ?? 0],
                FneRejet::CAUSE_RESEAU   => ['libelle' => 'Réseau',           'total' => $parCause[FneRejet::CAUSE_RESEAU] ?? 0],
                FneRejet::CAUSE_LOCALE   => ['libelle' => 'Bloqué ici',       'total' => $parCause[FneRejet::CAUSE_LOCALE] ?? 0],
                self::CAUSE_NON_CLASSES  => ['libelle' => 'Cause inconnue',   'total' => $parCause[self::CAUSE_NON_CLASSES] ?? 0],
            ],
            'totalRejets' => $parCause->sum(),
            'fiche'          => $fiche,
            'dernierPassage' => $dernierPassage ? CarbonImmutable::parse($dernierPassage) : null,
            'ecartsFiche'    => $fiche?->ecartsAvecEntreprise() ?? [],
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

        // La DGI n'a jamais examiné la pièce : il n'y a rien à comparer au
        // portail, et le rapprochement ne rendrait qu'une conclusion vide.
        // Le rejouer déguiserait un incident de transport en écart de données.
        if ($rejet->estReseau()) {
            return back()->with('erreur', "Cette pièce n'a pas été refusée par la DGI : la plateforme "
                . "n'a pas répondu. Il n'y a rien à rapprocher du portail — la pièce est à renvoyer.");
        }

        $diagnostic = $service->diagnostiquer($rejet);

        if ($diagnostic['releve'] === null) {
            // Le message annonçait qu'une demande venait d'être déposée. Rien
            // ne la dépose ici : la demande est ouverte à la consignation du
            // rejet, par `FneRejet::consigner()`, et elle l'est déjà ou ne le
            // sera pas. Une interface qui annonce un geste qu'elle n'a pas
            // fait se paie au moment où l'on attend le résultat.
            return back()->with('erreur', "Aucun relevé du portail n'est disponible pour cette entreprise. "
                . 'Le rapprochement se fera dès qu\'un relevé sera arrivé dans le dossier d\'import.');
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
