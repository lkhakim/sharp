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

// Parameter Global
$npwp_aktif = $_GET['npwp'] ?? '';
$tahun_aktif = $_GET['tahun'] ?? date('Y');
$current_file = basename($_SERVER['PHP_SELF']);

if (empty($npwp_aktif)) {
    header("Location: manajemen_wp.php");
    exit;
}

// Ambil Nama WP
try {
    $stmtWp = $db->prepare("SELECT nama FROM profil_wp WHERE npwp = ?");
    $stmtWp->execute([$npwp_aktif]);
    $wp = $stmtWp->fetch();
} catch (Exception $e) { $wp = ['nama' => 'WP Tidak Ditemukan']; }

// Handler POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Simpan Manual
    if (isset($_POST['save_ilap'])) {
        try {
            $id = $_POST['id'] ?? '';
            $tgl = $_POST['tanggal'];
            $kat = $_POST['kategori'];
            $jns = $_POST['jenis_saldo'];
            $nom = $_POST['nominal'];
            $sld = $_POST['saldo'];
            $thn = $_POST['tahun_input'] ?? $tahun_aktif;
            
            // Klasifikasi Otomatis
            $kat_data = klasifikasiIlap($_POST['keterangan'] ?? '');

            if (empty($id)) {
                $sql = "INSERT INTO data_ilap (npwp, tahun, tanggal, kategori, jenis_saldo, nominal, saldo, kategori_data, sumber_data, created_by) VALUES (?,?,?,?,?,?,?,?,'MANUAL',?)";
                $db->prepare($sql)->execute([$npwp_aktif, $thn, $tgl, $kat, $jns, $nom, $sld, $kat_data, $_SESSION['user_id']]);
            } else {
                $sql = "UPDATE data_ilap SET tahun=?, tanggal=?, kategori=?, jenis_saldo=?, nominal=?, saldo=?, kategori_data=? WHERE id=?";
                $db->prepare($sql)->execute([$thn, $tgl, $kat, $jns, $nom, $sld, $kat_data, $id]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$thn&status=success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
    }

    // 2. Import CSV/XLSX
    if (isset($_POST['import_csv'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            try {
                $file = $_FILES['csv_file']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                $data = ($ext === 'csv') ? parseCSV($file) : parseExcel($file, $ext);
                if (processUploadData($db, 'ilap', $npwp_aktif, $tahun_aktif, $data)) {
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
            $stmt = $db->prepare("INSERT INTO data_ilap (npwp, tahun, tanggal, kategori, jenis_saldo, nominal, saldo, kategori_data, sumber_data, created_by) VALUES (?,?,?,?,?,?,?,?,'AI',?)");
            foreach ($data_json as $item) {
                $kat_data = klasifikasiIlap($item['keterangan'] ?? '');
                $stmt->execute([
                    $npwp_aktif, $tahun_aktif, $item['tanggal'], 
                    strtoupper($item['kategori'] ?? 'PIHAK_LAIN'), 
                    strtoupper($item['jenis'] ?? 'KREDIT'), 
                    $item['nominal'], $item['saldo'] ?? 0, 
                    $kat_data, $_SESSION['user_id']
                ]);
            }
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=bulk_success");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>AI Error: " . $e->getMessage() . "</div>"; }
    }

    // Hapus Semua
    if (isset($_POST['delete_all'])) {
        try {
            $db->prepare("DELETE FROM data_ilap WHERE npwp = ? AND tahun = ?")->execute([$npwp_aktif, $tahun_aktif]);
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted_all");
            exit;
        } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
    }
}

// Hapus Tunggal
if (isset($_GET['delete'])) {
    try {
        $db->prepare("DELETE FROM data_ilap WHERE id = ? AND npwp = ?")->execute([$_GET['delete'], $npwp_aktif]);
        header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=deleted");
        exit;
    } catch (Exception $e) { $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
}

// Ambil List Data
try {
    $stmt = $db->prepare("SELECT * FROM data_ilap WHERE npwp = ? AND tahun = ? ORDER BY tanggal DESC");
    $stmt->execute([$npwp_aktif, $tahun_aktif]);
    $list_ilap = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $list_ilap = []; }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data ILAP Manager - <?= htmlspecialchars($wp['nama'] ?? $npwp_aktif) ?></title>
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
                <h4 class="fw-bold m-0 text-primary">Manajemen Data ILAP</h4>
                <span class="text-muted small">Rekening Koran / Data Pihak Ketiga | <?= $tahun_aktif; ?> | <?= htmlspecialchars($wp['nama']); ?></span>
            </div>
        </div>

        <?= $message ?>
        <?php if(isset($_GET['status'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Berhasil!</strong> Data ILAP telah diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card card-custom p-3 bg-white mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-dark">Filter & Impor</h6>
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
                            <i data-lucide="sparkles" class="inline me-1" style="width:16px;"></i> Scan PDF via AI
                        </button>
                        <form method="POST" class="d-grid" onsubmit="return confirm('Hapus semua data tahun <?= $tahun_aktif ?>?')">
                            <button type="submit" name="delete_all" class="btn btn-outline-danger btn-sm fw-bold">
                                <i data-lucide="trash-2" class="inline me-1" style="width:16px;"></i> Kosongkan Data
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0 text-primary" id="formTitle">Rekam Data ILAP</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="resetForm()">
                            <i data-lucide="refresh-cw" style="width: 14px;"></i>
                        </button>
                    </div>
                    
                    <form method="POST" id="ilapForm">
                        <input type="hidden" name="id" id="f_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Tahun Pajak</label>
                            <input type="number" name="tahun_input" id="f_tahun" class="form-control form-control-sm" value="<?= $tahun_aktif ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Transaksi</label>
                            <input type="date" name="tanggal" id="f_tgl" class="form-control form-control-sm" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Keterangan / Deskripsi</label>
                            <textarea name="keterangan" id="f_ket" class="form-control form-control-sm" rows="2" required placeholder="Klasifikasi otomatis berdasarkan teks ini..."></textarea>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Kategori Sumber</label>
                                <select name="kategori" id="f_kat" class="form-select form-select-sm">
                                    <option value="INSTANSI">Instansi</option>
                                    <option value="LEMBAGA">Lembaga</option>
                                    <option value="ASOSIASI">Asosiasi</option>
                                    <option value="PIHAK_LAIN" selected>Pihak Lain</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Jenis Saldo</label>
                                <select name="jenis_saldo" id="f_jns" class="form-select form-select-sm">
                                    <option value="KREDIT">KREDIT (In)</option>
                                    <option value="DEBIT">DEBIT (Out)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label">Nominal (Rp)</label>
                                <input type="number" step="0.01" name="nominal" id="f_nom" class="form-control form-control-sm" required placeholder="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Saldo (Rp)</label>
                                <input type="number" step="0.01" name="saldo" id="f_sld" class="form-control form-control-sm" value="0">
                            </div>
                        </div>

                        <button type="submit" name="save_ilap" class="btn btn-primary w-100 fw-bold">Simpan Data</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card card-custom bg-white h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="fw-bold m-0">Daftar Transaksi ILAP (<?= $tahun_aktif; ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 0.82rem;">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="text-start ps-3">Tanggal & Deskripsi</th>
                                    <th>Sumber</th>
                                    <th class="text-end">Nominal (Rp)</th>
                                    <th>Klasifikasi</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($list_ilap)): ?>
                                    <tr><td colspan="5" class="text-muted py-5">Belum ada data ILAP tersimpan.</td></tr>
                                <?php else: foreach($list_ilap as $i): ?>
                                    <tr>
                                        <td class="text-start ps-3">
                                            <div class="fw-bold"><?= date('d/m/Y', strtotime($i['tanggal'])) ?></div>
                                            <div class="text-muted" style="font-size: 11px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?= htmlspecialchars($i['kategori_data']) ?> | <?= $i['jenis_saldo'] ?>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= $i['kategori'] ?></span></td>
                                        <td class="text-end fw-bold <?= $i['jenis_saldo']=='KREDIT'?'text-success':'text-danger' ?>">
                                            <?= number_format($i['nominal'], 0, ',', '.') ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $cls = "bg-primary";
                                            if($i['kategori_data'] == 'COST') $cls = "bg-danger";
                                            elseif($i['kategori_data'] == 'ASSET') $cls = "bg-success";
                                            elseif($i['kategori_data'] == 'LIABILITY') $cls = "bg-warning text-dark";
                                            elseif($i['kategori_data'] == 'EQUITY') $cls = "bg-info text-dark";
                                            ?>
                                            <span class="badge <?= $cls ?> bg-opacity-10 <?= strpos($cls, 'text-dark')?'':'text-primary' ?> border" style="font-size: 10px;"><?= $i['kategori_data'] ?></span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick='editIlap(<?= json_encode($i) ?>)'>
                                                <i data-lucide="edit-3" style="width:14px"></i>
                                            </button>
                                            <a href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&delete=<?= $i['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Hapus data ini?')">
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
                <h6 class="modal-title fw-bold"><i data-lucide="file-spreadsheet" class="inline me-2" style="width:18px"></i> Import File ILAP</h6>
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
                            <strong>Tip:</strong> Pastikan header kolom sesuai template. Sistem akan mengklasifikasikan kategori data (Income/Cost/Asset/dll) secara otomatis.
                            <div class="mt-2 pt-2 border-top">
                                <span class="d-block mb-1 fw-bold text-dark">Unduh Template:</span>
                                <a href="download_template.php?type=ilap&format=csv" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> CSV
                                </a>
                                <a href="download_template.php?type=ilap&format=xls" class="btn btn-xs btn-outline-success py-0 px-2 ms-1" style="font-size: 10px;">
                                    <i data-lucide="download" class="inline" style="width:10px"></i> XLS
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="progressContainer" class="text-center py-4" style="display: none;">
                        <h6 class="text-success fw-bold mb-3" id="progressStatus">Memproses Data ILAP...</h6>
                        <div class="progress" style="height: 12px; border-radius: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="uploadProgressBar" style="width: 0%"></div>
                        </div>
                        <div class="mt-2 fw-bold text-muted" id="uploadProgressText">0%</div>
                    </div>
                </div>
                <div class="modal-footer" id="uploadFooter">
                    <input type="hidden" name="import_csv" value="1">
                    <button type="submit" class="btn btn-success fw-bold px-4">Mulai Unggah</button>
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
                <h6 class="modal-title fw-bold"><i data-lucide="sparkles" class="inline me-1"></i> AI Rekening Koran Scanner (ILAP)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="aiUploadStep">
                    <div class="ai-drop-zone" onclick="document.getElementById('aiFile').click()">
                        <i data-lucide="upload-cloud" style="width:48px; height:48px;" class="text-muted mb-3"></i>
                        <h5>Pilih Berkas PDF Rekening Koran</h5>
                        <p class="text-muted small">AI akan mengekstrak tanggal, keterangan, nominal, dan saldo secara otomatis.</p>
                        <input type="file" id="aiFile" class="d-none" accept=".pdf, image/*">
                    </div>
                </div>
                <div id="aiLoading" class="text-center py-5" style="display:none;">
                    <div class="spinner-border text-warning mb-3" style="width: 3rem; height: 3rem;"></div>
                    <h5 class="fw-bold text-primary">Gemini AI sedang membaca Rekening Koran...</h5>
                </div>
                <div id="aiResultStep" style="display:none;">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-bordered bg-white" style="font-size: 0.75rem;">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Keterangan</th>
                                    <th>Jenis</th>
                                    <th class="text-end">Nominal</th>
                                    <th class="text-end">Saldo</th>
                                </tr>
                            </thead>
                            <tbody id="aiResultBody"></tbody>
                        </table>
                    </div>
                    <form method="POST" class="mt-3 text-end">
                        <input type="hidden" name="ai_data_json" id="ai_data_json">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">Reset</button>
                        <button type="submit" name="bulk_save_ai" class="btn btn-primary btn-sm fw-bold px-4">Konfirmasi & Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function editIlap(data) {
        document.getElementById('formTitle').innerText = 'Edit Data ILAP';
        document.getElementById('f_id').value = data.id;
        document.getElementById('f_tahun').value = data.tahun;
        document.getElementById('f_tgl').value = data.tanggal;
        document.getElementById('f_ket').value = data.kategori_data; // Manual update if needed
        document.getElementById('f_kat').value = data.kategori;
        document.getElementById('f_jns').value = data.jenis_saldo;
        document.getElementById('f_nom').value = data.nominal;
        document.getElementById('f_sld').value = data.saldo;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formTitle').innerText = 'Rekam Data ILAP';
        document.getElementById('f_id').value = '';
        document.getElementById('ilapForm').reset();
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
        aiFile.onchange = async function(e) {
            const file = e.target.files[0];
            if(!file) return;

            document.getElementById('aiUploadStep').style.display = 'none';
            document.getElementById('aiLoading').style.display = 'block';

            const reader = new FileReader();
            reader.onload = async function() {
                const prompt = `Analisa Rekening Koran Bank dari dokumen ini. 
                Output: JSON ARRAY OF OBJECTS dengan field: tanggal (YYYY-MM-DD), keterangan, jenis (DEBIT/KREDIT), nominal, saldo.
                HANYA KEMBALIKAN JSON.`;

                try {
                    // API Key typically injected via config or session in real app
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
                                <td>${item.tanggal}</td>
                                <td>${item.keterangan}</td>
                                <td class="text-center">${item.jenis}</td>
                                <td class="text-end">${Number(item.nominal).toLocaleString('id-ID')}</td>
                                <td class="text-end">${Number(item.saldo).toLocaleString('id-ID')}</td>
                            </tr>
                        `;
                    });

                    document.getElementById('ai_data_json').value = JSON.stringify(data);
                    document.getElementById('aiLoading').style.display = 'none';
                    document.getElementById('aiResultStep').style.display = 'block';
                } catch (err) {
                    alert("AI Gagal memproses Rekening Koran.");
                    location.reload();
                }
            };
            reader.readAsText(file);
        };
    }
</script>
</body>
</html>