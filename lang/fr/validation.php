<?php

/**
 * Messages de validation en français.
 *
 * Sans ce fichier, Laravel affichait la clé brute — « validation.max.string » —
 * à l'écran dès qu'une règle échouait sans message dédié.
 */
return [
    'accepted'  => 'Le champ :attribute doit être accepté.',
    'after'     => 'Le champ :attribute doit être une date postérieure au :date.',
    'array'     => 'Le champ :attribute doit être un tableau.',
    'before'    => 'Le champ :attribute doit être une date antérieure au :date.',
    'boolean'   => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'date'      => 'Le champ :attribute n\'est pas une date valide.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'email'     => 'Le champ :attribute doit être une adresse e-mail valide.',
    'exists'    => 'La valeur sélectionnée pour :attribute est invalide.',
    'gt'        => [
        'numeric' => 'La valeur de :attribute doit être supérieure à :value.',
        'string'  => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],
    'image'   => 'Le champ :attribute doit être une image.',
    'in'      => 'La valeur sélectionnée pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max'     => [
        'array'   => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
        'file'    => 'Le fichier :attribute ne peut pas dépasser :max kilo-octets.',
        'numeric' => 'La valeur de :attribute ne peut pas dépasser :max.',
        'string'  => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'mimes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'min'   => [
        'array'   => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file'    => 'Le fichier :attribute doit peser au moins :min kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être au moins :min.',
        'string'  => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric'          => 'Le champ :attribute doit être un nombre.',
    'regex'            => 'Le format du champ :attribute est invalide.',
    'required'         => 'Le champ :attribute est obligatoire.',
    'required_if'      => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_with'    => 'Le champ :attribute est obligatoire quand :values est présent.',
    'size'             => [
        'array'   => 'Le champ :attribute doit contenir :size éléments.',
        'file'    => 'Le fichier :attribute doit peser :size kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être :size.',
        'string'  => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'attributes' => [
        'pied_de_page_facture'    => 'pied de page des factures',
        'facture_autres_mentions' => 'autres mentions légales',
        'remise_taux'             => 'remise',
        'batch_size'              => 'nombre de documents à normaliser',
        'numero_rne'              => 'numéro du reçu',
        'ncc'                     => 'NCC',
    ],
];
