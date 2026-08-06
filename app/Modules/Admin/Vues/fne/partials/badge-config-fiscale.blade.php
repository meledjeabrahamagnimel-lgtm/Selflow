{{-- État de la configuration fiscale, à droite de la barre de filtres. --}}
<div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
    @if($taxConfig)
        <span class="config-badge configured">
            <i class="fas fa-check-circle"></i>
            Config. fiscale active
            @if($taxConfig->categorie === 'CAS_A') — CAS A @else — CAS B @endif
        </span>
    @else
        <span class="config-badge not-configured">
            <i class="fas fa-exclamation-circle"></i> Config. fiscale non définie
        </span>
    @endif
    <button class="btn btn-outline" onclick="ouvrirModalConfig()" style="white-space:nowrap;">
        <i class="fas fa-cog"></i> Configuration Fiscale
    </button>
</div>
