<?php
/**
 * SHARP - Unified Navigation Component
 * Termasuk: Top Navbar, Dynamic Sidebar (Desktop), & Bottom Nav (Mobile)
 */
$current_page = basename($_SERVER['PHP_SELF']);
$user_nama = $_SESSION['nama'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'auditor';
?>

<style>
    :root {
        --primary-sharp: #1e3a8a;
        --secondary-sharp: #f59e0b;
        --sidebar-width: 260px;
        --sidebar-mini-width: 75px;
        --transition-speed: 0.3s;
    }

    /* --- SIDEBAR DESKTOP --- */
    .sidebar-desktop {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: var(--sidebar-width);
        background: white;
        border-right: 1px solid #e2e8f0;
        z-index: 1040;
        transition: width var(--transition-speed) ease;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
    }

    /* State Sidebar Mini (Minimized) */
    body.sidebar-mini .sidebar-desktop {
        width: var(--sidebar-mini-width);
    }

    .sidebar-header {
        padding: 20px;
        height: 70px;
        display: flex;
        align-items: center;
        background: var(--primary-sharp);
        color: white;
        white-space: nowrap;
    }

    /* Hide text on mini sidebar */
    body.sidebar-mini .sidebar-header span,
    body.sidebar-mini .sidebar-label,
    body.sidebar-mini .sidebar-item span {
        display: none;
    }

    .sidebar-menu {
        padding: 15px 0;
        flex-grow: 1;
    }

    .sidebar-item {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: #64748b;
        text-decoration: none;
        transition: all 0.2s;
        white-space: nowrap;
        border-left: 4px solid transparent;
    }

    .sidebar-item i {
        min-width: 35px;
        font-size: 20px;
    }

    .sidebar-item:hover, .sidebar-item.active {
        background: #f1f5f9;
        color: var(--primary-sharp);
        border-left-color: var(--primary-sharp);
    }

    /* --- TOP NAVBAR --- */
    .navbar-sharp {
        background: var(--primary-sharp);
        height: 70px;
        z-index: 1050;
        position: sticky;
        top: 0;
    }

    .btn-toggle-sidebar {
        background: rgba(255,255,255,0.1);
        border: none;
        color: white;
        padding: 5px 10px;
        border-radius: 8px;
        margin-right: 15px;
        transition: 0.2s;
    }

    .btn-toggle-sidebar:hover {
        background: rgba(255,255,255,0.2);
    }

    /* --- LAYOUT ADAPTATION --- */
    .main-content {
        transition: margin-left var(--transition-speed) ease;
        min-height: 100vh;
        padding-top: 20px;
    }

    @media (min-width: 992px) {
        .main-content { margin-left: var(--sidebar-width); }
        body.sidebar-mini .main-content { margin-left: var(--sidebar-mini-width); }
        .mobile-bottom-nav { display: none !important; }
    }

    @media (max-width: 991px) {
        .sidebar-desktop { display: none; }
        .main-content { margin-left: 0 !important; padding-bottom: 90px; }
        .mobile-bottom-nav { display: flex; }
    }

    /* --- MOBILE BOTTOM NAV --- */
    .mobile-bottom-nav {
        position: fixed; bottom: 0; left: 0; right: 0;
        background: white; justify-content: space-around;
        padding: 10px 0; box-shadow: 0 -3px 15px rgba(0,0,0,0.1);
        z-index: 1100;
    }
    .nav-btn { text-decoration: none; color: #94a3b8; font-size: 11px; text-align: center; flex: 1; }
    .nav-btn.active { color: var(--primary-sharp); }
    .nav-btn i { display: block; margin: 0 auto 3px; width: 22px; height: 22px; }

    /* Pewarnaan Role */
         .badge-role { font-size: 0.7rem; padding: 5px 10px; border-radius: 20px; font-weight: bold; }
        .role-admin { background-color: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .role-manager { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .role-supervisor { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .role-auditor { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

</style>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-sharp shadow-sm">
    <div class="container-fluid px-3">
        <div class="d-flex align-items-center">
            <!-- Sidebar Toggle Button (Desktop Only) -->
            <button class="btn-toggle-sidebar d-none d-lg-block" onclick="toggleSidebar()">
                <i data-lucide="menu"></i>
            </button>
            
            <a class="navbar-brand d-flex align-items-center fw-bold text-white m-0" href="dashboard.php">
                <i data-lucide="shield-check" class="me-2 text-warning"></i> 
                <span class="brand-text">SHARP</span>
            </a>
        </div>

        <div class="d-flex align-items-center">
            <div class="text-end me-3 d-none d-md-block">
                <span class="fw-semibold text-white"><?php echo $_SESSION['nama']; ?> </span>
                <span class="badge-role role-<?=$user_role?>"> <?=ucfirst($user_role)?> </span>
            </div>
            <a href="logout.php" class="btn btn-outline-light btn-sm rounded-circle p-2" title="Keluar">
                <i data-lucide="log-out" style=" height: 18px;"></i>
            </a>
        </div>
    </div>
</nav>

<!-- Sidebar Desktop -->
<aside class="sidebar-desktop d-none d-lg-flex">
    <div class="sidebar-header">
        <i data-lucide="zap" class="text-warning me-3"></i>
        <span class="fw-bold fs-5">Panel Analisa</span>
    </div>
    
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i data-lucide="layout-dashboard"></i> <span> Beranda Utama</span>
        </a>
        <!-- Menu Baru: Manajemen Wajib Pajak -->
        <a href="cari_wp.php" class="sidebar-item <?php echo ($current_page == 'cari_wp.php') ? 'active' : ''; ?>">
            <i data-lucide="search"></i> <span> Cari Wajib Pajak</span>
        </a>
        <a href="monitoring_kolektif.php" class="sidebar-item <?php echo ($current_page == 'monitoring_kolektif.php') ? 'active' : ''; ?>">
            <i data-lucide="pie-chart"></i> <span> Melihat Hasil Anlisa</span>
        </a>
                <a href="dashboard_manager.php" class="sidebar-item <?php echo ($current_page == 'dashboard_manager.php') ? 'active' : ''; ?>">
            <i data-lucide="bar-chart"></i> <span> Dashboard Manager</span>
        </a>
        
        <div class="px-4 py-2 small text-uppercase text-muted opacity-50 sidebar-label" style="font-size: 10px; letter-spacing: 1px;">Sistem</div>
        
        <a href="manajemen_klu.php" class="sidebar-item <?php echo ($current_page =='manajemen_klu.php') ? 'active' : ''; ?>">
            <i data-lucide="settings"></i> <span> Setting KLU</span>
        </a>
        <a href="manajemen_user.php" class="sidebar-item <?php echo ($current_page == 'manajemen_user.php') ? 'active' : ''; ?>">
            <i data-lucide="users"></i> <span> Manajemen User</span>
        </a>
    </div>

    <div class="p-3 border-top mt-auto">
        <div class="d-flex align-items-center text-muted small px-1">
            <i data-lucide="info" class="me-2" style="width: 14px;"></i>
            <span>v2.1</span>   
        </div>
    </div>
</aside>



<!-- Mobile Bottom Navigation -->
<div class="mobile-bottom-nav border-top">
    <div class="nav-item text-center">
        <a href="dashboard.php" class="text-primary text-decoration-none">
            <i data-lucide="layout-dashboard"></i>
            <small class="d-block">Dashboard</small>
        </a>
    </div>
    <div class="nav-item text-center">
        <a href="cari_wp.php" class="text-muted text-decoration-none">
            <i data-lucide="search"></i>
            <small class="d-block">Cari WP</small>
        </a>
    </div>
    <div class="nav-item text-center">
        <!--<a href="#" class="text-muted text-decoration-none" onclick="alert('Fitur SP2DK Mobile Sedang Dikembangkan')"> -->
        <a href="monitoring_kolektif.php" class="text-muted text-decoration-none">
            <i data-lucide="file-text"></i>
            <small class="d-block">Hasil Analisa</small>
        </a>
    </div>
    <div class="nav-item text-center">
        <a href="manajemen_klu.php" class="text-muted text-decoration-none">
            <i data-lucide="settings"></i>
            <small class="d-block">Benchmark KLU</small>
        </a>
    </div>
</div>


<script>
    // Pastikan Ikon Ter-render
    lucide.createIcons();

    // Fungsi Toggle Sidebar
    function toggleSidebar() {
        document.body.classList.toggle('sidebar-mini');
        
        // Simpan status ke localStorage agar awet saat pindah halaman
        const isMini = document.body.classList.contains('sidebar-mini');
        localStorage.setItem('sharp_sidebar_mini', isMini);
    }

    // Load status sidebar saat halaman dimuat
    (function() {
        const savedStatus = localStorage.getItem('sharp_sidebar_mini');
        // Default sidebar mini jika belum ada preference (Sesuai permintaan sebelumnya)
        if (savedStatus === 'true' || savedStatus === null) {
            document.body.classList.add('sidebar-mini');
        }
    })();
</script>