<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\Operation;
use App\Modules\Admin\Modeles\OrdreProduction;
use Illuminate\Support\Facades\DB;

/**
 * ComptabiliteService — Moteur d'écritures comptables SYSCOHADA révisé (Côte d'Ivoire)
 *
 * Comptes de référence utilisés (voir config('selflow.plan_comptable_defaut') pour les
 * valeurs par défaut, centralisées et modifiables à un seul endroit) :
 *  411xxx  Clients (compte collectif générique)
 *  401xxx  Fournisseurs (compte collectif générique)
 *  70xxxx  Produits des activités ordinaires (Classe 7)
 *  60xxxx  Achats de marchandises / matières (Classe 6)
 *  443100  État, TVA facturée sur ventes (collectée)
 *  445200  État, TVA déductible sur achats courants
 *  521xxx  Banques (établissements de crédit)
 *  571xxx  Caisse
 *  731100  Variation des stocks de produits fabriqués (production)
 *  603200  Variation des stocks de matières premières
 *
 * ─────────────────────────────────────────────────────────────────────────
 * RÈGLE FONDAMENTALE (corrigée le 22/07/2026 suite audit) :
 *
 * Une vente/achat réglé(e) INTÉGRALEMENT ET IMMÉDIATEMENT au moment de la
 * facturation ne transite JAMAIS par le compte 411/401. Utiliser
 * genererEcrituresVente()/genererEcrituresAchat(), qui décident seules.
 *
 * Chaque écriture générée est rattachée à une Operation (numero_saisie =
 * simple compteur séquentiel par entreprise), et le compte tiers individuel
 * est stocké dans la colonne dédiée compte_tiers — jamais à la place du
 * compte général.
 * ─────────────────────────────────────────────────────────────────────────
 * RÈGLE CODE JOURNAL PAR LIGNE (corrigée le 23/07/2026) :
 *
 * Une vente/achat COMPTANT reste UNE SEULE Opération (même N° Saisie pour
 * toutes ses lignes), mais chaque LIGNE porte le code journal du registre
 * SYSCOHADA auquel elle appartient réellement :
 *   - Lignes Produit(s) + TVA  -> journal VENTE (VTE) / ACHAT (ACH)
 *   - Ligne Caisse/Banque      -> journal de TRÉSORERIE (CAI/BQE...)
 * Avant ce correctif, TOUTES les lignes d'une vente comptant portaient à
 * tort le code du journal de caisse, y compris les lignes de vente/TVA —
 * on ne voyait donc jamais le code VTE sur une vente réglée comptant.
 * ─────────────────────────────────────────────────────────────────────────
 * RÈGLE LIBELLÉS (corrigée le 23/07/2026) :
 *
 * Pour chaque compte mouvementé par le détail d'une facture :
 *   - 1 à 3 produits distincts sur ce compte -> libellé = noms réels des
 *     produits ("Riz Parfumé 25kg, Huile Dinor 5L").
 *   - Plus de 3 produits distincts -> libellé court = intitulé SYSCOHADA du
 *     compte + "..." (ex: "Vente de marchandises..."), et la liste complète
 *     des produits est stockée dans la colonne 'description' de la ligne
 *     (affichable au clic sur les points de suspension côté vue).
 * ─────────────────────────────────────────────────────────────────────────
 */
class ComptabiliteService
{
    // ─────────────────────────────────────────────────────────────────
    // VENTES
    // ─────────────────────────────────────────────────────────────────

