<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titre', 'Tableau de bord') — Selflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 260px;
            --topbar-h: 64px;
            --primary:   #002B5C; /* Bleu royal profond */
            --primary-d: #001F42; /* Bleu plus foncé pour hover/active */
            --success:   #10b981;
            --warning:   #f59e0b;
            --danger:    #ef4444;
            --info:      #3b82f6;
            --bg:        #F4F6F9; /* Fond général gris-bleu très clair */
            --bg2:       #ffffff; /* Fond topbar et cartes */
            --bg3:       #EBF2FC; /* Fond actif/hover, en-têtes de table */
            --surface:   #ffffff; /* Cartes et modaux */
            --border:    #E2E8F0; /* Bordures claires */
            --text:      #1E293B; /* Texte sombre (slate-800) */
            --text-2:    #475569; /* Texte secondaire (slate-600) */
            --text-3:    #94a3b8; /* Texte atténué (slate-400) */
            --radius:    12px;
            --shadow:    0 10px 30px rgba(0, 0, 0, 0.05); /* Ombre douce */
        }

        @php
            $lectureSeule = session()->has('apercu_pdv_id');
        @endphp

        body {
            --banner-h: {{ $lectureSeule ? '40px' : '0px' }};
        }

        html, body { height: 100%; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }

        /* ── SIDEBAR ─────────────────────────────── */
        .sidebar {
            position: fixed; top: 0; left: 0; width: var(--sidebar-w); height: 100vh;
            background: #002B5C; border-right: none;
            display: flex; flex-direction: column; z-index: 100;
            overflow-y: auto; overflow-x: hidden;
            transition: transform 0.3s ease, width 0.3s ease;
        }
        .sidebar-logo {
            padding: 24px 20px 20px;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-logo .logo-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 800; color: #fff; letter-spacing: -1px;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .sidebar-logo .logo-text { font-size: 18px; font-weight: 700; color: #ffffff; }
        .sidebar-logo .logo-sub  { font-size: 11px; color: rgba(255, 255, 255, 0.6); margin-top: 1px; }

        .sidebar-pdv {
            margin: 14px 12px 0;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
            border-radius: 10px; padding: 10px 14px; font-size: 12px;
        }
        .sidebar-pdv .pdv-label { color: rgba(255, 255, 255, 0.5); margin-bottom: 3px; text-transform: uppercase; letter-spacing:.5px; font-size: 10px; }
        .sidebar-pdv .pdv-name  { color: #FFC107; font-weight: 600; }

        .sidebar-nav { flex: 1; padding: 16px 12px; display: flex; flex-direction: column; gap: 2px; }

        .nav-section { margin-top: 16px; margin-bottom: 6px; }
        .nav-section span {
            font-size: 10px; font-weight: 600; letter-spacing: .8px;
            text-transform: uppercase; color: rgba(255, 255, 255, 0.4); padding: 0 10px;
        }

        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; border-radius: 8px; text-decoration: none;
            color: rgba(255, 255, 255, 0.75); font-weight: 500; font-size: 13.5px;
            transition: all .15s; position: relative;
        }
        .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: #ffffff; }
        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.25);
        }
        .nav-item.active i { color: #FFC107; }
        .nav-item i { width: 18px; text-align: center; font-size: 14px; }

        .nav-badge {
            margin-left: auto; background: var(--danger);
            color: #fff; font-size: 10px; font-weight: 700;
            padding: 2px 7px; border-radius: 20px; line-height: 1.4;
        }

        .sidebar-footer {
            padding: 16px 12px; border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-user {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px;
            background: rgba(255, 255, 255, 0.08);
        }
        .sidebar-user .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px; color: #fff; flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-user .user-info .name  { font-size: 13px; font-weight: 600; color: #ffffff; }
        .sidebar-user .user-info .role  { font-size: 11px; color: rgba(255, 255, 255, 0.5); text-transform: capitalize; }
        .sidebar-user .logout-btn {
            margin-left: auto; color: rgba(255, 255, 255, 0.6); font-size: 15px;
            transition: color .15s; cursor: pointer; border: none; background: none;
        }
        .sidebar-user .logout-btn:hover { color: #fca5a5; }

        /* ── TOPBAR ──────────────────────────────── */
        .topbar {
            position: fixed; top: var(--banner-h); left: var(--sidebar-w); right: 0;
            height: var(--topbar-h); background: var(--bg2);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 28px;
            gap: 16px; z-index: 90;
            transition: left 0.3s ease, top 0.3s ease;
        }
        .topbar-title { font-size: 16px; font-weight: 700; flex: 1; color: var(--text); }
        .topbar-title span { color: var(--text-2); font-weight: 400; font-size: 13px; margin-left: 6px; }

        .topbar-badge {
            display: flex; align-items: center; gap: 6px;
            background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25);
            border-radius: 20px; padding: 5px 12px; font-size: 12px; color: #b91c1c;
        }

        .topbar-clock {
            font-size: 13px; color: var(--text-2);
            background: #F1F5F9; border-radius: 8px; padding: 6px 12px;
        }

        /* ── MAIN CONTENT ────────────────────────── */
        .main-wrap {
            margin-left: var(--sidebar-w);
            padding-top: calc(var(--topbar-h) + var(--banner-h));
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Collapsed / Expanded states for sidebar */
        body.sidebar-collapsed {
            --sidebar-w: 0px;
        }
        body.sidebar-collapsed .sidebar {
            transform: translateX(-100%);
        }

        /* Hamburger button style */
        .toggle-sidebar-btn {
            background: none; border: none; font-size: 18px; color: var(--text);
            cursor: pointer; padding: 8px; display: flex; align-items: center;
            justify-content: center; transition: color .15s; margin-right: 12px;
            border-radius: 6px;
        }
        .toggle-sidebar-btn:hover { background: var(--bg3); color: var(--primary); }

        /* Banner aperçu */
        .banner-apercu {
            background: #FEF3C7; border-bottom: 1px solid #FCD34D; color: #92400E;
            padding: 8px 24px; display: flex; align-items: center; justify-content: space-between;
            position: fixed; top: 0; left: var(--sidebar-w); right: 0; height: 40px; z-index: 105;
            transition: left 0.3s ease; font-size: 13px;
        }
        .banner-content { display: flex; align-items: center; gap: 8px; }
        .banner-content i { font-size: 15px; color: #D97706; }
        .btn-quit-apercu {
            background: #D97706; color: #fff; border: none; padding: 6px 12px;
            border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: background .15s;
        }
        .btn-quit-apercu:hover { background: #B45309; }
        .main-content { padding: 28px 32px; }

        /* ── ALERTS ──────────────────────────────── */
        .alert {
            padding: 14px 18px; border-radius: 10px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px; font-size: 13.5px;
        }
        .alert-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .alert-danger  { background: #FEF2F2; border: 1px solid #FEE2E2; color: #991B1B; }
        .alert-warning { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }

        /* ── CARDS ───────────────────────────────── */
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow);
        }
        .card-header {
            padding: 18px 22px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 15px; font-weight: 700; color: var(--text); }
        .card-body { padding: 22px; }

        /* ── STAT CARDS ──────────────────────────── */
        .stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 18px; margin-bottom: 24px; }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 20px 22px;
            display: flex; align-items: center; gap: 16px;
            transition: transform .2s, box-shadow .2s;
            box-shadow: var(--shadow);
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(0,0,0,.08); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .stat-icon.green  { background: rgba(16,185,129,.1); color: var(--success); }
        .stat-icon.red    { background: rgba(239,68,68,.1);  color: var(--danger);  }
        .stat-icon.blue   { background: rgba(59,130,246,.1); color: var(--info);    }
        .stat-icon.yellow { background: rgba(245,158,11,.1); color: var(--warning); }
        .stat-icon.purple { background: rgba(0,43,92,.1);    color: var(--primary); }
        .stat-value { font-size: 22px; font-weight: 800; line-height: 1.2; color: var(--text); }
        .stat-label { font-size: 12px; color: var(--text-2); margin-top: 3px; }
        .stat-change { font-size: 11px; margin-top: 5px; }
        .stat-change.up   { color: var(--success); }
        .stat-change.down { color: var(--danger); }

        /* ── TABLE ───────────────────────────────── */
        .table-wrap { overflow: auto; max-height: 70vh; border-radius: var(--radius); }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #F8FAFC; padding: 12px 16px; text-align: left;
            font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .5px; color: var(--text-2); border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 10; white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(0,0,0,.02); }
        tbody td { padding: 13px 16px; font-size: 13px; vertical-align: middle; color: var(--text); white-space: nowrap; }

        /* ── BADGES ──────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .badge-success { background: rgba(16,185,129,.1); color: #065f46; }
        .badge-warning { background: rgba(245,158,11,.1);  color: #92400e; }
        .badge-danger  { background: rgba(239,68,68,.1);   color: #991b1b; }
        .badge-info    { background: rgba(59,130,246,.1);  color: #1e3a8a; }
        .badge-purple  { background: rgba(0,43,92,.1);     color: #002b5c; }
        .badge-gray    { background: rgba(100,116,139,.1); color: #475569; }

        /* ── BUTTONS ─────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 18px; border-radius: 8px; font-size: 13px;
            font-weight: 600; cursor: pointer; border: none; text-decoration: none;
            transition: all .15s; font-family: 'Inter', sans-serif;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-d); transform: translateY(-1px); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-outline {
            background: transparent; color: var(--text-2);
            border: 1px solid var(--border);
        }
        .btn-outline:hover { background: #F1F5F9; color: var(--text); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* ── FORMS ───────────────────────────────── */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
        .form-control {
            width: 100%; background: #ffffff; border: 1px solid var(--border);
            border-radius: 8px; padding: 10px 14px; color: var(--text);
            font-size: 13px; font-family: 'Inter', sans-serif;
            transition: border-color .15s, box-shadow .15s; outline: none;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0, 43, 92, 0.15); }
        .form-control::placeholder { color: var(--text-3); }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .is-invalid { border-color: var(--danger) !important; }
        .invalid-feedback { font-size: 12px; color: #dc2626; margin-top: 4px; }

        /* ── PAGINATION ──────────────────────────── */
        .pagination { display: flex; list-style: none; gap: 6px; justify-content: center; padding: 20px 0; margin: 0; }
        .pagination .page-link {
            background: var(--bg3); border: 1px solid var(--border);
            border-radius: 6px; padding: 7px 14px; font-size: 13px; color: var(--text-2);
            text-decoration: none; transition: all .15s;
        }
        .pagination .page-link:hover,
        .pagination .page-item.active .page-link {
            background: var(--primary); color: #fff; border-color: var(--primary);
        }

        /* Compatibilité sans framework CSS pour la pagination Laravel (Bootstrap 5) */
        nav.justify-content-between {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            width: 100% !important;
        }
        nav.justify-content-between ul.pagination {
            display: flex !important;
            list-style: none !important;
            list-style-type: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        nav.justify-content-between ul.pagination li {
            list-style: none !important;
            list-style-type: none !important;
            display: inline-block !important;
        }
        nav.justify-content-between div.d-sm-none {
            display: none !important;
        }
        nav.justify-content-between div.d-none {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 8px !important;
        }
        nav.justify-content-between div.d-none > div.small,
        nav.justify-content-between div.d-none > div.text-muted {
            display: none !important;
        }

        /* ── GRID LAYOUTS ────────────────────────── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 22px; }
        .grid-3-1 { display: grid; grid-template-columns: 3fr 1fr; gap: 22px; }

        /* ── PAGE HEADER ─────────────────────────── */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .page-header h1 { font-size: 22px; font-weight: 800; }
        .page-header p  { color: var(--text-2); margin-top: 4px; font-size: 13px; }

        /* ── ALERT STOCK ─────────────────────────── */
        .stock-alerte { color: var(--danger); font-weight: 600; }
        .stock-ok     { color: var(--success); }
        .stock-warning{ color: var(--warning); }

        /* ── MODAL ───────────────────────────────── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.6); backdrop-filter: blur(4px);
            z-index: 200; display: none; align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; padding: 28px; width: 100%; max-width: 540px;
            box-shadow: 0 24px 64px rgba(0,0,0,.5); animation: modalIn .2s ease;
            max-height: 85vh; overflow-y: auto;
        }
        @keyframes modalIn { from { opacity:0; transform: scale(.96) translateY(8px); } to { opacity:1; transform: none; } }
        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .modal-header h3 { font-size: 16px; font-weight: 700; }
        .modal-close { background: none; border: none; color: var(--text-3); font-size: 20px; cursor: pointer; }
        .modal-close:hover { color: var(--danger); }

        /* ── SCROLLBAR ───────────────────────────── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--bg3); border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-3); }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px !important; }
            .main-wrap { margin-left: 0 !important; }
            .topbar { left: 0 !important; }
            .banner-apercu { left: 0 !important; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
    @yield('styles')
</head>
<body>

@if(session()->has('apercu_pdv_id'))
<div class="banner-apercu">
    <div class="banner-content">
        <i class="fas fa-eye"></i>
        <span><strong>Mode Aperçu :</strong> Vous consultez l'interface caissier de <strong>{{ session('apercu_pdv_nom') }}</strong> en mode <strong>Lecture Seule</strong>. Les actions d'enregistrement et de modification sont désactivées.</span>
    </div>
    <form method="POST" action="{{ route('admin.pdv.desactiver_apercu') }}">
        @csrf
        <button type="submit" class="btn-quit-apercu">
            <i class="fas fa-right-from-bracket"></i> Quitter l'aperçu
        </button>
    </form>
</div>
@endif

<!-- ────────────────── SIDEBAR ────────────────── -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">S</div>
        <div>
            <div class="logo-text">Selflow</div>
            <div class="logo-sub">Gestion commerciale</div>
        </div>
    </div>

    @php
        $nomPdvAffichage = session('apercu_pdv_nom') ?? session('point_de_vente_actif_nom') ?? auth()->user()->pointDeVente?->nom;
        $estApercu = session()->has('apercu_pdv_id');
    @endphp

    @if($nomPdvAffichage)
    <div class="sidebar-pdv">
        <div class="pdv-label">
            <i class="fas fa-store"></i> 
            @if($estApercu)
                Aperçu Point de Vente
            @else
                Point de Vente
            @endif
        </div>
        <div class="pdv-name">{{ $nomPdvAffichage }}</div>
    </div>
    @endif

    <!-- Sélecteur d'exercice/période -->
    <div class="sidebar-periode" style="margin: 10px 12px 0; position: relative;">
        <button onclick="togglePeriodeDropdown(event)" style="width: 100%; display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 10px 14px; color: #ffffff; cursor: pointer; text-align: left; transition: all 0.2s; outline: none;">
            <i class="far fa-calendar-alt" style="font-size: 16px; color: #FFC107;"></i>
            <div style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <div style="font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px;">Période en cours</div>
                <div style="font-size: 13px; font-weight: 600;">{{ session('active_periode_nom', 'Non défini') }}</div>
            </div>
            <i class="fas fa-chevron-down" style="font-size: 10px; color: rgba(255,255,255,0.6);"></i>
        </button>

        <div id="periodeDropdownMenu" style="display: none; position: absolute; left: 0; right: 0; top: calc(100% + 6px); background: #ffffff; border: 1px solid var(--border); border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.25); z-index: 1000; padding: 6px 0;">
            <div style="padding: 6px 14px 10px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 600; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.5px;">
                Changer de période
            </div>
            <div style="max-height: 180px; overflow-y: auto;">
                @if(isset($global_periodes) && $global_periodes->count() > 0)
                    @php
                        $sortedPeriodes = $global_periodes->sortByDesc('date_debut');
                    @endphp
                    @foreach($sortedPeriodes as $p)
                        <form method="POST" action="{{ route('admin.periods.switch') }}" style="margin: 0;">
                            @csrf
                            <input type="hidden" name="periode_id" value="{{ $p->id }}">
                            <button type="submit" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 8px 14px; border: none; background: none; text-align: left; cursor: pointer; color: var(--text); font-family: inherit; font-size: 12.5px; transition: background 0.15s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='none'">
                                <span>{{ $p->nom }}</span>
                                @if(session('active_periode_id') == $p->id)
                                    <span class="badge badge-success" style="padding: 2px 6px; font-size: 9px;">Actif</span>
                                @endif
                            </button>
                        </form>
                    @endforeach
                @else
                    <div style="padding: 10px 14px; font-size: 12px; color: var(--text-3); text-align: center;">Aucun exercice configuré</div>
                @endif
            </div>
            @if(auth()->user()->role === 'admin')
                <div style="border-top: 1px solid var(--border); margin: 6px 0 4px;"></div>
                <a href="{{ route('admin.entreprise.parametres') }}" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 12px; color: var(--primary); font-weight: 600; text-decoration: none; transition: background 0.15s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='none'">
                    <i class="fas fa-sliders" style="font-size: 11px;"></i> Gérer les exercices
                </a>
            @endif
        </div>
    </div>

    <nav class="sidebar-nav">
        @if(request()->routeIs('caissier.*'))
            <!-- ── CAISSIER SIDEBAR ── -->
            <div class="nav-section"><span>Caisse</span></div>
            <a href="{{ route('caissier.ventes.nouvelle') }}" class="nav-item {{ request()->routeIs('caissier.ventes.nouvelle') ? 'active' : '' }}">
                <i class="fas fa-cash-register"></i> Nouvelle vente
            </a>
            <a href="{{ route('caissier.ventes.factures') }}" class="nav-item {{ request()->routeIs('caissier.ventes.factures') ? 'active' : '' }}">
                <i class="fas fa-file-invoice"></i> Mes factures
            </a>

            <div class="nav-section"><span>Stock</span></div>
            <a href="{{ route('caissier.stock.index') }}" class="nav-item {{ request()->routeIs('caissier.stock.index') ? 'active' : '' }}">
                <i class="fas fa-boxes-stacked"></i> Consulter stock
            </a>

            <div class="nav-section"><span>Trésorerie</span></div>
            <a href="{{ route('caissier.tresorerie.encaissements') }}" class="nav-item {{ request()->routeIs('caissier.tresorerie.encaissements') ? 'active' : '' }}">
                <i class="fas fa-arrow-down" style="color:#10b981;"></i> Mes encaissements
            </a>
        @else
            <!-- ── ADMIN SIDEBAR RESTUCTURÉ ── -->
            
            <!-- 1. Principal & Tableaux de bord -->
            @if(auth()->user()->aHabilitation('tableau_de_bord_personnel') || auth()->user()->aHabilitation('tableau_de_bord_general'))
            <div class="nav-section"><span>Principal</span></div>
            @if(auth()->user()->aHabilitation('tableau_de_bord_personnel'))
            <a href="{{ route('admin.tableau_de_bord') }}" class="nav-item {{ request()->routeIs('admin.tableau_de_bord') ? 'active' : '' }}">
                <i class="fas fa-chart-pie"></i> TDB Personnel
            </a>
            @endif
            @if(auth()->user()->aHabilitation('tableau_de_bord_general'))
            <a href="{{ route('admin.tableau_de_bord_general') }}" class="nav-item {{ request()->routeIs('admin.tableau_de_bord_general') ? 'active' : '' }}">
                <i class="fas fa-chart-line"></i> TDB Général
            </a>
            @endif
            @endif

            <!-- 2. Ventes -->
            @if(auth()->user()->aHabilitation('nouvelle_vente') || auth()->user()->aHabilitation('factures_vente') || auth()->user()->aHabilitation('historique_ventes'))
            <div class="nav-section"><span>Ventes</span></div>
            @if(auth()->user()->aHabilitation('nouvelle_vente'))
            <a href="{{ route('admin.ventes.nouvelle') }}" class="nav-item {{ request()->routeIs('admin.ventes.nouvelle') ? 'active' : '' }}">
                <i class="fas fa-cash-register"></i> Nouvelle vente
            </a>
            @endif
            @if(auth()->user()->aHabilitation('factures_vente'))
            <a href="{{ route('admin.ventes.factures') }}" class="nav-item {{ request()->routeIs('admin.ventes.factures') ? 'active' : '' }}">
                <i class="fas fa-file-invoice"></i> Factures vente
            </a>
            @endif
            @if(auth()->user()->aHabilitation('historique_ventes'))
            <a href="{{ route('admin.ventes.historique') }}" class="nav-item {{ request()->routeIs('admin.ventes.historique') ? 'active' : '' }}">
                <i class="fas fa-history"></i> Historique ventes
            </a>
            @endif
            @endif

            <!-- 3. Achats -->
            @if(auth()->user()->aHabilitation('nouvel_achat') || auth()->user()->aHabilitation('factures_achat') || auth()->user()->aHabilitation('historique_achats'))
            <div class="nav-section"><span>Achats</span></div>
            @if(auth()->user()->aHabilitation('nouvel_achat'))
            <a href="{{ route('admin.achats.nouveau') }}" class="nav-item {{ request()->routeIs('admin.achats.nouveau') ? 'active' : '' }}">
                <i class="fas fa-cart-plus"></i> Nouvel achat
            </a>
            @endif
            @if(auth()->user()->aHabilitation('factures_achat'))
            <a href="{{ route('admin.achats.factures') }}" class="nav-item {{ request()->routeIs('admin.achats.factures') ? 'active' : '' }}">
                <i class="fas fa-file-invoice-dollar"></i> Factures achat
            </a>
            @endif
            @if(auth()->user()->aHabilitation('historique_achats'))
            <a href="{{ route('admin.achats.historique') }}" class="nav-item {{ request()->routeIs('admin.achats.historique') ? 'active' : '' }}">
                <i class="fas fa-receipt"></i> Historique achats
            </a>
            @endif
            @endif

            <!-- 4. Stock -->
            @if(auth()->user()->aHabilitation('stock_articles') || auth()->user()->aHabilitation('stock_mouvements'))
            <div class="nav-section"><span>Stock</span></div>
            @if(auth()->user()->aHabilitation('stock_articles'))
            <a href="{{ route('admin.stock.index') }}" class="nav-item {{ request()->routeIs('admin.stock.index') ? 'active' : '' }}">
                <i class="fas fa-boxes-stacked"></i> Articles & stock
            </a>
            @endif
            @if(auth()->user()->aHabilitation('stock_mouvements'))
            <a href="{{ route('admin.stock.mouvements') }}" class="nav-item {{ request()->routeIs('admin.stock.mouvements') ? 'active' : '' }}">
                <i class="fas fa-arrows-up-down"></i> Mouvements
            </a>
            @endif
            @endif

            <!-- 5. Comptabilité -->
            @if(auth()->user()->aHabilitation('tresorerie_encaissements') || auth()->user()->aHabilitation('tresorerie_decaissements') || auth()->user()->aHabilitation('tresorerie_journal') || auth()->user()->aHabilitation('tresorerie_codes_journaux') || auth()->user()->aHabilitation('comptabilite_globale') || auth()->user()->aHabilitation('comptabilite_creances') || auth()->user()->aHabilitation('comptabilite_plan_comptable'))
            <div class="nav-section"><span>Comptabilité</span></div>
            @if(auth()->user()->aHabilitation('tresorerie_encaissements'))
            <a href="{{ route('admin.tresorerie.encaissements') }}" class="nav-item {{ request()->routeIs('admin.tresorerie.encaissements') ? 'active' : '' }}">
                <i class="fas fa-arrow-down" style="color:#10b981;"></i> Encaissements
            </a>
            @endif
            @if(auth()->user()->aHabilitation('tresorerie_decaissements'))
            <a href="{{ route('admin.tresorerie.decaissements') }}" class="nav-item {{ request()->routeIs('admin.tresorerie.decaissements') ? 'active' : '' }}">
                <i class="fas fa-arrow-up" style="color:#ef4444;"></i> Décaissements
            </a>
            @endif
            @if(auth()->user()->aHabilitation('tresorerie_journal'))
            <a href="{{ route('admin.tresorerie.journal') }}" class="nav-item {{ request()->routeIs('admin.tresorerie.journal') ? 'active' : '' }}">
                <i class="fas fa-wallet"></i> Solde &amp; journal
            </a>
            @endif
            @if(auth()->user()->aHabilitation('tresorerie_codes_journaux'))
            <a href="{{ route('admin.tresorerie.codes_journaux') }}" class="nav-item {{ request()->routeIs('admin.tresorerie.codes_journaux') ? 'active' : '' }}">
                <i class="fas fa-book"></i> Codes Journaux
            </a>
            @endif
            @if(auth()->user()->aHabilitation('comptabilite_globale'))
            <a href="{{ route('admin.comptabilite.globale') }}" class="nav-item {{ request()->routeIs('admin.comptabilite.globale') ? 'active' : '' }}">
                <i class="fas fa-list-check"></i> Opération &amp; écriture globale
            </a>
            @endif
            @if(auth()->user()->aHabilitation('comptabilite_creances'))
            <a href="{{ route('admin.comptabilite.creances') }}" class="nav-item {{ request()->routeIs('admin.comptabilite.creances') ? 'active' : '' }}">
                <i class="fas fa-scale-balanced"></i> Créances &amp; règlements
            </a>
            @endif
            @if(auth()->user()->aHabilitation('comptabilite_plan_comptable'))
            <a href="{{ route('admin.comptabilite.plan_comptable') }}" class="nav-item {{ request()->routeIs('admin.comptabilite.plan_comptable') ? 'active' : '' }}">
                <i class="fas fa-book-open"></i> Plan Comptable
            </a>
            @endif
            @endif

            <!-- 6. Points de vente -->
            @if(auth()->user()->aHabilitation('gestion_pdv') || auth()->user()->aHabilitation('gestion_personnel') || auth()->user()->aHabilitation('gestion_habilitations'))
            <div class="nav-section"><span>Points de vente</span></div>
            @if(auth()->user()->aHabilitation('gestion_pdv'))
            <a href="{{ route('admin.pdv.index') }}" class="nav-item {{ request()->routeIs('admin.pdv.index') ? 'active' : '' }}">
                <i class="fas fa-store"></i> Points de vente
            </a>
            @endif
            @if(auth()->user()->aHabilitation('gestion_personnel'))
            <a href="{{ route('admin.personnel.index') }}" class="nav-item {{ request()->routeIs('admin.personnel.index') && !request('tab') ? 'active' : '' }}">
                <i class="fas fa-users-gear"></i> Personnels & accès
            </a>
            @endif
            @if(auth()->user()->aHabilitation('gestion_habilitations'))
            <a href="{{ route('admin.personnel.index') }}?tab=habilitations" class="nav-item {{ request()->routeIs('admin.personnel.index') && request('tab') === 'habilitations' ? 'active' : '' }}">
                <i class="fas fa-shield-halved"></i> Habilitations
            </a>
            @endif
            @endif

            <!-- 7. Produits -->
            @if(auth()->user()->aHabilitation('catalogue_produits'))
            <div class="nav-section"><span>Produits</span></div>
            <a href="{{ route('admin.produits.index') }}" class="nav-item {{ request()->routeIs('admin.produits.index') ? 'active' : '' }}">
                <i class="fas fa-barcode"></i> Produits
            </a>
            @endif

            <!-- 8. Tiers -->
            @if(auth()->user()->aHabilitation('tiers_clients') || auth()->user()->aHabilitation('tiers_fournisseurs'))
            <div class="nav-section"><span>Tiers</span></div>
            @if(auth()->user()->aHabilitation('tiers_clients'))
            <a href="{{ route('admin.clients.index') }}" class="nav-item {{ request()->routeIs('admin.clients.index') ? 'active' : '' }}">
                <i class="fas fa-users"></i> Clients
            </a>
            @endif
            @if(auth()->user()->aHabilitation('tiers_fournisseurs'))
            <a href="{{ route('admin.fournisseurs.index') }}" class="nav-item {{ request()->routeIs('admin.fournisseurs.index') ? 'active' : '' }}">
                <i class="fas fa-handshake"></i> Fournisseurs
            </a>
            @endif
            @endif

            <!-- 9. Rapports -->
            @if(auth()->user()->aHabilitation('rapports_analyse'))
            <div class="nav-section"><span>Rapports</span></div>
            <a href="{{ route('admin.rapports.analyse_activite') }}" class="nav-item {{ request()->routeIs('admin.rapports.analyse_activite') ? 'active' : '' }}">
                <i class="fas fa-chart-mixed"></i> Analyse d'activité
            </a>
            @endif

            <!-- 10. Paramètres entreprise (admin uniquement) -->
            @if(auth()->user()->role === 'admin')
            <div class="nav-section"><span>Entreprise</span></div>
            <a href="{{ route('admin.entreprise.parametres') }}" class="nav-item {{ request()->routeIs('admin.entreprise.parametres') ? 'active' : '' }}">
                <i class="fas fa-gear"></i> Paramètres &amp; logos
            </a>
            @endif

        @endif
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar">{{ strtoupper(substr(auth()->user()->nom, 0, 1)) }}</div>
            <div class="user-info">
                <div class="name">{{ auth()->user()->nom }}</div>
                <div class="role">{{ auth()->user()->role }}</div>
            </div>
            <form method="POST" action="{{ route('deconnexion') }}">
                @csrf
                <button type="submit" class="logout-btn" title="Se déconnecter">
                    <i class="fas fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

<!-- ────────────────── TOPBAR ────────────────── -->
<header class="topbar">
    <button class="toggle-sidebar-btn" id="toggleSidebar" aria-label="Menu principal">
        <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-title">
        @yield('topbar_titre', 'Tableau de bord')
        <span>/ {{ auth()->user()->entreprise->nom ?? 'Selflow' }} — {{ session('apercu_pdv_nom') ?? session('point_de_vente_actif_nom') ?? auth()->user()->pointDeVente?->nom ?? 'Siège' }}</span>
    </div>

    <div style="display:flex; align-items:center; gap:16px;">
        <div class="topbar-clock" id="horloge">--:--:--</div>
        
        {{-- Bouton Profil et Menu Déroulant (Images 2 et 3) --}}
        <div class="user-dropdown" style="position:relative; display:inline-block;">
            <button class="user-dropdown-btn" onclick="toggleUserDropdown()" style="display:flex; align-items:center; gap:8px; background:#ffffff; border:1px solid var(--border); border-radius:30px; padding:4px 14px 4px 4px; cursor:pointer; font-family:inherit; transition: all 0.15s; outline:none;">
                <div style="width:30px; height:30px; border-radius:50%; background:#002B5C; color:#ffffff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px;">
                    {{ strtoupper(substr(auth()->user()->nom, 0, 1)) }}
                </div>
                <span style="font-size:12.5px; font-weight:600; color:var(--primary); max-width:120px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    {{ auth()->user()->nom }}
                </span>
                <i class="fas fa-chevron-down" style="font-size:9px; color:var(--text-3);"></i>
            </button>
            
            <div class="user-dropdown-menu" id="userDropdownMenu" style="display:none; position:absolute; right:0; top:calc(100% + 8px); width:230px; background:#ffffff; border:1px solid var(--border); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.08); z-index:1000; padding:6px 0;">
                <div style="padding:10px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px;">
                    <div style="width:34px; height:34px; border-radius:50%; background:#002B5C; color:#ffffff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px;">
                        {{ strtoupper(substr(auth()->user()->nom, 0, 1)) }}
                    </div>
                    <div style="overflow:hidden;">
                        <div style="font-weight:700; font-size:12.5px; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ auth()->user()->nom }}</div>
                        <div style="font-size:11px; color:var(--text-3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ auth()->user()->email }}</div>
                    </div>
                </div>
                <a href="#" style="display:flex; align-items:center; gap:8px; padding:8px 14px; font-size:12.5px; color:var(--text); text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='none'">
                    <i class="far fa-user" style="width:14px; color:var(--text-2);"></i> Mon profil
                </a>
                <a href="#" style="display:flex; align-items:center; gap:8px; padding:8px 14px; font-size:12.5px; color:var(--text); text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='none'">
                    <i class="fas fa-gear" style="width:14px; color:var(--text-2);"></i> Paramètres
                </a>
                <a href="#" style="display:flex; align-items:center; gap:8px; padding:8px 14px; font-size:12.5px; color:var(--text); text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='none'">
                    <i class="far fa-credit-card" style="width:14px; color:var(--text-2);"></i> Facturation
                </a>
                <div style="border-top:1px solid var(--border); margin:4px 0;"></div>
                <form method="POST" action="{{ route('deconnexion') }}" id="formDeconnexionDropdown" style="margin:0;">
                    @csrf
                    <button type="submit" style="width:100%; display:flex; align-items:center; gap:8px; padding:8px 14px; font-size:12.5px; color:var(--danger); border:none; background:none; cursor:pointer; text-align:left; font-family:inherit; transition:background 0.15s;" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='none'">
                        <i class="fas fa-right-from-bracket" style="width:14px;"></i> Se déconnecter
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<!-- ────────────────── CONTENU PRINCIPAL ────────────────── -->
<div class="main-wrap">
    <main class="main-content">

        @if(session('succes'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ session('succes') }}
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger">
            <i class="fas fa-circle-exclamation"></i>
            <div>
                @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        </div>
        @endif

        @yield('contenu')
    </main>
</div>

<script>
    // Horloge en temps réel
    function majHorloge() {
        const el = document.getElementById('horloge');
        if (el) {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('fr-FR');
        }
    }
    majHorloge();
    setInterval(majHorloge, 1000);

    // Toggle Sidebar
    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            document.body.classList.toggle('sidebar-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
        }
    });

    // Menu déroulant Profil utilisateur
    function toggleUserDropdown() {
        const menu = document.getElementById('userDropdownMenu');
        if (menu) {
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
    }

    // Menu déroulant Période
    function togglePeriodeDropdown(e) {
        e.stopPropagation();
        const menu = document.getElementById('periodeDropdownMenu');
        if (menu) {
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
    }

    // Fermeture automatique au clic extérieur
    document.addEventListener('click', (e) => {
        // Profil
        const btn = document.querySelector('.user-dropdown-btn');
        const menu = document.getElementById('userDropdownMenu');
        if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
            menu.style.display = 'none';
        }

        // Période
        const pBtn = document.querySelector('.sidebar-periode button');
        const pMenu = document.getElementById('periodeDropdownMenu');
        if (pBtn && pMenu && !pBtn.contains(e.target) && !pMenu.contains(e.target)) {
            pMenu.style.display = 'none';
        }
    });

    // Fermeture auto des alertes
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            a.style.transition = 'opacity .4s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 400);
        });
    }, 5000);

    // Modals
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById(btn.dataset.modalOpen)?.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay')?.classList.remove('open');
        });
    });
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // Lecture seule globale si en mode aperçu
    @if($lectureSeule)
    document.addEventListener('DOMContentLoaded', () => {
        const elementsToDisable = document.querySelectorAll(
            'input:not(#toggleSidebar *), select:not(#toggleSidebar *), textarea:not(#toggleSidebar *), button:not(#toggleSidebar):not(.btn-quit-apercu):not(.btn-quit-apercu *):not(.logout-btn):not(.logout-btn *), a.btn:not(.nav-item)'
        );
        elementsToDisable.forEach(el => {
            if (el.closest('.sidebar')) return;
            el.disabled = true;
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.65';
            if (el.tagName === 'A') {
                el.addEventListener('click', e => e.preventDefault());
            }
        });
    });
    @endif
</script>
@yield('scripts')
</body>
</html>
