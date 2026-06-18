<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\VenteDetail;
use App\Modules\Admin\Modeles\Banque;
use App\Modules\Admin\Modeles\PointDeVente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VenteApiControleur
{
    /**
     * Charger les données pour initialiser le panier de vente.
     */
    public function donneesFormulaire(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = $this->obtenirPointDeVenteId($request);

        $clients = Client::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $produits = Produit::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $categories = $produits->pluck('categorie')->unique()->sort()->values();
        $banques = Banque::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();

        return response()->json([
            'statut' => 'succes',
            'donnees' => [
                'clients' => $clients,
                'produits' => $produits,
                'categories' => $categories,
                'point_de_vente_actif_id' => intval($pointDeVenteId),
                'banques' => $banques
            ]
        ]);
    }

    /**
     * Enregistrer une nouvelle vente.
     */
    public function enregistrer(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = $this->obtenirPointDeVenteId($request);

        $request->validate([
            'client_id'      => ['nullable', 'integer', 'exists:clients,id'],
            'mode_paiement'  => ['required', 'string'],
            'remise'         => ['nullable', 'numeric', 'min:0'],
            'articles'       => ['required', 'array', 'min:1'],
            'articles.*.produit_id'      => ['nullable', 'integer', 'exists:produits,id'],
            'articles.*.libelle_virtuel' => ['nullable', 'string', 'max:255'],
            'articles.*.quantite'        => ['required', 'integer', 'min:1'],
            'articles.*.unite'           => ['nullable', 'string', 'max:50'],
            'articles.*.prix_unitaire'   => ['nullable', 'numeric', 'min:0'],
        ], [
            'articles.required' => 'Veuillez ajouter au moins un article au panier.',
        ]);

        if ($request->mode_paiement === 'Banque') {
            $request->validate([
                'banque_id' => ['required', 'integer', 'exists:banques,id'],
            ]);
        }

        if ($request->mode_paiement !== 'Crédit') {
            $request->validate([
                'montant_paye' => ['required', 'numeric', 'min:0.01'],
            ], [
                'montant_paye.required' => 'Le montant payé est obligatoire pour ce mode de paiement.',
                'montant_paye.min' => 'Le montant payé doit être supérieur à 0.',
            ]);
        }

        $venteData = [];

        DB::transaction(function () use ($request, $pointDeVenteId, &$venteData) {
            $montantHt  = 0;
            $tvaActive = $request->boolean('tva_active', false);
            $remise = floatval($request->input('remise', 0));

            // Précalcul des montants
            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::findOrFail($article['produit_id']);
                    $prix = $produit->prix_vente;
                } else {
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                }

                $ht = $article['quantite'] * $prix;
                $montantHt += $ht;
            }

            $montantHtNet = max(0, $montantHt - $remise);
            $montantTva = $tvaActive ? ($montantHtNet * 0.18) : 0;
            $montantTtc = $montantHtNet + $montantTva;

            // Mode de paiement
            $modePaiementFinal = $request->mode_paiement;
            if ($request->mode_paiement === 'Banque' && $request->filled('banque_id')) {
                $banque = Banque::findOrFail($request->banque_id);
                $modePaiementFinal = 'Banque : ' . $banque->nom;
            }

            // Génération numéro facture
            $numero = 'VT-' . now()->format('d-m-Y') . '-' . str_pad(
                Vente::whereYear('created_at', now()->year)->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            // Calcul du statut de paiement
            $montantPaye = 0;
            if ($request->mode_paiement === 'Crédit') {
                $statutVente = 'Crédit';
            } else {
                $montantPaye = $request->filled('montant_paye') ? floatval($request->montant_paye) : $montantTtc;
                if ($montantPaye <= 0) {
                    $statutVente = 'Crédit';
                    $montantPaye = 0;
                } elseif ($montantPaye >= $montantTtc) {
                    $statutVente = 'Payé';
                } else {
                    $statutVente = 'Avance';
                }
            }

            $vente = Vente::create([
                'point_de_vente_id' => $pointDeVenteId,
                'client_id'         => $request->client_id ?: null,
                'numero_facture'    => $numero,
                'date_vente'        => now()->toDateString(),
                'mode_paiement'     => $modePaiementFinal,
                'montant_ht'        => $montantHt,
                'montant_tva'       => $montantTva,
                'remise'            => $remise,
                'montant_ttc'       => $montantTtc,
                'statut'            => $statutVente,
            ]);

            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::lockForUpdate()->findOrFail($article['produit_id']);
                    $prix = $produit->prix_vente;
                    $nomElement = $produit->nom;
                } else {
                    $produit = null;
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                    $nomElement = $article['libelle_virtuel'] ?? 'Saisie libre';
                }

                $ht = $article['quantite'] * $prix;
                $tva = $tvaActive ? ($ht * 0.18) : 0;

                VenteDetail::create([
                    'vente_id'        => $vente->id,
                    'produit_id'      => $produit ? $produit->id : null,
                    'libelle_virtuel' => $produit ? null : $nomElement,
                    'quantite'        => $article['quantite'],
                    'unite'           => $article['unite'] ?? 'Unité',
                    'prix_unitaire'   => $prix,
                    'montant_tva'     => $tva,
                    'montant_ttc'     => $ht + $tva,
                ]);

                if ($produit) {
                    $stockAvant = $produit->stock_actuel;
                    $produit->decrement('stock_actuel', $article['quantite']);

                    MouvementStock::create([
                        'produit_id'         => $produit->id,
                        'point_de_vente_id'  => $pointDeVenteId,
                        'type_mouvement'     => 'Sortie',
                        'quantite'           => $article['quantite'],
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant - $article['quantite'],
                        'reference_document' => $numero,
                    ]);
                }
            }

            // Trésorerie
            if ($statutVente !== 'Crédit' && $montantPaye > 0) {
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $pointDeVenteId,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Encaissement',
                    'libelle'            => 'Vente — Facture ' . $numero,
                    'mode_paiement'      => $modePaiementFinal,
                    'montant_entree'     => $montantPaye,
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $soldeActuel + $montantPaye,
                    'reference_document' => $numero,
                ]);
            }

            $venteData = [
                'vente_id' => $vente->id,
                'numero_facture' => $vente->numero_facture,
                'details' => [
                    'montant_ht' => floatval($montantHt),
                    'remise' => floatval($remise),
                    'montant_ht_net' => floatval($montantHtNet),
                    'montant_tva' => floatval($montantTva),
                    'montant_ttc' => floatval($montantTtc),
                    'statut_paiement' => $statutVente
                ]
            ];
        });

        return response()->json([
            'statut' => 'succes',
            'message' => 'Vente enregistrée avec succès !',
            'donnees' => $venteData
        ], 201);
    }

    /**
     * Liste des factures de vente.
     */
    public function factures(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $ventes = Vente::with(['client', 'pointDeVente', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(20);

        return response()->json([
            'statut' => 'succes',
            'ventes' => $ventes
        ]);
    }

    /**
     * Historique des ventes.
     */
    public function historique(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $ventes = Vente::with(['client', 'pointDeVente'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(30);

        return response()->json([
            'statut' => 'succes',
            'ventes' => $ventes
        ]);
    }

    /**
     * Détails d'une facture.
     */
    public function details(Vente $vente): JsonResponse
    {
        if ($vente->pointDeVente->entreprise_id !== Auth::user()->entreprise_id) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        $vente->load(['client', 'pointDeVente.entreprise', 'details.produit']);

        return response()->json([
            'statut' => 'succes',
            'donnees' => [
                'vente' => [
                    'id' => $vente->id,
                    'numero_facture' => $vente->numero_facture,
                    'date_vente' => $vente->date_vente->toDateString(),
                    'mode_paiement' => $vente->mode_paiement,
                    'montant_ht' => floatval($vente->montant_ht),
                    'remise' => floatval($vente->remise),
                    'montant_tva' => floatval($vente->montant_tva),
                    'montant_ttc' => floatval($vente->montant_ttc),
                    'statut' => $vente->statut,
                    'normalise' => $vente->normalise,
                    'qr_code_data' => $vente->qr_code_data,
                    'client' => $vente->client ? [
                        'nom' => $vente->client->nom,
                        'telephone' => $vente->client->telephone,
                        'ncc' => $vente->client->ncc
                    ] : null,
                    'point_de_vente' => [
                        'nom' => $vente->pointDeVente->nom,
                        'ville' => $vente->pointDeVente->ville,
                        'commune' => $vente->pointDeVente->commune,
                        'entreprise' => [
                            'nom' => $vente->pointDeVente->entreprise->nom,
                            'telephone' => $vente->pointDeVente->entreprise->telephone,
                            'ncc' => $vente->pointDeVente->entreprise->ncc
                        ]
                    ],
                    'details' => $vente->details->map(function ($d) {
                        return [
                            'produit_id' => $d->produit_id,
                            'nom_produit' => $d->produit ? $d->produit->nom : $d->libelle_virtuel,
                            'quantite' => $d->quantite,
                            'unite' => $d->unite,
                            'prix_unitaire' => floatval($d->prix_unitaire),
                            'montant_ttc' => floatval($d->montant_ttc)
                        ];
                    })
                ]
            ]
        ]);
    }

    /**
     * Normaliser la facture (DGI).
     */
    public function normaliser(Vente $vente): JsonResponse
    {
        if ($vente->pointDeVente->entreprise_id !== Auth::user()->entreprise_id) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        if ($vente->normalise) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Cette facture est déjà normalisée.',
                'qr_code_data' => $vente->qr_code_data
            ], 400);
        }

        // Simulation
        $qrData = implode('|', [
            $vente->numero_facture,
            now()->format('YmdHis'),
            'DGI-CI',
            strtoupper(substr(md5($vente->id . $vente->numero_facture . now()->timestamp), 0, 12)),
        ]);

        $vente->update([
            'normalise'     => true,
            'type_facture'  => 'normale',
            'qr_code_data'  => $qrData,
        ]);

        return response()->json([
            'statut' => 'succes',
            'message' => 'Facture ' . $vente->numero_facture . ' normalisée avec succès.',
            'qr_code_data' => $qrData
        ]);
    }

    /**
     * Modifier rapidement le statut de règlement.
     */
    public function modifierStatut(Vente $vente, Request $request): JsonResponse
    {
        if ($vente->pointDeVente->entreprise_id !== Auth::user()->entreprise_id) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        $request->validate([
            'statut'        => ['required', 'string', 'in:Payé,Crédit,Avance'],
            'mode_paiement' => ['nullable', 'string', 'max:100'],
        ]);

        $vente->update($request->only(['statut', 'mode_paiement']));

        return response()->json([
            'statut' => 'succes',
            'message' => 'Facture mise à jour avec succès.'
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
