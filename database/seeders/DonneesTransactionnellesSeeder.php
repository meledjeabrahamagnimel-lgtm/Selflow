<?php

namespace Database\Seeders;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\AchatDetail;
use App\Modules\Admin\Modeles\B2bNegotiation;
use App\Modules\Admin\Modeles\BonLivraison;
use App\Modules\Admin\Modeles\BonLivraisonDetail;
use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\OrdreProduction;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Authentification\Modeles\Utilisateur;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\VenteDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DonneesTransactionnellesSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->command->info('🧹 Nettoyage des données transactionnelles...');

        // Purge des tables transactionnelles
        DB::table('mouvements_stock')->truncate();
        DB::table('tresorerie_journal')->truncate();
        DB::table('ecritures_comptables')->truncate();
        DB::table('b2b_negotiations')->truncate();
        DB::table('bon_livraison_details')->truncate();
        DB::table('bons_livraison')->truncate();
        DB::table('vente_details')->truncate();
        DB::table('ventes')->truncate();
        DB::table('achat_details')->truncate();
        DB::table('achats')->truncate();
        DB::table('ordres_production')->truncate();

        Schema::enableForeignKeyConstraints();

        $this->command->info('✨ Reconstitution des transactions SYSCOHADA enrichies...');

        $entreprise1 = Entreprise::first();
        $entreprise2 = Entreprise::skip(1)->first() ?? $entreprise1;

        if (!$entreprise1) {
            $this->command->error('Aucune entreprise disponible.');
            return;
        }

        $pdv1 = PointDeVente::where('entreprise_id', $entreprise1->id)->first();
        $pdv2 = PointDeVente::where('entreprise_id', $entreprise2->id)->first() ?? $pdv1;

        $user1 = Utilisateur::where('entreprise_id', $entreprise1->id)->first() ?? Utilisateur::first();
        $user2 = Utilisateur::where('entreprise_id', $entreprise2->id)->first() ?? $user1;

        $dateStr = date('dmy'); // jjmmaa (ex: 210726)

        // ─────────────────────────────────────────────────────────────────────
        // 1. CLIENTS & FOURNISSEURS & PRODUITS
        // ─────────────────────────────────────────────────────────────────────
        $clientsE1 = Client::where('entreprise_id', $entreprise1->id)->get();
        if ($clientsE1->isEmpty()) {
            $c1 = Client::create(['entreprise_id' => $entreprise1->id, 'nom' => 'CIVIL CONSTRUCTION SARL', 'telephone' => '+225 07001122', 'email' => 'contact@civil-const.ci', 'compte_comptable' => '411100']);
            $c2 = Client::create(['entreprise_id' => $entreprise1->id, 'nom' => 'SOCIETE IVOIRIENNE DE BATIMENT', 'telephone' => '+225 07003344', 'email' => 'contact@sib-ci.com', 'compte_comptable' => '411200']);
            $clientsE1 = collect([$c1, $c2]);
        }
        $client1 = $clientsE1->first();
        $client2 = $clientsE1->skip(1)->first() ?? $client1;

        $fournisseursE1 = Fournisseur::where('entreprise_id', $entreprise1->id)->get();
        if ($fournisseursE1->isEmpty()) {
            $f1 = Fournisseur::create(['entreprise_id' => $entreprise1->id, 'nom' => 'ACIÉRIE ET MÉTALLURGIE CI', 'telephone' => '+225 27210099', 'email' => 'commercial@acierie.ci', 'compte_comptable' => '401100']);
            $f2 = Fournisseur::create(['entreprise_id' => $entreprise1->id, 'nom' => 'COMPOSITES ET PLASTIQUES D ABIDJAN', 'telephone' => '+225 27228811', 'email' => 'contact@cpa.ci', 'compte_comptable' => '401200']);
            $fournisseursE1 = collect([$f1, $f2]);
        }
        $fourn1 = $fournisseursE1->first();
        $fourn2 = $fournisseursE1->skip(1)->first() ?? $fourn1;

        $produitsE1 = Produit::where('entreprise_id', $entreprise1->id)->get();
        $prodP1  = $produitsE1->where('type', 'produit_fini')->first() ?? $produitsE1->first();
        $prodP2  = $produitsE1->where('type', 'produit_fini')->skip(1)->first() ?? $prodP1;
        $prodMP1 = $produitsE1->where('type', 'matiere_premiere')->first() ?? $produitsE1->first();
        $prodMP2 = $produitsE1->where('type', 'matiere_premiere')->skip(1)->first() ?? $prodMP1;

        // ─────────────────────────────────────────────────────────────────────
        // 2. DEVIS & BONS DE COMMANDE
        // ─────────────────────────────────────────────────────────────────────
        $devisList = [
            ['ref' => "DEV-{$dateStr}-0001", 'client' => $client1, 'prod' => $prodP1, 'qte' => 10, 'px' => 50000, 'date' => now()->subDays(30)],
            ['ref' => "DEV-{$dateStr}-0002", 'client' => $client2, 'prod' => $prodP2, 'qte' => 25, 'px' => 35000, 'date' => now()->subDays(25)],
            ['ref' => "BC-{$dateStr}-0001", 'client' => $client1, 'prod' => $prodP1, 'qte' => 15, 'px' => 50000, 'date' => now()->subDays(20)],
            ['ref' => "BC-{$dateStr}-0002", 'client' => $client2, 'prod' => $prodP2, 'qte' => 40, 'px' => 35000, 'date' => now()->subDays(18)],
        ];

        foreach ($devisList as $d) {
            $ht  = $d['qte'] * $d['px'];
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            $isBcm = str_starts_with($d['ref'], 'BC-');
            $v = Vente::create([
                'point_de_vente_id' => $pdv1->id, 'utilisateur_id' => $user1->id, 'client_id' => $d['client']->id,
                'numero_facture' => $d['ref'], 'date_vente' => $d['date'], 'mode_paiement' => 'crédit',
                'montant_ht' => $ht, 'montant_tva' => $tva, 'montant_ttc' => $ttc,
                'statut' => 'Non payé', 'etape' => $isBcm ? 'Bon de Commande' : 'Devis', 'type_facture' => 'Doit',
            ]);
            VenteDetail::create(['vente_id' => $v->id, 'produit_id' => $d['prod']->id, 'quantite' => $d['qte'], 'prix_unitaire' => $d['px'], 'montant_tva' => $tva, 'montant_ttc' => $ttc]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 3. FACTURES DE VENTES (Format VTE-jjmmaa-xxxx)
        // ─────────────────────────────────────────────────────────────────────
        $ventesFactures = [
            ['ref' => "VTE-{$dateStr}-0001", 'client' => $client1, 'prod' => $prodP1, 'qte' => 20, 'px' => 50000, 'mode' => 'espèces',  'statut' => 'Payé',     'days' => 22],
            ['ref' => "VTE-{$dateStr}-0002", 'client' => $client2, 'prod' => $prodP2, 'qte' => 30, 'px' => 35000, 'mode' => 'virement', 'statut' => 'Payé',     'days' => 17],
            ['ref' => "VTE-{$dateStr}-0003", 'client' => $client1, 'prod' => $prodP1, 'qte' => 15, 'px' => 52000, 'mode' => 'crédit',   'statut' => 'Non payé', 'days' => 12],
            ['ref' => "VTE-{$dateStr}-0004", 'client' => $client2, 'prod' => $prodP2, 'qte' => 50, 'px' => 35000, 'mode' => 'crédit',   'statut' => 'Non payé', 'days' => 8],
            ['ref' => "VTE-{$dateStr}-0005", 'client' => $client1, 'prod' => $prodP1, 'qte' => 12, 'px' => 50000, 'mode' => 'espèces',  'statut' => 'Payé',     'days' => 3],
        ];

        $ventesObjets = [];
        foreach ($ventesFactures as $vf) {
            $ht  = $vf['qte'] * $vf['px'];
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            $date = now()->subDays($vf['days']);

            $v = Vente::create([
                'point_de_vente_id' => $pdv1->id, 'utilisateur_id' => $user1->id, 'client_id' => $vf['client']->id,
                'numero_facture' => $vf['ref'], 'date_vente' => $date, 'mode_paiement' => $vf['mode'],
                'montant_ht' => $ht, 'montant_tva' => $tva, 'montant_ttc' => $ttc,
                'statut' => $vf['statut'], 'etape' => 'Facture', 'type_facture' => 'Doit',
            ]);
            VenteDetail::create(['vente_id' => $v->id, 'produit_id' => $vf['prod']->id, 'quantite' => $vf['qte'], 'prix_unitaire' => $vf['px'], 'montant_tva' => $tva, 'montant_ttc' => $ttc]);
            $this->creerEcrituresVente($v, $vf['client']);

            if ($vf['statut'] === 'Payé') {
                $modeClean = ucfirst($vf['mode']);
                $banque = strtolower($vf['mode']) === 'virement' ? 'BICICI — Compte courant' : null;
                $this->creerOperationTresorerie($pdv1, $user1, $date, 'recette', 'Encaissement vente ' . $vf['ref'], $modeClean, $banque, $ttc, 0, $vf['ref']);
            }

            MouvementStock::create([
                'point_de_vente_id' => $pdv1->id, 'produit_id' => $vf['prod']->id, 'utilisateur_id' => $user1->id,
                'type_mouvement' => 'sortie', 'sous_type' => 'vente', 'quantite' => $vf['qte'], 'stock_avant' => 100, 'stock_apres' => 100 - $vf['qte'],
                'reference_document' => $vf['ref'],
            ]);

            $ventesObjets[$vf['ref']] = $v;
        }

        // ─────────────────────────────────────────────────────────────────────
        // 4. AVOIRS CLIENTS (Format AVO-VTE-jjmmaa-xxxx — 5 Avoirs)
        // ─────────────────────────────────────────────────────────────────────
        $avoirsClients = [
            ['ref' => "AVO-VTE-{$dateStr}-0001", 'parent_ref' => "VTE-{$dateStr}-0001", 'client' => $client1, 'prod' => $prodP1, 'qte' => 2, 'px' => 50000, 'motif' => 'Retour marchandise défectueuse', 'days' => 15],
            ['ref' => "AVO-VTE-{$dateStr}-0002", 'parent_ref' => "VTE-{$dateStr}-0002", 'client' => $client2, 'prod' => $prodP2, 'qte' => 3, 'px' => 35000, 'motif' => 'Remise commerciale sur lot livré', 'days' => 11],
            ['ref' => "AVO-VTE-{$dateStr}-0003", 'parent_ref' => "VTE-{$dateStr}-0003", 'client' => $client1, 'prod' => $prodP1, 'qte' => 1, 'px' => 52000, 'motif' => 'Erreur de facturation de prix', 'days' => 7],
            ['ref' => "AVO-VTE-{$dateStr}-0004", 'parent_ref' => "VTE-{$dateStr}-0004", 'client' => $client2, 'prod' => $prodP2, 'qte' => 5, 'px' => 35000, 'motif' => 'Avoir pour dommage de transport', 'days' => 4],
            ['ref' => "AVO-VTE-{$dateStr}-0005", 'parent_ref' => "VTE-{$dateStr}-0005", 'client' => $client1, 'prod' => $prodP1, 'qte' => 2, 'px' => 50000, 'motif' => 'Retour client après recette', 'days' => 1],
        ];

        foreach ($avoirsClients as $avc) {
            $ht  = - ($avc['qte'] * $avc['px']);
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            $date = now()->subDays($avc['days']);
            $parent = $ventesObjets[$avc['parent_ref']] ?? null;

            $v = Vente::create([
                'parent_id'         => $parent?->id,
                'point_de_vente_id' => $pdv1->id, 'utilisateur_id' => $user1->id, 'client_id' => $avc['client']->id,
                'numero_facture'    => $avc['ref'], 'date_vente' => $date, 'mode_paiement' => 'espèces',
                'montant_ht'        => $ht, 'montant_tva' => $tva, 'montant_ttc' => $ttc,
                'statut'            => 'Payé', 'etape' => 'Facture', 'type_facture' => 'Avoir', 'raison_avoir' => $avc['motif'],
            ]);
            VenteDetail::create(['vente_id' => $v->id, 'produit_id' => $avc['prod']->id, 'quantite' => $avc['qte'], 'prix_unitaire' => $avc['px'], 'montant_tva' => $tva, 'montant_ttc' => $ttc]);
            $this->creerEcrituresAvoirVente($v, $avc['client']);
            $this->creerOperationTresorerie($pdv1, $user1, $date, 'depense', 'Remboursement avoir client ' . $avc['ref'], 'Espèces', null, 0, abs($ttc), $avc['ref']);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 5. FACTURES D'ACHATS (Format ACH-jjmmaa-xxxx)
        // ─────────────────────────────────────────────────────────────────────
        $achatsFactures = [
            ['ref' => "ACH-{$dateStr}-0001", 'fourn' => $fourn1, 'prod' => $prodMP1, 'qte' => 200, 'px' => 10000, 'mode' => 'virement', 'statut' => 'Payé',     'days' => 24],
            ['ref' => "ACH-{$dateStr}-0002", 'fourn' => $fourn2, 'prod' => $prodMP2, 'qte' => 150, 'px' => 8000,  'mode' => 'crédit',   'statut' => 'Non payé', 'days' => 19],
            ['ref' => "ACH-{$dateStr}-0003", 'fourn' => $fourn1, 'prod' => $prodMP1, 'qte' => 100, 'px' => 10500, 'mode' => 'virement', 'statut' => 'Payé',     'days' => 14],
            ['ref' => "ACH-{$dateStr}-0004", 'fourn' => $fourn2, 'prod' => $prodMP2, 'qte' => 300, 'px' => 7800,  'mode' => 'crédit',   'statut' => 'Non payé', 'days' => 9],
            ['ref' => "ACH-{$dateStr}-0005", 'fourn' => $fourn1, 'prod' => $prodMP1, 'qte' => 80,  'px' => 10000, 'mode' => 'espèces',  'statut' => 'Payé',     'days' => 2],
        ];

        $achatsObjets = [];
        foreach ($achatsFactures as $af) {
            $ht  = $af['qte'] * $af['px'];
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            $date = now()->subDays($af['days']);

            $a = Achat::create([
                'point_de_vente_id' => $pdv1->id, 'utilisateur_id' => $user1->id, 'fournisseur_id' => $af['fourn']->id,
                'numero_facture' => $af['ref'], 'date_achat' => $date, 'mode_paiement' => $af['mode'],
                'montant_ht' => $ht, 'montant_tva' => $tva, 'montant_ttc' => $ttc,
                'statut' => $af['statut'], 'etape' => 'Facture', 'type_facture' => 'Doit',
            ]);
            AchatDetail::create(['achat_id' => $a->id, 'produit_id' => $af['prod']->id, 'quantite' => $af['qte'], 'prix_unitaire' => $af['px'], 'montant_tva' => $tva, 'montant_ttc' => $ttc]);
            $this->creerEcrituresAchat($a, $af['fourn']);

            if ($af['statut'] === 'Payé') {
                $modeClean = ucfirst($af['mode']);
                $banque = strtolower($af['mode']) === 'virement' ? 'BICICI — Compte courant' : null;
                $this->creerOperationTresorerie($pdv1, $user1, $date, 'depense', 'Règlement fournisseur ' . $af['ref'], $modeClean, $banque, 0, $ttc, $af['ref']);
            }

            $achatsObjets[$af['ref']] = $a;
        }

        // ─────────────────────────────────────────────────────────────────────
        // 6. AVOIRS FOURNISSEURS (Format AVO-ACH-jjmmaa-xxxx — 5 Avoirs)
        // ─────────────────────────────────────────────────────────────────────
        $avoirsFournisseurs = [
            ['ref' => "AVO-ACH-{$dateStr}-0001", 'parent_ref' => "ACH-{$dateStr}-0001", 'fourn' => $fourn1, 'prod' => $prodMP1, 'qte' => 20, 'px' => 10000, 'motif' => 'Remise accordée après contrôle qualité', 'days' => 18],
            ['ref' => "AVO-ACH-{$dateStr}-0002", 'parent_ref' => "ACH-{$dateStr}-0002", 'fourn' => $fourn2, 'prod' => $prodMP2, 'qte' => 15, 'px' => 8000,  'motif' => 'Retour de pièces non conformes', 'days' => 13],
            ['ref' => "AVO-ACH-{$dateStr}-0003", 'parent_ref' => "ACH-{$dateStr}-0003", 'fourn' => $fourn1, 'prod' => $prodMP1, 'qte' => 10, 'px' => 10500, 'motif' => 'Avoir pour surcoût d emballage', 'days' => 10],
            ['ref' => "AVO-ACH-{$dateStr}-0004", 'parent_ref' => "ACH-{$dateStr}-0004", 'fourn' => $fourn2, 'prod' => $prodMP2, 'qte' => 25, 'px' => 7800,  'motif' => 'Escompte de règlement anticipé', 'days' => 6],
            ['ref' => "AVO-ACH-{$dateStr}-0005", 'parent_ref' => "ACH-{$dateStr}-0005", 'fourn' => $fourn1, 'prod' => $prodMP1, 'qte' => 5,  'px' => 10000, 'motif' => 'Remboursement partiel transport', 'days' => 1],
        ];

        foreach ($avoirsFournisseurs as $avf) {
            $ht  = - ($avf['qte'] * $avf['px']);
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            $date = now()->subDays($avf['days']);
            $parent = $achatsObjets[$avf['parent_ref']] ?? null;

            $a = Achat::create([
                'parent_id'         => $parent?->id,
                'point_de_vente_id' => $pdv1->id, 'utilisateur_id' => $user1->id, 'fournisseur_id' => $avf['fourn']->id,
                'numero_facture'    => $avf['ref'], 'date_achat' => $date, 'mode_paiement' => 'virement',
                'montant_ht'        => $ht, 'montant_tva' => $tva, 'montant_ttc' => $ttc,
                'statut'            => 'Payé', 'etape' => 'Facture', 'type_facture' => 'Avoir', 'raison_avoir' => $avf['motif'],
            ]);
            AchatDetail::create(['achat_id' => $a->id, 'produit_id' => $avf['prod']->id, 'quantite' => $avf['qte'], 'prix_unitaire' => $avf['px'], 'montant_tva' => $tva, 'montant_ttc' => $ttc]);
            $this->creerEcrituresAvoirAchat($a, $avf['fourn']);
            $this->creerOperationTresorerie($pdv1, $user1, $date, 'recette', 'Remboursement avoir fournisseur ' . $avf['ref'], 'Virement', 'BICICI — Compte courant', abs($ttc), 0, $avf['ref']);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 7. ORDRES DE PRODUCTION (Format OD-jjmmaa-xxxx — 8 Opérations OD)
        // ─────────────────────────────────────────────────────────────────────
        $ordresProduction = [
            ['ref' => "OD-{$dateStr}-0001", 'mp' => $prodMP1, 'pf' => $prodP1, 'qte_mp' => 50,  'qte_pf' => 10, 'days' => 26],
            ['ref' => "OD-{$dateStr}-0002", 'mp' => $prodMP2, 'pf' => $prodP2, 'qte_mp' => 80,  'qte_pf' => 16, 'days' => 21],
            ['ref' => "OD-{$dateStr}-0003", 'mp' => $prodMP1, 'pf' => $prodP1, 'qte_mp' => 120, 'qte_pf' => 24, 'days' => 16],
            ['ref' => "OD-{$dateStr}-0004", 'mp' => $prodMP2, 'pf' => $prodP2, 'qte_mp' => 60,  'qte_pf' => 12, 'days' => 14],
            ['ref' => "OD-{$dateStr}-0005", 'mp' => $prodMP1, 'pf' => $prodP1, 'qte_mp' => 200, 'qte_pf' => 40, 'days' => 11],
            ['ref' => "OD-{$dateStr}-0006", 'mp' => $prodMP2, 'pf' => $prodP2, 'qte_mp' => 90,  'qte_pf' => 18, 'days' => 8],
            ['ref' => "OD-{$dateStr}-0007", 'mp' => $prodMP1, 'pf' => $prodP1, 'qte_mp' => 150, 'qte_pf' => 30, 'days' => 5],
            ['ref' => "OD-{$dateStr}-0008", 'mp' => $prodMP2, 'pf' => $prodP2, 'qte_mp' => 100, 'qte_pf' => 20, 'days' => 2],
        ];

        foreach ($ordresProduction as $op) {
            $date = now()->subDays($op['days']);
            $this->creerOperationDiversesProduction($pdv1, $user1, $op['mp'], $op['pf'], $op['ref'], $op['qte_mp'], $op['qte_pf'], $date);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 8. NÉGOCIATIONS B2B
        // ─────────────────────────────────────────────────────────────────────
        if ($entreprise1->id !== $entreprise2->id) {
            B2bNegotiation::create([
                'entreprise_client_id'      => $entreprise1->id,
                'entreprise_fournisseur_id' => $entreprise2->id,
                'statut'                    => 'en_cours',
                'prix_final'                => 4500000,
                'type_facturation'          => 'normal',
                'produits_demandes'         => json_encode([['nom' => 'Matériaux industriels', 'quantite' => 50, 'prix_souhaite' => 90000]]),
                'historique_discussions'    => json_encode([['auteur' => 'Maison Dupont', 'message' => 'Demande de cotation annuelle', 'date' => now()->toIso8601String()]]),
            ]);
        }

        $this->command->info('✅ Base transactionnelle SYSCOHADA complète recréée avec succès !');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS ÉCRITURES COMPTABLES & TRÉSORERIE
    // ─────────────────────────────────────────────────────────────────────────
    private function creerEcrituresVente(Vente $v, Client $client): void
    {
        $codeJournal = 'VTE';
        $ref = $v->numero_facture;

        // 1. Débit Client 411 (TTC)
        EcritureComptable::create([
            'entreprise_id'     => $v->pointDeVente->entreprise_id,
            'point_de_vente_id'  => $v->point_de_vente_id,
            'date_ecriture'     => $v->date_vente,
            'libelle'           => "Vente Facture {$ref} — {$client->nom}",
            'reference_document'=> $ref,
            'code_journal'      => $codeJournal,
            'compte_debit'      => $client->compte_comptable ?? '411100',
            'compte_credit'     => null,
            'debit'             => $v->montant_ttc,
            'credit'            => 0,
        ]);

        // 2. Crédit Vente 701 (HT)
        EcritureComptable::create([
            'entreprise_id'     => $v->pointDeVente->entreprise_id,
            'point_de_vente_id'  => $v->point_de_vente_id,
            'date_ecriture'     => $v->date_vente,
            'libelle'           => "Produit des ventes — Facture {$ref}",
            'reference_document'=> $ref,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => '701100',
            'debit'             => 0,
            'credit'            => $v->montant_ht,
        ]);

        // 3. Crédit TVA Facturée 443100
        if ($v->montant_tva > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $v->pointDeVente->entreprise_id,
                'point_de_vente_id'  => $v->point_de_vente_id,
                'date_ecriture'     => $v->date_vente,
                'libelle'           => "TVA Facturée — Facture {$ref}",
                'reference_document'=> $ref,
                'code_journal'      => $codeJournal,
                'compte_debit'      => null,
                'compte_credit'     => '443100',
                'debit'             => 0,
                'credit'            => $v->montant_tva,
            ]);
        }
    }

    private function creerEcrituresAvoirVente(Vente $av, Client $client): void
    {
        $codeJournal = 'VTE';
        $ref = $av->numero_facture;
        $ht  = abs($av->montant_ht);
        $tva = abs($av->montant_tva);
        $ttc = abs($av->montant_ttc);

        // 1. Débit Avoir sur vente 701 (HT)
        EcritureComptable::create([
            'entreprise_id'     => $av->pointDeVente->entreprise_id,
            'point_de_vente_id'  => $av->point_de_vente_id,
            'date_ecriture'     => $av->date_vente,
            'libelle'           => "Avoir sur vente — Facture {$ref}",
            'reference_document'=> $ref,
            'code_journal'      => $codeJournal,
            'compte_debit'      => '701100',
            'compte_credit'     => null,
            'debit'             => $ht,
            'credit'            => 0,
        ]);

        // 2. Débit TVA Facturée 443100
        if ($tva > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $av->pointDeVente->entreprise_id,
                'point_de_vente_id'  => $av->point_de_vente_id,
                'date_ecriture'     => $av->date_vente,
                'libelle'           => "Régularisation TVA Avoir — {$ref}",
                'reference_document'=> $ref,
                'code_journal'      => $codeJournal,
                'compte_debit'      => '443100',
                'compte_credit'     => null,
                'debit'             => $tva,
                'credit'            => 0,
            ]);
        }

        // 3. Crédit Client 411 (TTC)
        EcritureComptable::create([
            'entreprise_id'     => $av->pointDeVente->entreprise_id,
            'point_de_vente_id'  => $av->point_de_vente_id,
            'date_ecriture'     => $av->date_vente,
            'libelle'           => "Avoir accordé client — Facture {$ref}",
            'reference_document'=> $ref,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => $client->compte_comptable ?? '411100',
            'debit'             => 0,
            'credit'            => $ttc,
        ]);
    }

    private function creerEcrituresAchat(Achat $a, Fournisseur $fourn): void
    {
        $codeJournal = 'ACH';
        $ref = $a->numero_facture;

        // 1. Débit Achat 601 (HT)
        EcritureComptable::create([
            'entreprise_id'     => $a->pointDeVente->entreprise_id,
            'point_de_vente_id'  => $a->point_de_vente_id,
            'date_ecriture'     => $a->date_achat,
            'libelle'           => "Achat matières/marchandises — Facture {$ref}",
            'reference_document'=> $ref,
            'code_journal'      => $codeJournal,
            'compte_debit'      => '601100',
            'compte_credit'     => null,
            'debit'             => $a->montant_ht,
            'credit'            => 0,
        ]);

        // 2. Débit TVA Déductible 445200
        if ($a->montant_tva > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $a->pointDeVente->entreprise_id,
                'point_de_vente_id'  => $a->point_de_vente_id,
                'date_ecriture'     => $a->date_achat,
                'libelle'           => "TVA Déductible sur Achat — Facture {$ref}",
                'reference_document'=> $ref,
                'code_journal'      => $codeJournal,
                'compte_debit'      => '445200',
                'compte_credit'     => null,
                'debit'             => $a->montant_tva,
                'credit'            => 0,
            ]);
        }

        // 3. Crédit Fournisseur 401 (TTC)
        EcritureComptable::create([
            'entreprise_id'     => $a->pointDeVente->entreprise_id,
            'point_de_vente_id'  => $a->point_de_vente_id,
            'date_ecriture'     => $a->date_achat,
            'libelle'           => "Dette Fournisseur {$fourn->nom} — Facture {$ref}",
            'reference_document'=> $ref,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => $fourn->compte_comptable ?? '401100',
            'debit'             => 0,
            'credit'            => $a->montant_ttc,
        ]);
    }

    private function creerEcrituresAvoirAchat(Achat $av, Fournisseur $fourn): void
    {
        $codeJournal = 'ACH';
        $ref = $av->numero_facture;
        $ht  = abs($av->montant_ht);
        $tva = abs($av->montant_tva);
        $ttc = abs($av->montant_ttc);

        // 1. Débit Fournisseur 401 (TTC)
        EcritureComptable::create([
            'entreprise_id'     => $av->pointDeVente->entreprise_id,
            'point_de_vente_id'  => $av->point_de_vente_id,
            'date_ecriture'     => $av->date_achat,
            'libelle'           => "Annulation dette Fournisseur — Facture {$ref}",
            'reference_document'=> $ref,
            'code_journal'      => $codeJournal,
            'compte_debit'      => $fourn->compte_comptable ?? '401100',
            'compte_credit'     => null,
            'debit'             => $ttc,
            'credit'            => 0,
        ]);

        // 2. Crédit Achat 601 (HT)
        EcritureComptable::create([
            'entreprise_id'     => $av->pointDeVente->entreprise_id,
            'point_de_vente_id'  => $av->point_de_vente_id,
            'date_ecriture'     => $av->date_achat,
            'libelle'           => "Avoir obtenu sur Achat — Facture {$ref}",
            'reference_document'=> $ref,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => '601100',
            'debit'             => 0,
            'credit'            => $ht,
        ]);

        // 3. Crédit TVA Déductible 445200
        if ($tva > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $av->pointDeVente->entreprise_id,
                'point_de_vente_id'  => $av->point_de_vente_id,
                'date_ecriture'     => $av->date_achat,
                'libelle'           => "Régularisation TVA Déductible Avoir — {$ref}",
                'reference_document'=> $ref,
                'code_journal'      => $codeJournal,
                'compte_debit'      => null,
                'compte_credit'     => '445200',
                'debit'             => 0,
                'credit'            => $tva,
            ]);
        }
    }

    private function creerOperationDiversesProduction(
        PointDeVente $pdv,
        Utilisateur $user,
        Produit $mp,
        Produit $pf,
        string $refOd,
        float $qteMp,
        float $qtePf,
        $date
    ): void {
        $codeJournal = 'OD';
        $coutMp = $qteMp * $mp->prix_achat;
        $valeurPf = $qtePf * ($pf->prix_achat > 0 ? $pf->prix_achat : $mp->prix_achat * 1.5);

        // ── 1. Ordre de production dans la BD ──
        OrdreProduction::create([
            'entreprise_id'     => $pdv->entreprise_id,
            'point_de_vente_id' => $pdv->id,
            'code_ordre'        => $refOd,
            'produit_fini_id'   => $pf->id,
            'quantite_cible'    => $qtePf,
            'statut'            => 'Terminé',
            'date_production'   => $date,
        ]);

        // ── 2. Écritures comptables OD SYSCOHADA (Transformation MP -> PF) ──
        // Sortie MP : Débit 603200 (Variation MP) / Crédit 311000 (Stock MP)
        EcritureComptable::create([
            'entreprise_id'     => $pdv->entreprise_id,
            'point_de_vente_id'  => $pdv->id,
            'date_ecriture'     => $date,
            'libelle'           => "Consommation MP {$mp->nom} — {$refOd}",
            'reference_document'=> $refOd,
            'code_journal'      => $codeJournal,
            'compte_debit'      => '603200',
            'compte_credit'     => null,
            'debit'             => $coutMp,
            'credit'            => 0,
        ]);
        EcritureComptable::create([
            'entreprise_id'     => $pdv->entreprise_id,
            'point_de_vente_id'  => $pdv->id,
            'date_ecriture'     => $date,
            'libelle'           => "Sortie Stock MP {$mp->nom} — {$refOd}",
            'reference_document'=> $refOd,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => '311000',
            'debit'             => 0,
            'credit'            => $coutMp,
        ]);

        // Entrée PF : Débit 312000 (Stock PF) / Crédit 731100 (Variation Produite)
        EcritureComptable::create([
            'entreprise_id'     => $pdv->entreprise_id,
            'point_de_vente_id'  => $pdv->id,
            'date_ecriture'     => $date,
            'libelle'           => "Entrée Stock PF {$pf->nom} — {$refOd}",
            'reference_document'=> $refOd,
            'code_journal'      => $codeJournal,
            'compte_debit'      => '312000',
            'compte_credit'     => null,
            'debit'             => $valeurPf,
            'credit'            => 0,
        ]);
        EcritureComptable::create([
            'entreprise_id'     => $pdv->entreprise_id,
            'point_de_vente_id'  => $pdv->id,
            'date_ecriture'     => $date,
            'libelle'           => "Production Stockée PF {$pf->nom} — {$refOd}",
            'reference_document'=> $refOd,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => '731100',
            'debit'             => 0,
            'credit'            => $valeurPf,
        ]);

        // ── 3. Mouvements de stock ──
        MouvementStock::create([
            'point_de_vente_id' => $pdv->id, 'produit_id' => $mp->id, 'utilisateur_id' => $user->id,
            'type_mouvement' => 'sortie', 'sous_type' => 'production', 'quantite' => $qteMp, 'stock_avant' => 500, 'stock_apres' => 500 - $qteMp,
            'reference_document' => $refOd,
        ]);
        MouvementStock::create([
            'point_de_vente_id' => $pdv->id, 'produit_id' => $pf->id, 'utilisateur_id' => $user->id,
            'type_mouvement' => 'entrée', 'sous_type' => 'production', 'quantite' => $qtePf, 'stock_avant' => 10, 'stock_apres' => 10 + $qtePf,
            'reference_document' => $refOd,
        ]);
    }

    private function creerOperationTresorerie(
        PointDeVente $pdv,
        Utilisateur $user,
        $date,
        string $type,
        string $libelle,
        string $mode,
        ?string $banque,
        float $entree,
        float $sortie,
        string $refDoc
    ): void {
        $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pdv->id)->orderByDesc('id')->value('solde_resultat') ?? 0;
        $nvSolde     = $type === 'recette' ? ($soldeActuel + $entree) : ($soldeActuel - $sortie);

        TresorerieJournal::create([
            'point_de_vente_id'  => $pdv->id,
            'utilisateur_id'     => $user->id,
            'date_operation'     => $date,
            'type_operation'     => $type,
            'libelle'            => $libelle,
            'mode_paiement'      => $mode,
            'moyen_bancaire'     => $banque,
            'reference_paiement' => $refDoc,
            'montant_entree'     => $entree,
            'montant_sortie'     => $sortie,
            'solde_resultat'     => $nvSolde,
            'reference_document' => $refDoc,
        ]);
    }
}

