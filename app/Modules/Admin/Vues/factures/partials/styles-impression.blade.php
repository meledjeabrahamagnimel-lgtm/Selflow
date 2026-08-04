{{--
    Regles d'impression / export PDF, communes aux documents de vente et d'achat.
    A inclure a l'interieur du bloc <style> de la vue.

    Historique : l'export passait par html2canvas (bibliotheque html2pdf).
    La page etait photographiee, puis l'image collee dans un PDF. Trois defauts
    en decoulaient, insolubles par nature :
      - texte rasterise, donc flou a l'impression ;
      - mise en page calquee sur la largeur de l'ecran et etiree vers l'A4,
        d'ou les documents deformes et coupes ;
      - plusieurs secondes de calcul a chaque clic.

    On confie desormais le rendu au moteur d'impression du navigateur : texte
    vectoriel, pagination A4 native, aucun delai.

    Le bouton « Imprimer / PDF » recopie le document dans un cadre isole (voir
    partials/script-impression) ; ces regles-ci servent a l'impression directe
    de la page, au clavier (Ctrl+P), ou l'interface est encore presente.
--}}
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            overflow: visible !important;
        }

        /* Rien de l'interface ne doit atterrir sur la feuille. */
        .no-print, .controls-card, .sidebar, header, .topbar, .banner-alert,
        .user-dropdown-menu, .sidebar-logo, .sidebar-pdv, nav, footer, .breadcrumb {
            display: none !important;
        }

        .invoice {
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            /* `.invoice` masque son debordement pour arrondir ses coins ; a
               l'impression, ce masquage amputait le tableau. */
            overflow: visible !important;
        }

        /* Un tableau long se poursuit page suivante, mais sans couper de ligne. */
        table { page-break-inside: auto !important; }
        tr, td, th { page-break-inside: avoid !important; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }

        /* Une adresse de verification imprimee n'a pas a etre soulignee en bleu. */
        a { color: inherit !important; text-decoration: none !important; }
    }
