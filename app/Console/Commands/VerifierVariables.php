<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Variables lues sans avoir jamais été écrites.
 *
 * PHP ne signale cette faute qu'à l'exécution, au moment où l'utilisateur
 * clique. Un `$dgiFiltre` déclaré et relu `$dgi_filtre` a ainsi renvoyé une
 * erreur 500 sur le filtre des factures d'achat, en passant la revue, les tests
 * et `php -l` sans encombre.
 *
 * Cette commande relit le projet sur son arbre syntaxique et signale, portée par
 * portée, toute variable lue sans affectation préalable.
 *
 * À lancer avant chaque commit : `php artisan verifier:variables`
 */
class VerifierVariables extends Command
{
    protected $signature = 'verifier:variables
                            {chemin=app : Dossier à analyser}';

    protected $description = 'Signale les variables lues sans avoir jamais été écrites';

    public function handle(): int
    {
        $racine = base_path($this->argument('chemin'));

        if (!is_dir($racine)) {
            $this->error("Dossier introuvable : {$racine}");
            return self::FAILURE;
        }

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $signales = [];

        foreach ($this->fichiers($racine) as $fichier) {
            try {
                $ast = $parser->parse(file_get_contents($fichier));
            } catch (\Throwable $e) {
                $this->warn("Analyse impossible : {$fichier}");
                continue;
            }
            if (!$ast) {
                continue;
            }

            // Les fonctions flèches capturent automatiquement leur portée
            // parente : on ne les traite pas comme des portées distinctes.
            $portees = $finder->find($ast, fn (Node $n) => (
                $n instanceof Node\Stmt\ClassMethod
                || $n instanceof Node\Stmt\Function_
                || $n instanceof Node\Expr\Closure
            ) && $n->stmts !== null);

            foreach ($portees as $portee) {
                $propres = array_merge(
                    $portee->params,
                    $portee->stmts,
                    $portee instanceof Node\Expr\Closure ? $portee->uses : []
                );

                $ecrits = $this->nomsEcrits($finder, $propres);

                // Une closure imbriquée peut écrire dans la portée parente par
                // référence : ses écritures comptent ici aussi.
                foreach ($finder->find($portee->stmts, fn (Node $n) => $n instanceof Node\Expr\Closure) as $closure) {
                    $ecrits += $this->nomsEcrits($finder, [$closure]);
                }

                foreach ($finder->findInstanceOf($propres, Node\Expr\Variable::class) as $variable) {
                    if (!is_string($variable->name) || $variable->name === 'this') {
                        continue;
                    }
                    if (isset($ecrits[$variable->name])) {
                        continue;
                    }

                    $relatif = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $fichier);
                    $signales[] = [$relatif, $variable->getStartLine(), '$' . $variable->name];
                }
            }
        }

        if (empty($signales)) {
            $this->info('Aucune variable lue sans avoir été écrite.');
            return self::SUCCESS;
        }

        $this->error(count($signales) . ' lecture(s) sans écriture :');
        $this->table(['Fichier', 'Ligne', 'Variable'], $signales);
        $this->newLine();
        $this->line('Les fonctions natives qui écrivent par référence — preg_match, sscanf, similar_text —');
        $this->line('produisent de faux positifs : leur paramètre de sortie apparaît ici.');

        return self::FAILURE;
    }

    /**
     * Fichiers PHP du dossier, hors dépendances.
     *
     * @return iterable<string>
     */
    private function fichiers(string $racine): iterable
    {
        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            $chemin = $fichier->getPathname();
            if ($fichier->getExtension() === 'php' && !str_contains($chemin, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                yield $chemin;
            }
        }
    }

    /**
     * Noms de variables écrits dans ces nœuds.
     *
     * @param  array<Node>  $noeuds
     * @return array<string, true>
     */
    private function nomsEcrits(NodeFinder $finder, array $noeuds): array
    {
        $noms = [];

        $ajouter = function (?Node $n) use (&$noms, &$ajouter): void {
            if ($n instanceof Node\Expr\Variable && is_string($n->name)) {
                $noms[$n->name] = true;
            }
            // Destructurations : [$a, $b] = …, list($a) = …, $t['k'] = …
            if ($n instanceof Node\Expr\ArrayDimFetch) {
                $ajouter($n->var);
            }
            if ($n instanceof Node\Expr\List_ || $n instanceof Node\Expr\Array_) {
                foreach ($n->items as $item) {
                    if ($item instanceof Node\ArrayItem) {
                        $ajouter($item->value);
                        $ajouter($item->key);
                    }
                }
            }
        };

        foreach ($finder->find($noeuds, fn (Node $n) => true) as $n) {
            match (true) {
                $n instanceof Node\Param            => $ajouter($n->var),
                $n instanceof Node\Expr\Assign,
                $n instanceof Node\Expr\AssignRef,
                $n instanceof Node\Expr\AssignOp    => $ajouter($n->var),
                $n instanceof Node\Expr\ClosureUse  => $ajouter($n->var),
                $n instanceof Node\Expr\PreInc,
                $n instanceof Node\Expr\PostInc,
                $n instanceof Node\Expr\PreDec,
                $n instanceof Node\Expr\PostDec     => $ajouter($n->var),
                $n instanceof Node\Stmt\Catch_      => $ajouter($n->var),
                $n instanceof Node\Stmt\Foreach_    => [$ajouter($n->keyVar), $ajouter($n->valueVar)],
                $n instanceof Node\Stmt\Static_     => array_map(fn ($v) => $ajouter($v->var), $n->vars),
                $n instanceof Node\Stmt\Global_,
                $n instanceof Node\Stmt\Unset_      => array_map($ajouter, $n->vars),
                // Sortie par référence des fonctions natives courantes.
                $n instanceof Node\Expr\FuncCall
                    && $n->name instanceof Node\Name
                    && isset(self::SORTIES_PAR_REFERENCE[$n->name->toString()])
                    => $ajouter($n->args[self::SORTIES_PAR_REFERENCE[$n->name->toString()]]->value ?? null),
                default => null,
            };
        }

        return $noms;
    }

    /**
     * Fonctions natives dont un argument est une sortie : nom => rang de l'argument.
     */
    private const SORTIES_PAR_REFERENCE = [
        'preg_match'     => 2,
        'preg_match_all' => 2,
        'sscanf'         => 2,
        'similar_text'   => 2,
        'str_replace'    => 3,
        'preg_replace'   => 4,
    ];
}
