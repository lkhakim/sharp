<?php
/**
 * SHARP - Dashboard Utama (Mobile-Friendly)
 * Entry point aplikasi dengan data real-time dari database.
 */
session_start();
require_once 'config.php';

// Proteksi akses, tendang ke login jika belum ada session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


// Simulasi Data Makro KPP (Ditarik dari DB)
$fin = [
    'total_potensi' => 45800000000,
    'total_realisasi' => 12500000000,
    'pencapaian' => 27.2
];

$tahun_aktif = $_GET['tahun'] ?? "semua"; // Default ke tahun laporan terakhir (T-1)
if ($tahun_aktif=="semua"){
    $filter_tahun = 2000;
    $tahun_aktif = date('Y');
}else{
    $filter_tahun = $tahun_aktif;
}

try {
    // 1. Stats Total WP Terdaftar
    $stmtWp = $db->query("SELECT COUNT(*) FROM profil_wp");
    $statTotalWp = $stmtWp->fetchColumn() ?: 0;

    // 2. Stats WP Selesai Dianalisis
    $stmtAnalyzed = $db->prepare("SELECT COUNT(DISTINCT npwp) FROM hasil_analisis WHERE tahun BETWEEN ? AND ?");
    $stmtAnalyzed->execute([$filter_tahun, $tahun_aktif]);
    $statAnalyzed = $stmtAnalyzed->fetchColumn() ?: 0;

    // 3. Stats Risiko Tinggi
    $stmtHighRisk = $db->prepare("SELECT COUNT(*) FROM hasil_analisis WHERE level_risiko = 'TINGGI' AND tahun BETWEEN ? AND ?");
    $stmtHighRisk->execute([$filter_tahun,$tahun_aktif]);
    $statHighRisk = $stmtHighRisk->fetchColumn() ?: 0;

    // 4. Stats Penerimaan (Total Setoran)
    $stmtRev = $db->prepare("SELECT SUM(nilai_setoran) FROM setoran_pajak WHERE tahun BETWEEN ? AND ?");
    $stmtRev->execute([$filter_tahun,$tahun_aktif]);
    $statRevenue = $stmtRev->fetchColumn() ?: 0;
    
    // Helper Format Miliar/Juta
    function formatSingkat($angka) {
        if ($angka >= 1000000000) return number_format($angka / 1000000000, 2, ',', '.') . ' M';
        if ($angka >= 1000000) return number_format($angka / 1000000, 2, ',', '.') . ' Jt';
        return number_format($angka, 0, ',', '.');
    }
    $revenueFormatted = formatSingkat($statRevenue);

    // 5. Data Chart Bar (Tren Risiko 5 Tahun Terakhir)
    $stmtBar = $db->query("
        SELECT tahun, 
               SUM(CASE WHEN level_risiko = 'RENDAH' THEN 1 ELSE 0 END) as rendah,
               SUM(CASE WHEN level_risiko = 'SEDANG' THEN 1 ELSE 0 END) as sedang,
               SUM(CASE WHEN level_risiko = 'TINGGI' THEN 1 ELSE 0 END) as tinggi
        FROM hasil_analisis 
        GROUP BY tahun 
        ORDER BY tahun ASC LIMIT 5
    ");
    $dataBar = $stmtBar->fetchAll(PDO::FETCH_ASSOC);

    // 6. Data Chart Pie (Top 5 KLU Terbesar berdasarkan Profil WP)
    $stmtPie = $db->query("
        SELECT klu, COUNT(*) as jumlah 
        FROM profil_wp 
        GROUP BY klu 
        ORDER BY jumlah DESC LIMIT 5
    ");
    $dataPie = $stmtPie->fetchAll(PDO::FETCH_ASSOC);

    // 7. Tabel WP Prioritas (Butuh Tindak Lanjut - Risiko Tinggi & Sedang)
    $stmtTable = $db->prepare("
        SELECT p.nama, p.npwp, p.klu, h.skor_risiko, h.level_risiko 
        FROM profil_wp p 
        JOIN hasil_analisis h ON p.npwp = h.npwp 
        WHERE h.level_risiko IN ('TINGGI', 'SEDANG') AND h.tahun = ? 
        ORDER BY h.skor_risiko DESC LIMIT 10
    ");
    $stmtTable->execute([$tahun_aktif]);
    $priorityWPs = $stmtTable->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Fallback jika database belum lengkap/error
    $statTotalWp = 0; $statAnalyzed = 0; $statHighRisk = 0; $revenueFormatted = "0";
    $dataBar = []; $dataPie = []; $priorityWPs = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHARP - Sistem Hybrid Analisa Risiko Pajak</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        :root {
            --primary-color: #1e3a8a; 
            --secondary-color: #f59e0b;
            --bg-light: #f8fafc;
        }
        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 85px; /* Memberi ruang untuk mobile bottom nav */
        }
        .card-stat {
            border-radius: 15px;
            border: none;
            transition: transform 0.2s;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stat-icon { 
            width: 45px; height: 45px; 
            border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; 
        }
        .card-stat:hover { transform: translateY(-5px); }
        .risiko-TINGGI { border-left: 5px solid #ef4444; }
        .risiko-SEDANG { border-left: 5px solid #f59e0b; }
        .risiko-RENDAH { border-left: 5px solid #10b981; }
        
        /* Sidebar desktop & Bottom nav mobile */
        @media (max-width: 768px) {
            .desktop-nav { display: none; }
            .mobile-bottom-nav {
                position: fixed; bottom: 0; width: 100%;
                background: white; display: flex; justify-content: space-around;
                padding: 10px 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 1000;
            }
        }

        @media (min-width: 769px) { .mobile-bottom-nav { display: none; } }
        .btn-sharp { background-color: var(--primary-color); color: white; }
        .btn-sharp:hover { background-color: #172554; color: white; }
        
        /* Styling untuk AI Modal Content */
        #aiContent h3, #aiContent h4 { color: var(--primary-color); font-size: 1.1rem; font-weight: bold; margin-top: 1rem; }
        #aiContent ul { padding-left: 1.2rem; }
        #aiContent li { margin-bottom: 0.5rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>
<div class="main-content">
<div class="container my-4">
    <!-- Header Dashboard -->
    <div class="row mb-4">
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h4 class="fw-bold m-0">Ringkasan Pengawasan</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-warning btn-sm fw-bold shadow-sm" onclick="generateExecutiveSummary()">
                    <i data-lucide="sparkles" class="inline me-1" style="width: 16px;"></i> Ringkasan AI ✨
                </button>
                <div class="dropdown">
                    <button class="btn btn-white btn-sm border dropdown-toggle bg-white" type="button" data-bs-toggle="dropdown">
                        Tahun Pajak: <?php echo $tahun_aktif; ?>
                    </button>
                    <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?tahun=semua">Semua</a></li>
                        <?php for($y=date('Y'); $y>=2022; $y--): ?>
                            <li><a class="dropdown-item" href="?tahun=<?= $y ?>"><?= $y ?></a></li>
                        <?php endfor; ?>
                    </ul>
                </div>
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
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4" id="dashboardStats">
        <div class="col-md-4">
            <div class="card card-stat p-3 bg-white h-100 border-start border-3 border-dark">
                <div class="stat-icon bg-dark bg-opacity-10 text-dark mb-2">
                    <i data-lucide="users" style="width: 20px;"></i>
                </div>
                <small class="text-muted">Total WP Terdaftar</small>
                <h3 class="fw-bold mb-0 text-dark" id="stat-total"><?php echo number_format($statTotalWp); ?></h3>
                <small class="text-primary fw-bold">Entitas Pengawasan</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat p-3 bg-white h-100 border-start border-3 border-success">
                <div class="stat-icon bg-success bg-opacity-10 text-success mb-2">
                    <i data-lucide="shield-check" style="width: 20px;"></i>
                </div>    
                <small class="text-muted">Analisis Selesai <?=$filter_tahun?> - <?=$tahun_aktif?> </small>
                <h3 class="fw-bold mb-0 text-success" id="stat-analyzed"><?php echo number_format($statAnalyzed); ?></h3>
                <small class="text-success fw-bold">Dari Total WP</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat p-3 bg-white h-100 border-start border-3 border-danger">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger mb-2">
                    <i data-lucide="triangle-alert" style="width: 20px;"></i>
                </div>
                <small class="text-muted">Risiko Tinggi</small>
                <h3 class="fw-bold mb-0 text-danger" id="stat-highrisk"><?php echo number_format($statHighRisk); ?></h3>
                <small class="text-danger fw-bold">Butuh SP2DK</small>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-7">
            <div class="card card-stat p-3 h-100 bg-white">
                <h6 class="fw-bold mb-3 text-primary">Tren Analisis Kepatuhan Berbasis Risiko</h6>
                <div id="chart_div_bar" style="width: 100%; height: 300px;"></div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card card-stat p-3 h-100 bg-white">
                <h6 class="fw-bold mb-3 text-primary">Sebaran 5 KLU Terbesar</h6>
                <div id="chart_div_pie" style="width: 100%; height: 300px;"></div>
            </div>
        </div>
    </div>

    <!-- Daftar WP Prioritas (Hasil Analisa) -->
    <div class="card card-stat bg-white mb-5">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
            <h6 class="fw-bold m-0 text-danger">Wajib Pajak Prioritas (Tindak Lanjut)</h6>
            <a href="monitoring_kolektif.php" class="btn btn-sharp btn-sm">
                Lihat Semua Data
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Nama WP / NPWP</th>
                            <th>Kode KLU</th>
                            <th>Skor Risiko</th>
                            <th>Level</th>
                            <th class="text-end pe-3">Aksi & Insight</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($priorityWPs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Belum ada WP Risiko Tinggi/Sedang untuk Tahun <?php echo $tahun_aktif; ?>.</td>
                        </tr>
                        <?php else: foreach($priorityWPs as $pwp): ?>
                        <tr class="risiko-<?php echo $pwp['level_risiko']; ?>">
                            <td class="ps-3">
                                <span class="fw-bold d-block text-truncate text-dark" style="max-width: 200px;"><?php echo htmlspecialchars($pwp['nama']); ?></span>
                                <small class="text-muted"><?php echo htmlspecialchars($pwp['npwp']); ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($pwp['klu'] ?? '-'); ?></span></td>
                            <td>
                                <?php 
                                    $badgeClass = $pwp['level_risiko'] == 'TINGGI' ? 'bg-danger' : 'bg-warning text-dark';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> px-2 py-1"><?php echo $pwp['skor_risiko']; ?></span>
                            </td>
                            <td>
                                <?php 
                                    $textClass = $pwp['level_risiko'] == 'TINGGI' ? 'text-danger' : 'text-warning';
                                ?>
                                <span class="<?php echo $textClass; ?> fw-bold small"><?php echo $pwp['level_risiko']; ?></span>
                            </td>
                            <td class="text-end pe-3">
                                <!--
                                <button class="btn btn-outline-warning btn-sm rounded-pill mb-1 d-block w-100" 
                                    onclick="analyzeWP('<?php echo htmlspecialchars(addslashes($pwp['nama'])); ?>', '<?php echo htmlspecialchars(addslashes($pwp['npwp'])); ?>', '<?php echo htmlspecialchars(addslashes($pwp['klu'])); ?>', <?php echo $pwp['skor_final']; ?>)">
                                    <i data-lucide="sparkles" class="inline" style="width:14px;"></i> AI Insight ✨
                                </button>
                                -->
                                <a href="profil_wp.php?npwp=<?php echo urlencode($pwp['npwp']); ?>" class="btn btn-outline-primary btn-sm rounded-pill d-block w-100">Lihat Profil</a>
                                <a href="hasil_analisa.php?npwp=<?php echo urlencode($pwp['npwp']); ?>&tahun=<?php echo urlencode($tahun_aktif); ?>" class="btn btn-outline-primary btn-sm rounded-pill d-block w-100">Lihat Analisa</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modal Bantuan AI Gemini -->
<div class="modal fade" id="geminiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="geminiModalTitle">
                    <i data-lucide="sparkles" class="inline me-2 text-warning"></i> SHARP AI Assistant
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div id="aiLoading" class="text-center py-5">
                    <div class="spinner-border text-warning mb-3" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="text-primary fw-bold">Gemini sedang menganalisis data...</h5>
                    <p class="text-muted small">Memproses triliunan parameter pajak untuk Anda.</p>
                </div>
                <div id="aiContent" class="bg-white p-3 rounded shadow-sm" style="display: none; font-size: 0.95rem; color: #334155;">
                    <!-- Konten dari AI -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script type="text/javascript">
    // Inisialisasi Icon Lucide
    lucide.createIcons();

    // Terima Data dari PHP
    const rawDataBar = <?php echo json_encode($dataBar); ?>;
    const rawDataPie = <?php echo json_encode($dataPie); ?>;

    // Google Charts Implementation
    google.charts.load('current', {'packages':['corechart', 'bar']});
    google.charts.setOnLoadCallback(drawCharts);

    function drawCharts() {
        // --- 1. Dynamic Bar Chart ---
        var dataBarArray = [['Tahun', 'Rendah', 'Sedang', 'Tinggi']];
        if (rawDataBar.length === 0) {
            dataBarArray.push(['<?php echo $tahun_aktif; ?>', 0, 0, 0]); // Fallback
        } else {
            rawDataBar.forEach(item => {
                dataBarArray.push([
                    item.tahun.toString(), 
                    parseInt(item.rendah), 
                    parseInt(item.sedang), 
                    parseInt(item.tinggi)
                ]);
            });
        }
        var dataBar = google.visualization.arrayToDataTable(dataBarArray);
        var optionsBar = {
            chartArea: {width: '80%', height: '70%'},
            legend: { position: 'top', alignment: 'start' },
            colors: ['#10b981', '#f59e0b', '#ef4444'],
            isStacked: true,
            vAxis: { gridlines: { color: '#f1f5f9' }, minValue: 0 }
        };
        var chartBar = new google.visualization.ColumnChart(document.getElementById('chart_div_bar'));
        chartBar.draw(dataBar, optionsBar);

        // --- 2. Dynamic Pie Chart ---
        var dataPieArray = [['KLU', 'Jumlah WP']];
        if (rawDataPie.length === 0) {
            dataPieArray.push(['Belum Ada Data', 1]); // Fallback
        } else {
            rawDataPie.forEach(item => {
                dataPieArray.push([
                    item.klu ? "KLU " + item.klu.toString() : 'Lainnya', 
                    parseInt(item.jumlah)
                ]);
            });
        }
        var dataPie = google.visualization.arrayToDataTable(dataPieArray);
        var optionsPie = {
            pieHole: 0.4,
            colors: ['#1e3a8a', '#3b82f6', '#93c5fd', '#bfdbfe', '#cbd5e1'],
            legend: { position: 'bottom' },
            chartArea: {width: '90%', height: '80%'}
        };
        var chartPie = new google.visualization.PieChart(document.getElementById('chart_div_pie'));
        chartPie.draw(dataPie, optionsPie);
    }

    // Responsive Chart Resize
    window.onresize = function() { drawCharts(); };

    const geminiModal = new bootstrap.Modal(document.getElementById('geminiModal'));

    async function fetchGeminiWithBackoff(promptText) {
        const apiKey = ""; // Disuntikkan otomatis pada runtime / environment
        const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`;
        
        const payload = { contents: [{ parts: [{ text: promptText }] }] };
        let retries = 5; let delay = 1000;

        for (let i = 0; i < retries; i++) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                return result.candidates?.[0]?.content?.parts?.[0]?.text || "Gagal mengekstrak teks dari respons AI.";
            } catch (e) {
                if (i === retries - 1) {
                    console.error("Gemini API failed:", e);
                    return "<p class='text-danger fw-bold'>Terjadi kesalahan koneksi ke Gemini API. Pastikan kunci API valid.</p>";
                }
                await new Promise(res => setTimeout(res, delay));
                delay *= 2;
            }
        }
    }

    function showAILoading(title) {
        document.getElementById('geminiModalTitle').innerHTML = `<i data-lucide="sparkles" class="inline me-2 text-warning"></i> ${title}`;
        lucide.createIcons();
        document.getElementById('aiLoading').style.display = 'block';
        document.getElementById('aiContent').style.display = 'none';
        document.getElementById('aiContent').innerHTML = '';
        geminiModal.show();
    }

    function hideAILoading(htmlContent) {
        document.getElementById('aiLoading').style.display = 'none';
        const contentDiv = document.getElementById('aiContent');
        contentDiv.style.display = 'block';
        contentDiv.innerHTML = htmlContent.replace(/```html/g, '').replace(/```/g, '');
    }

    async function generateExecutiveSummary() {
        showAILoading("Ringkasan Eksekutif AI");
        const totalWP = document.getElementById('stat-total').innerText;
        const analyzed = document.getElementById('stat-analyzed').innerText;
        const highRisk = document.getElementById('stat-highrisk').innerText;
        const revenue = document.getElementById('stat-revenue').innerText;

        const prompt = `Anda adalah Asisten AI untuk Kepala Kantor Pelayanan Pajak. 
        Berdasarkan metrik pengawasan tahun <?php echo $tahun_aktif; ?>:
        - Total Wajib Pajak Terdaftar: ${totalWP}
        - Wajib Pajak Selesai Dianalisis: ${analyzed}
        - WP Risiko Tinggi Ditemukan: ${highRisk} entitas
        - Penerimaan Bruto Tercatat: ${revenue}
        
        Buat ringkasan eksekutif (maks 2 paragraf) tentang kinerja pengawasan ini. Berikan 2 rekomendasi strategis konkret untuk menindaklanjuti WP risiko tinggi tersebut. 
        Gunakan tag HTML sederhana (seperti <h3>, <p>, <ul>, <li>, <b>) tanpa markdown backticks.`;

        const responseHTML = await fetchGeminiWithBackoff(prompt);
        hideAILoading(responseHTML);
    }

    async function analyzeWP(nama, npwp, klu, skor) {
        showAILoading(`AI Profiling: ${nama}`);
        const prompt = `Anda adalah Auditor Pajak Senior. Saya sedang mengawasi Wajib Pajak:
        - Nama WP: ${nama}
        - NPWP: ${npwp}
        - Sektor Usaha (KLU): ${klu}
        - Skor Risiko Ekualisasi: ${skor}/100
        
        Buatkan brief singkat untuk Auditor lapangan. 
        1. Sebutkan potensi Modus Penghindaran Pajak yang umum di sektor klasifikasi KLU "${klu}".
        2. Berikan 3 langkah taktis pemeriksaan dokumen apa saja yang harus divalidasi.
        Gunakan tag HTML (<h4>, <p>, <ul>, <li>, <b>) tanpa markdown backticks.`;

        const responseHTML = await fetchGeminiWithBackoff(prompt);
        hideAILoading(responseHTML);
    }
</script>
</body>
</html>