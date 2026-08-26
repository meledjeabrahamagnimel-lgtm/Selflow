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
     * Le plan comptable, **en entier**.
     *
     * Il ne l'était pas : l'entreprise recevait les 41 comptes marqués
     * « communs » — clients, fournisseurs, TVA, trésorerie, achats, ventes,
     * stocks — et rien d'autre. Les 1 256 comptes de l'acte uniforme restaient
     * un dictionnaire, servant à nommer une subdivision sans jamais entrer dans
     * le plan de personne.
     *
     * Le compte manquait donc dès qu'on sortait de l'ordinaire : une
     * immobilisation, un emprunt, une charge de personnel, un impôt autre que
     * la TVA. Il fallait le créer à la main, en devinant son numéro — et une
     * imputation sur un compte inventé ne se rattrape pas : elle traverse la
     * balance, le grand livre et la liasse.
     *
     * L'intitulé des comptes communs prime sur celui de l'acte uniforme, et
     * c'est déjà vrai en base : le référentiel les y écrase à l'ensemencement,
     * parce que « État, TVA facturée (18 % — régime réel) » dit plus que
     * « État, TVA facturée ».
     */
    private static function poserComptesCommuns(Entreprise $entreprise): int
    {
        $deja = PlanComptable::where('entreprise_id', $entreprise->id)
            ->pluck('numero')
            ->flip();

        $aPoser = Compte::orderBy('numero')->get()
            ->reject(fn (Compte $compte) => $deja->has($compte->numero))
            ->map(fn (Compte $compte) => [
                'entreprise_id' => $entreprise->id,
                'numero'        => $compte->numero,
                'libelle'       => $compte->intitule,
                'created_at'    => now(),
                'updated_at'    => now(),
            ])
            ->all();

        // Une ligne à la fois ferait plus de mille requêtes à chaque création
        // d'entreprise, et autant à chaque clic sur « Poser le plan complet ».
        foreach (array_chunk($aPoser, 200) as $lot) {
            PlanComptable::insert($lot);
        }

        return count($aPoser);
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
                'systeme'       => $journal['systeme'] ?? false,
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
