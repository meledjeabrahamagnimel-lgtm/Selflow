<?php

namespace Database\Seeders;

use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\FicheTechnique;
use App\Modules\Admin\Modeles\FicheTechniqueDetail;
use App\Modules\Admin\Modeles\OrdreProduction;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use Illuminate\Database\Seeder;

class RecettesEnrichiesSeeder extends Seeder
{
    public function run(): void
    {
        $entreprises = Entreprise::all();

        if ($entreprises->count() < 1) {
            $this->command->error('Aucune entreprise trouvee. Lancez d abord DonneesInitialesSeeder.');
            return;
        }

        foreach ($entreprises as $entreprise) {
            $secteurs = $entreprise->secteur_activite ?? [];
            $modules  = $entreprise->modules_actifs   ?? [];
            if (!in_array('Industriel', $secteurs)) { $secteurs[] = 'Industriel'; }
            if (!in_array('production', $modules))  { $modules[]  = 'production'; }
            $entreprise->update(['secteur_activite' => $secteurs, 'modules_actifs' => $modules]);

            $this->seederPourEntreprise($entreprise);
        }

        $this->command->info('Recettes enrichies inserees avec succes.');
    }

    private function seederPourEntreprise(Entreprise $ent): void
    {
        $pdv = PointDeVente::where('entreprise_id', $ent->id)->first();
        if (!$pdv) {
            $this->command->warn("Aucun PDV pour {$ent->nom}");
            return;
        }

        $catMetal   = Categorie::firstOrCreate(['entreprise_id' => $ent->id, 'nom' => 'Metallurgie & Acier'],         ['prefixe' => 'MET']);
        $catPlastic = Categorie::firstOrCreate(['entreprise_id' => $ent->id, 'nom' => 'Plastiques & Composites'],     ['prefixe' => 'PLC']);
        $catElec    = Categorie::firstOrCreate(['entreprise_id' => $ent->id, 'nom' => 'Electronique Industrielle'],   ['prefixe' => 'ELEC']);
        $catBois    = Categorie::firstOrCreate(['entreprise_id' => $ent->id, 'nom' => 'Menuiserie & Bois'],           ['prefixe' => 'BOIS']);

        $mp = $this->creerMatieres($ent, $pdv, $catMetal, $catPlastic, $catElec, $catBois);
        $this->creerRecettes($ent, $pdv, $mp, $catMetal, $catPlastic, $catElec, $catBois);

        $this->command->info("  -> {$ent->nom} : recettes creees.");
    }

