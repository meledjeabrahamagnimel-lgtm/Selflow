<?php

namespace App\Modules\Admin\Regles;

/**
 * Les étapes de la création d'un compte — **une seule définition**.
 *
 * Deux écrans créent une entreprise : `/inscription`, où elle s'inscrit
 * elle-même, et l'écran du superadministrateur, où on l'inscrit pour elle.
 * Ils demandaient des choses différentes, dans un ordre différent, avec des
 * champs différents — au point que le superadministrateur avait oublié le mot
 * de passe du gérant, si bien qu'aucun compte créé par lui n'était utilisable.
 *
 * Les étapes vivent donc ici, et les deux écrans les lisent. Ajouter un champ,
 * c'est le poser une fois.
 *
 * ## Ce qui est obligatoire, et ce qui ne l'est pas
 *
 * **Deux étapes seulement bloquent la création** : l'entreprise a besoin d'un
 * nom, et il lui faut un responsable qui puisse se connecter. Tout le reste —
 * la situation fiscale, l'accès à l'espace FNE, le domaine d'activité — se
 * renseigne aussi bien après, une fois dans l'application, et la retarder
 * n'apporte rien : un formulaire de quarante champs se remplit mal, ou pas.
 *
 * Ce qui reste à compléter n'est pas oublié pour autant : les paramètres de
 * l'entreprise signalent une inscription incomplète, et le parcours de
 * configuration reprend là où on l'a laissé.
 */
class EtapesCreation
{
    /**
     * @return array<int, array{cle: string, titre: string, resume: string, obligatoire: bool, icone: string}>
     */
    public static function toutes(): array
    {
        return [
            [
                'cle'         => 'entreprise',
                'titre'       => "L'entreprise",
                'resume'      => 'Son nom, sa forme juridique, son régime d\'imposition.',
                'obligatoire' => true,
                'icone'       => 'ti-building',
            ],
            [
                'cle'         => 'responsable',
                'titre'       => 'Le responsable',
                'resume'      => 'Qui administrera l\'espace, et avec quel mot de passe.',
                'obligatoire' => true,
                'icone'       => 'ti-user',
            ],
            [
                'cle'         => 'fne',
                'titre'       => 'La facture normalisée',
                'resume'      => 'Un compte FNE existe déjà, ou il faut en ouvrir un.',
                'obligatoire' => false,
                'icone'       => 'ti-file-invoice',
            ],
            [
                'cle'         => 'domaine',
                'titre'       => "Le domaine d'activité",
                'resume'      => 'Ce qui décide des modules et du catalogue proposés.',
                'obligatoire' => false,
                'icone'       => 'ti-category',
            ],
        ];
    }

    /** Les étapes sans lesquelles rien ne se crée. */
    public static function obligatoires(): array
    {
        return array_values(array_filter(self::toutes(), fn ($e) => $e['obligatoire']));
    }

    /** Celles qui peuvent attendre l'entrée dans l'application. */
    public static function facultatives(): array
    {
        return array_values(array_filter(self::toutes(), fn ($e) => !$e['obligatoire']));
    }

    public static function nombre(): int
    {
        return count(self::toutes());
    }
}
