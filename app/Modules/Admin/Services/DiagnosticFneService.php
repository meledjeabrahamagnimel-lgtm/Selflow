<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\FneRejet;
use App\Modules\Admin\Modeles\PortailFneFiche;
use App\Modules\Admin\Modeles\PortailFnePointFacturation;
use Illuminate\Database\Eloquent\Model;

/**
 * Rapproche une pièce refusée par la DGI du dernier relevé du portail.
 *
 * ## Ce qu'il rend
 *
 * Une phrase que l'utilisateur comprend, à la place d'un code. Au lieu de :
 *
 *     « Le nom du point de vente doit être déclaré à l'identique sur votre
 *       espace FNE. »
 *
 * il rend :
 *
 *     « Vous avez envoyé "FACTURATION SIEGE". Le portail, relevé le
 *       21/08/2026, déclare "FACTURATION SIÈGE" et "FACTURATION ANNEXE". »
 *
 * ## Ce qu'il ne fait pas
 *
 * **Il n'écrit rien et ne corrige rien.** Ni la pièce, ni l'entreprise, ni le
 * paramétrage. Il lit un rejet, lit un relevé, et rend la comparaison. Qui
 * décide de corriger, et quoi, reste un être humain — trois des champs du
 * portail (`timbre_quittance`, `bapa`, `sticker_solde_alerte`) commandent le
 * comportement fiscal, et une facture ne doit pas changer parce qu'un fichier
 * est arrivé dans un dossier.
 *
 * ## Ce qu'il ne peut pas dire
 *
 * Le portail ne porte pas tout. Un rejet sur `clientNcc` met en cause le NCC du
 * *client*, que le portail de l'entreprise n'affiche nulle part. Ces cas sont
 * rendus explicitement comme hors de portée, plutôt que passés sous silence :
 * un diagnostic muet se lit comme un diagnostic favorable.
 */
class DiagnosticFneService
{
    /**
     * Les champs du payload DGI que le portail sait éclairer.
     *
     * `pointOfSale` et `establishment` sont construits par
     * `FneService::normaliserFacture()` à partir du point de vente de la pièce
     * et du nom de l'entreprise (`FneService.php:169` et `:194`). On les
     * re-dérive ici plutôt que de les conserver au moment du rejet : la règle
     * d'or gèle `FneService`, donc ces deux expressions ne bougeront pas sous
     * nos pieds. Si un jour elles bougent, c'est que la règle a été levée, et
     * ce fichier fait partie de ce qu'il faudra relire.
     */
    private const CHAMPS_ECLAIRES = ['pointOfSale', 'establishment'];

    /**
     * Rapproche un rejet du dernier relevé disponible.
     *
     * @return array{
     *   rejet_id: int,
     *   releve: array{date: string|null, import_id: int}|null,
     *   champs: array<int, array<string, mixed>>,
     *   ecarts_fiche: array<string, array{portail: mixed, selflow: mixed}>,
     *   conclusion: string
     * }
     */
    public function diagnostiquer(FneRejet $rejet): array
    {
        $fiche  = $this->dernierReleve($rejet);
        $points = $fiche ? $this->pointsDuReleve($fiche) : [];

        $champs = [];

        foreach ($rejet->nomsDesChamps() as $champ) {
            $champs[] = $this->examiner($rejet, $champ, $fiche, $points);
        }

        // Les écarts de la fiche accompagnent le diagnostic sans en faire
        // partie : ils ne sont pas la cause du rejet, mais celui qui répare une
        // facture a intérêt à voir que le timbre de quittance diverge.
        $ecartsFiche = $fiche ? $fiche->ecartsAvecEntreprise() : [];

        return [
            'rejet_id'     => $rejet->id,
            'releve'       => $fiche ? [
                'date'      => $fiche->date_scraping?->format('d/m/Y'),
                'import_id' => $fiche->import_id,
            ] : null,
            'champs'       => $champs,
            'ecarts_fiche' => $ecartsFiche,
            'conclusion'   => $this->conclusion($fiche, $champs),
        ];
    }

