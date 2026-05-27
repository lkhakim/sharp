<?php
ob_start(); // Mencegah error "Headers Already Sent" penyebab layar blank
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";

// Parameter Global Dinamis
$npwp_aktif = $_GET['npwp'] ?? '';
$tahun_aktif = $_GET['tahun'] ?? date('Y');
$current_file = basename($_SERVER['PHP_SELF']); // Dinamis mencegah 404

if (empty($npwp_aktif)) {
    header("Location: manajemen_wp.php");
    exit;
}

// Ambil Nama WP untuk Header
try {
    $stmtWp = $db->prepare("SELECT nama FROM profil_wp WHERE npwp = ?");
    $stmtWp->execute([$npwp_aktif]);
    $wp = $stmtWp->fetch();
} catch (Exception $e) {
    $wp = ['nama' => 'WP Tidak Ditemukan'];
}

require_once 'functions_upload.php';

// Handler Hapus Semua (Delete All)
if (isset($_POST['delete_all'])) {
    try {
        $stmt = $db->prepare("DELETE FROM mutasi_bank WHERE npwp = ? AND tahun = ?");
        $stmt->execute([$npwp_aktif, $tahun_aktif]);
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted_all");
        exit;
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menghapus semua data: " . $e->getMessage() . "</div>";
    }
}

