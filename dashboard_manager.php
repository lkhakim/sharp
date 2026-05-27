<?php
/**
 * SHARP - Dashboard Eksekutif (Mobile Friendly)
 * Akses khusus Manager untuk memantau performa KPP secara makro.
 */
require_once 'config.php';
session_start();

// Proteksi: Hanya Manager yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit;
}

$tahun = $_GET['tahun'] ?? date('Y');

// Simulasi Data Makro KPP (Ditarik dari DB)
$fin = [
    'total_potensi' => 45800000000,
    'total_realisasi' => 12500000000,
    'pencapaian' => 27.2
];

$sp2dk = [
    'total_wp_risiko' => 142,
    'terbit_sp2dk' => 85,
    'selesai_lhp' => 12
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - SHARP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        :root { 
            --primary-color: #1e3a8a; 
            --secondary-color: #f59e0b;
            --bg-light: #f8fafc; 
        }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Inter', sans-serif; 
            padding-bottom: 80px; 
        }
        
        /* Nav Header Samakan dengan index.php */
        .navbar-sharp {
            background-color: var(--primary-color);
            color: white;
        }
        
        .card-dash { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        }
        
        .stat-icon { 
            width: 45px; height: 45px; 
            border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; 
        }
        
        /* Mobile Bottom Navigation */
        .mobile-bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: white; display: flex; justify-content: space-around;
            padding: 12px 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .mobile-bottom-nav a { text-decoration: none; color: #64748b; font-size: 11px; text-align: center; }
        .mobile-bottom-nav a.active { color: var(--primary-color); }
        
        /* Hide Desktop Elements on Mobile */
        @media (max-width: 768px) {
            .display-6 { font-size: 1.5rem; }
            .desktop-nav { display: none; }
        }
        @media (min-width: 769px) {
            .mobile-bottom-nav { display: none; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">

<div class="container my-4 px-3">
    <!-- Filter TA & Title -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold m-0">Ringkasan Eksekutif</h4>
            <div class="dropdown">
                <button class="btn btn-white btn-sm border dropdown-toggle fw-bold" type="button" data-bs-toggle="dropdown">
                    Tahun Pajak: <?php echo $tahun; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="?tahun=2024">2024</a></li>
                    <li><a class="dropdown-item" href="?tahun=2023">2023</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Financial Performance Card -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card card-dash p-4 bg-primary text-white shadow-lg" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%) !important;">
                <div class="d-flex justify-content-between mb-3">
                    <h6 class="fw-bold m-0 opacity-75">Realisasi Recovery Potensi</h6>
                    <i data-lucide="trending-up" class="text-warning"></i>
                </div>
                <h2 class="fw-bold mb-1">Rp <?php echo number_format($fin['total_realisasi']/1000000000, 1); ?> M</h2>
                <div class="progress mb-2" style="height: 10px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                    <div class="progress-bar bg-warning" style="width: <?php echo $fin['pencapaian']; ?>%"></div>
                </div>
                <div class="d-flex justify-content-between">
                    <small class="opacity-75">Target: Rp <?php echo number_format($fin['total_potensi']/1000000000, 1); ?> M</small>
                    <small class="fw-bold"><?php echo $fin['pencapaian']; ?>%</small>
                </div>
            </div>
        </div>

        <!-- Monitoring Stats -->
        <div class="col-6">
            <div class="card card-dash p-3 bg-white h-100 risiko-tinggi" style="border-left: 5px solid #ef4444;">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger mb-2">
                    <i data-lucide="alert-circle" style="width: 20px;"></i>
                </div>
                <small class="text-muted d-block">WP Risiko Tinggi</small>
                <h4 class="fw-bold mb-0 text-danger"><?php echo $sp2dk['total_wp_risiko']; ?></h4>
            </div>
        </div>
        <div class="col-6">
            <div class="card card-dash p-3 bg-white h-100" style="border-left: 5px solid #f59e0b;">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning mb-2">
                    <i data-lucide="file-text" style="width: 20px;"></i>
                </div>
                <small class="text-muted d-block">SP2DK Terbit</small>
                <h4 class="fw-bold mb-0 text-warning"><?php echo $sp2dk['terbit_sp2dk']; ?></h4>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-7">
            <div class="card card-dash p-3 bg-white">
                <h6 class="fw-bold mb-3">Penerimaan per Sektor KLU</h6>
                <div id="chart_revenue" style="width: 100%; height: 250px;"></div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card card-dash p-3 bg-white">
                <h6 class="fw-bold mb-3">Status Penyelesaian</h6>
                <div id="chart_status" style="width: 100%; height: 250px;"></div>
            </div>
        </div>
    </div>

    <!-- AI Insights -->
    <button class="btn btn-dark w-100 py-3 mb-5 fw-bold shadow-sm d-flex align-items-center justify-content-center" onclick="alert('AI sedang menyusun laporan manajerial...')">
        <i data-lucide="sparkles" class="me-2 text-warning"></i> Generate AI Executive Report ✨
    </button>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawCharts);

    function drawCharts() {
        var dataRev = google.visualization.arrayToDataTable([
            ['Sektor', 'Miliar', { role: 'style' }],
            ['Konstruksi', 5.2, '#1e3a8a'], 
            ['Dagang', 3.8, '#3b82f6'], 
            ['Jasa', 2.1, '#60a5fa'], 
            ['Lainnya', 1.4, '#93c5fd']
        ]);
        var chartRev = new google.visualization.ColumnChart(document.getElementById('chart_revenue'));
        chartRev.draw(dataRev, { 
            legend: 'none', 
            chartArea: {width: '85%', height: '70%'},
            vAxis: { gridlines: { color: '#f1f5f9' } }
        });

        var dataStat = google.visualization.arrayToDataTable([
            ['Status', 'Jumlah'],
            ['Belum SP2DK', 45], ['Proses SP2DK', 12], ['LHP/Selesai', 28]
        ]);
        var chartStat = new google.visualization.PieChart(document.getElementById('chart_status'));
        chartStat.draw(dataStat, { 
            pieHole: 0.5, 
            colors: ['#ef4444', '#f59e0b', '#10b981'], 
            chartArea: {width: '90%', height: '80%'},
            legend: { position: 'bottom' }
        });
    }
</script>
</body>
</html>