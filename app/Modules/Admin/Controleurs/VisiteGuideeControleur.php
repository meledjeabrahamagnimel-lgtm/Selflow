<?php

namespace App\Modules\Admin\Controleurs;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     *
     * L'entrée de menu est un formulaire ordinaire — pas d'appel JavaScript :
     * la visite doit pouvoir se relancer même si un script a échoué, ce qui est
     * précisément le moment où l'utilisateur en a besoin. La réponse suit donc
     * ce que le client demande : du JSON pour un appel de script, et sinon un
     * retour au tableau de bord, où la visite reprend au premier écran.
     */
    public function rejouer(Request $request): JsonResponse|RedirectResponse
    {
        $utilisateur = Auth::user();

        abort_unless($utilisateur, 403);

        $utilisateur->forceFill(['visite_guidee_terminee_le' => null])->save();

        if ($request->expectsJson()) {
            return response()->json(['statut' => 'ok']);
        }

        return redirect()->route('admin.tableau_de_bord');
    }
}
