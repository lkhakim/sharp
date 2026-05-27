<?php
/**
 * SHARP - Modul Benchmark KLU
 * Fitur: CRUD data rasio benchmark DJP (Berdasarkan SE-139/PJ/2010 atau data AI).
 */
require_once 'config.php';
session_start();

// Proteksi Halaman
if (!isset($_SESSION['user_id'])) {
    // header("Location: login.php");
    // exit;
}

$message = "";

// 1. Logika Hapus Benchmark
if (isset($_GET['delete'])) {
    try {
        $stmt = $db->prepare("DELETE FROM benchmark_klu WHERE klu = ?");
        $stmt->execute([$_GET['delete']]);
        $message = "<div class='alert alert-success'>Data Benchmark berhasil dihapus.</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menghapus: " . $e->getMessage() . "</div>";
    }
}

// 2. Logika Simpan (Tambah / Edit) Benchmark
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_benchmark'])) {
    $klu = $_POST['klu'] ?? '';
    $nama = $_POST['nama_klasifikasi_usaha'] ?? '';
    $npm = $_POST['npm'] ?? 0;
    $opm = $_POST['opm'] ?? 0;
    $gpm = $_POST['gpm'] ?? 0;
    $rasio_gaji = $_POST['rasio_gaji_omset'] ?? 0;
    $cttor = $_POST['cttor'] ?? 0;
    $der = $_POST['der'] ?? 0;
    $cr = $_POST['current_ratio'] ?? 0;
    
    try {
        // Menggunakan ON DUPLICATE KEY UPDATE untuk menggabungkan Insert dan Edit
        $sql = "INSERT INTO benchmark_klu 
                (klu, nama_klasifikasi_usaha, npm, opm, gpm, rasio_gaji_omset, cttor, der, current_ratio) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                nama_klasifikasi_usaha = VALUES(nama_klasifikasi_usaha),
                npm = VALUES(npm), opm = VALUES(opm), gpm = VALUES(gpm),
                rasio_gaji_omset = VALUES(rasio_gaji_omset), cttor = VALUES(cttor),
                der = VALUES(der), current_ratio = VALUES(current_ratio)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$klu, $nama, $npm, $opm, $gpm, $rasio_gaji, $cttor, $der, $cr]);
        $message = "<div class='alert alert-success'>Data Benchmark KLU <strong>$klu</strong> berhasil disimpan.</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menyimpan: " . $e->getMessage() . "</div>";
    }
}

