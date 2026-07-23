<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Operation;
use App\Modules\Admin\Modeles\Entreprise;

/**
 * Migre les écritures comptables historiques (créées avant le correctif du
 * 22/07/2026) vers la nouvelle architecture :
 *   1. Rattache chaque écriture à une Operation réelle (operation_id),
 *      groupée par (entreprise, code_journal, reference_document) — ce
 *      qui sépare naturellement une ancienne "facture" (journal VTE/ACH)
 *      de son ancien "règlement" (journal CAI/BQ), puisqu'ils utilisaient
 *      déjà des code_journal différents.
 *   2. Détecte les lignes où compte_debit/compte_credit contient en réalité
 *      un CODE TIERS INDIVIDUEL (ex: 411001) au lieu du compte collectif
 *      général (ex: 411000) — bug historique — et déplace la valeur vers
 *      la colonne compte_tiers, en remettant le compte général réel.
 *
 * ⚠️ CE SCRIPT N'A PAS ÉTÉ TESTÉ SUR UNE BASE DE DONNÉES RÉELLE (rédigé
 * dans un environnement sans accès à la base de production). À exécuter
 * OBLIGATOIREMENT sur une copie de sauvegarde / environnement de staging
 * avant toute exécution sur la base réelle. Tourne en mode simulation
 * (--dry-run) par défaut : aucune écriture n'est modifiée tant que
 * --force n'est pas explicitement passé.
 *
 * Usage :
 *   php artisan selflow:migrer-ecritures-historiques                (simulation, affiche le rapport)
 *   php artisan selflow:migrer-ecritures-historiques --force         (applique réellement les changements)
 *   php artisan selflow:migrer-ecritures-historiques --entreprise=3  (limiter à une entreprise)
 */
