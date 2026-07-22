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
 * facturation ne transite JAMAIS par le compte 411/401. Le compte 411/401
 * n'est mouvementé que s'il subsiste une créance/dette réelle (paiement
 * différé, total ou partiel). Auparavant le service générait toujours une
 * écriture "facture" (411 débit) PUIS une écriture "règlement" séparée
 * (411 crédit) même pour un encaissement simultané, ce qui créait un
 * mouvement fictif sur le 411 qui s'annulait instantanément — inutile et
 * trompeur pour le lettrage. Utiliser genererEcrituresVente()/
 * genererEcrituresAchat() ci-dessous, qui décident automatiquement.
 *
 * Chaque écriture générée est maintenant rattachée à une Operation
 * (numero_saisie séquentiel par journal), et le compte tiers individuel
 * (numero_tiers du client/fournisseur) est stocké dans la colonne dédiée
 * compte_tiers — jamais à la place du compte général.
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
     *
     * @param Vente  $vente
     * @param float  $montantPaye     Montant réellement encaissé au moment de la facturation (0 si vente à crédit pure)
     * @param string $modePaiement    'Espèces', 'Banque : <intitulé>', etc.
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
                $operation = Operation::creer(
                    $entrepriseId, $pdvId, $date, 'VenteComptant',
                    $codeJournalFinancier, $refDoc, $libelleGeneral . ' (comptant)'
                );

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalFinancier,
                    $refDoc . '/Vente comptant', $compteFinancier, null, null, $ttc, 0);

                foreach ($ventilation['comptes'] as $compte => $montantHt) {
                    self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalFinancier,
                        $refDoc . ' / Vente suivant détail - Compte ' . $compte, null, $compte, null, 0, $montantHt);
                }
                if ($ventilation['tva'] > 0) {
                    self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalFinancier,
                        $refDoc . '/TVA Collectée Vente', null, config('selflow.plan_comptable_defaut.tva_collectee'), null, 0, $ventilation['tva']);
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
                $refDoc . '/Facturation Vente', $compteClientGeneral, null, $compteClientTiers, $ttc, 0);

            foreach ($ventilation['comptes'] as $compte => $montantHt) {
                self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalVente,
                    $refDoc . ' / Vente suivant détail - Compte ' . $compte, null, $compte, null, 0, $montantHt);
            }
            if ($ventilation['tva'] > 0) {
                self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalVente,
                    $refDoc . '/TVA Collectée Vente', null, config('selflow.plan_comptable_defaut.tva_collectee'), null, 0, $ventilation['tva']);
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

        $produitsStr = self::libelleProduits($vente->loadMissing('details.produit')->details);
        $refPaiement = $referencePaiement ?? $vente->reference_paiement;
        $libellePaiement = 'Rglt/' . $refDoc . ($refPaiement ? '/' . $refPaiement : '') . '/Vente ' . $produitsStr;

        DB::transaction(function () use (
            $entrepriseId, $pdvId, $date, $refDoc, $codeJournal, $compteFinancier,
            $compteClientGeneral, $compteClientTiers, $libellePaiement, $montant, $contexte
        ) {
            $operation = Operation::creer(
                $entrepriseId, $pdvId, $date, 'ReglementVente',
                $codeJournal, $refDoc, $contexte ?? 'Règlement client'
            );

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $libellePaiement, $compteFinancier, null, null, $montant, 0);

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $libellePaiement, null, $compteClientGeneral, $compteClientTiers, 0, $montant);

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
                $operation = Operation::creer(
                    $entrepriseId, $pdvId, $date, 'AchatComptant',
                    $codeJournalFinancier, $refDoc, $libelleGeneral . ' (comptant)'
                );

                foreach ($ventilation['comptes'] as $compte => $montantHt) {
                    self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalFinancier,
                        $refDoc . ' / Achat suivant détail - Compte ' . $compte, $compte, null, null, $montantHt, 0);
                }
                if ($ventilation['tva'] > 0) {
                    self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalFinancier,
                        $refDoc . '/TVA Déductible Achat', config('selflow.plan_comptable_defaut.tva_deductible'), null, null, $ventilation['tva'], 0);
                }

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalFinancier,
                    $refDoc . '/Achat comptant', null, $compteFinancier, null, 0, $ttc);

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

            foreach ($ventilation['comptes'] as $compte => $montantHt) {
                self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalAchat,
                    $refDoc . ' / Achat suivant détail - Compte ' . $compte, $compte, null, null, $montantHt, 0);
            }
            if ($ventilation['tva'] > 0) {
                self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalAchat,
                    $refDoc . '/TVA Déductible Achat', config('selflow.plan_comptable_defaut.tva_deductible'), null, null, $ventilation['tva'], 0);
            }

            self::ligne($opFacture, $entrepriseId, $pdvId, $date, $refDoc, $codeJournalAchat,
                $refDoc . '/Facturation Achat', null, $compteFournisseurGeneral, $compteFournisseurTiers, 0, $ttc);

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

        $produitsStr = self::libelleProduits($achat->loadMissing('details.produit')->details);
        $refPaiement = $referencePaiement ?? $achat->reference_paiement;
        $libellePaiement = 'Rglt/' . $refDoc . ($refPaiement ? '/' . $refPaiement : '') . '/Achat ' . $produitsStr;

        DB::transaction(function () use (
            $entrepriseId, $pdvId, $date, $refDoc, $codeJournal, $compteFinancier,
            $compteFournisseurGeneral, $compteFournisseurTiers, $libellePaiement, $montant, $contexte
        ) {
            $operation = Operation::creer(
                $entrepriseId, $pdvId, $date, 'ReglementAchat',
                $codeJournal, $refDoc, $contexte ?? 'Règlement fournisseur'
            );

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $libellePaiement, $compteFournisseurGeneral, null, $compteFournisseurTiers, $montant, 0);

            self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                $libellePaiement, null, $compteFinancier, null, 0, $montant);

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
                    $refDoc . '/Conso MP ' . $conso['produit']->nom, '603200', null, null, $valeurMp, 0);

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . '/Sortie Stock MP ' . $conso['produit']->nom, null, '311000', null, 0, $valeurMp);
            }

            if ($valeurProduction > 0) {
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . '/Entrée PF ' . $ordre->produitFini->nom, '351100', null, null, $valeurProduction, 0);

                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . '/Production stockée ' . $ordre->produitFini->nom, null, '731100', null, 0, $valeurProduction);
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
                $refDoc . '/Facturation Avoir Client', null, $compteClientGeneral, $compteClientTiers, 0, $avoir->montant_ttc);

            foreach ($ventilation['comptes'] as $compte => $montantHt) {
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / Avoir Vente - Compte ' . $compte, $compte, null, null, $montantHt, 0);
            }

            if ($ventilation['tva'] > 0) {
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . '/Annulation TVA Collectée', config('selflow.plan_comptable_defaut.tva_collectee'), null, null, $ventilation['tva'], 0);
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
                $refDoc . '/Facturation Avoir Fournisseur', $compteFournisseurGeneral, null, $compteFournisseurTiers, $avoir->montant_ttc, 0);

            foreach ($ventilation['comptes'] as $compte => $montantHt) {
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . ' / Avoir Achat - Compte ' . $compte, null, $compte, null, 0, $montantHt);
            }

            if ($ventilation['tva'] > 0) {
                self::ligne($operation, $entrepriseId, $pdvId, $date, $refDoc, $codeJournal,
                    $refDoc . '/Annulation TVA Déductible', null, config('selflow.plan_comptable_defaut.tva_deductible'), null, 0, $ventilation['tva']);
            }

            $operation->cloturerEquilibre();
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS INTERNES
    // ─────────────────────────────────────────────────────────────────

    /**
     * Crée une ligne d'écriture rattachée à une opération.
     * Un seul et unique endroit du code écrit dans ecritures_comptables :
     * garantit que operation_id et compte_tiers sont toujours renseignés
     * cohéremment (jamais de compte tiers écrit à la place du compte général).
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
        float $credit
    ): void {
        EcritureComptable::create([
            'operation_id'       => $operation->id,
            'entreprise_id'      => $entrepriseId,
            'point_de_vente_id'  => $pdvId,
            'date_ecriture'      => $date,
            'libelle'            => $libelle,
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
     * de la remise globale au prorata, et calcule la TVA totale.
     * @return array{comptes: array<string,float>, tva: float}
     */
    private static function ventilationVente(Vente $vente): array
    {
        $pourcentageRemise = ($vente->remise > 0 && $vente->montant_ht > 0)
            ? ($vente->remise / $vente->montant_ht)
            : 0;

        $comptes = [];
        foreach ($vente->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            if ($pourcentageRemise > 0) {
                $ht = $ht - ($ht * $pourcentageRemise);
            }
            if ($ht > 0) {
                $compte = $detail->produit?->compte_vente ?? config('selflow.plan_comptable_defaut.vente_defaut');
                $comptes[$compte] = ($comptes[$compte] ?? 0) + $ht;
            }
        }

        return ['comptes' => $comptes, 'tva' => (float) ($vente->montant_tva ?? 0)];
    }

    /**
     * Ventile les lignes d'un achat par compte de produit, et recalcule la
     * TVA totale ligne par ligne à partir du taux de chaque produit (plus
     * fiable que le montant de TVA saisi globalement sur la facture).
     * @return array{comptes: array<string,float>, tva: float}
     */
    private static function ventilationAchat(Achat $achat): array
    {
        $comptes = [];
        $totalTva = 0;
        foreach ($achat->details as $detail) {
            $ht = $detail->quantite * $detail->prix_unitaire;
            if ($ht > 0) {
                $compte = $detail->produit?->compte_achat ?? config('selflow.plan_comptable_defaut.achat_defaut');
                $comptes[$compte] = ($comptes[$compte] ?? 0) + $ht;

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
     * Dérive un libellé général d'opération à partir des comptes de vente
     * mouvementés (classe SYSCOHADA), au lieu d'un texte fixe générique.
     */
    private static function libelleGeneralVente(array $ventilation): string
    {
        return self::libelleGeneralDepuisComptes(array_keys($ventilation['comptes']), [
            '701' => 'Vente de marchandises',
            '702' => 'Vente de produits finis',
            '703' => "Vente de produits intermédiaires et résiduels",
            '704' => 'Travaux facturés',
            '705' => 'Services vendus',
            '706' => 'Travaux, services vendus',
            '707' => 'Produits accessoires',
        ], 'Vente de marchandises et services');
    }

    private static function libelleGeneralAchat(array $ventilation): string
    {
        return self::libelleGeneralDepuisComptes(array_keys($ventilation['comptes']), [
            '601' => 'Achat de marchandises',
            '602' => 'Achat de matières premières',
            '604' => 'Achat de fournitures non stockées',
            '605' => 'Autres achats',
            '606' => 'Fournitures non stockables',
        ], 'Achats divers');
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

    private static function libelleProduits($details): string
    {
        $produits = [];
        foreach ($details as $detail) {
            $nom = $detail->libelle_virtuel ?? $detail->produit?->nom;
            if ($nom) {
                $produits[] = $nom;
            }
        }
        return count($produits) > 0 ? implode(', ', array_unique($produits)) : 'Marchandises';
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
