<?php
/**
 * SHARP - Modul Laporan Hasil Pemeriksaan (LHP)
 * Digunakan untuk mencatat ketetapan pajak akhir setelah proses SP2DK.
 */

require_once 'config.php';
session_start();

$npwp = $_GET['npwp'] ?? '01.234.567.8-001.000';
$tahun = $_GET['tahun'] ?? date('Y');

// === CATAT LOG AKSES HALAMAN ===
if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'] ?? 'Unknown', 'LHP', "Membuka riwayat ketetapan pajak/LHP untuk NPWP: $npwp");
}

// Proses Simpan LHP
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lhp'])) {
    try {
        $sql = "INSERT INTO lhp_log 
                (nomer_ketetapan, jenis_ketetapan, jenis_pemeriksaan, jenis_pajak, nilai_pajak, tgl_ketetapan, tahun_pajak, tahun, npwp, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $_POST['nomer_ketetapan'],
            $_POST['jenis_ketetapan'],
            $_POST['jenis_pemeriksaan'],
            $_POST['jenis_pajak'],
            $_POST['nilai_pajak'],
            $_POST['tgl_ketetapan'],
            $_POST['tahun_pajak'],
            $tahun,
            $npwp
        ]);
        $message = "<div class='alert alert-success'>Data LHP berhasil dicatat!</div>";

        // === CATAT LOG AKTIVITAS SIMPAN KETETAPAN LHP ===
        if (isset($_SESSION['user_id'])) {
            $nilai_format = number_format($_POST['nilai_pajak'], 0, ',', '.');
            catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'] ?? 'Unknown', 'LHP', "Mencatat ketetapan pajak baru (".$_POST['jenis_ketetapan'].") untuk NPWP: $npwp senilai Rp $nilai_format");
        }
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menyimpan: " . $e->getMessage() . "</div>";
    }
}

// Fetch Data WP
try {
    $stmtWp = $db->prepare("SELECT nama FROM profil_wp WHERE npwp = ?");
    $stmtWp->execute([$npwp]);
    $wp = $stmtWp->fetch(PDO::FETCH_ASSOC);

    // Fetch History LHP
    $stmtHistory = $db->prepare("SELECT * FROM lhp_log WHERE npwp = ? ORDER BY tgl_ketetapan DESC");
    $stmtHistory->execute([$npwp]);
    $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LHP & Ketetapan - SHARP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Inter', sans-serif; padding-bottom: 50px; }
        .navbar-sharp { background-color: var(--primary); color: white; }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #475569; }
    </style>
</head>
<body>

<nav class="navbar navbar-sharp mb-4 shadow">
    <div class="container">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center" href="monitoring_kolektif.php">
            <i data-lucide="chevron-left" class="me-2"></i> Laporan Akhir (LHP)
        </a>
    </div>
</nav>

<div class="container">
    <div class="row g-4">
        <!-- Panel Kiri: Form Input -->
        <div class="col-lg-5">
            <div class="card card-custom p-4 bg-white">
                <div class="mb-4">
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($wp['nama'] ?? 'Wajib Pajak'); ?></h5>
                    <small class="text-muted">NPWP: <?php echo $npwp; ?></small>
                </div>

                <hr>
                
                <?php echo $message; ?>

                <form method="POST" action="">
                    <h6 class="fw-bold text-primary mb-3">Input Ketetapan Baru</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Nomor Ketetapan / LHP</label>
                        <input type="text" name="nomer_ketetapan" class="form-control" placeholder="LHP-123/WPJ.../2024" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Ketetapan</label>
                            <select name="jenis_ketetapan" class="form-select">
                                <option value="SKPKB">SKPKB (Kurang Bayar)</option>
                                <option value="SKPN">SKPN (Nihil)</option>
                                <option value="SKPLB">SKPLB (Lebih Bayar)</option>
                                <option value="STP">STP (Tagihan)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Pemeriksaan</label>
                            <select name="jenis_pemeriksaan" class="form-select">
                                <option value="Pemeriksaan Khusus">Pemeriksaan Khusus</option>
                                <option value="Pemeriksaan Rutin">Pemeriksaan Rutin</option>
                                <option value="Penelitian">Penelitian (SP2DK)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Pajak</label>
                        <select name="jenis_pajak" class="form-select">
                            <option value="PPh Badan">PPh Badan</option>
                            <option value="PPh OP">PPh OP</option>
                            <option value="PPN">PPN</option>
                            <option value="PPh Final">PPh Final</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nilai Ketetapan (Rp)</label>
                        <input type="number" name="nilai_pajak" class="form-control" placeholder="0" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tgl Ketetapan</label>
                            <input type="date" name="tgl_ketetapan" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tahun Pajak</label>
                            <input type="number" name="tahun_pajak" class="form-control" value="<?php echo $tahun; ?>" required>
                        </div>
                    </div>

                    <button type="submit" name="save_lhp" class="btn btn-primary w-100 fw-bold py-2 mt-2">
                        <i data-lucide="save" class="me-1" style="width:18px"></i> Simpan LHP
                    </button>
                </form>
            </div>
        </div>

        <!-- Panel Kanan: Riwayat -->
        <div class="col-lg-7">
            <div class="card card-custom p-4 bg-white h-100">
                <h6 class="fw-bold mb-4 d-flex align-items-center">
                    <i data-lucide="history" class="me-2 text-primary"></i> Riwayat Ketetapan Pajak
                </h6>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No. LHP / Tgl</th>
                                <th>Jenis</th>
                                <th class="text-end">Nilai (Rp)</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted small">Belum ada data ketetapan pajak.</td>
                            </tr>
                            <?php else: foreach($history as $h): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold small"><?php echo $h['nomer_ketetapan']; ?></div>
                                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($h['tgl_ketetapan'])); ?></small>
                                </td>
                                <td>
                                    <div class="small fw-bold"><?php echo $h['jenis_pajak']; ?></div>
                                    <small class="badge bg-light text-dark border" style="font-size: 0.6rem;"><?php echo $h['jenis_ketetapan']; ?></small>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php echo number_format($h['nilai_pajak'], 0, ',', '.'); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success" style="font-size: 0.65rem;">TERBIT</span>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-auto p-3 bg-light rounded border text-center">
                    <div class="small text-muted mb-1">Total Realisasi Penerimaan (Semua Tahun)</div>
                    <h4 class="fw-bold text-primary mb-0">
                        Rp <?php 
                            $total = array_sum(array_column($history, 'nilai_pajak'));
                            echo number_format($total, 0, ',', '.');
                        ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>