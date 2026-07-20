@extends('admin::gabarits.application')
@section('titre', ($mode === 'creation' ? 'Nouvelle Recette' : 'Modifier la Recette'))
@section('topbar_titre', 'Production — Fiche Technique')

@section('styles')
<style>
    .ingredient-row {
        background: #f8fafc;
        border: 0.5px solid var(--border);
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 12px;
        display: grid;
        grid-template-columns: 3fr 1.5fr 1fr auto;
        gap: 16px;
        align-items: center;
        transition: all 0.2s;
    }
    .ingredient-row:hover {
        border-color: var(--primary-l);
        background: #f1f5f9;
    }
    .btn-delete-row {
        background: none;
        border: none;
        color: var(--danger);
        font-size: 16px;
        cursor: pointer;
        padding: 6px;
        border-radius: 4px;
        transition: background 0.15s;
    }
    .btn-delete-row:hover {
        background: rgba(239,68,68,0.1);
    }
</style>
@endsection

@section('contenu')
<div class="page-header">
    <div>
        <h1>
            <i class="fas fa-flask" style="color:var(--primary); margin-right:8px;"></i>
            {{ $mode === 'creation' ? 'Nouvelle Recette' : 'Modifier la Recette' }}
        </h1>
        <p>Associez des matières premières et ingrédients au produit fabriqué.</p>
    </div>
    <a href="{{ route('admin.production.fiches_techniques.index') }}" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
</div>

