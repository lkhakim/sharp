<?php
ob_start();
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


$message = "";

$npwp_aktif = $_GET['npwp'] ?? '';
$tahun_aktif = $_GET['tahun'] ?? date('Y');
$current_file = basename($_SERVER['PHP_SELF']);

if (empty($npwp_aktif)) {
    header("Location: manajemen_wp.php");
    exit;
}

try {
    $stmtWp = $db->prepare("SELECT nama FROM profil_wp WHERE npwp = ?");
    $stmtWp->execute([$npwp_aktif]);
    $wp = $stmtWp->fetch();
} catch (Exception $e) {
    $wp = ['nama' => 'WP Tidak Ditemukan'];
}

if (isset($_POST['delete_all'])) {
    try {
        $stmt = $db->prepare("DELETE FROM faktur_pajak WHERE npwp = ? AND tahun = ?");
        $stmt->execute([$npwp_aktif, $tahun_aktif]);
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted_all");
        exit;
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menghapus: " . $e->getMessage() . "</div>";
    }
}

if (isset($_POST['delete_single'])) {
    try {
        $stmt = $db->prepare("DELETE FROM faktur_pajak WHERE id = ? AND npwp = ?");
        $stmt->execute([$_POST['delete_id'], $npwp_aktif]);
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted");
        exit;
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menghapus: " . $e->getMessage() . "</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Simpan Manual Form
    if (isset($_POST['save_faktur'])) {
        try {
            $id = $_POST['id'] ?? '';
            $jenis_faktur = $_POST['jenis_faktur'];
            $no_faktur = $_POST['no_faktur'];
            $tgl_faktur = $_POST['tgl_faktur'];
            $status = $_POST['status'] ?? 'approved';
            $masa_pajak = $_POST['masa_pajak'];
            $npwp_lawan = $_POST['npwp_lawan'];
            $nama_lawan = $_POST['nama_lawan'];
            $dilaporkan_spt = $_POST['dilaporkan_spt'] ?? 'ya';
            $masa_kredit = $_POST['masa_kredit'];
            $dikreditkan = $_POST['dikreditkan'] ?? 'ya';
            $dpp = $_POST['dpp'];
            $ppn = $_POST['ppn'];
            $ppnbm = $_POST['ppnbm'] ?? 0;
            $tahun_input = $_POST['tahun_input'] ?? $tahun_aktif;

            if (empty($id)) {
                $sql = "INSERT INTO faktur_pajak (npwp, tahun, jenis_faktur, no_faktur, tgl_faktur, status, masa_pajak, npwp_lawan, nama_lawan, dilaporkan_spt, masa_kredit, dikreditkan, dpp, ppn, ppnbm, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
                $db->prepare($sql)->execute([$npwp_aktif, $tahun_input, $jenis_faktur, $no_faktur, $tgl_faktur, $status, $masa_pajak, $npwp_lawan, $nama_lawan, $dilaporkan_spt, $masa_kredit, $dikreditkan, $dpp, $ppn, $ppnbm]);
            } else {
                $sql = "UPDATE faktur_pajak SET tahun=?, jenis_faktur=?, no_faktur=?, tgl_faktur=?, status=?, masa_pajak=?, npwp_lawan=?, nama_lawan=?, dilaporkan_spt=?, masa_kredit=?, dikreditkan=?, dpp=?, ppn=?, ppnbm=? WHERE id=?";
                $db->prepare($sql)->execute([$tahun_input, $jenis_faktur, $no_faktur, $tgl_faktur, $status, $masa_pajak, $npwp_lawan, $nama_lawan, $dilaporkan_spt, $masa_kredit, $dikreditkan, $dpp, $ppn, $ppnbm, $id]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_input&status=success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
    }

    // Import CSV/XLSX Bulk (Centralized)
    if (isset($_POST['import_csv'])) {
        require_once 'functions_upload.php';
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            try {
                $file = $_FILES['csv_file']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                
                $data = ($ext === 'csv') ? parseCSV($file) : parseExcel($file, $ext);
                
                if (processUploadData($db, 'faktur', $npwp_aktif, $tahun_aktif, $data)) {
                    header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
                    exit;
                }
            } catch (Exception $e) { $message = "<div class='alert alert-danger'>Import Error: " . $e->getMessage() . "</div>"; }
        }
    }
}

try {
    $stmt = $db->prepare("SELECT * FROM faktur_pajak WHERE npwp = ? AND tahun = ? ORDER BY tgl_faktur DESC, id DESC");
    $stmt->execute([$npwp_aktif, $tahun_aktif]);
    $list_faktur = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $list_faktur = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Faktur - <?= htmlspecialchars($wp['nama'] ?? $npwp_aktif) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; --accent: #f59e0b; }
        body { background-color: var(--bg); font-family: 'Inter', sans-serif; padding-bottom: 85px; }
        .main-content { margin-left: 260px; padding: 20px; transition: margin-left 0.3s; }
        body.sidebar-mini .main-content { margin-left: 75px; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.8rem; font-weight: 600; color: #475569; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding-bottom: 90px; } }
    </style>
</head>
<body class="sidebar-mini">

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid px-4 mt-3">
        
        <div class="d-flex align-items-center mb-4">
            <a href="profil_wp.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-sm btn-outline-secondary me-3" title="Kembali">
                <i data-lucide="arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold m-0 text-primary">Manajemen e-Faktur</h4>
                <span class="text-muted small">NPWP: <?= $npwp_aktif; ?> | Tahun: <?= $tahun_aktif; ?> | <?= htmlspecialchars($wp['nama']); ?></span>
            </div>
        </div>

        <?= $message ?>
        <?php if(isset($_GET['status'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Aksi Berhasil!</strong> Data faktur telah diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card card-custom p-3 bg-white mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-dark">Filter & Aksi</h6>
                        <select class="form-select form-select-sm w-auto" onchange="location.href='?npwp=<?= $npwp_aktif ?>&tahun='+this.value">
                            <?php for($y=date('Y'); $y>=2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $tahun_aktif==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalCSV">
                            <i data-lucide="upload" class="inline me-1" style="width:16px;"></i> Import CSV e-Faktur
                        </button>
                        <button class="btn btn-outline-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalDeleteAll">
                            <i data-lucide="trash-2" class="inline me-1" style="width:16px;"></i> Hapus Semua Data
                        </button>
                    </div>
                </div>

                <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-primary" id="formTitle">Rekam Faktur</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="resetForm()">
                            <i data-lucide="refresh-cw" style="width: 14px;"></i>
                        </button>
                    </div>
                    
                    <form method="POST" id="fakturForm">
                        <input type="hidden" name="id" id="f_id">
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Jenis Faktur</label>
                                <select name="jenis_faktur" id="f_jenis" class="form-select form-select-sm">
                                    <option value="KELUARAN">KELUARAN (Jual)</option>
                                    <option value="MASUKAN">MASUKAN (Beli)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Tgl Faktur</label>
                                <input type="date" name="tgl_faktur" id="f_tgl" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">No Faktur (16 Digit)</label>
                            <input type="text" name="no_faktur" id="f_no" class="form-control form-control-sm" required placeholder="010.000-xx.xxxxxxx">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">NPWP Lawan</label>
                                <input type="text" name="npwp_lawan" id="f_npwp" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nama Lawan</label>
                                <input type="text" name="nama_lawan" id="f_nama" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="form-label">Masa Pajak</label>
                                <input type="number" name="masa_pajak" id="f_masa" class="form-control form-control-sm" required min="1" max="12">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Masa Kredit</label>
                                <input type="number" name="masa_kredit" id="f_kredit" class="form-control form-control-sm" min="1" max="12">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Dikreditkan?</label>
                                <select name="dikreditkan" id="f_dikreditkan" class="form-select form-select-sm">
                                    <option value="ya">Ya</option>
                                    <option value="tidak">Tidak</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label">DPP (Rp)</label>
                                <input type="number" step="0.01" name="dpp" id="f_dpp" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">PPN (Rp)</label>
                                <input type="number" step="0.01" name="ppn" id="f_ppn" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <button type="submit" name="save_faktur" class="btn btn-primary w-100 fw-bold">Simpan Faktur</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card card-custom bg-white h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="fw-bold m-0">Daftar Faktur Pajak (<?= $tahun_aktif; ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 0.85rem;">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="text-start ps-3">No. Faktur & Lawan</th>
                                    <th>Jenis / Masa</th>
                                    <th class="text-end">DPP (Rp)</th>
                                    <th class="text-end">PPN (Rp)</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($list_faktur)): ?>
                                    <tr><td colspan="6" class="text-muted py-5">Belum ada data faktur.</td></tr>
                                <?php else: foreach($list_faktur as $f): ?>
                                    <tr>
                                        <td class="text-start ps-3">
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($f['no_faktur']) ?></div>
                                            <div class="text-truncate text-muted" style="max-width: 150px; font-size: 0.75rem;">
                                                <?= htmlspecialchars($f['nama_lawan']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $f['jenis_faktur']=='MASUKAN'?'bg-success':'bg-danger' ?>"><?= $f['jenis_faktur'] ?></span><br>
                                            <small class="text-muted">Masa: <?= $f['masa_pajak'] ?></small>
                                        </td>
                                        <td class="text-end"><?= number_format($f['dpp'], 0, ',', '.') ?></td>
                                        <td class="text-end fw-bold"><?= number_format($f['ppn'], 0, ',', '.') ?></td>
                                        <td>
                                            <?php if($f['status'] === 'approved'): ?>
                                                <span class="badge bg-primary">Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= $f['status'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick='editFaktur(<?= json_encode($f) ?>)'>
                                                <i data-lucide="edit-3" style="width:14px"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="showDeleteModal(<?= $f['id'] ?>)">
                                                <i data-lucide="trash-2" style="width:14px"></i>
                                            </button>
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

<!-- Modal Hapus All -->
<div class="modal fade" id="modalDeleteAll" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold">Konfirmasi Hapus</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i data-lucide="alert-triangle" class="text-danger mb-3" style="width: 48px; height: 48px;"></i>
                <p>Hapus seluruh faktur tahun <?= $tahun_aktif ?>? Aksi ini permanen.</p>
            </div>
            <div class="modal-footer d-flex justify-content-center">
                <form method="POST">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_all" class="btn btn-danger btn-sm fw-bold">Ya, Hapus Semua</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hapus Single -->
<div class="modal fade" id="modalDeleteSingle" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold">Hapus Faktur</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Yakin ingin menghapus faktur ini?</p>
            </div>
            <div class="modal-footer d-flex justify-content-center">
                <form method="POST">
                    <input type="hidden" name="delete_id" id="delete_single_id">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_single" class="btn btn-danger btn-sm fw-bold">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal CSV/XLSX -->
<div class="modal fade" id="modalCSV" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title fw-bold"><i data-lucide="file-spreadsheet" class="inline me-2" style="width:18px"></i> Import File e-Faktur</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formUploadCSV" onsubmit="handleUpload(event)">
                <div class="modal-body">
                    <div id="uploadUI">
                        <div class="mb-3">
                            <label class="form-label">Pilih File CSV atau XLSX</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,.xls,.xlsx,.txt" required>
                        </div>
                        <div class="alert alert-info py-2 small mb-0">
                            <strong>Tip:</strong> Mendukung format ekspor e-Faktur dalam bentuk CSV maupun XLSX.
                            <div class="mt-2 pt-2 border-top">
                                <span class="d-block mb-1 fw-bold text-dark">Unduh Template:</span>
                                <a href="download_template.php?type=faktur&format=csv" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> CSV
                                </a>
                                <a href="download_template.php?type=faktur&format=xls" class="btn btn-xs btn-outline-success py-0 px-2 ms-1" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> XLS
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="progressContainer" class="text-center py-4" style="display: none;">
                        <h6 class="text-success fw-bold mb-3" id="progressStatus">Membaca Data Faktur...</h6>
                        <div class="progress" style="height: 12px; border-radius: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="uploadProgressBar" style="width: 0%"></div>
                        </div>
                        <div class="mt-2 fw-bold text-muted" id="uploadProgressText">0%</div>
                    </div>
                </div>
                <div class="modal-footer" id="uploadFooter">
                    <input type="hidden" name="import_csv" value="1">
                    <button type="submit" class="btn btn-success fw-bold px-4">Unggah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function editFaktur(data) {
        document.getElementById('formTitle').innerText = 'Edit Faktur';
        document.getElementById('f_id').value = data.id;
        document.getElementById('f_jenis').value = data.jenis_faktur;
        document.getElementById('f_no').value = data.no_faktur;
        document.getElementById('f_tgl').value = data.tgl_faktur;
        document.getElementById('f_npwp').value = data.npwp_lawan;
        document.getElementById('f_nama').value = data.nama_lawan;
        document.getElementById('f_masa').value = data.masa_pajak;
        document.getElementById('f_kredit').value = data.masa_kredit;
        document.getElementById('f_dikreditkan').value = data.dikreditkan;
        document.getElementById('f_dpp').value = data.dpp;
        document.getElementById('f_ppn').value = data.ppn;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formTitle').innerText = 'Rekam Faktur';
        document.getElementById('f_id').value = '';
        document.getElementById('fakturForm').reset();
    }

    function showDeleteModal(id) {
        document.getElementById('delete_single_id').value = id;
        new bootstrap.Modal(document.getElementById('modalDeleteSingle')).show();
    }

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
            progress += Math.floor(Math.random() * 20) + 5; 
            if (progress > 50 && progress < 80) status.innerText = "Mengekstrak Masa dan DPP...";
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
</script>
</body>
</html>