    /**
     * Le relevé le plus récent pour l'entreprise du rejet.
     *
     * Par `login` autant que par `entreprise_id` : un relevé arrivé avant que
     * l'entreprise ne soit créée dans Selflow reste rattaché à son seul login,
     * et il vaut mieux un relevé orphelin que pas de relevé du tout.
     */
    private function dernierReleve(FneRejet $rejet): ?PortailFneFiche
    {
        $requete = PortailFneFiche::query()
            ->orderByDesc('date_scraping')
            ->orderByDesc('id');

        if ($rejet->entreprise_id) {
            $requete->where(function ($q) use ($rejet) {
                $q->where('entreprise_id', $rejet->entreprise_id);

                if ($rejet->login) {
                    $q->orWhere('login', $rejet->login);
                }
            });
        } elseif ($rejet->login) {
            $requete->where('login', $rejet->login);
        } else {
            return null;
        }

        return $requete->first();
    }

    /**
     * Les points de facturation du même relevé.
     *
     * Le tableur et le JSON arrivent en deux fichiers, donc deux imports : on
     * rapproche par login et date de relevé, pas par `import_id`.
     *
     * @return array<int, PortailFnePointFacturation>
     */
    private function pointsDuReleve(PortailFneFiche $fiche): array
    {
        return PortailFnePointFacturation::where('login', $fiche->login)
            ->where('date_scraping', $fiche->date_scraping)
            ->orderBy('nom')
            ->get()
            ->all();
    }

    /**
     * @param  array<int, PortailFnePointFacturation>  $points
     * @return array<string, mixed>
     */
    private function examiner(FneRejet $rejet, string $champ, ?PortailFneFiche $fiche, array $points): array
    {
        $raison = $rejet->champs[$champ] ?? null;
        $raison = is_array($raison) ? implode(', ', array_map('strval', $raison)) : (string) $raison;

        $base = ['champ' => $champ, 'raison_dgi' => $raison];

        if (!in_array($champ, self::CHAMPS_ECLAIRES, true)) {
            return $base + [
                'verdict'     => 'hors_portee',
                'explication' => "Le portail FNE n'affiche pas ce champ : la comparaison "
                    . 'ne peut pas être faite ici.',
            ];
        }

        if ($fiche === null) {
            return $base + [
                'verdict'     => 'sans_releve',
                'explication' => "Aucun relevé du portail pour cette entreprise. "
                    . 'Une demande de relevé a été déposée.',
            ];
        }

        $envoye = $this->valeurEnvoyee($rejet, $champ);

        if ($champ === 'pointOfSale') {
            $declares = array_values(array_filter(array_map(
                fn (PortailFnePointFacturation $p) => $p->nom,
                array_filter($points, fn (PortailFnePointFacturation $p) => $p->estActif())
            )));

            return $base + [
                'envoye'      => $envoye,
                'portail'     => $declares,
                'verdict'     => $this->verdict($envoye, $declares),
                'explication' => $this->expliquerPointDeVente($envoye, $declares, $fiche),
            ];
        }

        // `establishment` : le portail ne rend pas le nom de l'établissement,
        // seulement son identifiant DGI. On dit ce qu'on a, sans le maquiller
        // en réponse.
        $identifiants = array_values(array_unique(array_filter(array_map(
            fn (PortailFnePointFacturation $p) => $p->etablissement_id,
            $points
        ))));

        return $base + [
            'envoye'      => $envoye,
            'portail'     => $identifiants,
            'verdict'     => 'a_verifier',
            'explication' => sprintf(
                'Vous avez envoyé « %s ». Le portail ne publie pas le nom de '
                . "l'établissement, seulement son identifiant (%s) : le nom est à "
                . "relever à l'écran, sur la fiche de l'entreprise.",
                $envoye ?? '(vide)',
                $identifiants ? implode(', ', $identifiants) : 'aucun'
            ),
        ];
    }

