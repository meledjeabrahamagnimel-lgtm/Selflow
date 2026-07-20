<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Jobs\NormaliserFactureFne;
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
                'banque_id'          => ['required', 'integer', 'exists:banques,id'],
                'moyen_bancaire'     => ['required', 'string', 'in:carte,virement,cheque'],
                'reference_paiement' => ['required', 'string', 'max:255'],
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

            $typeDocument = $request->input('type_document', 'Facture');

            // Génération numéro facture et calcul du statut
            $montantPaye = 0;
            if ($typeDocument === 'Devis') {
                $numero = 'DEV-' . now()->year . '-' . str_pad(
                    Vente::where('numero_facture', 'LIKE', 'DEV-%')->whereYear('created_at', now()->year)->count() + 1,
                    4, '0', STR_PAD_LEFT
                );
                $statutVente = 'Brouillon';
            } elseif ($typeDocument === 'Commande') {
                $numero = 'CMD-' . now()->year . '-' . str_pad(
                    Vente::where('numero_facture', 'LIKE', 'CMD-%')->whereYear('created_at', now()->year)->count() + 1,
                    4, '0', STR_PAD_LEFT
                );
                $statutVente = 'Confirmée';
            } else {
                $numero = 'VT-' . now()->year . '-' . str_pad(
                    Vente::where('numero_facture', 'LIKE', 'VT-%')->whereYear('created_at', now()->year)->count() + 1,
                    4, '0', STR_PAD_LEFT
                );

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
            }

            $vente = Vente::create([
                'point_de_vente_id' => $pointDeVenteId,
                'client_id'         => $request->client_id ?: null,
                'numero_facture'    => $numero,
                'date_vente'        => now()->toDateString(),
                'mode_paiement'     => $modePaiementFinal,
                'moyen_bancaire'    => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                'reference_paiement'=> $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                'montant_ht'        => $montantHt,
                'montant_tva'       => $montantTva,
                'remise'            => $remise,
                'montant_ttc'       => $montantTtc,
                'statut'            => $statutVente,
                'type_facture'      => $typeDocument,
            ]);

            // Mettre à jour le document parent si existant (ex: Devis -> Facture)
            if ($request->filled('reference_parent_id')) {
                Vente::where('numero_facture', $request->reference_parent_id)
                    ->update(['statut' => $typeDocument === 'Facture' ? 'Facturé' : 'Converti']);
            }

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

                if ($typeDocument === 'Facture' && $produit) {
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

            // Normalisation FNE DGI-CI en arrière-plan (Section 18.5)
            if ($typeDocument === 'Facture') {
                $estRne = empty($request->client_id) || ($request->client_id == 'divers');
                NormaliserFactureFne::dispatch($vente, $estRne);
            }

            // Trésorerie
            if ($typeDocument === 'Facture' && $statutVente !== 'Crédit' && $montantPaye > 0) {
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $pointDeVenteId,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Encaissement',
                    'libelle'            => 'Vente — Facture ' . $numero,
                    'mode_paiement'      => $modePaiementFinal,
                    'moyen_bancaire'     => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    'reference_paiement' => $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
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
        $ventes = Vente::with(['client', 'pointDeVente.entreprise', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->where('numero_facture', 'LIKE', 'VT-%')
            ->latest()
            ->paginate(20);

        return response()->json([
            'statut' => 'succes',
            'ventes' => $ventes
        ]);
    }

    /**
     * Liste des devis.
     */
    public function devis(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $ventes = Vente::with(['client', 'pointDeVente.entreprise', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->where('numero_facture', 'LIKE', 'DEV-%')
            ->latest()
            ->paginate(20);

        return response()->json([
            'statut' => 'succes',
            'ventes' => $ventes
        ]);
    }

    /**
     * Liste des commandes.
     */
    public function commandes(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $ventes = Vente::with(['client', 'pointDeVente.entreprise', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->where('numero_facture', 'LIKE', 'CMD-%')
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
     * Modifier rapidement le statut de règlement (paiement partiel ou total).
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
            'montant_paye'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $updateData = $request->only(['statut', 'mode_paiement']);
        // montant_paye is not stored in ventes table, it is tracked via TresorerieJournal
        $vente->update($updateData);

        // Enregistrer l'encaissement si paiement effectif
        if (in_array($request->statut, ['Payé', 'Avance']) && $request->filled('montant_paye')) {
            $soldeActuel = TresorerieJournal::where('point_de_vente_id', $vente->point_de_vente_id)
                ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

            TresorerieJournal::create([
                'entreprise_id'      => Auth::user()->entreprise_id,
                'point_de_vente_id'  => $vente->point_de_vente_id,
                'type_operation'     => 'Encaissement',
                'libelle'            => 'Règlement facture vente ' . $vente->numero_facture,
                'montant_entree'     => floatval($request->input('montant_paye')),
                'montant_sortie'     => 0,
                'solde_resultat'     => $soldeActuel + floatval($request->input('montant_paye')),
                'mode_paiement'      => $request->input('mode_paiement', 'Espèces'),
                'reference_document' => $vente->numero_facture,
                'date_operation'     => now()->toDateString(),
            ]);
        }

        return response()->json([
            'statut'  => 'succes',
            'message' => 'Facture mise à jour avec succès.',
            'nouveau_statut' => $vente->fresh()->statut,
        ]);
    }

    /**
     * Récupère la liste des factures impayées (statut Crédit ou Avance).
     */
    public function impayes(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        
        $impayes = Vente::with(['client'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->whereIn('statut', ['Crédit', 'Avance'])
            ->get()
            ->map(function ($vente) {
                // Si pas de date_echeance, on calcule depuis date_vente
                $dateRef = $vente->date_echeance ?? $vente->date_vente;
                $delay = $dateRef ? \Carbon\Carbon::parse($dateRef)->diffInDays(now(), false) : 0;
                
                return [
                    'id_facture' => $vente->id,
                    'nom_client' => $vente->client ? $vente->client->nom : 'Client Divers',
                    'numero_facture' => $vente->numero_facture,
                    // Si on ne stocke pas le reste à payer en base, on affiche le TTC.
                    'montant_restant' => $vente->montant_ttc, 
                    'jours_retard' => max(0, intval($delay)),
                    'telephone' => $vente->client ? $vente->client->telephone : '',
                    'email' => $vente->client ? $vente->client->email : '',
                ];
            });

        return response()->json([
            'statut' => 'succes',
            'donnees' => $impayes
        ]);
    }

    /**
     * Déclenche une relance client.
     */
    public function relancer($id): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        
        $vente = Vente::with(['client', 'pointDeVente'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->findOrFail($id);

        // Ici, logiquement vous envoyez un email ou SMS.
        // Exemple : Mail::to($vente->client->email)->send(new RelanceFactureMail($vente));
        
        return response()->json([
            'statut' => 'succes',
            'message' => 'Relance traitée avec succès pour la facture ' . $vente->numero_facture
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
