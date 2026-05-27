<?php
ob_start();
require_once 'config.php';
require_once 'functions_upload.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";

// Parameter Global Dinamis
$npwp_aktif = $_GET['npwp'] ?? '';
$tahun_aktif = $_GET['tahun'] ?? date('Y') - 1;
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

// Handler POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Simpan Manual
    if (isset($_POST['save_akun'])) {
        try {
            $id = $_POST['id'] ?? '';
            $kode = $_POST['kode_akun'];
            $nama = $_POST['nama_akun'];
            $jenis = $_POST['jenis'];
            $nominal = $_POST['nominal'];
            $tahun_input = $_POST['tahun_input'] ?? $tahun_aktif;
            
            // Gunakan manual dari form jika ada, jika tidak otomatis
            $kategori_akun = $_POST['kategori_akun'] ?? null;
            $kategori_arus_kas = $_POST['kategori_arus_kas'] ?? null;
            $jenis_pilihan = $_POST['jenis'] ?? null;

            if (empty($kategori_akun) || empty($kategori_arus_kas) || empty($jenis_pilihan)) {
                $klas = klasifikasiAkun($nama);
                $kategori_akun = $kategori_akun ?: $klas['kategori'];
                $kategori_arus_kas = $kategori_arus_kas ?: $klas['arus_kas'];
                $jenis_pilihan = $jenis_pilihan ?: $klas['jenis'];
            }

            if (empty($id)) {
                $sql = "INSERT INTO mapping_akun (npwp, tahun, kode_akun, nama_akun, jenis, nominal, kategori_akun, kategori_arus_kas) VALUES (?,?,?,?,?,?,?,?)";
                $db->prepare($sql)->execute([$npwp_aktif, $tahun_input, $kode, $nama, $jenis_pilihan, $nominal, $kategori_akun, $kategori_arus_kas]);
            } else {
                $sql = "UPDATE mapping_akun SET kode_akun=?, nama_akun=?, jenis=?, nominal=?, kategori_akun=?, kategori_arus_kas=?, tahun=? WHERE id=?";
                $db->prepare($sql)->execute([$kode, $nama, $jenis_pilihan, $nominal, $kategori_akun, $kategori_arus_kas, $tahun_input, $id]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_input&status=success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
    }

    // 2. Import CSV/XLSX Bulk (Centralized)
    if (isset($_POST['import_csv'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            try {
                $file = $_FILES['csv_file']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                
                $data = ($ext === 'csv') ? parseCSV($file) : parseExcel($file, $ext);
                
                if (processUploadData($db, 'akun', $npwp_aktif, $tahun_aktif, $data)) {
                    header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
                    exit;
                }
            } catch (Exception $e) { $message = "<div class='alert alert-danger'>Import Error: " . $e->getMessage() . "</div>"; }
        }
    }

    // 3. Simpan Bulk dari AI Scanner
    if (isset($_POST['bulk_save_ai'])) {
        try {
            $data_json = json_decode($_POST['ai_data_json'], true);
            $stmt = $db->prepare("INSERT INTO mapping_akun (npwp, tahun, kode_akun, nama_akun, jenis, nominal, kategori_akun, kategori_arus_kas) VALUES (?,?,?,?,?,?,?,?)");
            
            foreach ($data_json as $item) {
                $itemTahun = $item['tahun'] ?? $tahun_aktif;
                $klas = klasifikasiAkun($item['nama_akun']);
                $stmt->execute([
                    $npwp_aktif, $itemTahun, $item['kode_akun'], $item['nama_akun'], 
                    strtoupper($item['jenis']), $item['nominal'], $klas['kategori'], $klas['arus_kas']
                ]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>AI Error: " . $e->getMessage() . "</div>"; }
    }

    // Hapus Semua Data
    if (isset($_POST['delete_all'])) {
        try {
            $db->prepare("DELETE FROM mapping_akun WHERE npwp = ? AND tahun = ?")->execute([$npwp_aktif, $tahun_aktif]);
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted_all");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
    }
}

// Hapus Data Tunggal
if (isset($_GET['delete'])) {
    try {
        $db->prepare("DELETE FROM mapping_akun WHERE id = ? AND npwp = ?")->execute([$_GET['delete'], $npwp_aktif]);
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted");
        exit;
    } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
}

// Ambil Daftar Data
try {
    $stmt = $db->prepare("SELECT * FROM mapping_akun WHERE npwp = ? AND tahun = ? ORDER BY kode_akun ASC");
    $stmt->execute([$npwp_aktif, $tahun_aktif]);
    $list_akun = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $list_akun = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapping Akun - <?= htmlspecialchars($wp['nama'] ?? $npwp_aktif) ?></title>
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
                <h4 class="fw-bold m-0 text-primary">Mapping Akun (Trial Balance)</h4>
                <span class="text-muted small">NPWP: <?= $npwp_aktif; ?> | Tahun: <?= $tahun_aktif; ?> | <?= htmlspecialchars($wp['nama']); ?></span>
            </div>
        </div>

        <?= $message ?>
        <?php if(isset($_GET['status'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Aksi Berhasil!</strong> Data mapping akun telah diperbarui.
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
                        <form method="POST" class="d-grid" onsubmit="return confirm('Hapus semua data tahun <?= $tahun_aktif ?>?')">
                            <button type="submit" name="delete_all" class="btn btn-outline-danger btn-sm fw-bold">
                                <i data-lucide="trash-2" class="inline me-1" style="width:16px;"></i> Hapus Semua Data
                            </button>
                        </form>
                        <a href="laporan_keuangan.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-primary btn-sm fw-bold">
                            <i data-lucide="file-text" class="inline me-1" style="width:16px;"></i> Lihat Laporan Keuangan
                        </a>
                    </div>
                </div>

                <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-primary" id="formTitle">Rekam Akun</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="resetForm()">
                            <i data-lucide="refresh-cw" style="width: 14px;"></i>
                        </button>
                    </div>
                    
                    <form method="POST" id="akunForm">
                        <input type="hidden" name="id" id="f_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Tahun Pajak</label>
                            <input type="number" name="tahun_input" id="f_tahun" class="form-control form-control-sm" value="<?= $tahun_aktif ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Akun</label>
                            <input type="text" name="nama_akun" id="f_nama" class="form-control form-control-sm" required placeholder="Cth: KAS DAN BANK">
                        </div>
                       <div class="mb-3">
                            <label class="form-label">Kode Akun</label>
                            <input type="text" name="kode_akun" id="f_kode" class="form-control form-control-sm" value="-- Otomatis --" required placeholder="Cth: 1-1100">
                        </div>
<?php 
// Ambil aturan kategori dari fungsi klasifikasiAkun secara dinamis untuk dropdown
$rules = [
    'piutang'=>['PIUTANG JANGKA PANJANG','BEBAN DIBAYAR DI MUKA','PIUTANG RAGU-RAGU','PIUTANG LAIN-LAIN','PIUTANG USAHA PIHAK YANG MEMPUNYAI HUBUNGAN ISTIMEWA','PIUTANG USAHA PIHAK KETIGA','PIUTANG','BAYAR DIMUKA','UANG MUKA PEMBELIAN','PIUTANG USAHA'],
    'utang_bank'=>['UTANG BANK','PINJAMAN BANK','HUTANG BANK','HUTANG BUNGA','BAGIAN HUTANG JANGKA PANJANG YANG JATUH TEMPO DALAM TAHUN BERJALAN'],
    'utang'=>['UTANG','HUTANG','KEWAJIBAN','PINJAMAN','HUTANG USAHA','HUTANG PAJAK','HUTANG DEVIDEN','BIAYA YANG MASIH HARUS DIBAYAR','UANG MUKA PELANGGAN','KEWAJIBAN LANCAR LAINNYA','KEWAJIBAN PAJAK','KEWAJIBAN TIDAK LANCAR LAINNYA'],
    'pembelian' => ['PEMBELIAN', 'PEMBELIAN BARANG', 'PEMBELIAN BAHAN', 'HARGA POKOK', 'HPP','BELI','BEBAN POKOK PENJUALAN','BEBAN POKOK PRODUKSI'],
    'persediaan_akhir' => ['PERSEDIAAN AKHIR', 'SALDO AKHIR PERSEDIAAN'],
    'persediaan_awal' => ['PERSEDIAAN AWAL', 'SALDO AWAL PERSEDIAAN'],
    'persediaan'=>['PERSEDIAAN'],
    'aset_tetap'=>['AKTIVA TIDAK LANCAR LAINNYA','AKTIVA TETAP LAINNYA','TANAH DAN BANGUNAN','AKUM. PENYUSUTAN','AKUMULASI PENYUSUTAN','TANAH','BANGUNAN','ASET TETAP','KENDARAAN'],
    'modal'=>['MODAL','LABA DITAHAN','SAHAM','DEVIDEN','MODAL SAHAM','AGIO','TAMBAHAN MODAL DISETOR','LABA DITAHAN','EKUITAS'],
    'pendapatan_lain'=>['PENDAPATAN LAIN','JASA GIRO','DILUAR USAHA','PENGHASILAN LAIN','MANFAAT PAJAK','PENGHASILAN/(BEBAN) LAIN','BAGIAN LABA (RUGI) PERUSAHAAN ASOSIASI','PENDAPATAN NON OPERASIONAL','PENDAPATAN BUNGA','PENDAPATAN SEWA','PENDAPATAN ROYALTI','PENDAPATAN DIVIDEN','PENGHASILAN DI LUAR USAHA','PENDAPATAN LUAR USAHA','PENGHASILAN LUAR USAHA'],
    'peredaran_usaha'=>['JUAL','PENJUALAN','OMSET','PENJUALAN BERSIH','PENJUALAN NETTO','PENJUALAN USAHA','PENJUALAN DAGANG','PENJUALAN PRODUK','PENJUALAN JASA','PENGHASILAN USAHA','PENGHASILAN JASA','PENDAPATAN USAHA','PENDAPATAN JASA','PENGHASILAN'],
    'beban_lain'=>['BEBAN BUNGA','BEBAN ADM','BEBAN PAJAK','BIAYA ADMINISTRASI','BIAYA ADM','BIAYA BUNGA','BIAYA LUAR USAHA','BEBAN DILUAR USAHA','BEBAN NON OPERASIONAL','BIAYA DILUAR USAHA','BEBAN DILUAR USAHA','PAJAK'],
    'beban_gaji'=>['GAJI','UPAH','HONOR','BONUS','TUNJANGAN','REMUNERASI','BIAYA KARYAWAN','BEBAN KARYAWAN'],
    'beban_usaha'=>['BEBAN USAHA','SEWA','LISTRIK','TELEPON','INTERNET','BIAYA','BEBAN UMUM DAN ADMINISTRASI'],
    'penyusutan'=>['PENYUSUTAN','AMORTISASI','DEPRESIASI'],
    'koreksi_fiskal_positif'=>['FISKAL POSITIF','KOREKSI PENYUSUTAN','KOREKSI PERSEDIAAN','KOREKSI ASET','KOREKSI AKTIVA','KOREKSI PENDAPATAN','KOREKSI OMSET'],
    'koreksi_fiskal_negatif'=>['FISKAL NEGATIF','PENGHASILAN FINAL','HIBAH','WARISAN','BUKAN OBJEK PAJAK','PTKP','KOREKSI BIAYA','KOREKSI BEBAN'],
    'kas'=>['KAS','BANK','KAS DAN SETARA KAS'],
    'aset_lancar'=>['AKTIVA LANCAR LAINNYA','ASET LANCAR','INVESTASI SEMENTARA'],
    'aset_tidak_berwujud'=>['AKUM. AMORTISASI','AKUMULASI AMORTISASI','HARTA TIDAK BERWUJUD','AKTIVA PAJAK TANGGUHAN']    
];
$arus_kas_opts = ['arus_kas_investasi', 'arus_kas_pendanaan', 'arus_kas_operasi_masuk', 'arus_kas_operasi_keluar', 'abaikan'];
?>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Kategori Akun</label>
                                <select name="kategori_akun" id="f_kategori" class="form-select form-select-sm">
                                    <option value="">-- Otomatis --</option>
                                    <?php foreach(array_keys($rules) as $k): ?><option value="<?= $k ?>"><?= ucwords(str_replace('_', ' ', $k)) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Kategori Arus Kas</label>
                                <select name="kategori_arus_kas" id="f_arus_kas" class="form-select form-select-sm">
                                    <option value="">-- Otomatis --</option>
                                    <?php foreach($arus_kas_opts as $a): ?><option value="<?= $a ?>"><?= ucwords(str_replace('_', ' ', $a)) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label">Jenis</label>
                                <select name="jenis" id="f_jenis" class="form-select form-select-sm">
                                    <option value="">-- Otomatis --</option>
                                    <option value="DEBIT">DEBIT</option>
                                    <option value="KREDIT">KREDIT</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nominal (Rp)</label>
                                <input type="number" step="0.01" name="nominal" id="f_nominal" class="form-control form-control-sm" required placeholder="0">
                            </div>
                        </div>

                        <button type="submit" name="save_akun" class="btn btn-primary w-100 fw-bold">Simpan Akun</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card card-custom bg-white h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="fw-bold m-0">Daftar Akun Tersimpan (<?= $tahun_aktif; ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 0.85rem;">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="text-start ps-3">Kode & Nama Akun</th>
                                    <th>Jenis</th>
                                    <th class="text-end">Nominal (Rp)</th>
                                    <th>Kategori</th>
                                    <th>Arus Kas</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($list_akun)): ?>
                                    <tr><td colspan="6" class="text-muted py-5">Belum ada data mapping akun.</td></tr>
                                <?php else: foreach($list_akun as $a): ?>
                                    <tr>
                                        <td class="text-start ps-3">
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($a['kode_akun']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($a['nama_akun']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $a['jenis']=='DEBIT'?'bg-light text-primary border':'bg-light text-danger border' ?>"><?= $a['jenis'] ?></span>
                                        </td>
                                        <td class="text-end fw-bold"><?= number_format($a['nominal'], 0, ',', '.') ?></td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size: 10px;"><?= $a['kategori_akun'] ?></span></td>
                                        <td><span class="badge <?= $a['kategori_arus_kas']=='Abaikan'?'bg-light text-muted':'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25' ?>" style="font-size: 10px;"><?= $a['kategori_arus_kas'] ?></span></td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick='editAkun(<?= json_encode($a) ?>)'>
                                                <i data-lucide="edit-3" style="width:14px"></i>
                                            </button>
                                            <a href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&delete=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Hapus akun ini?')">
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

<!-- Modal CSV/XLSX -->
<div class="modal fade" id="modalCSV" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title fw-bold"><i data-lucide="file-spreadsheet" class="inline me-2" style="width:18px"></i> Import File Mapping Akun</h6>
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
                            <strong>Tip:</strong> Sistem akan mengklasifikasikan kategori akun dan arus kas secara otomatis.
                            <div class="mt-2 pt-2 border-top">
                                <span class="d-block mb-1 fw-bold text-dark">Unduh Template:</span>
                                <a href="download_template.php?type=akun&format=csv&tahun=<?=$tahun_aktif?>" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> CSV
                                </a>
                                <a href="download_template.php?type=akun&format=xls&tahun=<?=$tahun_aktif?>" class="btn btn-xs btn-outline-success py-0 px-2 ms-1" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> XLS
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="progressContainer" class="text-center py-4" style="display: none;">
                        <h6 class="text-success fw-bold mb-3" id="progressStatus">Menganalisa Nama Akun...</h6>
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
                <h6 class="modal-title fw-bold"><i data-lucide="sparkles" class="inline me-1"></i> AI Trial Balance Scanner</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="aiUploadStep">
                    <div class="ai-drop-zone" onclick="document.getElementById('aiFile').click()">
                        <i data-lucide="upload-cloud" style="width:48px; height:48px;" class="text-muted mb-3"></i>
                        <h5>Pilih Berkas Trial Balance</h5>
                        <p class="text-muted small">Mendukung format PDF atau Gambar TB. AI akan mengekstrak akun secara otomatis.</p>
                        <input type="file" id="aiFile" class="d-none" accept=".pdf, image/*">
                    </div>
                </div>
                <div id="aiLoading" class="text-center py-5" style="display:none;">
                    <div class="spinner-border text-warning mb-3" style="width: 3rem; height: 3rem;"></div>
                    <h5 class="fw-bold text-primary">Gemini AI sedang menganalisa dokumen...</h5>
                </div>
                <div id="aiResultStep" style="display:none;">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-bordered bg-white" style="font-size: 0.8rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Akun</th>
                                    <th>Jenis</th>
                                    <th class="text-end">Nominal</th>
                                </tr>
                            </thead>
                            <tbody id="aiResultBody"></tbody>
                        </table>
                    </div>
                    <form method="POST" class="mt-3 text-end">
                        <input type="hidden" name="ai_data_json" id="ai_data_json">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">Reset</button>
                        <button type="submit" name="bulk_save_ai" class="btn btn-primary btn-sm fw-bold px-4">Simpan Data AI</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function editAkun(data) {
        document.getElementById('formTitle').innerText = 'Edit Akun';
        document.getElementById('f_id').value = data.id;
        document.getElementById('f_tahun').value = data.tahun;
        document.getElementById('f_kode').value = data.kode_akun;
        document.getElementById('f_nama').value = data.nama_akun;
        document.getElementById('f_kategori').value = data.kategori_akun;
        document.getElementById('f_arus_kas').value = data.kategori_arus_kas;
        document.getElementById('f_jenis').value = data.jenis;
        document.getElementById('f_nominal').value = data.nominal;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formTitle').innerText = 'Rekam Akun';
        document.getElementById('f_id').value = '';
        document.getElementById('akunForm').reset();
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

    const aiFile = document.getElementById('aiFile');
    if (aiFile) {
        // ... existing aiFile logic ...
    }

    // Auto-detect classification
    $('#f_nama').on('blur', function() {
        const nama = $(this).val();
        if (nama.length > 3) {
            $.get('api/api_klasifikasi.php', { nama: nama }, function(data) {
                if (data.kategori) $('#f_kategori').val(data.kategori);
                if (data.arus_kas) $('#f_arus_kas').val(data.arus_kas);
                if (data.jenis) $('#f_jenis').val(data.jenis);
                if (data.kode_suggest && !$('#f_kode').val()) $('#f_kode').val(data.kode_suggest);
            });
        }
    });
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Re-initialize Lucide after dynamic content (if needed)
    $(document).ajaxStop(function() { lucide.createIcons(); });
</script>
</body>
</html>