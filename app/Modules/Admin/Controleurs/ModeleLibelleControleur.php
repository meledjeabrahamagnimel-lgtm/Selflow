<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\ModeleLibelle;
use App\Modules\Admin\Services\LibelleEcritureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Le paramétrage des libellés d'écriture.
 *
 * Jusqu'ici, le libellé d'une opération de vente valait l'intitulé SYSCOHADA
 * du compte mouvementé — « Vente de marchandises ». C'est ce que le compte
 * **est**, pas ce que l'opération **a été** : un grand livre du 701 dont
 * chaque ligne répète l'en-tête de la page n'apprend rien.
 *
 * L'écran laisse chaque entreprise décider ce qu'elle veut y lire. Ce qu'elle
 * ne touche pas garde le comportement d'avant, au caractère près.
 */
class ModeleLibelleControleur
{
    public function index(): View
    {
        $entreprise = Auth::user()->entreprise;

        $modeles = ModeleLibelle::where('entreprise_id', $entreprise->id)
            ->get()
            ->keyBy('type_operation');

        return view('admin::comptabilite.libelles', [
            'types'    => ModeleLibelle::TYPES,
            'defauts'  => ModeleLibelle::DEFAUTS,
            'jetons'   => ModeleLibelle::JETONS,
            'modeles'  => $modeles,
            'exemple'  => self::EXEMPLE,
        ]);
    }

    /**
     * Un jeu de valeurs plausible, qui sert d'aperçu. Il ne vient pas de la
     * base : montrer une vraie facture ici obligerait à en choisir une, et
     * l'aperçu changerait d'un jour à l'autre sans que le gabarit ait bougé.
     */
    private const EXEMPLE = [
        'piece'          => 'FV-240826-014',
        'tiers'          => 'SOCIÉTÉ IVOIRIENNE DE NÉGOCE',
        'produits'       => 'Ciment 50 kg, Fer à béton 8',
        'point_de_vente' => 'Dépôt de Yopougon',
        'date'           => '26/08/2026',
        'nature'         => 'Vente de marchandises',
        'journal'        => 'VTE',
        'reference'      => 'CHQ-4471',
        'role'           => 'Facturation Vente',
    ];

    public function enregistrer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $types = array_keys(ModeleLibelle::TYPES);

        $donnees = $request->validate([
            'gabarits'                      => ['required', 'array'],
            'gabarits.*'                    => ['array'],
            // Un type inconnu serait enregistré et jamais relu : il vaut mieux
            // le refuser que le laisser dormir dans la table.
            'gabarits.*.operation'          => ['nullable', 'string', 'max:255'],
            'gabarits.*.ligne'              => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($donnees, $entreprise, $types) {
            foreach ($donnees['gabarits'] as $type => $valeurs) {
                if (!in_array($type, $types, true)) {
                    continue;
                }

                $operation = trim((string) ($valeurs['operation'] ?? ''));
                $ligne     = trim((string) ($valeurs['ligne'] ?? ''));

                // Deux champs vides veulent dire « reviens au défaut ». On
                // supprime la ligne plutôt que d'enregistrer deux chaînes
                // vides : sans cela, un défaut qui évoluerait un jour ne
                // rattraperait pas les entreprises restées au vide.
                if ($operation === '' && $ligne === '') {
                    ModeleLibelle::where('entreprise_id', $entreprise->id)
                        ->where('type_operation', $type)
                        ->delete();
                    continue;
                }

                ModeleLibelle::updateOrCreate(
                    ['entreprise_id' => $entreprise->id, 'type_operation' => $type],
                    ['gabarit_operation' => $operation ?: null, 'gabarit_ligne' => $ligne ?: null],
                );
            }
        });

        // Le service garde les gabarits en mémoire le temps d'une requête :
        // sans cet oubli, une vente enregistrée juste après continuerait
        // d'écrire avec l'ancien.
        LibelleEcritureService::oublier($entreprise->id);

        return back()->with('succes', 'Les libellés d\'écriture sont enregistrés. Ils valent pour les écritures à venir ; celles déjà passées gardent le leur.');
    }

    /**
     * L'aperçu en direct, appelé par l'écran à chaque frappe.
     *
     * Il ne lit ni n'écrit la base : il applique le gabarit reçu au jeu
     * d'exemple. Un gabarit qu'on n'a pas encore enregistré doit pouvoir être
     * jugé avant de l'être.
     */
    public function apercu(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'gabarit' => ['required', 'string', 'max:255'],
            'type'    => ['required', 'string', Rule::in(array_keys(ModeleLibelle::TYPES))],
            'cible'   => ['required', 'string', Rule::in(['operation', 'ligne'])],
        ]);

        $jetons = self::EXEMPLE;

        // Sur l'opération, `{role}` n'a pas de sens : la ligne seule porte un
        // rôle. Le laisser rendre « Facturation Vente » ferait croire à un
        // aperçu que l'écriture ne produira jamais.
        if ($donnees['cible'] === 'operation') {
            $jetons['role'] = '';
        }

        return response()->json([
            'apercu' => LibelleEcritureService::appliquer($donnees['gabarit'], $jetons),
        ]);
    }
}