// 3. Ambil Daftar Benchmark
try {
    $benchmarks = $db->query("SELECT * FROM benchmark_klu ORDER BY klu ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $benchmarks = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benchmark KLU - SHARP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; }
        body { 
            background-color: var(--bg); 
            font-family: 'Inter', sans-serif; 
            padding-bottom: 85px;
        }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.2rem; }
        .table-responsive { max-height: 70vh; overflow-y: auto; }
        thead th { position: sticky; top: 0; background-color: #f1f5f9 !important; z-index: 1; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>
<div class="main-content">
<div class="container-fluid px-4 mt-4">
            <i data-lucide="bar-chart" class="me-2 text-warning"></i> MASTER BENCHMARK KLU
</div>


<div class="container-fluid px-4">
    <?php echo $message; ?>
    <div class="row g-4">
        
        <!-- Panel Daftar Tabel Benchmark -->
        <div class="col-lg-8">
            <div class="card card-custom bg-white">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold m-0">Database Standar Benchmark DJP</h6>
                    <div class="input-group" style="width: 250px;">
                        <span class="input-group-text bg-white"><i data-lucide="search" style="width:14px;"></i></span>
                        <input type="text" id="searchKlu" class="form-control form-control-sm border-start-0 ps-0" placeholder="Cari KLU..." onkeyup="filterTable()">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-center" id="kluTable" style="font-size: 0.85rem;">
                        <thead class="table-light text-muted">
                            <tr>
                                <th class="text-start ps-3">KLU</th>
                                <th>GPM</th>
                                <th>OPM</th>
                                <th>NPM</th>
                                <th>Gaji/Omset</th>
                                <th>CTTOR</th>
                                <th>DER</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($benchmarks)): ?>
                            <tr><td colspan="8" class="text-muted py-4">Data Benchmark KLU masih kosong.</td></tr>
                            <?php else: foreach($benchmarks as $b): ?>
                            <tr class="klu-row">
                                <td class="text-start ps-3">
                                    <div class="fw-bold text-primary klu-code"><?php echo htmlspecialchars($b['klu']); ?></div>
                                    <div class="text-sm" style="max-width: 200px; font-size: 0.75rem;" title="<?php echo htmlspecialchars($b['nama_klasifikasi_usaha']); ?>">
                                        <?php echo htmlspecialchars($b['nama_klasifikasi_usaha']); ?>
                                    </div>
                                </td>
                                <td><?php echo number_format($b['gpm'], 2); ?>%</td>
                                <td><?php echo number_format($b['opm'], 2); ?>%</td>
                                <td><?php echo number_format($b['npm'], 2); ?>%</td>
                                <td><?php echo number_format($b['rasio_gaji_omset'], 2); ?>%</td>
                                <td><?php echo number_format($b['cttor'], 2); ?></td>
                                <td><?php echo number_format($b['der'], 2); ?></td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="editData(<?php echo htmlspecialchars(json_encode($b)); ?>)">
                                        <i data-lucide="edit-3" style="width:14px;"></i>
                                    </button>
                                    <a href="?delete=<?php echo $b['klu']; ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Hapus benchmark KLU <?php echo $b['klu']; ?>?')">
                                        <i data-lucide="trash-2" style="width:14px;"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    
        <!-- Panel Form Input -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0 text-primary" id="formTitle">Tambah / Edit KLU</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="resetForm()" title="Reset Form">
                        <i data-lucide="refresh-cw" style="width: 14px;"></i>
                    </button>
                </div>
                
                <form method="POST" action="" id="kluForm">
                    <div class="mb-3">
                        <label class="form-label">Kode KLU</label>
                        <input type="text" name="klu" id="klu" class="form-control form-control-sm" placeholder="Contoh: 46100" required>
                        <small class="text-muted" style="font-size: 0.7rem;">Gunakan KLU sebagai Primary Key</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Klasifikasi Usaha</label>
                        <textarea name="nama_klasifikasi_usaha" id="nama_klasifikasi_usaha" class="form-control form-control-sm" rows="2" required placeholder="Perdagangan Besar..."></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label">GPM (%)</label>
                            <input type="number" step="0.01" name="gpm" id="gpm" class="form-control form-control-sm" placeholder="0.00">
                        </div>
                        <div class="col-4">
                            <label class="form-label">OPM (%)</label>
                            <input type="number" step="0.01" name="opm" id="opm" class="form-control form-control-sm" placeholder="0.00">
                        </div>
                        <div class="col-4">
                            <label class="form-label">NPM (%)</label>
                            <input type="number" step="0.01" name="npm" id="npm" class="form-control form-control-sm" placeholder="0.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-4">
                            <label class="form-label">Gaji / Omset</label>
                            <input type="number" step="0.01" name="rasio_gaji_omset" id="rasio_gaji_omset" class="form-control form-control-sm" placeholder="0.00">
                        </div>
                        <div class="col-4">
                            <label class="form-label">CTTOR</label>
                            <input type="number" step="0.01" name="cttor" id="cttor" class="form-control form-control-sm" placeholder="0.00">
                        </div>
                        <div class="col-4">
                            <label class="form-label">DER</label>
                            <input type="number" step="0.01" name="der" id="der" class="form-control form-control-sm" placeholder="0.00">
                        </div>
                    </div>

                    <button type="submit" name="save_benchmark" class="btn btn-primary w-100 fw-bold">
                        <i data-lucide="save" class="inline me-1" style="width:16px;"></i> Simpan Standar KLU
                    </button>
                </form>
            </div>
        </div>

    
    
    
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    // Fungsi mengisi form ketika tombol edit ditekan
    function editData(data) {
        document.getElementById('formTitle').innerText = "Edit KLU: " + data.klu;
        document.getElementById('klu').value = data.klu;
        document.getElementById('klu').readOnly = true; // KLU jadi primary key, jangan diubah saat edit
        document.getElementById('nama_klasifikasi_usaha').value = data.nama_klasifikasi_usaha;
        document.getElementById('npm').value = data.npm;
        document.getElementById('opm').value = data.opm;
        document.getElementById('gpm').value = data.gpm;
        document.getElementById('rasio_gaji_omset').value = data.rasio_gaji_omset;
        document.getElementById('cttor').value = data.cttor;
        document.getElementById('der').value = data.der;
        document.getElementById('current_ratio').value = data.current_ratio || 0;
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Fungsi untuk reset form
    function resetForm() {
        document.getElementById('formTitle').innerText = "Tambah / Edit KLU";
        document.getElementById('kluForm').reset();
        document.getElementById('klu').readOnly = false;
    }

    // Filter tabel secara live
    function filterTable() {
        let input = document.getElementById("searchKlu").value.toUpperCase();
        let rows = document.querySelectorAll(".klu-row");

        rows.forEach(row => {
            let kluCode = row.querySelector(".klu-code").innerText.toUpperCase();
            let kluName = row.querySelector(".klu-name").innerText.toUpperCase();
            
            if (kluCode.includes(input) || kluName.includes(input)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }
</script>
</body>
</html>