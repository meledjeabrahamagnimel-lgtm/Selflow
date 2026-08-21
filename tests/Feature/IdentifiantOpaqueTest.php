<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * L'identifiant que portent les adresses.
 *
 * `/admin/ventes/facture/4213` porte une information que personne n'a voulu
 * publier : **le nombre de factures de la plateforme**. Le lot 8.3 avait fermé
 * l'oracle en répondant 404 (page introuvable) sur la pièce d'autrui comme sur
 * une pièce inexistante — mais l'adresse elle-même parlait encore, et elle sort
 * de l'application : courriel transféré, capture d'écran, billet d'assistance,
 * historique d'un navigateur partagé.
 *
 * ## Une identité, une seule
 *
 * `getRouteKeyName()` vaut pour **toutes** les routes d'un modèle, web et API
 * confondues : le mobile emploie le même identifiant que le navigateur. Deux
 * identités — le numéro d'un côté, l'`uuid` de l'autre — auraient rendu
 * impossible de rapprocher un journal du serveur, un billet d'assistance et une
 * capture d'écran ; et la moitié du problème serait restée entière pour
 * toujours.
 *
 * La clé primaire ne change pas : l'entier reste ce qui porte les jointures,
 * les index et les clés étrangères.
 */
class IdentifiantOpaqueTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private PointDeVente $magasin;
    private Utilisateur $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create([
            'nom' => 'Quincaillerie du Bandama', 'regime_imposition' => 'RNI',
            'adresse' => 'Cocody, Abidjan', 'rccm' => 'CI-ABJ-2026-B-03333',
            'ncc' => '2601234A', 'gerant_fonction' => 'Gérant',
            'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $this->magasin = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Yao', 'prenom' => 'Adjoua', 'email' => 'adjoua@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->magasin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['point_de_vente_actif_id' => $this->magasin->id]);
    }

    private function uneVente(array $champs = []): Vente
    {
        return Vente::create(array_merge([
            'point_de_vente_id' => $this->magasin->id,
            'numero_facture'    => 'VTE-' . uniqid(),
            'date_vente'        => now()->toDateString(),
            'date_validite'     => now()->addDays(30)->toDateString(),
            'mode_paiement'     => 'Espèces',
            'montant_ht'        => 10000, 'montant_tva' => 1800, 'montant_ttc' => 11800,
            'statut'            => 'Payé', 'etape' => 'Facture',
        ], $champs));
    }

    // ══════════════ Ce que les écrans renvoient ══════════════

    /**
     * Le chemin complet du bouton « créer un avoir », de bout en bout.
     *
     * La recherche rendait l'identifiant **numérique**, l'écran le remettait
     * dans une adresse qui attend un `uuid`, et la requête tombait sur un
     * 404 (Not Found — introuvable). Le script lisant la réponse en JSON
     * recevait alors la page d'erreur en HTML :
     *
     *     GET /admin/ventes/facture-details/169 → 404
     *     SyntaxError: Unexpected token '<', "<!DOCTYPE "... is not valid JSON
     *
     * Le bouton ne fonctionnait donc pas du tout — ni pour les ventes, ni pour
     * les achats, où le même code vivait.
     */
    public function test_la_recherche_de_factures_rend_l_identifiant_public(): void
    {
        $vente = $this->uneVente(['numero_facture' => 'VTE-2026-0042']);

        $reponse = $this->getJson(route('admin.ventes.factures.rechercher', ['q' => 'VTE-2026-0042']))
            ->assertOk();

        $trouvee = collect($reponse->json())->firstWhere('id', $vente->uuid);

        $this->assertNotNull($trouvee, "La recherche doit rendre l'identifiant public de la pièce.");
        $this->assertNotContains($vente->id, collect($reponse->json())->pluck('id')->all(),
            "L'identifiant de base ne se publie pas : c'est lui qui disait le volume.");
    }

    public function test_l_identifiant_rendu_par_la_recherche_ouvre_bien_le_detail(): void
    {
        // Le test qui aurait attrapé la panne : ce que la recherche donne doit
        // être ce que l'adresse suivante accepte.
        $vente = $this->uneVente(['numero_facture' => 'VTE-2026-0043']);

        $resultats = $this->getJson(route('admin.ventes.factures.rechercher', ['q' => 'VTE-2026-0043']))
            ->assertOk()->json();

        $this->assertNotEmpty($resultats);

        $this->getJson(route('admin.ventes.factures.details', $resultats[0]['id']))
            ->assertOk()
            ->assertJsonPath('id', $vente->uuid);
    }

    public function test_le_detail_d_une_facture_ne_publie_pas_l_identifiant_de_base(): void
    {
        $vente = $this->uneVente();

        $this->getJson(route('admin.ventes.factures.details', $vente))
            ->assertOk()
            ->assertJsonPath('id', $vente->uuid);
    }

    public function test_l_identifiant_numerique_ne_designe_plus_rien(): void
    {
        // L'adresse exacte du signalement.
        $vente = $this->uneVente();

        $this->get('/admin/ventes/facture-details/' . $vente->id)
            ->assertNotFound();
    }

    // ══════════════ L'identifiant existe et ne se devine pas ══════════════

    public function test_toute_piece_recoit_un_identifiant_a_sa_creation(): void
    {
        $vente = $this->uneVente();

        $this->assertNotNull($vente->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $vente->uuid
        );
    }

    public function test_deux_pieces_voisines_portent_des_identifiants_sans_rapport(): void
    {
        // C'est tout l'objet : de l'un on ne déduit pas l'autre, alors que de
        // `4213` on déduisait `4214`.
        $premiere = $this->uneVente();
        $seconde  = $this->uneVente();

        $this->assertSame(1, $seconde->id - $premiere->id, 'Les clés primaires restent séquentielles.');
        $this->assertNotSame($premiere->uuid, $seconde->uuid);

        // Aucune parenté visible : les deux identifiants ne partagent pas même
        // leur début.
        $this->assertNotSame(substr($premiere->uuid, 0, 8), substr($seconde->uuid, 0, 8));
    }

    public function test_l_identifiant_ne_vient_jamais_d_une_requete(): void
    {
        // S'il était `fillable`, une requête pourrait choisir l'identifiant
        // d'une ressource — et donc le connaître avant de la créer.
        $vente = $this->uneVente(['uuid' => '00000000-0000-0000-0000-000000000000']);

        $this->assertNotSame('00000000-0000-0000-0000-000000000000', $vente->uuid);
    }

    // ══════════════ Les adresses ══════════════

    public function test_l_adresse_d_une_piece_ne_porte_plus_son_numero_de_ligne(): void
    {
        $vente = $this->uneVente();

        $adresse = route('admin.ventes.imprimer', $vente);

        // C'est le dernier segment qui désigne la pièce : le comparer entier
        // plutôt que chercher « /1 » quelque part dans l'adresse, qu'un
        // identifiant commençant par le même chiffre ferait échouer une fois
        // sur seize.
        $segment = basename(parse_url($adresse, PHP_URL_PATH));

        $this->assertSame($vente->uuid, $segment);
        $this->assertNotSame((string) $vente->id, $segment);
    }

    public function test_l_ancienne_adresse_numerique_ne_resout_plus_rien(): void
    {
        // Le comptage des identifiants ne dit plus rien, puisqu'il n'y a plus
        // d'identifiant à compter.
        $vente = $this->uneVente();

        $this->get('/admin/ventes/facture/' . $vente->id)->assertNotFound();
        $this->get(route('admin.ventes.imprimer', $vente))->assertOk();
    }

    public function test_l_adresse_resout_la_bonne_piece(): void
    {
        $premiere = $this->uneVente(['numero_facture' => 'VTE-PREMIERE']);
        $seconde  = $this->uneVente(['numero_facture' => 'VTE-SECONDE']);

        $this->get(route('admin.ventes.imprimer', $seconde))
            ->assertOk()
            ->assertSee('VTE-SECONDE')
            ->assertDontSee('VTE-PREMIERE');
    }

    // ══════════════ Une identité, pas deux ══════════════

    public function test_le_web_et_l_api_emploient_le_meme_identifiant(): void
    {
        // **Le point qui décide de la valeur du lot.** Deux identités auraient
        // rendu impossible de rapprocher un journal du serveur, un billet
        // d'assistance et une capture d'écran.
        $vente = $this->uneVente();

        $this->assertStringContainsString($vente->uuid, route('admin.ventes.imprimer', $vente));
        $this->assertStringContainsString($vente->uuid, url('/api/admin/ventes/facture/' . $vente->getRouteKey()));
        $this->assertSame('uuid', $vente->getRouteKeyName());
    }

    /**
     * Les modèles que les adresses désignent.
     *
     * @return array<int, array<int, string>>
     */
    public static function lesModelesDesAdresses(): array
    {
        return [
            [\App\Modules\Admin\Modeles\Vente::class],
            [\App\Modules\Admin\Modeles\Achat::class],
            [\App\Modules\Admin\Modeles\Produit::class],
            [\App\Modules\Admin\Modeles\Client::class],
            [\App\Modules\Admin\Modeles\Fournisseur::class],
            [\App\Modules\Admin\Modeles\PointDeVente::class],
            [\App\Modules\Admin\Modeles\BonLivraison::class],
            [\App\Modules\Admin\Modeles\Immobilisation::class],
            [\App\Modules\Admin\Modeles\Consignation::class],
            [\App\Modules\Admin\Modeles\Entreprise::class],
            [\App\Modules\Admin\Modeles\CodeJournal::class],
            [\App\Modules\Admin\Modeles\Lettrage::class],
            [\App\Modules\Admin\Modeles\TransfertStock::class],
            [\App\Modules\Admin\Modeles\FicheTechnique::class],
            [\App\Modules\Admin\Modeles\OrdreProduction::class],
            [\App\Modules\Admin\Modeles\DotationAmortissement::class],
            [\App\Modules\Admin\Modeles\B2bNegotiation::class],
            [\App\Modules\Admin\Modeles\Periode::class],
            [\App\Modules\Authentification\Modeles\Utilisateur::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lesModelesDesAdresses')]
    public function test_chaque_modele_designe_dans_une_adresse_porte_un_identifiant_opaque(string $classe): void
    {
        $modele = new $classe();

        $this->assertSame('uuid', $modele->getRouteKeyName(),
            "{$classe} est désigné dans une adresse : il doit porter un identifiant opaque.");

        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn($modele->getTable(), 'uuid'),
            "La table {$modele->getTable()} n'a pas de colonne `uuid`."
        );
    }

    public function test_l_api_publie_l_identifiant_a_cote_du_numero(): void
    {
        // L'application mobile construit ses adresses depuis ce que l'API rend :
        // sans l'identifiant dans la charge utile, elle n'a rien pour désigner
        // une ressource.
        $this->uneVente();

        $reponse = $this->actingAs($this->admin)->getJson('/api/admin/ventes/factures');

        if ($reponse->status() === 200) {
            $corps = $reponse->json();
            $premiere = data_get($corps, 'donnees.0') ?? data_get($corps, 'data.0') ?? data_get($corps, '0');

            if (is_array($premiere)) {
                $this->assertArrayHasKey('uuid', $premiere);
            }
        }

        // Quelle que soit la forme de la réponse, la source doit publier la
        // colonne : c'est elle qui rend l'adresse constructible.
        $this->assertStringContainsString(
            "'uuid' =>",
            file_get_contents(app_path('Modules/Admin/Controleurs/Api/VenteApiControleur.php'))
        );
    }

    // ══════════════ La copie ══════════════

    public function test_une_copie_ne_porte_pas_l_identifiant_de_l_original(): void
    {
        // `replicate()` recopiait l'`uuid` : la contrainte d'unicité refusait
        // l'enregistrement, et si elle ne l'avait pas fait, **l'adresse du
        // devis aurait désigné sa commande** — exactement ce que
        // l'identifiant sert à empêcher.
        $devis = $this->uneVente(['etape' => 'Devis']);

        $copie = $devis->replicate();
        $copie->numero_facture = 'VTE-COPIE';
        $copie->save();

        $this->assertNotNull($copie->uuid);
        $this->assertNotSame($devis->uuid, $copie->uuid);
    }

    public function test_la_conversion_d_un_devis_donne_deux_adresses_distinctes(): void
    {
        $client = Client::create(['entreprise_id' => $this->entreprise->id, 'nom' => 'Konan BTP']);
        $produit = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'CIM-001',
            'nom' => 'Ciment', 'type' => 'marchandise', 'prix_achat' => 5000, 'prix_vente' => 6500,
        ]);

        $devis = $this->uneVente(['etape' => 'Devis', 'client_id' => $client->id]);

        \App\Modules\Admin\Modeles\VenteDetail::create([
            'vente_id' => $devis->id, 'produit_id' => $produit->id,
            'quantite' => 2, 'prix_unitaire' => 6500, 'montant_tva' => 2340, 'montant_ttc' => 15340,
        ]);

        $this->post(route('admin.ventes.convertir.commande', $devis));

        $commande = Vente::where('etape', 'Bon de commande')->firstOrFail();

        $this->assertNotSame($devis->fresh()->uuid, $commande->uuid);
    }

    // ══════════════ Le cloisonnement tient toujours ══════════════

    public function test_la_piece_d_un_concurrent_reste_introuvable(): void
    {
        // L'identifiant opaque s'ajoute au 404 (page introuvable), il ne le
        // remplace pas : deviner un identifiant reste possible en théorie, et
        // l'appartenance doit continuer d'être vérifiée.
        $autre = Entreprise::create(['nom' => 'Quincaillerie rivale']);
        $sonMagasin = PointDeVente::create([
            'entreprise_id' => $autre->id,
            'nom' => 'Magasin rival', 'ville' => 'Abidjan', 'commune' => 'Yopougon',
        ]);

        $sienne = Vente::create([
            'point_de_vente_id' => $sonMagasin->id,
            'numero_facture'    => 'VTE-RIVALE',
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Espèces',
            'montant_ht'        => 100000, 'montant_tva' => 18000, 'montant_ttc' => 118000,
            'statut'            => 'Payé', 'etape' => 'Facture',
        ]);

        $this->get(route('admin.ventes.imprimer', $sienne))->assertNotFound();
    }

    // ══════════════ Ce qui empêche la dérive ══════════════

    public function test_aucune_vue_ne_construit_d_adresse_avec_un_numero_de_ligne(): void
    {
        // `route('admin.ventes.imprimer', $vente->id)` génère l'entier là où la
        // route attend l'identifiant : l'adresse ne résout plus rien, et le
        // défaut ne se voit qu'au clic.
        $fautives = [];
        $vues = [];
        $pile = [app_path('Modules')];

        while ($pile) {
            foreach (scandir($dossier = array_pop($pile)) ?: [] as $entree) {
                if ($entree === '.' || $entree === '..') {
                    continue;
                }

                $chemin = $dossier . DIRECTORY_SEPARATOR . $entree;

                if (is_dir($chemin)) {
                    $pile[] = $chemin;
                } elseif (str_ends_with($chemin, '.blade.php')) {
                    $vues[] = $chemin;
                }
            }
        }

        foreach ($vues as $chemin) {
            if (preg_match("/route\(\s*'(?:admin|caissier|superadmin)\.[a-z_.0-9]+'\s*,\s*\\\$[a-zA-Z_][a-zA-Z_0-9]*->id\s*\)/",
                    file_get_contents($chemin))) {
                $fautives[] = basename($chemin);
            }
        }

        $this->assertSame([], array_values(array_unique($fautives)),
            'Ces vues construisent une adresse avec un numéro de ligne : ' . implode(', ', $fautives));
    }

    /**
     * Une insertion brute ne passe pas par le modèle.
     *
     * Les semeurs écrivent en SQL direct pour aller vite — et le crochet
     * `creating` qui pose l'identifiant ne s'exécute alors pas. La pièce naît
     * sans adresse publique, MySQL l'accepte (une colonne unique tolère autant
     * de `NULL` qu'on veut), et rien ne se voit **jusqu'à ce qu'un écran
     * demande cette adresse** : le tableau de bord répondait 500 (*Internal
     * Server Error* — erreur interne du serveur) sur la première vente semée.
     *
     * Le test lit la source : toute insertion brute dans une table à
     * identifiant opaque doit poser l'`uuid` elle-même.
     */
    public function test_aucun_peuplement_n_insere_sans_identifiant_opaque(): void
    {
        $tables = [
            'ventes', 'achats', 'entreprises', 'produits', 'bons_livraison',
            'points_de_vente', 'b2b_negotiations', 'immobilisations',
            'fiches_techniques', 'codes_journaux', 'clients', 'consignations',
            'fournisseurs', 'transferts_stock', 'lettrages', 'periodes',
            'dotations_amortissement', 'ordres_production', 'utilisateurs',
        ];

        $sources = array_merge(
            glob(database_path('seeders/*.php')) ?: [],
            glob(app_path('Console/Commands/*.php')) ?: []
        );

        $fautives = [];

        foreach ($sources as $chemin) {
            $src = file_get_contents($chemin);

            foreach ($tables as $table) {
                // Chaque site d'insertion est examiné à part : le tableau passé
                // à `insert()` est construit plus haut, dans une variable, et
                // c'est **ce tableau-là** qui doit porter l'identifiant. Chercher
                // « uuid » n'importe où dans le fichier laisserait passer un site
                // fautif dès qu'un autre, correct, est présent.
                $motif = "/DB::table\(\s*'{$table}'\s*\)->insert(?:GetId)?\s*\(\s*(\\\$[a-zA-Z_][a-zA-Z_0-9]*)\s*\)/";

                if (!preg_match_all($motif, $src, $trouves, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($trouves[1] as [$variable, $ouInsere]) {
                    $nom = preg_quote(substr($variable, 1), '/');

                    // La construction du tableau, cherchée **en remontant depuis
                    // l'insertion** : le même nom de variable sert dans plusieurs
                    // méthodes du même fichier, et partir du début en attraperait
                    // une autre. C'est la dernière affectation avant l'insertion
                    // qui décrit ce qui part en base.
                    $avant = substr($src, 0, $ouInsere);

                    if (!preg_match_all('/\$' . $nom . '(?:\[\])?\s*=\s*\[(.*?)\n\s*\];/s',
                            $avant, $blocs, PREG_SET_ORDER)) {
                        $fautives[] = basename($chemin) . ' → ' . $table . ' (tableau ' . $variable . ' introuvable)';
                        continue;
                    }

                    $dernier = end($blocs);

                    if (!str_contains($dernier[1], "'uuid'")) {
                        $fautives[] = basename($chemin) . ' → ' . $table;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($fautives)),
            'Ces peuplements insèrent en SQL brut sans poser l\'identifiant opaque, '
            . 'ce qui produit des pièces sans adresse publique : ' . implode(', ', array_unique($fautives)));
    }
}
