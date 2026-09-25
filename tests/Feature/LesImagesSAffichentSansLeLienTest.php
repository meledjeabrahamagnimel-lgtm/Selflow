<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Services\FichierPublic;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les images s'affichent, que `public/storage` soit posé ou non.
 *
 * Tout ce qui est déposé — logos, photos d'articles, avatars, vitrine — vit
 * dans `storage/app/public` et s'affiche par `public/storage`, un lien
 * symbolique que pose `php artisan storage:link`.
 *
 * Sans ce lien, l'adresse répond 404 (Not Found — introuvable) et **rien ne le
 * dit** : l'image manque, la page se charge normalement. En local le lien est
 * posé depuis longtemps ; sur un hébergement mutualisé il ne l'est pas
 * toujours, et il ne survit pas à tous les déploiements.
 *
 * `Produit::photoReelle()` savait déjà se replier. **Les logos, non** : c'est
 * le défaut constaté le 25/09/2026, où les deux logos de l'entreprise
 * s'affichaient cassés dans les paramètres en production.
 */
class LesImagesSAffichentSansLeLienTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;

    protected function setUp(): void
    {
        parent::setUp();
        FichierPublic::oublierLeLien();

        $this->entreprise = Entreprise::create([
            'nom' => 'DC-KNOWING CGA', 'regime_imposition' => 'RNI',
            'adresse' => 'Riviera II', 'rccm' => 'CI-ABJ-2018-B-31734',
            'ncc' => '1864699A', 'gerant_fonction' => 'Gérant',
            'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'ventes', 'comptabilite'],
            'logo_path' => 'logos/dc-knowing.png',
        ]);

        $site = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'PDV-marcory', 'ville' => 'Abidjan', 'commune' => 'Marcory',
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Kouadio', 'prenom' => 'Lewis', 'email' => 'lewis-images@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $site->id,
        ]);
    }

    protected function tearDown(): void
    {
        FichierPublic::oublierLeLien();
        parent::tearDown();
    }

    /** Faire comme si le lien n'était pas posé. */
    private function sansLeLien(): void
    {
        FichierPublic::oublierLeLien();

        // `file_exists` suit le lien : on le fait répondre non en le rendant
        // introuvable pour la durée de l'épreuve.
        if (File::exists(public_path('storage'))) {
            $this->markTestSkipped('Le lien de stockage est pose sur ce poste.');
        }
    }

    // ══════════════ L'adresse rendue ══════════════

    public function test_avec_le_lien_l_adresse_est_directe(): void
    {
        if (!FichierPublic::lienPose()) {
            $this->markTestSkipped("Le lien de stockage n'est pas pose sur ce poste.");
        }

        // Servie par le serveur web, sans passer par PHP — et **relative** :
        // `asset()` la bâtissait sur `APP_URL`, et une `APP_URL` restée sur
        // l'adresse de développement faisait pointer l'image ailleurs.
        $this->assertSame('/storage/logos/dc-knowing.png', FichierPublic::url('logos/dc-knowing.png'));
    }

    public function test_sans_le_lien_l_adresse_passe_par_l_application(): void
    {
        $this->sansLeLien();

        $this->assertSame(
            route('admin.media', ['dossier' => 'logos', 'fichier' => 'dc-knowing.png']),
            FichierPublic::url('logos/dc-knowing.png')
        );
    }

    public function test_une_adresse_deja_complete_se_rend_telle_quelle(): void
    {
        // Certains logos ont été saisis comme des liens.
        $this->assertSame(
            'https://exemple.ci/logo.png',
            FichierPublic::url('https://exemple.ci/logo.png')
        );
    }

    public function test_rien_a_montrer_ne_rend_rien(): void
    {
        $this->assertNull(FichierPublic::url(null));
        $this->assertNull(FichierPublic::url(''));
    }

    // ══════════════ Ce que la porte refuse ══════════════

    public function test_la_porte_ne_laisse_pas_remonter_l_arborescence(): void
    {
        // Simulation d'attaque : le chemin vient d'une colonne, écrite par un
        // formulaire. Un `..` y remonterait jusqu'au `.env`.
        foreach ([
            'logos/../../../.env',
            '../.env',
            'logos/sous/dossier.png',
            'secrets/cle.txt',
        ] as $chemin) {
            $this->assertSame([null, null], FichierPublic::decouper($chemin), $chemin);
        }
    }

    public function test_la_route_refuse_un_dossier_inconnu(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/media/secrets/cle.txt')
            ->assertNotFound();
    }

    public function test_la_route_refuse_un_fichier_absent(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->get(route('admin.media', ['dossier' => 'logos', 'fichier' => 'absent.png']))
            ->assertNotFound();
    }

    public function test_la_route_sert_un_fichier_present(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/dc-knowing.png', 'contenu-du-logo');

        $this->actingAs($this->admin)
            ->get(route('admin.media', ['dossier' => 'logos', 'fichier' => 'dc-knowing.png']))
            ->assertOk();
    }

    public function test_on_n_entre_pas_sans_etre_connecte(): void
    {
        // Ces fichiers sont ceux des entreprises.
        $this->get(route('admin.media', ['dossier' => 'logos', 'fichier' => 'dc-knowing.png']))
            ->assertRedirect();
    }

    // ══════════════ Plus personne ne bâtit son adresse seul ══════════════

    public function test_aucun_ecran_ne_batit_son_adresse_a_part(): void
    {
        /*
         * La règle vivait dans `Produit` et nulle part ailleurs : les logos,
         * les avatars et la vitrine appelaient `Storage::url()` directement, et
         * n'avaient donc aucun repli. Une seule porte, une seule règle.
         */
        $fautifs = [];

        foreach (['app/Modules/Admin', 'app/Modules/Authentification'] as $racine) {
            $chemin = base_path($racine);

            foreach (File::allFiles($chemin) as $fichier) {
                $contenu = file_get_contents($fichier->getPathname());

                if (str_contains($contenu, "disk('public')->url(")) {
                    $fautifs[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $fichier->getPathname());
                }
            }
        }

        $this->assertSame([], $fautifs,
            'Ces fichiers batissent leur adresse sans repli : ' . implode(', ', $fautifs));
    }
}