    /**
     * La valeur que la pièce a fait partir, re-dérivée depuis la pièce.
     */
    private function valeurEnvoyee(FneRejet $rejet, string $champ): ?string
    {
        $piece = $rejet->piece();

        if (!$piece instanceof Model) {
            return null;
        }

        return match ($champ) {
            'pointOfSale'   => trim($piece->pointDeVente?->nom ?: 'Siège'),
            'establishment' => $piece->pointDeVente?->entreprise?->nom,
            default         => null,
        };
    }

    /**
     * @param  array<int, string>  $declares
     */
    private function verdict(?string $envoye, array $declares): string
    {
        if ($declares === []) {
            return 'sans_releve';
        }

        if ($envoye !== null && in_array($envoye, $declares, true)) {
            return 'concordant';
        }

        return 'ecart';
    }

    /**
     * @param  array<int, string>  $declares
     */
    private function expliquerPointDeVente(?string $envoye, array $declares, PortailFneFiche $fiche): string
    {
        $date = $fiche->date_scraping?->format('d/m/Y') ?? 'date inconnue';

        if ($declares === []) {
            return "Le relevé du {$date} ne déclare aucun point de facturation actif.";
        }

        $liste = '« ' . implode(' », « ', $declares) . ' »';

        if ($envoye !== null && in_array($envoye, $declares, true)) {
            return sprintf(
                'Le point de vente « %s » est bien déclaré au portail (relevé du %s). '
                . "Le refus vient d'ailleurs.",
                $envoye,
                $date
            );
        }

        $proche = $this->plusProche($envoye, $declares);

        return sprintf(
            'Vous avez envoyé « %s ». Le portail, relevé le %s, déclare %s.%s',
            $envoye ?? '(vide)',
            $date,
            $liste,
            $proche ? " Le plus proche est « {$proche} »." : ''
        );
    }

    /**
     * Le nom déclaré le plus proche de ce qui a été envoyé.
     *
     * Les écarts observés sont des accents et des espaces — « FACTURATION
     * SIEGE » contre « FACTURATION SIÈGE ». Une distance de Levenshtein
     * désigne le bon candidat là où une comparaison stricte ne dit que « non ».
     *
     * @param  array<int, string>  $declares
     */
    private function plusProche(?string $envoye, array $declares): ?string
    {
        if ($envoye === null || $envoye === '' || $declares === []) {
            return null;
        }

        $meilleur = null;
        $distance = PHP_INT_MAX;

        foreach ($declares as $candidat) {
            // `levenshtein` travaille en octets : deux chaînes UTF-8 très
            // proches à l'oeil le sont un peu moins pour lui, un accent pesant
            // deux octets. Suffisant pour désigner un candidat, jamais utilisé
            // pour décider seul.
            $d = levenshtein(mb_strtoupper($envoye), mb_strtoupper($candidat));

            if ($d < $distance) {
                $distance = $d;
                $meilleur = $candidat;
            }
        }

        // Au-delà de la moitié de la longueur, ce n'est plus une faute de
        // frappe mais un autre point de vente : le proposer induirait en erreur.
        return $distance <= max(3, (int) floor(mb_strlen($envoye) / 2)) ? $meilleur : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $champs
     */
    private function conclusion(?PortailFneFiche $fiche, array $champs): string
    {
        if ($fiche === null) {
            return "Aucun relevé du portail n'est disponible pour cette entreprise : "
                . 'le diagnostic se limite au message de la plateforme.';
        }

        $ecarts = array_filter($champs, fn (array $c) => ($c['verdict'] ?? null) === 'ecart');

        if ($ecarts !== []) {
            return 'Le portail et la pièce ne disent pas la même chose. La correction '
                . 'est à appliquer manuellement, après vérification.';
        }

        return 'Le portail confirme les valeurs envoyées : la cause du refus est ailleurs. '
            . 'Le message de la plateforme fait foi.';
    }
}
