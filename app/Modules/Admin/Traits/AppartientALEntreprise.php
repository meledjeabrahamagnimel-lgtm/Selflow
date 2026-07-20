<?php

namespace App\Modules\Admin\Traits;

use Illuminate\Http\Response;

/**
 * Trait AppartientALEntreprise
 *
 * Vérifie qu'une ressource Eloquent appartient bien à l'entreprise
 * de l'utilisateur connecté. Protège contre les attaques IDOR
 * (Insecure Direct Object Reference) où un utilisateur modifierait
 * l'ID dans l'URL pour accéder aux données d'une autre entreprise.
 *
 * Usage dans un contrôleur :
 *   use AppartientALEntreprise;
 *
 *   $vente = Vente::findOrFail($id);
 *   $this->verifierAppartenance($vente, Auth::user()->entreprise);
 */
trait AppartientALEntreprise
{
    /**
     * Vérifie que la ressource appartient à l'entreprise.
     * Avorte avec une 403 si ce n'est pas le cas.
     *
     * @param \Illuminate\Database\Eloquent\Model $ressource Ressource à contrôler
     * @param \App\Modules\Admin\Modeles\Entreprise $entreprise L'entreprise de l'utilisateur connecté
     * @param string $colonneEntreprise Nom de la colonne FK entreprise (défaut: 'entreprise_id')
     */
    protected function verifierAppartenance(
        \Illuminate\Database\Eloquent\Model $ressource,
        \App\Modules\Admin\Modeles\Entreprise $entreprise,
        string $colonneEntreprise = 'entreprise_id'
    ): void {
        if ($ressource->{$colonneEntreprise} !== $entreprise->id) {
            abort(403, 'Accès interdit : cette ressource n\'appartient pas à votre entreprise.');
        }
    }

    /**
     * Recherche une ressource et vérifie immédiatement son appartenance.
     * Raccourci pratique : findOrFail + verifierAppartenance en une seule ligne.
     *
     * @param string $modele Classe Eloquent complète (ex: Vente::class)
     * @param int $id ID de la ressource
     * @param \App\Modules\Admin\Modeles\Entreprise $entreprise
     * @param string $colonneEntreprise
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function trouverEtVerifier(
        string $modele,
        int $id,
        \App\Modules\Admin\Modeles\Entreprise $entreprise,
        string $colonneEntreprise = 'entreprise_id'
    ): \Illuminate\Database\Eloquent\Model {
        $ressource = $modele::findOrFail($id);
        $this->verifierAppartenance($ressource, $entreprise, $colonneEntreprise);
        return $ressource;
    }
}