    /**
     * Point d'entrée unique pour la facturation d'une vente.
     * Décide automatiquement s'il s'agit d'une vente comptant (aucune ligne
     * 411) ou d'une vente à crédit / partiellement réglée (411 pour le
     * solde non couvert immédiatement).
     */
    public static function genererEcrituresVente(
        Vente $vente,
        float $montantPaye,
        string $modePaiement,
        ?string $date = null,
        ?string $moyenBancaire = null,
        ?string $referencePaiement = null
    ): void {
        $entrepriseId = $vente->pointDeVente->entreprise_id;
        $pdvId = $vente->point_de_vente_id;
        $date = $date ?? ($vente->date_vente ? $vente->date_vente->toDateString() : now()->toDateString());
        $refDoc = $vente->numero_facture;
        $ttc = (float) $vente->montant_ttc;
        $montantPaye = max(0, min($montantPaye, $ttc));

        $codeJournalVente = self::codeJournal($entrepriseId, 'Vente', 'VTE');
        [$compteFinancier, $codeJournalFinancier] = self::compteEtJournalFinancier($entrepriseId, $modePaiement);

        $ventilation = self::ventilationVente($vente);
        $libelleGeneral = self::libelleGeneralVente($ventilation);

        DB::transaction(function () use (
            $vente, $entrepriseId, $pdvId, $date, $refDoc, $ttc, $montantPaye,
            $codeJournalVente, $compteFinancier, $codeJournalFinancier,
            $ventilation, $libelleGeneral, $modePaiement
        ) {
            $estPaiementIntegralImmediat = $montantPaye >= $ttc && $ttc > 0;

            if ($estPaiementIntegralImmediat) {
                // ── Vente comptant : UNE SEULE opération, aucune ligne 411 ──
                // NB : le journal Caisse/Banque n'est utilisé QUE pour la ligne
                // financière ; les lignes Produit(s)/TVA portent le journal Vente.
                $operation = Operation::creer(
                    $entrepriseId, $pdvId, $date, 'VenteComptant',
                    $codeJournalFinancier, $refDoc, $libelleGeneral . ' (comptant)'
                );

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalFinancier,
                    $refDoc . ' / Vente comptant', $compteFinancier, null, null, $ttc, 0);

                foreach ($ventilation['comptes'] as $compte => $detailCompte) {
                    [$libelleDetail, $description] = self::libelleEtDescriptionDetailCompte(
                        $compte, $detailCompte['produits'], self::TABLE_SYSCOHADA_VENTE, 'Vente suivant détail'
                    );
                    self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalVente,
                        $refDoc . ' / ' . $libelleDetail, null, $compte, null, 0, $detailCompte['montant'], $description);
                }
                if ($ventilation['tva'] > 0) {
                    self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalVente,
                        $refDoc . ' / TVA Collectée Vente', null, config('selflow.plan_comptable_defaut.tva_collectee'), null, 0, $ventilation['tva']);
                }

                $operation->cloturerEquilibre();
                return;
            }

            // ── Vente à crédit (totale ou partielle) : passage obligatoire par le 411 ──
            $compteClientGeneral = $vente->client?->compte_comptable ?? config('selflow.plan_comptable_defaut.client_collectif');
            $compteClientTiers = $vente->client?->numero_tiers;

            $opFacture = Operation::creer(
                $entrepriseId, $pdvId, $date, 'FactureVente',
                $codeJournalVente, $refDoc, $libelleGeneral
            );

