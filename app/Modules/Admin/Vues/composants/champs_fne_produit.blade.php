{{--
    Champs FNE (DGI) d'un produit, partagés par le formulaire de création et
    les formulaires d'édition.

    Variables attendues :
      $cle     — suffixe unique des identifiants ('nouveau' ou l'id du produit)
      $produit — le produit édité, ou null en création
      $regime  — régime d'imposition de l'entreprise (pour l'aide affichée)
--}}
@php
    $produit      = $produit ?? null;
    $regime       = $regime ?? null;
    $codeManuel   = (bool) old('code_tva_manuel', $produit->code_tva_manuel ?? false);
    $codeChoisi   = old('code_tva', $produit->code_tva ?? null);
    $remiseProduit = old('remise_taux', $produit->remise_taux ?? 0);
    $tauxProduit  = (float) ($produit->taux_tva ?? 18);
    $codeAuto     = \App\Modules\Admin\Modeles\Produit::deduireCodeTva($tauxProduit, $regime);
    $taxesProduit = old('taxes_produit', $produit
        ? $produit->taxes->map(fn ($t) => ['nom' => $t->nom, 'taux' => (float) $t->taux])->values()->all()
        : []);
@endphp

<div class="form-group">
    <label class="form-label">Remise par défaut (%)</label>
    <input type="number" name="remise_taux" class="form-control"
           step="0.01" min="0" max="100" value="{{ $remiseProduit }}">
    <small style="color:var(--text-3); font-size:11px;">
        Remise appliquée d'office à ce produit lors d'une vente ou d'un bordereau d'achat.
        Elle reste modifiable ligne par ligne. Transmise à la FNE dans <code>items[].discount</code>.
    </small>
</div>

<div class="form-group">
    <label class="form-label">Régime de TVA <span style="color:var(--danger)">*</span></label>

    {{-- Un seul endroit fixe la TVA du produit : le code DGI porte le taux, il
         n'y a plus de « taux par défaut » saisi à part, qui pouvait diverger du
         code transmis. --}}
    <label style="display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600; color:var(--text-2); cursor:pointer; margin-bottom:8px;">
        <input type="checkbox" name="code_tva_manuel" value="1"
               id="code_tva_manuel_{{ $cle }}"
               onchange="basculerCodeTva('{{ $cle }}')"
               {{ $codeManuel ? 'checked' : '' }}
               style="width:15px; height:15px; cursor:pointer;">
        Choisir le régime manuellement
    </label>

    <div id="code_tva_auto_{{ $cle }}" style="display:{{ $codeManuel ? 'none' : 'block' }}; background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:9px 12px; font-size:12px; color:var(--text-2);">
        <i class="fas fa-wand-magic-sparkles" style="color:var(--primary);"></i>
        Déduit du taux de TVA
        @if($regime)
            et du régime <strong>{{ $regime }}</strong>
        @endif
        — actuellement
        <strong id="apercu_code_tva_{{ $cle }}">{{ $codeAuto }}</strong>
        (<strong id="apercu_taux_tva_{{ $cle }}">{{ rtrim(rtrim(number_format($tauxProduit, 2, ',', ' '), '0'), ',') }}</strong> %).
    </div>

    <div id="code_tva_manuel_container_{{ $cle }}" style="display:{{ $codeManuel ? 'block' : 'none' }};">
        <select name="code_tva" id="code_tva_select_{{ $cle }}" class="form-control"
                onchange="appliquerTauxDuCodeTva('{{ $cle }}')">
            @foreach(\App\Modules\Admin\Modeles\Produit::CODES_TVA as $code => $infos)
                <option value="{{ $code }}" data-taux="{{ $infos['taux'] }}" {{ $codeChoisi === $code ? 'selected' : '' }}>{{ $infos['libelle'] }}</option>
            @endforeach
            <option value="AUTRE" {{ $codeChoisi === 'AUTRE' ? 'selected' : '' }}>Autre taux — saisie libre</option>
        </select>
        <div id="code_tva_libre_{{ $cle }}" style="display:{{ $codeChoisi === 'AUTRE' ? 'block' : 'none' }}; margin-top:8px;">
            <label class="form-label" style="font-size:11px;">Taux de TVA (%)</label>
            <input type="number" id="taux_libre_{{ $cle }}" class="form-control"
                   step="0.01" min="0" max="100" value="{{ $tauxProduit }}"
                   oninput="synchroniserTauxLibre('{{ $cle }}')">
            <small style="color:var(--text-3); font-size:11px;">
                Un taux hors barème DGI est transmis sous le code TVA normal.
            </small>
        </div>
        <small style="color:var(--text-3); font-size:11px;">
            TVAC et TVAD valent toutes deux 0 % : seul ce choix permet de les distinguer.
        </small>
    </div>

    {{-- Le taux réel du produit, alimenté par le régime choisi ci-dessus --}}
    <input type="hidden" name="taux_tva" id="tva_input_{{ $cle }}" value="{{ $tauxProduit }}">
</div>

<div class="form-group" style="grid-column:1/-1;">
    <label class="form-label">Autres taxes appliquées à ce produit</label>
    <div id="taxes_produit_{{ $cle }}" style="display:flex; flex-direction:column; gap:8px;"></div>
    <button type="button" class="btn btn-outline" style="margin-top:8px; font-size:12px;"
            onclick="ajouterTaxeProduit('{{ $cle }}')">
        <i class="fas fa-plus"></i> Ajouter d'autres taxes
    </button>
    <small style="display:block; margin-top:6px; color:var(--text-3); font-size:11px;">
        Taxes spécifiques au produit (GRA, AIRSI…), transmises à la FNE dans
        <code>items[].customTaxes</code>. Taux strictement supérieur à 0 et au plus égal à 100 %.
    </small>
    <script>
        window.taxesProduitInitiales = window.taxesProduitInitiales || {};
        window.taxesProduitInitiales['{{ $cle }}'] = @json($taxesProduit);
    </script>
</div>
