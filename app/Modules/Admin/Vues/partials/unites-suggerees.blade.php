{{--
    Suggestions d'unités pour les champs « Unité ».

    Un `datalist` et non un `select` : le champ reste libre. Fermer la liste
    obligerait à prévoir tous les métiers — le tâcheron facture au « voyage », le
    vétérinaire à la « tête », l'école à l'« élève » — et le premier
    conditionnement absent bloquerait la fiche. La liste sert à retrouver
    l'orthographe déjà employée, pas à contraindre : c'est ce qui évite d'avoir
    « pièce », « piece » et « Pièce » comme trois unités différentes.

    Les unités déjà saisies par l'entreprise passent en premier — ce sont les
    siennes, et ce sont celles qu'elle cherche.
--}}
@php
    $unitesDeLEntreprise = \App\Modules\Admin\Modeles\Produit::query()
        ->when(auth()->user()?->entreprise_id, fn ($q, $id) => $q->where('entreprise_id', $id))
        ->whereNotNull('unite')
        ->where('unite', '!=', '')
        ->distinct()
        ->orderBy('unite')
        ->pluck('unite')
        ->all();

    // La comparaison se fait sans accent ni casse : « Pièce » déjà en base ne
    // doit pas réapparaître sous « pièce » dans les suggestions.
    $vues = [];
    $unites = [];

    foreach (array_merge($unitesDeLEntreprise, \App\Modules\Admin\Modeles\Produit::UNITES_COURANTES) as $unite) {
        $cle = mb_strtolower(trim($unite));

        if ($cle === '' || isset($vues[$cle])) {
            continue;
        }

        $vues[$cle] = true;
        $unites[] = $unite;
    }
@endphp

<datalist id="unites-suggerees">
    @foreach($unites as $unite)
        <option value="{{ $unite }}"></option>
    @endforeach
</datalist>