    private function creerMatieres(Entreprise $ent, PointDeVente $pdv, $catMetal, $catPlastic, $catElec, $catBois): array
    {
        $list = [
            ['nom' => 'Aluminium Brut (Lingot)',           'key' => 'ALU-LIN',  'cat' => $catMetal,   'unite' => 'Kg',    'prix' => 4200,  'stock' => 1000],
            ['nom' => 'Acier Inoxydable 304 (Barre)',       'key' => 'ACR-INX',  'cat' => $catMetal,   'unite' => 'Kg',    'prix' => 6800,  'stock' => 500],
            ['nom' => 'Cuivre Electrolytique (Fil)',        'key' => 'CUI-FIL',  'cat' => $catMetal,   'unite' => 'Kg',    'prix' => 9500,  'stock' => 200],
            ['nom' => 'Zinc Pur 99.9%',                    'key' => 'ZIN-PUR',  'cat' => $catMetal,   'unite' => 'Kg',    'prix' => 3200,  'stock' => 300],
            ['nom' => 'PVC Granules Rigides',               'key' => 'PVC-GRN',  'cat' => $catPlastic, 'unite' => 'Kg',    'prix' => 1800,  'stock' => 800],
            ['nom' => 'Polyethylene HDPE (Feuille)',        'key' => 'HDPE-FEU', 'cat' => $catPlastic, 'unite' => 'm2',   'prix' => 2400,  'stock' => 600],
            ['nom' => 'Resine Epoxy Durcisseur',            'key' => 'RES-EPX',  'cat' => $catPlastic, 'unite' => 'L',     'prix' => 7500,  'stock' => 150],
            ['nom' => 'Caoutchouc Naturel (Bloc)',          'key' => 'CAO-NAT',  'cat' => $catPlastic, 'unite' => 'Kg',    'prix' => 5500,  'stock' => 250],
            ['nom' => 'Condensateurs 100uF (Lot 100)',      'key' => 'COND-100', 'cat' => $catElec,    'unite' => 'Lot',   'prix' => 3500,  'stock' => 50],
            ['nom' => 'Resistances 10k Ohm (Lot 500)',      'key' => 'RESI-10K', 'cat' => $catElec,    'unite' => 'Lot',   'prix' => 1200,  'stock' => 100],
            ['nom' => 'Carte Circuit Imprime (PCB vierge)', 'key' => 'PCB-VRG',  'cat' => $catElec,    'unite' => 'Piece', 'prix' => 8500,  'stock' => 200],
            ['nom' => 'Transformateur 12V-5A',              'key' => 'TRF-12V',  'cat' => $catElec,    'unite' => 'Piece', 'prix' => 15000, 'stock' => 80],
            ['nom' => 'Bois Teck Seche (Planche 2m)',       'key' => 'BOIS-TCK', 'cat' => $catBois,    'unite' => 'Piece', 'prix' => 18000, 'stock' => 120],
            ['nom' => 'Contreplaques 18mm (240x120)',       'key' => 'CPL-18MM', 'cat' => $catBois,    'unite' => 'Panel', 'prix' => 22000, 'stock' => 80],
            ['nom' => 'Colle a Bois Pro (Bidon 5L)',        'key' => 'COL-BOIS', 'cat' => $catBois,    'unite' => 'Bidon', 'prix' => 9500,  'stock' => 40],
            ['nom' => 'Vernis Laque Transparent (1L)',      'key' => 'VRN-LAQ',  'cat' => $catBois,    'unite' => 'L',     'prix' => 6500,  'stock' => 60],
        ];

        $result = [];
        foreach ($list as $d) {
            $p = Produit::firstOrCreate(
                ['entreprise_id' => $ent->id, 'nom' => $d['nom']],
                [
                    'type'         => 'matiere_premiere',
                    'prix_achat'   => $d['prix'],
                    'prix_vente'   => 0,
                    'taux_tva'     => 0,
                    'categorie_id' => $d['cat']->id,
                    'unite'        => $d['unite'],
                    'compte_achat' => '602100',
                    'statut'       => 'actif',
                    'photo'        => $this->photo($d['key']),
                ]
            );
            Stock::updateOrCreate(
                ['produit_id' => $p->id, 'point_de_vente_id' => $pdv->id],
                ['quantite_disponible' => $d['stock'], 'stock_minimum' => (int)($d['stock'] * 0.1), 'stock_maximum' => $d['stock'] * 5]
            );
            $result[$d['key']] = $p;
        }
        return $result;
    }

