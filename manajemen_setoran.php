<?php
ob_start();
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
$current_file = basename($_SERVER['PHP_SELF']);

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

/**
 * Logika Validasi Otomatis Setoran
 */
function validasiSetoran($jenis_pajak, $map, $kjs) {
    $allowed_jenis = ['PPh_25(OP)', 'PPh_25(BADAN)'];
    $allowed_map = ['411125', '411126'];
    
    if (in_array($jenis_pajak, $allowed_jenis) && in_array($map, $allowed_map) && $kjs == '100') {
        return 'ya';
    }
    return 'tidak';
}

// Handler Operasi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Simpan/Update Manual
    if (isset($_POST['save_setoran'])) {
        try {
            $id = $_POST['id'] ?? '';
            $jenis_p = $_POST['jenis_pajak'];
            $jenis_s = $_POST['jenis_setoran'];
            $map = $_POST['map'];
            $kjs = $_POST['kjs'];
            $nilai = $_POST['nilai_setoran'];
            $tgl = $_POST['tgl_setor'];
            $ntpn = $_POST['ntpn'];
            $tahun_input = $_POST['tahun_input'] ?? $tahun_aktif;
            
            $dikreditkan = validasiSetoran($jenis_p, $map, $kjs);

            if (empty($id)) {
                $sql = "INSERT INTO setoran_pajak (npwp, tahun, jenis_pajak, jenis_setoran, map, kjs, nilai_setoran, tgl_setor, ntpn, dikreditkan) VALUES (?,?,?,?,?,?,?,?,?,?)";
                $db->prepare($sql)->execute([$npwp_aktif, $tahun_input, $jenis_p, $jenis_s, $map, $kjs, $nilai, $tgl, $ntpn, $dikreditkan]);
                catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Setoran Pajak', "Menambah setoran manual NTPN: $ntpn");
            } else {
                $sql = "UPDATE setoran_pajak SET tahun=?, jenis_pajak=?, jenis_setoran=?, map=?, kjs=?, nilai_setoran=?, tgl_setor=?, ntpn=?, dikreditkan=? WHERE id=?";
                $db->prepare($sql)->execute([$tahun_input, $jenis_p, $jenis_s, $map, $kjs, $nilai, $tgl, $ntpn, $dikreditkan, $id]);
                catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Setoran Pajak', "Update data setoran ID: $id");
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_input&status=success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Gagal Simpan: " . $e->getMessage() . "</div>"; }
    }

    // 2. Bulk Save dari AI Scanner
    if (isset($_POST['bulk_save_ai'])) {
        try {
            $data_json = json_decode($_POST['ai_data_json'], true);
            $stmt = $db->prepare("INSERT INTO setoran_pajak (npwp, tahun, jenis_pajak, jenis_setoran, map, kjs, nilai_setoran, tgl_setor, ntpn, dikreditkan) VALUES (?,?,?,?,?,?,?,?,?,?)");
            
            foreach ($data_json as $item) {
                $dikreditkan = validasiSetoran($item['jenis_pajak'], $item['map'], $item['kjs']);
                $stmt->execute([
                    $npwp_aktif, $tahun_aktif, $item['jenis_pajak'], $item['jenis_setoran'], 
                    $item['map'], $item['kjs'], $item['nilai_setoran'], $item['tgl_setor'], 
                    $item['ntpn'], $dikreditkan
                ]);
            }
            catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Setoran AI', "Bulk Import via AI " . count($data_json) . " data");
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>AI Import Error: " . $e->getMessage() . "</div>"; }
    }

    // 3. Import CSV/XLSX Bulk (Centralized)
    if (isset($_POST['import_csv'])) {
        require_once 'functions_upload.php';
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            try {
                $file = $_FILES['csv_file']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                
                $data = ($ext === 'csv') ? parseCSV($file) : parseExcel($file, $ext);
                
                if (processUploadData($db, 'setoran', $npwp_aktif, $tahun_aktif, $data)) {
                    header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
                    exit;
                }
            } catch (Exception $e) { $message = "<div class='alert alert-danger'>Import Error: " . $e->getMessage() . "</div>"; }
        }
    }
}

// Hapus Data Tunggal
if (isset($_GET['delete'])) {
    try {
        $db->prepare("DELETE FROM setoran_pajak WHERE id = ? AND npwp = ?")->execute([$_GET['delete'], $npwp_aktif]);
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted");
        exit;
    } catch (Exception $e) { $message = "<div class='alert alert-danger'>Gagal Hapus: " . $e->getMessage() . "</div>"; }
}

// Hapus Semua Data
if (isset($_POST['delete_all'])) {
    try {
        $stmt = $db->prepare("DELETE FROM setoran_pajak WHERE npwp = ? AND tahun = ?");
        $stmt->execute([$npwp_aktif, $tahun_aktif]);
        catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Setoran Pajak', "Menghapus SEMUA data setoran NPWP: $npwp_aktif Tahun: $tahun_aktif");
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=all_deleted");
        exit;
    } catch (Exception $e) { $message = "<div class='alert alert-danger'>Gagal Hapus Semua: " . $e->getMessage() . "</div>"; }
}

// Ambil Daftar Data
try {
    $stmt = $db->prepare("SELECT * FROM setoran_pajak WHERE npwp = ? AND tahun = ? ORDER BY tgl_setor DESC");
    $stmt->execute([$npwp_aktif, $tahun_aktif]);
    $list_setoran = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $list_setoran = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setoran Manager - <?= htmlspecialchars($wp['nama'] ?? $npwp_aktif) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; --accent: #f59e0b; }
        body { background-color: var(--bg); font-family: 'Inter', sans-serif; padding-bottom: 85px; }
        .main-content { margin-left: 260px; padding: 20px; transition: margin-left 0.3s; }
        body.sidebar-mini .main-content { margin-left: 75px; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.8rem; font-weight: 600; color: #475569; }
        .badge-credit { font-size: 0.65rem; padding: 4px 8px; border-radius: 4px; }
        .ai-drop-zone { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px; text-align: center; transition: 0.3s; cursor: pointer; }
        .ai-drop-zone:hover { border-color: var(--accent); background: #fffbeb; }
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
                <h4 class="fw-bold m-0 text-primary">Manajemen Setoran Pajak</h4>
                <span class="text-muted small">NPWP: <?= $npwp_aktif; ?> | Tahun: <?= $tahun_aktif; ?> | <?= htmlspecialchars($wp['nama']); ?></span>
            </div>
        </div>

        <?= $message ?>
        <?php if(isset($_GET['status'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Aksi Berhasil!</strong> Data setoran telah diperbarui.
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
                            <i data-lucide="upload" class="inline me-1" style="width:16px;"></i> Import CSV/XLSX
                        </button>
                        <button class="btn btn-outline-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAI">
                            <i data-lucide="sparkles" class="inline me-1" style="width:16px;"></i> Scan via AI
                        </button>
                        <button class="btn btn-outline-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalDeleteAll">
                            <i data-lucide="trash-2" class="inline me-1" style="width:16px;"></i> Hapus Semua Data
                        </button>
                    </div>
                </div>

                <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-primary" id="formTitle">Rekam Setoran</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="resetForm()">
                            <i data-lucide="refresh-cw" style="width: 14px;"></i>
                        </button>
                    </div>
                    
                    <form method="POST" id="setoranForm">
                        <input type="hidden" name="id" id="f_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Tahun Pajak</label>
                            <input type="number" name="tahun_input" id="f_tahun" class="form-control form-control-sm" value="<?= $tahun_aktif ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nomor NTPN / PBK</label>
                            <input type="text" name="ntpn" id="f_ntpn" class="form-control form-control-sm" required placeholder="16 Digit NTPN">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Tanggal Setor</label>
                                <input type="date" name="tgl_setor" id="f_tgl" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Jenis Pajak</label>
                                <select name="jenis_pajak" id="f_jenis_p" class="form-select form-select-sm">
                                    <option value="PPh_25(BADAN)">PPh 25 Badan</option>
                                    <option value="PPh_25(OP)">PPh 25 OP</option>
                                    <option value="PPh_29">PPh 29</option>
                                    <option value="PPh_21">PPh 21</option>
                                    <option value="PPh_22">PPh 22</option>
                                    <option value="PPh_23">PPh 23</option>
                                    <option value="PPh_Final">PPh Final</option>
                                    <option value="PPh_15">PPh 15</option>
                                    <option value="PPN">PPN</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Jenis Setoran</label>
                                <select name="jenis_setoran" id="f_jenis_s" class="form-select form-select-sm">
                                    <option value="SSP">SSP</option>
                                    <option value="PBK">PBK</option>
                                    <option value="STP">STP</option>
                                    <option value="SKP">SKP</option>
                                    <option value="DEPOSIT">DEPOSIT</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nilai Setoran (Rp)</label>
                                <input type="number" step="0.01" name="nilai_setoran" id="f_nilai" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label">MAP</label>
                                <input type="text" name="map" id="f_map" class="form-control form-control-sm" placeholder="411126" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">KJS</label>
                                <input type="text" name="kjs" id="f_kjs" class="form-control form-control-sm" placeholder="100" required>
                            </div>
                        </div>

                        <button type="submit" name="save_setoran" class="btn btn-primary w-100 fw-bold">Simpan Setoran</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card card-custom bg-white h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="fw-bold m-0">Daftar Setoran Pajak (<?= $tahun_aktif; ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 0.85rem;">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="text-start ps-3">NTPN & Jenis</th>
                                    <th>MAP / KJS</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Nilai (Rp)</th>
                                    <th>Kredit?</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($list_setoran)): ?>
                                    <tr><td colspan="6" class="text-muted py-5">Belum ada data setoran.</td></tr>
                                <?php else: foreach($list_setoran as $s): ?>
                                    <tr>
                                        <td class="text-start ps-3">
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($s['ntpn']) ?></div>
                                            <small class="text-muted"><?= $s['jenis_pajak'] ?> (<?= $s['jenis_setoran'] ?>)</small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= $s['map'] ?> - <?= $s['kjs'] ?></span></td>
                                        <td><?= date('d/m/Y', strtotime($s['tgl_setor'])) ?></td>
                                        <td class="text-end fw-bold text-success"><?= number_format($s['nilai_setoran'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="badge-credit <?= $s['dikreditkan']=='ya'?'bg-success text-white':'bg-secondary' ?>">
                                                <?= strtoupper($s['dikreditkan']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick='editSetoran(<?= json_encode($s) ?>)'>
                                                <i data-lucide="edit-3" style="width:14px"></i>
                                            </button>
                                            <a href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&delete=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Hapus setoran ini?')">
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
                <p>Hapus seluruh setoran tahun <?= $tahun_aktif ?>? Aksi ini permanen.</p>
            </div>
            <div class="modal-footer d-flex justify-content-center">
                <form method="POST">
                    <button type="submit" name="delete_all" class="btn btn-danger btn-sm fw-bold">Ya, Hapus Semua</button>
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
                <h6 class="modal-title fw-bold"><i data-lucide="file-spreadsheet" class="inline me-2" style="width:18px"></i> Import File Setoran</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formUploadCSV" onsubmit="handleUpload(event)">
                <div class="modal-body">
                    <div id="uploadUI">
                        <div class="mb-3">
                            <label class="form-label">Pilih File CSV atau XLSX</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv, .xlsx, .xls, .txt" required>
                        </div>
                        <div class="alert alert-info py-2 small mb-0">
                            <strong>Tip:</strong> Pastikan header kolom sesuai (ntpn, jenis_pajak, map, kjs, tgl_setor, nilai_setoran).
                            <div class="mt-2 pt-2 border-top">
                                <span class="d-block mb-1 fw-bold text-dark">Unduh Template:</span>
                                <a href="download_template.php?type=setoran&format=csv" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> CSV
                                </a>
                                <a href="download_template.php?type=setoran&format=xls" class="btn btn-xs btn-outline-success py-0 px-2 ms-1" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> XLS
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="progressContainer" class="text-center py-4" style="display: none;">
                        <h6 class="text-success fw-bold mb-3" id="progressStatus">Memproses Data Setoran...</h6>
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

<!-- Modal AI Scanner -->
<div class="modal fade" id="modalAI" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h6 class="modal-title fw-bold"><i data-lucide="sparkles" class="inline me-1"></i> AI Setoran Scanner</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light text-center py-5">
                <div class="ai-drop-zone" onclick="document.getElementById('aiFile').click()">
                    <i data-lucide="upload-cloud" style="width:48px; height:48px;" class="text-muted mb-3"></i>
                    <h5>Pilih Berkas SSP / Bukti Setor</h5>
                    <p class="text-muted small">AI akan membaca NTPN, MAP, KJS, dan Nominal secara otomatis.</p>
                    <input type="file" id="aiFile" class="d-none" accept=".pdf, image/*">
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function editSetoran(data) {
        document.getElementById('formTitle').innerText = 'Edit Setoran';
        document.getElementById('f_id').value = data.id;
        document.getElementById('f_tahun').value = data.tahun;
        document.getElementById('f_ntpn').value = data.ntpn;
        document.getElementById('f_tgl').value = data.tgl_setor;
        document.getElementById('f_jenis_p').value = data.jenis_pajak;
        document.getElementById('f_jenis_s').value = data.jenis_setoran;
        document.getElementById('f_nilai').value = data.nilai_setoran;
        document.getElementById('f_map').value = data.map;
        document.getElementById('f_kjs').value = data.kjs;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formTitle').innerText = 'Rekam Setoran';
        document.getElementById('f_id').value = '';
        document.getElementById('setoranForm').reset();
    }

    function handleUpload(e) {
        e.preventDefault(); 
        document.getElementById('uploadUI').style.display = 'none';
        document.getElementById('uploadFooter').style.display = 'none';
        document.getElementById('progressContainer').style.display = 'block';

        let progress = 0;
        const pb = document.getElementById('uploadProgressBar');
        const pct = document.getElementById('uploadProgressText');
        
        const interval = setInterval(() => {
            progress += 10;
            if(progress >= 100) {
                clearInterval(interval);
                document.getElementById('formUploadCSV').submit();
            }
            pb.style.width = progress + '%';
            pct.innerText = progress + '%';
        }, 100);
    }
</script>
</body>
</html>