            self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalVente,
                $refDoc . ' / Facturation Vente', $compteClientGeneral, null, $compteClientTiers, $ttc, 0);

            foreach ($ventilation['comptes'] as $compte => $detailCompte) {
                [$libelleDetail, $description] = self::libelleEtDescriptionDetailCompte(
                    $compte, $detailCompte['produits'], self::TABLE_SYSCOHADA_VENTE, 'Vente suivant détail'
                );
                self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalVente,
                    $refDoc . ' / ' . $libelleDetail, null, $compte, null, 0, $detailCompte['montant'], $description);
            }
            if ($ventilation['tva'] > 0) {
                self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalVente,
                    $refDoc . ' / TVA Collectée Vente', null, config('selflow.plan_comptable_defaut.tva_collectee'), null, 0, $ventilation['tva']);
            }
            $opFacture->cloturerEquilibre();

            // ── Règlement partiel encaissé immédiatement (solde restant à crédit) ──
            if ($montantPaye > 0) {
                self::genererEcritureReglementVente($vente, $montantPaye, $modePaiement, $date, null, null, 'Acompte à la facturation');
            }
        });
    }

    /**
     * Génère l'écriture de règlement client pour un encaissement DIFFÉRÉ
     * (postérieur à la facturation) ou pour un acompte encaissé en même
     * temps qu'une facturation à crédit partielle.
     * Débit Caisse/Banque (Montant) vs Crédit Client (Montant)
     */
    public static function genererEcritureReglementVente(
        Vente $vente,
        float $montant,
        string $modePaiement,
        ?string $date = null,
        ?string $moyenBancaire = null,
        ?string $referencePaiement = null,
        ?string $contexte = null
    ): void {
        if ($montant <= 0) return;

        $entrepriseId = $vente->pointDeVente->entreprise_id;
        $pdvId = $vente->point_de_vente_id;
        $date = $date ?? now()->toDateString();
        $refDoc = $vente->numero_facture;

        [$compteFinancier, $codeJournal] = self::compteEtJournalFinancier($entrepriseId, $modePaiement);

        $compteClientGeneral = $vente->client?->compte_comptable ?? config('selflow.plan_comptable_defaut.client_collectif');
        $compteClientTiers = $vente->client?->numero_tiers;

        [$libelleProduits, $descriptionProduits] = self::libelleEtDescriptionProduits($vente->loadMissing('details.produit')->details);
        $refPaiement = $referencePaiement ?? $vente->reference_paiement;
        $libellePaiement = 'Rglt/' . $refDoc . ($refPaiement ? '/' . $refPaiement : '') . '/Vente ' . $libelleProduits;

        DB::transaction(function () use (
            $entrepriseId, $pdvId, $date, $refDoc, $codeJournal, $compteFinancier,
            $compteClientGeneral, $compteClientTiers, $libellePaiement, $descriptionProduits, $montant, $contexte
        ) {
            $operation = Operation::creer(
                $entrepriseId, $pdvId, $date, 'ReglementVente',
                $codeJournal, $refDoc, $contexte ?? 'Règlement client'
            );

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $libellePaiement, $compteFinancier, null, null, $montant, 0, $descriptionProduits);

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $libellePaiement, null, $compteClientGeneral, $compteClientTiers, 0, $montant, $descriptionProduits);

            $operation->cloturerEquilibre();
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // ACHATS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Point d'entrée unique pour la facturation d'un achat.
     * Symétrique de genererEcrituresVente().
     */
    public static function genererEcrituresAchat(
        Achat $achat,
        float $montantPaye,
        string $modePaiement,
        ?string $date = null,
        ?string $moyenBancaire = null,
        ?string $referencePaiement = null
    ): void {
        $entrepriseId = $achat->pointDeVente->entreprise_id;
        $pdvId = $achat->point_de_vente_id;
        $date = $date ?? ($achat->date_achat ? $achat->date_achat->toDateString() : now()->toDateString());
        $refDoc = $achat->numero_facture;
        $ttc = (float) $achat->montant_ttc;
        $montantPaye = max(0, min($montantPaye, $ttc));

        $codeJournalAchat = self::codeJournal($entrepriseId, 'Achat', 'ACH');
        [$compteFinancier, $codeJournalFinancier] = self::compteEtJournalFinancier($entrepriseId, $modePaiement);

        $ventilation = self::ventilationAchat($achat);
        $libelleGeneral = self::libelleGeneralAchat($ventilation);

        DB::transaction(function () use (
            $achat, $entrepriseId, $pdvId, $date, $refDoc, $ttc, $montantPaye,
            $codeJournalAchat, $compteFinancier, $codeJournalFinancier,
            $ventilation, $libelleGeneral, $modePaiement
        ) {
            $estPaiementIntegralImmediat = $montantPaye >= $ttc && $ttc > 0;

            if ($estPaiementIntegralImmediat) {
                // ── Achat comptant : UNE SEULE opération, aucune ligne 401 ──
                // NB : même correctif que la vente comptant — le journal
                // Caisse/Banque n'est utilisé QUE pour la ligne financière.
                $operation = Operation::creer(
                    $entrepriseId, $pdvId, $date, 'AchatComptant',
                    $codeJournalFinancier, $refDoc, $libelleGeneral . ' (comptant)'
                );

                foreach ($ventilation['comptes'] as $compte => $detailCompte) {
                    [$libelleDetail, $description] = self::libelleEtDescriptionDetailCompte(
                        $compte, $detailCompte['produits'], self::TABLE_SYSCOHADA_ACHAT, 'Achat suivant détail'
                    );
                    self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalAchat,
                        $refDoc . ' / ' . $libelleDetail, $compte, null, null, $detailCompte['montant'], 0, $description);
                }
                if ($ventilation['tva'] > 0) {
                    self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalAchat,
                        $refDoc . ' / TVA Déductible Achat', config('selflow.plan_comptable_defaut.tva_deductible'), null, null, $ventilation['tva'], 0);
                }

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalFinancier,
                    $refDoc . ' / Achat comptant', null, $compteFinancier, null, 0, $ttc);

                $operation->cloturerEquilibre();
                return;
            }

            // ── Achat à crédit (total ou partiel) : passage obligatoire par le 401 ──
            $compteFournisseurGeneral = $achat->fournisseur?->compte_comptable ?? config('selflow.plan_comptable_defaut.fournisseur_collectif');
            $compteFournisseurTiers = $achat->fournisseur?->numero_tiers;

            $opFacture = Operation::creer(
                $entrepriseId, $pdvId, $date, 'FactureAchat',
                $codeJournalAchat, $refDoc, $libelleGeneral
            );

            foreach ($ventilation['comptes'] as $compte => $detailCompte) {
                [$libelleDetail, $description] = self::libelleEtDescriptionDetailCompte(
                    $compte, $detailCompte['produits'], self::TABLE_SYSCOHADA_ACHAT, 'Achat suivant détail'
                );
                self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalAchat,
                    $refDoc . ' / ' . $libelleDetail, $compte, null, null, $detailCompte['montant'], 0, $description);
            }
            if ($ventilation['tva'] > 0) {
                self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalAchat,
                    $refDoc . ' / TVA Déductible Achat', config('selflow.plan_comptable_defaut.tva_deductible'), null, null, $ventilation['tva'], 0);
            }

            self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalAchat,
                $refDoc . ' / Facturation Achat', null, $compteFournisseurGeneral, $compteFournisseurTiers, 0, $ttc);

            $opFacture->cloturerEquilibre();

            if ($montantPaye > 0) {
                self::genererEcritureReglementAchat($achat, $montantPaye, $modePaiement, $date, null, null, 'Acompte à la facturation');
            }
        });
    }

    /**
     * Génère l'écriture de règlement fournisseur pour un décaissement
     * DIFFÉRÉ ou un acompte encaissé en même temps qu'une facturation
     * à crédit partielle.
     * Débit Fournisseur (Montant) vs Crédit Caisse/Banque (Montant)
     */
    public static function genererEcritureReglementAchat(
        Achat $achat,
        float $montant,
        string $modePaiement,
        ?string $date = null,
        ?string $moyenBancaire = null,
        ?string $referencePaiement = null,
        ?string $contexte = null
    ): void {
        if ($montant <= 0) return;

        $entrepriseId = $achat->pointDeVente->entreprise_id;
        $pdvId = $achat->point_de_vente_id;
        $date = $date ?? now()->toDateString();
        $refDoc = $achat->numero_facture;

        [$compteFinancier, $codeJournal] = self::compteEtJournalFinancier($entrepriseId, $modePaiement);

        $compteFournisseurGeneral = $achat->fournisseur?->compte_comptable ?? config('selflow.plan_comptable_defaut.fournisseur_collectif');
        $compteFournisseurTiers = $achat->fournisseur?->numero_tiers;

        [$libelleProduits, $descriptionProduits] = self::libelleEtDescriptionProduits($achat->loadMissing('details.produit')->details);
        $refPaiement = $referencePaiement ?? $achat->reference_paiement;
        $libellePaiement = 'Rglt/' . $refDoc . ($refPaiement ? '/' . $refPaiement : '') . '/Achat ' . $libelleProduits;

        DB::transaction(function () use (
            $entrepriseId, $pdvId, $date, $refDoc, $codeJournal, $compteFinancier,
            $compteFournisseurGeneral, $compteFournisseurTiers, $libellePaiement, $descriptionProduits, $montant, $contexte
        ) {
            $operation = Operation::creer(
                $entrepriseId, $pdvId, $date, 'ReglementAchat',
                $codeJournal, $refDoc, $contexte ?? 'Règlement fournisseur'
            );

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $libellePaiement, $compteFournisseurGeneral, null, $compteFournisseurTiers, $montant, 0, $descriptionProduits);

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $libellePaiement, null, $compteFinancier, null, 0, $montant, $descriptionProduits);

            $operation->cloturerEquilibre();
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // PRODUCTION
    // ─────────────────────────────────────────────────────────────────

    /**
     * Génère les écritures comptables d'un ordre de production.
     *
     * Logique SYSCOHADA révisé (inventaire permanent) :
     *   Pour chaque matière première consommée :
     *     Débit  603200 (Variation des stocks de MP)   vs Crédit 311000 (Stock MP)
     *   Pour le produit fini fabriqué :
     *     Débit  351100 (Stock produits finis)          vs Crédit 731100 (Variation stocks PF)
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
        $codeJournal  = self::codeJournal($entrepriseId, 'OD', 'OD');

        DB::transaction(function () use ($ordre, $consommations, $valeurProduction, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal) {
            $operation = Operation::creer(
                $entrepriseId, $pdvId, $date, 'Production',
                $codeJournal, $refDoc, 'Production interne — ' . ($ordre->produitFini->nom ?? '')
            );

            foreach ($consommations as $conso) {
                $valeurMp = round($conso['quantite'] * ($conso['valeur_unitaire'] ?? $conso['produit']->prix_achat ?? 0), 2);
                if ($valeurMp <= 0) continue;

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / Conso MP ' . $conso['produit']->nom, '603200', null, null, $valeurMp, 0);

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / Sortie Stock MP ' . $conso['produit']->nom, null, '311000', null, 0, $valeurMp);
            }

            if ($valeurProduction > 0) {
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / Entrée PF ' . $ordre->produitFini->nom, '351100', null, null, $valeurProduction, 0);

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / Production stockée ' . $ordre->produitFini->nom, null, '731100', null, 0, $valeurProduction);
            }

            $operation->cloturerEquilibre();
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // AVOIRS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Génère les écritures comptables SYSCOHADA pour un avoir client.
     * Inverse la facturation d'origine : Crédit Client / Débit Vente / Débit TVA.
     */
    public static function genererEcritureAvoirVente(Vente $avoir): void
    {
        $entrepriseId = $avoir->pointDeVente->entreprise_id;
        $pdvId = $avoir->point_de_vente_id;
        $date = $avoir->date_vente ? $avoir->date_vente->toDateString() : now()->toDateString();
        $refDoc = $avoir->numero_facture;
        $codeJournal = self::codeJournal($entrepriseId, 'Vente', 'VTE');

        $compteClientGeneral = $avoir->client?->compte_comptable ?? config('selflow.plan_comptable_defaut.client_collectif');
        $compteClientTiers = $avoir->client?->numero_tiers;

        $ventilation = self::ventilationVente($avoir);

        DB::transaction(function () use (
            $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
            $compteClientGeneral, $compteClientTiers, $ventilation, $avoir
        ) {
            $operation = Operation::creer(
                $entrepriseId, $pdvId, $date, 'AvoirVente',
                $codeJournal, $refDoc, 'Avoir client'
            );

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $refDoc . ' / Facturation Avoir Client', null, $compteClientGeneral, $compteClientTiers, 0, $avoir->montant_ttc);

            foreach ($ventilation['comptes'] as $compte => $detailCompte) {
                [$libelleDetail, $description] = self::libelleEtDescriptionDetailCompte(
                    $compte, $detailCompte['produits'], self::TABLE_SYSCOHADA_VENTE, 'Avoir Vente'
                );
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / ' . $libelleDetail, $compte, null, null, $detailCompte['montant'], 0, $description);
            }

            if ($ventilation['tva'] > 0) {
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / Annulation TVA Collectée', config('selflow.plan_comptable_defaut.tva_collectee'), null, null, $ventilation['tva'], 0);
            }

            $operation->cloturerEquilibre();
        });
    }

    /**
     * Génère les écritures comptables SYSCOHADA pour un avoir fournisseur.
     * Inverse la facturation d'origine : Débit Fournisseur / Crédit Achat / Crédit TVA.
     */
    public static function genererEcritureAvoirAchat(Achat $avoir): void
    {
        $entrepriseId = $avoir->pointDeVente->entreprise_id;
        $pdvId = $avoir->point_de_vente_id;
        $date = $avoir->date_achat ? $avoir->date_achat->toDateString() : now()->toDateString();
        $refDoc = $avoir->numero_facture;
        $codeJournal = self::codeJournal($entrepriseId, 'Achat', 'ACH');

        $compteFournisseurGeneral = $avoir->fournisseur?->compte_comptable ?? config('selflow.plan_comptable_defaut.fournisseur_collectif');
        $compteFournisseurTiers = $avoir->fournisseur?->numero_tiers;

        $ventilation = self::ventilationAchat($avoir);

        DB::transaction(function () use (
            $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
            $compteFournisseurGeneral, $compteFournisseurTiers, $ventilation, $avoir
        ) {
            $operation = Operation::creer(
                $entrepriseId, $pdvId, $date, 'AvoirAchat',
                $codeJournal, $refDoc, 'Avoir fournisseur'
            );

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $refDoc . ' / Facturation Avoir Fournisseur', $compteFournisseurGeneral, null, $compteFournisseurTiers, $avoir->montant_ttc, 0);

            foreach ($ventilation['comptes'] as $compte => $detailCompte) {
                [$libelleDetail, $description] = self::libelleEtDescriptionDetailCompte(
                    $compte, $detailCompte['produits'], self::TABLE_SYSCOHADA_ACHAT, 'Avoir Achat'
                );
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / ' . $libelleDetail, null, $compte, null, 0, $detailCompte['montant'], $description);
            }

            if ($ventilation['tva'] > 0) {
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / Annulation TVA Déductible', null, config('selflow.plan_comptable_defaut.tva_deductible'), null, 0, $ventilation['tva']);
            }

            $operation->cloturerEquilibre();
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS PUBLICS — libellés pour la trésorerie (appelés par les contrôleurs)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Libellé informatif pour une ligne de TresorerieJournal liée à une vente
     * (remplace l'ancien texte fixe répété identiquement "Vente — Facture X"
     * par un intitulé dérivé du SYSCOHADA, plus parlant).
     */
    public static function libelleTresorerieVente(Vente $vente): string
    {
        $ventilation = self::ventilationVente($vente);
        $libelleGeneral = self::libelleGeneralVente($ventilation);
        return $libelleGeneral . ' — ' . $vente->numero_facture;
    }

    /**
     * Équivalent pour un achat.
     */
    public static function libelleTresorerieAchat(Achat $achat): string
    {
        $ventilation = self::ventilationAchat($achat);
        $libelleGeneral = self::libelleGeneralAchat($ventilation);
        return $libelleGeneral . ' — ' . $achat->numero_facture;
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS INTERNES
    // ─────────────────────────────────────────────────────────────────

    private const TABLE_SYSCOHADA_VENTE = [
        '701' => 'Vente de marchandises',
        '702' => 'Vente de produits finis',
        '703' => "Vente de produits intermédiaires et résiduels",
        '704' => 'Travaux facturés',
        '705' => 'Services vendus',
        '706' => 'Travaux, services vendus',
        '707' => 'Produits accessoires',
    ];

    private const TABLE_SYSCOHADA_ACHAT = [
        '601' => 'Achat de marchandises',
        '602' => 'Achat de matières premières',
        '604' => 'Achat de fournitures non stockées',
        '605' => 'Autres achats',
        '606' => 'Fournitures non stockables',
    ];

    /**
     * Crée une ligne d'écriture rattachée à une opération.
     * Un seul et unique endroit du code écrit dans ecritures_comptables :
     * garantit que operation_id et compte_tiers sont toujours renseignés
     * cohéremment (jamais de compte tiers écrit à la place du compte général).
     *
     * @param string|null $description Détail complet (ex: liste de tous les
     *   produits) quand le libellé court a été tronqué avec "..." — affiché
     *   côté vue au clic sur le libellé/les points de suspension.
     */
    private static function ligne(
        Operation $operation,
        int $entrepriseId,
        ?int $pdvId,
        string $date,
        ?string $refDoc,
        string $codeJournal,
        string $libelle,
        ?string $compteDebit,
        ?string $compteCredit,
        ?string $compteTiers,
        float $debit,
        float $credit,
        ?string $description = null
    ): void {
        EcritureComptable::create([
            'operation_id'       => $operation->id,
            'entreprise_id'      => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'      => $date,
            'libelle'            => $libelle,
            'description'        => $description,
            'reference_document' => $refDoc,
            'code_journal'       => $codeJournal,
            'compte_debit'       => $compteDebit,
            'compte_credit'      => $compteCredit,
            'compte_tiers'       => $compteTiers,
            'debit'              => $debit,
            'credit'             => $credit,
        ]);
    }

    private static function codeJournal(int $entrepriseId, string $type, string $fallback): string
    {
        return CodeJournal::where('entreprise_id', $entrepriseId)
            ->where('type', $type)
            ->value('code') ?? $fallback;
    }

    /**
     * Détermine le compte financier (caisse/banque) et le code journal
     * correspondant au mode de paiement donné.
     * @return array{0: string, 1: string} [compteFinancier, codeJournal]
     */
    private static function compteEtJournalFinancier(int $entrepriseId, string $modePaiement): array
    {
        $isBanque = str_starts_with(strtolower($modePaiement), 'banque');
        if (!$isBanque) {
            return [config('selflow.plan_comptable_defaut.caisse'), 'CAI'];
        }

        $parts = explode(' : ', $modePaiement);
        $intitule = isset($parts[1]) ? trim($parts[1]) : '';
        $journalObj = CodeJournal::where('entreprise_id', $entrepriseId)
            ->where('type', 'Banque')
            ->where('intitule', $intitule)
            ->first();

        if ($journalObj) {
            return [$journalObj->compte, $journalObj->code];
        }
        return [config('selflow.plan_comptable_defaut.banque_defaut'), 'BQE'];
    }

    /**
     * Ventile les lignes d'une vente par compte de produit, avec application
     * de la remise globale au prorata, calcule la TVA totale, et conserve la
     * liste des produits par compte (nécessaire pour les libellés intelligents).
     * @return array{comptes: array<string, array{montant: float, produits: array<string>}>, tva: float}
     */
    private static function ventilationVente(Vente $vente): array
    {
        $pourcentageRemise = ($vente->remise > 0 && $vente->montant_ht > 0)
            ? ($vente->remise / $vente->montant_ht)
            : 0;

        $comptes = [];
        foreach ($vente->details as $detail) {
            // La remise de ligne s'applique avant la remise globale : c'est
            // l'ordre retenu à la saisie et celui du récapitulatif de la FNE.
            $remiseLigne = (float) ($detail->remise_taux ?? 0);
            $ht = $detail->quantite * $detail->prix_unitaire * (1 - $remiseLigne / 100);
            if ($pourcentageRemise > 0) {
                $ht = $ht - ($ht * $pourcentageRemise);
            }
            if ($ht > 0) {
                $compte = $detail->produit?->compte_vente ?? config('selflow.plan_comptable_defaut.vente_defaut');
                if (!isset($comptes[$compte])) {
                    $comptes[$compte] = ['montant' => 0, 'produits' => []];
                }
                $comptes[$compte]['montant'] += $ht;
                $nom = $detail->libelle_virtuel ?? $detail->produit?->nom;
                if ($nom) $comptes[$compte]['produits'][] = $nom;
            }
        }

        return ['comptes' => $comptes, 'tva' => (float) ($vente->montant_tva ?? 0)];
    }

    /**
     * Ventile les lignes d'un achat par compte de produit, recalcule la TVA
     * totale ligne par ligne, et conserve la liste des produits par compte.
     * @return array{comptes: array<string, array{montant: float, produits: array<string>}>, tva: float}
     */
    private static function ventilationAchat(Achat $achat): array
    {
        $comptes = [];
        $totalTva = 0;
        foreach ($achat->details as $detail) {
            $remiseLigne = (float) ($detail->remise_taux ?? 0);
            $ht = $detail->quantite * $detail->prix_unitaire * (1 - $remiseLigne / 100);
            if ($ht > 0) {
                $compte = $detail->produit?->compte_achat ?? config('selflow.plan_comptable_defaut.achat_defaut');
                if (!isset($comptes[$compte])) {
                    $comptes[$compte] = ['montant' => 0, 'produits' => []];
                }
                $comptes[$compte]['montant'] += $ht;
                $nom = $detail->libelle_virtuel ?? $detail->produit?->nom;
                if ($nom) $comptes[$compte]['produits'][] = $nom;

                $tauxTva = $detail->produit?->taux_tva ?? 0;
                if ($tauxTva > 0) {
                    $totalTva += round($ht * ($tauxTva / 100), 2);
                }
            }
        }

        $tva = $totalTva > 0 ? $totalTva : (float) ($achat->montant_tva ?? 0);
        return ['comptes' => $comptes, 'tva' => $tva];
    }

    /**
     * Construit le libellé (court) et la description (détail complet, ou
     * null si inutile) d'une ligne d'écriture portant sur un compte donné :
     *   - 1 à 3 produits distincts  -> libellé = noms réels, pas de description
     *   - 4 produits distincts ou plus -> libellé = intitulé SYSCOHADA + "...",
     *     description = liste complète des produits (affichable au clic)
     *   - Aucun nom de produit disponible -> libellé générique fourni en repli
     * @return array{0: string, 1: ?string}
     */
    private static function libelleEtDescriptionDetailCompte(string $compte, array $nomsProduits, array $tableSyscohada, string $libelleReplis): array
    {
        $noms = array_values(array_unique(array_filter($nomsProduits)));

        if (count($noms) === 0) {
            return [$libelleReplis, null];
        }
        if (count($noms) <= 3) {
            return [implode(', ', $noms), null];
        }

        $prefixe3 = substr($compte, 0, 3);
        $syscohada = $tableSyscohada[$prefixe3] ?? $libelleReplis;
        return [$syscohada . '...', implode(', ', $noms)];
    }

    /**
     * Dérive un libellé général d'opération à partir des comptes de vente
     * mouvementés (classe SYSCOHADA), au lieu d'un texte fixe générique.
     */
    private static function libelleGeneralVente(array $ventilation): string
    {
        return self::libelleGeneralDepuisComptes(array_keys($ventilation['comptes']), self::TABLE_SYSCOHADA_VENTE, 'Vente de marchandises et services');
    }

    private static function libelleGeneralAchat(array $ventilation): string
    {
        return self::libelleGeneralDepuisComptes(array_keys($ventilation['comptes']), self::TABLE_SYSCOHADA_ACHAT, 'Achats divers');
    }

    private static function libelleGeneralDepuisComptes(array $comptes, array $table, string $fallbackMixte): string
    {
        if (empty($comptes)) {
            return $fallbackMixte;
        }

        $prefixes = array_unique(array_map(fn($c) => substr((string) $c, 0, 3), $comptes));

        if (count($prefixes) === 1 && isset($table[$prefixes[0]])) {
            return $table[$prefixes[0]];
        }

        return $fallbackMixte;
    }

    /**
     * Construit le libellé (court) et la description (détail complet, ou
     * null) pour une écriture de règlement portant sur plusieurs produits.
     * Même logique de seuil que libelleEtDescriptionDetailCompte() (3 max).
     * @return array{0: string, 1: ?string}
     */
    private static function libelleEtDescriptionProduits($details): array
    {
        $produits = [];
        foreach ($details as $detail) {
            $nom = $detail->libelle_virtuel ?? $detail->produit?->nom;
            if ($nom) $produits[] = $nom;
        }
        $noms = array_values(array_unique($produits));

        if (count($noms) === 0) {
            return ['Marchandises', null];
        }
        if (count($noms) <= 3) {
            return [implode(', ', $noms), null];
        }
        return [count($noms) . ' articles...', implode(', ', $noms)];
    }

    // ─────────────────────────────────────────────────────────────────
    // SYNCHRONISATION COMPTAFLOW
    // ─────────────────────────────────────────────────────────────────

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
                \App\Modules\Admin\Modeles\CodeJournal::where('entreprise_id', $entreprise->id)
                    ->where('source', 'comptaflow')
                    ->whereNotIn('code', $importedJournalCodes)
                    ->delete();

                // 3. Tiers — Filtrage par préfixe numérique (comme COMPTAFLOW le fait lui-même)
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

                    $isClient     = str_starts_with($numTiers, '41');
                    $isFournisseur = str_starts_with($numTiers, '40');

                    if (!$isClient && !$isFournisseur) continue;

                    $isSelflowLinked = in_array($typeTier, ['client', 'fournisseur'])
                                    && $numOriginal !== null
                                    && $numOriginal !== ''
                                    && is_numeric($numOriginal)
                                    && (int)$numOriginal > 0;

                    if ($isClient) {
                        if ($isSelflowLinked) {
                            \App\Modules\Admin\Modeles\Client::where('id', (int)$numOriginal)
                                ->where('entreprise_id', $entreprise->id)
                                ->whereIn('source', ['local', null])
                                ->update([
                                    'numero_tiers'    => $numTiers,
                                    'numero_original' => $numOriginal,
                                ]);
                        } else {
                            \App\Modules\Admin\Modeles\Client::updateOrCreate(
                                [
                                    'entreprise_id' => $entreprise->id,
                                    'numero_tiers'  => $numTiers,
                                ],
                                [
                                    'nom'              => ucwords(strtolower($intitule)),
                                    'source'           => 'comptaflow',
                                    'compte_comptable' => config('selflow.plan_comptable_defaut.client_collectif'),
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
                                    'compte_comptable' => config('selflow.plan_comptable_defaut.fournisseur_collectif'),
                                    'numero_original'  => $numOriginal,
                                ]
                            );
                            $nbFournisseurs++;
                            $importedFournisseurTiersNumbers[] = $numTiers;
                        }
                    }
                }

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
