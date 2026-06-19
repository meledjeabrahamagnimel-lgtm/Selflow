<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\CodeJournal;

class ComptabiliteService
{
    /**
     * Génère les écritures de facturation pour une vente.
     * Débit Client (TTC) vs Crédit Vente (HT) & Crédit TVA
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

        // 2. Crédit Vente par produit (HT Net)
        foreach ($vente->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            if ($pourcentageRemise > 0) {
                $ht = $ht - ($ht * $pourcentageRemise);
            }

            if ($ht > 0) {
                EcritureComptable::create([
                    'entreprise_id'     => $entrepriseId,
                    'point_de_vente_id'  => $pdvId,
                    'date_ecriture'     => $date,
                    'libelle'           => $refDoc . '/' . ($detail->produit?->nom ?? 'Marchandises'),
                    'reference_document'=> $refDoc,
                    'code_journal'      => $codeJournal,
                    'compte_debit'      => null,
                    'compte_credit'     => $detail->produit?->compte_vente ?? '701100',
                    'debit'             => 0,
                    'credit'            => $ht,
                ]);
            }
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
     * Crédit Fournisseur (TTC) vs Débit Achat (HT) & Débit TVA
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

        // 2. Débit Achat par produit (HT)
        foreach ($achat->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            if ($ht > 0) {
                EcritureComptable::create([
                    'entreprise_id'     => $entrepriseId,
                    'point_de_vente_id'  => $pdvId,
                    'date_ecriture'     => $date,
                    'libelle'           => $refDoc . '/' . ($detail->libelle_virtuel ?? ($detail->produit?->nom ?? 'Marchandises')),
                    'reference_document'=> $refDoc,
                    'code_journal'      => $codeJournal,
                    'compte_debit'      => $detail->produit?->compte_achat ?? '601100',
                    'compte_credit'     => null,
                    'debit'             => $ht,
                    'credit'            => 0,
                ]);
            }
        }

        // 3. Débit TVA Déductible
        if ($achat->montant_tva > 0) {
            EcritureComptable::create([
                'entreprise_id'     => $entrepriseId,
                'point_de_vente_id'  => $pdvId,
                'date_ecriture'     => $date,
                'libelle'           => $refDoc . '/TVA Déductible Achat',
                'reference_document'=> $refDoc,
                'code_journal'      => $codeJournal,
                'compte_debit'      => '445100',
                'compte_credit'     => null,
                'debit'             => $achat->montant_tva,
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
}
