<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\OrdreProduction;

/**
 * ComptabiliteService — Moteur d'écritures comptables SYSCOHADA révisé (Côte d'Ivoire)
 *
 * Comptes de référence utilisés :
 *  411xxx  Clients (tiers individuels)
 *  401xxx  Fournisseurs (tiers individuels)
 *  70xxxx  Produits des activités ordinaires (Classe 7)
 *  60xxxx  Achats de marchandises / matières (Classe 6)
 *  443100  État, TVA facturée sur ventes (collectée)
 *  445200  État, TVA déductible sur achats courants
 *  521xxx  Banques (établissements de crédit)
 *  571xxx  Caisse
 *  731100  Variation des stocks de produits fabriqués (production)
 *  603200  Variation des stocks de matières premières
 */
class ComptabiliteService
{
    /**
     * Génère les écritures de facturation pour une vente.
     * Débit Client (TTC) vs Crédit Vente (HT) & Crédit TVA (Groupés par Compte)
     */
    public static function genererEcritureFactureVente(Vente $vente): void
    {
        $entrepriseId = $vente->pointDeVente->entreprise_id;
        $pdvId = $vente->point_de_vente_id;
        $date = $vente->date_vente ? $vente->date_vente->toDateString() : now()->toDateString();
        $refDoc = $vente->numero_facture;

        // Trouver le code journal vente
        $codeJournal = CodeJournal::where('entreprise_id', $entrepriseId)
            ->where('type', 'Vente')
            ->value('code') ?? 'VTE';

        $compteClient = $vente->client?->numero_tiers ?? $vente->client?->compte_comptable ?? '411100';

        // 1. Débit Client (TTC)
        EcritureComptable::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'     => $date,
            'libelle'           => $refDoc . '/Facturation Vente',
            'reference_document'=> $refDoc,
            'code_journal'      => $codeJournal,
            'compte_debit'      => $compteClient,
            'compte_credit'     => null,
            'debit'             => $vente->montant_ttc,
            'credit'            => 0,
        ]);

        // Calcul de la remise globale au prorata pour chaque produit
        $pourcentageRemise = ($vente->remise > 0 && $vente->montant_ht > 0) 
            ? ($vente->remise / $vente->montant_ht) 
            : 0;

        // Regroupement par compte de vente
        $ventilation = [];
        foreach ($vente->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            if ($pourcentageRemise > 0) {
                $ht = $ht - ($ht * $pourcentageRemise);
            }

            if ($ht > 0) {
                $compte = $detail->produit?->compte_vente ?? '701100';
                if (!isset($ventilation[$compte])) {
                    $ventilation[$compte] = 0;
                }
                $ventilation[$compte] += $ht;
            }
        }

        // 2. Crédit Vente par compte (HT Net agrégé)
        foreach ($ventilation as $compte => $montantHt) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . ' / Vente suivant détail - Compte ' . $compte,
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => null,
                'compte_credit'     => $compte,
                'debit'             => 0,
                'credit'            => $montantHt,
            ]);
        }

        // 3. Crédit TVA Collectée (si active)
        if ($vente->montant_tva > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/TVA Collectée Vente',
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => null,
                'compte_credit'     => '443100',
                'debit'             => 0,
                'credit'            => $vente->montant_tva,
            ]);
        }
    }

    /**
     * Génère les écritures de facturation pour un achat.
     * Crédit Fournisseur (TTC) vs Débit Achat (HT) & Débit TVA (Groupés par Compte)
     */
    public static function genererEcritureFactureAchat(Achat $achat): void
    {
        $entrepriseId = $achat->pointDeVente->entreprise_id;
        $pdvId = $achat->point_de_vente_id;
        $date = $achat->date_achat ? $achat->date_achat->toDateString() : now()->toDateString();
        $refDoc = $achat->numero_facture;

        // Trouver le code journal achat
        $codeJournal = CodeJournal::where('entreprise_id', $entrepriseId)
            ->where('type', 'Achat')
            ->value('code') ?? 'ACH';

        $compteFournisseur = $achat->fournisseur?->numero_tiers ?? $achat->fournisseur?->compte_comptable ?? '401100';

        // 1. Crédit Fournisseur (TTC)
        EcritureComptable::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'     => $date,
            'libelle'           => $refDoc . '/Facturation Achat',
            'reference_document'=> $refDoc,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => $compteFournisseur,
            'debit'             => 0,
            'credit'            => $achat->montant_ttc,
        ]);

        // Regroupement par compte d'achat
        $ventilation = [];
        foreach ($achat->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            if ($ht > 0) {
                $compte = $detail->produit?->compte_achat ?? '601100';
                if (!isset($ventilation[$compte])) {
                    $ventilation[$compte] = 0;
                }
                $ventilation[$compte] += $ht;
            }
        }

        // 2. Débit Achat par compte (HT Net agrégé)
        foreach ($ventilation as $compte => $montantHt) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . ' / Achat suivant détail - Compte ' . $compte,
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => $compte,
                'compte_credit'     => null,
                'debit'             => $montantHt,
                'credit'            => 0,
            ]);
        }

        // 3. Débit TVA Déductible sur achats — Compte 445200 (SYSCOHADA CI révisé)
        // La TVA est calculée ligne par ligne depuis le taux de chaque produit.
        // On agrège toutes les TVA de toutes les lignes pour un seul crédit 401 TTC.
        $totalTvaCalculee = 0;
        foreach ($achat->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            $tauxTva = $detail->produit?->taux_tva ?? 0;
            if ($tauxTva > 0 && $ht > 0) {
                $totalTvaCalculee += round($ht * ($tauxTva / 100), 2);
            }
        }

        // Utiliser la TVA recalculée si > 0, sinon utiliser la TVA enregistrée sur la facture
        $montantTvaFinal = $totalTvaCalculee > 0 ? $totalTvaCalculee : (float)$achat->montant_tva;

        if ($montantTvaFinal > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/TVA Déductible Achat (445200)',
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => '445200', // SYSCOHADA CI : TVA récupérable sur achats courants
                'compte_credit'     => null,
                'debit'             => $montantTvaFinal,
                'credit'            => 0,
            ]);
        }
    }

    /**
     * Génère l'écriture de règlement client (vente).
     * Débit Caisse/Banque (Montant) vs Crédit Client (Montant)
     */
    public static function genererEcritureReglementVente(Vente $vente, float $montant, string $modePaiement, ?string $date = null, ?string $moyenBancaire = null, ?string $referencePaiement = null): void
    {
        if ($montant <= 0) return;

        $entrepriseId = $vente->pointDeVente->entreprise_id;
        $pdvId = $vente->point_de_vente_id;
        $date = $date ?? now()->toDateString();
        $refDoc = $vente->numero_facture;

        // Déterminer le journal et le compte financier
        $isBanque = str_starts_with(strtolower($modePaiement), 'banque');
        $codeJournal = 'CAI';
        $compteFinancier = '571000';
        if ($isBanque) {
            $parts = explode(' : ', $modePaiement);
            $intitule = isset($parts[1]) ? trim($parts[1]) : '';
            $journalObj = CodeJournal::where('entreprise_id', $entrepriseId)
                ->where('type', 'Banque')
                ->where('intitule', $intitule)
                ->first();
            if ($journalObj) {
                $codeJournal = $journalObj->code;
                $compteFinancier = $journalObj->compte;
            } else {
                $codeJournal = 'BQE';
                $compteFinancier = '521000';
            }
        }

        $compteClient = $vente->client?->numero_tiers ?? $vente->client?->compte_comptable ?? '411100';

        $vente->loadMissing('details.produit');
        $produits = [];
        foreach ($vente->details as $detail) {
            $nom = $detail->libelle_virtuel ?? $detail->produit?->nom;
            if ($nom) {
                $produits[] = $nom;
            }
        }
        $produitsStr = count($produits) > 0 ? implode(', ', array_unique($produits)) : 'Marchandises';

        $refPaiement = $referencePaiement ?? $vente->reference_paiement;
        $libellePaiement = 'Rglt/' . $refDoc;
        if ($refPaiement) {
            $libellePaiement .= '/' . $refPaiement;
        }
        $libellePaiement .= '/Vente ' . $produitsStr;

        // 1. Débit Banque/Caisse
        EcritureComptable::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'     => $date,
            'libelle'           => $libellePaiement,
            'reference_document'=> $refDoc,
            'code_journal'      => $codeJournal,
            'compte_debit'      => $compteFinancier,
            'compte_credit'     => null,
            'debit'             => $montant,
            'credit'            => 0,
        ]);

        // 2. Crédit Client
        EcritureComptable::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'     => $date,
            'libelle'           => $libellePaiement,
            'reference_document'=> $refDoc,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => $compteClient,
            'debit'             => 0,
            'credit'            => $montant,
        ]);
    }

    /**
     * Génère l'écriture de règlement fournisseur (achat).
     * Débit Fournisseur (Montant) vs Crédit Caisse/Banque (Montant)
     */
    public static function genererEcritureReglementAchat(Achat $achat, float $montant, string $modePaiement, ?string $date = null, ?string $moyenBancaire = null, ?string $referencePaiement = null): void
    {
        if ($montant <= 0) return;

        $entrepriseId = $achat->pointDeVente->entreprise_id;
        $pdvId = $achat->point_de_vente_id;
        $date = $date ?? now()->toDateString();
        $refDoc = $achat->numero_facture;

        // Déterminer le journal et le compte financier
        $isBanque = str_starts_with(strtolower($modePaiement), 'banque');
        $codeJournal = 'CAI';
        $compteFinancier = '571000';
        if ($isBanque) {
            $parts = explode(' : ', $modePaiement);
            $intitule = isset($parts[1]) ? trim($parts[1]) : '';
            $journalObj = CodeJournal::where('entreprise_id', $entrepriseId)
                ->where('type', 'Banque')
                ->where('intitule', $intitule)
                ->first();
            if ($journalObj) {
                $codeJournal = $journalObj->code;
                $compteFinancier = $journalObj->compte;
            } else {
                $codeJournal = 'BQE';
                $compteFinancier = '521000';
            }
        }

        $compteFournisseur = $achat->fournisseur?->numero_tiers ?? $achat->fournisseur?->compte_comptable ?? '401100';

        $achat->loadMissing('details.produit');
        $produits = [];
        foreach ($achat->details as $detail) {
            $nom = $detail->libelle_virtuel ?? $detail->produit?->nom;
            if ($nom) {
                $produits[] = $nom;
            }
        }
        $produitsStr = count($produits) > 0 ? implode(', ', array_unique($produits)) : 'Marchandises';

        $refPaiement = $referencePaiement ?? $achat->reference_paiement;
        $libellePaiement = 'Rglt/' . $refDoc;
        if ($refPaiement) {
            $libellePaiement .= '/' . $refPaiement;
        }
        $libellePaiement .= '/Achat ' . $produitsStr;

        // 1. Débit Fournisseur
        EcritureComptable::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'     => $date,
            'libelle'           => $libellePaiement,
            'reference_document'=> $refDoc,
            'code_journal'      => $codeJournal,
            'compte_debit'      => $compteFournisseur,
            'compte_credit'     => null,
            'debit'             => $montant,
            'credit'            => 0,
        ]);

        // 2. Crédit Banque/Caisse
        EcritureComptable::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'     => $date,
            'libelle'           => $libellePaiement,
            'reference_document'=> $refDoc,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => $compteFinancier,
            'debit'             => 0,
            'credit'            => $montant,
        ]);
    }

    /**
     * Génère les écritures comptables d'un ordre de production.
     *
     * Logique SYSCOHADA révisé (inventaire permanent) :
     *   Pour chaque matière première consommée :
     *     Débit  603200 (Variation des stocks de MP)   vs Crédit 311000 (Stock MP)
     *   Pour le produit fini fabriqué :
     *     Débit  351100 (Stock produits finis)          vs Crédit 731100 (Variation stocks PF)
     *
     * @param OrdreProduction $ordre  L'ordre validé (doit avoir produitFini et details chargés)
     * @param array $consommations    [['produit' => Produit, 'quantite' => float, 'valeur_unitaire' => float], ...]
     * @param float $valeurProduction Valeur estimée du lot fabriqué (quantite × coût unitaire PF)
     */
    public static function genererEcritureProduction(
        OrdreProduction $ordre,
        array $consommations,
        float $valeurProduction
    ): void {
        if (empty($consommations) && $valeurProduction <= 0) {
            return;
        }

        $entrepriseId = $ordre->pointDeVente->entreprise_id;
        $pdvId        = $ordre->point_de_vente_id;
        $date         = now()->toDateString();
        $refDoc       = $ordre->code_ordre;

        $codeJournal = CodeJournal::where('entreprise_id', $entrepriseId)
            ->where('type', 'OD') // Journal des opérations diverses
            ->value('code') ?? 'OD';

        // 1. Sorties de matières premières consommées
        foreach ($consommations as $conso) {
            $valeurMp = round($conso['quantite'] * ($conso['valeur_unitaire'] ?? $conso['produit']->prix_achat ?? 0), 2);
            if ($valeurMp <= 0) continue;

            // Débit 603200 — Variation stocks MP (constate la consommation)
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id' => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/Conso MP ' . $conso['produit']->nom,
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => '603200', // Variation des stocks de matières premières
                'compte_credit'     => null,
                'debit'             => $valeurMp,
                'credit'            => 0,
            ]);

            // Crédit 311000 — Stocks de matières premières (diminution de l'actif)
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id' => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/Sortie Stock MP ' . $conso['produit']->nom,
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => null,
                'compte_credit'     => '311000', // Stocks de matières premières
                'debit'             => 0,
                'credit'            => $valeurMp,
            ]);
        }

        // 2. Entrée du produit fini fabriqué
        if ($valeurProduction > 0) {
            // Débit 351100 — Stocks de produits finis (augmentation de l'actif)
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id' => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/Entrée PF ' . $ordre->produitFini->nom,
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => '351100', // Stocks de produits finis
                'compte_credit'     => null,
                'debit'             => $valeurProduction,
                'credit'            => 0,
            ]);

            // Crédit 731100 — Variation des stocks de produits fabriqués (produit)
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id' => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/Production stockée ' . $ordre->produitFini->nom,
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => null,
                'compte_credit'     => '731100', // Variation des stocks de produits fabriqués
                'debit'             => 0,
                'credit'            => $valeurProduction,
            ]);
        }
    }

    /**
     * Génère les écritures comptables SYSCOHADA pour un avoir client.
     */
    public static function genererEcritureAvoirVente(Vente $avoir): void
    {
        $entrepriseId = $avoir->pointDeVente->entreprise_id;
        $pdvId = $avoir->point_de_vente_id;
        $date = $avoir->date_vente ? $avoir->date_vente->toDateString() : now()->toDateString();
        $refDoc = $avoir->numero_facture;

        $codeJournal = CodeJournal::where('entreprise_id', $entrepriseId)
            ->where('type', 'Vente')
            ->value('code') ?? 'VTE';

        $compteClient = $avoir->client?->numero_tiers ?? $avoir->client?->compte_comptable ?? '411100';

        // 1. Crédit Client (TTC)
        EcritureComptable::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'     => $date,
            'libelle'           => $refDoc . '/Facturation Avoir Client',
            'reference_document'=> $refDoc,
            'code_journal'      => $codeJournal,
            'compte_debit'      => null,
            'compte_credit'     => $compteClient,
            'debit'             => 0,
            'credit'            => $avoir->montant_ttc,
        ]);

        // 2. Débit Vente par compte (HT Net)
        $ventilation = [];
        foreach ($avoir->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            if ($ht > 0) {
                $compte = $detail->produit?->compte_vente ?? '701100';
                if (!isset($ventilation[$compte])) {
                    $ventilation[$compte] = 0;
                }
                $ventilation[$compte] += $ht;
            }
        }

        foreach ($ventilation as $compte => $montantHt) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . ' / Avoir Vente - Compte ' . $compte,
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => $compte,
                'compte_credit'     => null,
                'debit'             => $montantHt,
                'credit'            => 0,
            ]);
        }

        // 3. Débit TVA Collectée
        if ($avoir->montant_tva > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/Annulation TVA Collectée',
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => '443100',
                'compte_credit'     => null,
                'debit'             => $avoir->montant_tva,
                'credit'            => 0,
            ]);
        }
    }

    /**
     * Génère les écritures comptables SYSCOHADA pour un avoir fournisseur.
     */
    public static function genererEcritureAvoirAchat(Achat $avoir): void
    {
        $entrepriseId = $avoir->pointDeVente->entreprise_id;
        $pdvId = $avoir->point_de_vente_id;
        $date = $avoir->date_achat ? $avoir->date_achat->toDateString() : now()->toDateString();
        $refDoc = $avoir->numero_facture;

        $codeJournal = CodeJournal::where('entreprise_id', $entrepriseId)
            ->where('type', 'Achat')
            ->value('code') ?? 'ACH';

        $compteFournisseur = $avoir->fournisseur?->numero_tiers ?? $avoir->fournisseur?->compte_comptable ?? '401100';

        // 1. Débit Fournisseur (TTC)
        EcritureComptable::create([
            'entreprise_id'     => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'     => $date,
            'libelle'           => $refDoc . '/Facturation Avoir Fournisseur',
            'reference_document'=> $refDoc,
            'code_journal'      => $codeJournal,
            'compte_debit'      => $compteFournisseur,
            'compte_credit'     => null,
            'debit'             => $avoir->montant_ttc,
            'credit'            => 0,
        ]);

        // 2. Crédit Achat par compte (HT Net)
        $ventilation = [];
        foreach ($avoir->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            if ($ht > 0) {
                $compte = $detail->produit?->compte_achat ?? '601100';
                if (!isset($ventilation[$compte])) {
                    $ventilation[$compte] = 0;
                }
                $ventilation[$compte] += $ht;
            }
        }

        foreach ($ventilation as $compte => $montantHt) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . ' / Avoir Achat - Compte ' . $compte,
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => null,
                'compte_credit'     => $compte,
                'debit'             => 0,
                'credit'            => $montantHt,
            ]);
        }

        // 3. Crédit TVA Déductible
        if ($avoir->montant_tva > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/Annulation TVA Déductible',
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => null,
                'compte_credit'     => '445200',
                'debit'             => 0,
                'credit'            => $avoir->montant_tva,
            ]);
        }
    }

    /**
     * Synchronise le plan comptable, les codes journaux et les tiers depuis COMPTAFLOW.
     *
     * @param \App\Modules\Admin\Modeles\Entreprise $entreprise
     * @return array
     */
    public static function synchroniserDepuisComptaflow($entreprise): array
    {
        if (empty($entreprise->comptaflow_sync_key)) {
            return ['success' => false, 'message' => "La clé de synchronisation n'est pas configurée."];
        }

        try {
            $comptaflowUrl = config('selflow.comptaflow_api_url', 'http://127.0.0.1:8000');
            $secret = config('selflow.comptaflow_api_secret', 'selflow-comptaflow-secret-2026');

            $clients = \App\Modules\Admin\Modeles\Client::where('entreprise_id', $entreprise->id)
                ->select('id', 'nom', 'email', 'telephone', 'adresse')
                ->get()
                ->toArray();

            $fournisseurs = \App\Modules\Admin\Modeles\Fournisseur::where('entreprise_id', $entreprise->id)
                ->select('id', 'nom', 'email', 'telephone', 'adresse')
                ->get()
                ->toArray();

            $response = \Illuminate\Support\Facades\Http::timeout(25)->post($comptaflowUrl . '/api/external/link-company', [
                'secret'             => $secret,
                'selflow_sync_key'   => $entreprise->comptaflow_sync_key,
                'selflow_company_id' => $entreprise->id,
                'clients'            => $clients,
                'fournisseurs'       => $fournisseurs,
            ]);

            if ($response->successful() && $response->json('success')) {
                $comptaflowCompanyId = $response->json('company_id');
                $entreprise->update([
                    'comptaflow_company_id'   => $comptaflowCompanyId,
                    'comptaflow_sync_status'  => 'active',
                    'comptaflow_last_sync_at' => now(),
                ]);

                // 1. Plan comptable
                $plan = $response->json('plan_comptable', []);
                $importedAccountNumbers = [];
                foreach ($plan as $acc) {
                    $num = $acc['numero_de_compte'];
                    $importedAccountNumbers[] = $num;
                    \App\Modules\Admin\Modeles\PlanComptable::updateOrCreate(
                        [
                            'entreprise_id' => $entreprise->id,
                            'numero'        => $num,
                        ],
                        [
                            'libelle'         => $acc['intitule'],
                            'numero_original' => $acc['numero_original'] ?? null,
                            'source'          => 'comptaflow',
                        ]
                    );
                }
                // Supprimer les comptes de source comptaflow qui ne sont plus dans le plan synchronisé
                \App\Modules\Admin\Modeles\PlanComptable::where('entreprise_id', $entreprise->id)
                    ->where('source', 'comptaflow')
                    ->whereNotIn('numero', $importedAccountNumbers)
                    ->delete();

                // 2. Codes journaux
                $journaux = $response->json('codes_journaux', []);
                $importedJournalCodes = [];
                foreach ($journaux as $cj) {
                    $code = $cj['code_journal'];
                    $importedJournalCodes[] = $code;
                    \App\Modules\Admin\Modeles\CodeJournal::updateOrCreate(
                        [
                            'entreprise_id' => $entreprise->id,
                            'code'          => $code,
                        ],
                        [
                            'intitule'        => $cj['intitule'],
                            'type'            => $cj['type'] === 'Trésorerie' ? 'Trésorerie' : ($cj['type'] === 'Achats' ? 'Achat' : ($cj['type'] === 'Ventes' ? 'Vente' : 'Autre')),
                            'compte'          => $cj['compte_numero'] ?? '471000',
                            'numero_original' => $cj['numero_original'] ?? null,
                            'source'          => 'comptaflow',
                        ]
                    );
                }
                // Supprimer les codes journaux de source comptaflow qui ne sont plus dans la liste synchronisée
                \App\Modules\Admin\Modeles\CodeJournal::where('entreprise_id', $entreprise->id)
                    ->where('source', 'comptaflow')
                    ->whereNotIn('code', $importedJournalCodes)
                    ->delete();

                // 3. Tiers — Filtrage par préfixe numérique (comme COMPTAFLOW le fait lui-même)
                //    41xxx → Clients  |  40xxx → Fournisseurs
                //    Tous les types sont traités ('client', 'fournisseur', 'Autre')
                $tiers = $response->json('tiers', []);
                $nbClients = 0;
                $nbFournisseurs = 0;
                $importedClientTiersNumbers = [];
                $importedFournisseurTiersNumbers = [];

                foreach ($tiers as $t) {
                    $numTiers    = trim($t['numero_de_tiers'] ?? '');
                    $typeTier    = $t['type_de_tiers'] ?? '';
                    $numOriginal = $t['numero_original'] ?? null;
                    $intitule    = trim($t['intitule'] ?? '');

                    if (empty($numTiers) || empty($intitule)) continue;

                    // Catégorisation par préfixe (règle COMPTAFLOW)
                    $isClient     = str_starts_with($numTiers, '41');
                    $isFournisseur = str_starts_with($numTiers, '40');

                    if (!$isClient && !$isFournisseur) continue;

                    // Un tiers lié à Selflow = type explicite ('client'/'fournisseur')
                    //   ET numero_original est un entier pur (= l'ID Selflow enregistré par COMPTAFLOW
                    //   lors du push de Selflow vers COMPTAFLOW)
                    $isSelflowLinked = in_array($typeTier, ['client', 'fournisseur'])
                                    && $numOriginal !== null
                                    && $numOriginal !== ''
                                    && is_numeric($numOriginal)
                                    && (int)$numOriginal > 0;

                    if ($isClient) {
                        if ($isSelflowLinked) {
                            // Ce client vient de Selflow → mettre à jour son numéro COMPTAFLOW
                            // sans changer sa source (il reste 'local')
                            \App\Modules\Admin\Modeles\Client::where('id', (int)$numOriginal)
                                ->where('entreprise_id', $entreprise->id)
                                ->whereIn('source', ['local', null])
                                ->update([
                                    'numero_tiers'    => $numTiers,
                                    'numero_original' => $numOriginal,
                                ]);
                        } else {
                            // Tiers COMPTAFLOW natif (historique importé, etc.)
                            \App\Modules\Admin\Modeles\Client::updateOrCreate(
                                [
                                    'entreprise_id' => $entreprise->id,
                                    'numero_tiers'  => $numTiers,
                                ],
                                [
                                    'nom'              => ucwords(strtolower($intitule)),
                                    'source'           => 'comptaflow',
                                    'compte_comptable' => '411100',
                                    'numero_original'  => $numOriginal,
                                ]
                            );
                            $nbClients++;
                            $importedClientTiersNumbers[] = $numTiers;
                        }
                    } elseif ($isFournisseur) {
                        if ($isSelflowLinked) {
                            \App\Modules\Admin\Modeles\Fournisseur::where('id', (int)$numOriginal)
                                ->where('entreprise_id', $entreprise->id)
                                ->whereIn('source', ['local', null])
                                ->update([
                                    'numero_tiers'    => $numTiers,
                                    'numero_original' => $numOriginal,
                                ]);
                        } else {
                            \App\Modules\Admin\Modeles\Fournisseur::updateOrCreate(
                                [
                                    'entreprise_id' => $entreprise->id,
                                    'numero_tiers'  => $numTiers,
                                ],
                                [
                                    'nom'              => ucwords(strtolower($intitule)),
                                    'source'           => 'comptaflow',
                                    'compte_comptable' => '401100',
                                    'numero_original'  => $numOriginal,
                                ]
                            );
                            $nbFournisseurs++;
                            $importedFournisseurTiersNumbers[] = $numTiers;
                        }
                    }
                }

                // Supprimer les tiers de source comptaflow qui ne sont plus dans les tiers synchronisés
                \App\Modules\Admin\Modeles\Client::where('entreprise_id', $entreprise->id)
                    ->where('source', 'comptaflow')
                    ->whereNotIn('numero_tiers', $importedClientTiersNumbers)
                    ->delete();

                \App\Modules\Admin\Modeles\Fournisseur::where('entreprise_id', $entreprise->id)
                    ->where('source', 'comptaflow')
                    ->whereNotIn('numero_tiers', $importedFournisseurTiersNumbers)
                    ->delete();

                return [
                    'success' => true,
                    'message' => "Synchronisation effectuée avec succès ! ({$nbClients} client(s) et {$nbFournisseurs} fournisseur(s) COMPTAFLOW importés)",
                ];

            }

            return ['success' => false, 'message' => $response->json('message') ?? 'Clé de synchronisation invalide.'];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur synchronisation depuis COMPTAFLOW: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur de connexion : ' . $e->getMessage()];
        }
    }
}

