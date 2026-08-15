<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Admin\Modeles\Referentiel\Categorie;
use App\Modules\Admin\Modeles\Referentiel\Famille;
use App\Modules\Admin\Modeles\Referentiel\Profil;
use App\Modules\Admin\Regles\Appartenance;
use App\Modules\Admin\Services\SouscriptionProfilService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Modules\Admin\Regles\Quantite;

/**
 * Le parcours de configuration d'une entreprise.
 *
 * Il se déplie : chaque étape n'apparaît qu'une fois la précédente validée, et
 * se recharge si l'utilisateur revient en arrière. Il se quitte et se reprend —
 * l'étape atteinte est retenue sur l'entreprise, pas dans la session, pour
 * qu'un changement de poste ou de navigateur ne fasse pas tout recommencer.
 *
 * Le superadmin n'y intervient jamais.
 *
 *   1. Catégorie      quel grand domaine
 *   2. Profils        le métier réel, plusieurs possibles, ou « Autre »
 *   3. Modules        ce que les profils ouvrent, décochable
 *   4. Familles       les rayons à retenir, tous cochés au départ
 *   5. Prix et stock  les seules colonnes que le référentiel laisse vides
 */
class SouscriptionControleur
{
    /** Nombre d'étapes du parcours. */
    private const DERNIERE_ETAPE = 5;

    public function index(Request $request): View
    {
        $entreprise = $this->entreprise();
        $etape = $this->etapeDemandee($request, $entreprise);

        return view('admin::souscription.parcours', [
            'entreprise' => $entreprise,
            'etape'      => $etape,
            'derniere'   => self::DERNIERE_ETAPE,
            'choix'      => $this->choix($entreprise),
        ] + $this->donneesDeLEtape($etape, $entreprise));
    }

    /**
     * Enregistrer une étape et passer à la suivante.
     */
    public function enregistrer(Request $request, int $etape): RedirectResponse
    {
        $entreprise = $this->entreprise();

        // On ne peut pas valider une étape que l'on n'a pas atteinte : sans ce
        // contrôle, un formulaire forgé sauterait le choix des profils et
        // souscrirait à des familles qui n'appartiennent à aucun d'eux.
        if ($etape > $entreprise->souscription_etape + 1 || $etape < 1 || $etape > self::DERNIERE_ETAPE) {
            return redirect()->route('admin.souscription.index')
                ->with('erreur', 'Cette étape n\'est pas encore accessible.');
        }

        $suite = match ($etape) {
            1 => $this->validerCategorie($request, $entreprise),
            2 => $this->validerProfils($request, $entreprise),
            3 => $this->validerModules($request, $entreprise),
            4 => $this->validerFamilles($request, $entreprise),
            5 => $this->validerPrix($request, $entreprise),
        };

        return $suite;
    }

    // ─────────────────────────────────────────────────────────────────
    // LES ÉTAPES
    // ─────────────────────────────────────────────────────────────────

    private function validerCategorie(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $donnees = $request->validate([
            'categorie_id' => ['required', 'integer', 'exists:referentiel_categories,id'],
        ], [
            'categorie_id.required' => 'Choisissez le domaine qui se rapproche le plus de votre activité.',
        ]);

        $this->memoriser($entreprise, ['categorie_id' => (int) $donnees['categorie_id']]);

        return $this->avancerVers(2, $entreprise);
    }

    private function validerProfils(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $donnees = $request->validate([
            'profils'          => ['required_without:activite_autre', 'array'],
            'profils.*'        => ['string', 'exists:referentiel_profils,code'],
            'activite_autre'   => ['nullable', 'string', 'max:150'],
        ], [
            'profils.required_without' => 'Choisissez au moins un métier, ou décrivez le vôtre.',
            'profils.*.exists'         => 'Un des métiers choisis est inconnu.',
        ]);

        $this->memoriser($entreprise, [
            'profils' => array_values($donnees['profils'] ?? []),
        ]);

        // Le référentiel ne couvre pas tous les métiers. Plutôt que de forcer
        // l'utilisateur dans une case, on retient ce qu'il dit — et cela
        // alimentera les versions suivantes du classeur.
        $entreprise->update(['activite_autre' => $donnees['activite_autre'] ?? null]);

        return $this->avancerVers(3, $entreprise);
    }

