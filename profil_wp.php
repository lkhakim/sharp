<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$npwp = $_GET['npwp'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($npwp)) {
    header("Location: manajemen_wp.php");
    exit;
}

// Fetch Master Data WP dengan uraian KLU
$sql = "SELECT p.*, b.nama_klasifikasi_usaha, b.npm as benchmark_npm 
        FROM profil_wp p 
        LEFT JOIN benchmark_klu b ON p.klu = b.klu 
        WHERE p.npwp = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$npwp]);
$wp = $stmt->fetch();

if (!$wp) {
    die("Wajib Pajak dengan NPWP tersebut tidak ditemukan.");
}

// Fetch Hasil Analisa Terakhir
$stmtH = $db->prepare("SELECT * FROM hasil_analisis WHERE npwp = ? AND tahun = ? ORDER BY id DESC LIMIT 1");
$stmtH->execute([$npwp, $tahun]);
$analisa_raw = $stmtH->fetch();
$analisa_data = json_decode($analisa_raw['data_json'] ?? '', true) ?? [];

// Fetch Validasi Lapangan (Single Record per WP)
$stmtV = $db->prepare("SELECT * FROM validasi_lapangan WHERE npwp = ? ORDER BY created_at DESC LIMIT 1");
$stmtV->execute([$npwp]);
$audit = $stmtV->fetch();

// Fetch Daftar Pemilik
$stmtP = $db->prepare("SELECT * FROM daftar_pemilik WHERE npwp_perusahaan = ? ORDER BY nilai_saham DESC");
$stmtP->execute([$npwp]);
$pemilik = $stmtP->fetchAll();

// Fetch Status Upload Data per Tahun (3 Tahun Terakhir)
$years_to_check = [];
for($i=0; $i<3; $i++) $years_to_check[] = $tahun - $i;

$upload_status = [];
foreach ($years_to_check as $y) {
    $upload_status[$y] = [
        'spt' => $db->query("SELECT 1 FROM spt_tahunan WHERE npwp='$npwp' AND tahun='$y' LIMIT 1")->fetch() ? true : false,
        'faktur' => $db->query("SELECT 1 FROM faktur_pajak WHERE npwp='$npwp' AND tahun='$y' LIMIT 1")->fetch() ? true : false,
        'bank' => $db->query("SELECT 1 FROM mutasi_bank WHERE npwp='$npwp' AND tahun='$y' LIMIT 1")->fetch() ? true : false,
        'akun' => $db->query("SELECT 1 FROM mapping_akun WHERE npwp='$npwp' AND tahun='$y' LIMIT 1")->fetch() ? true : false,
        'bupot' => $db->query("SELECT 1 FROM bukti_potong WHERE npwp='$npwp' AND tahun='$y' LIMIT 1")->fetch() ? true : false,
        'setoran' => $db->query("SELECT 1 FROM setoran_pajak WHERE npwp='$npwp' AND tahun='$y' LIMIT 1")->fetch() ? true : false,
        'ilap' => $db->query("SELECT 1 FROM data_ilap WHERE npwp='$npwp' AND tahun='$y' LIMIT 1")->fetch() ? true : false
    ];
}

catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'] ?? 'Unknown', 'Profil WP', "Melihat Profil Lengkap WP (360 View): " . $wp['nama']);

// Metrics for Dashboard
$omzet_spt = $analisa_data['simulation']['penjualan']['matching']['spt'] ?? 0;
$omzet_faktur = $analisa_data['simulation']['penjualan']['matching']['faktur'] ?? 0;
$omzet_pembukuan = $analisa_data['simulation']['penjualan']['matching']['pembukuan'] ?? 0;
$omzet_ilap = $analisa_data['simulation']['penjualan']['matching']['ilap'] ?? 0;
$skor_final = $analisa_raw['skor_final'] ?? 0;
$skor_validasi = $analisa_raw['skor_validasi'] ?? 0;
$skor_risiko = $analisa_raw['skor_risiko'] ?? 0;
$level_risiko = $analisa_raw['level_risiko'] ?? 'RENDAH';