    private function creerRecettes(Entreprise $ent, PointDeVente $pdv, array $mp, $catMetal, $catPlastic, $catElec, $catBois): void
    {
        $recettes = [
            [
                'nom' => 'Cadre Aluminium Soude Standard', 'pkey' => 'CAD-ALU', 'cat' => $catMetal,
                'prix_achat' => 45000, 'prix_vente' => 72000, 'unite' => 'Piece', 'stock' => 30,
                'desc' => 'Cadre aluminium soude pour structure industrielle legere.',
                'ing' => [['ALU-LIN', 4.5, 'Kg'], ['ZIN-PUR', 0.5, 'Kg']],
                'ordres' => [['OP-ALU-001', 20, 'Termine', -7], ['OP-ALU-002', 15, 'En cours', 0], ['OP-ALU-003', 25, 'Planifie', 5]],
            ],
            [
                'nom' => 'Poutre Acier Inoxydable 3m', 'pkey' => 'PTR-ACR', 'cat' => $catMetal,
                'prix_achat' => 85000, 'prix_vente' => 125000, 'unite' => 'Piece', 'stock' => 15,
                'desc' => 'Poutre acier inoxydable 304 pour construction marine et alimentaire.',
                'ing' => [['ACR-INX', 12, 'Kg'], ['ZIN-PUR', 1, 'Kg']],
                'ordres' => [['OP-ACR-001', 10, 'Termine', -10], ['OP-ACR-002', 8, 'En cours', 0]],
            ],
            [
                'nom' => 'Cable Electrique Cuivre (Rouleau 100m)', 'pkey' => 'CAB-CUI', 'cat' => $catMetal,
                'prix_achat' => 62000, 'prix_vente' => 95000, 'unite' => 'Rouleau', 'stock' => 50,
                'desc' => 'Cable electrique conducteur cuivre electrolytique, gaine PVC.',
                'ing' => [['CUI-FIL', 6, 'Kg'], ['PVC-GRN', 2, 'Kg']],
                'ordres' => [['OP-CAB-001', 40, 'Termine', -5], ['OP-CAB-002', 30, 'Brouillon', 3]],
            ],
            [
                'nom' => 'Tuyau PVC Rigide DN110 (Barre 3m)', 'pkey' => 'TUY-PVC', 'cat' => $catPlastic,
                'prix_achat' => 8500, 'prix_vente' => 14000, 'unite' => 'Barre', 'stock' => 200,
                'desc' => 'Tuyau PVC rigide pour canalisation eau potable et assainissement.',
                'ing' => [['PVC-GRN', 2.8, 'Kg'], ['CAO-NAT', 0.2, 'Kg']],
                'ordres' => [['OP-PVC-001', 150, 'Termine', -14], ['OP-PVC-002', 200, 'En cours', 0], ['OP-PVC-003', 100, 'Planifie', 7]],
            ],
            [
                'nom' => 'Bac HDPE Anti-Corrosion 200L', 'pkey' => 'BAC-HDPE', 'cat' => $catPlastic,
                'prix_achat' => 38000, 'prix_vente' => 58000, 'unite' => 'Piece', 'stock' => 25,
                'desc' => 'Bac polyethylene haute densite anti-corrosion pour produits chimiques.',
                'ing' => [['HDPE-FEU', 8, 'm2'], ['RES-EPX', 0.5, 'L'], ['PVC-GRN', 1, 'Kg']],
                'ordres' => [['OP-BAC-001', 20, 'Termine', -20], ['OP-BAC-002', 10, 'Brouillon', 10]],
            ],
            [
                'nom' => 'Regulateur de Tension 12V-5A', 'pkey' => 'REG-12V', 'cat' => $catElec,
                'prix_achat' => 35000, 'prix_vente' => 55000, 'unite' => 'Piece', 'stock' => 40,
                'desc' => 'Regulateur de tension industriel 12V/5A sur PCB avec boitier aluminium.',
                'ing' => [['PCB-VRG', 1, 'Piece'], ['TRF-12V', 1, 'Piece'], ['COND-100', 0.02, 'Lot'], ['RESI-10K', 0.01, 'Lot'], ['ALU-LIN', 0.1, 'Kg']],
                'ordres' => [['OP-REG-001', 30, 'Termine', -8], ['OP-REG-002', 25, 'En cours', 0], ['OP-REG-003', 20, 'Planifie', 14]],
            ],
            [
                'nom' => 'Module Capteur Industriel IoT', 'pkey' => 'MOD-IOT', 'cat' => $catElec,
                'prix_achat' => 48000, 'prix_vente' => 78000, 'unite' => 'Piece', 'stock' => 20,
                'desc' => 'Module capteur multi-parametre pour industrie 4.0 (temperature, pression, humidite).',
                'ing' => [['PCB-VRG', 1, 'Piece'], ['COND-100', 0.05, 'Lot'], ['RESI-10K', 0.03, 'Lot'], ['CUI-FIL', 0.05, 'Kg'], ['RES-EPX', 0.1, 'L']],
                'ordres' => [['OP-IOT-001', 15, 'Termine', -12], ['OP-IOT-002', 10, 'Brouillon', 7]],
            ],
            [
                'nom' => 'Bureau Teck Massif 160x80cm', 'pkey' => 'BUR-TCK', 'cat' => $catBois,
                'prix_achat' => 185000, 'prix_vente' => 295000, 'unite' => 'Piece', 'stock' => 8,
                'desc' => 'Bureau en teck massif seche, finition vernis laque, 160x80 cm.',
                'ing' => [['BOIS-TCK', 6, 'Piece'], ['CPL-18MM', 2, 'Panel'], ['COL-BOIS', 0.5, 'Bidon'], ['VRN-LAQ', 1.5, 'L']],
                'ordres' => [['OP-BUR-001', 5, 'Termine', -15], ['OP-BUR-002', 5, 'En cours', 0], ['OP-BUR-003', 8, 'Planifie', 21]],
            ],
            [
                'nom' => 'Etagere Contreplaque 5 Niveaux', 'pkey' => 'ETA-CPL', 'cat' => $catBois,
                'prix_achat' => 75000, 'prix_vente' => 120000, 'unite' => 'Piece', 'stock' => 15,
                'desc' => 'Etagere industrielle 5 niveaux en contreplaque 18mm, vernie, charge max 50kg/niveau.',
                'ing' => [['CPL-18MM', 6, 'Panel'], ['COL-BOIS', 0.3, 'Bidon'], ['VRN-LAQ', 2, 'L']],
                'ordres' => [['OP-ETA-001', 10, 'Termine', -18], ['OP-ETA-002', 8, 'Brouillon', 5]],
            ],
            [
                'nom' => 'Panneau Composite Epoxy-Metal (1m2)', 'pkey' => 'PAN-EPX', 'cat' => $catPlastic,
                'prix_achat' => 52000, 'prix_vente' => 84000, 'unite' => 'm2', 'stock' => 35,
                'desc' => 'Panneau composite resine epoxy + renfort aluminium pour facades industrielles.',
                'ing' => [['RES-EPX', 1.2, 'L'], ['HDPE-FEU', 1.1, 'm2'], ['ALU-LIN', 0.8, 'Kg'], ['CAO-NAT', 0.3, 'Kg']],
                'ordres' => [['OP-PAN-001', 25, 'Termine', -6], ['OP-PAN-002', 20, 'En cours', 0], ['OP-PAN-003', 30, 'Planifie', 10]],
            ],
        ];

        foreach ($recettes as $r) {
            $pf = Produit::firstOrCreate(
                ['entreprise_id' => $ent->id, 'nom' => $r['nom']],
                [
                    'type'         => 'produit_fini',
                    'prix_achat'   => $r['prix_achat'],
                    'prix_vente'   => $r['prix_vente'],
                    'taux_tva'     => 18,
                    'categorie_id' => $r['cat']->id,
                    'unite'        => $r['unite'],
                    'compte_vente' => '702100',
                    'compte_achat' => '711000',
                    'statut'       => 'actif',
                    'photo'        => $this->photo($r['pkey']),
                ]
            );

            Stock::updateOrCreate(
                ['produit_id' => $pf->id, 'point_de_vente_id' => $pdv->id],
                ['quantite_disponible' => $r['stock'], 'stock_minimum' => max(2, (int)($r['stock'] * 0.2)), 'stock_maximum' => $r['stock'] * 10]
            );

            $fiche = FicheTechnique::firstOrCreate(
                ['entreprise_id' => $ent->id, 'produit_fini_id' => $pf->id],
                ['description' => $r['desc']]
            );

            foreach ($r['ing'] as [$mpKey, $qte, $unite]) {
                if (!isset($mp[$mpKey])) continue;
                FicheTechniqueDetail::updateOrCreate(
                    ['fiche_technique_id' => $fiche->id, 'ingredient_id' => $mp[$mpKey]->id],
                    ['quantite' => $qte, 'unite' => $unite]
                );
            }

            foreach ($r['ordres'] as [$code, $qte, $statut, $jours]) {
                $codeOrdre = $code . '-E' . $ent->id;
                OrdreProduction::firstOrCreate(
                    ['entreprise_id' => $ent->id, 'code_ordre' => $codeOrdre],
                    [
                        'point_de_vente_id' => $pdv->id,
                        'produit_fini_id'   => $pf->id,
                        'quantite_cible'    => $qte,
                        'statut'            => $statut,
                        'date_production'   => now()->addDays($jours)->toDateString(),
                    ]
                );
            }
        }
    }