    private function validerModules(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $donnees = $request->validate([
            'modules'   => ['array'],
            'modules.*' => ['string', 'in:' . implode(',', Entreprise::TOUS_LES_MODULES)],
        ]);

        // Un module qui n'est pas autorisé ne peut pas s'activer, même envoyé
        // par un formulaire : les droits appartiennent au superadmin.
        //
        // Les modules structurels rejoignent le choix quoi qu'il arrive. Leurs
        // cases sont désactivées à l'écran, et une case désactivée **n'est pas
        // transmise** : sans ce rattrapage, valider l'étape les retirerait.
        $retenus = array_values(array_intersect(
            array_unique(array_merge($donnees['modules'] ?? [], Entreprise::MODULES_STRUCTURELS)),
            $entreprise->modulesAutorises()
        ));

        $this->memoriser($entreprise, ['modules' => $retenus]);

        return $this->avancerVers(4, $entreprise);
    }

    private function validerFamilles(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $donnees = $request->validate([
            'familles'   => ['array'],
            'familles.*' => ['string', 'max:32'],
        ]);

        $choix = $this->choix($entreprise);
        $retenues = $donnees['familles'] ?? [];

        // C'est ici que la souscription s'effectue : familles, articles,
        // comptes et modules. Tout ce qui précède n'était qu'un choix.
        $bilan = SouscriptionProfilService::souscrire($entreprise, $choix['profils'] ?? [], $retenues);

        // Les modules décochés à l'étape 3 le restent : la souscription ouvre
        // ce que le métier demande, la préférence de l'utilisateur prime.
        if (isset($choix['modules'])) {
            $entreprise->update([
                'modules_actifs' => array_values(array_intersect(
                    $bilan['modules'],
                    array_merge($choix['modules'], Entreprise::MODULES_STRUCTURELS)
                )),
            ]);
        }

        return $this->avancerVers(5, $entreprise)
            ->with('succes', sprintf(
                '%d famille(s) et %d article(s) ajoutés à votre catalogue.',
                $bilan['familles'], $bilan['articles']
            ));
    }

    private function validerPrix(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $donnees = $request->validate([
            'articles'                 => ['array'],
            'articles.*.id'            => ['required', 'integer', Appartenance::a('produits')],
            'articles.*.nom'           => ['nullable', 'string', 'max:255'],
            'articles.*.prix_achat'    => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'articles.*.prix_vente'    => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'articles.*.stock_initial' => Quantite::facultative(),
        ]);

        $pointDeVenteId = $this->siteDuStock($entreprise)?->id;

        foreach ($donnees['articles'] ?? [] as $ligne) {
            // `Appartenance` a déjà écarté les produits d'une autre entreprise ;
            // la condition ci-dessous est la ceinture après les bretelles.
            $produit = Produit::where('id', $ligne['id'])
                ->where('entreprise_id', $entreprise->id)
                ->first();

            if (!$produit) {
                continue;
            }

            // Chaque clé est facultative : le corps de la requête n'est pas le
            // formulaire, et une ligne partielle ne doit pas faire tomber
            // l'écran. Une valeur absente laisse l'existante en place.
            $produit->update(array_filter([
                'nom'        => ($ligne['nom'] ?? null) ?: null,
                'prix_achat' => $ligne['prix_achat'] ?? null,
                'prix_vente' => $ligne['prix_vente'] ?? null,
            ], fn ($v) => $v !== null));

            $this->poserStockInitial($produit, $pointDeVenteId, $ligne['stock_initial'] ?? null);
        }

        $entreprise->update([
            'souscription_etape'       => self::DERNIERE_ETAPE,
            'souscription_terminee_le' => now(),
        ]);

        return redirect()->route('admin.tableau_de_bord')
            ->with('succes', 'Votre entreprise est configurée. Bonne vente !');
    }

    /**
     * Le site sur lequel s'inscrit le stock d'ouverture.
     *
     * Celui de la session si l'utilisateur en a choisi un et qu'il appartient
     * bien à son entreprise — un identifiant resté en session après un
     * changement de compte ne doit pas désigner le dépôt du voisin. À défaut,
     * le premier site enregistré. Aucun site, aucun stock : la colonne
     * disparaît de l'écran plutôt que d'avaler la saisie.
     *
     * Le formulaire et l'enregistrement lisent la même méthode : la colonne
     * affichée est exactement celle qui sera écrite.
     */
    private function siteDuStock(Entreprise $entreprise): ?PointDeVente
    {
        if (!$entreprise->moduleEstActif('stock')) {
            return null;
        }

        $sites = PointDeVente::where('entreprise_id', $entreprise->id)->orderBy('id');

        if ($choisi = session('point_de_vente_actif_id')) {
            $site = (clone $sites)->where('id', $choisi)->first();

            if ($site) {
                return $site;
            }
        }

        return $sites->first();
    }

