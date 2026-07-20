<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\ProduitDetailLibre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProduitControleur
{
    public function index(): View
    {
        $entreprise = Auth::user()->entreprise;
        $produits   = Produit::where('entreprise_id', $entreprise->id)
            ->actifs()
            ->with(['category', 'sousCategorieRelation', 'stocks'])
            ->orderBy('nom')
            ->paginate(24);

        $produits_archives = Produit::where('entreprise_id', $entreprise->id)
            ->archives()
            ->with(['category'])
            ->orderBy('nom')
            ->paginate(24, ['*'], 'page_archives');

        $comptes = \App\Modules\Admin\Modeles\PlanComptable::whereNull('entreprise_id')
            ->orWhere('entreprise_id', $entreprise->id)
            ->orderBy('numero')
            ->get();

        $categories = \App\Modules\Admin\Modeles\Categorie::where('entreprise_id', $entreprise->id)
            ->with('sousCategories')
            ->orderBy('nom')
            ->get();

        $syscohadaKws = \App\Modules\Admin\Modeles\CategorieSyscohada::where(function($q) use ($entreprise) {
                $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
            })
            ->orderBy('libelle_affiche')
            ->get();

        return view('admin::produits.index', compact(
            'produits', 'produits_archives', 'comptes', 'categories', 'syscohadaKws'
        ));
    }

    public function creer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'           => ['required', 'string', 'max:200'],
            'type'          => ['required', 'string', 'in:marchandise,matiere_premiere,produit_fini,consommable_stockable,consommable_non_stockable,service'],
            'categorie_id'  => ['nullable', 'string'],
            'sous_categorie_id' => ['nullable', 'string'],
            'nouvelle_categorie' => ['nullable', 'string', 'max:100'],
            'prefixe_categorie' => ['nullable', 'string', 'max:5'],
            'nouvelle_sous_categorie' => ['nullable', 'string', 'max:100'],
            'prix_achat'    => ['required', 'numeric', 'min:0'],
            'prix_vente'    => ['required', 'numeric', 'min:0'],
            'taux_tva'      => ['required', 'numeric', 'min:0'],
            'compte_vente'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
            'compte_achat'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
            'stock_actuel'  => ['required', 'integer', 'min:0'],
            'stock_minimum' => ['required', 'integer', 'min:0'],
            'unite'         => ['nullable', 'string', 'max:20'],
        ]);

        $categorieId = $request->input('categorie_id');
        if ($categorieId === 'nouvelle' && $request->filled('nouvelle_categorie')) {
            $prefixe = strtoupper(trim($request->input('prefixe_categorie')));
            if (empty($prefixe)) {
                $prefixe = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', trim($request->input('nouvelle_categorie'))), 0, 4));
            }
            if (empty($prefixe)) {
                $prefixe = 'PROD';
            }
            
            // Unicité du préfixe
            $prefixeOriginal = $prefixe;
            $compteur = 1;
            while (\App\Modules\Admin\Modeles\Categorie::where('entreprise_id', $entreprise->id)->where('prefixe', $prefixe)->exists()) {
                $prefixe = substr($prefixeOriginal, 0, 3) . $compteur;
                $compteur++;
            }

            $categorie = \App\Modules\Admin\Modeles\Categorie::create([
                'entreprise_id' => $entreprise->id,
                'nom'           => trim($request->input('nouvelle_categorie')),
                'prefixe'       => $prefixe,
            ]);
            $categorieId = $categorie->id;
        }

        $sousCategorieId = $request->input('sous_categorie_id');
        if ($sousCategorieId === 'nouvelle' && $request->filled('nouvelle_sous_categorie') && $categorieId) {
            $sousCategorie = \App\Modules\Admin\Modeles\SousCategorie::create([
                'categorie_id' => $categorieId,
                'nom'          => trim($request->input('nouvelle_sous_categorie')),
            ]);
            $sousCategorieId = $sousCategorie->id;
        } elseif ($sousCategorieId === 'nouvelle' || empty($sousCategorieId)) {
            $sousCategorieId = null;
        }

        $produit = Produit::create([
            'entreprise_id'     => $entreprise->id,
            'nom'               => $request->nom,
            'type'              => $request->type,
            'categorie_id'      => $categorieId ?: null,
            'sous_categorie_id' => $sousCategorieId ?: null,
            'prix_achat'        => $request->prix_achat,
            'prix_vente'        => $request->prix_vente,
            'taux_tva'          => $request->taux_tva,
            'compte_vente'      => $request->compte_vente,
            'compte_achat'      => $request->compte_achat,
            'unite'             => $request->unite,
        ]);

        // Initialisation automatique des stocks par point de vente
        $pdvs = $entreprise->pointsDeVente;
        $defaultPdvId = session('point_de_vente_actif_id') 
            ?? auth()->user()->point_de_vente_id 
            ?? ($pdvs->first()->id ?? null);

        foreach ($pdvs as $pdv) {
            $isDefault = ($pdv->id == $defaultPdvId);
            \App\Modules\Admin\Modeles\Stock::create([
                'produit_id'          => $produit->id,
                'point_de_vente_id'   => $pdv->id,
                'quantite_disponible' => $isDefault ? $request->input('stock_actuel', 0) : 0,
                'stock_minimum'       => $isDefault ? $request->input('stock_minimum', 5) : 5,
                'stock_maximum'       => 100,
            ]);
        }

        return back()->with('succes', 'Produit ajouté au catalogue avec succès. Référence générée : ' . $produit->reference);
    }

    /**
     * Basculer le statut d'un produit entre actif et archivé.
     */
    public function archiver(Produit $produit): RedirectResponse
    {
        abort_unless($produit->entreprise_id === Auth::user()->entreprise_id, 403);

        $produit->update([
            'statut' => $produit->statut === 'actif' ? 'archive' : 'actif',
        ]);

        $msg = $produit->statut === 'archive'
            ? 'Produit archivé avec succès.'
            : 'Produit restauré dans le catalogue.'
        ;

        return back()->with('succes', $msg);
    }

    /**
     * Upload photo depuis l'interface web ou l'API mobile.
     * Retourne JSON pour l'API, redirect pour le web.
     */
    public function uploaderPhoto(Request $request, Produit $produit): JsonResponse
    {
        abort_unless($produit->entreprise_id === Auth::user()->entreprise_id, 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        // Supprimer l'ancienne photo si elle existe
        if ($produit->photo) {
            Storage::disk('public')->delete($produit->photo);
        }

        $extension = $request->file('photo')->extension();
        $chemin    = 'produits/' . Str::uuid() . '.' . $extension;
        $request->file('photo')->storeAs('', $chemin, 'public');

        $produit->update(['photo' => $chemin]);

        return response()->json([
            'success'   => true,
            'photo_url' => $produit->photo_url,
        ]);
    }

    public function modifier(Request $request, Produit $produit): RedirectResponse
    {
        abort_unless($produit->entreprise_id === Auth::user()->entreprise_id, 403);
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'           => ['required', 'string', 'max:200'],
            'type'          => ['required', 'string', 'in:marchandise,matiere_premiere,produit_fini,consommable_stockable,consommable_non_stockable,service'],
            'categorie_id'  => ['nullable', 'string'],
            'sous_categorie_id' => ['nullable', 'string'],
            'nouvelle_categorie' => ['nullable', 'string', 'max:100'],
            'prefixe_categorie' => ['nullable', 'string', 'max:5'],
            'nouvelle_sous_categorie' => ['nullable', 'string', 'max:100'],
            'prix_achat'    => ['required', 'numeric', 'min:0'],
            'prix_vente'    => ['required', 'numeric', 'min:0'],
            'taux_tva'      => ['required', 'numeric', 'min:0'],
            'compte_vente'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
            'compte_achat'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
            'stock_actuel'  => ['required', 'integer'],
            'stock_minimum' => ['required', 'integer', 'min:0'],
            'unite'         => ['nullable', 'string', 'max:20'],
        ]);

        $categorieId = $request->input('categorie_id');
        if ($categorieId === 'nouvelle' && $request->filled('nouvelle_categorie')) {
            $prefixe = strtoupper(trim($request->input('prefixe_categorie')));
            if (empty($prefixe)) {
                $prefixe = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', trim($request->input('nouvelle_categorie'))), 0, 4));
            }
            if (empty($prefixe)) {
                $prefixe = 'PROD';
            }
            
            // Unicité du préfixe
            $prefixeOriginal = $prefixe;
            $compteur = 1;
            while (\App\Modules\Admin\Modeles\Categorie::where('entreprise_id', $entreprise->id)->where('prefixe', $prefixe)->exists()) {
                $prefixe = substr($prefixeOriginal, 0, 3) . $compteur;
                $compteur++;
            }

            $categorie = \App\Modules\Admin\Modeles\Categorie::create([
                'entreprise_id' => $entreprise->id,
                'nom'           => trim($request->input('nouvelle_categorie')),
                'prefixe'       => $prefixe,
            ]);
            $categorieId = $categorie->id;
        }

        $sousCategorieId = $request->input('sous_categorie_id');
        if ($sousCategorieId === 'nouvelle' && $request->filled('nouvelle_sous_categorie') && $categorieId) {
            $sousCategorie = \App\Modules\Admin\Modeles\SousCategorie::create([
                'categorie_id' => $categorieId,
                'nom'          => trim($request->input('nouvelle_sous_categorie')),
            ]);
            $sousCategorieId = $sousCategorie->id;
        } elseif ($sousCategorieId === 'nouvelle' || empty($sousCategorieId)) {
            $sousCategorieId = null;
        }

        // Si la catégorie change et qu'aucune référence n'existe ou si on veut la recalculer
        // Notons que si le produit change de catégorie, on peut éventuellement générer une nouvelle référence, 
        // mais pour la traçabilité il vaut mieux garder l'originale à moins que l'utilisateur le souhaite.
        // La spécification demande "génération automatique et unique basée sur la catégorie" à la création.
        // Restons fidèles à la création pour garder la cohérence historique.

        $produit->update([
            'nom'               => $request->nom,
            'type'              => $request->type,
            'categorie_id'      => $categorieId ?: null,
            'sous_categorie_id' => $sousCategorieId ?: null,
            'prix_achat'        => $request->prix_achat,
            'prix_vente'        => $request->prix_vente,
            'taux_tva'          => $request->taux_tva,
            'compte_vente'      => $request->compte_vente,
            'compte_achat'      => $request->compte_achat,
            'unite'             => $request->unite,
        ]);

        $pdvId = session('point_de_vente_actif_id') 
            ?? auth()->user()->point_de_vente_id 
            ?? ($entreprise->pointsDeVente()->first()->id ?? null);

        if ($pdvId) {
            \App\Modules\Admin\Modeles\Stock::updateOrCreate([
                'produit_id'        => $produit->id,
                'point_de_vente_id' => $pdvId,
            ], [
                'quantite_disponible' => $request->input('stock_actuel', 0),
                'stock_minimum'       => $request->input('stock_minimum', 5),
            ]);
        }

        return back()->with('succes', 'Produit mis à jour avec succès.');
    }

    /**
     * Page fiche détaillée d'un produit.
     */
    public function fiche(Produit $produit): View
    {
        abort_unless($produit->entreprise_id === Auth::user()->entreprise_id, 403);

        $produit->load(['category', 'sousCategorieRelation', 'stocks.pointDeVente', 'detailsLibres']);

        return view('admin::produits.fiche', compact('produit'));
    }

    /**
     * Modifier la description inventaire uniquement.
     */
    public function description(Request $request, Produit $produit): RedirectResponse
    {
        abort_unless($produit->entreprise_id === Auth::user()->entreprise_id, 403);

        $request->validate([
            'description_inventaire' => ['nullable', 'string', 'max:5000'],
        ]);

        $produit->update(['description_inventaire' => $request->input('description_inventaire')]);

        return back()->with('succes', 'Description inventaire mise à jour.');
    }

    /**
     * Ajouter un ou plusieurs détails libres.
     */
    public function ajouterDetails(Request $request, Produit $produit): RedirectResponse
    {
        abort_unless($produit->entreprise_id === Auth::user()->entreprise_id, 403);

        $request->validate([
            'details'             => ['required', 'array', 'min:1'],
            'details.*.titre'     => ['required', 'string', 'max:150'],
            'details.*.description' => ['nullable', 'string', 'max:2000'],
        ]);

        $ordre = $produit->detailsLibres()->max('ordre') ?? 0;

        foreach ($request->input('details', []) as $d) {
            if (!empty($d['titre'])) {
                ProduitDetailLibre::create([
                    'produit_id'  => $produit->id,
                    'titre'       => $d['titre'],
                    'description' => $d['description'] ?? '',
                    'ordre'       => ++$ordre,
                ]);
            }
        }

        return back()->with('succes', 'Détail(s) libre(s) ajouté(s) avec succès.');
    }

    /**
     * Supprimer un détail libre.
     */
    public function supprimerDetail(ProduitDetailLibre $detail): RedirectResponse
    {
        abort_unless(
            Produit::where('id', $detail->produit_id)
                ->where('entreprise_id', Auth::user()->entreprise_id)
                ->exists(),
            403
        );

        $detail->delete();

        return back()->with('succes', 'Détail supprimé.');
    }
}
