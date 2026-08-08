<?php

namespace App\Modules\Admin\Controleurs;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * La visite guidée de première utilisation.
 *
 * Elle se retient en base plutôt que dans le navigateur : changer de poste ne
 * doit pas la faire recommencer, ni la faire disparaître pour quelqu'un qui ne
 * l'a jamais vue.
 */
class VisiteGuideeControleur
{
    /**
     * L'utilisateur a terminé la visite, ou l'a passée.
     */
    public function terminer(): JsonResponse
    {
        $utilisateur = Auth::user();

        abort_unless($utilisateur, 403);

        $utilisateur->forceFill(['visite_guidee_terminee_le' => now()])->save();

        return response()->json(['statut' => 'ok']);
    }

    /**
     * La revoir : depuis le menu, à tout moment.
     */
    public function rejouer(): JsonResponse
    {
        $utilisateur = Auth::user();

        abort_unless($utilisateur, 403);

        $utilisateur->forceFill(['visite_guidee_terminee_le' => null])->save();

        return response()->json(['statut' => 'ok']);
    }
}
