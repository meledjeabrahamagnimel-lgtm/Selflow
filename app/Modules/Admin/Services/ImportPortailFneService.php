<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PortailFneDemande;
use App\Modules\Admin\Modeles\PortailFneFiche;
use App\Modules\Admin\Modeles\PortailFneImport;
use App\Modules\Admin\Modeles\PortailFnePointFacturation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Recueille les relevés du portail FNE déposés dans un dossier, et les range
 * en base.
 *
 * ## Ce qu'il lit
 *
 * Deux fichiers par entreprise, nommés `<login>_<date>.json` et
 * `<login>_<date>.xlsx` :
 *
 * - le **JSON** porte la fiche de l'entreprise — adresse, commune, quartier,
 *   solde d'alerte des stickers, timbre de quittance, bordereau d'achat ;
 * - le **tableur** porte les points de facturation, une ligne par point.
 *
 * Le `login` est celui du portail, en pratique le NCC : c'est par lui que le
 * relevé retrouve son entreprise dans Selflow. Un nom qui ne suit pas la
 * nomenclature est laissé de côté et signalé — deviner à quelle entreprise
 * appartient un fichier nommé au hasard reviendrait à ranger les données
 * fiscales d'un client chez un autre.
 *
 * ## Ce qu'il ne fait pas, et c'est délibéré
 *
 * **Il n'écrit rien dans `entreprises`, ni nulle part ailleurs dans
 * l'application.** Il dépose ce qu'il a lu dans ses trois tables, et s'arrête
 * là. Trois des champs relevés — `timbre_quittance`, `bapa`,
 * `sticker_solde_alerte` — commandent le comportement fiscal de Selflow : les
 * recopier automatiquement ferait changer une facture parce qu'un fichier a été
 * déposé dans un dossier, sans que personne ne l'ait décidé. Le rapprochement
 * se regarde (`PortailFneFiche::ecartsAvecEntreprise()`) avant de s'appliquer.
 *
 * **Il ne déplace ni ne supprime les fichiers lus.** L'empreinte SHA-256 rend
 * la relecture d'un dossier sans effet : c'est elle qui tient lieu de marque de
 * traitement, pas le déplacement du fichier. Le dossier d'origine reste donc
 * intact, et un relevé peut être relu après correction d'un défaut d'import.
 *
 * ## Usage
 *
 *   app(ImportPortailFneService::class)->importerDossier();
 *   app(ImportPortailFneService::class)->importerFichier('C:/…/1864699A_20260821.json');
 */
class ImportPortailFneService
{
    /**
     * Les clés du JSON du portail, et la colonne qui les reçoit.
     *
     * Le portail rend ses libellés en français, accents et apostrophes
     * compris. La correspondance est explicite plutôt que déduite : un libellé
     * qui change au portail doit casser bruyamment ici, et non arriver
     * silencieusement dans la mauvaise colonne.
     *
     * @var array<string, string>
     */
    private const CHAMPS_FICHE = [
        'Email'                                                 => 'email',
        'Téléphone'                                             => 'telephone',
        'Adresse'                                               => 'adresse',
        'Commune'                                               => 'commune',
        'Quartier'                                              => 'quartier',
        'Référence Cadastrale'                                  => 'reference_cadastrale',
        'IDU'                                                   => 'idu',
        "Propriétaire du local professionnel de l'entreprise"   => 'proprietaire_local',
        "Sticker : solde d'alerte"                              => 'sticker_solde_alerte',
        'Références bancaires'                                  => 'ref_bancaire',
        'Timbre de quittance'                                   => 'timbre_quittance',
        "Bordereau d'achat de produits agricoles"               => 'bapa',
        'Pied de page des factures'                             => 'pied_de_page_facture',
        'Factures autres mentions'                              => 'facture_autres_mentions',
    ];

    /**
     * Les en-têtes du tableur, et la colonne qui les reçoit.
     *
     * La lecture se fait par en-tête et non par position : le portail peut
     * réordonner ses colonnes, et un import qui compte les colonnes rangerait
     * alors un statut dans un nom d'établissement.
     *
     * @var array<string, string>
     */
    private const COLONNES_POINTS = [
        'nom'                     => 'nom',
        'outil'                   => 'outil',
        'id du terminal'          => 'terminal_id',
        'statut'                  => 'statut',
        'raison de statut'        => 'raison_statut',
        "id de l'etablissement"   => 'etablissement_id',
        'cree a'                  => 'cree_a',
        'mise a jour a'           => 'mis_a_jour_a',
    ];

    /**
     * Les champs de la fiche qui sont des nombres, et ceux qui sont des
     * booléens. Tout le reste est conservé tel quel.
     */
    private const CHAMPS_ENTIERS  = ['sticker_solde_alerte'];
    private const CHAMPS_BOOLEENS = ['timbre_quittance', 'bapa'];

