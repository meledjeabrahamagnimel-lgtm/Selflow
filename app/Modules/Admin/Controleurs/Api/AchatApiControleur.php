<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\AchatDetail;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AchatApiControleur
{
    /**
     * Charger les données pour initialiser le formulaire d'achat.
     */
    public function donneesFormulaire(Request $request): JsonResponse
    {
        $entreprise  = Auth::user()->entreprise;
        $fournisseurs = Fournisseur::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $produits     = Produit::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $pointDeVenteId = $this->obtenirPointDeVenteId($request);

        return response()->json([
            'statut' => 'succes',
            'donnees' => [
                'fournisseurs' => $fournisseurs,
                'produits' => $produits,
                'point_de_vente_actif_id' => intval($pointDeVenteId)
            ]
        ]);
    }

    /**
     * Enregistrer un nouvel achat.
     */
    public function enregistrer(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = $this->obtenirPointDeVenteId($request);

        $request->validate([
            'fournisseur_id' => ['required', 'integer', 'exists:fournisseurs,id'],
            'date_achat'     => ['required', 'date'],
            'mode_paiement'  => ['required', 'string'],
            'articles'       => ['required', 'array', 'min:1'],
            'articles.*.produit_id'    => ['required', 'integer', 'exists:produits,id'],
            'articles.*.quantite'      => ['required', 'integer', 'min:1'],
            'articles.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ], [
            'fournisseur_id.required' => 'Veuillez sélectionner un fournisseur.',
            'articles.required'       => 'Veuillez ajouter au moins un article.',
        ]);

        $achatData = [];

        DB::transaction(function () use ($request, $pointDeVenteId, &$achatData) {
            $montantHt  = 0;
            $montantTva = 0;

            foreach ($request->articles as $article) {
                $ht          = $article['quantite'] * $article['prix_unitaire'];
                $tva         = $ht * 0.18;
                $montantHt  += $ht;
                $montantTva += $tva;
            }

            $montantTtc = $montantHt + $montantTva;

            // Générer le numéro de facture d'achat
            $numero = 'AC-' . now()->year . '-' . str_pad(
                Achat::whereYear('created_at', now()->year)->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            $achat = Achat::create([
                'point_de_vente_id' => $pointDeVenteId,
                'fournisseur_id'    => $request->fournisseur_id,
                'numero_facture'    => $numero,
                'date_achat'        => $request->date_achat,
                'mode_paiement'     => $request->mode_paiement,
                'montant_ht'        => $montantHt,
                'montant_tva'       => $montantTva,
                'montant_ttc'       => $montantTtc,
                'statut'            => 'Payé',
            ]);

            foreach ($request->articles as $article) {
                $produit = Produit::lockForUpdate()->find($article['produit_id']);
                $ht      = $article['quantite'] * $article['prix_unitaire'];
                $tva     = $ht * 0.18;

                AchatDetail::create([
                    'achat_id'       => $achat->id,
                    'produit_id'     => $produit->id,
                    'quantite'       => $article['quantite'],
                    'prix_unitaire'  => $article['prix_unitaire'],
                    'montant_tva'    => $tva,
                    'montant_ttc'    => $ht + $tva,
                ]);

                // Augmenter le stock + mouvement
                $stockAvant = $produit->stock_actuel;
                $produit->increment('stock_actuel', $article['quantite']);

                MouvementStock::create([
                    'produit_id'         => $produit->id,
                    'point_de_vente_id'  => $pointDeVenteId,
                    'type_mouvement'     => 'Entrée',
                    'quantite'           => $article['quantite'],
                    'stock_avant'        => $stockAvant,
                    'stock_apres'        => $stockAvant + $article['quantite'],
                    'reference_document' => $numero,
                ]);
            }

            // Enregistrement automatique en décaissement (trésorerie)
            $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

            TresorerieJournal::create([
                'point_de_vente_id'  => $pointDeVenteId,
                'date_operation'     => $request->date_achat,
                'type_operation'     => 'Décaissement',
                'libelle'            => 'Achat — Facture ' . $numero,
                'mode_paiement'      => $request->mode_paiement,
                'montant_entree'     => 0,
                'montant_sortie'     => $montantTtc,
                'solde_resultat'     => $soldeActuel - $montantTtc,
                'reference_document' => $numero,
            ]);

            $achatData = [
                'achat_id' => $achat->id,
                'numero_facture' => $achat->numero_facture
            ];
        });

        return response()->json([
            'statut' => 'succes',
            'message' => 'Achat enregistré et facture générée avec succès.',
            'donnees' => $achatData
        ], 201);
    }

    /**
     * Liste des factures d'achat.
     */
    public function factures(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $achats = Achat::with(['fournisseur', 'pointDeVente', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(20);

        return response()->json([
            'statut' => 'succes',
            'achats' => $achats
        ]);
    }

    /**
     * Historique des achats.
     */
    public function historique(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $achats = Achat::with(['fournisseur', 'pointDeVente'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(30);

        return response()->json([
            'statut' => 'succes',
            'achats' => $achats
        ]);
    }

    /**
     * Détail d'une facture d'achat.
     */
    public function details(Achat $achat): JsonResponse
    {
        if ($achat->pointDeVente->entreprise_id !== Auth::user()->entreprise_id) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        $achat->load(['fournisseur', 'pointDeVente.entreprise', 'details.produit']);

        return response()->json([
            'statut' => 'succes',
            'donnees' => [
                'achat' => [
                    'id' => $achat->id,
                    'numero_facture' => $achat->numero_facture,
                    'date_achat' => $achat->date_achat->toDateString(),
                    'mode_paiement' => $achat->mode_paiement,
                    'montant_ht' => floatval($achat->montant_ht),
                    'montant_tva' => floatval($achat->montant_tva),
                    'montant_ttc' => floatval($achat->montant_ttc),
                    'statut' => $achat->statut,
                    'fournisseur' => [
                        'nom' => $achat->fournisseur->nom,
                        'telephone' => $achat->fournisseur->telephone,
                        'ncc' => $achat->fournisseur->ncc
                    ],
                    'point_de_vente' => [
                        'nom' => $achat->pointDeVente->nom,
                        'ville' => $achat->pointDeVente->ville,
                        'commune' => $achat->pointDeVente->commune
                    ],
                    'details' => $achat->details->map(function ($d) {
                        return [
                            'produit_id' => $d->produit_id,
                            'nom_produit' => $d->produit->nom,
                            'quantite' => $d->quantite,
                            'prix_unitaire' => floatval($d->prix_unitaire),
                            'montant_ttc' => floatval($d->montant_ttc)
                        ];
                    })
                ]
            ]
        ]);
    }

    /**
     * Récupère le point de vente actif.
     */
    private function obtenirPointDeVenteId(Request $request)
    {
        return $request->header('X-Point-De-Vente-Id') 
            ?? $request->query('point_de_vente_id') 
            ?? session('point_de_vente_actif_id') 
            ?? Auth::user()->point_de_vente_id;
    }
}
