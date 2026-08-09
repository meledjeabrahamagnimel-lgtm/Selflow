<?php

namespace App\Modules\Admin\Controleurs;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\FicheTechnique;
use App\Modules\Admin\Modeles\FicheTechniqueDetail;
use App\Modules\Admin\Modeles\OrdreProduction;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Admin\Modeles\PointDeVente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Modules\Admin\Regles\Appartenance;
use App\Modules\Admin\Services\ImputationService;
use App\Modules\Admin\Services\StockService;

class ProductionControleur extends Controller
{
    /**
     * Liste des fiches techniques.
     */
    public function indexFichesTechniques(Request $request): View
    {
        $entrepriseId = Auth::user()->entreprise_id;
        
        $query = FicheTechnique::with(['produitFini', 'details.ingredient'])
            ->where('entreprise_id', $entrepriseId);

        if ($request->filled('recherche')) {
            $recherche = $request->recherche;
            $query->whereHas('produitFini', function ($q) use ($recherche) {
                $q->where('nom', 'LIKE', '%' . $recherche . '%')
                  ->orWhere('reference', 'LIKE', '%' . $recherche . '%');
            });
        }

        $fiches = $query->latest()->paginate(15);

        return view('admin::production.fiches.index', compact('fiches'));
    }

    /**
     * Formulaire de création de fiche technique.
     */
    public function creerFicheTechnique(): View
    {
        $entrepriseId = Auth::user()->entreprise_id;

        // Récupérer les produits finis qui n'ont pas encore de fiche technique
        $produitsFini = Produit::where('entreprise_id', $entrepriseId)
            ->where('type', 'produit_fini')
            ->whereDoesntHave('ficheTechnique')
            ->orderBy('nom')
            ->get();

        // Récupérer uniquement les ingrédients de type matière première
        $ingredients = Produit::where('entreprise_id', $entrepriseId)
            ->where('type', 'matiere_premiere')
            ->orderBy('nom')
            ->get();

        return view('admin::production.fiches.fiche', [
            'fiche' => new FicheTechnique(),
            'produitsFini' => $produitsFini,
            'ingredients' => $ingredients,
            'mode' => 'creation'
        ]);
    }

