<?php

namespace App\Console\Commands;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Produit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pourquoi la photo d'un article ne s'affiche pas.
 *
 * Trois causes possibles, et **aucune ne se distingue à l'écran** :
 *
 *  1. l'article n'a pas de photo — la vignette montre alors l'image d'attente,
 *     qui ressemble à une photo, et le fond de carte reste vide ;
 *  2. la colonne porte un chemin, mais le fichier n'est plus sur le disque —
 *     même symptôme ;
 *  3. le fichier est là, mais `public/storage` n'existe pas : l'adresse rendue
 *     tombe alors en 404 (Not Found — introuvable). La vignette bascule sur
 *     l'image d'attente par son `onerror` ; le fond de carte, qui n'en a pas,
 *     ne laisse rien.
 *
 * Cette commande dit laquelle des trois s'applique, plutôt que de laisser
 * chercher.
 */
class DiagnostiquerPhotos extends Command
{
    protected $signature = 'selflow:photos {--entreprise= : N\'examiner qu\'une entreprise, par son identifiant}';

    protected $description = 'Dire quels articles ont une photo, laquelle manque, et si le lien de stockage est posé';

    public function handle(): int
    {
        $this->comptesRendusDuLien();

        $entreprises = Entreprise::query()
            ->when($this->option('entreprise'), fn ($q) => $q->where('id', $this->option('entreprise')))
            ->orderBy('nom')->get();

        if ($entreprises->isEmpty()) {
            $this->warn('Aucune entreprise.');

            return self::SUCCESS;
        }

        foreach ($entreprises as $entreprise) {
            $this->pourUneEntreprise($entreprise);
        }

        return self::SUCCESS;
    }

    private function comptesRendusDuLien(): void
    {
        $this->newLine();

        if (file_exists(public_path('storage'))) {
            $this->info('✓ Le lien public/storage est posé — les images sont servies directement.');
        } else {
            $this->warn('• Le lien public/storage n\'est pas posé.');
            $this->line('  Les images passent par l\'application, ce qui fonctionne mais coûte plus cher.');
            $this->line('  Pour le poser : php artisan storage:link');
        }

        $this->newLine();
    }

    private function pourUneEntreprise(Entreprise $entreprise): void
    {
        $articles = Produit::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)->orderBy('nom')->get();

        $sans     = $articles->filter(fn ($a) => !$a->photo);
        $absents  = $articles->filter(fn ($a) => $a->photo
            && !str_starts_with($a->photo, 'http')
            && !Storage::disk('public')->exists($a->photo));
        $bonnes   = $articles->count() - $sans->count() - $absents->count();

        $this->line("<options=bold>{$entreprise->nom}</> — {$articles->count()} article(s)");
        $this->line("  photo en place ........ {$bonnes}");
        $this->line("  aucune photo .......... {$sans->count()}");
        $this->line("  fichier introuvable ... {$absents->count()}");

        // Les noms, pas seulement les nombres : c'est en les lisant qu'on
        // reconnaît « tiens, celui-là je croyais lui en avoir mis une ».
        if ($absents->isNotEmpty()) {
            $this->newLine();
            $this->warn('  Ces articles portent un chemin vers un fichier qui n\'existe plus :');
            foreach ($absents->take(20) as $article) {
                $this->line("    · {$article->nom}  →  {$article->photo}");
            }
            if ($absents->count() > 20) {
                $this->line('    · … et ' . ($absents->count() - 20) . ' autre(s)');
            }
        }

        if ($sans->isNotEmpty()) {
            $this->newLine();
            $this->line('  <comment>Sans photo</> (leur carte reste sans fond, c\'est voulu) :');
            foreach ($sans->take(20) as $article) {
                $this->line("    · {$article->nom}");
            }
            if ($sans->count() > 20) {
                $this->line('    · … et ' . ($sans->count() - 20) . ' autre(s)');
            }
        }

        $this->newLine();
    }
}
