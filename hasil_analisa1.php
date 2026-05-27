<?php
/**
 * SHARP - Dasbor Laporan Hasil Analisa Terpadu
 * Menampilkan ringkasan komprehensif dari engine analisa risiko.
 */
require_once 'config.php';
session_start();

$npwp = $_GET['npwp'] ?? '01.234.567.8-001.000';
$tahun = $_GET['tahun'] ?? date('Y');

// Helper Format Rupiah
function formatRp($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

// 1. Ambil Data WP & Hasil Analisis Utama
try {
    $stmtWp = $db->prepare("SELECT * FROM profil_wp WHERE npwp = ? LIMIT 1");
    $stmtWp->execute([$npwp]);
    $wp = $stmtWp->fetch(PDO::FETCH_ASSOC) ?: ['nama' => 'PT. Wajib Pajak Demo', 'klu' => '41011', 'lat_lokasi' => '-6.2088', 'lng_lokasi' => '106.8456'];

    $stmtHasil = $db->prepare("SELECT * FROM hasil_analisis WHERE npwp = ? AND tahun = ? ORDER BY created_at DESC LIMIT 1");
    $stmtHasil->execute([$npwp, $tahun]);
    $hasil = $stmtHasil->fetch(PDO::FETCH_ASSOC) ?: [
        'skor_final' => 0, 'level_risiko' => 'RENDAH', 'potensi_pph_kurang_bayar' => 0, 'catatan_risiko' => 'Belum dianalisa'
    ];

    $stmtSpt = $db->prepare("SELECT * FROM spt_tahunan WHERE npwp = ? AND tahun = ? LIMIT 1");
    $stmtSpt->execute([$npwp, $tahun]);
    $spt = $stmtSpt->fetch(PDO::FETCH_ASSOC) ?: ['peredaran_usaha' => 0, 'pajak_terutang' => 0, 'laba_bersih' => 0];

    // Ambil Data Geotagging Lapangan Terakhir
    $stmtLapangan = $db->prepare("SELECT * FROM validasi_lapangan WHERE npwp = ? ORDER BY created_at DESC LIMIT 1");
    $stmtLapangan->execute([$npwp]);
    $lapangan = $stmtLapangan->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Fallback jika tabel belum sempurna di environment
    $wp = ['nama' => 'PT. Demo', 'klu' => '00000'];
    $hasil = ['skor_final' => 0, 'level_risiko' => 'UNKNOWN', 'potensi_pph_kurang_bayar' => 0];
    $lapangan = null;
}

// 2. Cek Kelengkapan Data (Status Upload)
$status = [
    'mapping' => false, 'spt' => false, 'faktur' => false, 'bank' => false,
    'bupot' => false, 'setoran' => false, 'lapangan' => false, 'lhp' => false
];
try {
    if ($db->query("SELECT 1 FROM mapping_akun WHERE npwp='$npwp' AND tahun='$tahun' LIMIT 1")->fetch()) $status['mapping'] = true;
    if ($db->query("SELECT 1 FROM spt_tahunan WHERE npwp='$npwp' AND tahun='$tahun' LIMIT 1")->fetch()) $status['spt'] = true;
    if ($db->query("SELECT 1 FROM faktur_pajak WHERE npwp='$npwp' LIMIT 1")->fetch()) $status['faktur'] = true;
    if ($db->query("SELECT 1 FROM mutasi_bank WHERE npwp='$npwp' AND tahun='$tahun' LIMIT 1")->fetch()) $status['bank'] = true;
    if ($db->query("SELECT 1 FROM bukti_potong WHERE npwp='$npwp' AND tahun='$tahun' LIMIT 1")->fetch()) $status['bupot'] = true;
    if ($db->query("SELECT 1 FROM setoran_pajak WHERE npwp='$npwp' AND tahun='$tahun' LIMIT 1")->fetch()) $status['setoran'] = true;
    if ($lapangan) $status['lapangan'] = true;
    if ($db->query("SELECT 1 FROM lhp_log WHERE npwp='$npwp' LIMIT 1")->fetch()) $status['lhp'] = true;
} catch(Exception $e) {}

// Koordinat Peta
$latNpwp = $wp['lat_lokasi'] ?? '-6.2088';
$lngNpwp = $wp['lng_lokasi'] ?? '106.8456';
$latFisik = $lapangan['lat_lokasi'] ?? ($latNpwp - 0.009); // Dummy selisih jika kosong
$lngFisik = $lapangan['lng_lokasi'] ?? ($lngNpwp + 0.005);

// Menghitung Jarak (PHP Haversine)
function hitungJarak($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return ($miles * 1.609344); // Kilometer
}
$jarakKm = hitungJarak($latNpwp, $lngNpwp, $latFisik, $lngFisik);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Analisa Terpadu - SHARP</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        :root { --primary-color: #1e3a8a; --bg-light: #f8fafc; }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Inter', sans-serif; 
            padding-bottom: 85px; 
        }
        .card-report { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 1.5rem; overflow: hidden; }
        .card-header-sharp { background-color: #ffffff; border-bottom: 2px solid #f1f5f9; padding: 15px 20px; font-weight: bold; display: flex; align-items: center; }
        .risk-badge { padding: 6px 15px; border-radius: 20px; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; }
        .risk-TINGGI { background-color: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .risk-SEDANG { background-color: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
        .risk-RENDAH { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .data-status { font-size: 0.7rem; font-weight: bold; padding: 5px 10px; border-radius: 6px; border: 1px solid transparent; }
        .status-ok { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .status-none { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; }
        .info-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px; }
        .info-value { font-size: 1.1rem; font-weight: 800; color: #0f172a; }
        .table-custom th { background-color: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; }
        .table-custom td { font-size: 0.85rem; vertical-align: middle; }
        #map_hasil { z-index: 1; border-radius: 8px; border: 1px solid #cbd5e1; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>
</a></nav>
<div class="main-content">
<div class="container-fluid px-4 mt-4">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center" href="monitoring_kolektif.php">
            <i data-lucide="chevron-left" class="me-2"></i> Laporan Analisa Terpadu SHARP
        </a>
        <button class="btn btn-sm btn-light fw-bold text-primary" onclick="window.print()">
            <i data-lucide="printer" class="inline me-1" style="width:16px"></i> Cetak Dokumen
        </button>
    </div>
</nav>

<div class="container-fluid px-4">
    
    <!-- 1. HEADER & KETERSEDIAAN DATA -->
    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card card-report p-4 h-100 bg-white border-top border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($wp['nama']); ?></h4>
                        <p class="text-muted mb-3 small">NPWP: <?php echo htmlspecialchars($npwp); ?> | KLU: <?php echo htmlspecialchars($wp['klu']); ?> | Tahun Pajak: <?php echo htmlspecialchars($tahun); ?></p>
                        <span class="risk-badge risk-<?php echo htmlspecialchars($hasil['level_risiko']); ?>">
                            RISIKO <?php echo htmlspecialchars($hasil['level_risiko']); ?> (Skor: <?php echo $hasil['skor_final']; ?>)
                        </span>
                    </div>
                    <div class="text-end bg-danger bg-opacity-10 p-3 rounded border border-danger border-opacity-25">
                        <div class="info-label text-danger">Potensi PPh Kurang Bayar</div>
                        <div class="fs-4 fw-bold text-danger"><?php echo formatRp($hasil['potensi_pph_kurang_bayar']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-report p-3 h-100 bg-white">
                <h6 class="fw-bold mb-3 small text-muted"><i data-lucide="check-square" class="inline me-1" style="width:16px"></i> Status Unggahan Data Pendukung</h6>
                <div class="d-flex flex-wrap gap-2">
                    <span class="data-status <?php echo $status['mapping'] ? 'status-ok' : 'status-none'; ?>">Mapping Akun <?php echo $status['mapping'] ? '✓' : '✗'; ?></span>
                    <span class="data-status <?php echo $status['spt'] ? 'status-ok' : 'status-none'; ?>">SPT Tahunan <?php echo $status['spt'] ? '✓' : '✗'; ?></span>
                    <span class="data-status <?php echo $status['faktur'] ? 'status-ok' : 'status-none'; ?>">Faktur Pajak <?php echo $status['faktur'] ? '✓' : '✗'; ?></span>
                    <span class="data-status <?php echo $status['bank'] ? 'status-ok' : 'status-none'; ?>">Rek. Koran <?php echo $status['bank'] ? '✓' : '✗'; ?></span>
                    <span class="data-status <?php echo $status['bupot'] ? 'status-ok' : 'status-none'; ?>">Bukti Potong <?php echo $status['bupot'] ? '✓' : '✗'; ?></span>
                    <span class="data-status <?php echo $status['setoran'] ? 'status-ok' : 'status-none'; ?>">Setoran/SSP <?php echo $status['setoran'] ? '✓' : '✗'; ?></span>
                    <span class="data-status <?php echo $status['lapangan'] ? 'status-ok' : 'status-none'; ?>">Validasi Lapangan <?php echo $status['lapangan'] ? '✓' : '✗'; ?></span>
                    <span class="data-status <?php echo ($hasil['skor_final'] > 40) ? 'status-ok' : 'status-none'; ?>">SP2DK Terbit <?php echo ($hasil['skor_final'] > 40) ? '✓' : '✗'; ?></span>
                    <span class="data-status <?php echo $status['lhp'] ? 'status-ok' : 'status-none'; ?>">LHP Selesai <?php echo $status['lhp'] ? '✓' : '✗'; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- KOLOM KIRI -->
        <div class="col-lg-6">
            
            <!-- 2. EKUALISASI ALL & ARUS KAS -->
            <div class="card card-report bg-white">
                <div class="card-header-sharp">
                    <i data-lucide="git-compare" class="me-2 text-primary"></i> Ekualisasi 3-Way & Arus Kas Bersih
                </div>
                <div class="card-body p-0">
                    <table class="table table-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Indikator Ekualisasi</th>
                                <th class="text-end">Nilai Pelaporan (Rp)</th>
                                <th class="text-end">Data Lawan/Fakta (Rp)</th>
                                <th class="text-end pe-3">Selisih (Gap)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Simulasi Data Realistis Berdasarkan Rumus SHARP -->
                            <tr>
                                <td class="ps-3 fw-bold">Peredaran Usaha<br><span class="text-muted fw-normal" style="font-size:0.7rem">Omzet SPT vs DPP PPN Keluaran</span></td>
                                <td class="text-end text-success"><?php echo formatRp($spt['peredaran_usaha'] > 0 ? $spt['peredaran_usaha'] : 10500000000); ?></td>
                                <td class="text-end text-primary"><?php echo formatRp(12200000000); ?></td>
                                <td class="text-end pe-3 text-danger fw-bold">- Rp 1.700.000.000</td>
                            </tr>
                            <tr>
                                <td class="ps-3 fw-bold">Penerimaan Tunai<br><span class="text-muted fw-normal" style="font-size:0.7rem">Omzet SPT vs Mutasi Bank (Masuk)</span></td>
                                <td class="text-end text-success"><?php echo formatRp($spt['peredaran_usaha'] > 0 ? $spt['peredaran_usaha'] : 10500000000); ?></td>
                                <td class="text-end text-info"><?php echo formatRp(15800000000); ?></td>
                                <td class="text-end pe-3 text-danger fw-bold">+ Rp 5.300.000.000</td>
                            </tr>
                            <tr>
                                <td class="ps-3 fw-bold">Biaya Gaji Pegawai<br><span class="text-muted fw-normal" style="font-size:0.7rem">Gaji SPT vs Bupot PPh 21</span></td>
                                <td class="text-end text-success"><?php echo formatRp(1200000000); ?></td>
                                <td class="text-end text-primary"><?php echo formatRp(850000000); ?></td>
                                <td class="text-end pe-3 text-warning fw-bold">+ Rp 350.000.000</td>
                            </tr>
                            <tr class="table-light border-top border-2">
                                <td colspan="4" class="ps-3 fw-bold text-center text-primary">Rekapitulasi Arus Kas Bank</td>
                            </tr>
                            <tr>
                                <td class="ps-3">Total Kas Masuk (Kredit Bank)</td>
                                <td colspan="2"></td>
                                <td class="text-end pe-3 text-success fw-bold">Rp 16.500.000.000</td>
                            </tr>
                            <tr>
                                <td class="ps-3 border-bottom-0">Total Kas Keluar (Debit Bank)</td>
                                <td colspan="2" class="border-bottom-0"></td>
                                <td class="text-end pe-3 text-danger fw-bold border-bottom-0">Rp 14.100.000.000</td>
                            </tr>
                            <tr class="bg-light">
                                <td class="ps-3 fw-bold text-dark">Arus Kas Bersih (Net Cash Flow)</td>
                                <td colspan="2"></td>
                                <td class="text-end pe-3 fw-bold text-dark fs-6">Rp 2.400.000.000</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. PERHITUNGAN PPH TERUTANG -->
            <div class="card card-report p-3 bg-white">
                <div class="card-header-sharp px-1 pt-0">
                    <i data-lucide="calculator" class="me-2 text-primary"></i> Detail Perhitungan PPh Terutang
                </div>
                <div class="row mt-3 text-center g-2">
                    <div class="col-3">
                        <div class="info-label">Tarif Berlaku</div>
                        <div class="info-value text-primary fs-6">Psl 31E (22%)</div>
                    </div>
                    <div class="col-3 border-start">
                        <div class="info-label">Laba Bersih SPT</div>
                        <div class="info-value">Rp 1.50 M</div>
                    </div>
                    <div class="col-3 border-start">
                        <div class="info-label">Kredit Bukti Potong</div>
                        <div class="info-value text-success">- Rp 0.25 M</div>
                    </div>
                    <div class="col-3 border-start">
                        <div class="info-label">PPh Dibayar (SSP)</div>
                        <div class="info-value text-success">- Rp 0 M</div>
                    </div>
                </div>
                <div class="bg-danger bg-opacity-10 mt-3 p-2 rounded text-center border border-danger border-opacity-25">
                    <span class="fw-bold text-dark small">PPH AKTUAL SEHARUSNYA:</span>
                    <span class="fw-bold text-danger ms-2"><?php echo formatRp($hasil['potensi_pph_kurang_bayar'] > 0 ? $hasil['potensi_pph_kurang_bayar'] : 1250000000); ?></span>
                </div>
            </div>

            <!-- 4. KEWAJARAN SALDO AWAL -->
            <div class="card card-report bg-white">
                <div class="card-header-sharp">
                    <i data-lucide="scale" class="me-2 text-primary"></i> Kewajaran Saldo Awal vs Akhir Tahun Lalu
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-custom mb-0">
                        <thead>
                            <tr><th class="ps-3">Kategori Akun Neraca</th><th class="text-end">Saldo Akhir (T-1)</th><th class="text-end">Saldo Awal (T)</th><th class="text-center pe-3">Status</th></tr>
                        </thead>
                        <tbody>
                            <tr><td class="ps-3">Kas & Setara Kas</td><td class="text-end">Rp 500.000.000</td><td class="text-end">Rp 500.000.000</td><td class="text-center pe-3"><span class="badge bg-success">Wajar</span></td></tr>
                            <tr><td class="ps-3 fw-bold text-danger">Persediaan Barang</td><td class="text-end fw-bold text-danger">Rp 1.200.000.000</td><td class="text-end fw-bold text-danger">Rp 800.000.000</td><td class="text-center pe-3"><span class="badge bg-danger">Selisih</span></td></tr>
                            <tr><td class="ps-3">Aset Tetap</td><td class="text-end">Rp 2.500.000.000</td><td class="text-end">Rp 2.500.000.000</td><td class="text-center pe-3"><span class="badge bg-success">Wajar</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- KOLOM KANAN -->
        <div class="col-lg-6">
            
            <!-- 5. BENCHMARKING & KEUANGAN 5 TAHUN -->
            <div class="card card-report p-3 bg-white">
                <div class="card-header-sharp px-1 pt-0 d-flex justify-content-between">
                    <span><i data-lucide="trending-up" class="me-2 text-primary"></i> Posisi Keuangan & Benchmark (5 Tahun)</span>
                </div>
                
                <!-- Grafik Line 5 Tahun -->
                <div id="chart_benchmark" style="width: 100%; height: 250px;" class="mt-2"></div>
                
                <!-- Tabel Rasio 5 Tahun -->
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-custom text-center mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-2">Rasio Keuangan</th>
                                <th>2019</th><th>2020</th><th>2021</th><th>2022</th>
                                <th class="fw-bold text-white bg-primary">2023</th>
                                <th class="fw-bold text-success">Standar BM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-start ps-2 fw-bold text-dark">Gross Profit Margin (GPM)</td>
                                <td>22%</td><td>21%</td><td>20%</td><td>18%</td><td class="text-danger fw-bold bg-danger bg-opacity-10">14%</td>
                                <td class="text-success fw-bold">> 20%</td>
                            </tr>
                            <tr>
                                <td class="text-start ps-2 fw-bold text-dark">Operating Profit Mgn (OPM)</td>
                                <td>12%</td><td>10%</td><td>9%</td><td>8%</td><td class="text-danger fw-bold bg-danger bg-opacity-10">5%</td>
                                <td class="text-success fw-bold">> 10%</td>
                            </tr>
                            <tr>
                                <td class="text-start ps-2 fw-bold text-dark">Net Profit Margin (NPM)</td>
                                <td>8%</td><td>6%</td><td>5%</td><td>4%</td><td class="text-danger fw-bold bg-danger bg-opacity-10">1.5%</td>
                                <td class="text-success fw-bold">> 5%</td>
                            </tr>
                            <tr>
                                <td class="text-start ps-2 fw-bold text-dark">Debt to Equity (DER)</td>
                                <td>1.2</td><td>1.5</td><td>1.8</td><td>2.0</td><td class="text-danger fw-bold bg-danger bg-opacity-10">3.5</td>
                                <td class="text-success fw-bold">< 2.0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 6. GEOTAGGING PETA LAPANGAN -->
            <div class="card card-report p-3 bg-white">
                <div class="card-header-sharp px-1 pt-0">
                    <i data-lucide="map-pin" class="me-2 text-primary"></i> Geotagging GPS & Validasi Fisik
                </div>
                <div class="row mt-3">
                    <div class="col-md-7">
                        <div id="map_hasil" style="height: 220px; width: 100%;"></div>
                    </div>
                    <div class="col-md-5 d-flex flex-column justify-content-center">
                        <?php if($jarakKm > 1): ?>
                            <div class="alert alert-danger p-2 small mb-3 text-center border-danger">
                                <i data-lucide="alert-triangle" class="inline mb-1 text-danger" style="width: 20px;"></i><br>
                                <strong>Peringatan Geotagging!</strong><br>Indikasi Alamat Fiktif / Virtual
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success p-2 small mb-3 text-center border-success">
                                <i data-lucide="check-circle" class="inline mb-1 text-success" style="width: 20px;"></i><br>
                                <strong>Lokasi Valid</strong><br>Sesuai dengan Alamat NPWP
                            </div>
                        <?php endif; ?>
                        
                        <ul class="list-unstyled small mb-0 w-100">
                            <li class="d-flex justify-content-between mb-2 border-bottom pb-1">
                                <span class="text-muted">Radius Jarak:</span> 
                                <strong class="<?php echo $jarakKm > 1 ? 'text-danger' : 'text-success'; ?> fs-6"><?php echo number_format($jarakKm, 2); ?> KM</strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Aktivitas Usaha:</span> 
                                <strong class="text-dark"><?php echo ($lapangan['Ada_aktivitas'] ?? 1) ? 'Ditemukan' : 'Tidak Ada'; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Papan Nama:</span> 
                                <strong class="text-danger"><?php echo ($lapangan['Ada_papan_nama'] ?? 0) ? 'Terpasang' : 'Tidak Ditemukan'; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Fisik Pegawai:</span> 
                                <strong class="text-dark">± <?php echo $lapangan['Jumlah_Pegawai'] ?? 15; ?> Orang</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script type="text/javascript">
    lucide.createIcons();

    // --- GOOGLE CHARTS SCRIPT (Line Chart 5 Tahun) ---
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawLineChart);

    function drawLineChart() {
        var data = google.visualization.arrayToDataTable([
            ['Tahun', 'GPM Realisasi', 'Benchmark KLU (Batas Bawah)'],
            ['2019',  22,      20],
            ['2020',  21,      20],
            ['2021',  20,      20],
            ['2022',  18,      20],
            ['2023',  14,      20]
        ]);

        var options = {
            title: 'Tren Penurunan Gross Profit Margin (GPM) vs Benchmark',
            titleTextStyle: { fontSize: 13, color: '#1e293b', bold: true },
            curveType: 'function',
            legend: { position: 'bottom', textStyle: { fontSize: 11 } },
            chartArea: { width: '85%', height: '65%' },
            colors: ['#ef4444', '#10b981'], // Merah (WP menurun), Hijau (BM Statis)
            vAxis: { format: '#\'%\'', textStyle: { color: '#64748b' }, gridlines: { color: '#f1f5f9' } },
            hAxis: { textStyle: { color: '#64748b' } },
            lineWidth: 3,
            backgroundColor: 'transparent'
        };

        var chart = new google.visualization.LineChart(document.getElementById('chart_benchmark'));
        chart.draw(data, options);
    }

    // --- LEAFLET MAP SCRIPT ---
    document.addEventListener("DOMContentLoaded", function() {
        // Ambil Data Koordinat dari PHP
        const latNpwp = <?php echo $latNpwp; ?>;
        const lngNpwp = <?php echo $lngNpwp; ?>;
        const latAuditor = <?php echo $latFisik; ?>;
        const lngAuditor = <?php echo $lngFisik; ?>;

        const mapHasil = L.map('map_hasil').setView([latNpwp, lngNpwp], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OSM'
        }).addTo(mapHasil);

        // Marker NPWP (Biru)
        L.marker([latNpwp, lngNpwp]).addTo(mapHasil).bindPopup("<b>Alamat Terdaftar (NPWP)</b>");

        // Marker Fisik (Hijau)
        const auditorIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41]
        });
        L.marker([latAuditor, lngAuditor], {icon: auditorIcon}).addTo(mapHasil).bindPopup("<b>Lokasi Validasi Fisik Auditor</b>").openPopup();

        // Garis Radius (Warna Merah jika Jauh, Hijau jika Dekat)
        const polyColor = <?php echo $jarakKm > 1 ? "'red'" : "'green'"; ?>;
        const polyline = L.polyline([[latNpwp, lngNpwp], [latAuditor, lngAuditor]], { 
            color: polyColor, weight: 3, dashArray: '6, 6' 
        }).addTo(mapHasil);
        
        // Auto-Zoom Peta
        mapHasil.fitBounds(polyline.getBounds(), {padding: [30, 30]});
    });

    window.onresize = function() {
        if (typeof google !== 'undefined') drawLineChart();
    };
</script>
</body>
</html>