    /**
     * Enregistrer une nouvelle fiche technique.
     */
    public function enregistrerFicheTechnique(Request $request): RedirectResponse
    {
        $entrepriseId = Auth::user()->entreprise_id;

        $request->validate([
            'produit_fini_id' => [$request->filled('nouveau_produit_fini_nom') ? 'nullable' : 'required', 'integer', Appartenance::a('produits', 'id')],
            'nouveau_produit_fini_nom' => ['nullable', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'ingredients'     => ['required', 'array', 'min:1'],
            'ingredients.*.ingredient_id' => ['required', 'integer', Appartenance::a('produits', 'id')],
            'ingredients.*.quantite'      => ['required', 'numeric', 'min:0.0001'],
            'ingredients.*.unite'         => ['required', 'string', 'max:50'],
        ]);

        $produitFiniId = null;

        if ($request->filled('nouveau_produit_fini_nom')) {
            $nomNouveau = trim($request->nouveau_produit_fini_nom);
            
            // Chercher s'il existe déjà un produit du même nom
            $existant = Produit::where('entreprise_id', $entrepriseId)
                ->where('nom', $nomNouveau)
                ->first();
                
            if ($existant) {
                $produitFiniId = $existant->id;
            } else {
                // Créer le produit fini
                $nouveauProduit = Produit::create([
                    'entreprise_id' => $entrepriseId,
                    'nom'           => $nomNouveau,
                    'type'          => 'produit_fini',
                    'prix_achat'    => 0,
                    'prix_vente'    => 0,
                    'taux_tva'      => 18.0,
                    'unite'         => 'Unité',
                    'statut'        => 'actif',
                ]);
                $produitFiniId = $nouveauProduit->id;
                
                // Initialiser le stock à 0 sur tous les points de vente.
                //
                // La colonne s'appelle `quantite_disponible` ; `quantite`
                // n'existe pas et n'est pas dans `$fillable`, si bien que la
                // valeur passait à la trappe sans que rien ne le signale.
                foreach (PointDeVente::where('entreprise_id', $entrepriseId)->get() as $pdv) {
                    Stock::create([
                        'point_de_vente_id'   => $pdv->id,
                        'produit_id'          => $nouveauProduit->id,
                        'quantite_disponible' => 0,
                    ]);
                }
            }
        } else {
            $produitFiniId = $request->produit_fini_id;
        }

        // S'assurer que le produit fini n'a pas déjà de fiche technique
        $dejaExiste = FicheTechnique::where('entreprise_id', $entrepriseId)
            ->where('produit_fini_id', $produitFiniId)
            ->exists();

        if ($dejaExiste) {
            return back()->with('erreur', 'Ce produit possède déjà une fiche technique. Veuillez la modifier plutôt.');
        }

        DB::transaction(function () use ($request, $entrepriseId, $produitFiniId) {
            $fiche = FicheTechnique::create([
                'entreprise_id'   => $entrepriseId,
                'produit_fini_id' => $produitFiniId,
                'description'     => $request->description,
            ]);

            foreach ($request->ingredients as $ing) {
                FicheTechniqueDetail::create([
                    'fiche_technique_id' => $fiche->id,
                    'ingredient_id'      => $ing['ingredient_id'],
                    'quantite'           => $ing['quantite'],
                    'unite'              => $ing['unite'],
                ]);
            }
        });

        return redirect()->route('admin.production.fiches_techniques.index')
            ->with('succes', 'Fiche technique enregistrée avec succès !');
    }

    /**
     * Formulaire de modification de fiche technique.
     */
    public function modifierFicheTechnique(FicheTechnique $fiche): View
    {
        $entrepriseId = Auth::user()->entreprise_id;
        abort_unless($fiche->entreprise_id === $entrepriseId, 404);

        $fiche->load(['details.ingredient', 'produitFini']);

        // Le produit fini de la fiche actuelle
        $produitsFini = Produit::where('id', $fiche->produit_fini_id)->get();

        // Récupérer uniquement les ingrédients de type matière première
        $ingredients = Produit::where('entreprise_id', $entrepriseId)
            ->where('type', 'matiere_premiere')
            ->orderBy('nom')
            ->get();

        return view('admin::production.fiches.fiche', [
            'fiche' => $fiche,
            'produitsFini' => $produitsFini,
            'ingredients' => $ingredients,
            'mode' => 'edition'
        ]);
    }

    /**
     * Enregistrer les modifications de la fiche technique.
     */
    public function enregistrerModificationFicheTechnique(Request $request, FicheTechnique $fiche): RedirectResponse
    {
        $entrepriseId = Auth::user()->entreprise_id;
        abort_unless($fiche->entreprise_id === $entrepriseId, 404);

        $request->validate([
            'description' => ['nullable', 'string'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.ingredient_id' => ['required', 'integer', Appartenance::a('produits', 'id')],
            'ingredients.*.quantite'      => ['required', 'numeric', 'min:0.0001'],
            'ingredients.*.unite'         => ['required', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($request, $fiche) {
            $fiche->update([
                'description' => $request->description,
            ]);

            // Recréer les détails
            $fiche->details()->delete();

            foreach ($request->ingredients as $ing) {
                FicheTechniqueDetail::create([
                    'fiche_technique_id' => $fiche->id,
                    'ingredient_id'      => $ing['ingredient_id'],
                    'quantite'           => $ing['quantite'],
                    'unite'              => $ing['unite'],
                ]);
            }
        });

        return redirect()->route('admin.production.fiches_techniques.index')
            ->with('succes', 'Fiche technique mise à jour avec succès !');
    }

    /**
     * Supprimer une fiche technique.
     */
    public function supprimerFicheTechnique(FicheTechnique $fiche): RedirectResponse
    {
        abort_unless($fiche->entreprise_id === Auth::user()->entreprise_id, 404);
        $fiche->delete();

        return redirect()->route('admin.production.fiches_techniques.index')
            ->with('succes', 'Fiche technique supprimée avec succès !');
    }

    /**
     * Liste des ordres de production (OP).
     */
    public function indexOrdres(Request $request): View
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $pointDeVenteId = session('point_de_vente_actif_id');

        $query = OrdreProduction::with(['produitFini', 'pointDeVente'])
            ->where('entreprise_id', $entrepriseId);

        if ($pointDeVenteId) {
            $query->where('point_de_vente_id', $pointDeVenteId);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $ordres = $query->latest()->paginate(15);
        $pdvs = PointDeVente::where('entreprise_id', $entrepriseId)->get();

        return view('admin::production.ordres.index', compact('ordres', 'pdvs'));
    }

    /**
     * Formulaire de création d'ordre de production.
     */
    public function creerOrdre(): View
    {
        $entrepriseId = Auth::user()->entreprise_id;

        // Récupérer les produits finis ayant une fiche technique, avec fiches et stocks ingrédients
        $produitsFini = Produit::with(['ficheTechnique.details.ingredient.stocks'])
            ->where('entreprise_id', $entrepriseId)
            ->where('type', 'produit_fini')
            ->whereHas('ficheTechnique')
            ->orderBy('nom')
            ->get();

        $pdvs = PointDeVente::where('entreprise_id', $entrepriseId)->get();

        return view('admin::production.ordres.creer', compact('produitsFini', 'pdvs'));
    }

    /**
     * Enregistrer un ordre de production.
     */
    public function enregistrerOrdre(Request $request): RedirectResponse
    {
        $entrepriseId = Auth::user()->entreprise_id;

        $request->validate([
            'produit_fini_id'   => ['required', 'integer', Appartenance::a('produits', 'id')],
            'point_de_vente_id' => ['required', 'integer', Appartenance::a('points_de_vente', 'id')],
            'quantite_cible'    => ['required', 'numeric', 'min:0.0001'],
            'date_production'   => ['required', 'date'],
        ]);

        $code = OrdreProduction::genererCode($entrepriseId);

        OrdreProduction::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id' => $request->point_de_vente_id,
            'produit_fini_id'   => $request->produit_fini_id,
            'code_ordre'        => $code,
            'quantite_cible'    => $request->quantite_cible,
            'statut'            => 'Brouillon',
            'date_production'   => $request->date_production,
        ]);

        return redirect()->route('admin.production.ordres.index')
            ->with('succes', 'Ordre de production créé avec succès !');
    }

    /**
     * Valider et exécuter un ordre de production (validation atomique de stock).
     */
    public function validerOrdre(OrdreProduction $ordre): RedirectResponse
    {
        $entrepriseId = Auth::user()->entreprise_id;
        abort_unless($ordre->entreprise_id === $entrepriseId, 404);

        if ($ordre->statut === 'Terminé') {
            return back()->with('info', 'Cet ordre de production est déjà terminé.');
        }

        $fiche = FicheTechnique::where('produit_fini_id', $ordre->produit_fini_id)
            ->where('entreprise_id', $entrepriseId)
            ->first();

        if (!$fiche) {
            return back()->with('erreur', 'Aucune fiche technique trouvée pour ce produit fini.');
        }

        // 1. Contrôle strict de stock serveur pour tous les ingrédients
        $erreurs = [];
        $besoins = [];

        foreach ($fiche->details as $d) {
            $besoin = $d->quantite * $ordre->quantite_cible;
            $dispo = $d->ingredient->stockActuel($ordre->point_de_vente_id);
            $besoins[] = [
                'ingredient' => $d->ingredient,
                'quantite'   => $besoin
            ];

            if ($dispo < $besoin) {
                $erreurs[] = "Le stock de l'ingrédient {$d->ingredient->nom} est insuffisant (Disponible: {$dispo} {$d->unite}, Requis: {$besoin} {$d->unite}).";
            }
        }

        if (!empty($erreurs)) {
            return back()->with('erreurs_validation', $erreurs)
                ->with('erreur', 'Validation annulée en raison d\'un stock insuffisant.');
        }

        // 1 bis. Le cas où la comptabilité ressortirait de travers.
        //
        // Chaque mouvement écrit sa paire équilibrée, ou n'écrit rien. Une
        // fabrication dont les matières sont imputées mais dont le produit fini
        // ne l'est pas reste donc équilibrée au bilan — et **fausse au compte
        // de résultat** : les matières partent en charge, et rien n'entre en
        // face. La marge de l'atelier apparaît en perte sèche, et l'écart ne se
        // voit qu'à la révision.
        //
        // Le contrôle ne se déclenche que dans ce cas précis. Un atelier qui n'a
        // rien paramétré du tout ne tient simplement pas d'inventaire permanent,
        // et rien ne l'y oblige.
        $matieresImputees = collect($besoins)->contains(
            fn ($b) => ImputationService::peutTenirLInventairePermanent($b['ingredient'])
        );

        if ($matieresImputees && !ImputationService::peutTenirLInventairePermanent($ordre->produitFini)) {
            $manque = implode(', ', ImputationService::manqueUnCompte($ordre->produitFini));

            return back()->with('erreur',
                "Les matières de cet ordre s'imputent en comptabilité, mais pas le produit fini "
                . "« {$ordre->produitFini->nom} » : il lui manque le {$manque}. La fabrication "
                . 'apparaîtrait en perte sèche. Renseignez les comptes sur sa fiche ou sur son '
                . 'rayon avant de valider.');
        }

        // 2. Transaction SQL atomique
        DB::transaction(function () use ($ordre, $besoins) {
            // Ce que la fabrication a réellement coûté, matière par matière.
            // C'est le CUMP (Coût Unitaire Moyen Pondéré) de chaque sortie qui
            // le dit — non `prix_achat`, qui est un prix de catalogue figé.
            $coutMatieres = 0.0;

            foreach ($besoins as $b) {
                $sortie = StockService::sortie(
                    $b['ingredient'], (int) $ordre->point_de_vente_id, (float) $b['quantite'],
                    MouvementStock::PRODUCTION_CONSOMMATION,
                    ['piece' => $ordre, 'reference' => $ordre->code_ordre]
                );

                if ($sortie) {
                    $coutMatieres += (float) $sortie->quantite * (float) ($sortie->cout_unitaire ?? 0);
                }
            }

            $produitFini = $ordre->produitFini;
            $qtyFini = (float) $ordre->quantite_cible;

            // **Le produit fini entre au coût de ce qui l'a fabriqué.** Il
            // entrait jusqu'ici à son propre `prix_achat` — le prix d'achat
            // d'une chose qu'on ne rachète pas, presque toujours nul ou faux.
            // La production apparaissait alors en perte sèche : les matières
            // sortaient en charge, et rien n'entrait en face.
            $coutUnitaire = $qtyFini > 0 ? round($coutMatieres / $qtyFini, Stock::DECIMALES_COUT) : 0.0;

            // « Entree » sans accent partait ici : l'ecran des mouvements
            // compare la chaine exacte, et une entree de production s'affichait
            // en rouge, precedee d'un signe moins. Le service pose la constante.
            //
            // **Aucune écriture comptable n'est appelée ici.** Depuis le lot
            // 4.2, la porte unique du stock écrit elle-même l'inventaire
            // permanent : `ComptabiliteService::genererEcritureProduction()`
            // doublait chaque consommation et chaque entrée, et le coût de
            // production ressortait au double.
            StockService::entree($produitFini, (int) $ordre->point_de_vente_id, $qtyFini,
                MouvementStock::PRODUCTION_ENTREE,
                ['piece' => $ordre, 'reference' => $ordre->code_ordre,
                 'cout_unitaire' => $coutUnitaire]);

            $ordre->update(['statut' => 'Terminé']);
        });

        return redirect()->route('admin.production.ordres.index')
            ->with('succes', "L'ordre de production {$ordre->code_ordre} a été validé et le stock mis à jour !");
    }
}
