@extends('admin::gabarits.application')
@section('titre', 'Gestion du stock')
@section('topbar_titre', 'Stock — Inventaire')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-boxes-stacked"></i> Inventaire du stock</h1>
        <p>{{ $produits->count() }} produit(s) au catalogue</p>
    </div>
    <a href="{{ route('admin.stock.mouvements') }}" class="btn btn-outline">
        <i class="fas fa-arrows-up-down"></i> Mouvements
    </a>
</div>

{{-- Filtres catégorie --}}
<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px;">
    <button class="cat-btn active" data-cat="all" onclick="filtrerCat(this)" style="padding:6px 16px; border-radius:20px; border:1px solid var(--border); background:var(--primary); color:#fff; font-size:12px; font-weight:600; cursor:pointer;">Tous</button>
    @foreach($categories as $cat)
    <button class="cat-btn" data-cat="{{ $cat }}" onclick="filtrerCat(this)" style="padding:6px 16px; border-radius:20px; border:1px solid var(--border); background:var(--bg3); color:var(--text-2); font-size:12px; font-weight:600; cursor:pointer;">{{ $cat }}</button>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-list"></i> Catalogue complet</h2>
        <input type="text" id="searchStock" placeholder="🔍 Rechercher…" class="form-control" style="width:220px;" oninput="filtrerRecherche(this.value)">
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Produit</th>
                    <th>Catégorie</th>
                    <th>Prix achat</th>
                    <th>Prix vente</th>
                    <th>Stock actuel</th>
                    <th>Stock min.</th>
                    <th>État</th>
                    <th>Valeur stock</th>
                </tr>
            </thead>
            <tbody id="tableauStock">
                @foreach($produits as $p)
                <tr data-cat="{{ $p->categorie }}" data-nom="{{ strtolower($p->nom) }} {{ strtolower($p->reference) }}">
                    <td style="font-family:monospace; color:var(--text-3); font-size:12px;">{{ $p->reference }}</td>
                    <td style="font-weight:600;">{{ $p->nom }}</td>
                    <td><span class="badge badge-purple">{{ $p->categorie ?? '—' }}</span></td>
                    <td>{{ number_format($p->prix_achat, 0, ',', ' ') }} F</td>
                    <td style="font-weight:600; color:var(--success);">{{ number_format($p->prix_vente, 0, ',', ' ') }} F</td>
                    <td>
                        <span style="font-weight:800; font-size:16px; {{ $p->stock_actuel == 0 ? 'color:var(--danger)' : ($p->stock_actuel <= $p->stock_minimum ? 'color:var(--warning)' : 'color:var(--success)') }}">
                            {{ $p->stock_actuel }}
                        </span>
                        <span style="color:var(--text-3); font-size:11px;"> {{ $p->unite }}</span>
                    </td>
                    <td style="color:var(--text-3);">{{ $p->stock_minimum }}</td>
                    <td>
                        @if($p->stock_actuel == 0)
                            <span class="badge badge-danger"><i class="fas fa-circle-xmark"></i> Rupture</span>
                        @elseif($p->stock_actuel <= $p->stock_minimum)
                            <span class="badge badge-warning"><i class="fas fa-triangle-exclamation"></i> Faible</span>
                        @else
                            <span class="badge badge-success"><i class="fas fa-check"></i> OK</span>
                        @endif
                    </td>
                    <td style="font-weight:600;">{{ number_format($p->stock_actuel * $p->prix_achat, 0, ',', ' ') }} F</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="padding:16px 22px; border-top:1px solid var(--border); display:flex; gap:24px;">
        <div style="font-size:13px; color:var(--text-2);">
            Valeur totale du stock :
            <strong style="color:var(--primary); font-size:15px;">
                {{ number_format($produits->sum(fn($p) => $p->stock_actuel * $p->prix_achat), 0, ',', ' ') }} FCFA
            </strong>
        </div>
        <div style="font-size:13px; color:var(--text-2);">
            Alertes : <strong style="color:var(--danger);">{{ $produits->where('stock_actuel', 0)->count() }} rupture(s)</strong>
            / <strong style="color:var(--warning);">{{ $produits->filter(fn($p) => $p->stock_actuel > 0 && $p->stock_actuel <= $p->stock_minimum)->count() }} faible(s)</strong>
        </div>
    </div>
</div>

<script>
function filtrerCat(btn) {
    document.querySelectorAll('.cat-btn').forEach(b => {
        b.style.background = 'var(--bg3)'; b.style.color = 'var(--text-2)'; b.style.borderColor = 'var(--border)';
    });
    btn.style.background = 'var(--primary)'; btn.style.color = '#fff'; btn.style.borderColor = 'var(--primary)';
    const cat = btn.dataset.cat;
    document.querySelectorAll('#tableauStock tr').forEach(tr => {
        tr.style.display = (cat === 'all' || tr.dataset.cat === cat) ? '' : 'none';
    });
}
function filtrerRecherche(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#tableauStock tr').forEach(tr => {
        tr.style.display = tr.dataset.nom.includes(q) ? '' : 'none';
    });
}
</script>
@endsection
