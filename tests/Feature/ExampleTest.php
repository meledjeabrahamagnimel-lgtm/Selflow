<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La porte d'entrée répond.
 *
 * C'était l'épreuve d'exemple livrée avec Laravel, et elle affirmait encore
 * que la racine redirige vers `/connexion`. Ce n'est plus vrai : elle sert la
 * vitrine. Elle n'avait pas non plus `RefreshDatabase`, si bien que le jour où
 * la racine a eu besoin d'une table, elle est tombée en 500 (Internal Server
 * Error — erreur interne du serveur) au lieu de dire ce qui manquait.
 *
 * L'aiguillage complet est éprouvé dans `AiguillageRacineTest` ; celle-ci ne
 * garde que le contrôle le plus grossier — la page s'affiche.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_racine_repond_a_un_visiteur(): void
    {
        $this->get('/')->assertOk();
    }
}
