<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PortailFneFiche;
use App\Modules\Admin\Modeles\PortailFneImport;
use App\Modules\Admin\Modeles\PortailFnePointFacturation;
use App\Modules\Admin\Services\ImportPortailFneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce qu'un relevé du portail FNE devient une fois rangé.
 *
 * Trois choses se vérifient ici, et aucune n'est décorative :
 *
 * 1. **Le rattachement passe par le login.** Un relevé rangé chez la mauvaise
 *    entreprise mettrait les données fiscales d'un client dans le dossier d'un
 *    autre. Le login du portail est un NCC, et le NCC ne s'écrit pas partout de
 *    la même façon — `1864699A` ici, `1864699 A` là.
 * 2. **Le même fichier ne se range pas deux fois.** La commande est faite pour
 *    être relancée sur un dossier entier ; sans l'empreinte, chaque passage
 *    dupliquerait l'historique et fausserait « depuis quand ».
 * 3. **Rien ne remonte dans `entreprises`.** Le relevé dit que le timbre de
 *    quittance est actif au portail ; l'entreprise, dans Selflow, dit le
 *    contraire. C'est un écart à regarder, pas une mise à jour à appliquer —
 *    `timbre_quittance` change ce qui part à la DGI.
 */
class ImportPortailFneTest extends TestCase
{
    use RefreshDatabase;

    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dossier = storage_path('framework/testing/portail-fne-' . uniqid());
        mkdir($this->dossier, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dossier . '/*') ?: [] as $fichier) {
            unlink($fichier);
        }
        @rmdir($this->dossier);

        parent::tearDown();
    }

    public function test_la_fiche_json_est_rangee_et_rattachee_par_le_ncc(): void
    {
        // Le NCC est écrit avec un espace côté Selflow, sans espace au portail.
        $entreprise = $this->uneEntreprise(['ncc' => '1864699 A']);

        $this->poser('1864699A_20260821.json', json_encode([
            'Email'                                                => 'it.dcknowing@gmail.com',
            'Téléphone'                                            => '2722421443',
            'Adresse'                                              => '8XVQ+29Q',
            'Commune'                                              => 'COCODY',
            'Quartier'                                             => 'RIVIERA II AFRICAINE',
            'Référence Cadastrale'                                 => '*',
            'IDU'                                                  => '*',
            "Propriétaire du local professionnel de l'entreprise"  => null,
            "Sticker : solde d'alerte"                             => '5000',
            'Références bancaires'                                 => null,
            'Timbre de quittance'                                  => true,
            "Bordereau d'achat de produits agricoles"              => true,
            'Pied de page des factures'                            => null,
            'Factures autres mentions'                             => null,
        ], JSON_UNESCAPED_UNICODE));

        $rapport = app(ImportPortailFneService::class)->importerDossier($this->dossier);

        $this->assertSame(1, $rapport['importes']);
        $this->assertSame(0, $rapport['erreurs']);

        $fiche = PortailFneFiche::sole();

        $this->assertSame($entreprise->id, $fiche->entreprise_id);
        $this->assertSame('1864699A', $fiche->login);
        $this->assertSame('21/08/2026', $fiche->date_scraping->format('d/m/Y'));
        $this->assertSame('COCODY', $fiche->commune);

        // « 5000 » entre guillemets au portail, un entier en base.
        $this->assertSame(5000, $fiche->sticker_solde_alerte);
        $this->assertTrue($fiche->timbre_quittance);
        $this->assertTrue($fiche->bapa);

        // « * » est la façon dont le portail écrit « je n'ai pas cette valeur ».
        $this->assertNull($fiche->idu);
        $this->assertNull($fiche->reference_cadastrale);

        // Tous les libellés du portail ont trouvé une colonne.
        $this->assertNull($fiche->champs_inconnus);
    }

    public function test_un_libelle_inconnu_du_portail_est_conserve_et_non_jete(): void
    {
        $this->uneEntreprise(['ncc' => '1864699A']);

        $this->poser('1864699A_20260821.json', json_encode([
            'Email'                => 'contact@exemple.ci',
            'Nouveau champ DGI'    => 'valeur inattendue',
        ], JSON_UNESCAPED_UNICODE));

        app(ImportPortailFneService::class)->importerDossier($this->dossier);

        $fiche = PortailFneFiche::sole();

        $this->assertSame('contact@exemple.ci', $fiche->email);
        $this->assertSame(
            ['Nouveau champ DGI' => 'valeur inattendue'],
            $fiche->champs_inconnus
        );
    }

    public function test_le_meme_fichier_relu_ne_se_range_pas_deux_fois(): void
    {
        $this->uneEntreprise(['ncc' => '1864699A']);

        $this->poser('1864699A_20260821.json', json_encode(['Email' => 'a@b.ci']));

        $service = app(ImportPortailFneService::class);

        $premier = $service->importerDossier($this->dossier);
        $second  = $service->importerDossier($this->dossier);

        $this->assertSame(1, $premier['importes']);
        $this->assertSame(0, $second['importes']);
        $this->assertSame(1, $second['ignores']);
        $this->assertSame(1, PortailFneImport::count());
        $this->assertSame(1, PortailFneFiche::count());
    }

    public function test_un_nom_hors_nomenclature_est_refuse_plutot_que_devine(): void
    {
        $this->uneEntreprise(['ncc' => '1864699A']);

        $this->poser('5e7062d7c617200950f6340ad47cedd1.json', json_encode(['Email' => 'a@b.ci']));

        $rapport = app(ImportPortailFneService::class)->importerDossier($this->dossier);

        $this->assertSame(0, $rapport['importes']);
        $this->assertSame(1, $rapport['erreurs']);
        $this->assertSame(0, PortailFneImport::count());
    }

    public function test_un_login_inconnu_conserve_le_releve_sans_le_rattacher(): void
    {
        $this->poser('0000000Z_20260821.json', json_encode(['Email' => 'a@b.ci']));

        $rapport = app(ImportPortailFneService::class)->importerDossier($this->dossier);

        $this->assertSame(1, $rapport['importes']);

        $import = PortailFneImport::sole();

        $this->assertNull($import->entreprise_id);
        $this->assertTrue($import->estOrphelin());
    }

    public function test_le_tableur_des_points_de_facturation_est_lu_par_entete(): void
    {
        $entreprise = $this->uneEntreprise(['ncc' => '1864699A']);

        // Colonnes volontairement dans un autre ordre que celui du portail :
        // la lecture doit suivre les en-têtes, pas les positions.
        $this->poserTableur('1864699A_20260821.xlsx', [
            ["ID de l'établissement", 'Nom', 'Statut', 'Outil', 'ID du terminal', 'Raison de statut', 'Créé à', 'Mise à jour à'],
            ['42200613-f402-40a8-bd4d-a778bb5b96f0', 'FACTURATION SIEGE', '1', 'Application FNE', '', '', '2026-07-30T10:38:40.726Z', '2026-07-30T10:38:40.726Z'],
            ['', '', '', '', '', '', '', ''],
        ]);

        $rapport = app(ImportPortailFneService::class)->importerDossier($this->dossier);

        $this->assertSame(1, $rapport['importes']);

        // La ligne vide du tableur n'est pas un point de facturation.
        $point = PortailFnePointFacturation::sole();

        $this->assertSame($entreprise->id, $point->entreprise_id);
        $this->assertSame('FACTURATION SIEGE', $point->nom);
        $this->assertSame('Application FNE', $point->outil);
        $this->assertSame('42200613-f402-40a8-bd4d-a778bb5b96f0', $point->etablissement_id);
        $this->assertNull($point->terminal_id);
        $this->assertTrue($point->estActif());
        $this->assertSame('30/07/2026 10:38', $point->cree_a->format('d/m/Y H:i'));
    }

    public function test_le_releve_ne_modifie_pas_l_entreprise(): void
    {
        $entreprise = $this->uneEntreprise([
            'ncc'                  => '1864699A',
            'commune'              => null,
            'timbre_quittance'     => false,
            'bapa'                 => false,
            'sticker_solde_alerte' => 5,
        ]);

        $this->poser('1864699A_20260821.json', json_encode([
            'Commune'                                 => 'COCODY',
            "Sticker : solde d'alerte"                => '5000',
            'Timbre de quittance'                     => true,
            "Bordereau d'achat de produits agricoles" => true,
        ], JSON_UNESCAPED_UNICODE));

        app(ImportPortailFneService::class)->importerDossier($this->dossier);

        $entreprise->refresh();

        $this->assertNull($entreprise->commune);
        $this->assertFalse((bool) $entreprise->timbre_quittance);
        $this->assertFalse((bool) $entreprise->bapa);
        $this->assertSame(5, (int) $entreprise->sticker_solde_alerte);

        // L'écart est visible, il n'est simplement pas appliqué.
        $ecarts = PortailFneFiche::sole()->ecartsAvecEntreprise();

        $this->assertArrayHasKey('timbre_quittance', $ecarts);
        $this->assertArrayHasKey('sticker_solde_alerte', $ecarts);
        $this->assertSame(5000, $ecarts['sticker_solde_alerte']['portail']);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function uneEntreprise(array $attributs = []): Entreprise
    {
        return Entreprise::create(array_merge([
            'nom'               => 'DC-KNOWING CGA',
            'regime_imposition' => 'RNI',
            'adresse'           => 'RIVIERA II AFRICAINE',
            'rccm'              => 'CI-ABJ-2026-B-' . random_int(10000, 99999),
            'gerant_fonction'   => 'Gérant',
            'secteur_activite'  => ['Commerce'],
            'modules_actifs'    => ['principal', 'ventes', 'achats'],
        ], $attributs));
    }

    private function poser(string $nom, string $contenu): void
    {
        file_put_contents($this->dossier . DIRECTORY_SEPARATOR . $nom, $contenu);
    }

    /**
     * @param  array<int, array<int, string>>  $lignes
     */
    private function poserTableur(string $nom, array $lignes): void
    {
        $classeur = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $classeur->getActiveSheet()->fromArray($lignes, null, 'A1');

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($classeur))
            ->save($this->dossier . DIRECTORY_SEPARATOR . $nom);

        $classeur->disconnectWorksheets();
    }
}
