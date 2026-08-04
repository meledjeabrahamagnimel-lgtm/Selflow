<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\SousCategorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportControleur
{
    // ─────────────────────────────────────────────────────────────
    // Colonnes CSV par module
    // ─────────────────────────────────────────────────────────────

    private const COLONNES = [
        'points-de-vente' => [
            'headers'  => ['nom', 'ville', 'commune', 'responsable', 'telephone', 'statut'],
            'exemple'  => [
                ['Agence Cocody', 'Abidjan', 'COCODY', 'Konan Kouassi', '0707000001', 'actif'],
                ['Agence Plateau', 'Abidjan', 'PLATEAU', 'Aya Boni', '0707000002', 'actif'],
            ],
        ],
        'clients' => [
            'headers'  => ['nom', 'type_facturation', 'telephone', 'email', 'adresse', 'ncc', 'regime_imposition', 'rccm', 'compte_comptable', 'numero_tiers'],
            'exemple'  => [
                ['Société ABC SARL', 'B2B', '+225 27 00 00 01', 'contact@abc.ci', 'Cocody, Abidjan', '2302178R', 'RNI', 'CI-ABJ-2021-001', '411001', 'C001'],
                ['Marie Koffi', 'B2C', '+225 07 00 00 02', 'marie@gmail.com', 'Yopougon, Abidjan', '', '', '', '411002', 'C002'],
            ],
        ],
        'fournisseurs' => [
            'headers'  => ['nom', 'type_facturation', 'telephone', 'email', 'secteur', 'adresse', 'ncc', 'regime_imposition', 'rccm', 'compte_comptable', 'numero_tiers'],
            'exemple'  => [
                ['CDCI Distribution', 'B2B', '+225 27 00 01 00', 'cdci@cdci.ci', 'Distribution', 'Zone 4, Marcory', '2169728N', 'RSI', 'CI-ABJ-2020-100', '401001', 'F001'],
                ['Société Générale CI', 'B2G', '+225 20 00 00 00', 'sgci@sg.ci', 'Finance', 'Plateau', '', '', '', '401002', 'F002'],
            ],
        ],
        'utilisateurs' => [
            'headers'  => ['nom', 'prenom', 'email', 'role', 'point_de_vente', 'fonction', 'date_debut_contrat', 'statut'],
            'exemple'  => [
                ['Koffi', 'Amos', 'amos.koffi@monentreprise.ci', 'caissier', 'Agence Cocody', 'Caissier Principal', '01/01/2025', 'actif'],
                ['Boni', 'Aya', 'aya.boni@monentreprise.ci', 'responsable_pdv', 'Agence Plateau', 'Responsable', '15/03/2024', 'actif'],
            ],
        ],
        'produits' => [
            'headers'  => ['nom', 'type', 'categorie', 'sous_categorie', 'unite', 'prix_achat', 'prix_vente', 'taux_tva', 'compte_vente', 'compte_achat', 'reference', 'statut'],
            'exemple'  => [
                ['Stylo bille bleu', 'consommable_stockable', 'Fournitures', 'Papeterie', 'pièce', '150', '250', '18', '701001', '601001', 'STYL-BLU-001', 'actif'],
                ['Riz parfumé 25kg', 'marchandise', 'Alimentation', 'Céréales', 'sac', '12000', '15000', '0', '701002', '601002', '', 'actif'],
            ],
        ],
    ];

    // ─────────────────────────────────────────────────────────────
    // GET — Télécharger le fichier CSV d'exemple
    // ─────────────────────────────────────────────────────────────

    public function telechargerExemple(string $type): Response
    {
        $config = self::COLONNES[$type] ?? null;
        abort_if(!$config, 404, "Module d'import inconnu : {$type}");

        $rows = [$config['headers'], ...$config['exemple']];

        if (request('format') === 'excel') {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();

            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $colIndex => $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                    $sheet->setCellValue($colLetter . ($rowIndex + 1), $value);
                }
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();

            return response($content, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="import_' . $type . '_exemple.xlsx"',
            ]);
        }

        $output = '';
        foreach ($rows as $row) {
            $output .= implode(';', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\r\n";
        }

        return response($output, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="import_' . $type . '_exemple.csv"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST — Prévisualisation (5 premières lignes)
    // ─────────────────────────────────────────────────────────────

    public function preview(Request $request, string $type): JsonResponse
    {
        $config = self::COLONNES[$type] ?? null;
        if (!$config) return response()->json(['success' => false, 'message' => 'Module inconnu.'], 400);

        $request->validate(['fichier' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']]);

        try {
            $lignes = $this->lireFichier($request->file('fichier'));
            if (empty($lignes)) {
                return response()->json(['success' => false, 'message' => 'Fichier vide ou illisible.']);
            }

            $entetes = array_shift($lignes);
            $preview = array_slice($lignes, 0, 5);

            return response()->json([
                'success'  => true,
                'entetes'  => $entetes,
                'lignes'   => $preview,
                'total'    => count($lignes),
                'colonnes_attendues' => $config['headers'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lecture : ' . $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // POST — Importer (validation + insertion ligne par ligne)
    // ─────────────────────────────────────────────────────────────

    public function importer(Request $request, string $type): JsonResponse
    {
        $config = self::COLONNES[$type] ?? null;
        if (!$config) return response()->json(['success' => false, 'message' => 'Module inconnu.'], 400);

        $request->validate(['fichier' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']]);

        $entreprise = Auth::user()->entreprise;
        $lignes     = $this->lireFichier($request->file('fichier'));

        if (empty($lignes)) {
            return response()->json(['success' => false, 'message' => 'Fichier vide.']);
        }

        $entetes   = array_map('strtolower', array_map('trim', array_shift($lignes)));
        $importes  = 0;
        $erreurs   = [];

        DB::beginTransaction();
        try {
            foreach ($lignes as $idx => $row) {
                $num   = $idx + 2; // ligne humaine (1 = entetes)
                $data  = array_combine($entetes, array_pad($row, count($entetes), ''));

                $erreur = $this->traiterLigne($type, $data, $entreprise, $num);
                if ($erreur) {
                    $erreurs[] = $erreur;
                } else {
                    $importes++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import ' . $type . ' — Exception : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur critique : ' . $e->getMessage()]);
        }

        return response()->json([
            'success'  => true,
            'importes' => $importes,
            'erreurs'  => $erreurs,
            'message'  => "{$importes} ligne(s) importée(s) avec succès." .
                          (count($erreurs) ? ' ' . count($erreurs) . ' erreur(s) ignorée(s).' : ''),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Lecture CSV / XLSX → tableau brut de lignes
    // ─────────────────────────────────────────────────────────────

    private function lireFichier(\Illuminate\Http\UploadedFile $fichier): array
    {
        $ext = strtolower($fichier->getClientOriginalExtension());

        if (in_array($ext, ['xlsx', 'xls'])) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fichier->getRealPath());
                $worksheet   = $spreadsheet->getActiveSheet();
                $rows        = [];
                foreach ($worksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $rowData[] = $cell->getValue();
                    }
                    if (count(array_filter($rowData, fn($v) => !is_null($v) && trim((string)$v) !== '')) > 0) {
                        $rows[] = $rowData;
                    }
                }
                return $rows;
            } catch (\Exception $e) {
                throw new \RuntimeException('Erreur lors de la lecture du fichier Excel : ' . $e->getMessage());
            }
        }

        // CSV (utf-8 ou latin-1 avec BOM)
        $contenu = file_get_contents($fichier->getRealPath());
        $contenu = ltrim($contenu, "\xEF\xBB\xBF");
        if (!mb_detect_encoding($contenu, 'UTF-8', true)) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'ISO-8859-1');
        }

        $lignes = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $contenu)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $delim  = substr_count($line, ';') >= substr_count($line, ',') ? ';' : ',';
            $lignes[] = str_getcsv($line, $delim);
        }
        return $lignes;
    }

    // ─────────────────────────────────────────────────────────────
    // Traitement d'une ligne selon le type
    // ─────────────────────────────────────────────────────────────

    private function traiterLigne(string $type, array $data, $entreprise, int $num): ?string
    {
        return match ($type) {
            'points-de-vente' => $this->importerPointDeVente($data, $entreprise, $num),
            'clients'         => $this->importerClient($data, $entreprise, $num),
            'fournisseurs'    => $this->importerFournisseur($data, $entreprise, $num),
            'utilisateurs'    => $this->importerUtilisateur($data, $entreprise, $num),
            'produits'        => $this->importerProduit($data, $entreprise, $num),
            default           => "Ligne {$num} : module inconnu.",
        };
    }

    // ─────────────────────────────────────────────────────────────
    // MODULE 1 — Points de vente
    // ─────────────────────────────────────────────────────────────

    private function importerPointDeVente(array $d, $entreprise, int $num): ?string
    {
        $nom = trim($d['nom'] ?? '');
        if (!$nom) return "Ligne {$num} : 'nom' obligatoire.";

        PointDeVente::firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'nom' => $nom],
            [
                'ville'       => trim($d['ville'] ?? ''),
                'commune'     => trim($d['commune'] ?? ''),
                'responsable' => trim($d['responsable'] ?? ''),
                'telephone'   => trim($d['telephone'] ?? ''),
                'statut'      => in_array(strtolower($d['statut'] ?? ''), ['inactif', 'inactive', '0']) ? 'inactif' : 'actif',
            ]
        );
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // MODULE 2A — Clients
    // ─────────────────────────────────────────────────────────────

    private function importerClient(array $d, $entreprise, int $num): ?string
    {
        $nom = trim($d['nom'] ?? '');
        if (!$nom) return "Ligne {$num} : 'nom' obligatoire.";

        $type = strtoupper(trim($d['type_facturation'] ?? 'B2C'));
        if (!in_array($type, ['B2B', 'B2C', 'B2G', 'B2F'])) $type = 'B2C';

        $ncc = $this->normaliserNcc(trim($d['ncc'] ?? ''));
        if ($type === 'B2B' && !$ncc) {
            return "Ligne {$num} : NCC obligatoire pour le type B2B (client: {$nom}).";
        }
        if ($ncc && !$this->nccValide($ncc)) {
            return "Ligne {$num} : NCC invalide pour le client {$nom}. Il doit contenir 8 caractères et se terminer par une lettre majuscule.";
        }

        // Numéro tiers : auto si vide
        $numeroTiers = trim($d['numero_tiers'] ?? '');
        if (!$numeroTiers) {
            $max = Client::where('entreprise_id', $entreprise->id)
                ->where('numero_tiers', 'like', '411%')
                ->max('numero_tiers');
            $numeroTiers = $max ? str_pad((int)$max + 1, strlen($max), '0', STR_PAD_LEFT) : '411001';
        }

        Client::firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'nom' => $nom],
            [
                'type_facturation' => $type,
                'telephone'        => trim($d['telephone'] ?? ''),
                'email'            => trim($d['email'] ?? '') ?: null,
                'adresse'          => trim($d['adresse'] ?? ''),
                'ncc'              => $type === 'B2B' ? $ncc : null,
                'regime_imposition'=> trim($d['regime_imposition'] ?? ''),
                'rccm'             => trim($d['rccm'] ?? ''),
                'compte_comptable' => trim($d['compte_comptable'] ?? '') ?: '411000',
                'numero_tiers'     => $numeroTiers,
                'source'           => 'import_csv',
            ]
        );
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // MODULE 2B — Fournisseurs
    // ─────────────────────────────────────────────────────────────

    private function importerFournisseur(array $d, $entreprise, int $num): ?string
    {
        $nom = trim($d['nom'] ?? '');
        if (!$nom) return "Ligne {$num} : 'nom' obligatoire.";

        $type = strtoupper(trim($d['type_facturation'] ?? 'B2B'));
        if (!in_array($type, ['B2B', 'B2C', 'B2G', 'B2F'])) $type = 'B2B';

        $ncc = $this->normaliserNcc(trim($d['ncc'] ?? ''));
        if ($type === 'B2B' && !$ncc) {
            return "Ligne {$num} : NCC obligatoire pour le type B2B (fournisseur: {$nom}).";
        }
        if ($ncc && !$this->nccValide($ncc)) {
            return "Ligne {$num} : NCC invalide pour le fournisseur {$nom}. Il doit contenir 8 caractères et se terminer par une lettre majuscule.";
        }

        $numeroTiers = trim($d['numero_tiers'] ?? '');
        if (!$numeroTiers) {
            $max = Fournisseur::where('entreprise_id', $entreprise->id)
                ->where('numero_tiers', 'like', '401%')
                ->max('numero_tiers');
            $numeroTiers = $max ? str_pad((int)$max + 1, strlen($max), '0', STR_PAD_LEFT) : '401001';
        }

        Fournisseur::firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'nom' => $nom],
            [
                'type_facturation' => $type,
                'telephone'        => trim($d['telephone'] ?? ''),
                'email'            => trim($d['email'] ?? '') ?: null,
                'secteur'          => trim($d['secteur'] ?? ''),
                'adresse'          => trim($d['adresse'] ?? ''),
                'ncc'              => $type === 'B2B' ? $ncc : null,
                'regime_imposition'=> trim($d['regime_imposition'] ?? ''),
                'rccm'             => trim($d['rccm'] ?? ''),
                'compte_comptable' => trim($d['compte_comptable'] ?? '') ?: '401000',
                'numero_tiers'     => $numeroTiers,
                'source'           => 'import_csv',
            ]
        );
        return null;
    }

    private function normaliserNcc(?string $ncc): ?string
    {
        $ncc = $ncc === null ? '' : preg_replace('/\s+/', '', $ncc);
        $ncc = strtoupper($ncc);

        return $ncc === '' ? null : $ncc;
    }

    private function nccValide(?string $ncc): bool
    {
        return $ncc === null || preg_match('/^[A-Z0-9]{7}[A-Z]$/', $ncc) === 1;
    }

    // ─────────────────────────────────────────────────────────────
    // MODULE 3 — Utilisateurs
    // ─────────────────────────────────────────────────────────────

    private function importerUtilisateur(array $d, $entreprise, int $num): ?string
    {
        $nom    = trim($d['nom'] ?? '');
        $prenom = trim($d['prenom'] ?? '');
        $email  = trim($d['email'] ?? '');
        $role   = trim($d['role'] ?? 'caissier');

        if (!$nom)   return "Ligne {$num} : 'nom' obligatoire.";
        if (!$prenom) return "Ligne {$num} : 'prenom' obligatoire.";
        if (!$email)  return "Ligne {$num} : 'email' obligatoire.";

        if (!in_array($role, ['admin_secondaire', 'responsable_pdv', 'caissier'])) {
            $role = 'caissier';
        }

        // Résoudre le point de vente par nom
        $pdvId = null;
        $pdvNom = trim($d['point_de_vente'] ?? '');
        if ($pdvNom) {
            $pdv = PointDeVente::where('entreprise_id', $entreprise->id)
                ->whereRaw('LOWER(nom) = ?', [strtolower($pdvNom)])
                ->first();
            if ($pdv) $pdvId = $pdv->id;
        }

        $dateDebut = null;
        if (!empty($d['date_debut_contrat'])) {
            try {
                $dateDebut = \Carbon\Carbon::createFromFormat('d/m/Y', $d['date_debut_contrat'])->toDateString();
            } catch (\Exception $e) {
                // Format invalide, on ignore
            }
        }

        $motDePasse = Str::random(12);

        $userModel = \App\Modules\Authentification\Modeles\Utilisateur::class;
        $userModel::firstOrCreate(
            ['email' => $email],
            [
                'entreprise_id'         => $entreprise->id,
                'point_de_vente_id'     => $pdvId,
                'nom'                   => $nom,
                'prenom'                => $prenom,
                'password'              => Hash::make($motDePasse),
                'role'                  => $role,
                'fonction'              => trim($d['fonction'] ?? ''),
                'date_debut_contrat'    => $dateDebut,
                'statut'                => strtolower($d['statut'] ?? '') === 'inactif' ? 'inactif' : 'actif',
                'doit_changer_password' => true,
            ]
        );
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // MODULE 4 — Produits
    // ─────────────────────────────────────────────────────────────

    private function importerProduit(array $d, $entreprise, int $num): ?string
    {
        $nom = trim($d['nom'] ?? '');
        if (!$nom) return "Ligne {$num} : 'nom' obligatoire.";

        $typesValides = ['marchandise', 'matiere_premiere', 'produit_fini', 'consommable_stockable', 'consommable_non_stockable', 'service'];
        $type = trim($d['type'] ?? 'marchandise');
        if (!in_array($type, $typesValides)) {
            return "Ligne {$num} : type '{$type}' invalide. Valeurs : " . implode(', ', $typesValides);
        }

        $prixAchat  = (float) str_replace([' ', ','], ['', '.'], $d['prix_achat'] ?? 0);
        $prixVente  = (float) str_replace([' ', ','], ['', '.'], $d['prix_vente'] ?? 0);
        $tauxTva    = (int) ($d['taux_tva'] ?? 18);
        if (!in_array($tauxTva, [0, 9, 18])) $tauxTva = 18;

        if ($prixAchat <= 0) return "Ligne {$num} : prix_achat doit être > 0.";
        if ($prixVente <= 0) return "Ligne {$num} : prix_vente doit être > 0.";

        // Résoudre catégorie par nom
        $categorieId = null;
        if (!empty($d['categorie'])) {
            $cat = Categorie::where('entreprise_id', $entreprise->id)
                ->whereRaw('LOWER(nom) = ?', [strtolower($d['categorie'])])
                ->first();
            $categorieId = $cat?->id;
        }

        // Référence : auto si vide
        $reference = trim($d['reference'] ?? '');
        if (!$reference) {
            $reference = strtoupper(Str::slug($nom, '-')) . '-' . strtoupper(Str::random(4));
        }

        Produit::firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'reference' => $reference],
            [
                'nom'          => $nom,
                'type'         => $type,
                'categorie_id' => $categorieId,
                'unite'        => trim($d['unite'] ?? 'pièce'),
                'prix_achat'   => $prixAchat,
                'prix_vente'   => $prixVente,
                'taux_tva'     => $tauxTva,
                'compte_vente' => trim($d['compte_vente'] ?? '') ?: '701000',
                'compte_achat' => trim($d['compte_achat'] ?? '') ?: '601000',
                'statut'       => strtolower($d['statut'] ?? '') === 'inactif' ? 'inactif' : 'actif',
            ]
        );
        return null;
    }
}