{{-- Zone d'erreurs --}}
@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:20px;">
        <ul style="margin:0; padding-left:20px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $mode === 'creation' ? route('admin.production.fiches_techniques.enregistrer') : route('admin.production.fiches_techniques.modifier.enregistrer', $fiche) }}">
    @csrf
    @if($mode === 'edition')
        @method('PUT')
    @endif

    <div class="grid-3-1">
        
        {{-- Section Principale --}}
        <div>
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2>Composition et Ingrédients</h2>
                </div>
                <div class="card-body">
                    <div id="ingredients-container">
                        @php
                            $details = old('ingredients', $fiche->details ?? []);
                        @endphp

                        @if(count($details) > 0)
                            @foreach($details as $index => $detail)
                                @php
                                    $detailObj = is_array($detail) ? (object)$detail : $detail;
                                @endphp
                                <div class="ingredient-row" id="row-{{ $index }}">
                                    <div>
                                        <label class="form-label" style="font-size:10px;">Ingrédient / Matière Première</label>
                                        <select name="ingredients[{{ $index }}][ingredient_id]" class="form-control" required>
                                            <option value="">Sélectionner un ingrédient...</option>
                                            @foreach($ingredients as $ing)
                                                <option value="{{ $ing->id }}" {{ $ing->id == $detailObj->ingredient_id ? 'selected' : '' }}>
                                                    {{ $ing->nom }} ({{ $ing->reference }}) — Stock : {{ $ing->stock_actuel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label" style="font-size:10px;">Quantité requise</label>
                                        <input type="number" step="0.0001" min="0.0001" name="ingredients[{{ $index }}][quantite]" value="{{ $detailObj->quantite }}" placeholder="Ex: 0.350" class="form-control" required>
                                    </div>
                                    <div>
                                        <label class="form-label" style="font-size:10px;">Unité</label>
                                        <select name="ingredients[{{ $index }}][unite]" class="form-control" required>
                                            <option value="kg" {{ $detailObj->unite == 'kg' ? 'selected' : '' }}>kg</option>
                                            <option value="g" {{ $detailObj->unite == 'g' ? 'selected' : '' }}>g</option>
                                            <option value="l" {{ $detailObj->unite == 'l' ? 'selected' : '' }}>l</option>
                                            <option value="ml" {{ $detailObj->unite == 'ml' ? 'selected' : '' }}>ml</option>
                                            <option value="Unité" {{ $detailObj->unite == 'Unité' ? 'selected' : '' }}>Unité</option>
                                        </select>
                                    </div>
                                    <div style="padding-top:20px;">
                                        <button type="button" onclick="supprimerLigne({{ $index }})" class="btn-delete-row" title="Supprimer cet ingrédient">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            {{-- Ligne par défaut pour démarrage --}}
                            <div class="ingredient-row" id="row-0">
                                <div>
                                    <label class="form-label" style="font-size:10px;">Ingrédient / Matière Première</label>
                                    <select name="ingredients[0][ingredient_id]" class="form-control" required>
                                        <option value="">Sélectionner un ingrédient...</option>
                                        @foreach($ingredients as $ing)
                                            <option value="{{ $ing->id }}">
                                                {{ $ing->nom }} ({{ $ing->reference }}) — Stock : {{ $ing->stock_actuel }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" style="font-size:10px;">Quantité requise</label>
                                    <input type="number" step="0.0001" min="0.0001" name="ingredients[0][quantite]" placeholder="Ex: 0.350" class="form-control" required>
                                </div>
                                <div>
                                    <label class="form-label" style="font-size:10px;">Unité</label>
                                    <select name="ingredients[0][unite]" class="form-control" required>
                                        <option value="kg">kg</option>
                                        <option value="g">g</option>
                                        <option value="l">l</option>
                                        <option value="ml">ml</option>
                                        <option value="Unité" selected>Unité</option>
                                    </select>
                                </div>
                                <div style="padding-top:20px;">
                                    <button type="button" onclick="supprimerLigne(0)" class="btn-delete-row" title="Supprimer cet ingrédient">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>

                    <button type="button" onclick="ajouterLigne()" class="btn btn-outline" style="margin-top:8px;">
                        <i class="fas fa-plus"></i> Ajouter un ingrédient
                    </button>
                </div>
            </div>
        </div>

        {{-- Barre latérale de configuration --}}
        <div>
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2>Produit Fini</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label class="form-label" style="margin-bottom:0;">Produit fabriqué</label>
                            @if($mode === 'creation')
                                <label style="font-size:11.5px; font-weight:600; cursor:pointer; color:var(--primary); display:flex; align-items:center; gap:4px; margin-bottom:0;">
                                    <input type="checkbox" id="chkNouveauProduit" onchange="toggleNouveauProduit()" style="cursor:pointer;"> Saisie libre (Nouveau)
                                </label>
                            @endif
                        </div>
                        @if($mode === 'creation')
                            <div id="selectProduitContainer">
                                <select name="produit_fini_id" id="produitFiniSelect" class="form-control" required>
                                    <option value="">Choisir un produit fini...</option>
                                    @foreach($produitsFini as $pf)
                                        <option value="{{ $pf->id }}" {{ old('produit_fini_id') == $pf->id ? 'selected' : '' }}>
                                            {{ $pf->nom }} ({{ $pf->reference }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="inputProduitContainer" style="display:none;">
                                <input type="text" name="nouveau_produit_fini_nom" id="nouveauProduitInput" class="form-control" placeholder="Nom du nouveau produit fini à créer...">
                            </div>
                        @else
                            <input type="text" class="form-control" value="{{ $fiche->produitFini->nom }} ({{ $fiche->produitFini->reference }})" disabled>
                            <input type="hidden" name="produit_fini_id" value="{{ $fiche->produit_fini_id }}">
                        @endif
                    </div>

                    <div class="form-group" style="margin-top:16px;">
                        <label class="form-label">Notes de fabrication</label>
                        <textarea name="description" rows="5" class="form-control" placeholder="Ajoutez des détails sur la recette, le temps de cuisson, ou des consignes pour l'atelier...">{{ old('description', $fiche->description) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; display:flex; justify-content:center; margin-top:20px;">
                        <i class="fas fa-save"></i> Enregistrer la Recette
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

<script>
    let rowIndex = {{ count($details) > 0 ? count($details) : 1 }};
    const ingredientsData = @json($ingredients);

    function ajouterLigne() {
        const container = document.getElementById('ingredients-container');
        
        let selectOptions = '<option value="">Sélectionner un ingrédient...</option>';
        ingredientsData.forEach(ing => {
            selectOptions += `<option value="${ing.id}">${ing.nom} (${ing.reference}) — Stock : ${ing.stock_actuel}</option>`;
        });

        const newRow = document.createElement('div');
        newRow.className = 'ingredient-row';
        newRow.id = `row-${rowIndex}`;
        newRow.innerHTML = `
            <div>
                <label class="form-label" style="font-size:10px;">Ingrédient / Matière Première</label>
                <select name="ingredients[${rowIndex}][ingredient_id]" class="form-control" required>
                    ${selectOptions}
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:10px;">Quantité requise</label>
                <input type="number" step="0.0001" min="0.0001" name="ingredients[${rowIndex}][quantite]" placeholder="Ex: 0.350" class="form-control" required>
            </div>
            <div>
                <label class="form-label" style="font-size:10px;">Unité</label>
                <select name="ingredients[${rowIndex}][unite]" class="form-control" required>
                    <option value="kg">kg</option>
                    <option value="g">g</option>
                    <option value="l">l</option>
                    <option value="ml">ml</option>
                    <option value="Unité" selected>Unité</option>
                </select>
            </div>
            <div style="padding-top:20px;">
                <button type="button" onclick="supprimerLigne(${rowIndex})" class="btn-delete-row" title="Supprimer cet ingrédient">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;

        container.appendChild(newRow);
        rowIndex++;
    }

    function supprimerLigne(index) {
        const row = document.getElementById(`row-${index}`);
        if (row) {
            // S'il reste au moins une ligne, on supprime, sinon on alerte
            const allRows = document.querySelectorAll('.ingredient-row');
            if (allRows.length > 1) {
                row.remove();
            } else {
                alert('Une fiche technique doit comporter au moins un ingrédient.');
            }
        }
    }

    function toggleNouveauProduit() {
        const chk = document.getElementById('chkNouveauProduit');
        const selContainer = document.getElementById('selectProduitContainer');
        const inpContainer = document.getElementById('inputProduitContainer');
        const sel = document.getElementById('produitFiniSelect');
        const inp = document.getElementById('nouveauProduitInput');
        
        if (chk && chk.checked) {
            if (selContainer) selContainer.style.display = 'none';
            if (sel) {
                sel.required = false;
                sel.value = '';
            }
            if (inpContainer) inpContainer.style.display = 'block';
            if (inp) {
                inp.required = true;
                inp.focus();
            }
        } else {
            if (selContainer) selContainer.style.display = 'block';
            if (sel) sel.required = true;
            if (inpContainer) inpContainer.style.display = 'none';
            if (inp) {
                inp.required = false;
                inp.value = '';
            }
        }
    }
</script>
@endsection
