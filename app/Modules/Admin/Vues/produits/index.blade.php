@extends('admin::gabarits.application')
@section('titre', 'Catalogue produits')
@section('topbar_titre', 'Catalogue — Produits')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-barcode"></i> Catalogue produits</h1>
        <p>{{ $produits->total() }} produit(s) enregistré(s)</p>
    </div>
    <button class="btn btn-primary" data-modal-open="modalNouveauProduit">
        <i class="fas fa-plus"></i> Ajouter un produit
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Produit</th>
                    <th>Type</th>
                    <th>Catégorie</th>
                    <th>Prix achat</th>
                    <th>Prix vente</th>
                    <th>Marge</th>
                    <th>Stock</th>
                    <th>Min.</th>
                    <th>État</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($produits as $p)
                <tr>
                    <td style="font-family:monospace; font-size:12px; color:var(--text-3);">{{ $p->reference }}</td>
                    <td style="font-weight:600;">{{ $p->nom }}</td>
                    <td>
                        @if($p->type === 'service')
                            <span class="badge" style="background:#eff6ff; color:#1e40af; padding:2px 8px; border-radius:20px; font-weight:600; font-size:11px;">Service</span>
                        @elseif($p->type === 'consommable')
                            <span class="badge" style="background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:20px; font-weight:600; font-size:11px;">Consommable</span>
                        @else
                            <span class="badge" style="background:#ecfdf5; color:#065f46; padding:2px 8px; border-radius:20px; font-weight:600; font-size:11px;">Stockable</span>
                        @endif
                    </td>
                    <td><span class="badge badge-purple">{{ $p->categorie ?? '—' }}</span></td>
                    <td>{{ number_format($p->prix_achat, 0, ',', ' ') }} F</td>
                    <td style="color:var(--success); font-weight:600;">{{ number_format($p->prix_vente, 0, ',', ' ') }} F</td>
                    <td style="color:var(--info);">
                        @php $marge = $p->prix_achat > 0 ? round((($p->prix_vente - $p->prix_achat) / $p->prix_achat) * 100) : 0; @endphp
                        +{{ $marge }}%
                    </td>
                    <td style="font-weight:800; {{ $p->stock_actuel == 0 ? 'color:var(--danger)' : ($p->stock_actuel <= $p->stock_minimum ? 'color:var(--warning)' : 'color:var(--success)') }}">
                        {{ $p->stock_actuel }} {{ $p->unite }}
                    </td>
                    <td style="color:var(--text-3);">{{ $p->stock_minimum }}</td>
                    <td>
                        @if($p->stock_actuel == 0)
                            <span class="badge badge-danger">Rupture</span>
                        @elseif($p->stock_actuel <= $p->stock_minimum)
                            <span class="badge badge-warning">Faible</span>
                        @else
                            <span class="badge badge-success">OK</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-outline btn-sm" data-modal-open="modalModifier{{ $p->id }}">
                            <i class="fas fa-pen"></i>
                        </button>
                    </td>
                </tr>

                {{-- Modal modifier --}}
                <div class="modal-overlay" id="modalModifier{{ $p->id }}">
                    <div class="modal">
                        <div class="modal-header">
                            <h3>Modifier — {{ $p->nom }}</h3>
                            <button class="modal-close" data-modal-close>✕</button>
                        </div>
                        <form method="POST" action="{{ route('admin.produits.modifier', $p) }}">
                            @csrf @method('PUT')
                            <div class="form-grid-2">
                                <div class="form-group" style="grid-column:1/-1;">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="nom" class="form-control" value="{{ $p->nom }}" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Type d'article</label>
                                    <select name="type" class="form-control" required>
                                        <option value="stockable" {{ $p->type === 'stockable' ? 'selected' : '' }}>Article Stockable</option>
                                        <option value="consommable" {{ $p->type === 'consommable' ? 'selected' : '' }}>Consommable (sans stock)</option>
                                        <option value="service" {{ $p->type === 'service' ? 'selected' : '' }}>Service (non physique)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Catégorie</label>
                                    <input type="text" name="categorie" class="form-control" value="{{ $p->categorie }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Unité</label>
                                    <input type="text" name="unite" class="form-control" value="{{ $p->unite }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Taux TVA par défaut</label>
                                    <select name="taux_tva" class="form-control" required>
                                        <option value="18.00" {{ $p->taux_tva == 18.00 ? 'selected' : '' }}>18% (Taux normal)</option>
                                        <option value="0.00" {{ $p->taux_tva == 0.00 ? 'selected' : '' }}>0% (Exonéré)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Compte de vente</label>
                                    <select name="compte_vente" class="form-control" required>
                                        @foreach($comptes as $compte)
                                            <option value="{{ $compte->numero }}" {{ ($p->compte_vente ?? '701100') == $compte->numero ? 'selected' : '' }}>
                                                {{ $compte->numero }} - {{ $compte->libelle }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Compte d'achat</label>
                                    <select name="compte_achat" class="form-control" required>
                                        @foreach($comptes as $compte)
                                            <option value="{{ $compte->numero }}" {{ ($p->compte_achat ?? '601100') == $compte->numero ? 'selected' : '' }}>
                                                {{ $compte->numero }} - {{ $compte->libelle }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix achat</label>
                                    <input type="number" name="prix_achat" class="form-control" value="{{ $p->prix_achat }}" min="0" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix vente</label>
                                    <input type="number" name="prix_vente" class="form-control" value="{{ $p->prix_vente }}" min="0" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Stock actuel</label>
                                    <input type="number" name="stock_actuel" class="form-control" value="{{ $p->stock_actuel }}" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Stock minimum</label>
                                    <input type="number" name="stock_minimum" class="form-control" value="{{ $p->stock_minimum }}" min="0" required>
                                </div>
                            </div>
                            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Sauvegarder</button>
                            </div>
                        </form>
                    </div>
                </div>

                @endforeach
            </tbody>
        </table>
        <div style="padding: 0 16px;">{{ $produits->links() }}</div>
    </div>
</div>

{{-- Modal Nouveau Produit --}}
<div class="modal-overlay" id="modalNouveauProduit">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Nouveau produit</h3>
            <button class="modal-close" data-modal-close>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.produits.creer') }}">
            @csrf
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Référence <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="reference" class="form-control" placeholder="ART-001" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nom produit <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="nom" class="form-control" placeholder="Huile Dinor 1L" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Type d'article <span style="color:var(--danger)">*</span></label>
                    <select name="type" class="form-control" required>
                        <option value="stockable">Article Stockable</option>
                        <option value="consommable">Consommable (sans stock)</option>
                        <option value="service">Service (non physique)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catégorie</label>
                    <input type="text" name="categorie" class="form-control" placeholder="Épicerie">
                </div>
                <div class="form-group">
                    <label class="form-label">Unité</label>
                    <input type="text" name="unite" class="form-control" placeholder="pcs, kg, L…">
                </div>
                <div class="form-group">
                    <label class="form-label">Taux TVA par défaut <span style="color:var(--danger)">*</span></label>
                    <select name="taux_tva" class="form-control" required>
                        <option value="18.00">18% (Taux normal)</option>
                        <option value="0.00">0% (Exonéré / TVAD)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Compte de vente <span style="color:var(--danger)">*</span></label>
                    <select name="compte_vente" class="form-control" required>
                        @foreach($comptes as $compte)
                            <option value="{{ $compte->numero }}" {{ $compte->numero == '701100' ? 'selected' : '' }}>
                                {{ $compte->numero }} - {{ $compte->libelle }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Compte d'achat <span style="color:var(--danger)">*</span></label>
                    <select name="compte_achat" class="form-control" required>
                        @foreach($comptes as $compte)
                            <option value="{{ $compte->numero }}" {{ $compte->numero == '601100' ? 'selected' : '' }}>
                                {{ $compte->numero }} - {{ $compte->libelle }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Prix d'achat (FCFA) <span style="color:var(--danger)">*</span></label>
                    <input type="number" name="prix_achat" class="form-control" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Prix de vente (FCFA) <span style="color:var(--danger)">*</span></label>
                    <input type="number" name="prix_vente" class="form-control" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock initial <span style="color:var(--danger)">*</span></label>
                    <input type="number" name="stock_actuel" class="form-control" min="0" value="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock minimum <span style="color:var(--danger)">*</span></label>
                    <input type="number" name="stock_minimum" class="form-control" min="0" value="5" required>
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Ajouter au catalogue</button>
            </div>
        </form>
    </div>
</div>
@endsection
