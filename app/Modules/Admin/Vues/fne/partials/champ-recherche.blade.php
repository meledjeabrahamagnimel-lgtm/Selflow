{{-- Recherche libre du registre, insérée dans la barre de filtres. --}}
<div class="form-group" style="flex:1; min-width:220px;">
    <label>Recherche (n° pièce, n° FNE, tiers)</label>
    <input type="text" id="f-recherche" class="form-control" placeholder="Rechercher..." oninput="page=1;debouncedRafraichir();">
</div>
