<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PlanComptable;
use App\Modules\Admin\Modeles\Referentiel\Compte;
use Illuminate\Support\Facades\DB;

/**
 * De quoi travailler dès le premier jour.
 *
 * Une entreprise qui vient d'être créée n'a ni plan comptable ni journal : la
 * première vente échoue, ou pire, s'impute sur des comptes inventés à la volée.
 * Ce service lui donne le nécessaire — les comptes communs à tous les profils
 * et les journaux usuels de Côte d'Ivoire, mobile money compris.
 *
 * Rien de tout cela ne dépend de Comptaflow : une entreprise sans abonnement
 * comptable doit pouvoir tenir ses écritures dans Selflow seul.
 *
 * Deux principes :
 *
 * - **Ce qui ne sert pas s'archive, il ne se supprime pas.** Un compte ou un
 *   journal effacé après avoir servi laisserait des écritures orphelines.
 * - **Rien n'écrase ce que l'entreprise a déjà.** Le service peut tourner deux
 *   fois — à la création puis à la souscription d'un profil — sans rien
 *   dupliquer ni rien réécrire de ce que l'utilisateur a modifié.
 */
class TrousseauEntrepriseService
{
    /**
     * Doter une entreprise de son plan et de ses journaux.
     *
     * @return array{comptes: int, journaux: int} ce qui a été effectivement créé
     */
    public static function doter(Entreprise $entreprise): array
    {
        return DB::transaction(fn () => [
            'comptes'  => self::poserComptesCommuns($entreprise),
            'journaux' => self::poserJournauxUsuels($entreprise),
        ]);
    }

    /**
     * Les comptes que tout profil reçoit : clients, fournisseurs, TVA,
     * trésorerie, achats, ventes, stocks.
     */
    private static function poserComptesCommuns(Entreprise $entreprise): int
    {
        $crees = 0;

        foreach (Compte::where('commun', true)->orderBy('numero')->get() as $compte) {
            $existe = PlanComptable::where('entreprise_id', $entreprise->id)
                ->where('numero', $compte->numero)
                ->exists();

            if ($existe) {
                continue;
            }

            PlanComptable::create([
                'entreprise_id' => $entreprise->id,
                'numero'        => $compte->numero,
                'libelle'       => $compte->intitule,
            ]);
            $crees++;
        }

        return $crees;
    }

    /**
     * Les journaux usuels.
     *
     * Le mobile money est rangé en subdivision de `521` « Banques locales », à
     * partir de `521500` : c'est un avoir détenu chez un établissement agréé,
     * pas de l'espèce en caisse. L'acte uniforme est antérieur à ces moyens de
     * paiement et n'en prévoit aucun compte ; `5211` à `5214` restent libres
     * pour les banques de l'entreprise. Un expert-comptable qui préfère un
     * autre rangement n'a qu'à modifier le compte du journal.
     */
    private static function poserJournauxUsuels(Entreprise $entreprise): int
    {
        $crees = 0;

        foreach (self::journauxParDefaut() as $journal) {
            $existe = CodeJournal::where('entreprise_id', $entreprise->id)
                ->where('code', $journal['code'])
                ->exists();

            if ($existe) {
                continue;
            }

            CodeJournal::create([
                'entreprise_id' => $entreprise->id,
                'code'          => $journal['code'],
                'type'          => $journal['type'],
                'intitule'      => $journal['intitule'],
                'compte'        => $journal['compte'],
            ]);
            $crees++;

            // Un journal de trésorerie porte un compte : il doit exister au plan
            // pour que l'écriture de règlement s'impute quelque part.
            if ($journal['compte']) {
                self::assurerCompte($entreprise, $journal['compte'], $journal['intitule']);
            }
        }

        return $crees;
    }

    /**
     * Créer un compte au plan de l'entreprise s'il n'y figure pas.
     *
     * L'intitulé vient du référentiel quand celui-ci le connaît ; sinon, le nom
     * du journal fait l'affaire — « MTN Mobile Money » vaut mieux que
     * « Banques locales » sur un compte qui ne sert qu'à ça.
     */
    private static function assurerCompte(Entreprise $entreprise, string $numero, string $defaut): void
    {
        $existe = PlanComptable::where('entreprise_id', $entreprise->id)
            ->where('numero', $numero)
            ->exists();

        if ($existe) {
            return;
        }

        PlanComptable::create([
            'entreprise_id' => $entreprise->id,
            'numero'        => $numero,
            'libelle'       => Compte::where('numero', $numero)->where('commun', true)->value('intitule')
                ?? $defaut,
        ]);
    }

    /**
     * @return array<int, array{code: string, type: string, intitule: string, compte: ?string}>
     */
    public static function journauxParDefaut(): array
    {
        $chemin = database_path('data/referentiel/journaux_defaut.json');

        if (!is_file($chemin)) {
            throw new \RuntimeException("Journaux par défaut introuvables : {$chemin}");
        }

        return json_decode(file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);
    }
}
