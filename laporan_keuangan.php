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

// Fetch Profile
$stmtWp = $db->prepare("SELECT nama FROM profil_wp WHERE npwp = ?");
$stmtWp->execute([$npwp]);
$wp = $stmtWp->fetch(PDO::FETCH_ASSOC);

if (!$wp) {
    die("<div class='container mt-5 alert alert-warning'>Data WP tidak ditemukan.</div>");
}

// Fetch Accounts from mapping_akun
$stmt = $db->prepare("SELECT * FROM mapping_akun WHERE npwp = ? AND tahun = ? ORDER BY kode_akun ASC");
$stmt->execute([$npwp, $tahun]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorize Accounts
$data = [
    'aktiva' => [
        'lancar' => [],
        'tetap' => [],
        'lainnya' => []
    ],
    'pasiva' => [
        'utang' => [],
        'modal' => []
    ],
    'pendapatan' => [],
    'beban' => [],
    'hpp' => []
];

$totals = [
    'aktiva' => 0,
    'pasiva' => 0,
    'pendapatan' => 0,
    'beban' => 0,
    'hpp' => 0
];

foreach ($accounts as $acc) {
    $cat = $acc['kategori_akun'];
    $nom = (float)$acc['nominal'];
    $jenis = strtoupper($acc['jenis']); // DEBIT or KREDIT

    // Rugi Laba
    if (in_array($cat, ['peredaran_usaha', 'pendapatan_lain'])) {
        $data['pendapatan'][] = $acc;
        $totals['pendapatan'] += ($jenis == 'KREDIT' ? $nom : -$nom);
    } elseif (in_array($cat, ['pembelian', 'persediaan_awal', 'persediaan_akhir', 'hpp'])) {
        $data['hpp'][] = $acc;
        if ($cat == 'persediaan_akhir') {
            // Persediaan akhir mengurangi HPP
            $totals['hpp'] -= ($jenis == 'KREDIT' ? $nom : $nom);
        } else {
            $totals['hpp'] += ($jenis == 'DEBIT' ? $nom : $nom);
        }
    } elseif (in_array($cat, ['beban_gaji', 'beban_usaha', 'penyusutan', 'beban_lain'])) {
        $data['beban'][] = $acc;
        $totals['beban'] += ($jenis == 'DEBIT' ? $nom : -$nom);
    } 
    // Neraca
    elseif (in_array($cat, ['kas', 'bank', 'piutang', 'persediaan', 'aset_lancar'])) {
        $data['aktiva']['lancar'][] = $acc;
        $totals['aktiva'] += ($jenis == 'DEBIT' ? $nom : -$nom);
    } elseif (in_array($cat, ['aset_tetap', 'aset_tidak_berwujud', 'akumulasi_penyusutan'])) {
        $data['aktiva']['tetap'][] = $acc;
        // Akumulasi penyusutan adalah contra-asset (KREDIT)
        $totals['aktiva'] += ($jenis == 'DEBIT' ? $nom : -$nom);
    } elseif (in_array($cat, ['utang', 'utang_bank', 'utang_pendek', 'utang_panjang'])) {
        $data['pasiva']['utang'][] = $acc;
        $totals['pasiva'] += ($jenis == 'KREDIT' ? $nom : -$nom);
    } elseif ($cat == 'modal') {
        $data['pasiva']['modal'][] = $acc;
        $totals['pasiva'] += ($jenis == 'KREDIT' ? $nom : -$nom);
    } else {
        $data['aktiva']['lainnya'][] = $acc;
        $totals['aktiva'] += ($jenis == 'DEBIT' ? $nom : -$nom);
    }
}

$laba_kotor = $totals['pendapatan'] - $totals['hpp'];
$laba_bersih = $laba_kotor - $totals['beban'];

// Final Pasiva including Net Profit
$total_pasiva_final = $totals['pasiva'] + $laba_bersih;
$selisih = $totals['aktiva'] - $total_pasiva_final;

function toRp(float $n) {
    return number_format($n, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - <?= htmlspecialchars($wp['nama']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; transition: margin-left 0.3s; }
        .card-report { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; margin-bottom: 20px; }
        .report-header { border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 15px; }
        .table-report td { padding: 8px 0; border: none; font-size: 0.9rem; }
        .table-report .label { color: #475569; }
        .table-report .value { text-align: right; font-weight: 600; color: #1e293b; }
        .table-report .subtotal { border-top: 1px solid #e2e8f0; font-weight: 700; color: #1e3a8a; }
        .table-report .total-row { background: #f1f5f9; font-weight: 800; font-size: 1rem; color: #1e3a8a; }
        @media (max-width: 991px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid px-4 mt-3">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center mb-4">
                <a href="manajemen_akun.php?npwp=<?= $npwp ?>&tahun=<?= $tahun ?>" class="btn btn-sm btn-outline-secondary me-3" title="Kembali">
                    <i data-lucide="arrow-left"></i>
                </a>
                <div>
                <h4 class="fw-bold m-0 text-primary">Laporan Keuangan</h4>
                <span class="text-muted">NPWP: <?= $npwp ?> | <?= htmlspecialchars($wp['nama']) ?> | Tahun: <?= $tahun ?></span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm w-auto" onchange="location.href='?npwp=<?= $npwp ?>&tahun='+this.value">
                    <?php for($y=date('Y'); $y>=2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $tahun==$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                    <i data-lucide="printer" class="inline me-1" style="width:16px"></i> Cetak
                </button>
            </div>
        </div>

        <?php if (abs($selisih) > 1): ?>
            <div class="alert alert-danger shadow-sm d-flex align-items-center">
                <i data-lucide="alert-triangle" class="me-3" style="width:24px; height:24px;"></i>
                <div>
                    <h6 class="fw-bold mb-0">Neraca Tidak Seimbang!</h6>
                    <span>Terdapat selisih antara Aktiva dan Pasiva sebesar <strong>Rp <?= toRp($selisih) ?></strong>. Silakan periksa kembali mapping akun Anda.</span>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success shadow-sm d-flex align-items-center">
                <i data-lucide="check-circle" class="me-3" style="width:24px; height:24px;"></i>
                <div>
                    <h6 class="fw-bold mb-0">Neraca Seimbang</h6>
                    <span>Total Aktiva dan Pasiva sudah sesuai.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- LAPORAN LABA RUGI -->
            <div class="col-lg-6">
                <div class="card card-report p-4">
                    <div class="report-header text-center">
                        <h5 class="fw-bold mb-0">LAPORAN LABA RUGI</h5>
                        <small class="text-muted">Untuk periode yang berakhir pada 31 Desember <?= $tahun ?></small>
                    </div>
                    
                    <table class="table table-report">
                        <!-- PENDAPATAN -->
                        <tr><td colspan="2" class="fw-bold text-dark pt-3">PENDAPATAN</td></tr>
                        <?php foreach($data['pendapatan'] as $acc): ?>
                            <tr>
                                <td class="label"><?= htmlspecialchars($acc['nama_akun']) ?></td>
                                <td class="value"><?= toRp($acc['nominal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="subtotal">
                            <td>TOTAL PENDAPATAN</td>
                            <td class="value"><?= toRp($totals['pendapatan']) ?></td>
                        </tr>

                        <!-- HPP -->
                        <tr><td colspan="2" class="fw-bold text-dark pt-3">HARGA POKOK PENJUALAN</td></tr>
                        <?php foreach($data['hpp'] as $acc): ?>
                            <tr>
                                <td class="label"><?= htmlspecialchars($acc['nama_akun']) ?></td>
                                <td class="value"><?= ($acc['kategori_akun'] == 'persediaan_akhir' ? '(' . toRp($acc['nominal']) . ')' : toRp($acc['nominal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="subtotal">
                            <td>TOTAL HARGA POKOK PENJUALAN</td>
                            <td class="value"><?= toRp($totals['hpp']) ?></td>
                        </tr>

                        <tr class="fw-bold text-primary py-2" style="background: #eff6ff;">
                            <td>LABA KOTOR</td>
                            <td class="value"><?= toRp($laba_kotor) ?></td>
                        </tr>

                        <!-- BEBAN OPERASIONAL -->
                        <tr><td colspan="2" class="fw-bold text-dark pt-3">BEBAN OPERASIONAL</td></tr>
                        <?php foreach($data['beban'] as $acc): ?>
                            <tr>
                                <td class="label"><?= htmlspecialchars($acc['nama_akun']) ?></td>
                                <td class="value"><?= toRp($acc['nominal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="subtotal">
                            <td>TOTAL BEBAN OPERASIONAL</td>
                            <td class="value"><?= toRp($totals['beban']) ?></td>
                        </tr>

                        <tr class="total-row mt-4">
                            <td>LABA (RUGI) BERSIH</td>
                            <td class="value"><?= toRp($laba_bersih) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- NERACA -->
            <div class="col-lg-6">
                <div class="card card-report p-4">
                    <div class="report-header text-center">
                        <h5 class="fw-bold mb-0">NERACA</h5>
                        <small class="text-muted">Per 31 Desember <?= $tahun ?></small>
                    </div>

                    <div class="row">
                        <!-- AKTIVA -->
                        <div class="col-12">
                            <h6 class="fw-bold text-primary mt-2">AKTIVA</h6>
                            <table class="table table-report mb-0">
                                <tr><td colspan="2" class="fw-bold text-dark small">AKTIVA LANCAR</td></tr>
                                <?php foreach($data['aktiva']['lancar'] as $acc): ?>
                                    <tr>
                                        <td class="label ps-3"><?= htmlspecialchars($acc['nama_akun']) ?></td>
                                        <td class="value"><?= toRp($acc['nominal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr><td colspan="2" class="fw-bold text-dark small pt-2">AKTIVA TETAP</td></tr>
                                <?php foreach($data['aktiva']['tetap'] as $acc): ?>
                                    <tr>
                                        <td class="label ps-3"><?= htmlspecialchars($acc['nama_akun']) ?></td>
                                        <td class="value">
                                            <?= ($acc['kategori_akun'] == 'akumulasi_penyusutan' ? '(' . toRp($acc['nominal']) . ')' : toRp($acc['nominal'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if(!empty($data['aktiva']['lainnya'])): ?>
                                    <tr><td colspan="2" class="fw-bold text-dark small pt-2">AKTIVA LAINNYA</td></tr>
                                    <?php foreach($data['aktiva']['lainnya'] as $acc): ?>
                                        <tr>
                                            <td class="label ps-3"><?= htmlspecialchars($acc['nama_akun']) ?></td>
                                            <td class="value"><?= toRp($acc['nominal']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <tr class="total-row">
                                    <td>TOTAL AKTIVA</td>
                                    <td class="value"><?= toRp($totals['aktiva']) ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- PASIVA -->
                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-primary">PASIVA</h6>
                            <table class="table table-report">
                                <tr><td colspan="2" class="fw-bold text-dark small">KEWAJIBAN</td></tr>
                                <?php foreach($data['pasiva']['utang'] as $acc): ?>
                                    <tr>
                                        <td class="label ps-3"><?= htmlspecialchars($acc['nama_akun']) ?></td>
                                        <td class="value"><?= toRp($acc['nominal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr><td colspan="2" class="fw-bold text-dark small pt-2">EKUITAS / MODAL</td></tr>
                                <?php foreach($data['pasiva']['modal'] as $acc): ?>
                                    <tr>
                                        <td class="label ps-3"><?= htmlspecialchars($acc['nama_akun']) ?></td>
                                        <td class="value"><?= toRp($acc['nominal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td class="label ps-3">Laba Tahun Berjalan</td>
                                    <td class="value"><?= toRp($laba_bersih) ?></td>
                                </tr>

                                <tr class="total-row">
                                    <td>TOTAL PASIVA</td>
                                    <td class="value"><?= toRp($total_pasiva_final) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
