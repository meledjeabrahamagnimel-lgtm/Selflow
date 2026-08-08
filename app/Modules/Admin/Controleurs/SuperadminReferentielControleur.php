<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Referentiel\Article;
use App\Modules\Admin\Modeles\Referentiel\Categorie;
use App\Modules\Admin\Modeles\Referentiel\Compte;
use App\Modules\Admin\Modeles\Referentiel\Famille;
use App\Modules\Admin\Modeles\Referentiel\Profil;
use App\Modules\Admin\Modeles\Referentiel\TypeArticle;
use App\Modules\Admin\Services\TrousseauEntrepriseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultation du référentiel de préparamétrage.
 *
 * Le référentiel est chargé depuis un classeur converti en JSON, et rien dans
 * l'application ne permettait de vérifier ce qui avait été chargé : il fallait
 * ouvrir la base. Cet écran le montre — profils, familles, articles, comptes —
 * et sert à contrôler une nouvelle version du classeur avant de la déployer.
 *
 * Consultation seule. Le référentiel se modifie dans le classeur, puis se
 * recharge par le seeder : une correction faite ici serait perdue au prochain
 * chargement, et personne ne saurait pourquoi.
 */
class SuperadminReferentielControleur
{
    public function index(Request $request): View
    {
        $categories = Categorie::withCount('profils')->orderBy('ordre')->get();

        $profils = Profil::with('categorie')
            ->withCount(['familles', 'articles'])
            ->when($request->filled('categorie'), fn ($q) => $q->where('categorie_id', $request->query('categorie')))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = trim($request->query('recherche'));
                $q->where(fn ($sq) => $sq->where('nom', 'like', "%{$terme}%")
                    ->orWhere('code', 'like', "%{$terme}%"));
            })
            ->orderBy('nom')
            ->get();

        return view('admin::superadmin.referentiel.index', [
            'categories'     => $categories,
            'profils'        => $profils,
            'typesArticles'  => TypeArticle::orderBy('code')->get(),
            'journaux'       => TrousseauEntrepriseService::journauxParDefaut(),
            'compteurs'      => [
                'profils'   => Profil::count(),
                'familles'  => Famille::count(),
                'articles'  => Article::count(),
                'comptes'   => Compte::count(),
                'communs'   => Compte::where('commun', true)->count(),
            ],
        ]);
    }

    /**
     * Le détail d'un profil : ce qu'une entreprise recevra en le choisissant.
     */
    public function profil(string $code): View
    {
        $profil = Profil::with('categorie')->where('code', $code)->firstOrFail();

        return view('admin::superadmin.referentiel.profil', [
            'profil'   => $profil,
            'familles' => Famille::with('typeArticle')
                ->where('profil_id', $profil->id)
                ->orderBy('code')
                ->get(),
            'articles' => Article::with('famille')
                ->where('profil_id', $profil->id)
                ->orderBy('code')
                ->get(),
        ]);
    }
}