class MigrerEcrituresHistoriques extends Command
{
    protected $signature = 'selflow:migrer-ecritures-historiques
                            {--force : Applique réellement les changements (sinon simulation seule)}
                            {--entreprise= : ID de l\'entreprise à traiter (optionnel, sinon toutes)}';

    protected $description = 'Rattache les écritures historiques (sans operation_id) à de vraies Operations et sépare compte général / compte tiers.';

    public function handle(): int
    {
        $dryRun = !$this->option('force');
        $entrepriseId = $this->option('entreprise');

        if ($dryRun) {
            $this->warn('⚠️  MODE SIMULATION (--dry-run implicite) — aucune donnée ne sera modifiée.');
            $this->warn('   Relancez avec --force une fois le rapport vérifié pour appliquer réellement.');
        } else {
            $this->error('🔴 MODE APPLICATION RÉELLE — les données vont être modifiées.');
            if (!$this->confirm('Avez-vous fait une sauvegarde complète de la base avant de continuer ?')) {
                $this->info('Annulé. Faites une sauvegarde puis relancez.');
                return self::FAILURE;
            }
        }

        $query = EcritureComptable::whereNull('operation_id');
        if ($entrepriseId) {
            $query->where('entreprise_id', $entrepriseId);
        }

        $total = $query->count();
        $this->info("📊 {$total} écriture(s) historique(s) sans operation_id trouvée(s).");

        if ($total === 0) {
            $this->info('Rien à migrer.');
            return self::SUCCESS;
        }

        $entreprises = Entreprise::whereIn('id', $query->distinct()->pluck('entreprise_id'))->get()->keyBy('id');

        // Groupement (entreprise, code_journal, reference_document) — reproduit fidèlement
        // le comportement de l'ancien affichage (MIN(id) par ce même triplet), mais en
        // créant cette fois une vraie ligne Operation au lieu d'un simple recalcul d'affichage.
        $groupes = $query->get()->groupBy(function ($e) {
            return $e->entreprise_id . '|' . $e->code_journal . '|' . ($e->reference_document ?? 'SANS-REF-' . $e->id);
        });

        $this->info('📦 ' . $groupes->count() . ' groupe(s) (= future(s) Operation) détecté(s).');

        $nbOperationsCreees = 0;
        $nbLignesReattachees = 0;
        $nbTiersCorriges = 0;
        $nbDesequilibrees = 0;
        $anomalies = [];

        $bar = $this->output->createProgressBar($groupes->count());
        $bar->start();

        foreach ($groupes as $cle => $lignes) {
            [$entId, $codeJournal, $refDoc] = explode('|', $cle, 3);
            $entId = (int) $entId;
            $entreprise = $entreprises->get($entId);

            $totalDebit = round((float) $lignes->sum('debit'), 2);
            $totalCredit = round((float) $lignes->sum('credit'), 2);
            $estEquilibree = abs($totalDebit - $totalCredit) < 0.01;

            if (!$estEquilibree) {
                $nbDesequilibrees++;
                $anomalies[] = "Groupe déséquilibré : entreprise #{$entId}, journal {$codeJournal}, réf {$refDoc} (débit={$totalDebit}, crédit={$totalCredit}) — À VÉRIFIER MANUELLEMENT, non migré automatiquement.";
                $bar->advance();
                continue;
            }

            // Type d'opération déduit heuristiquement (best-effort, pour un
            // libellé/traçabilité correcte — n'affecte pas les montants)
            $typeOperation = $this->deviderTypeOperation($codeJournal, $refDoc, $lignes);

            $premiereLigneDate = $lignes->first()->date_ecriture;
            $premierePdv = $lignes->first()->point_de_vente_id;
            $premierLibelle = $lignes->first()->libelle;

            if (!$dryRun) {
                DB::transaction(function () use (
                    $entId, $premierePdv, $premiereLigneDate, $typeOperation,
                    $codeJournal, $refDoc, $premierLibelle, $lignes, $entreprise, &$nbTiersCorriges
                ) {
                    $operation = Operation::creer(
                        $entId, $premierePdv, $premiereLigneDate, $typeOperation,
                        $codeJournal, $refDoc !== null && !str_starts_with($refDoc, 'SANS-REF-') ? $refDoc : null,
                        $premierLibelle
                    );
                    $operation->est_equilibree = true;
                    $operation->save();

                    foreach ($lignes as $ligne) {
                        $this->corrigerCompteTiersSiNecessaire($ligne, $entreprise, $nbTiersCorriges);
                        $ligne->operation_id = $operation->id;
                        $ligne->save();
                    }
                });
            } else {
                // Simulation : on détecte quand même les corrections de compte tiers à venir, sans écrire
                foreach ($lignes as $ligne) {
                    if ($this->detecterCompteTiersAAmeliorer($ligne, $entreprise)) {
                        $nbTiersCorriges++;
                    }
                }
            }

            $nbOperationsCreees++;
            $nbLignesReattachees += $lignes->count();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Indicateur', 'Valeur'],
            [
                ['Opérations ' . ($dryRun ? 'à créer' : 'créées'), $nbOperationsCreees],
                ['Lignes ' . ($dryRun ? 'à rattacher' : 'rattachées'), $nbLignesReattachees],
                ['Lignes avec compte tiers ' . ($dryRun ? 'à corriger' : 'corrigées'), $nbTiersCorriges],
                ['Groupes déséquilibrés (NON migrés, à traiter manuellement)', $nbDesequilibrees],
            ]
        );

        if (!empty($anomalies)) {
            $this->warn('⚠️  Anomalies détectées (non migrées automatiquement) :');
            foreach (array_slice($anomalies, 0, 30) as $a) {
                $this->line('   - ' . $a);
            }
            if (count($anomalies) > 30) {
                $this->line('   ... et ' . (count($anomalies) - 30) . ' de plus (voir logs).');
            }
        }

        if ($dryRun) {
            $this->info('✅ Simulation terminée. Relancez avec --force pour appliquer réellement ces changements.');
        } else {
            $this->info('✅ Migration appliquée avec succès.');
        }

        return self::SUCCESS;
    }

    /**
     * Déduit le type d'opération le plus probable à partir du code journal
     * et de la présence (ou non) d'un compte 411/401 dans les lignes.
     * Best-effort — sert uniquement à la lisibilité/traçabilité future,
     * n'affecte jamais les montants ni les comptes existants.
     */
    private function deviderTypeOperation(string $codeJournal, string $refDoc, $lignes): string
    {
        $aUnCompteClientOuFournisseur = $lignes->contains(function ($l) {
            $c = $l->compte_debit ?? $l->compte_credit ?? '';
            return str_starts_with($c, '411') || str_starts_with($c, '401');
        });

        if (str_starts_with($refDoc, 'OD-') || str_starts_with($refDoc, 'MN-')) {
            return 'OD';
        }
        if (str_starts_with($refDoc, 'AVO-VTE-')) {
            return 'AvoirVente';
        }
        if (str_starts_with($refDoc, 'AVO-ACH-')) {
            return 'AvoirAchat';
        }
        if (str_starts_with($refDoc, 'AV-')) {
            // Ancien préfixe générique d'avoir (avant le 22/07/2026), utilisé
            // aussi bien côté vente qu'achat — on distingue via la présence
            // du compte client (411) vs fournisseur (401) dans les lignes.
            $aUnCompte411 = $lignes->contains(function ($l) {
                $c = $l->compte_debit ?? $l->compte_credit ?? '';
                return str_starts_with($c, '411');
            });
            return $aUnCompte411 ? 'AvoirVente' : 'AvoirAchat';
        }
        if (str_starts_with($refDoc, 'VTE-') || str_starts_with($refDoc, 'VT-') || str_starts_with($refDoc, 'BC-') || str_starts_with($refDoc, 'DV-')) {
            return $aUnCompteClientOuFournisseur ? 'FactureVente' : 'ReglementVente';
        }
        if (str_starts_with($refDoc, 'ACH-') || str_starts_with($refDoc, 'AC-') || str_starts_with($refDoc, 'DP-')) {
            return $aUnCompteClientOuFournisseur ? 'FactureAchat' : 'ReglementAchat';
        }
        return 'OD';
    }

    /**
     * Si une ligne a, dans compte_debit/compte_credit, une valeur qui
     * ressemble à un code tiers individuel (préfixe 411/401 mais différent
     * du compte collectif générique de l'entreprise) plutôt qu'au compte
     * général réel, la corrige : déplace la valeur vers compte_tiers et
     * remet le compte général par défaut à la place.
     */
    private function corrigerCompteTiersSiNecessaire(EcritureComptable $ligne, ?Entreprise $entreprise, int &$compteur): void
    {
        if ($this->detecterCompteTiersAAmeliorer($ligne, $entreprise)) {
            $estClient = str_starts_with($ligne->compte_debit ?? $ligne->compte_credit ?? '', '411');
            $compteGeneral = $estClient
                ? config('selflow.plan_comptable_defaut.client_collectif')
                : config('selflow.plan_comptable_defaut.fournisseur_collectif');

            if ($ligne->compte_debit) {
                $ligne->compte_tiers = $ligne->compte_debit;
                $ligne->compte_debit = $compteGeneral;
            } else {
                $ligne->compte_tiers = $ligne->compte_credit;
                $ligne->compte_credit = $compteGeneral;
            }
            $compteur++;
        }
    }

    private function detecterCompteTiersAAmeliorer(EcritureComptable $ligne, ?Entreprise $entreprise): bool
    {
        if ($ligne->compte_tiers) {
            return false; // déjà correctement renseigné
        }
        $compte = $ligne->compte_debit ?? $ligne->compte_credit ?? '';
        if (!str_starts_with($compte, '411') && !str_starts_with($compte, '401')) {
            return false;
        }
        // Compte générique attendu (401000/411000) — si la valeur diffère,
        // c'est très probablement un code tiers individuel mal placé.
        $general = str_starts_with($compte, '411')
            ? config('selflow.plan_comptable_defaut.client_collectif')
            : config('selflow.plan_comptable_defaut.fournisseur_collectif');

        return $compte !== $general;
    }
}
