<?php

namespace App\Modules\Admin\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Période retenue par les tableaux de bord.
 *
 * Les écrans se sont longtemps partagés en deux camps. Le tableau de bord
 * général filtrait par mois / semaine / jour à l'intérieur de la période
 * comptable active ; les écrans FNE, eux, prenaient un type de période et une
 * date de référence, sans considération pour l'exercice ouvert. Deux pages
 * ouvertes côte à côte annonçaient donc des chiffres différents pour ce que
 * l'utilisateur croyait être le même périmètre.
 *
 * Ce service tranche : une seule lecture des filtres, un seul intervalle.
 */
class FiltrePeriodeService
{
    /**
     * Restreindre une requête à la période retenue.
     *
     * C'est la méthode à préférer. Les filtres du tableau de bord général ne
     * décrivent pas un intervalle continu mais trois composantes de date
     * combinées : « jour 12 » sans mois désigne le douzième jour de chaque
     * mois, pas une seule date. Reproduire cela avec des bornes est
     * impossible ; on applique donc les mêmes conditions que lui, sur le fond
     * de l'exercice actif — que le PeriodeScope pose déjà sur les ventes et
     * les achats, mais pas sur la trésorerie, la production ni les transferts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function appliquer($query, string $colonneDate, Request $request)
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $colonneDate)) {
            throw new \InvalidArgumentException("Colonne de date invalide : {$colonneDate}");
        }

        [$debut, $fin] = self::exerciceActif();
        $query->whereBetween($colonneDate, [$debut, $fin]);

        $mois    = $request->query('filtre_mois', 'tous');
        $semaine = $request->query('filtre_semaine', 'tous');
        $jour    = $request->query('filtre_jour', 'tous');

        if ($mois !== 'tous') {
            $query->whereMonth($colonneDate, (int) $mois);
        }
        if ($semaine !== 'tous') {
            // Numérotation ISO, celle que la liste déroulante émet comme valeur.
            // Les écrans envoyaient un numéro ISO que le serveur relisait en
            // `WEEK(date, 1)` : les deux coïncident toute l'année sauf sur la
            // semaine à cheval sur le 1er janvier, que le mode 1 numérote 0.
            $query->whereRaw("WEEKOFYEAR({$colonneDate}) = ?", [(int) $semaine]);
        }
        if ($jour !== 'tous') {
            $query->whereDay($colonneDate, (int) $jour);
        }

        return $query;
    }

    /**
     * Intervalle [début, fin] englobant la période retenue.
     *
     * Utile pour l'afficher ou pour borner un traitement, jamais pour filtrer :
     * les filtres n'étant pas continus, ces bornes sont un englobant, pas un
     * équivalent. Voir `appliquer()`.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function intervalle(Request $request): array
    {
        [$debutExercice, $finExercice] = self::exerciceActif();

        $mois    = $request->query('filtre_mois', 'tous');
        $semaine = $request->query('filtre_semaine', 'tous');
        $jour    = $request->query('filtre_jour', 'tous');

        if ($mois === 'tous' && $semaine === 'tous' && $jour === 'tous') {
            return [$debutExercice, $finExercice];
        }

        $annee = $debutExercice->year;

        // Le mois pose le cadre ; à défaut, l'exercice entier sert de cadre et
        // seuls la semaine ou le jour viennent le restreindre.
        if ($mois !== 'tous') {
            $debut = Carbon::create($annee, (int) $mois, 1)->startOfMonth();
            $fin   = $debut->copy()->endOfMonth();
        } else {
            $debut = $debutExercice->copy();
            $fin   = $finExercice->copy();
        }

        if ($semaine !== 'tous') {
            // Semaine ISO, comme le WEEK(date, 1) de la requête historique.
            $debutSemaine = Carbon::now()->setISODate($annee, (int) $semaine)->startOfWeek();
            $finSemaine   = $debutSemaine->copy()->endOfWeek();

            $debut = $debut->greaterThan($debutSemaine) ? $debut : $debutSemaine;
            $fin   = $fin->lessThan($finSemaine) ? $fin : $finSemaine;
        }

        if ($jour !== 'tous') {
            // Un numéro de jour n'a de sens qu'à l'intérieur d'un mois : sans
            // mois choisi, on le rapporte au mois de la date de début.
            $moisDuJour = $mois !== 'tous' ? (int) $mois : $debut->month;
            $date = Carbon::create($annee, $moisDuJour, (int) $jour);

            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        return [$debut->startOfDay(), $fin->endOfDay()];
    }

    /**
     * Libellé de la période retenue, pour l'afficher tel quel à l'écran.
     */
    public static function libelle(Request $request): string
    {
        $mois    = $request->query('filtre_mois', 'tous');
        $semaine = $request->query('filtre_semaine', 'tous');
        $jour    = $request->query('filtre_jour', 'tous');

        if ($mois === 'tous' && $semaine === 'tous' && $jour === 'tous') {
            return session('active_periode_nom', 'période en cours');
        }

        $noms = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        $libelle = '';

        if ($mois !== 'tous') {
            $libelle = $noms[(int) $mois - 1] ?? ('Mois ' . $mois);
        }
        if ($jour !== 'tous') {
            $libelle = $mois !== 'tous' ? $jour . ' ' . $libelle : 'Jour ' . $jour;
        }
        if ($semaine !== 'tous') {
            // Dans un mois, les semaines sont nommées 1..N — leur rang dans le
            // mois — alors que la valeur transmise reste le numéro ISO.
            $libelle = 'Semaine ' . ($mois !== 'tous'
                ? (self::rangDeLaSemaineDansLeMois((int) $mois, (int) $semaine) ?? $semaine)
                : $semaine);
        }

        return $libelle;
    }

    /**
     * Rang (1..N) d'une semaine ISO à l'intérieur d'un mois de l'exercice.
     */
    private static function rangDeLaSemaineDansLeMois(int $mois, int $semaineIso): ?int
    {
        [$debutExercice] = self::exerciceActif();

        $premier = Carbon::create($debutExercice->year, $mois, 1)->startOfMonth();
        $dernier = $premier->copy()->endOfMonth();

        $semaines = [];
        for ($jour = $premier->copy(); $jour->lessThanOrEqualTo($dernier); $jour->addDay()) {
            $numero = (int) $jour->isoWeek();
            if (!in_array($numero, $semaines, true)) {
                $semaines[] = $numero;
            }
        }
        sort($semaines);

        $rang = array_search($semaineIso, $semaines, true);

        return $rang === false ? null : $rang + 1;
    }

    /**
     * Période comptable ouverte, ou l'année en cours à défaut.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function exerciceActif(): array
    {
        $debut = session('active_periode_debut');
        $fin   = session('active_periode_fin');

        if ($debut && $fin) {
            try {
                return [Carbon::parse($debut)->startOfDay(), Carbon::parse($fin)->endOfDay()];
            } catch (\Throwable $e) {
                // Session corrompue : on retombe sur l'année civile.
            }
        }

        return [now()->startOfYear(), now()->endOfYear()];
    }
}