    /**
     * Lit tous les relevés d'un dossier.
     *
     * @param  string|null  $dossier  Le dossier à parcourir. Par défaut, celui
     *                                de `config('selflow.portail_fne.dossier_import')`.
     * @return array{dossier: string, importes: int, ignores: int, erreurs: int, details: array<int, array<string, mixed>>}
     */
    public function importerDossier(?string $dossier = null): array
    {
        $dossier = $dossier ?: (string) config('selflow.portail_fne.dossier_import');

        $rapport = [
            'dossier'  => $dossier,
            'importes' => 0,
            'ignores'  => 0,
            'erreurs'  => 0,
            'details'  => [],
        ];

        if (!is_dir($dossier)) {
            $rapport['erreurs']++;
            $rapport['details'][] = [
                'fichier' => $dossier,
                'statut'  => 'erreur',
                'message' => "Le dossier d'import n'existe pas.",
            ];

            return $rapport;
        }

        $fichiers = array_merge(
            glob(rtrim($dossier, '/\\') . DIRECTORY_SEPARATOR . '*.json') ?: [],
            glob(rtrim($dossier, '/\\') . DIRECTORY_SEPARATOR . '*.xlsx') ?: [],
        );

        // Les plus anciens d'abord : le dernier relevé lu doit être le plus
        // récent, sinon l'ordre des lignes en base ment sur l'ordre des faits.
        sort($fichiers);

        foreach ($fichiers as $chemin) {
            $resultat = $this->importerFichier($chemin);

            $rapport['details'][] = $resultat;
            $cle = match ($resultat['statut']) {
                'importe' => 'importes',
                'ignore'  => 'ignores',
                default   => 'erreurs',
            };
            $rapport[$cle]++;
        }

        return $rapport;
    }

    /**
     * Lit un relevé et le range en base.
     *
     * @return array{fichier: string, statut: 'importe'|'ignore'|'erreur', message: string, import_id: int|null, lignes: int}
     */
    public function importerFichier(string $chemin): array
    {
        $nom = basename($chemin);

        if (!is_file($chemin) || !is_readable($chemin)) {
            return $this->resultat($nom, 'erreur', 'Fichier introuvable ou illisible.');
        }

        $nomenclature = $this->analyserNom($nom);

        if ($nomenclature === null) {
            return $this->resultat(
                $nom,
                'erreur',
                'Nom hors nomenclature : attendu <login>_<date>.json ou <login>_<date>.xlsx.'
            );
        }

        [$login, $date, $type] = [$nomenclature['login'], $nomenclature['date'], $nomenclature['type']];

        $empreinte = hash_file('sha256', $chemin);

        $dejaVu = PortailFneImport::where('fichier_empreinte', $empreinte)->first();

        if ($dejaVu) {
            return $this->resultat(
                $nom,
                'ignore',
                "Déjà importé le {$dejaVu->created_at->format('d/m/Y à H:i')}.",
                $dejaVu->id,
                $dejaVu->lignes_importees
            );
        }

        $entreprise = $this->resoudreEntreprise($login);

        try {
            return DB::transaction(function () use ($chemin, $nom, $login, $date, $type, $empreinte, $entreprise) {
                $donnees = $type === PortailFneImport::TYPE_FICHE
                    ? $this->lireJson($chemin)
                    : $this->lireTableur($chemin);

                $import = PortailFneImport::create([
                    'entreprise_id'     => $entreprise?->id,
                    'login'             => $login,
                    'date_scraping'     => $date,
                    'type'              => $type,
                    'fichier_nom'       => $nom,
                    'fichier_empreinte' => $empreinte,
                    'donnees_brutes'    => $donnees,
                    'statut'            => PortailFneImport::STATUT_IMPORTE,
                    'importe_at'        => now(),
                ]);

                $lignes = $type === PortailFneImport::TYPE_FICHE
                    ? $this->rangerFiche($import, $donnees)
                    : $this->rangerPoints($import, $donnees);

                $import->update(['lignes_importees' => $lignes]);

                // Une demande de relevé en attente pour ce login est servie par
                // l'arrivée du fichier, jamais par la parole du scraper : un
                // scraper qui échoue en silence laisse sa demande ouverte, et
                // c'est exactement ce qu'on veut voir dans la file.
                PortailFneDemande::where('login', $login)
                    ->where('statut', PortailFneDemande::STATUT_EN_ATTENTE)
                    ->get()
                    ->each(fn (PortailFneDemande $demande) => $demande->servir($import));

                $message = $entreprise
                    ? "Rattaché à {$entreprise->nom}."
                    : "Aucune entreprise ne porte le NCC {$login} : relevé conservé sans rattachement.";

                return $this->resultat($nom, 'importe', $message, $import->id, $lignes);
            });
        } catch (Throwable $e) {
            // La trace du fichier fautif survit à l'échec, hors transaction :
            // sans elle, un fichier illisible se redéposerait indéfiniment sans
            // que rien n'explique pourquoi il n'arrive jamais en base.
            $import = PortailFneImport::create([
                'entreprise_id'     => $entreprise?->id,
                'login'             => $login,
                'date_scraping'     => $date,
                'type'              => $type,
                'fichier_nom'       => $nom,
                'fichier_empreinte' => $empreinte,
                'statut'            => PortailFneImport::STATUT_ERREUR,
                'message'           => $e->getMessage(),
            ]);

            Log::error('Import portail FNE : lecture impossible', [
                'fichier'   => $nom,
                'import_id' => $import->id,
                'erreur'    => $e->getMessage(),
            ]);

            return $this->resultat($nom, 'erreur', $e->getMessage(), $import->id);
        }
    }

