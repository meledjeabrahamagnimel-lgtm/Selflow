<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\TransfertStock;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockControleur
{
    public function index(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;
        
        // Liste de tous les points de vente
        $pointsDeVente = $entreprise->pointsDeVente()->orderBy('nom')->get();

        // Récupérer le point de vente à filtrer (session, requête ou par défaut 'tout')
        $pointDeVenteId = $request->input('point_de_vente_id') ?? $request->input('site_id');
        if ($pointDeVenteId === null) {
            $pointDeVenteId = session('point_de_vente_actif_id') 
                ?? Auth::user()->point_de_vente_id 
                ?? 'tout';
        }

        $query = Produit::where('produits.entreprise_id', $entreprise->id)
            ->with(['stocks']);

        $produits = $query
            ->leftJoin('categories', 'produits.categorie_id', '=', 'categories.id')
            ->select('produits.*')
            ->orderBy('categories.nom')
            ->orderBy('produits.nom')
            ->get();

        // Surcharger stock_actuel et stock_minimum pour la compatibilité avec la vue
        foreach ($produits as $p) {
            if ($pointDeVenteId === 'tout') {
                $totalStock = $p->stocks->sum('quantite_disponible');
                $totalMin = $p->stocks->sum('stock_minimum');
                $p->setAttribute('stock_actuel', $totalStock);
                $p->setAttribute('stock_minimum', $totalMin);
            } else {
                $stockObj = $p->stocks->where('point_de_vente_id', $pointDeVenteId)->first();
                $p->setAttribute('stock_actuel', $stockObj ? $stockObj->quantite_disponible : 0);
                $p->setAttribute('stock_minimum', $stockObj ? $stockObj->stock_minimum : 5);
            }
        }

        $categories = $produits->pluck('categorie')->unique()->sort()->values();

        // Compteurs interactifs Odoo filtrés par point de vente actif
        $qReceptions = Achat::whereIn('etape', ['Bon de commande', 'Facture'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->whereHas('details', function($q) {
                $q->whereColumn('quantite', '>', 'quantite_receptionnee');
            });

        $qLivraisons = Vente::whereIn('etape', ['Bon de commande', 'Facture'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->whereHas('details', function($q) {
                $q->whereColumn('quantite', '>', 'quantite_livree');
            });

        $qTransferts = TransfertStock::enAttente()
            ->whereHas('produit', fn($q) => $q->where('entreprise_id', $entreprise->id));

        if ($pointDeVenteId !== 'tout') {
            $qReceptions->where('point_de_vente_id', $pointDeVenteId);
            $qLivraisons->where('point_de_vente_id', $pointDeVenteId);
            $qTransferts->where(function($q) use ($pointDeVenteId) {
                $q->where('point_de_vente_source_id', $pointDeVenteId)
                  ->orWhere('point_de_vente_destination_id', $pointDeVenteId);
            });
        }

        $receptionsATraiter = $qReceptions->count();
        $livraisonsATraiter = $qLivraisons->count();
        $transfertsATraiter = $qTransferts->count();

        return view('admin::stock.index', compact(
            'produits', 
            'categories', 
            'pointsDeVente', 
            'pointDeVenteId',
            'receptionsATraiter',
            'livraisonsATraiter',
            'transfertsATraiter'
        ));
    }

    public function mouvements(Request $request): View
    {
        $entreprise  = Auth::user()->entreprise;
        $pointDeVenteId = Auth::user()->estCaissier()
            ? Auth::user()->point_de_vente_id
            : session('point_de_vente_actif_id');

        $section = $request->input('section', 'tous');

        $query = MouvementStock::with([
            'produit', 'pointDeVente', 'pointDeVenteSource', 'utilisateur', 'fournisseur', 'client'
        ]);

        if ($pointDeVenteId) {
            $query->where('point_de_vente_id', $pointDeVenteId);
        } else {
            $query->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
        }

        // Filtrer selon la section/sous-type
        if ($section === 'achats') {
            $query->where('sous_type', 'Reception');
        } elseif ($section === 'ventes') {
            $query->where('sous_type', 'Livraison');
        } elseif ($section === 'transferts') {
            $query->where('sous_type', 'Transfert');
        } elseif ($section === 'rebuts') {
            $query->where('sous_type', 'Rebut');
        }

        $mouvements = $query->latest()->paginate(30);

        return view('admin::stock.mouvements', compact('mouvements', 'section'));
    }

    /**
     * Liste des produits périmés ou proches de la péremption (Page Rebut).
     */
    public function rebut(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = session('point_de_vente_actif_id') 
            ?? Auth::user()->point_de_vente_id 
            ?? 'tout';

        // Produits avec date de péremption définie
        $query = Produit::where('entreprise_id', $entreprise->id)
            ->whereNotNull('date_peremption')
            ->with(['stocks']);

        $produits = $query->get();

        // Séparer les produits en deux collections : périmés et bientôt périmés (< 30 jours)
        $perimes = collect();
        $proches = collect();

        foreach ($produits as $p) {
            // Surcharger le stock disponible pour le site actif
            if ($pointDeVenteId === 'tout') {
                $p->setAttribute('stock_actuel', $p->stocks->sum('quantite_disponible'));
            } else {
                $stockObj = $p->stocks->where('point_de_vente_id', $pointDeVenteId)->first();
                $p->setAttribute('stock_actuel', $stockObj ? $stockObj->quantite_disponible : 0);
            }

            if ($p->estPerime()) {
                $perimes->push($p);
            } elseif ($p->bientotPerime(30)) {
                $proches->push($p);
            }
        }

        return view('admin::stock.rebut', compact('perimes', 'proches', 'pointDeVenteId'));
    }

    /**
     * Action de retrait d'un produit périmé (Rebut).
     */
    public function retirerRebut(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'produit_id' => ['required', 'integer', 'exists:produits,id'],
            'quantite'   => ['required', 'integer', 'min:1'],
        ]);

        $produit = Produit::findOrFail($request->produit_id);
        abort_unless($produit->entreprise_id === Auth::user()->entreprise_id, 403);

        $pointDeVenteId = session('point_de_vente_actif_id') 
            ?? Auth::user()->point_de_vente_id;

        if (!$pointDeVenteId || $pointDeVenteId === 'tout') {
            return back()->with('erreur', 'Veuillez sélectionner un point de vente spécifique pour effectuer un retrait.');
        }

        $stockObj = \App\Modules\Admin\Modeles\Stock::where('produit_id', $produit->id)
            ->where('point_de_vente_id', $pointDeVenteId)
            ->first();

        $stockDisponible = $stockObj ? $stockObj->quantite_disponible : 0;
        $quantiteRetrait = intval($request->quantite);

        if ($quantiteRetrait > $stockDisponible) {
            return back()->with('erreur', "Quantité insuffisante en stock (Disponible: $stockDisponible).");
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($produit, $pointDeVenteId, $stockDisponible, $quantiteRetrait) {
            // Décrémenter le stock
            $produit->decrementStock($pointDeVenteId, $quantiteRetrait);

            // Mouvement de type Rebut
            MouvementStock::create([
                'produit_id'         => $produit->id,
                'point_de_vente_id'  => $pointDeVenteId,
                'type_mouvement'     => 'Sortie',
                'sous_type'          => 'Rebut',
                'quantite'           => $quantiteRetrait,
                'stock_avant'        => $stockDisponible,
                'stock_apres'        => $stockDisponible - $quantiteRetrait,
                'utilisateur_id'     => Auth::id(),
                'reference_document' => 'REBUT-' . now()->format('YmdHis'),
            ]);
        });

        // Journaliser
        \App\Modules\Admin\Modeles\JournalAudit::create([
            'entreprise_id'  => $produit->entreprise_id,
            'utilisateur_id' => Auth::id(),
            'action'         => 'retrait_rebut',
            'details'        => "Retrait de {$quantiteRetrait} {$produit->unite} de {$produit->nom} pour rebut",
        ]);

        return back()->with('succes', 'Le produit a été retiré du stock avec succès.');
    }

    /**
     * Liste des réceptions fournisseur en attente (Flux Réception).
     */
    public function receptions(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;

        $achats = Achat::with(['fournisseur', 'pointDeVente', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->whereIn('etape', ['Bon de commande', 'Facture'])
            ->whereHas('details', function ($q) {
                $q->whereColumn('quantite', '>', 'quantite_receptionnee');
            })
            ->latest()
            ->paginate(20);

        return view('admin::stock.receptions', compact('achats'));
    }

    /**
     * Fiche de réception fournisseur.
     */
    public function ficheReception(Achat $achat): View
    {
        abort_unless($achat->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        $achat->load(['fournisseur', 'pointDeVente', 'details.produit']);

        return view('admin::stock.reception_fiche', compact('achat'));
    }

    /**
     * Valider la réception physique d'une commande d'achat.
     */
    public function validerReception(Request $request, Achat $achat): \Illuminate\Http\RedirectResponse
    {
        abort_unless($achat->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        
        $request->validate([
            'reception'   => ['required', 'array', 'min:1'],
            'reception.*' => ['required', 'integer', 'min:0'],
        ]);

        $pointDeVenteId = $achat->point_de_vente_id;

        DB::transaction(function () use ($request, $achat, $pointDeVenteId) {
            foreach ($request->reception as $detailId => $qtyAReceptionner) {
                $qtyAReceptionner = intval($qtyAReceptionner);
                if ($qtyAReceptionner <= 0) continue;

                $detail = AchatDetail::findOrFail($detailId);
                abort_unless($detail->achat_id === $achat->id, 403);

                $dejaRecu = $detail->quantite_receptionnee;
                $cmd      = $detail->quantite;
                $maxPossible = $cmd - $dejaRecu;

                if ($qtyAReceptionner > $maxPossible) {
                    throw new \InvalidArgumentException("La quantité reçue pour {$detail->produit->nom} ne peut dépasser {$maxPossible}.");
                }

                $produit = $detail->produit;
                if ($produit && $produit->estStockable()) {
                    $stockObj = Stock::where('produit_id', $produit->id)
                        ->where('point_de_vente_id', $pointDeVenteId)
                        ->first();
                    $stockAvant = $stockObj ? $stockObj->quantite_disponible : 0;

                    // Ajuster les quantités de réception et de stock
                    $detail->increment('quantite_receptionnee', $qtyAReceptionner);
                    $produit->incrementStock($pointDeVenteId, $qtyAReceptionner);

                    // Mouvement de stock Entrée sous-type Réception
                    MouvementStock::create([
                        'produit_id'         => $produit->id,
                        'point_de_vente_id'  => $pointDeVenteId,
                        'type_mouvement'     => 'Entrée',
                        'sous_type'          => 'Reception',
                        'fournisseur_id'     => $achat->fournisseur_id,
                        'utilisateur_id'     => Auth::id(),
                        'quantite'           => $qtyAReceptionner,
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant + $qtyAReceptionner,
                        'reference_document' => $achat->numero_facture,
                    ]);
                }
            }

            // Mettre à jour l'étape ou le statut si tout est entièrement réceptionné
            $toutRecu = true;
            foreach ($achat->details as $d) {
                if ($d->quantite_receptionnee < $d->quantite) {
                    $toutRecu = false;
                    break;
                }
            }
            if ($toutRecu && $achat->etape === 'Bon de commande') {
                $achat->update(['etape' => 'Facture', 'statut' => 'Payé']);
            }
        });

        // Journaliser
        \App\Modules\Admin\Modeles\JournalAudit::create([
            'entreprise_id'  => $achat->pointDeVente->entreprise_id,
            'utilisateur_id' => Auth::id(),
            'action'         => 'reception_stock',
            'details'        => "Réception de stock effectuée pour l'achat #{$achat->id} ({$achat->numero_facture})",
        ]);

        return redirect()->route('admin.stock.receptions')
            ->with('succes', 'Réception validée et enregistrée en stock.');
    }

    /**
     * Liste des livraisons client en attente (Flux Livraison).
     */
    public function livraisons(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;

        $ventes = Vente::with(['client', 'pointDeVente', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->whereIn('etape', ['Bon de commande', 'Facture'])
            ->whereHas('details', function ($q) {
                $q->whereColumn('quantite', '>', 'quantite_livree');
            })
            ->latest()
            ->paginate(20);

        return view('admin::stock.livraisons', compact('ventes'));
    }

    /**
     * Fiche de livraison client.
     */
    public function ficheLivraison(Vente $vente): View
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        $vente->load(['client', 'pointDeVente', 'details.produit']);

        return view('admin::stock.livraison_fiche', compact('vente'));
    }

    /**
     * Valider la livraison physique d'une commande de vente.
     */
    public function validerLivraison(Request $request, Vente $vente): \Illuminate\Http\RedirectResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);

        $request->validate([
            'livraison'   => ['required', 'array', 'min:1'],
            'livraison.*' => ['required', 'integer', 'min:0'],
        ]);

        $pointDeVenteId = $vente->point_de_vente_id;

        DB::transaction(function () use ($request, $vente, $pointDeVenteId) {
            foreach ($request->livraison as $detailId => $qtyALivrer) {
                $qtyALivrer = intval($qtyALivrer);
                if ($qtyALivrer <= 0) continue;

                $detail = VenteDetail::findOrFail($detailId);
                abort_unless($detail->vente_id === $vente->id, 403);

                $dejaLivre = $detail->quantite_livree;
                $cmd       = $detail->quantite;
                $maxPossible = $cmd - $dejaLivre;

                if ($qtyALivrer > $maxPossible) {
                    throw new \InvalidArgumentException("La quantité livrée pour {$detail->produit->nom} ne peut dépasser {$maxPossible}.");
                }

                $produit = $detail->produit;
                if ($produit && $produit->estStockable()) {
                    $stockObj = Stock::where('produit_id', $produit->id)
                        ->where('point_de_vente_id', $pointDeVenteId)
                        ->first();
                    $stockDisponible = $stockObj ? $stockObj->quantite_disponible : 0;

                    if ($qtyALivrer > $stockDisponible) {
                        throw new \InvalidArgumentException("Stock insuffisant pour livrer {$produit->nom} (Disponible: {$stockDisponible}, Demandé: {$qtyALivrer}).");
                    }

                    // Ajuster les quantités de livraison et de stock
                    $detail->increment('quantite_livree', $qtyALivrer);
                    $produit->decrementStock($pointDeVenteId, $qtyALivrer);

                    // Mouvement de stock Sortie sous-type Livraison
                    MouvementStock::create([
                        'produit_id'         => $produit->id,
                        'point_de_vente_id'  => $pointDeVenteId,
                        'type_mouvement'     => 'Sortie',
                        'sous_type'          => 'Livraison',
                        'client_id'          => $vente->client_id,
                        'utilisateur_id'     => Auth::id(),
                        'quantite'           => $qtyALivrer,
                        'stock_avant'        => $stockDisponible,
                        'stock_apres'        => $stockDisponible - $qtyALivrer,
                        'reference_document' => $vente->numero_facture,
                    ]);
                }
            }

            // Mettre à jour l'étape ou le statut si tout est entièrement livré
            $toutLivre = true;
            foreach ($vente->details as $d) {
                if ($d->quantite_livree < $d->quantite) {
                    $toutLivre = false;
                    break;
                }
            }
            if ($toutLivre && $vente->etape === 'Bon de commande') {
                $vente->update(['etape' => 'Facture', 'statut' => 'Payé']);
            }
        });

        // Journaliser
        \App\Modules\Admin\Modeles\JournalAudit::create([
            'entreprise_id'  => $vente->pointDeVente->entreprise_id,
            'utilisateur_id' => Auth::id(),
            'action'         => 'livraison_stock',
            'details'        => "Livraison de stock effectuée pour la vente #{$vente->id} ({$vente->numero_facture})",
        ]);

        return redirect()->route('admin.stock.livraisons')
            ->with('succes', 'Livraison validée et stock mis à jour.');
    }
}
