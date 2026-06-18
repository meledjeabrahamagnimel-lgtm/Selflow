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

            // Charger les informations client/fournisseur pour chaque opération
            foreach ($operations as $op) {
                $op->tier_nom = '—';
                if ($op->reference_document) {
                    if (str_starts_with($op->reference_document, 'VT-')) {
                        $vente = Vente::where('numero_facture', $op->reference_document)->with('client')->first();
                        $op->tier_nom = $vente?->client?->nom ?? 'Client de passage';
                        $op->statut = $vente?->statut ?? '—';
                    } elseif (str_starts_with($op->reference_document, 'AC-')) {
                        $achat = Achat::where('numero_facture', $op->reference_document)->with('fournisseur')->first();
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

            // Calculer le montant déjà réglé via la trésorerie
            $regle = TresorerieJournal::where('reference_document', $vente->numero_facture)->sum('montant_entree');

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

        // Filtrer pour ne garder que ceux qui ont un solde débiteur positif
        $creancesClients = array_filter($creancesClients, fn($c) => $c['solde'] > 0);

        // 2. Calcul des dettes fournisseurs
        $achatsImpayes = Achat::with(['fournisseur', 'pointDeVente'])
            ->whereHas('pointDeVente', function ($q) use ($entreprise) {
                $q->where('entreprise_id', $entreprise->id);
            })
            ->where('etape', 'Facture')
            ->whereIn('statut', ['Crédit', 'Avance'])
            ->get();

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

            $regle = TresorerieJournal::where('reference_document', $achat->numero_facture)->sum('montant_sortie');

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

        return view('admin::comptabilite.creances', compact('creancesClients', 'dettesFournisseurs'));
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
                $payments = TresorerieJournal::where('reference_document', $inv->numero_facture)
                    ->orderBy('date_operation')
                    ->get();

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
                $payments = TresorerieJournal::where('reference_document', $inv->numero_facture)
                    ->orderBy('date_operation')
                    ->get();

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

        $entreprise = Auth::user()->entreprise;
        $pdvId = session('point_de_vente_actif_id') ?? Auth::user()->point_de_vente_id;

        DB::transaction(function () use ($request, $pdvId, $entreprise) {
            $numFacture = $request->numero_facture;
            $montant = floatval($request->montant);
            $mode = $request->mode_paiement;
            $date = $request->date_operation;

            if ($request->type === 'client') {
                $vente = Vente::where('numero_facture', $numFacture)->firstOrFail();
                
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
                    'montant_entree'     => $montant,
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $soldeActuel + $montant,
                    'reference_document' => $numFacture,
                ]);

                // 2. Générer l'écriture comptable
                ComptabiliteService::genererEcritureReglementVente($vente, $montant, $mode, $date);

                // 3. Mettre à jour le statut de la facture
                $nouveauPaye = $dejaPaye + $montant;
                if ($nouveauPaye >= $vente->montant_ttc) {
                    $vente->update(['statut' => 'Payé']);
                } else {
                    $vente->update(['statut' => 'Avance']);
                }
            } else {
                $achat = Achat::where('numero_facture', $numFacture)->firstOrFail();
                
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
                    'montant_entree'     => 0,
                    'montant_sortie'     => $montant,
                    'solde_resultat'     => $soldeActuel - $montant,
                    'reference_document' => $numFacture,
                ]);

                // 2. Générer l'écriture comptable
                ComptabiliteService::genererEcritureReglementAchat($achat, $montant, $mode, $date);

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
        $query = \App\Modules\Admin\Modeles\PlanComptable::query();

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
        $request->validate([
            'numero'  => ['required', 'string', 'max:20', 'unique:plan_comptable,numero'],
            'libelle' => ['required', 'string', 'max:255'],
        ], [
            'numero.required' => 'Le numéro de compte est obligatoire.',
            'numero.unique'   => 'Ce numéro de compte existe déjà.',
            'libelle.required' => 'Le libellé est obligatoire.',
        ]);

        \App\Modules\Admin\Modeles\PlanComptable::create($request->only(['numero', 'libelle']));

        return back()->with('succes', 'Compte comptable créé avec succès.');
    }
}