function toRpShort(float $num) {
    if ($num >= 1000000000) return number_format($num / 1000000000, 1) . ' Milyar';
    if ($num >= 1000000) return number_format($num / 1000000, 1) . ' Juta';
    return number_format($num, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil 360° - <?= htmlspecialchars($wp['nama']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { 
            --primary: #0f172a; 
            --accent: #3b82f6;
            --risk-low: #10b981;
            --risk-medium: #f59e0b;
            --risk-high: #ef4444;
            --bg-subtle: #f8fafc;
        }
        body { background-color: #f1f5f9; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        .main-content { padding: 24px; transition: margin-left 0.3s; }
        @media (min-width: 992px) { .main-content { margin-left: 280px; } }
        
        .glass-card { background: white; border-radius: 16px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .stat-card { padding: 20px; border-radius: 16px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-4px); }
        
        .risk-ring { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.5rem; position: relative; }
        .risk-ring::after { content: ''; position: absolute; inset: 0; border-radius: 50%; border: 6px solid #e2e8f0; }
        .risk-ring .value { z-index: 1; }
        
        .nav-pills-custom .nav-link { border-radius: 10px; color: #64748b; font-weight: 600; margin-right: 8px; border: 1px solid transparent; }
        .nav-pills-custom .nav-link.active { background: var(--primary); color: white; }
        .nav-pills-custom .nav-link:not(.active):hover { background: #e2e8f0; }

        .metric-label { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .metric-value { font-size: 1.1rem; font-weight: 700; color: #1e293b; }
        
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .gap-indicator { font-size: 0.7rem; font-weight: 600; padding: 2px 8px; border-radius: 12px; }
        
        #map-360 { height: 350px; border-radius: 16px; }
        .audit-photo { width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 12px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        
        <!-- Header 360° -->
        <div>
                <h3 class="fw-800 m-0">360° Information Profile</h3>
                <p class="text-muted">Comprehensive Audit & Risk Overview for Taxpayer</p>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div></div>    
            <div class="d-flex gap-2">
                <select class="form-select border-0 shadow-sm" style="width: 200px;" onchange="location.href='?npwp=<?= $npwp ?>&tahun='+this.value">
                    <?php for($y=date('Y'); $y>=2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>>Tahun <?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-primary shadow-sm fw-bold px-4" onclick="window.print()"><i data-lucide="printer" class="me-2"></i>Cetak</button>
            </div>
        </div>

        <!-- Dashboard Summary Grid -->
        <div class="row g-4 mb-4">
            <!-- WP Basic Info Card -->
            <div class="col-xl-4 col-md-12">
                <div class="glass-card p-4 h-100 position-relative overflow-hidden">
                    <div class="position-absolute" style="top: -20px; right: -20px; opacity: 0.05; transform: rotate(15deg);">
                        <i data-lucide="building-2" style="width: 150px; height: 150px;"></i>
                    </div>
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i data-lucide="user-check"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold m-0"><?= htmlspecialchars($wp['nama']); ?></h5>
                            <span class="text-muted small">NPWP: <?= $wp['npwp'] ?></span>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="metric-label">Jenis WP</div>
                            <div class="metric-value"><?= $wp['jenis_wp'] ?></div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="metric-label">UMKM</div>
                            <div class="metric-value"><?= $wp['is_umkm'] ? 'Ya' : 'Tidak' ?></div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="metric-label">Status PKP</div>
                            <div class="metric-value"><?= $wp['tgl_pkp'] ? 'PKP' : 'NON-PKP' ?></div>
                        </div>

                        <div class="col-12">
                            <div class="metric-label">Klasifikasi Usaha (KLU)</div>
                            <div class="metric-value small text-truncate"><?= $wp['klu'] ?> - <?= htmlspecialchars($wp['nama_klasifikasi_usaha']) ?></div>
                        </div>
                        <div class="col-12 mt-4 d-flex gap-2">
                            <a href="manajemen_wp.php?edit=<?= $wp['npwp'] ?>" class="btn btn-light flex-grow-1 fw-bold border" style="font-size: 0.8rem;">Edit Master</a>
            
                        </div>
                    </div>
                </div>
            </div>

            <!-- Risk Score Card -->
            <div class="col-xl-4 col-md-6">
                <div class="glass-card p-4 h-100 text-center d-flex flex-column align-items-center justify-content-center">
                    <div class="metric-label mb-3">Total Skor Risiko</div>
                    <?php 
                    
                        $color = var_export($level_risiko == 'TINGGI' ? 'var(--risk-high)' : ($level_risiko == 'SEDANG' ? 'var(--risk-medium)' : 'var(--risk-low)'), true);
                        $score_class = $level_risiko == 'TINGGI' ? 'text-danger' : ($level_risiko == 'SEDANG' ? 'text-warning' : 'text-success');
                    ?>
                    <div class="risk-ring mb-3">
                        <div class="value <?= $score_class ?>"><?= $skor_final ?></div>
                        <svg class="position-absolute" width="80" height="80">
                            <circle cx="40" cy="40" r="37" fill="transparent" stroke="<?= trim($color, "'") ?>" stroke-width="6" stroke-dasharray="<?= ($skor_final/100)*232 ?> 232" transform="rotate(-90 40 40)"></circle>
                        </svg>
                    </div>
                    <h4 class="fw-bold <?= $score_class ?> m-0">RISIKO <?= $level_risiko ?></h4>
                    <p class="text-muted small mt-2">Berdasarkan Analisa Risiko & Validasi Lapangan</p>
                    <div class="mt-3 d-flex gap-2">
                        <a href="jalankan_analisa.php?npwp=<?= $wp['npwp'] ?>&tahun=<?= $tahun ?>" class="btn btn-primary btn-sm px-3 fw-bold">Proses Analisa</a>
                        <a href="validasi_lapangan.php?npwp=<?= $wp['npwp'] ?>&tahun=<?= $tahun ?>" class="btn btn-outline-danger btn-sm px-3 fw-bold">Validasi Lapangan</a>
                    </div>
                </div>
            </div>

            <!-- Financial Pulse (Omzet Comparison) -->
            <div class="col-xl-4 col-md-6">
                <div class="glass-card p-4 h-100">
                    <div class="metric-label mb-3">Peredaran Usaha (Financial Pulse)</div>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-bold">SPT Tahunan</span>
                            <span class="fw-800"><?= toRpShort($omzet_spt) ?></span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="small">e-Faktur</span>
                            <div>
                                <span class="fw-bold"><?= toRpShort($omzet_faktur) ?></span>
                                <?php $gapF = $omzet_faktur - $omzet_spt; ?>
                                <span class="gap-indicator <?= $gapF > 0 ? 'bg-danger text-white' : 'bg-success text-white' ?> ms-2">
                                    <?= ($gapF > 0 ? '+' : '') . number_format(($gapF/max(1,$omzet_spt))*100, 1) ?>%
                                </span>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small">Data ILAP</span>
                            <div>
                                <span class="fw-bold"><?= toRpShort($omzet_ilap) ?></span>
                                <?php $gapI = $omzet_ilap - $omzet_spt; ?>
                                <span class="gap-indicator <?= $gapI > 0 ? 'bg-danger text-white' : 'bg-success text-white' ?> ms-2">
                                    <?= ($gapI > 0 ? '+' : '') . number_format(($gapI/max(1,$omzet_spt))*100, 1) ?>%
                                </span>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small">Laporan Keuangan</span>
                            <div>
                                <span class="fw-bold"><?= toRpShort($omzet_pembukuan) ?></span>
                                <?php $gapB = $omzet_pembukuan - $omzet_spt; ?>
                                <span class="gap-indicator <?= $gapB > 0 ? 'bg-danger text-white' : 'bg-success text-white' ?> ms-2">
                                    <?= ($gapB > 0 ? '+' : '') . number_format(($gapB/max(1,$omzet_spt))*100, 1) ?>%
                                </span>
                            </div>
                        </div>
                        <a href="laporan_keuangan.php?npwp=<?= $wp['npwp'] ?>&tahun=<?= $tahun ?>" class="btn btn-primary flex-grow-1 fw-bold" style="font-size: 0.8rem;">Laporan Keuangan</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Detail Section -->
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Nav Tabs -->
                <ul class="nav nav-pills nav-pills-custom mb-4" id="pills-tab" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pills-general"><i data-lucide="layout" class="me-2 inline"></i>General</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-owner"><i data-lucide="users" class="me-2 inline"></i>Owner/PIC</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-tax"><i data-lucide="file-check" class="me-2 inline"></i>Tax Compliance</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-field"><i data-lucide="map" class="me-2 inline"></i>Field Audit</button></li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    <!-- General Tab -->
                    <div class="tab-pane fade show active" id="pills-general">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Identity & Core Information</h6>
                            <div class="row g-4">
                                <div class="col-md-12">
                                    <div class="metric-label">Registered Address</div>
                                    <div class="fw-bold"><?= htmlspecialchars($wp['alamat']) ?></div>
                                    <div class="text-muted small mt-1"><?= $wp['kelurahan'] ?>, <?= $wp['kecamatan'] ?>, <?= $wp['kota'] ?>, <?= $wp['propinsi'] ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="metric-label">Contact Primary</div>
                                    <div class="fw-bold"><i data-lucide="phone" class="inline me-2 text-primary" style="width:14px"></i><?= $wp['telpon'] ?: '-' ?></div>
                                    <div class="fw-bold mt-1"><i data-lucide="mail" class="inline me-2 text-primary" style="width:14px"></i><?= $wp['email'] ?: '-' ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="metric-label">Registration Timeline</div>
                                    <div class="small fw-bold">Daftar: <?= date('d/m/Y', strtotime($wp['tgl_daftar'])) ?></div>
                                    <?php if($wp['tgl_pkp']): ?>
                                        <div class="small fw-bold text-info">PKP: <?= date('d/m/Y', strtotime($wp['tgl_pkp'])) ?></div>
                                    <?php else: ?>
                                        <div class="small fw-bold text-muted">Status: Non-PKP</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- AI Business Process Section -->
                            <hr class="my-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold m-0"><i data-lucide="brain-circuit" class="me-2 text-primary" style="width:18px;"></i>AI Business Insight (BMC)</h6>
                                <button class="btn btn-xs btn-outline-primary fw-bold" id="btnUpdateBisnis" onclick="generateBusinessProcess()">
                                    <i data-lucide="refresh-cw" class="me-1" style="width:12px;"></i> Update Insight
                                </button>
                            </div>

                            <div id="aiBisnisContent">
                                <?php 
                                    $bisnis = json_decode($wp['proses_bisnis'] ?? '', true);
                                    if ($bisnis): 
                                ?>
                                    <div class="row g-3">
                                        <?php 
                                        $labels = [
                                            'segmentasi_pasar' => 'Segmentasi Pasar', 'proposisi_nilai' => 'Proposisi Nilai',
                                            'saluran' => 'Saluran (Channels)', 'hubungan_pelanggan' => 'Hubungan Pelanggan',
                                            'sumber_pendapatan' => 'Sumber Pendapatan', 'sumber_daya_utama' => 'Sumber Daya Utama',
                                            'mitra_utama' => 'Mitra Utama', 'struktur_biaya' => 'Struktur Biaya', 'pesaing' => 'Pesaing Utama'
                                        ];
                                        foreach($labels as $key => $lbl): 
                                        ?>
                                        <div class="col-md-4">
                                            <div class="p-2 border rounded bg-light h-100">
                                                <div class="metric-label mb-1" style="font-size: 0.6rem;"><?= $lbl ?></div>
                                                <div class="small fw-semibold" style="line-height: 1.3; font-size: 0.75rem;"><?= htmlspecialchars($bisnis[$key] ?? 'N/A') ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 bg-light rounded-3 border-dashed">
                                        <i data-lucide="sparkles" class="text-primary mb-2" style="width:32px; height:32px;"></i>
                                        <p class="small text-muted mb-3">Belum ada profil proses bisnis untuk KLU ini.</p>
                                        <button class="btn btn-primary btn-sm fw-bold px-4" onclick="generateBusinessProcess()">Generate via Gemini AI</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Owner Tab -->
                    <div class="tab-pane fade" id="pills-owner">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Owners & Management Board</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle">
                                    <thead class="bg-light">
                                        <tr class="metric-label">
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th class="text-end">Share Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($pemilik)): ?>
                                            <tr><td colspan="3" class="text-center py-4 text-muted">No owner data registered.</td></tr>
                                        <?php else: foreach($pemilik as $p): ?>
                                            <tr class="border-bottom">
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($p['nama']) ?></div>
                                                    <div class="small text-muted">NIK: <?= $p['nik'] ?></div>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= $p['jabatan'] ?></span></td>
                                                <td class="text-end fw-bold text-primary">Rp <?= number_format($p['nilai_saham'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tax Compliance Tab -->
                    <div class="tab-pane fade" id="pills-tax">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Tax Documentation Pipeline (<?= $tahun ?>)</h6>
                            <div class="row g-3">
                                <?php 
                                    $status = $upload_status[$tahun] ?? [];
                                    $items = [
                                        'spt' => ['lbl' => 'SPT Tahunan', 'icon' => 'file-text', 'link' => 'manajemen_spt.php'],
                                        'faktur' => ['lbl' => 'e-Faktur', 'icon' => 'file-spreadsheet', 'link' => 'manajemen_faktur.php'],
                                        'bank' => ['lbl' => 'Bank Statements', 'icon' => 'landmark', 'link' => 'manajemen_bank.php'],
                                        'akun' => ['lbl' => 'Accounting Data', 'icon' => 'book-open', 'link' => 'manajemen_akun.php'],
                                        'bupot' => ['lbl' => 'Withholding Tax', 'icon' => 'scissors', 'link' => 'manajemen_bupot.php'],
                                        'setoran' => ['lbl' => 'Tax Payments', 'icon' => 'credit-card', 'link' => 'manajemen_setoran.php'],
                                        'ilap' => ['lbl' => 'Third Party ILAP', 'icon' => 'database', 'link' => 'manajemen_ilap.php']
                                    ];
                                    foreach($items as $key => $it): 
                                        $isDone = $status[$key] ?? false;
                                ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="p-3 border rounded-3 d-flex align-items-center gap-3 <?= $isDone ? 'bg-success bg-opacity-10 border-success' : 'bg-light border-dashed' ?>">
                                        <div class="<?= $isDone ? 'text-success' : 'text-muted' ?>">
                                            <i data-lucide="<?= $it['icon'] ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="small fw-bold"><?= $it['lbl'] ?></div>
                                            <div class="small text-muted"><?= $isDone ? 'Data Available' : 'No Data' ?></div>
                                        </div>
                                        <a href="<?= $it['link'] ?>?npwp=<?= $wp['npwp'] ?>&tahun=<?= $tahun ?>" class="btn btn-xs btn-outline-dark p-1"><i data-lucide="chevron-right" style="width:14px"></i></a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Field Audit Tab -->
                    <div class="tab-pane fade" id="pills-field">
                        <div class="glass-card p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold m-0">Latest Field Validation Report</h6>
                                <a href="validasi_lapangan.php?npwp=<?= $wp['npwp'] ?>&tahun=<?= $tahun ?>" class="btn btn-sm btn-outline-primary fw-bold">New Audit</a>
                            </div>
                            <?php if($audit): ?>
                                <div class="row g-4">
                                    <div class="col-md-6 text-center">
                                        <div class="metric-label mb-2">Location Geotag</div>
                                        <img src="<?= getImageUrl($audit['link_foto_lokasi']) ?>" class="audit-photo border" onerror="this.src='assets/images/no-image.png'">
                                    </div>
                                    <div class="col-md-6 text-center">
                                        <div class="metric-label mb-2">Business Activity</div>
                                        <img src="<?= getImageUrl($audit['link_foto_kegiatan']) ?>" class="audit-photo border" onerror="this.src='assets/images/no-image.png'">
                                    </div>
                                    <div class="col-12">
                                        <div class="bg-light p-3 rounded-3">
                                            <div class="row g-3 mb-3">
                                                <?php 
                                                $chks = [
                                                    ['l'=>'Alamat Sesuai', 'v'=>$audit['Alamat_sesuai']],
                                                    ['l'=>'Ada Papan Nama', 'v'=>$audit['Ada_papan_nama']],
                                                    ['l'=>'Ada Aktivitas', 'v'=>$audit['Ada_aktivitas']],
                                                    ['l'=>'Aset Terlihat', 'v'=>$audit['Aset_terlihat']],
                                                    ['l'=>'Ada Pembukuan', 'v'=>$audit['Ada_pembukuan']],
                                                    ['l'=>'Pembukuan Rapi', 'v'=>$audit['Pembukuan_rapi']],
                                                    ['l'=>'Faktur Tersimpan', 'v'=>$audit['Faktur_tersimpan']],
                                                    ['l'=>'PIC Menguasai', 'v'=>$audit['PIC_menguasai']],
                                                    ['l'=>'Penjelasan Wajar', 'v'=>$audit['Penjelasan_wajar']],
                                                    ['l'=>'Pegawai Sesuai SPT', 'v'=>$audit['Pegawai_sesuai_SPT']],
                                                    ['l'=>'Alamat Fiktif?', 'v'=>$audit['Alamat_fiktif'], 'inv'=>true],
                                                    ['l'=>'Kantor Sewa?', 'v'=>$audit['Kantor_virtual_sewa'], 'inv'=>true],
                                                    ['l'=>'WP Resisten?', 'v'=>$audit['Tidak_kooperatif'], 'inv'=>true]         
                                                ];
                                                foreach($chks as $c):
                                                    $isTrue = $c['v'] == 1;
                                                    $isWarning = isset($c['inv']) && $isTrue;
                                                    $color = $isWarning ? 'text-danger fw-bold' : ($isTrue ? 'text-success' : 'text-muted');
                                                ?>
                                                <div class="col-md-4 col-6 d-flex align-items-center gap-2 small fw-bold <?= $color ?>">
                                                    <i data-lucide="<?= $isTrue ? 'check-circle-2' : 'circle' ?>" style="width:16px"></i>
                                                    <?= $c['l'] ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="metric-label">Auditor's Note:</div>
                                            <div class="small text-dark mb-3 bg-warning p-2 rounded">'<?= htmlspecialchars($audit['catatan'] ?: 'N/A') ?>'</div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i data-lucide="camera-off" class="text-muted mb-2" style="width:40px"></i>
                                    <p class="text-muted small">Belum ada kunjungan lapangan terekam tahun ini.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar Map -->
            <div class="col-lg-4">
                <div class="glass-card p-4 h-100">
                    <h6 class="fw-bold mb-3"><i data-lucide="map-pin" class="me-2 text-danger"></i>Geographic Identity & Geofencing</h6>
                    <div id="map-360" class="shadow-sm border"></div>
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="metric-label">Registered Coordinates</div>
                                <div class="fw-bold small text-muted"><?= $wp['lat_npwp'] ?? '-' ?>, <?= $wp['lng_npwp'] ?? '-' ?></div>
                            </div>
                            <?php if($audit && !empty($audit['lat_kegiatan'])): ?>
                                <div class="text-end">
                                    <div class="metric-label">Geofence Gap</div>
                                    <div id="geofence-gap-val" class="fw-bold text-danger">Calculating...</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="metric-label mb-2">Validation Status</div>
                            <?php if($audit): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <i data-lucide="check-circle-2" class="text-success" style="width:16px"></i>
                                    <span class="small fw-bold">Verified on <?= date('d/m/Y', strtotime($audit['created_at'])) ?></span>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem;">GEO-TAGGED</span>
                                    <span class="badge bg-<?= $audit['Alamat_sesuai'] ? 'success' : 'danger' ?> rounded-pill" style="font-size: 0.65rem;"><?= $audit['Alamat_sesuai'] ? 'ALAMAT SESUAI' : 'ALAMAT TIDAK SESUAI' ?></span>
                                </div>
                            <?php else: ?>
                                <div class="d-flex align-items-center gap-2 text-muted">
                                    <i data-lucide="alert-circle" style="width:16px"></i>
                                    <span class="small fw-bold">No Field Data Available</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr>
                        <div class="metric-label">Tax Office (KPP)</div>
                        <div class="fw-bold">KPP Pratama XXX [<?= $wp['kode_kpp'] ?>]</div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="mt-5 mb-4 text-center">
            <p class="small text-muted">SHARP - Systematic Hybrid Analysis Risk for Tax Dashboard v2.5</p>
        </footer>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    lucide.createIcons();

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c; 
    }

    document.addEventListener('DOMContentLoaded', function() {
        const lat = parseFloat(<?= $wp['lat_npwp'] ?: '0'; ?>);
        const lng = parseFloat(<?= $wp['lng_npwp'] ?: '0'; ?>);
        const mapDiv = document.getElementById('map-360');

        if(lat !== 0 && lng !== 0) {
            const map = L.map('map-360').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
                attribution: '© OpenStreetMap' 
            }).addTo(map);
            
            const redIcon = L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
            });

            L.marker([lat, lng], {icon: redIcon}).addTo(map).bindPopup('<b>Registered: <?= htmlspecialchars($wp['nama']); ?></b>').openPopup();
            
            <?php if($audit && !empty($audit['lat_kegiatan'])): ?>
                const latAuditor = parseFloat(<?= $audit['lat_kegiatan'] ?>);
                const lngAuditor = parseFloat(<?= $audit['lng_kegiatan'] ?>);
                
                const blueIcon = L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
                });

                L.marker([latAuditor, lngAuditor], {icon: blueIcon}).addTo(map).bindPopup('<b>Last Audit Point</b>');
                const polyline = L.polyline([[lat, lng], [latAuditor, lngAuditor]], {color: '#3b82f6', weight: 2, dashArray: '5, 10'}).addTo(map);
                map.fitBounds(polyline.getBounds(), {padding: [50, 50]});

                const distKm = calculateDistance(lat, lng, latAuditor, lngAuditor);
                const distText = distKm < 1 ? Math.round(distKm * 1000) + " m" : distKm.toFixed(2) + " km";
                const gapEl = document.getElementById('geofence-gap-val');
                if(gapEl) gapEl.innerText = distText;
            <?php endif; ?>

            // Re-render map when tab changes (to fix leaflet grey tiles)
            document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(btn => {
                btn.addEventListener('shown.bs.tab', () => map.invalidateSize());
            });
        } else {
            mapDiv.innerHTML = `<div class="d-flex align-items-center justify-content-center h-100 bg-light rounded text-muted">Coordinates Unavailable</div>`;
        }
    });

    async function generateBusinessProcess() {
        const btn = document.getElementById('btnUpdateBisnis');
        const container = document.getElementById('aiBisnisContent');
        const originalHtml = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Analyzing...';
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-grow text-primary mb-3"></div>
                <h6 class="fw-bold text-primary">Gemini AI sedang merumuskan Model Bisnis...</h6>
                <p class="small text-muted">Menganalisa sektor: <?= htmlspecialchars($wp['nama_klasifikasi_usaha']) ?></p>
            </div>
        `;

        try {
            const formData = new FormData();
            formData.append('npwp', '<?= $npwp ?>');
            formData.append('nama_klu', '<?= addslashes($wp['nama_klasifikasi_usaha']) ?>');

            const response = await fetch('api/api_generate_bisnis.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                const data = result.data;
                const labels = {
                    segmentasi_pasar: 'Segmentasi Pasar', proposisi_nilai: 'Proposisi Nilai',
                    saluran: 'Saluran (Channels)', hubungan_pelanggan: 'Hubungan Pelanggan',
                    sumber_pendapatan: 'Sumber Pendapatan', sumber_daya_utama: 'Sumber Daya Utama',
                    mitra_utama: 'Mitra Utama', struktur_biaya: 'Struktur Biaya', pesaing: 'Pesaing Utama'
                };

                let html = '<div class="row g-3">';
                for (const [key, lbl] of Object.entries(labels)) {
                    html += `
                        <div class="col-md-4">
                            <div class="p-2 border rounded bg-light h-100">
                                <div class="metric-label mb-1" style="font-size: 0.6rem;">${lbl}</div>
                                <div class="small fw-semibold" style="line-height: 1.3; font-size: 0.75rem;">${data[key] || 'N/A'}</div>
                            </div>
                        </div>
                    `;
                }
                html += '</div>';
                container.innerHTML = html;
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            } else {
                alert("AI Error: " + result.message);
                location.reload();
            }
        } catch (error) {
            alert("Connection error to Gemini API.");
            location.reload();
        }
    }
</script>
</body>
</html>