    private function photo(string $key): string
    {
        $map = [
            'ALU-LIN'  => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'ACR-INX'  => 'https://images.unsplash.com/photo-1586864387967-d02ef85d93e8?w=400',
            'CUI-FIL'  => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?w=400',
            'ZIN-PUR'  => 'https://images.unsplash.com/photo-1604079628040-94301bb21b91?w=400',
            'PVC-GRN'  => 'https://images.unsplash.com/photo-1519735777090-ec97162dc266?w=400',
            'HDPE-FEU' => 'https://images.unsplash.com/photo-1616400619175-5beda3a17896?w=400',
            'RES-EPX'  => 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=400',
            'CAO-NAT'  => 'https://images.unsplash.com/photo-1574263867128-a3d5c1b1deae?w=400',
            'COND-100' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400',
            'RESI-10K' => 'https://images.unsplash.com/photo-1601524909162-ae8725290836?w=400',
            'PCB-VRG'  => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400',
            'TRF-12V'  => 'https://images.unsplash.com/photo-1497436072909-60f360e1d4b1?w=400',
            'BOIS-TCK' => 'https://images.unsplash.com/photo-1541123437800-1bb1317badc2?w=400',
            'CPL-18MM' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400',
            'COL-BOIS' => 'https://images.unsplash.com/photo-1572635148818-ef6fd45eb394?w=400',
            'VRN-LAQ'  => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'CAD-ALU'  => 'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=400',
            'PTR-ACR'  => 'https://images.unsplash.com/photo-1581094794329-c8112a89af12?w=400',
            'CAB-CUI'  => 'https://images.unsplash.com/photo-1586864387967-d02ef85d93e8?w=400',
            'TUY-PVC'  => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'BAC-HDPE' => 'https://images.unsplash.com/photo-1616400619175-5beda3a17896?w=400',
            'REG-12V'  => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400',
            'MOD-IOT'  => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400',
            'BUR-TCK'  => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=400',
            'ETA-CPL'  => 'https://images.unsplash.com/photo-1568123382-da7fd498a71a?w=400',
            'PAN-EPX'  => 'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=400',
        ];
        return $map[$key] ?? 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400';
    }
}
