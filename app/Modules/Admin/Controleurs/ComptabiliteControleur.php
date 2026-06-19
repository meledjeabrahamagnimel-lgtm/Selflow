<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Services\ComptabiliteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ComptabiliteControleur
{
    /**
     * Page « Opération & Écriture Globale »
     */
    public function globale(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;
        $isAdmin = Auth::user()->role === 'admin';

        // Récupérer les points de vente pour le filtre
        $pointsDeVente = PointDeVente::where('entreprise_id', $entreprise->id)->get();

        // Récupérer le point de vente sélectionné pour le filtre
        $pdvFilter = $request->input('point_de_vente_id');
        if (!$isAdmin) {
            $pdvFilter = session('point_de_vente_actif_id') ?? Auth::user()->point_de_vente_id;
        }

        $mode = $request->input('mode', 'operations'); // operations ou ecritures
        $minIds = [];
        $tresoMap = [];
        $venteMap = [];
        $achatMap = [];

        if ($mode === 'ecritures') {
            // Vue écritures (Grand Livre double-entrée)
            $query = EcritureComptable::with(['pointDeVente'])
                ->where('entreprise_id', $entreprise->id);

            if (!empty($pdvFilter)) {
                $query->where('point_de_vente_id', $pdvFilter);
            }

            $ecritures = $query->orderBy('date_ecriture', 'desc')->orderBy('id', 'desc')->paginate(30);
            $operations = collect();

            // Récupérer le MIN(id) pour chaque groupe de transaction (même code_journal + même reference_document)
            // afin de l'utiliser comme "N° saisie" commun en fallback
            $references = $ecritures->pluck('reference_document')->filter()->unique();
            if ($references->isNotEmpty()) {
                $minIds = DB::table('ecritures_comptables')
                    ->select('code_journal', 'reference_document', DB::raw('MIN(id) as min_id'))
                    ->whereIn('reference_document', $references)
                    ->groupBy('code_journal', 'reference_document')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->code_journal . '_' . $item->reference_document => $item->min_id];
                    })
                    ->toArray();

                // Cartographie des ID d'opérations de trésorerie pour faire correspondre le N° saisie à l'ID d'opération
                $tresoMap = TresorerieJournal::whereIn('reference_document', $references)
                    ->pluck('id', 'reference_document')
                    ->toArray();

                $venteMap = Vente::whereIn('numero_facture', $references)
                    ->pluck('id', 'numero_facture')
                    ->toArray();

                $achatMap = Achat::whereIn('numero_facture', $references)
                    ->pluck('id', 'numero_facture')
                    ->toArray();
            }
        } else {
            // Vue opérations (Trésorerie / Caisse)
            $query = TresorerieJournal::with(['pointDeVente'])
                ->whereHas('pointDeVente', function ($q) use ($entreprise) {
                    $q->where('entreprise_id', $entreprise->id);
                });

            if (!empty($pdvFilter)) {
                $query->where('point_de_vente_id', $pdvFilter);
            }

            $operations = $query->orderBy('date_operation', 'desc')->orderBy('id', 'desc')->paginate(30);

            // Charger en lot pour éviter le problème N+1
            $references = $operations->pluck('reference_document')->filter()->unique();
            $ventes = collect();
            $achats = collect();

            $venteRefs = $references->filter(fn($ref) => str_starts_with($ref, 'VT-'));
            if ($venteRefs->isNotEmpty()) {
                $ventes = Vente::whereIn('numero_facture', $venteRefs)->with('client')->get()->keyBy('numero_facture');
            }

            $achatRefs = $references->filter(fn($ref) => str_starts_with($ref, 'AC-'));
            if ($achatRefs->isNotEmpty()) {
                $achats = Achat::whereIn('numero_facture', $achatRefs)->with('fournisseur')->get()->keyBy('numero_facture');
            }

            foreach ($operations as $op) {
                $op->tier_nom = '—';
                if ($op->reference_document) {
                    if (str_starts_with($op->reference_document, 'VT-')) {
                        $vente = $ventes->get($op->reference_document);
                        $op->tier_nom = $vente?->client?->nom ?? 'Client de passage';
                        $op->statut = $vente?->statut ?? '—';
                    } elseif (str_starts_with($op->reference_document, 'AC-')) {
                        $achat = $achats->get($op->reference_document);
                        $op->tier_nom = $achat?->fournisseur?->nom ?? '—';
                        $op->statut = $achat?->statut ?? '—';
                    }
                }
            }
            $ecritures = collect();
        }

        return view('admin::comptabilite.globale', compact('pointsDeVente', 'pdvFilter', 'mode', 'operations', 'ecritures', 'isAdmin', 'minIds', 'tresoMap', 'venteMap', 'achatMap'));
    }

    /**
     * Page « Créances & Règlements »
     */
    public function creances(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;

        // 1. Calcul des créances clients
        $ventesImpayees = Vente::with(['client', 'pointDeVente'])
            ->whereHas('pointDeVente', function ($q) use ($entreprise) {
                $q->where('entreprise_id', $entreprise->id);
            })
            ->where('etape', 'Facture')
            ->whereIn('statut', ['Crédit', 'Avance'])
            ->get();

        // Optimisation N+1 : Charger la somme des règlements de trésorerie en une seule requête
        $venteRefs = $ventesImpayees->pluck('numero_facture');
        $reglementsVentes = TresorerieJournal::whereIn('reference_document', $venteRefs)
            ->groupBy('reference_document')
            ->select('reference_document', DB::raw('SUM(montant_entree) as total_regle'))
            ->pluck('total_regle', 'reference_document');

        $creancesClients = [];
        foreach ($ventesImpayees as $vente) {
            $clientId = $vente->client_id ?? 0;
            $clientNom = $vente->client?->nom ?? 'Client de passage';

            if (!isset($creancesClients[$clientId])) {
                $creancesClients[$clientId] = [
                    'id' => $clientId,
                    'nom' => $clientNom,
                    'invoices' => [],
                    'total_ttc' => 0,
                    'total_regle' => 0,
                    'solde' => 0,
                ];
            }

            $regle = floatval($reglementsVentes->get($vente->numero_facture) ?? 0);

            $creancesClients[$clientId]['invoices'][] = [
                'id' => $vente->id,
                'numero' => $vente->numero_facture,
                'date' => $vente->date_vente->format('d/m/Y'),
                'ttc' => $vente->montant_ttc,
                'regle' => $regle,
                'reste' => $vente->montant_ttc - $regle
            ];

            $creancesClients[$clientId]['total_ttc'] += $vente->montant_ttc;
            $creancesClients[$clientId]['total_regle'] += $regle;
            $creancesClients[$clientId]['solde'] += ($vente->montant_ttc - $regle);
        }

        $creancesClients = array_filter($creancesClients, fn($c) => $c['solde'] > 0);

        // 2. Calcul des dettes fournisseurs
        $achatsImpayes = Achat::with(['fournisseur', 'pointDeVente'])
            ->whereHas('pointDeVente', function ($q) use ($entreprise) {
                $q->where('entreprise_id', $entreprise->id);
            })
            ->where('etape', 'Facture')
            ->whereIn('statut', ['Crédit', 'Avance'])
            ->get();

        // Optimisation N+1 : Charger la somme des règlements d'achats en une seule requête
        $achatRefs = $achatsImpayes->pluck('numero_facture');
        $reglementsAchats = TresorerieJournal::whereIn('reference_document', $achatRefs)
            ->groupBy('reference_document')
            ->select('reference_document', DB::raw('SUM(montant_sortie) as total_regle'))
            ->pluck('total_regle', 'reference_document');

        $dettesFournisseurs = [];
        foreach ($achatsImpayes as $achat) {
            $fournisseurId = $achat->fournisseur_id;
            $fournisseurNom = $achat->fournisseur?->nom ?? 'Inconnu';

            if (!isset($dettesFournisseurs[$fournisseurId])) {
                $dettesFournisseurs[$fournisseurId] = [
                    'id' => $fournisseurId,
                    'nom' => $fournisseurNom,
                    'invoices' => [],
                    'total_ttc' => 0,
                    'total_regle' => 0,
                    'solde' => 0,
                ];
            }

            $regle = floatval($reglementsAchats->get($achat->numero_facture) ?? 0);

            $dettesFournisseurs[$fournisseurId]['invoices'][] = [
                'id' => $achat->id,
                'numero' => $achat->numero_facture,
                'date' => $achat->date_achat->format('d/m/Y'),
                'ttc' => $achat->montant_ttc,
                'regle' => $regle,
                'reste' => $achat->montant_ttc - $regle
            ];

            $dettesFournisseurs[$fournisseurId]['total_ttc'] += $achat->montant_ttc;
            $dettesFournisseurs[$fournisseurId]['total_regle'] += $regle;
            $dettesFournisseurs[$fournisseurId]['solde'] += ($achat->montant_ttc - $regle);
        }

        $dettesFournisseurs = array_filter($dettesFournisseurs, fn($f) => $f['solde'] > 0);

        $banques = CodeJournal::where('type', 'Banque')->where('entreprise_id', $entreprise->id)->orderBy('intitule')->get();

        return view('admin::comptabilite.creances', compact('creancesClients', 'dettesFournisseurs', 'banques'));
    }

    /**
     * Afficher le relevé détaillé (Fiche Tiers)
     */
    public function releveTiers(string $type, int $id): View
    {
        $entreprise = Auth::user()->entreprise;

        if ($type === 'client') {
            $tier = Client::where('entreprise_id', $entreprise->id)->findOrFail($id);

            // Récupérer toutes les factures validées du client
            $invoices = Vente::where('client_id', $id)
                ->where('etape', 'Facture')
                ->orderBy('date_vente')
                ->get();

            // Optimisation N+1 : Charger tous les règlements liés en une seule requête
            $invoiceRefs = $invoices->pluck('numero_facture');
            $reglementsGrouped = TresorerieJournal::whereIn('reference_document', $invoiceRefs)
                ->orderBy('date_operation')
                ->get()
                ->groupBy('reference_document');

            $operations = [];
            foreach ($invoices as $inv) {
                // Facturation (Débit du compte tiers)
                $operations[] = [
                    'date' => $inv->date_vente,
                    'piece' => $inv->numero_facture,
                    'libelle' => 'Facture ' . $inv->numero_facture,
                    'debit' => $inv->montant_ttc,
                    'credit' => 0,
                ];

                // Règlements associés (Crédit du compte tiers)
                $payments = $reglementsGrouped->get($inv->numero_facture) ?? collect();

                foreach ($payments as $pay) {
                    $operations[] = [
                        'date' => $pay->date_operation,
                        'piece' => $pay->reference_document,
                        'libelle' => 'Règlement ' . $pay->mode_paiement,
                        'debit' => 0,
                        'credit' => $pay->montant_entree,
                    ];
                }
            }
        } else {
            $tier = Fournisseur::where('entreprise_id', $entreprise->id)->findOrFail($id);

            // Récupérer tous les achats validés auprès de ce fournisseur
            $invoices = Achat::where('fournisseur_id', $id)
                ->where('etape', 'Facture')
                ->orderBy('date_achat')
                ->get();

            // Optimisation N+1 : Charger tous les règlements liés en une seule requête
            $invoiceRefs = $invoices->pluck('numero_facture');
            $reglementsGrouped = TresorerieJournal::whereIn('reference_document', $invoiceRefs)
                ->orderBy('date_operation')
                ->get()
                ->groupBy('reference_document');

            $operations = [];
            foreach ($invoices as $inv) {
                // Facturation d'achat (Crédit du compte tiers)
                $operations[] = [
                    'date' => $inv->date_achat,
                    'piece' => $inv->numero_facture,
                    'libelle' => 'Achat ' . $inv->numero_facture,
                    'debit' => 0,
                    'credit' => $inv->montant_ttc,
                ];

                // Règlements associés (Débit du compte tiers)
                $payments = $reglementsGrouped->get($inv->numero_facture) ?? collect();

                foreach ($payments as $pay) {
                    $operations[] = [
                        'date' => $pay->date_operation,
                        'piece' => $pay->reference_document,
                        'libelle' => 'Règlement ' . $pay->mode_paiement,
                        'debit' => $pay->montant_sortie,
                        'credit' => 0,
                    ];
                }
            }
        }

        // Trier les opérations par date chronologique
        usort($operations, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Calculer le solde progressif
        $solde = 0;
        foreach ($operations as &$op) {
            if ($type === 'client') {
                $solde += ($op['debit'] - $op['credit']);
            } else {
                $solde += ($op['credit'] - $op['debit']);
            }
            $op['solde'] = $solde;
        }

        return view('admin::comptabilite.releve_tiers', compact('tier', 'type', 'operations', 'solde'));
    }

    /**
     * Enregistrer un règlement
     */
    public function enregistrerReglement(Request $request): RedirectResponse
    {
        $request->validate([
            'type'            => ['required', 'string', 'in:client,fournisseur'],
            'tier_id'         => ['required', 'integer'],
            'numero_facture'  => ['required', 'string'],
            'montant'         => ['required', 'numeric', 'min:1'],
            'mode_paiement'   => ['required', 'string'],
            'date_operation'  => ['required', 'date'],
        ]);

        if ($request->mode_paiement === 'Banque') {
            $request->validate([
                'banque_id'          => ['required', 'integer', 'exists:codes_journaux,id'],
                'moyen_bancaire'     => ['required', 'string', 'in:carte,virement,cheque'],
                'reference_paiement' => ['required', 'string', 'max:255'],
            ], [
                'banque_id.required'          => 'Veuillez sélectionner la banque.',
                'moyen_bancaire.required'     => 'Veuillez sélectionner le moyen de paiement bancaire.',
                'reference_paiement.required' => 'Veuillez saisir le numéro ou référence de paiement.',
            ]);
        }

        $entreprise = Auth::user()->entreprise;

        DB::transaction(function () use ($request, $entreprise) {
            $numFacture = $request->numero_facture;
            $montant = floatval($request->montant);
            $mode = $request->mode_paiement;
            if ($request->mode_paiement === 'Banque' && $request->filled('banque_id')) {
                $codeJournal = CodeJournal::findOrFail($request->banque_id);
                $mode = 'Banque : ' . $codeJournal->intitule;
            }
            $date = $request->date_operation;

            if ($request->type === 'client') {
                $vente = Vente::where('numero_facture', $numFacture)->firstOrFail();
                if (!$vente->point_de_vente_id) {
                    $vente->point_de_vente_id = session('point_de_vente_actif_id')
                        ?? Auth::user()->point_de_vente_id
                        ?? PointDeVente::where('entreprise_id', $entreprise->id)->value('id');
                    $vente->save();
                    $vente->load('pointDeVente');
                }
                $pdvId = $vente->point_de_vente_id;
                
                // Calcul du reste à payer
                $dejaPaye = TresorerieJournal::where('reference_document', $numFacture)->sum('montant_entree');
                $reste = $vente->montant_ttc - $dejaPaye;

                if ($montant > $reste) {
                    abort(422, "Le montant du règlement ne peut pas dépasser le reste à payer (" . $reste . " F).");
                }

                // 1. Enregistrer en trésorerie
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pdvId)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $pdvId,
                    'date_operation'     => $date,
                    'type_operation'     => 'Encaissement',
                    'libelle'            => 'Règlement Client — Facture ' . $numFacture,
                    'mode_paiement'      => $mode,
                    'moyen_bancaire'     => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    'reference_paiement' => $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                    'montant_entree'     => $montant,
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $soldeActuel + $montant,
                    'reference_document' => $numFacture,
                ]);

                // 2. Générer l'écriture comptable
                ComptabiliteService::genererEcritureReglementVente(
                    $vente,
                    $montant,
                    $mode,
                    $date,
                    $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    $request->mode_paiement === 'Banque' ? $request->reference_paiement : null
                );

                // 3. Mettre à jour le statut de la facture
                $nouveauPaye = $dejaPaye + $montant;
                if ($nouveauPaye >= $vente->montant_ttc) {
                    $vente->update(['statut' => 'Payé']);
                } else {
                    $vente->update(['statut' => 'Avance']);
                }
            } else {
                $achat = Achat::where('numero_facture', $numFacture)->firstOrFail();
                if (!$achat->point_de_vente_id) {
                    $achat->point_de_vente_id = session('point_de_vente_actif_id')
                        ?? Auth::user()->point_de_vente_id
                        ?? PointDeVente::where('entreprise_id', $entreprise->id)->value('id');
                    $achat->save();
                    $achat->load('pointDeVente');
                }
                $pdvId = $achat->point_de_vente_id;
                
                // Calcul du reste à payer
                $dejaPaye = TresorerieJournal::where('reference_document', $numFacture)->sum('montant_sortie');
                $reste = $achat->montant_ttc - $dejaPaye;

                if ($montant > $reste) {
                    abort(422, "Le montant du règlement ne peut pas dépasser le reste à payer (" . $reste . " F).");
                }

                // 1. Enregistrer en trésorerie
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pdvId)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $pdvId,
                    'date_operation'     => $date,
                    'type_operation'     => 'Décaissement',
                    'libelle'            => 'Règlement Fournisseur — Facture ' . $numFacture,
                    'mode_paiement'      => $mode,
                    'moyen_bancaire'     => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    'reference_paiement' => $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                    'montant_entree'     => 0,
                    'montant_sortie'     => $montant,
                    'solde_resultat'     => $soldeActuel - $montant,
                    'reference_document' => $numFacture,
                ]);

                // 2. Générer l'écriture comptable
                ComptabiliteService::genererEcritureReglementAchat(
                    $achat,
                    $montant,
                    $mode,
                    $date,
                    $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    $request->mode_paiement === 'Banque' ? $request->reference_paiement : null
                );

                // 3. Mettre à jour le statut de la facture
                $nouveauPaye = $dejaPaye + $montant;
                if ($nouveauPaye >= $achat->montant_ttc) {
                    $achat->update(['statut' => 'Payé']);
                } else {
                    $achat->update(['statut' => 'Avance']);
                }
            }
        });

        return back()->with('succes', 'Règlement enregistré avec succès.');
    }

    /**
     * Page « Plan Comptable »
     */
    public function planComptable(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;
        $query = \App\Modules\Admin\Modeles\PlanComptable::where(function ($q) use ($entreprise) {
            $q->whereNull('entreprise_id')
              ->orWhere('entreprise_id', $entreprise->id);
        });

        if ($request->filled('numero')) {
            $query->where('numero', 'like', $request->input('numero') . '%');
        }

        if ($request->filled('classe')) {
            $query->where('numero', 'like', $request->input('classe') . '%');
        }

        if ($request->filled('libelle')) {
            $query->where('libelle', 'like', '%' . $request->input('libelle') . '%');
        }

        $comptes = $query->orderBy('numero')->paginate(10);

        return view('admin::comptabilite.plan_comptable', compact('comptes'));
    }

    /**
     * Créer un compte dans le Plan Comptable
     */
    public function creerCompteComptable(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'numero'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::unique('plan_comptable', 'numero')->where('entreprise_id', $entreprise->id)
            ],
            'libelle' => ['required', 'string', 'max:255'],
        ], [
            'numero.required' => 'Le numéro de compte est obligatoire.',
            'numero.unique'   => 'Ce numéro de compte existe déjà pour votre entreprise.',
            'libelle.required' => 'Le libellé est obligatoire.',
        ]);

        \App\Modules\Admin\Modeles\PlanComptable::create(array_merge(
            $request->only(['numero', 'libelle']),
            ['entreprise_id' => $entreprise->id]
        ));

        return back()->with('succes', 'Compte comptable créé avec succès.');
    }
}
