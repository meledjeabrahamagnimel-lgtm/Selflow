<?php

namespace Tests\Feature;

use App\Modules\Admin\Services\FiltrePeriodeService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Un seul filtre de période pour tous les tableaux de bord.
 *
 * Le tableau de bord général filtrait par mois / semaine / jour à l'intérieur
 * de la période comptable active ; les écrans FNE prenaient un type de période
 * et une date de référence, sans considérer l'exercice ouvert. Deux pages
 * ouvertes côte à côte annonçaient donc des chiffres différents pour ce que
 * l'utilisateur croyait être le même périmètre.
 *
 * Ces tests verrouillent la sémantique retenue, celle du tableau de bord
 * général : trois composantes de date combinées, appliquées sur le fond de
 * l'exercice actif.
 */
class FiltrePeriodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session([
            'active_periode_debut' => '2026-01-01',
            'active_periode_fin'   => '2026-12-31',
            'active_periode_nom'   => 'Exercice 2026',
        ]);
    }

    private function requete(array $filtres = []): Request
    {
        return Request::create('/', 'GET', $filtres);
    }

    /** Le SQL produit, pour lire ce que le filtre a réellement posé. */
    private function sql(array $filtres, string $colonne = 'date_vente'): string
    {
        $query = \Illuminate\Support\Facades\DB::table('ventes');
        FiltrePeriodeService::appliquer($query, $colonne, $this->requete($filtres));

        return $query->toSql();
    }

    public function test_sans_filtre_l_intervalle_est_l_exercice_actif(): void
    {
        [$debut, $fin] = FiltrePeriodeService::intervalle($this->requete());

        $this->assertSame('2026-01-01', $debut->toDateString());
        $this->assertSame('2026-12-31', $fin->toDateString());
    }

    public function test_sans_exercice_en_session_l_annee_civile_sert_de_repli(): void
    {
        session()->forget(['active_periode_debut', 'active_periode_fin']);

        [$debut, $fin] = FiltrePeriodeService::intervalle($this->requete());

        $this->assertSame(now()->startOfYear()->toDateString(), $debut->toDateString());
        $this->assertSame(now()->endOfYear()->toDateString(), $fin->toDateString());
    }

    public function test_le_mois_borne_l_intervalle_au_mois_choisi(): void
    {
        [$debut, $fin] = FiltrePeriodeService::intervalle($this->requete(['filtre_mois' => '3']));

        $this->assertSame('2026-03-01', $debut->toDateString());
        $this->assertSame('2026-03-31', $fin->toDateString());
    }

    public function test_mois_et_jour_designent_une_seule_date(): void
    {
        [$debut, $fin] = FiltrePeriodeService::intervalle(
            $this->requete(['filtre_mois' => '3', 'filtre_jour' => '12'])
        );

        $this->assertSame('2026-03-12', $debut->toDateString());
        $this->assertSame('2026-03-12', $fin->toDateString());
    }

    /**
     * Le cas qui interdit de filtrer par bornes.
     *
     * « Jour 12 » sans mois désigne le douzième jour de CHAQUE mois, comme sur
     * le tableau de bord général. Aucun intervalle continu ne décrit cela :
     * `appliquer()` pose donc une condition sur le jour du mois, pas des bornes.
     */
    public function test_le_jour_seul_porte_sur_tous_les_mois(): void
    {
        $sql = strtolower($this->sql(['filtre_jour' => '12']));

        // MySQL compile `whereDay` en day(), SQLite en strftime('%d', …).
        $this->assertTrue(
            str_contains($sql, 'day(') || str_contains($sql, "'%d'"),
            "Le filtre jour devrait porter sur le jour du mois : {$sql}"
        );
        $this->assertFalse(
            str_contains($sql, 'month(') || str_contains($sql, "'%m'"),
            "Le filtre jour seul ne devrait restreindre aucun mois : {$sql}"
        );
    }

    public function test_l_exercice_actif_borne_toujours_la_requete(): void
    {
        // Même filtrée sur un mois, la requête reste bornée par l'exercice :
        // c'est ce que le PeriodeScope fait aux ventes et aux achats, et ce que
        // la trésorerie, la production et les transferts n'avaient pas.
        $sql = strtolower($this->sql(['filtre_mois' => '3']));

        $this->assertStringContainsString('between', $sql);
        $this->assertTrue(
            str_contains($sql, 'month(') || str_contains($sql, "'%m'"),
            "Le filtre mois devrait s'ajouter aux bornes de l'exercice : {$sql}"
        );
    }

    public function test_la_semaine_est_numerotee_comme_la_liste_deroulante(): void
    {
        // Les listes déroulantes émettent un numéro de semaine ISO ; le serveur
        // le relisait en `WEEK(date, 1)`, qui numérote 0 la semaine à cheval sur
        // le 1er janvier. Les deux doivent parler la même langue.
        $this->assertStringContainsString(
            'WEEKOFYEAR(date_vente)',
            $this->sql(['filtre_semaine' => '7'])
        );
    }

    public function test_le_libelle_numerote_la_semaine_par_son_rang_dans_le_mois(): void
    {
        // Mars 2026 couvre les semaines ISO 9 à 14 : la 11e est la troisième du
        // mois. C'est ce rang que les listes déroulantes affichent.
        $this->assertSame(
            'Semaine 3',
            FiltrePeriodeService::libelle($this->requete(['filtre_mois' => '3', 'filtre_semaine' => '11']))
        );
    }

    public function test_le_libelle_garde_le_numero_iso_hors_d_un_mois(): void
    {
        $this->assertSame(
            'Semaine 11',
            FiltrePeriodeService::libelle($this->requete(['filtre_semaine' => '11']))
        );
    }

    public function test_une_colonne_de_date_invalide_est_refusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sql([], 'date_vente); drop table ventes; --');
    }

    public function test_le_libelle_reprend_le_nom_de_l_exercice_sans_filtre(): void
    {
        $this->assertSame('Exercice 2026', FiltrePeriodeService::libelle($this->requete()));
    }

    public function test_le_libelle_nomme_le_mois_choisi(): void
    {
        $this->assertStringContainsString(
            'Mars',
            FiltrePeriodeService::libelle($this->requete(['filtre_mois' => '3']))
        );
    }
}