    /**
     * Poser le stock d'ouverture d'un article.
     *
     * Seuls les articles qui se comptent en reçoivent un : un service n'a ni
     * quantité disponible, ni seuil, ni rupture. Créer sa fiche le ferait
     * figurer en permanence dans les alertes de stock — pour un cabinet
     * comptable, dont tous les articles sont des missions, le tableau de bord
     * n'annoncerait que des ruptures sur des choses qui ne s'épuisent pas.
     *
     * C'est un inventaire d'ouverture : la quantité constatée au jour du
     * démarrage, sur le point de vente actif.
     */
    private function poserStockInitial(Produit $produit, ?int $pointDeVenteId, $quantite): void
    {
        if (!$produit->estStockable() || !$pointDeVenteId || $quantite === null || $quantite === '') {
            return;
        }

        Stock::updateOrCreate(
            ['produit_id' => $produit->id, 'point_de_vente_id' => $pointDeVenteId],
            ['quantite_disponible' => round((float) $quantite, Stock::DECIMALES),
             'stock_minimum' => 5, 'stock_maximum' => 100]
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // OUTILS
    // ─────────────────────────────────────────────────────────────────

    private function entreprise(): Entreprise
    {
        $entreprise = Auth::user()?->entreprise;

        abort_unless($entreprise, 403, 'Vous n\'êtes rattaché à aucune entreprise.');

        return $entreprise;
    }

    /**
     * Étape à afficher : celle demandée si elle a été atteinte, sinon la
     * dernière atteinte.
     */
    private function etapeDemandee(Request $request, Entreprise $entreprise): int
    {
        $atteinte = min((int) $entreprise->souscription_etape + 1, self::DERNIERE_ETAPE);
        $demandee = (int) $request->query('etape', $atteinte);

        return max(1, min($demandee, $atteinte));
    }

    private function avancerVers(int $etape, Entreprise $entreprise): RedirectResponse
    {
        if ($etape - 1 > $entreprise->souscription_etape) {
            $entreprise->update(['souscription_etape' => $etape - 1]);
        }

        return redirect()->route('admin.souscription.index', ['etape' => $etape]);
    }

    /**
     * Les choix faits jusqu'ici, conservés en session.
     *
     * @return array<string, mixed>
     */
    private function choix(Entreprise $entreprise): array
    {
        return session("souscription.{$entreprise->id}", []);
    }

    private function memoriser(Entreprise $entreprise, array $valeurs): void
    {
        session(["souscription.{$entreprise->id}" => array_merge($this->choix($entreprise), $valeurs)]);
    }

    /**
     * Ce que l'étape a besoin d'afficher.
     *
     * @return array<string, mixed>
     */
    private function donneesDeLEtape(int $etape, Entreprise $entreprise): array
    {
        $choix = $this->choix($entreprise);

        return match ($etape) {
            1 => ['categories' => Categorie::withCount('profils')->orderBy('ordre')->get()],

            2 => ['profils' => Profil::withCount(['familles', 'articles'])
                    ->when(isset($choix['categorie_id']), fn ($q) => $q->where('categorie_id', $choix['categorie_id']))
                    ->orderBy('nom')->get()],

            3 => ['modulesProposes' => $this->modulesProposes($choix['profils'] ?? []),
                  'modulesAutorises' => $entreprise->modulesAutorises()],

            4 => ['familles' => Famille::with(['typeArticle', 'profil'])
                    ->whereHas('profil', fn ($q) => $q->whereIn('code', $choix['profils'] ?? []))
                    ->withCount('articles')
                    ->orderBy('profil_id')->orderBy('code')->get()],

            // Le stock d'ouverture se pose sur un point de vente : sans site
            // enregistré, la colonne n'aurait nulle part où écrire, et le champ
            // ne ferait qu'avaler la saisie. On la retire plutôt que de mentir.
            5 => ['articles' => Produit::with(['categorieRelation', 'stocks'])
                    ->where('entreprise_id', $entreprise->id)
                    ->orderBy('reference')->get(),
                  'siteDuStock' => $this->siteDuStock($entreprise)],

            default => [],
        };
    }

    /**
     * Modules que les profils choisis ouvrent, plus le socle commun.
     *
     * @param  array<int, string>  $codesProfils
     * @return array<int, string>
     */
    private function modulesProposes(array $codesProfils): array
    {
        $modules = Entreprise::MODULES_SOCLE;

        foreach (Profil::whereIn('code', $codesProfils)->get() as $profil) {
            $modules = array_merge($modules, $profil->modulesOuverts());
        }

        return array_values(array_unique($modules));
    }
}