// Handler Simpan Data (Single, Bulk AI, & Bulk CSV)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Simpan Single dari Form Manual
    if (isset($_POST['save_bank'])) {
        try {
            $id = $_POST['id'] ?? '';
            $tanggal = $_POST['tanggal'];
            $keterangan = $_POST['keterangan'];
            $jenis = $_POST['jenis'];
            $nominal = $_POST['nominal'];
            $saldo = $_POST['saldo'] ?? 0;
            $kategori = $_POST['kategori'] ?? autoKategorisasi($keterangan);
            $tahun_input = $_POST['tahun_input'] ?? $tahun_aktif;

            if (empty($id)) {
                $sql = "INSERT INTO mutasi_bank (npwp, tahun, tanggal, keterangan, jenis, nominal, saldo, kategori, sumber_file, created_at) VALUES (?,?,?,?,?,?,?,?,'MANUAL',NOW())";
                $db->prepare($sql)->execute([$npwp_aktif, $tahun_input, $tanggal, $keterangan, $jenis, $nominal, $saldo, $kategori]);
            } else {
                $sql = "UPDATE mutasi_bank SET tahun=?, tanggal=?, keterangan=?, jenis=?, nominal=?, saldo=?, kategori=? WHERE id=?";
                $db->prepare($sql)->execute([$tahun_input, $tanggal, $keterangan, $jenis, $nominal, $saldo, $kategori, $id]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_input&status=success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
    }

    // 2. Simpan Bulk dari AI Parser
    if (isset($_POST['bulk_save_ai'])) {
        try {
            $data_json = json_decode($_POST['ai_data_json'], true);
            
            // Auto Delete Data Lama di Periode/Tahun yang sama sebelum Insert
            $stmtDel = $db->prepare("DELETE FROM mutasi_bank WHERE npwp = ? AND tahun = ? AND sumber_file = 'AI_PARSER'");
            $stmtDel->execute([$npwp_aktif, $tahun_aktif]);

            $stmt = $db->prepare("INSERT INTO mutasi_bank (npwp, tahun, tanggal, keterangan, jenis, nominal, saldo, kategori, sumber_file, created_at) VALUES (?,?,?,?,?,?,?,?,'AI_PARSER',NOW())");
            
            foreach ($data_json['data'] as $item) {
                // Konversi format tanggal YYYY-MM-DD
                $tgl = date('Y-m-d', strtotime(str_replace('/', '-', $item['tanggal'])));
                $kategori_final = autoKategorisasi($item['keterangan']);

                $stmt->execute([
                    $npwp_aktif, $tahun_aktif, $tgl, $item['keterangan'], 
                    strtoupper($item['jenis']), $item['nominal'], $item['saldo'], 
                    $kategori_final
                ]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Bulk Error: " . $e->getMessage() . "</div>"; }
    }

    // 3. Simpan Bulk dari Upload CSV/XLSX Manual (Centralized)
    if (isset($_POST['import_csv']) || isset($_POST['import_file'])) {
        require_once 'functions_upload.php';
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            try {
                $file = $_FILES['csv_file']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                
                $data = ($ext === 'csv') ? parseCSV($file) : parseExcel($file, $ext);
                
                if (processUploadData($db, 'bank', $npwp_aktif, $tahun_aktif, $data)) {
                    header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
                    exit;
                }
            } catch (Exception $e) { $message = "<div class='alert alert-danger'>Import Error: " . $e->getMessage() . "</div>"; }
        } else {
            $message = "<div class='alert alert-danger'>Pilih file CSV atau XLSX terlebih dahulu.</div>";
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $db->prepare("DELETE FROM mutasi_bank WHERE id = ? AND npwp = ?")->execute([$_GET['delete'], $npwp_aktif]);
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted");
        exit;
    } catch (Exception $e) { $message = "<div class='alert alert-danger'>Gagal Hapus: " . $e->getMessage() . "</div>"; }
}

// Tarik data tabel mutasi_bank
try {
    $stmt = $db->prepare("SELECT * FROM mutasi_bank WHERE npwp = ? AND tahun = ? ORDER BY tanggal DESC, id DESC");
    $stmt->execute([$npwp_aktif, $tahun_aktif]);
    $list_bank = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $list_bank = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mutasi Bank - <?= htmlspecialchars($wp['nama'] ?? $npwp_aktif) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; --accent: #f59e0b; }
        body { background-color: var(--bg); font-family: 'Inter', sans-serif; padding-bottom: 85px; }
        .main-content { margin-left: 260px; padding: 20px; transition: margin-left 0.3s; }
        body.sidebar-mini .main-content { margin-left: 75px; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.8rem; font-weight: 600; color: #475569; }
        .ai-drop-zone { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px; text-align: center; transition: 0.3s; cursor: pointer; }
        .ai-drop-zone:hover { border-color: var(--accent); background: #fffbeb; }
        .text-debit { color: #dc2626; font-weight: bold; } /* Merah */
        .text-kredit { color: #16a34a; font-weight: bold; } /* Hijau */
        .row-error { background-color: #ffe4e6 !important; } /* Pink untuk baris error saldo */
        @media (max-width: 991px) { .main-content { margin-left: 0; padding-bottom: 90px; } }
    </style>
</head>
<body class="sidebar-mini">

<?php include 'navbar.php'; ?>

<!-- WRAPPER MAIN CONTENT -->
<div class="main-content">
    <div class="container-fluid px-4 mt-3">
        
        <!-- Header -->
        <div class="d-flex align-items-center mb-4">
            <a href="profil_wp.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-sm btn-outline-secondary me-3" title="Kembali ke Profil WP">
                <i data-lucide="arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold m-0 text-primary">Manajemen Rekening Koran / Bank</h4>
                <span class="text-muted small">NPWP: <?php echo $npwp_aktif; ?> | Tahun Pajak: <?php echo $tahun_aktif; ?> | <?php echo htmlspecialchars($wp['nama']); ?></span>
            </div>
        </div>

        <?= $message ?>
        <?php if(isset($_GET['status']) && $_GET['status'] == 'bulk_success'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Import Berhasil!</strong> Data berhasil diproses.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Sukses!</strong> Data berhasil disimpan/diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif(isset($_GET['status']) && $_GET['status'] == 'deleted_all'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Berhasil!</strong> Semua mutasi bank tahun <?= $tahun_aktif ?> telah dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- LAYOUT 4/8 SPLIT -->
        <div class="row g-4">
            <!-- Panel Kiri: Aksi Massal & Form Manual -->
            <div class="col-lg-4">
                
                <!-- Aksi Massal & Filter -->
                <div class="card card-custom p-3 bg-white mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-dark">Filter Tahun Mutasi</h6>
                        <select class="form-select form-select-sm w-auto" onchange="location.href='?npwp=<?= $npwp_aktif ?>&tahun='+this.value">
                            <?php for($y=date('Y'); $y>=2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $tahun_aktif==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalCSV">
                            <i data-lucide="upload" class="inline me-1" style="width:16px;"></i> Import CSV Mutasi Bank
                        </button>
                        <button class="btn btn-outline-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAI">
                            <i data-lucide="sparkles" class="inline me-1" style="width:16px;"></i> Validasi Running Balance AI
                        </button>
                        <form method="POST" action="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="d-grid m-0" onsubmit="return confirm('Hapus seluruh data Mutasi Bank untuk tahun <?= $tahun_aktif ?>? Aksi ini tidak dapat dibatalkan.');">
                            <button type="submit" name="delete_all" class="btn btn-outline-danger btn-sm fw-bold">
                                <i data-lucide="trash-2" class="inline me-1" style="width:16px;"></i> Hapus Semua (<?= $tahun_aktif ?>)
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Form Manual Inline -->
                <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-primary" id="formTitle">Rekam Manual Mutasi</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="resetForm()" title="Reset Form">
                            <i data-lucide="refresh-cw" style="width: 14px;"></i>
                        </button>
                    </div>
                    
                    <form method="POST" action="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" id="bankForm">
                        <input type="hidden" name="id" id="f_id">
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Tahun Pajak</label>
                                <input type="number" name="tahun_input" id="tahun_input" class="form-control form-control-sm" value="<?= $tahun_aktif ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Tanggal Transaksi</label>
                                <input type="date" name="tanggal" id="f_tgl" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Keterangan Transaksi</label>
                            <textarea name="keterangan" id="f_ket" class="form-control form-control-sm" rows="2" required placeholder="Contoh: TRF DARI Bpk Budi INV-001"></textarea>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Jenis Arus Kas</label>
                                <select name="jenis" id="f_jenis" class="form-select form-select-sm">
                                    <option value="KREDIT">Kredit (Uang Masuk)</option>
                                    <option value="DEBIT">Debit (Uang Keluar)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nominal (Rp)</label>
                                <input type="number" step="0.01" name="nominal" id="f_nom" class="form-control form-control-sm" required placeholder="0">
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label">Kategori (Opsional)</label>
                                <select name="kategori" id="f_kat" class="form-select form-select-sm">
                                    <option value="">-- Auto Klasifikasi --</option>
                                    <option value="PENJUALAN">Penjualan/Penerimaan</option>
                                    <option value="PEMBELIAN">Pembelian/HPP</option>
                                    <option value="GAJI">Biaya Gaji</option>
                                    <option value="JASA">Biaya Jasa</option>
                                    <option value="OPERASIONAL">Biaya Operasional</option>
                                    <option value="PAJAK">Setoran Pajak</option>
                                    <option value="TRANSFER">Transfer Rekening</option>
                                    <option value="LAINNYA">Lain-lain</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Saldo Akhir (Rp)</label>
                                <input type="number" step="0.01" name="saldo" id="f_sld" class="form-control form-control-sm" placeholder="0">
                            </div>
                        </div>

                        <button type="submit" name="save_bank" class="btn btn-primary w-100 fw-bold">
                            <i data-lucide="save" class="inline me-1" style="width:16px;"></i> Simpan Transaksi Bank
                        </button>
                    </form>
                </div>
            </div>

            <!-- Panel Kanan: Tabel -->
            <div class="col-lg-8">
                <div class="card card-custom bg-white h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="fw-bold m-0">Rekening Koran Tersimpan (Tahun <?php echo $tahun_aktif; ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="text-start ps-3">Tgl & Keterangan</th>
                                    <th>Kategori</th>
                                    <th class="text-end">Debit (Keluar)</th>
                                    <th class="text-end">Kredit (Masuk)</th>
                                    <th class="text-end">Saldo Bank</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($list_bank)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada data mutasi bank untuk tahun ini.</td></tr>
                                <?php else: foreach($list_bank as $b): ?>
                                    <tr>
                                        <td class="text-start ps-3">
                                            <div class="fw-bold text-dark"><?= date('d/m/Y', strtotime($b['tanggal'])) ?></div>
                                            <div class="text-truncate text-muted" style="max-width: 180px; font-size: 0.75rem;" title="<?= htmlspecialchars($b['keterangan']) ?>">
                                                <?= htmlspecialchars($b['keterangan']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= $b['kategori'] ?></span>
                                        </td>
                                        <td class="text-end <?= $b['jenis']=='DEBIT'?'text-debit':'' ?>">
                                            <?= $b['jenis']=='DEBIT' ? number_format($b['nominal'], 0, ',', '.') : '-' ?>
                                        </td>
                                        <td class="text-end <?= $b['jenis']=='KREDIT'?'text-kredit':'' ?>">
                                            <?= $b['jenis']=='KREDIT' ? number_format($b['nominal'], 0, ',', '.') : '-' ?>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?= number_format($b['saldo'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick='editBank(<?= json_encode($b) ?>)'>
                                                <i data-lucide="edit-3" style="width:14px"></i>
                                            </button>
                                            <a href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Hapus transaksi ini?')">
                                                <i data-lucide="trash-2" style="width:14px"></i>
                                            </a>
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
</div>

<!-- MODAL IMPORT CSV/XLSX MANUAL & PROGRESS BAR -->
<div class="modal fade" id="modalCSV" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title fw-bold"><i data-lucide="file-spreadsheet" class="inline me-2" style="width:18px"></i> Import File Rekening Bank</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" enctype="multipart/form-data" id="formUploadCSV" onsubmit="handleUpload(event)">
                <div class="modal-body">
                    <div id="uploadUI">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pilih File CSV atau XLSX</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,.xls,.xlsx,.txt" required>
                        </div>
                        <div class="alert alert-info py-2 small mb-0">
                            <strong>Tip:</strong> Mesin akan mengelompokkan kategori secara otomatis. Format XLSX juga didukung untuk kemudahan Anda.
                            <div class="mt-2 pt-2 border-top">
                                <span class="d-block mb-1 fw-bold text-dark">Unduh Template:</span>
                                <a href="download_template.php?type=bank&format=csv" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> CSV
                                </a>
                                <a href="download_template.php?type=bank&format=xls" class="btn btn-xs btn-outline-success py-0 px-2 ms-1" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> XLS
                                </a>
                            </div>
                        </div>
                    </div>

                    <div id="progressContainer" class="text-center py-4" style="display: none;">
                        <h6 class="text-success fw-bold mb-3" id="progressStatus">Membaca & Mengkategorisasi Arus Kas...</h6>
                        <div class="progress" style="height: 12px; border-radius: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="uploadProgressBar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="mt-2 fw-bold text-muted" id="uploadProgressText">0%</div>
                    </div>
                </div>
                <div class="modal-footer" id="uploadFooter">
                    <input type="hidden" name="import_csv" value="1">
                    <button type="submit" class="btn btn-success fw-bold px-4">Unggah & Proses</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL AI UPLOAD & VALIDASI RUNNING BALANCE -->
<div class="modal fade" id="modalAI" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h6 class="modal-title fw-bold"><i data-lucide="sparkles" class="inline me-1"></i> AI Running Balance Validator</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="uploadStep">
                    <div class="ai-drop-zone" onclick="document.getElementById('aiFile').click()">
                        <i data-lucide="landmark" style="width:48px; height:48px;" class="text-muted mb-3"></i>
                        <h5>Klik atau Tarik Dokumen Mutasi Bank</h5>
                        <p class="text-muted small">Mendukung format PDF/CSV Rekening Koran. AI akan menghitung ulang Saldo (Running Balance).</p>
                        <input type="file" id="aiFile" class="d-none" accept=".pdf, .csv, image/*">
                    </div>
                </div>

                <div id="aiLoading" class="text-center py-5 scanning-loader" style="display:none;">
                    <div class="spinner-border text-warning mb-3" style="width: 3rem; height: 3rem;"></div>
                    <h5 class="fw-bold text-primary">Gemini AI sedang membaca saldo & mutasi...</h5>
                </div>

                <div id="resultStep" style="display:none;">
                    
                    <!-- Alert Status Validasi -->
                    <div id="alertValidation" class="alert alert-info py-2 small mb-3">
                        <i data-lucide="info" class="inline me-1"></i> Hasil pembacaan mutasi. Silakan periksa validitas Running Balance di bawah.
                    </div>

                    <div class="row mb-3 g-2">
                        <div class="col-md-4">
                            <div class="p-2 bg-white border rounded">
                                <small class="text-muted d-block fw-bold">Saldo Awal AI</small>
                                <span class="fs-5 fw-bold" id="txtSaldoAwal">Rp 0</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2 bg-white border rounded">
                                <small class="text-muted d-block fw-bold">Total Kredit (In)</small>
                                <span class="fs-5 fw-bold text-success" id="txtTotKredit">Rp 0</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2 bg-white border rounded">
                                <small class="text-muted d-block fw-bold">Total Debit (Out)</small>
                                <span class="fs-5 fw-bold text-danger" id="txtTotDebit">Rp 0</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 350px;">
                        <table class="table table-sm table-hover table-bordered bg-white" style="font-size: 0.8rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Keterangan</th>
                                    <th>Debit</th>
                                    <th>Kredit</th>
                                    <th>Saldo (Koran)</th>
                                    <th>Saldo (Hitungan Sistem)</th>
                                </tr>
                            </thead>
                            <tbody id="aiResultBody"></tbody>
                        </table>
                    </div>
                    <form method="POST" action="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="mt-3" onsubmit="return confirmSaveAI(event)">
                        <input type="hidden" name="ai_data_json" id="ai_data_json">
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check form-switch text-danger">
                                <input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked" checked disabled>
                                <label class="form-check-label small fw-bold" for="flexSwitchCheckChecked">Otomatis timpa/hapus data bank lama di periode ini</label>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">Batal Upload</button>
                                <button type="submit" name="bulk_save_ai" class="btn btn-primary btn-sm fw-bold" id="btnTetapSimpan">Tetap Simpan Semua Data</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();
    
    // Flag penanda apakah running balance valid
    let isBalanceValid = true; 

    function editBank(data) {
        document.getElementById('formTitle').innerText = 'Edit Transaksi Bank';
        document.getElementById('f_id').value = data.id;
        document.getElementById('tahun_input').value = data.tahun;
        document.getElementById('f_tgl').value = data.tanggal;
        document.getElementById('f_ket').value = data.keterangan;
        document.getElementById('f_jenis').value = data.jenis;
        document.getElementById('f_nom').value = data.nominal;
        document.getElementById('f_kat').value = data.kategori || '';
        document.getElementById('f_sld').value = data.saldo || 0;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formTitle').innerText = 'Rekam Manual Mutasi';
        document.getElementById('f_id').value = '';
        document.getElementById('bankForm').reset();
    }

    // Animasi Upload Progress Bar CSV
    function handleUpload(e) {
        e.preventDefault(); 
        const fileInput = document.getElementById('csv_file');
        if(!fileInput.files.length) return;

        document.getElementById('uploadUI').style.display = 'none';
        document.getElementById('uploadFooter').style.display = 'none';
        document.getElementById('progressContainer').style.display = 'block';

        let progress = 0;
        const pb = document.getElementById('uploadProgressBar');
        const pct = document.getElementById('uploadProgressText');
        const status = document.getElementById('progressStatus');

        const interval = setInterval(() => {
            progress += Math.floor(Math.random() * 15) + 5; 
            
            if (progress > 40 && progress < 80) status.innerText = "Mengklasifikasikan Transaksi Operasional & Gaji...";
            if (progress >= 80) status.innerText = "Menyimpan ke Database...";
            
            if(progress >= 100) {
                progress = 100;
                clearInterval(interval);
                status.innerText = "Selesai!";
                pb.classList.remove('progress-bar-animated');
                setTimeout(() => { document.getElementById('formUploadCSV').submit(); }, 500);
            }
            pb.style.width = progress + '%';
            pct.innerText = progress + '%';
        }, 200);
    }

    // Peringatan Konfirmasi AI
    function confirmSaveAI(e) {
        if (!isBalanceValid) {
            return confirm("WARNING: Saldo Running Balance tidak cocok dengan file Koran! Apakah Anda yakin ingin TETAP MENYIMPAN data cacat ini?");
        }
        return true;
    }

    // AI Scanner Format Saldo & Ekstraksi JSON
    const aiFile = document.getElementById('aiFile');
    if (aiFile) {
        aiFile.onchange = async function(e) {
            const file = e.target.files[0];
            if(!file) return;

            document.getElementById('uploadStep').style.display = 'none';
            document.getElementById('aiLoading').style.display = 'block';

            const reader = new FileReader();
            reader.onload = async function() {
                const fileContent = reader.result;
                
                // Prompt Ketat untuk Gemini mengatur metadata Saldo dan Array
                const prompt = `Anda adalah sistem Bank Statement Parser. Ekstrak data mutasi bank dari dokumen/teks berikut:
                ${fileContent.substring(0, 5000)}
                
                Tugas:
                Outputkan HANYA JSON object dengan format persis seperti ini:
                {
                  "metadata": {
                    "saldo_awal": (angka dari teks saldo awal/sebelum mutasi pertama, default 0),
                    "saldo_akhir": (angka dari teks saldo akhir)
                  },
                  "data": [
                    { "tanggal": "YYYY-MM-DD", "keterangan": "Uraian...", "jenis": "DEBIT atau KREDIT", "nominal": 15000, "saldo": 200000 }
                  ]
                }
                Pastikan nominal dan saldo murni angka (tanpa Rp, tanpa titik/koma ribuan).`;

                try {
                    const apiKey = ""; 
                    const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
                    });
                    
                    const result = await response.json();
                    let jsonText = result.candidates[0].content.parts[0].text;
                    jsonText = jsonText.replace(/```json/g, '').replace(/```/g, '');
                    const parsedData = JSON.parse(jsonText);

                    // LOGIKA VALIDASI RUNNING BALANCE DI JAVASCRIPT
                    const tbody = document.getElementById('aiResultBody');
                    tbody.innerHTML = '';
                    
                    let runningSaldo = Number(parsedData.metadata.saldo_awal) || 0;
                    let totKredit = 0, totDebit = 0;
                    isBalanceValid = true;
                    let numErrors = 0;

                    parsedData.data.forEach(item => {
                        let nominal = Number(item.nominal) || 0;
                        let saldoKoran = Number(item.saldo) || 0;
                        
                        // Hitung Running Balance
                        if (item.jenis.toUpperCase() === 'KREDIT') {
                            runningSaldo += nominal;
                            totKredit += nominal;
                        } else {
                            runningSaldo -= nominal;
                            totDebit += nominal;
                        }

                        // Deteksi Selisih
                        let selisih = Math.abs(runningSaldo - saldoKoran);
                        let isRowError = selisih > 10; // Margin eror pembulatan kecil
                        
                        if (isRowError) {
                            isBalanceValid = false;
                            numErrors++;
                        }

                        // Render Tabel Preview
                        tbody.innerHTML += `
                            <tr class="${isRowError ? 'row-error' : ''}">
                                <td>${item.tanggal}</td>
                                <td>${item.keterangan}</td>
                                <td class="text-end text-debit">${item.jenis.toUpperCase() === 'DEBIT' ? nominal.toLocaleString('id-ID') : '-'}</td>
                                <td class="text-end text-kredit">${item.jenis.toUpperCase() === 'KREDIT' ? nominal.toLocaleString('id-ID') : '-'}</td>
                                <td class="text-end">${saldoKoran.toLocaleString('id-ID')}</td>
                                <td class="text-end fw-bold ${isRowError ? 'text-danger' : 'text-success'}">${runningSaldo.toLocaleString('id-ID')}</td>
                            </tr>
                        `;
                    });

                    // Update UI Info
                    document.getElementById('txtSaldoAwal').innerText = `Rp ${(Number(parsedData.metadata.saldo_awal)||0).toLocaleString('id-ID')}`;
                    document.getElementById('txtTotKredit').innerText = `Rp ${totKredit.toLocaleString('id-ID')}`;
                    document.getElementById('txtTotDebit').innerText = `Rp ${totDebit.toLocaleString('id-ID')}`;

                    const alertDiv = document.getElementById('alertValidation');
                    if (isBalanceValid) {
                        alertDiv.className = "alert alert-success py-2 small mb-3";
                        alertDiv.innerHTML = `<i data-lucide="check-circle" class="inline me-1"></i> Running Balance Cocok (Valid). Aman untuk disimpan.`;
                        document.getElementById('btnTetapSimpan').innerText = "Simpan Data Bank";
                        document.getElementById('btnTetapSimpan').className = "btn btn-success btn-sm fw-bold";
                    } else {
                        alertDiv.className = "alert alert-danger py-2 small mb-3";
                        alertDiv.innerHTML = `<i data-lucide="alert-triangle" class="inline me-1"></i> <strong>Peringatan!</strong> Terdapat ${numErrors} baris selisih saldo antara file koran dengan hitungan matematis sistem.`;
                        document.getElementById('btnTetapSimpan').innerText = "Abaikan & Tetap Simpan";
                        document.getElementById('btnTetapSimpan').className = "btn btn-danger btn-sm fw-bold";
                    }

                    document.getElementById('ai_data_json').value = JSON.stringify(parsedData);
                    document.getElementById('aiLoading').style.display = 'none';
                    document.getElementById('resultStep').style.display = 'block';
                    lucide.createIcons();
                } catch (err) {
                    alert("Gagal membaca struktur file Bank. Pastikan file jelas dan menggunakan format yang benar.");
                    location.reload();
                }
            };
            reader.readAsText(file);
        };
    }
</script>
</body>
</html>