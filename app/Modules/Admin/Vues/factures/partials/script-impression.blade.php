{{--
    Export PDF des documents (vente et achat), a inclure a l'interieur d'un
    bloc <script>. La vue appelante doit definir `nomFichierPdf()`, qui renvoie
    le nom du fichier propose, sans extension.
--}}
/**
 * Export PDF du seul document affiche.
 *
 * La capture d'ecran (html2canvas) est abandonnee : elle rasterisait la page,
 * d'ou un texte flou, une mise en page calquee sur la largeur de la fenetre —
 * donc deformee — et plusieurs secondes d'attente a chaque clic. Le moteur
 * d'impression du navigateur fait le meme travail en vectoriel, instantanement
 * et au format A4 exact. Dans la boite de dialogue, la destination
 * « Enregistrer au format PDF » produit le fichier.
 *
 * Le masquage du reste de la page est fait en JavaScript plutot qu'en CSS :
 * la structure de la page varie d'un ecran a l'autre (admin, caissier,
 * apercu), et une liste de selecteurs finissait toujours par en oublier un.
 * On remonte donc jusqu'au <body> en masquant, a chaque etage, tout ce qui
 * n'est pas sur le chemin du document.
 */
function telechargerPdf() {
    var piece = document.querySelector('.invoice');
    if (!piece) return;

    // Le navigateur propose le titre de l'onglet comme nom de fichier.
    var titreInitial = document.title;
    document.title = (typeof nomFichierPdf === 'function') ? nomFichierPdf() : titreInitial;

    var restaurations = [];
    var noeud = piece;

    while (noeud.parentElement && noeud !== document.body) {
        var parent = noeud.parentElement;

        Array.prototype.forEach.call(parent.children, function (frere) {
            if (frere === noeud) return;
            var ancienAffichage = frere.style.display;
            frere.style.display = 'none';
            restaurations.push(function () { frere.style.display = ancienAffichage; });
        });

        if (parent !== document.body) {
            parent.classList.add('chemin-impression');
            (function (p) {
                restaurations.push(function () { p.classList.remove('chemin-impression'); });
            })(parent);
        }

        noeud = parent;
    }

    var dejaRestaure = false;
    function restaurer() {
        if (dejaRestaure) return;
        dejaRestaure = true;
        restaurations.forEach(function (annuler) { annuler(); });
        document.title = titreInitial;
        window.removeEventListener('afterprint', restaurer);
    }

    window.addEventListener('afterprint', restaurer);
    window.print();

    // Filet de securite : quelques navigateurs n'emettent jamais `afterprint`.
    setTimeout(restaurer, 1000);
}
