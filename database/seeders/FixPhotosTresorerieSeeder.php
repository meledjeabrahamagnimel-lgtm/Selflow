<?php

namespace Database\Seeders;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixPhotosTresorerieSeeder extends Seeder
{
    public function run(): void
    {
        // ═══════════════════════════════════════════════════════════════
        // FIX 1 — Remplacer Unsplash par picsum.photos (HTTP/CORS libre)
        // ═══════════════════════════════════════════════════════════════
        $produits = Produit::whereNotNull('photo')->get();
        $photoUpdated = 0;
        foreach ($produits as $p) {
            if (strpos($p->photo, 'unsplash.com') !== false) {
                $seed = abs(crc32($p->nom . $p->id)) % 1000;
                DB::table('produits')
                    ->where('id', $p->id)
                    ->update(['photo' => 'https://picsum.photos/seed/' . $seed . '/400/300']);
                $photoUpdated++;
            }
        }
        $this->command->info("  Photos mises a jour : {$photoUpdated}");

        // ═══════════════════════════════════════════════════════════════
        // FIX 2 — Créer les entrées TresorerieJournal manquantes
        // ═══════════════════════════════════════════════════════════════
        $modeMap = [
            'especes'   => 'Espèces',
            'espèces'   => 'Espèces',
            'virement'  => 'Virement',
            'cheque'    => 'Chèque',
            'chèque'    => 'Chèque',
            'carte'     => 'Carte bancaire',
            'mobile'    => 'Mobile Money',
            'credit'    => 'Crédit',
            'crédit'    => 'Crédit',
        ];

        $inserted = 0;

        // ── Ventes payées → recettes ──────────────────────────────────
        $ventes = Vente::whereIn('statut', ['Payé', 'Paye'])->get();
        foreach ($ventes as $v) {
            $exists = TresorerieJournal::where('reference_document', $v->numero_facture)->exists();
            if ($exists || $v->montant_ttc <= 0) {
                continue;
            }
            $solde = TresorerieJournal::where('point_de_vente_id', $v->point_de_vente_id)
                ->orderByDesc('id')->value('solde_resultat') ?? 0;
            $mode = $modeMap[strtolower($v->mode_paiement ?? '')] ?? 'Espèces';
            TresorerieJournal::create([
                'point_de_vente_id'  => $v->point_de_vente_id,
                'utilisateur_id'     => $v->utilisateur_id,
                'date_operation'     => $v->date_vente,
                'type_operation'     => 'recette',
                'libelle'            => 'Encaissement — ' . $v->numero_facture,
                'mode_paiement'      => $mode,
                'moyen_bancaire'     => in_array(strtolower($v->mode_paiement ?? ''), ['virement', 'cheque', 'chèque']) ? 'BICICI — Compte courant' : null,
                'reference_paiement' => $v->numero_facture,
                'montant_entree'     => $v->montant_ttc,
                'montant_sortie'     => 0,
                'solde_resultat'     => $solde + $v->montant_ttc,
                'reference_document' => $v->numero_facture,
            ]);
            $inserted++;
        }

        // ── Achats payés → dépenses (avoirs → recettes) ───────────────
        $achats = Achat::whereIn('statut', ['Payé', 'Paye'])->get();
        foreach ($achats as $a) {
            $exists = TresorerieJournal::where('reference_document', $a->numero_facture)->exists();
            if ($exists) {
                continue;
            }
            $montant   = abs($a->montant_ttc);
            $isAvoir   = $a->montant_ttc < 0;
            $solde     = TresorerieJournal::where('point_de_vente_id', $a->point_de_vente_id)
                ->orderByDesc('id')->value('solde_resultat') ?? 0;
            $mode      = $modeMap[strtolower($a->mode_paiement ?? '')] ?? 'Virement';
            $nvSolde   = $isAvoir ? $solde + $montant : $solde - $montant;

            TresorerieJournal::create([
                'point_de_vente_id'  => $a->point_de_vente_id,
                'utilisateur_id'     => $a->utilisateur_id,
                'date_operation'     => $a->date_achat,
                'type_operation'     => $isAvoir ? 'recette' : 'depense',
                'libelle'            => $isAvoir ? 'Remboursement avoir — ' . $a->numero_facture : 'Règlement fournisseur — ' . $a->numero_facture,
                'mode_paiement'      => $mode,
                'moyen_bancaire'     => in_array(strtolower($a->mode_paiement ?? ''), ['virement', 'cheque', 'chèque']) ? 'BICICI — Compte courant' : null,
                'reference_paiement' => $a->numero_facture,
                'montant_entree'     => $isAvoir ? $montant : 0,
                'montant_sortie'     => $isAvoir ? 0 : $montant,
                'solde_resultat'     => $nvSolde,
                'reference_document' => $a->numero_facture,
            ]);
            $inserted++;
        }

        $this->command->info("  Entrees TresorerieJournal depuis ventes/achats : {$inserted}");

        // ── Opérations courantes supplémentaires pour enrichir le journal ─
        $pdvs = PointDeVente::with('utilisateurs')->get();
        $extraInserted = 0;

        $depensesCourantes = [
            ['libelle' => 'Loyer mensuel bureau', 'montant' => 350000, 'mode' => 'Virement', 'banque' => 'BICICI — Compte courant', 'jours' => -28],
            ['libelle' => 'Facture electricite SODECI', 'montant' => 45000, 'mode' => 'Espèces', 'banque' => null, 'jours' => -25],
            ['libelle' => 'Abonnement internet ORANGE', 'montant' => 72000, 'mode' => 'Virement', 'banque' => 'BICICI — Compte courant', 'jours' => -22],
            ['libelle' => 'Carburant vehicule livraison', 'montant' => 38000, 'mode' => 'Espèces', 'banque' => null, 'jours' => -18],
            ['libelle' => 'Entretien et reparations materiel', 'montant' => 125000, 'mode' => 'Chèque', 'banque' => 'BICICI — Compte courant', 'jours' => -14],
            ['libelle' => 'Achat fournitures de bureau', 'montant' => 28000, 'mode' => 'Espèces', 'banque' => null, 'jours' => -10],
            ['libelle' => 'Cotisation patronale CNPS', 'montant' => 210000, 'mode' => 'Virement', 'banque' => 'BICICI — Compte courant', 'jours' => -7],
            ['libelle' => 'Frais telephone et communication', 'montant' => 55000, 'mode' => 'Mobile Money', 'banque' => null, 'jours' => -4],
        ];

        $recettesDiverses = [
            ['libelle' => 'Vente comptoir — divers clients', 'montant' => 180000, 'mode' => 'Espèces', 'banque' => null, 'jours' => -20],
            ['libelle' => 'Encaissement avance client pro', 'montant' => 500000, 'mode' => 'Virement', 'banque' => 'BICICI — Compte courant', 'jours' => -15],
            ['libelle' => 'Remboursement frais transport', 'montant' => 35000, 'mode' => 'Espèces', 'banque' => null, 'jours' => -8],
            ['libelle' => 'Prestation service conseil', 'montant' => 250000, 'mode' => 'Virement', 'banque' => 'BICICI — Compte courant', 'jours' => -3],
        ];

        foreach ($pdvs as $pdv) {
            $userId = $pdv->utilisateurs->first()?->id ?? 1;

            foreach ($depensesCourantes as $d) {
                $solde = TresorerieJournal::where('point_de_vente_id', $pdv->id)->orderByDesc('id')->value('solde_resultat') ?? 0;
                TresorerieJournal::create([
                    'point_de_vente_id'  => $pdv->id,
                    'utilisateur_id'     => $userId,
                    'date_operation'     => now()->addDays($d['jours']),
                    'type_operation'     => 'depense',
                    'libelle'            => $d['libelle'],
                    'mode_paiement'      => $d['mode'],
                    'moyen_bancaire'     => $d['banque'],
                    'reference_paiement' => null,
                    'montant_entree'     => 0,
                    'montant_sortie'     => $d['montant'],
                    'solde_resultat'     => $solde - $d['montant'],
                    'reference_document' => null,
                ]);
                $extraInserted++;
            }

            foreach ($recettesDiverses as $r) {
                $solde = TresorerieJournal::where('point_de_vente_id', $pdv->id)->orderByDesc('id')->value('solde_resultat') ?? 0;
                TresorerieJournal::create([
                    'point_de_vente_id'  => $pdv->id,
                    'utilisateur_id'     => $userId,
                    'date_operation'     => now()->addDays($r['jours']),
                    'type_operation'     => 'recette',
                    'libelle'            => $r['libelle'],
                    'mode_paiement'      => $r['mode'],
                    'moyen_bancaire'     => $r['banque'],
                    'reference_paiement' => null,
                    'montant_entree'     => $r['montant'],
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $solde + $r['montant'],
                    'reference_document' => null,
                ]);
                $extraInserted++;
            }
        }

        $this->command->info("  Operations courantes supplementaires : {$extraInserted}");

        $total = TresorerieJournal::count();
        $this->command->info("  TOTAL TresorerieJournal : {$total} entrees");
        $this->command->info('Fix termine avec succes.');
    }
}