    /**
     * Décompose `1864699A_20260821.json` en login, date et type.
     *
     * Le login est **tout ce qui précède le dernier tiret bas**, et non le
     * premier segment : `LOGIN_CLIENT_20260821` désigne un login qui contient
     * lui-même un tiret bas, et découper au premier le tronquerait.
     *
     * @return array{login: string, date: CarbonImmutable, type: string}|null
     */
    private function analyserNom(string $nom): ?array
    {
        $extension = strtolower(pathinfo($nom, PATHINFO_EXTENSION));

        $type = match ($extension) {
            'json' => PortailFneImport::TYPE_FICHE,
            'xlsx' => PortailFneImport::TYPE_POINTS,
            default => null,
        };

        if ($type === null) {
            return null;
        }

        $base = pathinfo($nom, PATHINFO_FILENAME);
        $position = strrpos($base, '_');

        if ($position === false || $position === 0) {
            return null;
        }

        $login = substr($base, 0, $position);
        $date  = $this->lireDate(substr($base, $position + 1));

        if ($date === null || trim($login) === '') {
            return null;
        }

        return ['login' => trim($login), 'date' => $date, 'type' => $type];
    }

    /**
     * Les deux écritures de date rencontrées dans les noms de fichiers.
     */
    private function lireDate(string $valeur): ?CarbonImmutable
    {
        foreach (['Ymd', 'Y-m-d', 'd-m-Y', 'dmY'] as $format) {
            $date = CarbonImmutable::createFromFormat('!' . $format, $valeur);

            if ($date !== false && $date->format($format) === $valeur) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Retrouve l'entreprise que désigne le login du portail.
     *
     * La comparaison ignore les espaces et la casse : le portail rend
     * `1864699A` là où Selflow enregistre parfois `1864699 A`. Le NCC d'abord,
     * le compte contribuable ensuite — c'est le NCC qui identifie l'entreprise
     * auprès de la DGI.
     */
    private function resoudreEntreprise(string $login): ?Entreprise
    {
        $recherche = $this->normaliser($login);

        if ($recherche === '') {
            return null;
        }

        foreach (['ncc', 'compte_contribuable'] as $colonne) {
            $entreprise = Entreprise::whereNotNull($colonne)
                ->where($colonne, '<>', '')
                ->get()
                ->first(fn (Entreprise $e) => $this->normaliser((string) $e->{$colonne}) === $recherche);

            if ($entreprise) {
                return $entreprise;
            }
        }

        return null;
    }

    private function normaliser(string $valeur): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $valeur) ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function lireJson(string $chemin): array
    {
        $contenu = file_get_contents($chemin);

        if ($contenu === false) {
            throw new \RuntimeException('Lecture du fichier impossible.');
        }

        $donnees = json_decode($contenu, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($donnees)) {
            throw new \RuntimeException('Le JSON ne contient pas un objet.');
        }

        return $donnees;
    }

    /**
     * Lit le tableur et rend ses lignes sous forme de tableaux associatifs,
     * clés = en-têtes de la première ligne.
     *
     * @return array<int, array<string, string|null>>
     */
    private function lireTableur(string $chemin): array
    {
        $lecteur = IOFactory::createReaderForFile($chemin);
        $lecteur->setReadDataOnly(true);

        $feuille = $lecteur->load($chemin)->getActiveSheet();
        $lignes  = $feuille->toArray(null, true, false, false);

        if ($lignes === []) {
            return [];
        }

        $entetes = array_map(
            fn ($entete) => trim((string) $entete),
            array_shift($lignes)
        );

        $resultat = [];

        foreach ($lignes as $ligne) {
            $associee = [];

            foreach ($entetes as $index => $entete) {
                if ($entete === '') {
                    continue;
                }

                $valeur = $ligne[$index] ?? null;
                $associee[$entete] = $valeur === null ? null : trim((string) $valeur);
            }

            // Une ligne entièrement vide est un artefact du tableur, pas un
            // point de facturation.
            if (array_filter($associee, fn ($v) => $v !== null && $v !== '') !== []) {
                $resultat[] = $associee;
            }
        }

        return $resultat;
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function rangerFiche(PortailFneImport $import, array $donnees): int
    {
        $valeurs = [
            'import_id'     => $import->id,
            'entreprise_id' => $import->entreprise_id,
            'login'         => $import->login,
            'date_scraping' => $import->date_scraping,
        ];

        $inconnus = [];

        foreach ($donnees as $libelle => $valeur) {
            $colonne = self::CHAMPS_FICHE[trim((string) $libelle)] ?? null;

            if ($colonne === null) {
                $inconnus[$libelle] = $valeur;
                continue;
            }

            $valeurs[$colonne] = $this->convertir($colonne, $valeur);
        }

        $valeurs['champs_inconnus'] = $inconnus ?: null;

        PortailFneFiche::create($valeurs);

        if ($inconnus !== []) {
            Log::warning('Import portail FNE : champs inconnus dans la fiche', [
                'fichier' => $import->fichier_nom,
                'champs'  => array_keys($inconnus),
            ]);
        }

        return 1;
    }

    /**
     * @param  array<int, array<string, string|null>>  $lignes
     */
    private function rangerPoints(PortailFneImport $import, array $lignes): int
    {
        $comptees = 0;

        foreach ($lignes as $ligne) {
            $valeurs = [
                'import_id'     => $import->id,
                'entreprise_id' => $import->entreprise_id,
                'login'         => $import->login,
                'date_scraping' => $import->date_scraping,
            ];

            foreach ($ligne as $entete => $valeur) {
                $colonne = self::COLONNES_POINTS[$this->cleEntete($entete)] ?? null;

                if ($colonne === null) {
                    continue;
                }

                $valeurs[$colonne] = in_array($colonne, ['cree_a', 'mis_a_jour_a'], true)
                    ? $this->lireHorodatage($valeur)
                    : ($valeur === '' ? null : $valeur);
            }

            PortailFnePointFacturation::create($valeurs);
            $comptees++;
        }

        return $comptees;
    }

    /**
     * Ramène un en-tête de colonne à une forme comparable : sans accents, sans
     * casse, sans espaces superflus. « ID de l'établissement » et « ID de
     * l'etablissement » désignent la même colonne.
     */
    private function cleEntete(string $entete): string
    {
        $sansAccent = strtr(
            $entete,
            ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a',
             'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u',
             'ü' => 'u', 'ç' => 'c', 'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'À' => 'a',
             'Ô' => 'o', 'Ç' => 'c', '’' => "'"]
        );

        return trim(preg_replace('/\s+/', ' ', mb_strtolower($sansAccent)) ?? '');
    }

    /**
     * Le portail rend ses dates en ISO 8601 avec millisecondes
     * (`2026-07-30T10:38:40.726Z`). Une date illisible ne fait pas échouer
     * l'import : elle reste nulle, et la ligne conserve le reste.
     */
    private function lireHorodatage(?string $valeur): ?CarbonImmutable
    {
        if ($valeur === null || trim($valeur) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($valeur);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Le portail rend ses nombres et ses booléens tantôt typés, tantôt entre
     * guillemets. La colonne, elle, a un type.
     */
    private function convertir(string $colonne, mixed $valeur): mixed
    {
        if ($valeur === null) {
            return null;
        }

        if (in_array($colonne, self::CHAMPS_ENTIERS, true)) {
            $chiffres = preg_replace('/[^0-9-]/', '', (string) $valeur);

            return ($chiffres === '' || $chiffres === null) ? null : (int) $chiffres;
        }

        if (in_array($colonne, self::CHAMPS_BOOLEENS, true)) {
            return filter_var($valeur, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $texte = trim((string) $valeur);

        // Le portail écrit « * » pour un champ qu'il n'a pas. Le conserver tel
        // quel ferait passer une absence pour une valeur.
        return ($texte === '' || $texte === '*') ? null : $texte;
    }

    /**
     * @return array{fichier: string, statut: string, message: string, import_id: int|null, lignes: int}
     */
    private function resultat(
        string $fichier,
        string $statut,
        string $message,
        ?int $importId = null,
        int $lignes = 0
    ): array {
        return [
            'fichier'   => $fichier,
            'statut'    => $statut,
            'message'   => $message,
            'import_id' => $importId,
            'lignes'    => $lignes,
        ];
    }
}
