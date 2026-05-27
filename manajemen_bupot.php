<?php
ob_start(); // Mencegah error "Headers Already Sent" penyebab layar blank
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

//$db = getConnection();
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

// Handler Hapus Semua (Delete All)
if (isset($_POST['delete_all'])) {
    try {
        $stmt = $db->prepare("DELETE FROM bukti_potong WHERE npwp = ? AND tahun = ?");
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
    if (isset($_POST['save_bupot'])) {
        try {
            $id = $_POST['id'] ?? '';
            $kode_objek = $_POST['kode_objek_pajak'];
            $no_bupot = $_POST['no_bupot'];
            $jenis_penerbitan = $_POST['jenis_penerbitan'];
            $npwp_lawan = $_POST['npwp_lawan'];
            $nama_lawan = $_POST['nama_lawan'];
            $dpp = $_POST['dpp_bupot'];
            $pph = $_POST['nilai_pph'];
            $fasilitas = $_POST['fasilitas'] ?? 'Tidak';
            $dilapor = $_POST['dilapor_spt'] ?? 'tidak';
            $tahun_input = $_POST['tahun_input'] ?? $tahun_aktif;

            // Validasi Otomatis
            $prefix = substr($kode_objek, 0, 2);
            $jenis_pajak = 'PPh_Final';
            if ($prefix == '21') $jenis_pajak = 'PPh_21';
            elseif ($prefix == '22') $jenis_pajak = 'PPh_22';
            elseif ($prefix == '24' || $prefix == '23') $jenis_pajak = 'PPh_23';
            elseif ($prefix == '28' || $prefix == '4-') $jenis_pajak = 'PPh_Final';

            // Logika pengkreditan
            $dikreditkan = (in_array($jenis_pajak, ['PPh_21', 'PPh_22', 'PPh_23']) && ($npwp_lawan !== $npwp_aktif)) ? 'ya' : 'tidak';
            $sifat_bupot = $_POST['sifat_bupot'] ?? 'Tidak Final';
            if ($jenis_pajak == 'PPh_Final') $sifat_bupot = 'Final';

            if (empty($id)) {
                $sql = "INSERT INTO bukti_potong (npwp, tahun, kode_objek_pajak, no_bupot, jenis_penerbitan, npwp_lawan, nama_lawan, dpp_bupot, nilai_pph, jenis_pajak, sifat_bupot, fasilitas, dilapor_spt, dikreditkan, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
                $db->prepare($sql)->execute([$npwp_aktif, $tahun_input, $kode_objek, $no_bupot, $jenis_penerbitan, $npwp_lawan, $nama_lawan, $dpp, $pph, $jenis_pajak, $sifat_bupot, $fasilitas, $dilapor, $dikreditkan]);
            } else {
                $sql = "UPDATE bukti_potong SET tahun=?, kode_objek_pajak=?, no_bupot=?, jenis_penerbitan=?, npwp_lawan=?, nama_lawan=?, dpp_bupot=?, nilai_pph=?, jenis_pajak=?, sifat_bupot=?, fasilitas=?, dilapor_spt=?, dikreditkan=? WHERE id=?";
                $db->prepare($sql)->execute([$tahun_input, $kode_objek, $no_bupot, $jenis_penerbitan, $npwp_lawan, $nama_lawan, $dpp, $pph, $jenis_pajak, $sifat_bupot, $fasilitas, $dilapor, $dikreditkan, $id]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_input&status=success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
    }

    // 2. Simpan Bulk dari AI Parser
    if (isset($_POST['bulk_save_ai'])) {
        try {
            $data_json = json_decode($_POST['ai_data_json'], true);
            $stmt = $db->prepare("INSERT INTO bukti_potong (npwp, tahun, kode_objek_pajak, no_bupot, jenis_penerbitan, npwp_lawan, nama_lawan, dpp_bupot, nilai_pph, jenis_pajak, sifat_bupot, dikreditkan, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            
            foreach ($data_json as $item) {
                $prefix = substr($item['kode_objek'], 0, 2);
                $jp = 'PPh_Final';
                if ($prefix == '21') $jp = 'PPh_21';
                elseif ($prefix == '22') $jp = 'PPh_22';
                elseif ($prefix == '24' || $prefix == '23') $jp = 'PPh_23';
                
                $kredit = (in_array($jp, ['PPh_21', 'PPh_22', 'PPh_23']) && ($item['npwp_lawan'] !== $npwp_aktif)) ? 'ya' : 'tidak';
                $sifat = ($jp == 'PPh_Final') ? 'Final' : 'Tidak Final';

                $stmt->execute([
                    $npwp_aktif, $tahun_aktif, $item['kode_objek'], $item['no_bupot'], 
                    $item['jenis_penerbitan'], $item['npwp_lawan'], $item['nama_lawan'], 
                    $item['dpp'], $item['pph'], $jp, $sifat, $kredit
                ]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Bulk Error: " . $e->getMessage() . "</div>"; }
    }

    // 3. Simpan Bulk dari Upload CSV/XLSX Manual (Centralized)
    if (isset($_POST['import_csv'])) {
        require_once 'functions_upload.php';
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            try {
                $file = $_FILES['csv_file']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                
                $data = ($ext === 'csv') ? parseCSV($file) : parseExcel($file, $ext);
                
                if (processUploadData($db, 'bupot', $npwp_aktif, $tahun_aktif, $data)) {
                    header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
                    exit;
                }
            } catch (Exception $e) { $message = "<div class='alert alert-danger'>Import Error: " . $e->getMessage() . "</div>"; }
        }
    }
}


//$kode_objek = $data[$objIdx];
//$npwp_l = $data[$npwpIdx];
                        
//$prefix = substr($kode_objek, 0, 2);
//                        $jp = 'PPh_Final';
//                        if ($prefix == '21') $jp = 'PPh_21';
//                        elseif ($prefix == '22') $jp = 'PPh_22';
//                        elseif ($prefix == '24' || $prefix == '23') $jp = 'PPh_23';
//                        elseif ($prefix == '28' || $prefix == '4-') $jp = 'PPh_Final';
//
//                        $kredit = (in_array($jp, ['PPh_21', 'PPh_22', 'PPh_23']) && ($npwp_l !== $npwp_aktif)) ? 'ya' : 'tidak';
//                        $sifat = ($jp == 'PPh_Final' || strpos($kode_objek, '4-') === 0 || strpos($kode_objek, '28-') === 0) ? 'Final' : 'Tidak Final';

//                        $stmt->execute([
//                            $npwp_aktif, $itemTahun, $kode_objek, $data[$noIdx], 
//                            $data[$jnsIdx] ?? 'BPNR', $npwp_l, $data[$namaIdx], 
//                            $data[$dppIdx], $data[$pphIdx], $jp, $sifat, $kredit
//                        ]);
//                   }   
//                }
//                fclose($handle);
//                header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
//                exit;
//            } catch (Exception $e) { $message = "<div class='alert alert-danger'>Import Error: " . $e->getMessage() . "</div>"; }
//        } else {
//            $message = "<div class='alert alert-danger'>Pilih file CSV terlebih dahulu.</div>";
//        }
//    }
//}

if (isset($_GET['delete'])) {
    try {
        $db->prepare("DELETE FROM bukti_potong WHERE id = ? AND npwp = ?")->execute([$_GET['delete'], $npwp_aktif]);
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted");
        exit;
    } catch (Exception $e) { $message = "<div class='alert alert-danger'>Gagal Hapus: " . $e->getMessage() . "</div>"; }
}

try {
    $stmt = $db->prepare("SELECT * FROM bukti_potong WHERE npwp = ? AND tahun = ? ORDER BY id DESC");
    $stmt->execute([$npwp_aktif, $tahun_aktif]);
    $list_bupot = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $list_bupot = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bupot Manager - <?= htmlspecialchars($wp['nama'] ?? $npwp_aktif) ?></title>
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
        .scanning-loader { display: none; }
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
                <h4 class="fw-bold m-0 text-primary">Manajemen Bukti Potong</h4>
                <span class="text-muted small">NPWP: <?php echo $npwp_aktif; ?> | Tahun Pajak: <?php echo $tahun_aktif; ?> | <?php echo htmlspecialchars($wp['nama']); ?></span>
            </div>
        </div>

        <?= $message ?>
        <?php if(isset($_GET['status']) && $_GET['status'] == 'bulk_success'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Import Berhasil!</strong> Data CSV berhasil diproses.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Sukses!</strong> Data berhasil disimpan/diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif(isset($_GET['status']) && $_GET['status'] == 'deleted_all'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Berhasil!</strong> Semua data bukti potong tahun <?= $tahun_aktif ?> telah dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- LAYOUT 4/8 SPLIT SEPERTI SETORAN PAJAK -->
        <div class="row g-4">
            <!-- Panel Kiri: Aksi Massal & Form Manual -->
            <div class="col-lg-4">
                
                <!-- Aksi Massal & Filter -->
                <div class="card card-custom p-3 bg-white mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-dark">Aksi Data Bupot</h6>
                        <select class="form-select form-select-sm w-auto" onchange="location.href='?npwp=<?= $npwp_aktif ?>&tahun='+this.value">
                            <?php for($y=date('Y'); $y>=2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $tahun_aktif==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalCSV">
                            <i data-lucide="upload" class="inline me-1" style="width:16px;"></i> Import CSV Bupot
                        </button>
                        <button class="btn btn-outline-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAI">
                            <i data-lucide="sparkles" class="inline me-1" style="width:16px;"></i> Upload via AI Scanner
                        </button>
                        <form method="POST" action="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="d-grid m-0" onsubmit="return confirm('Hapus seluruh data Bukti Potong untuk tahun <?= $tahun_aktif ?>? Aksi ini tidak dapat dibatalkan.');">
                            <button type="submit" name="delete_all" class="btn btn-outline-danger btn-sm fw-bold">
                                <i data-lucide="trash-2" class="inline me-1" style="width:16px;"></i> Hapus Semua (<?= $tahun_aktif ?>)
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Form Manual Inline -->
                <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-primary" id="formTitle">Rekam Manual Bupot</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="resetForm()" title="Reset Form">
                            <i data-lucide="refresh-cw" style="width: 14px;"></i>
                        </button>
                    </div>
                    
                    <form method="POST" action="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" id="bupotForm">
                        <input type="hidden" name="id" id="f_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Tahun Pajak</label>
                            <input type="number" name="tahun_input" id="tahun_input" class="form-control form-control-sm" value="<?= $tahun_aktif ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nomor Bupot</label>
                            <input type="text" name="no_bupot" id="f_no" class="form-control form-control-sm" required placeholder="Contoh: BPN-12345">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Kode Objek Pajak</label>
                                <input type="text" name="kode_objek_pajak" id="f_kode" class="form-control form-control-sm" required placeholder="Contoh: 23-104-00">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Penerbitan</label>
                                <select name="jenis_penerbitan" id="f_penerbitan" class="form-select form-select-sm">
                                    <option value="BPPU">BPPU</option>
                                    <option value="BPNR">BPNR</option>
                                    <option value="Penyetoran_Sendiri">Setor Sendiri</option>
                                    <option value="Bupot_Bulanan_Pegawai">Bupot Pegawai</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">NPWP Lawan</label>
                            <input type="text" name="npwp_lawan" id="f_npwp_l" class="form-control form-control-sm" required placeholder="Format: 00.000.000.0-000.000">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Lawan</label>
                            <input type="text" name="nama_lawan" id="f_nama_l" class="form-control form-control-sm" required placeholder="Nama PT / CV">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Sifat Bupot</label>
                                <select name="sifat_bupot" id="f_sifat" class="form-select form-select-sm">
                                    <option value="Tidak Final">Tidak Final</option>
                                    <option value="Final">Final</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Fasilitas?</label>
                                <select name="fasilitas" id="f_fasilitas" class="form-select form-select-sm">
                                    <option value="Tidak">Tidak</option>
                                    <option value="SKB">SKB (Bebas)</option>
                                    <option value="DTP">DTP</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label">Nilai DPP (Rp)</label>
                                <input type="number" step="0.01" name="dpp_bupot" id="f_dpp" class="form-control form-control-sm" required placeholder="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nilai PPh (Rp)</label>
                                <input type="number" step="0.01" name="nilai_pph" id="f_pph" class="form-control form-control-sm" required placeholder="0">
                            </div>
                        </div>

                        <button type="submit" name="save_bupot" class="btn btn-primary w-100 fw-bold">
                            <i data-lucide="save" class="inline me-1" style="width:16px;"></i> Simpan Data Bupot
                        </button>
                    </form>
                </div>
            </div>

            <!-- Panel Kanan: Tabel -->
            <div class="col-lg-8">
                <div class="card card-custom bg-white h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="fw-bold m-0">Daftar Bukti Potong / Pungut (Tahun <?php echo $tahun_aktif; ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 0.85rem;">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="text-start ps-3">No. Bupot & Lawan</th>
                                    <th>Objek</th>
                                    <th class="text-end">DPP (Rp)</th>
                                    <th class="text-end">PPh (Rp)</th>
                                    <th>Kredit?</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($list_bupot)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada data bukti potong untuk tahun ini.</td></tr>
                                <?php else: foreach($list_bupot as $b): ?>
                                    <tr>
                                        <td class="text-start ps-3">
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($b['no_bupot']) ?></div>
                                            <div class="text-truncate text-muted" style="max-width: 150px; font-size: 0.75rem;" title="<?= htmlspecialchars($b['nama_lawan']) ?>">
                                                <?= htmlspecialchars($b['nama_lawan']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= $b['kode_objek_pajak'] ?></span><br>
                                            <small class="fw-bold <?= $b['sifat_bupot']=='Final'?'text-danger':'text-success' ?>" style="font-size: 0.65rem;"><?= $b['jenis_pajak'] ?></small>
                                        </td>
                                        <td class="text-end"><?= number_format($b['dpp_bupot'], 0, ',', '.') ?></td>
                                        <td class="text-end fw-bold text-danger"><?= number_format($b['nilai_pph'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="badge-credit <?= $b['dikreditkan']=='ya'?'bg-success text-white':'bg-secondary' ?>">
                                                <?= strtoupper($b['dikreditkan']=='ya'?'KREDIT':'NON-KREDIT') ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick='editBupot(<?= json_encode($b) ?>)'>
                                                <i data-lucide="edit-3" style="width:14px"></i>
                                            </button>
                                            <a href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Hapus Bukti Potong ini?')">
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
                <h6 class="modal-title fw-bold"><i data-lucide="file-spreadsheet" class="inline me-2" style="width:18px"></i> Import File Bukti Potong</h6>
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
                            <strong>Tip:</strong> Mendukung format CSV dan XLSX. Sistem akan memvalidasi Kredit Pajak PPh 21/22/23 secara otomatis.
                            <div class="mt-2 pt-2 border-top">
                                <span class="d-block mb-1 fw-bold text-dark">Unduh Template:</span>
                                <a href="download_template.php?type=bupot&format=csv" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> CSV
                                </a>
                                <a href="download_template.php?type=bupot&format=xls" class="btn btn-xs btn-outline-success py-0 px-2 ms-1" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> XLS
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Animation UI (Awalnya disembunyikan) -->
                    <div id="progressContainer" class="text-center py-4" style="display: none;">
                        <h6 class="text-success fw-bold mb-3" id="progressStatus">Membaca & Mengklasifikasi Data...</h6>
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

<!-- MODAL AI UPLOAD -->
<div class="modal fade" id="modalAI" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h6 class="modal-title fw-bold"><i data-lucide="sparkles" class="inline me-1"></i> AI Document Scanner</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="uploadStep">
                    <div class="ai-drop-zone" onclick="document.getElementById('aiFile').click()">
                        <i data-lucide="upload-cloud" style="width:48px; height:48px;" class="text-muted mb-3"></i>
                        <h5>Klik atau Tarik Berkas Di Sini</h5>
                        <p class="text-muted small">Mendukung format PDF atau Gambar bukti potong</p>
                        <input type="file" id="aiFile" class="d-none" accept=".pdf, image/*">
                    </div>
                </div>

                <div id="aiLoading" class="text-center py-5 scanning-loader">
                    <div class="spinner-border text-warning mb-3" style="width: 3rem; height: 3rem;"></div>
                    <h5 class="fw-bold text-primary">Gemini AI sedang membaca dokumen...</h5>
                </div>

                <div id="resultStep" style="display:none;">
                    <div class="alert alert-info py-2 small mb-3">
                        <i data-lucide="info" class="inline me-1"></i> Hasil ekstraksi AI. Silakan periksa sebelum disimpan.
                    </div>
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-hover table-bordered bg-white" style="font-size: 0.8rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th>No. Bupot</th>
                                    <th>Objek</th>
                                    <th>Lawan Transaksi</th>
                                    <th>DPP</th>
                                    <th>PPh</th>
                                    <th>Penerbitan</th>
                                </tr>
                            </thead>
                            <tbody id="aiResultBody"></tbody>
                        </table>
                    </div>
                    <form method="POST" action="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="mt-3 text-end">
                        <input type="hidden" name="ai_data_json" id="ai_data_json">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">Batal</button>
                        <button type="submit" name="bulk_save_ai" class="btn btn-primary btn-sm fw-bold">Simpan Data AI</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function editBupot(data) {
        document.getElementById('formTitle').innerText = 'Edit Bukti Potong';
        document.getElementById('f_id').value = data.id;
        document.getElementById('tahun_input').value = data.tahun;
        document.getElementById('f_no').value = data.no_bupot;
        document.getElementById('f_kode').value = data.kode_objek_pajak;
        document.getElementById('f_penerbitan').value = data.jenis_penerbitan;
        document.getElementById('f_npwp_l').value = data.npwp_lawan;
        document.getElementById('f_nama_l').value = data.nama_lawan;
        document.getElementById('f_dpp').value = data.dpp_bupot;
        document.getElementById('f_pph').value = data.nilai_pph;
        document.getElementById('f_sifat').value = data.sifat_bupot;
        document.getElementById('f_fasilitas').value = data.fasilitas || 'Tidak';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formTitle').innerText = 'Rekam Manual Bupot';
        document.getElementById('f_id').value = '';
        document.getElementById('bupotForm').reset();
    }

    // Animasi Upload Progress Bar CSV
    function handleUpload(e) {
        e.preventDefault(); 
        
        const fileInput = document.getElementById('csv_file');
        if(!fileInput.files.length) return;

        // Switch UI
        document.getElementById('uploadUI').style.display = 'none';
        document.getElementById('uploadFooter').style.display = 'none';
        document.getElementById('progressContainer').style.display = 'block';

        let progress = 0;
        const pb = document.getElementById('uploadProgressBar');
        const pct = document.getElementById('uploadProgressText');
        const status = document.getElementById('progressStatus');

        // Simulasi proses membaca file dan verifikasi AI
        const interval = setInterval(() => {
            progress += Math.floor(Math.random() * 15) + 5; 
            
            if (progress > 30 && progress < 75) status.innerText = "Mengklasifikasikan Jenis PPh (21/22/23/Final)...";
            if (progress >= 75) status.innerText = "Menyimpan Ratusan Baris ke Database...";
            
            if(progress >= 100) {
                progress = 100;
                clearInterval(interval);
                status.innerText = "Selesai!";
                pb.classList.remove('progress-bar-animated');
                
                // Submit form aslinya setelah 0.5 detik
                setTimeout(() => {
                    document.getElementById('formUploadCSV').submit();
                }, 500);
            }
            
            pb.style.width = progress + '%';
            pct.innerText = progress + '%';
        }, 200);
    }

    // AI Scanner (Tetap Menggunakan Script Sebelumnya)
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
                const prompt = `Anda adalah sistem OCR pajak. Ekstrak data bukti potong dari dokumen berikut.
                Dokumen: ${fileContent.substring(0, 5000)}
                Outputkan HANYA JSON ARRAY OF OBJECTS dengan field: no_bupot, kode_objek, npwp_lawan, nama_lawan, dpp, pph, jenis_penerbitan.`;

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
                    const data = JSON.parse(jsonText);

                    const tbody = document.getElementById('aiResultBody');
                    tbody.innerHTML = '';
                    data.forEach(item => {
                        tbody.innerHTML += `
                            <tr>
                                <td>${item.no_bupot}</td>
                                <td>${item.kode_objek}</td>
                                <td>${item.nama_lawan}<br><small class="text-muted">${item.npwp_lawan}</small></td>
                                <td class="text-end">${Number(item.dpp).toLocaleString('id-ID')}</td>
                                <td class="text-end fw-bold text-danger">${Number(item.pph).toLocaleString('id-ID')}</td>
                                <td>${item.jenis_penerbitan}</td>
                            </tr>
                        `;
                    });

                    document.getElementById('ai_data_json').value = JSON.stringify(data);
                    document.getElementById('aiLoading').style.display = 'none';
                    document.getElementById('resultStep').style.display = 'block';
                    lucide.createIcons();
                } catch (err) {
                    alert("AI Gagal memproses dokumen.");
                    location.reload();
                }
            };
            reader.readAsText(file);
        };
    }
</script>
</body>
</html>