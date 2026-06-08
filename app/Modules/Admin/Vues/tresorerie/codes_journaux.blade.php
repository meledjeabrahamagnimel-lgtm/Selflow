@extends('admin::gabarits.application')
@section('titre', 'Codes Journaux')
@section('topbar_titre', 'Trésorerie — Codes Journaux')

@section('contenu')
<div class="page-header">
    <div>
        <h1><i class="fas fa-book"></i> Codes Journaux</h1>
        <p>Gérez les journaux comptables et leurs comptes associés</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-open="modalNouveauJournal">
        <i class="fas fa-plus-circle"></i> Nouveau code
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        @if($codes->isEmpty())
        <div style="padding:48px; text-align:center; color:var(--text-3);">
            <i class="fas fa-book-open" style="font-size:48px; display:block; margin-bottom:12px; opacity:.2;"></i>
            Aucun code journal configuré pour le moment.
        </div>
        @else
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Code</th>
                    <th>Intitulé</th>
                    <th>Compte</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($codes as $code)
                <tr>
                    <td>
                        @if($code->type === 'Vente')
                            <span class="badge badge-info"><i class="fas fa-cash-register"></i> Vente</span>
                        @elseif($code->type === 'Achat')
                            <span class="badge badge-warning"><i class="fas fa-cart-shopping"></i> Achat</span>
                        @elseif($code->type === 'Caisse')
                            <span class="badge badge-success"><i class="fas fa-wallet"></i> Caisse</span>
                        @elseif($code->type === 'Banque')
                            <span class="badge badge-purple"><i class="fas fa-building-columns"></i> Banque</span>
                        @else
                            <span class="badge badge-gray"><i class="fas fa-folder"></i> {{ $code->type }}</span>
                        @endif
                    </td>
                    <td style="font-weight:700; font-family:monospace; font-size:13px; color:var(--primary);">{{ $code->code }}</td>
                    <td style="font-weight:500;">{{ $code->intitule }}</td>
                    <td style="font-family:monospace; font-size:13px;">{{ $code->compte }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.tresorerie.supprimer_code_journal', $code->id) }}" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce code journal ?')" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 5px 10px;">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

<!-- Modal Nouveau Code Journal -->
<div class="modal-overlay" id="modalNouveauJournal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Nouveau Code Journal</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.tresorerie.creer_code_journal') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Type de journal <span style="color:var(--danger)">*</span></label>
                <select name="type" class="form-control" required>
                    <option value="Vente">Vente</option>
                    <option value="Achat">Achat</option>
                    <option value="Caisse">Caisse</option>
                    <option value="Banque">Banque</option>
                    <option value="Général">Général</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Code <span style="color:var(--danger)">*</span></label>
                <input type="text" name="code" class="form-control" placeholder="Ex: VT, CAI, BQ" required maxlength="10">
            </div>

            <div class="form-group">
                <label class="form-label">Intitulé <span style="color:var(--danger)">*</span></label>
                <input type="text" name="intitule" class="form-control" placeholder="Ex: Journal de Caisse" required>
            </div>

            <div class="form-group">
                <label class="form-label">Compte comptable <span style="color:var(--danger)">*</span></label>
                <input type="text" name="compte" class="form-control" placeholder="Ex: 571100, 521100" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endsection
