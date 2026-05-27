<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$npwp = $_GET['npwp'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');

// Fetch Profile & Analysis Result
$stmt = $db->prepare("SELECT h.*, p.*, h.created_at as pada, h.created_by as oleh    
                     FROM hasil_analisis h 
                     JOIN profil_wp p ON h.npwp = p.npwp 
                     WHERE h.npwp = ? AND h.tahun = ?");
$stmt->execute([$npwp, $tahun]);
$analisa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$analisa) {
    die("<div class='container mt-5 alert alert-warning'>Hasil analisa untuk NPWP $npwp Tahun $tahun tidak ditemukan. <a href='profil_wp.php?npwp=$npwp'>Kembali ke Profil</a></div>");
}

$data = json_decode($analisa['data_json'] ?? '', true) ?? [];

// Fetch History for Benchmark Chart (Last 5 Years)
$stmtHist = $db->prepare("SELECT tahun, data_json FROM hasil_analisis WHERE npwp = ? AND tahun <= ? ORDER BY tahun ASC LIMIT 5");
$stmtHist->execute([$npwp, $tahun]);
$history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

$hist_data = [];
foreach ($history as $h) {
    $h_json = json_decode($h['data_json'], true);
    if (isset($h_json['rasio'])) {
        $hist_data[$h['tahun']] = $h_json['rasio'];
    }
}

// Helper Format Rupiah
function toRp(float $angka): string {
    return "Rp " . number_format($angka ?? 0, 0, ',', '.');
}

// Color Mapping
$level_color = [
    'TINGGI' => 'danger',
    'SEDANG' => 'warning',
    'RENDAH' => 'success'
];
$color = $level_color[$analisa['level_risiko']] ?? 'secondary';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Analisa - <?= htmlspecialchars($analisa['nama']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e3a8a; --bg: #f1f5f9; }
        body { background-color: var(--bg); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; transition: margin-left 0.3s; }
        @media (max-width: 991px) { .main-content { margin-left: 0; } }
        
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .score-circle {
            width: 120px; height: 120px;
            border-radius: 50%;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            border: 8px solid;
            background: white;
            margin: 0 auto;
        }
        .stat-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .stat-value { font-size: 1.1rem; font-weight: 700; color: #1e293b; }
        .progress { height: 8px; border-radius: 4px; }
        .table-custom thead { background: #f8fafc; }
        .table-custom th { font-size: 0.75rem; text-transform: uppercase; color: #64748b; border: none; }
        .badge-pill { border-radius: 50px; padding: 5px 15px; font-weight: 600; }
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            padding: 1.5rem;
        }

        .accordion-toggle {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .accordion-toggle:hover {
            background-color: #e9ecef;
        }

        .hiddenRow {
            padding: 0 !important;
        }

        .accordian-body {
            /* Bootstrap collapse classes handle the animation, 
               but we add a little padding for visual hierarchy */
            background-color: #fafbfc;
        }

        .child-row td:first-child {
            /* Indent child rows to show hierarchy */
            padding-left: 3rem;
            position: relative;
        }

        /* Add a subtle visual cue for child rows */
        .child-row td:first-child::before {
            content: "└";
            position: absolute;
            left: 1.5rem;
            color: #adb5bd;
        }

        /* Animated chevron icon */
        .chevron-icon {
            transition: transform 0.3s ease;
            margin-right: 8px;
            color: #6c757d;
        }

          /* Sub chevron icon animation */
        .chevron-icon-sub {
            transition: transform 0.3s ease;
            margin-right: 6px;
            color: #8c96a0;
            font-size: 0.85rem;
        }

        /* Rotate chevron when collapsed (default state is closed in Bootstrap, 
           but we manage the icon rotation via JS to match the state) */
        .accordion-toggle[aria-expanded="true"] .chevron-icon {
            transform: rotate(90deg);
        }
        
        /* Rotate sub chevron when expanded */
        .accordion-toggle-sub[aria-expanded="true"] .chevron-icon-sub {
            transform: rotate(90deg);
        }

        .fw-bold {
            font-weight: 600 !important;
        }

    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        
        <!-- HEADER -->
         <div>
                <h3 class="fw-800 m-0">Risk Engine Report</h3>
                <p class="text-muted">Results of Tax Risk Assessment Analysis</p>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div></div>
            <div class="d-flex gap-2">
                <a href="profil_wp.php?npwp=<?= $npwp ?>&tahun=<?= $tahun ?>" class="btn btn-success shadow-sm fw-bold">
                    <i data-lucide="user" class="inline me-1" style="width:18px;"></i> Profil WP
                </a>
                <a href="laporan_keuangan.php?npwp=<?= $npwp ?>&tahun=<?= $tahun ?>" class="btn btn-warning shadow-sm fw-bold">
                    <i data-lucide="file-text" class="inline me-1" style="width:18px;"></i> Cetak Laporan Keuangan
                </a>
                <a href="generate_lha.php?npwp=<?= $npwp ?>&tahun=<?= $tahun ?>" class="btn btn-danger shadow-sm fw-bold">
                    <i data-lucide="file-text" class="inline me-1" style="width:18px;"></i> Cetak LHA
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary bg-white shadow-sm">
                    <i data-lucide="printer" style="width:18px;"></i>
                </button>
            </div>
        </div>

        <div class="row g-4">
            <!-- LEFT COL: SCORE & PROFILE -->
            <div class="col-lg-4">
                <div class="card card-custom p-4 mb-4 text-center">
                    <div class="stat-value">TAHUN PAJAK: <?= $tahun ?></div>
                    <div class="stat-label mb-3">Skor Risiko Final (0-100)</div>
                    <div class="score-circle border-<?= $color ?> mb-3">
                        <h1 class="fw-bold m-0 text-<?= $color ?>"><?= $analisa['skor_final'] ?? $analisa['skor_risiko'] ?></h1>
                        <span class="small fw-bold text-muted">SKOR</span>
                    </div>
                    <div class="badge bg-<?= $color ?> fs-6 mb-4 badge-pill"><?= $analisa['level_risiko'] ?></div>
                    
                    <div class="text-start border-top pt-3">
                        <div class="mb-2">
                            <div class="stat-label">Wajib Pajak</div>
                            <div class="stat-value text-primary"><?= htmlspecialchars($analisa['nama']) ?></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="stat-label">Jenis WP</div>
                                <div class="stat-value"><?= $analisa['jenis_wp'] ?></div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="stat-label">UMKM</div>
                                <div class="stat-value"><?= $analisa['is_umkm']==1 ? 'Ya' : 'Tidak' ?></div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="stat-label">Status PKP</div>
                                <div class="stat-value"><?= $analisa['tgl_pkp'] ? 'PKP' : 'NON-PKP' ?></div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="stat-label">Terakhir Dianalisa</div>
                                <div class="stat-value small"><?= date('d M Y H:i', strtotime($analisa['pada'])) ?></div>
                            </div>
                            <div class="col-6 text-end">
                                <div class="stat-label">Oleh</div>
                                <div class="stat-value small"><?= $analisa['oleh'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-custom p-4 border-start border-4 border-info bg-white">
                    <h6 class="fw-bold mb-3 d-flex align-items-center">
                        <i data-lucide="alert-circle" class="me-2 text-info"></i> Indikasi Temuan
                    </h6>
                    <ul class="list-group list-group-flush small">
                        <?php 
                        $notes = explode(". ", $analisa['catatan_risiko'] ?? '');
                        foreach($notes as $note): if(trim($note) == "") continue;
                        ?>
                        <li class="list-group-item px-0 py-2 d-flex align-items-start gap-2 border-0">
                            <i data-lucide="corner-down-right" class="text-muted mt-1" style="width:14px; flex-shrink:0;"></i>
                            <span><?= htmlspecialchars($note) ?>.</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- RIGHT COL: DETAILED RECONCILIATION -->
            <div class="col-lg-8">
                
                <!-- 0. SIMULASI PAJAK VS PEMBUKUAN -->
                <div class="card card-custom mb-4 overflow-hidden border-top border-4 border-primary">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold m-0 d-flex align-items-center">
                            <i data-lucide="calculator" class="me-2 text-primary"></i> Penghitungan Potensi Pajak (Simulasi)
                        </h6>
                    </div>
                    <?php 
                        $sim = $data['simulation'] ?? []; 
                        if(!empty($sim)):
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Deskripsi Pos</th>
                                    <th class="text-end">Nilai Nominal</th>
                                    <th class="text-end">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- 1. Penjualan / Penghasilan Bruto (Accordion Parent) -->
                                <tr class="accordion-toggle table-success cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapsePenjualan" aria-expanded="false" aria-controls="collapsePenjualan">
                                    <td class="fw-bold">
                                        <i data-lucide="chevron-right" class="chevron-icon me-2 text-success"></i>
                                        Penjualan / Penghasilan Bruto
                                    </td>
                                    <td class="text-end fw-bold"><?= toRp($sim['penjualan']['value']) ?></td>
                                    <td class="text-end">Data Matching <span class="badge bg-success ms-2">Klik Detail</span></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="hiddenRow p-0">
                                        <div class="collapse" id="collapsePenjualan">
                                            <table class="table table-borderless mb-0 bg-light">
                                                <tbody>
                                                    <tr class="border-bottom text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Penghasilan Bruto SPT</td>
                                                        <td class="text-end" style="width: 33%;"><?= toRp($sim['penjualan']['matching']['spt']) ?></td>
                                                        <td>SPT Tahunan</td>
                                                        <td><?= $sim['penjualan']['value'] == $sim['penjualan']['matching']['spt'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                    </tr>
                                                    <tr class="border-bottom text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Omset Pembukuan</td>
                                                        <td class="text-end"><?= toRp($sim['penjualan']['matching']['pembukuan']) ?></td>
                                                        <td>Buku Besar</td>
                                                        <td><?= $sim['penjualan']['value'] == $sim['penjualan']['matching']['pembukuan'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                    </tr>
                                                    <tr class="border-bottom text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Equalisasi DPP PPN Keluaran</td>
                                                        <td class="text-end"><?= toRp($sim['penjualan']['matching']['faktur']) ?></td>
                                                        <td>SPT Masa PPN</td>
                                                        <td><?= $sim['penjualan']['value'] == $sim['penjualan']['matching']['faktur'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                    </tr>
                                                    <tr class="border-bottom text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Mutasi Masuk Bank</td>
                                                        <td class="text-end"><?= toRp($sim['penjualan']['matching']['bank']) ?></td>
                                                        <td>Rekening Koran</td>
                                                        <td><?= $sim['penjualan']['value'] == $sim['penjualan']['matching']['bank'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                    </tr>
                                                    <tr class="border-bottom text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Data ILAP (INCOME)</td>
                                                        <td class="text-end"><?= toRp($sim['penjualan']['matching']['ilap']) ?></td>
                                                        <td>KPDL/Data Matching</td>
                                                        <td><?= $sim['penjualan']['value'] == $sim['penjualan']['matching']['ilap'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                    </tr>
                                                    <tr class="border-bottom text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>DPP PPh 21/22/24/4(2)</td>
                                                        <td class="text-end"><?= toRp($sim['penjualan']['matching']['bupot']) ?></td>
                                                        <td>Bukti Potong Lawan</td>
                                                        <td><?= $sim['penjualan']['value'] == $sim['penjualan']['matching']['bupot'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                    </tr>
                                                    <tr class="border-bottom text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Penyesuaian Saldo Kas</td>
                                                        <td class="text-end"><?= toRp($sim['penjualan']['matching']['adjusment_saldo_kas']) ?></td>
                                                        <td>Pengujian Arus Kas</td>
                                                        <td><?= $sim['penjualan']['value'] == $sim['penjualan']['matching']['adjusment_saldo_kas'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>

                                <?php if($analisa['is_umkm'] != 1) { ?>
                                    <?php if($analisa['jenis_wp'] != 'OP'){ ?>
                                        <!-- 2. Harga Pokok Penjualan (HPP) (Accordion Parent) -->
                                        <tr class="accordion-toggle cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseHPP" aria-expanded="false" aria-controls="collapseHPP">
                                            <td class="fw-bold text-primary">
                                                <i data-lucide="chevron-right" class="chevron-icon me-2"></i>
                                                Harga Pokok Penjualan (HPP)
                                            </td>
                                            <td class="text-end fw-bold text-primary"><?= toRp($sim['hpp']['value']) ?></td>
                                            <td class="text-end">Rincian HPP <span class="badge bg-primary ms-2">Klik Detail</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="hiddenRow p-0">
                                                <div class="collapse" id="collapseHPP">
                                                    <table class="table table-borderless mb-0">
                                                        <tbody>
                                                            <!-- A. Persediaan Awal (Sub-Accordion) -->
                                                            <tr class="accordion-toggle-sub border-bottom text-primary cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapsePersediaanAwal" aria-expanded="false" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;">
                                                                    <span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>
                                                                    <i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> Persediaan Awal
                                                                </td>
                                                                <td class="text-end"><?= toRp($sim['persediaan_awal']['value']) ?></td>
                                                                <td class="text-end">Data Matching <span class="badge bg-light text-secondary border">Lihat</span></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="3" class="hiddenRow p-0">
                                                                    <div class="collapse bg-light" id="collapsePersediaanAwal">
                                                                        <table class="table table-borderless mb-0">
                                                                            <tbody>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Persediaan Awal SPT</td>
                                                                                    <td class="text-end" style="width: 33%;"><?= toRp($sim['persediaan_awal']['matching']['spt']) ?></td>
                                                                                    <td>SPT Tahunan</td>
                                                                                    <td class="text-end"><?= $sim['persediaan_awal']['value'] == $sim['persediaan_awal']['matching']['spt'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Persediaan Awal Pembukuan</td>
                                                                                    <td class="text-end"><?= toRp($sim['persediaan_awal']['matching']['pembukuan']) ?></td>
                                                                                    <td>Buku Besar</td>
                                                                                    <td class="text-end"><?= $sim['persediaan_awal']['value'] == $sim['persediaan_awal']['matching']['pembukuan'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Saldo Akhir Tahun Lalu</td>
                                                                                    <td class="text-end"><?= toRp($sim['persediaan_awal']['matching']['saldo_akhir_lalu']) ?></td>
                                                                                    <td>Closing Saldo</td>
                                                                                    <td class="text-end"><?= $sim['persediaan_awal']['value'] == $sim['persediaan_awal']['matching']['saldo_akhir_lalu'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <!-- B. Pembelian (Sub-Accordion) -->
                                                            <tr class="accordion-toggle-sub border-bottom text-primary cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapsePembelianDetail" aria-expanded="false" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;">
                                                                    <span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>
                                                                    <i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> Pembelian
                                                                </td>
                                                                <td class="text-end"><?= toRp($sim['pembelian']['value']) ?></td>
                                                                <td class="text-end">Data Matching <span class="badge bg-light text-secondary border">Lihat</span></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="3" class="hiddenRow p-0">
                                                                    <div class="collapse bg-light" id="collapsePembelianDetail">
                                                                        <table class="table table-borderless mb-0">
                                                                            <tbody>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Pembelian SPT</td>
                                                                                    <td class="text-end" style="width: 33%;"><?= toRp($sim['pembelian']['matching']['spt']) ?></td>
                                                                                    <td>SPT Tahunan</td>
                                                                                    <td class="text-end"><?= $sim['pembelian']['value'] == $sim['pembelian']['matching']['spt'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Pembelian Pembukuan</td>
                                                                                    <td class="text-end"><?= toRp($sim['pembelian']['matching']['pembukuan']) ?></td>
                                                                                    <td>Buku Besar</td>
                                                                                    <td class="text-end"><?= $sim['pembelian']['value'] == $sim['pembelian']['matching']['pembukuan'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Equalisasi DPP PPN Masukan</td>
                                                                                    <td class="text-end"><?= toRp($sim['pembelian']['matching']['faktur']) ?></td>
                                                                                    <td>SPT Masa PPN</td>
                                                                                    <td class="text-end"><?= $sim['pembelian']['value'] == $sim['pembelian']['matching']['faktur'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Mutasi Keluar Bank</td>
                                                                                    <td class="text-end"><?= toRp($sim['pembelian']['matching']['bank']) ?></td>
                                                                                    <td>Rekening Koran</td>
                                                                                    <td class="text-end"><?= $sim['pembelian']['value'] == $sim['pembelian']['matching']['bank'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Data ILAP (COST)</td>
                                                                                    <td class="text-end"><?= toRp($sim['pembelian']['matching']['ilap']) ?></td>
                                                                                    <td>KPDL/Data Matching</td>
                                                                                    <td class="text-end"><?= $sim['pembelian']['value'] == $sim['pembelian']['matching']['ilap'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>                                                                        
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <!-- C. Persediaan Akhir (Sub-Accordion) -->
                                                            <tr class="accordion-toggle-sub border-bottom text-primary cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapsePersediaanAkhir" aria-expanded="false" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;">
                                                                    <span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>
                                                                    <i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> Persediaan Akhir
                                                                </td>
                                                                <td class="text-end">(<?= toRp($sim['persediaan_akhir']['value']) ?>)</td>
                                                                <td class="text-end">Data Matching <span class="badge bg-light text-secondary border">Lihat</span></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="3" class="hiddenRow p-0">
                                                                    <div class="collapse bg-light" id="collapsePersediaanAkhir">
                                                                        <table class="table table-borderless mb-0">
                                                                            <tbody>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Persediaan Akhir SPT</td>
                                                                                    <td class="text-end" style="width: 33%;"><?= toRp($sim['persediaan_akhir']['matching']['spt']) ?></td>
                                                                                    <td>SPT Tahunan</td>
                                                                                    <td class="text-end"><?= $sim['persediaan_akhir']['value'] == $sim['persediaan_akhir']['matching']['spt'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Stock Opname Pembukuan</td>
                                                                                    <td class="text-end"><?= toRp($sim['persediaan_akhir']['matching']['pembukuan']) ?></td>
                                                                                    <td>Buku Besar</td>
                                                                                    <td class="text-end"><?= $sim['persediaan_akhir']['value'] == $sim['persediaan_akhir']['matching']['pembukuan'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Adjusment Persediaan Akhir</td>
                                                                                    <td class="text-end"><?= toRp($sim['persediaan_akhir']['matching']['max_value']) ?></td>
                                                                                    <td>Batas Maksimal</td>
                                                                                    <td class="text-end"><?= $sim['persediaan_akhir']['value'] == $sim['persediaan_akhir']['matching']['max_value'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- 3. Laba Kotor (Accordion Parent) -->
                                        <tr class="accordion-toggle table-light cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseLabaKotor" aria-expanded="false" aria-controls="collapseLabaKotor">
                                            <td class="fw-bold">
                                                <i data-lucide="chevron-right" class="chevron-icon me-2"></i>
                                                Laba Usaha (Laba Kotor)
                                            </td>
                                            <td class="text-end fw-bold"><?= toRp($sim['laba_kotor']['value']) ?></td>
                                            <td class="text-end">Perbandingan <span class="badge bg-secondary ms-2">Klik Detail</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="hiddenRow p-0">
                                                <div class="collapse" id="collapseLabaKotor">
                                                    <table class="table table-borderless mb-0 bg-light">
                                                        <tbody>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Laba Bruto</td>
                                                                <td class="text-end" style="width: 33%;"><?= toRp($sim['laba_kotor']['matching']['spt']) ?></td>
                                                                <td>SPT Tahunan</td>
                                                                <td class="text-end"><?= $sim['laba_kotor']['value'] == $sim['laba_kotor']['matching']['spt'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                            </tr>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Laba Kotor</td>
                                                                <td class="text-end"><?= toRp($sim['laba_kotor']['matching']['pembukuan']) ?></td>
                                                                <td>Buku Besar</td>
                                                                <td class="text-end"><?= $sim['laba_kotor']['value'] == $sim['laba_kotor']['matching']['pembukuan'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                            </tr>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Equalisasi PKPM DPP PPN</td>
                                                                <td class="text-end"><?= toRp($sim['laba_kotor']['matching']['faktur']) ?></td>
                                                                <td>SPT Masa PPN</td>
                                                                <td class="text-end"><?= $sim['laba_kotor']['value'] == $sim['laba_kotor']['matching']['faktur'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- 4. Biaya Operasional / Usaha (Accordion Parent) -->
                                        <tr class="accordion-toggle cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseBiayaOperasional" aria-expanded="false" aria-controls="collapseBiayaOperasional">
                                            <td class="fw-bold text-primary">
                                                <i data-lucide="chevron-right" class="chevron-icon me-2"></i>
                                                Biaya Operasional / Usaha
                                            </td>
                                            <td class="text-end fw-bold text-primary"><?= toRp($sim['beban_usaha']['value']) ?></td>
                                            <td class="text-end">Data Matching <span class="badge bg-primary ms-2">Klik Detail</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="hiddenRow p-0">
                                                <div class="collapse" id="collapseBiayaOperasional">
                                                    <table class="table table-borderless mb-0">
                                                        <tbody>
                                                            <!-- Sub-accordion for Beban Usaha SPT -->
                                                            <tr class="accordion-toggle-sub border-bottom text-muted cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseBebanUsahaSpt" aria-expanded="false" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;">
                                                                    <span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>
                                                                    <i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> Beban Usaha SPT
                                                                </td>
                                                                <td class="text-end" style="width: 33%;"><?= toRp($sim['beban_usaha']['matching']['spt']['sum']) ?></td>
                                                                <td>SPT Tahunan <span class="badge bg-light text-secondary border">Lihat</span></td>
                                                                <td class="text-end"><?= $sim['beban_usaha']['value'] == $sim['beban_usaha']['matching']['spt']['sum'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="4" class="hiddenRow p-0">
                                                                    <div class="collapse bg-light" id="collapseBebanUsahaSpt">
                                                                        <table class="table table-borderless mb-0">
                                                                            <tbody>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Gaji (SPT)</td>
                                                                                    <td class="text-end" style="width: 33%;"><?= toRp($sim['beban_usaha']['matching']['spt']['gaji']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Biaya Operasional (SPT)</td>
                                                                                    <td class="text-end"><?= toRp($sim['beban_usaha']['matching']['spt']['biaya_operasional']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Biaya Penyusutan (SPT)</td>
                                                                                    <td class="text-end"><?= toRp($sim['beban_usaha']['matching']['spt']['biaya_penyusutan']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <!-- Sub-accordion for Biaya Operasional Pembukuan -->
                                                            <tr class="accordion-toggle-sub border-bottom text-muted cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseBiayaOperasionalPembukuan" aria-expanded="false" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;">
                                                                    <span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>
                                                                    <i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> Biaya Operasional Pembukuan
                                                                </td>
                                                                <td class="text-end"><?= toRp($sim['beban_usaha']['matching']['pembukuan']['sum']) ?></td>
                                                                <td>Buku Besar <span class="badge bg-light text-secondary border">Lihat</span></td>
                                                                <td class="text-end"><?= $sim['beban_usaha']['value'] == $sim['beban_usaha']['matching']['pembukuan']['sum'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="4" class="hiddenRow p-0">
                                                                    <div class="collapse bg-light" id="collapseBiayaOperasionalPembukuan">
                                                                        <table class="table table-borderless mb-0">
                                                                            <tbody>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Gaji (Pembukuan)</td>
                                                                                    <td class="text-end" style="width: 33%;"><?= toRp($sim['beban_usaha']['matching']['pembukuan']['gaji']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Biaya Operasional (Pembukuan)</td>
                                                                                    <td class="text-end"><?= toRp($sim['beban_usaha']['matching']['pembukuan']['biaya_operasional']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Biaya Penyusutan (Pembukuan)</td>
                                                                                    <td class="text-end"><?= toRp($sim['beban_usaha']['matching']['pembukuan']['biaya_penyusutan']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <!-- Sub-accordion for DPP PPh 21/22/23/4(2) -->
                                                            <tr class="accordion-toggle-sub border-bottom text-muted cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseBebanUsahaBupot" aria-expanded="false" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;">
                                                                    <span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>
                                                                    <i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> DPP PPh 21/22/23/4(2)
                                                                </td>
                                                                <td class="text-end"><?= toRp($sim['beban_usaha']['matching']['bupot']['sum']) ?></td>
                                                                <td>SPT PPh Unifikasi <span class="badge bg-light text-secondary border">Lihat</span></td>
                                                                <td class="text-end"><?= $sim['beban_usaha']['value'] == $sim['beban_usaha']['matching']['bupot']['sum'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="4" class="hiddenRow p-0">
                                                                    <div class="collapse bg-light" id="collapseBebanUsahaBupot">
                                                                        <table class="table table-borderless mb-0">
                                                                            <tbody>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Gaji (Bupot)</td>
                                                                                    <td class="text-end" style="width: 33%;"><?= toRp($sim['beban_usaha']['matching']['bupot']['gaji']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Jasa (Bupot)</td>
                                                                                    <td class="text-end"><?= toRp($sim['beban_usaha']['matching']['bupot']['jasa']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Sewa (Bupot)</td>
                                                                                    <td class="text-end"><?= toRp($sim['beban_usaha']['matching']['bupot']['sewa']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- 5. Penghasilan (Beban) Luar Usaha (Accordion Parent) -->
                                        <tr class="accordion-toggle cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseLuarUsaha" aria-expanded="false" aria-controls="collapseLuarUsaha">
                                            <td class="text-primary fw-bold">
                                                <i data-lucide="chevron-right" class="chevron-icon me-2"></i>
                                                Penghasilan (Beban) Luar Usaha
                                            </td>
                                            <td class="text-end text-primary fw-bold"><?= toRp($sim['luar_usaha']['value']) ?></td>
                                            <td class="text-end">Data Matching <span class="badge bg-primary ms-2">Klik Detail</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="hiddenRow p-0">
                                                <div class="collapse bg-light" id="collapseLuarUsaha">
                                                    <table class="table table-borderless mb-0">
                                                        <tbody>
                                                            <!-- Sub-accordion for Penghasilan (Beban) Luar Usaha SPT -->
                                                            <tr class="accordion-toggle-sub border-bottom text-muted cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseLuarUsahaSpt" aria-expanded="false" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;">
                                                                    <span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>
                                                                    <i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> Penghasilan (Beban) Luar Usaha SPT
                                                                </td>
                                                                <td class="text-end" style="width: 33%;"><?= toRp($sim['luar_usaha']['matching']['spt']['sum']) ?></td>
                                                                <td>SPT Tahunan <span class="badge bg-light text-secondary border">Lihat</span></td>
                                                                <td class="text-end"><?= $sim['luar_usaha']['value'] == $sim['luar_usaha']['matching']['spt']['sum'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="4" class="hiddenRow p-0">
                                                                    <div class="collapse bg-light" id="collapseLuarUsahaSpt">
                                                                        <table class="table table-borderless mb-0">
                                                                            <tbody>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Penghasilan Luar Usaha (SPT)</td>
                                                                                    <td class="text-end" style="width: 33%;"><?= toRp($sim['luar_usaha']['matching']['spt']['penghasilan_luar']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Biaya Luar Usaha (SPT)</td>
                                                                                    <td class="text-end">(<?= toRp($sim['luar_usaha']['matching']['spt']['biaya_luar']) ?>)</td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <!-- Sub-accordion for Penghasilan (Beban) Luar Usaha Pembukuan -->
                                                            <tr class="accordion-toggle-sub border-bottom text-muted cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseLuarUsahaPembukuan" aria-expanded="false" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;">
                                                                    <span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>
                                                                    <i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> Penghasilan (Beban) Luar Usaha Pembukuan
                                                                </td>
                                                                <td class="text-end"><?= toRp($sim['luar_usaha']['matching']['pembukuan']['sum']) ?></td>
                                                                <td>Buku Besar <span class="badge bg-light text-secondary border">Lihat</span></td>
                                                                <td class="text-end"><?= $sim['luar_usaha']['value'] == $sim['luar_usaha']['matching']['pembukuan']['sum'] ? '<i data-lucide="circle-check-big" class="text-success"></i>' : '' ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="4" class="hiddenRow p-0">
                                                                    <div class="collapse bg-light" id="collapseLuarUsahaPembukuan">
                                                                        <table class="table table-borderless mb-0">
                                                                            <tbody>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Penghasilan Luar Usaha (Pembukuan)</td>
                                                                                    <td class="text-end" style="width: 33%;"><?= toRp($sim['luar_usaha']['matching']['pembukuan']['penghasilan_luar']) ?></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                                <tr class="text-muted" style="font-size: 0.85rem;">
                                                                                    <td style="padding-left: 4.5rem; position: relative;"><span style="position: absolute; left: 3rem; color: #adb5bd;">└</span>Biaya Luar Usaha (Pembukuan)</td>
                                                                                    <td class="text-end">(<?= toRp($sim['luar_usaha']['matching']['pembukuan']['biaya_luar']) ?>)</td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- 6. Laba Bersih (Accordion Parent) -->
                                        <tr class="accordion-toggle table-info cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseLabaBersih" aria-expanded="false" aria-controls="collapseLabaBersih">
                                            <td class="fw-bold">
                                                <i data-lucide="chevron-right" class="chevron-icon me-2"></i>
                                                Laba Bersih
                                            </td>
                                            <td class="text-end fw-bold"><?= toRp($sim['laba_bersih']['value']) ?></td>
                                            <td class="text-end">Perbandingan<span class="badge bg-secondary ms-2">Klik Detail</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="hiddenRow p-0">
                                                <div class="collapse" id="collapseLabaBersih">
                                                    <table class="table table-borderless mb-0 bg-light">
                                                        <tbody>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Laba Bersih Fiskal</td>
                                                                <td class="text-end" style="width: 33%;"><?= toRp($sim['laba_bersih']['matching']['spt']) ?></td>
                                                                <td>SPT Tahunan</td>
                                                            </tr>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Laba Bersih Pembukuan</td>
                                                                <td class="text-end"><?= toRp($sim['laba_bersih']['matching']['pembukuan']) ?></td>
                                                                <td>Buku Besar</td>
                                                            </tr>                                                            
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>

                                    <?php } ?>
                                    
                                    <?php if($data['spt']['norma'] > 0 && $analisa['jenis_wp'] == 'OP'){ ?>
                                        <tr>
                                            <td>Norma Penghitungan Penghasilan Netto</td>
                                            <td class="text-end fw-bold"><?= $sim['norma']['value'] ?>%</td>
                                            <td>NPPN Pajak</td>
                                        </tr>
                                    <?php } ?>

                                    <!-- 7. Penghasilan Netto (Row Utama) -->
                                    <tr class="table-success">
                                        <td class="fw-bold">Penghasilan Netto</td>
                                        <td class="text-end fw-bold"><?= toRp($sim['penghasilan_netto']['value']) ?></td>
                                        <td>Netto Fiskal</td>
                                    </tr>

                                    <!-- 8. Koreksi Fiskal (Accordion Parent) -->
                                        <tr class="accordion-toggle table-danger cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseKoreksiFiskal" aria-expanded="false" aria-controls="collapseKoreksiFiskal">
                                            <td class="fw-bold">
                                                <i data-lucide="chevron-right" class="chevron-icon me-2"></i>
                                                Koreksi Fiskal
                                            </td>
                                            <td class="text-end fw-bold">(<?= toRp($sim['koreksi_fiskal']['value']) ?>)</td>
                                            <td class="text-end">Rekonsiliasi Pajak <span class="badge bg-danger ms-2">Klik Detail</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="hiddenRow p-0">
                                                <div class="collapse" id="collapseKoreksiFiskal">
                                                    <table class="table table-borderless mb-0 bg-light">
                                                        <tbody>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Koreksi Fiskal Positif</td>
                                                                <td class="text-end" style="width: 33%;"><?= toRp($sim['koreksi_fiskal']['matching']['koreksi_positif']) ?></td>
                                                                <td>UU PPh Pasal 9</td>
                                                            </tr>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Koreksi Fiskal Negatif</td>
                                                                <td class="text-end">(<?= toRp($sim['koreksi_fiskal']['matching']['koreksi_negatif']) ?>)</td>
                                                                <td>UU PPh Pasal 11</td>
                                                            </tr>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Penghasilan Bersifat Final</td>
                                                                <td class="text-end"><?= toRp($sim['koreksi_fiskal']['matching']['penghasilan_final']) ?></td>
                                                                <td>UU PPh Pasal 4(2)</td>
                                                            </tr>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Penghasilan Bukan Objek Pajak</td>
                                                                <td class="text-end"><?= toRp($sim['koreksi_fiskal']['matching']['penghasilan_bukan_objek']) ?></td>
                                                                <td>UU PPh Pasal 4(3) </td>
                                                            </tr>
                                                            <tr class="text-muted" style="font-size: 0.9rem;">
                                                                <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Kompensasi Kerugian Fiskal</td>
                                                                <td class="text-end"><?= toRp($sim['koreksi_fiskal']['matching']['kompensasi_kerugian']) ?></td>
                                                                <td>UU PPh Pasal 6(2)</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>

                                    <?php if($analisa['jenis_wp'] == 'OP'){ ?>
                                        <tr class="table-danger">
                                            <td>Penghasilan Tidak Kena Pajak</td>
                                            <td class="text-end fw-bold"><?= toRp($sim['ptkp']['value']) ?></td>
                                            <td>PTKP</td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>

                                <!-- 9. Penghasilan Kena Pajak (PKP) -->
                                <tr class="table-warning">
                                    <td class="fw-bold">Penghasilan Kena Pajak</td>
                                    <td class="text-end fw-bold"><?= toRp($sim['pkp']['value']) ?></td>
                                    <td>PKP Dasar Tarif</td>
                                </tr>

                                <!-- 10. PPh Terutang (Wajar) -->
                                <tr class="border-top-2">
                                    <td><b>PPh Terutang (Wajar)</b></td>
                                    <td class="text-end fw-bold text-primary"><?= toRp($sim['pajak_terutang']['value']) ?></td>
                                    <td><?= $sim['pajak_terutang']['tarif_ket'] ?></td>
                                </tr>

                                <!-- 11. Kredit Pajak (Accordion Parent) -->
                                <tr class="accordion-toggle cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseKreditPajak" aria-expanded="false" aria-controls="collapseKreditPajak">
                                    <td class="fw-bold text-success">
                                        <i data-lucide="chevron-right" class="chevron-icon me-2 text-success"></i>
                                        Kredit Pajak (Potongan & Angsuran)
                                    </td>
                                    <td class="text-end fw-bold text-success">- <?= toRp($sim['kredit_pajak']['value']) ?></td>
                                    <td class="text-end">Lihat Rincian <span class="badge bg-success ms-2">Klik Detail</span></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="hiddenRow p-0">
                                        <div class="collapse" id="collapseKreditPajak">
                                            <table class="table table-borderless mb-0 bg-light">
                                                <tbody>
                                                    <tr class="text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Bukti Potong Pihak ke-3</td>
                                                        <td class="text-end" style="width: 33%;"><?= toRp($sim['kredit_pajak']['matching']['setoran']['bukti_potong']) ?></td>
                                                        <td>PPh Pasal 21/22/23</td>
                                                    </tr>
                                                    <tr class="text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Setoran PPh Pasal 25</td>
                                                        <td class="text-end"><?= toRp($sim['kredit_pajak']['matching']['setoran']['pph_25']) ?></td>
                                                        <td>Angsuran Masa</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>

                                <!-- 12. PPh Sudah Dibayar / Setoran (Accordion Parent) -->
                                <tr class="accordion-toggle cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseSetoran" aria-expanded="false" aria-controls="collapseSetoran">
                                    <td class="fw-bold text-success">
                                        <i data-lucide="chevron-right" class="chevron-icon me-2 text-success"></i>
                                        Setoran Pelunasan PPh
                                    </td>
                                    <td class="text-end fw-bold text-success">- <?= toRp($sim['setoran']['value']) ?></td>
                                    <td class="text-end">Lihat Setoran <span class="badge bg-success ms-2">Klik Detail</span></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="hiddenRow p-0">
                                        <div class="collapse" id="collapseSetoran">
                                            <table class="table table-borderless mb-0 bg-light">
                                                <tbody>
                                                    <?php if($analisa['is_umkm'] == 0) { ?>
                                                    <tr class="text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Pelunasan PPh Pasal 29</td>
                                                        <td class="text-end" style="width: 33%;"><?= toRp($sim['setoran']['matching']['setoran']['pph_29']) ?></td>
                                                        <td>SPT Tahunan</td>
                                                    </tr>
                                                    <?php }else{ ?>
                                                    <tr class="text-muted" style="font-size: 0.9rem;">
                                                        <td style="padding-left: 3rem; position: relative;"><span style="position: absolute; left: 1.5rem; color: #adb5bd;">└</span>Pelunasan PPh Final (UMKM)</td>
                                                        <td class="text-end"><?= toRp($sim['setoran']['matching']['setoran']['pph_final']) ?></td>
                                                        <td>Total Setoran PPh Final</td>
                                                    </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>

                                <!-- 13. Potensi Kurang Bayar (Audit Gap) -->
                                <?php if($sim['pph_kb_lb'] > 0) { ?>
                                <tr class="table-danger">
                                    <td class="fw-bold text-danger"><i data-lucide="shield-alert" class="text-danger"></i> Potensi Kurang Bayar (Tax Gap)</td>
                                    <td class="text-end fw-bold text-danger"><?= toRp($sim['pph_kb_lb']) ?></td>
                                    <td class="fw-bold">Rekomendasi Potensi Pajak</td>
                                </tr>
                                <?php }else{ ?>
                                <tr class="table-success">
                                    <td class="fw-bold text-success"><i data-lucide="shield-check" class="text-success"></i> Potensi Kelebihan Bayar</td>
                                    <td class="text-end fw-bold text-success"><?= toRp($sim['pph_kb_lb']) ?></td>
                                    <td class="fw-bold">Rekomendasi Restitusi Pajak</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">Data simulasi tidak tersedia untuk record lama. Jalankan ulang analisa.</div>
                    <?php endif; ?>
                </div>

                
            <!-- 2. UJI KEAWARAN SALDO DAN ARUS KAS BERSIH -->
                <div class="row g-4 mb-4">
                    
                    <div class="col-md-6">
                        <div class="card card-custom p-4 h-100 border-top border-4 border-danger">
                            <h6 class="fw-bold mb-3 d-flex align-items-center">
                                <i data-lucide="shuffle" class="me-2 text-warning"></i> Arus Kas Bersih
                            </h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">Saldo Kas</span>
                                <span class="fw-bold"><?= toRp($data['kewajaran_saldo']['arus_kas']['saldo_kas'] ?? 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small"><b>Arus Kas Bersih</b></span>
                                <span class="fw-bold">(<?= toRp($data['kewajaran_saldo']['arus_kas']['kas_bersih'] ?? 0) ?>)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">└ Arus Kas Operasi</span>
                                <span class="small"><?= toRp($data['kewajaran_saldo']['arus_kas']['kas_operasi'] ?? 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">└ Arus Kas Investasi</span>
                                <span class="small"><?= toRp($data['kewajaran_saldo']['arus_kas']['kas_investasi'] ?? 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">└ Arus Kas Pendanaan</span>
                                <span class="small"><?= toRp($data['kewajaran_saldo']['arus_kas']['kas_pendanaan'] ?? 0) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-bold">Selisih Saldo Kas</span>
                                <span class="badge bg-warning text-dark"><?= toRp($data['kewajaran_saldo']['arus_kas']['selisih_kas'] ?? 0) ?></span>
                            </div>
                            <div>
                                <span class="small">└ Saldo Kas Awal Tahun Ini</span>
                                <span class="small"><?= toRp($data['kewajaran_saldo']['arus_kas']['kas_awal_ini'] ?? 0) ?></span>
                            </div>
                            <div>
                                <span class="small">└ Saldo Kas Akhir Tahun Lalu</span>
                                <span class="small">(<?= toRp($data['kewajaran_saldo']['arus_kas']['kas_akhir_lalu'] ?? 0) ?>)</span>
                            </div>
                            <div colspan="5" class="p-2 small text-muted border-top">
                            <i data-lucide="info" class="small"></i> <b>Saldo Kas Awal = Saldo Kas - Arus Kas Bersih</b>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-custom p-4 h-100 border-top border-4 border-success">
                            <h6 class="fw-bold mb-3 d-flex align-items-center">
                                <i data-lucide="scale" class="me-2 text-info"></i> Kewajaran Saldo Neraca
                            </h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">Selisih Persediaan</span>
                                <div>
                                <span class="fw-bold"><?= toRp($data['kewajaran_saldo']['persediaan']['gap'] ?? 0) ?></span>
                                <span><?= $data['kewajaran_saldo']['persediaan']['gap'] == 0 ? '<i data-lucide="check-circle" class="text-success"></i>' : '<i data-lucide="x-circle" class="text-danger"></i>' ?></span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">Selisih Penyusutan</span>
                                <div>
                                <span class="fw-bold text-end"><?= toRp($data['kewajaran_saldo']['penyusutan']['gap'] ?? 0) ?></span>
                                <span><?= $data['kewajaran_saldo']['penyusutan']['gap'] == 0 ? '<i data-lucide="check-circle" class="text-success"></i>' : '<i data-lucide="x-circle" class="text-danger"></i>' ?></span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">Selisih Aktiva Tetap</span>
                                <div>
                                <span class="fw-bold text-end"><?= toRp($data['kewajaran_saldo']['aset']['gap'] ?? 0) ?></span>
                                <span><?= $data['kewajaran_saldo']['aset']['gap'] == 0 ? '<i data-lucide="check-circle" class="text-success"></i>' : '<i data-lucide="x-circle" class="text-danger"></i>' ?></span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">Selisih Utang</span>
                                <div>
                                <span class="fw-bold text-end"><?= toRp($data['kewajaran_saldo']['utang']['gap'] ?? 0) ?></span>
                                <span><?= $data['kewajaran_saldo']['utang']['gap'] == 0 ? '<i data-lucide="check-circle" class="text-success"></i>' : '<i data-lucide="x-circle" class="text-danger"></i>' ?></span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">Selisih Modal</span>
                                <div>
                                <span class="fw-bold text-end"><?= toRp($data['kewajaran_saldo']['modal']['gap'] ?? 0) ?></span>
                                <span><?= $data['kewajaran_saldo']['modal']['gap'] == 0 ? '<i data-lucide="check-circle" class="text-success"></i>' : '<i data-lucide="x-circle" class="text-danger"></i>' ?></span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-bold">Selisih Saldo Neraca</span>
                                <span class="badge bg-danger"><?= toRp($data['kewajaran_saldo']['selisih'] ?? 0) ?></span>
                            </div>
                            <div>
                                <span class="small">└ Total Aktiva</span>
                                <span class="small"><?= toRp($data['kewajaran_saldo']['total_aktiva'] ?? 0) ?></span>
                            </div>
                            <div>
                                <span class="small">└ Total Pasiva</span>
                                <span class="small"><?= toRp($data['kewajaran_saldo']['total_pasiva'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                  
                <!-- 3. EXPENSE VERIFICATION (GAJI & JASA) -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card card-custom p-4 h-100 border-top border-4 border-info">
                            <h6 class="fw-bold mb-3 d-flex align-items-center">
                                <i data-lucide="users" class="me-2 text-info"></i> Gaji vs PPh 21
                            </h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">Biaya Gaji (Buku)</span>
                                <span class="fw-bold"><?= toRp($sim['beban_usaha']['matching']['pembukuan']['gaji'] ?? 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">DPP PPh 21 (Bupot)</span>
                                <span class="fw-bold"><?= toRp($sim['beban_usaha']['matching']['bupot']['gaji'] ?? 0) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-bold">Selisih Objek</span>
                                <span class="badge bg-danger"><?= toRp($sim['beban_usaha']['matching']['pembukuan']['gaji'] ?? 0 - $sim['beban_usaha']['matching']['bupot']['gaji'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-custom p-4 h-100 border-top border-4 border-warning">
                            <h6 class="fw-bold mb-3 d-flex align-items-center">
                                <i data-lucide="wrench" class="me-2 text-warning"></i> Jasa/Sewa vs PPh 23
                            </h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">Biaya Jasa (Buku)</span>
                                <span class="fw-bold"><?= toRp($sim['beban_usaha']['matching']['pembukuan']['jasa'] ?? 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small">DPP PPh 23 (Bupot)</span>
                                <span class="fw-bold"><?= toRp($sim['beban_usaha']['matching']['bupot']['jasa'] ?? 0) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-bold">Selisih Objek</span>
                                <span class="badge bg-warning text-dark"><?= toRp($sim['beban_usaha']['matching']['pembukuan']['biaya_operasional'] ?? 0 - $sim['beban_usaha']['matching']['bupot']['jasa'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
               
                    
               
<?php if($analisa['is_umkm'] != 1) { ?>
                <!-- 4. ANALISA RASIO KEUANGAN & BENCHMARK -->
                <div class="card card-custom mb-4 overflow-hidden border-top border-4 border-indigo" style="border-top-color: #6366f1 !important;">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold m-0 d-flex align-items-center">
                            <i data-lucide="trending-up" class="me-2 text-indigo"></i> Financial Ratios & Industry Benchmark
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Rasio Keuangan</th>
                                    <th class="text-center">Nilai WP</th>
                                    <th class="text-center">Benchmark KLU</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $ratios = $data['rasio'] ?? [];
                                foreach($ratios as $key => $r):
                                    $val = $r['value'] ?? 0;
                                    $bench = $r['benchmark'] ?? 0;
                                    $is_percent = !in_array($key, ['der', 'cr']);
                                    
                                    // Status Logic
                                    $status = 'Fair';
                                    $badge = 'success';
                                    $text_color='light';
                                    if ($key == 'cttor') {
                                        if ($val < $bench) { $status = 'Undervalue'; $badge = 'danger'; $text_color='light';}
                                    } elseif ($key == 'der') {
                                        if ($val > $bench) { $status = 'Over Leverage'; $badge = 'warning'; $text_color='dark';}
                                    } elseif ($key == 'cr') {
                                        if ($val < $bench) { $status = 'Low Liquidity'; $badge = 'warning'; $text_color='dark';}
                                    } else {
                                        if ($val < ($bench * 0.5)) { $status = 'Unfair'; $badge = 'danger'; $text_color='light';}
                                        elseif ($val < $bench) { $status = 'Undervalue'; $badge = 'warning'; $text_color='dark';}
                                    }
                                ?>
                                <tr class="accordion-toggle-sub border-bottom text-primary cursor-pointer" data-bs-toggle="collapse"  aria-expanded="false" style="font-size: 0.9rem;" data-bs-target="#rowKet<?= $key ?>" aria-expanded="false" aria-controls="rowKet<?= $key ?>" class="cursor-pointer">
                                    <td class="fw-bold text-uppercase"><i data-lucide="chevron-right" class="chevron-icon-sub me-1"></i> <?= str_replace('_', ' ', $key) ?></td>
                                    <td class="text-center fw-bold"><?= $is_percent ? number_format($val, 2) . '%' : number_format($val, 2) ?></td>
                                    <td class="text-center text-muted"><?= $is_percent ? number_format($bench, 2) . '%' : number_format($bench, 2) ?></td>
                                    <td class="text-center"><span class="badge  bg-<?= $badge ?> text-<?= $text_color ?>"><?= $status ?></span></td>
    
                                    </tr>
                                    <tr class="collapse bg-light" id="rowKet<?= $key ?>">
                                        <td colspan="5" class="p-2 small text-muted border-top">
                                            <?= nl2br(htmlspecialchars($r['ket'])) ?>
                                        </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 5. TREND ANALISA 5 TAHUN (DYNAMIC CHART) -->
                <div class="card card-custom mb-4 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold m-0 d-flex align-items-center">
                            <i data-lucide="bar-chart-3" class="me-2 text-primary"></i> Tren Performa vs Benchmark (5 Tahun)
                        </h6>
                        <div class="col-md-4">
                            <select id="ratioSelector" class="form-select form-select-sm shadow-sm" onchange="updateChart()">
                                <option value="gpm">Gross Profit Margin (GPM)</option>
                                <option value="opm">Operating Profit Margin (OPM)</option>
                                <option value="npm">Net Profit Margin (NPM)</option>
                                <option value="cttor">CTTOR</option>
                                <option value="der">Debt to Equity Ratio (DER)</option>
                            </select>
                        </div>
                    </div>
                    <div id="benchmark_chart" style="width: 100%; height: 350px;"></div>
                </div>

               
                

            </div>
        </div>
<?php }else{ ?>
<div class="alert alert-light border p-4 text-center">
                        <i data-lucide="alert-triangle" class="text-warning mb-2" style="width:40px; height:40px;"></i>
                        <h6>Bencmarking Tidak Bisa Ditampilkan</h6>
                        <p class="small text-muted mb-0">Data Bencmarking hanya untuk Wajib Pajak yang menggunakan Pembukuan</p>
                    </div>
<?php } ?>

<!-- RAW DATA VIEWER -->
                <div class="mt-4 text-end">
                    <button class="btn btn-link btn-sm text-muted text-decoration-none" data-bs-toggle="collapse" data-bs-target="#collapseJson">
                        <i data-lucide="code" class="inline me-1" style="width:14px;"></i> Lihat Data Mentah (JSON)
                    </button>
                    <div class="collapse mt-2" id="collapseJson">
                        <pre class="bg-dark text-white p-3 rounded text-start small"><?= json_encode($data, JSON_PRETTY_PRINT) ?></pre>
                    </div>
                </div>

        <div class="footer text-center mt-5 mb-5">
            <p class="text-muted small">SHARP Engine v3.0 | Systematic Hybrid Analysis Risk for Tax</p>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    lucide.createIcons();

    // Data History from PHP
    const historyData = <?= json_encode($hist_data) ?>;
    
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawChart);

    function drawChart(ratioKey = 'gpm') {
        const dataArray = [['Tahun', 'Nilai WP', 'Benchmark']];
        
        // Prepare Data
        Object.keys(historyData).sort().forEach(thn => {
            const r = historyData[thn][ratioKey] || {};
            dataArray.push([
                thn.toString(), 
                parseFloat(r.value || 0), 
                parseFloat(r.benchmark || 0)
            ]);
        });

        if (dataArray.length === 1) {
            document.getElementById('benchmark_chart').innerHTML = '<div class="h-100 d-flex align-items-center justify-content-center text-muted">Data history tidak mencukupi untuk grafik.</div>';
            return;
        }

        const data = google.visualization.arrayToDataTable(dataArray);
        const options = {
            title: 'Tren ' + ratioKey.toUpperCase() + ' vs Benchmark',
            curveType: 'function',
            legend: { position: 'bottom' },
            colors: ['#1e3a8a', '#dc2626'], // #dc2626 = merah
            chartArea: { width: '85%', height: '70%' },
            hAxis: { title: 'Tahun' },
            vAxis: { title: 'Nilai (%)' },
            animation: { startup: true, duration: 1000, easing: 'out' },
            series: {
                0: { lineDashStyle: [0] },      // Nilai WP: solid
                1: { lineDashStyle: [4, 4] }   // Benchmark: dashed
            }
        };

        const chart = new google.visualization.LineChart(document.getElementById('benchmark_chart'));
        chart.draw(data, options);
    }

    function updateChart() {
        const key = document.getElementById('ratioSelector').value;
        drawChart(key);
    }

    window.onresize = () => drawChart(document.getElementById('ratioSelector').value);

   //Animasi ioon '>' akordion togle
   document.addEventListener('DOMContentLoaded', function () {
            var toggleRows = document.querySelectorAll('.accordion-toggle');
            
            toggleRows.forEach(function(row) {
                row.addEventListener('click', function() {
                    var isExpanded = this.getAttribute('aria-expanded') === 'true';
                    // The aria-expanded attribute updates *after* the click event starts processing,
                    // so we use the current state to predict the next state.
                    // If it was expanded (true), it's closing. If it was closed (false), it's opening.
                });
            });
        });
    
</script>
</body>
</html>

