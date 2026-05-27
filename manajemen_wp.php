<?php
require_once 'config.php';
session_start();

// Proteksi akses
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$npwp_aktif = $_GET['npwp'] ?? '';
$tahun_aktif = $_GET['tahun'] ?? date('Y');
$current_file = basename($_SERVER['PHP_SELF']);

//$db = getConnection(); // Asumsi fungsi ini ada di config.php dan mengembalikan objek mysqli atau PDO. 
// Note: Kode di bawah menggunakan pendekatan prepared statements yang aman.

$message = "";

// 1. Aksi Simpan / Update Profil WP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_wp'])) {
    try {
        $data = [
            'npwp' => $_POST['npwp'],
            'nama' => $_POST['nama'],
            'alamat' => $_POST['alamat'],
            'kelurahan' => $_POST['kelurahan'],
            'kecamatan' => $_POST['kecamatan'],
            'kota' => $_POST['kota'],
            'propinsi' => $_POST['propinsi'],
            'kode_kpp' => $_POST['kode_kpp'],
            'telpon' => $_POST['telpon'],
            'email' => $_POST['email'],
            'klu' => $_POST['klu'],
            'jenis_wp' => $_POST['jenis_wp'],
            'is_umkm' => isset($_POST['is_umkm']) ? 1 : 0,
            'tgl_daftar' => !empty($_POST['tgl_daftar']) ? $_POST['tgl_daftar'] : null,
            'tgl_pkp' => !empty($_POST['tgl_pkp']) ? $_POST['tgl_pkp'] : null,
            'lat_npwp' => !empty($_POST['lat_npwp']) ? $_POST['lat_npwp'] : null,
            'lng_npwp' => !empty($_POST['lng_npwp']) ? $_POST['lng_npwp'] : null,
            'pemilik_nama' => $_POST['pemilik_nama'],
            'pemilik_nik' => $_POST['pemilik_nik'],
            'pemilik_jabatan' => $_POST['pemilik_jabatan'],
            'is_pic' => isset($_POST['is_pic']) ? 1 : 0,
            'created_by' => $_SESSION['user_id']
        ];

        if (isset($_POST['is_edit']) && $_POST['is_edit'] == '1') {
            $sql = "UPDATE profil_wp SET 
                    nama=?, alamat=?, kelurahan=?, kecamatan=?, kota=?, propinsi=?, 
                    kode_kpp=?, telpon=?, email=?, klu=?, jenis_wp=?, is_umkm=?, 
                    tgl_daftar=?, tgl_pkp=?, lat_npwp=?, lng_npwp=?, 
                    pemilik_nama=?, pemilik_nik=?, pemilik_jabatan=?, is_pic=?, created_by=? 
                    WHERE npwp=?";
            $stmt = $db->prepare($sql);
            // Re-order data array for update
            $update_data = array_values($data);
            array_shift($update_data); // remove npwp from start
            $update_data[] = $data['npwp']; // add npwp to end for WHERE clause
            $stmt->execute($update_data);
            $msg_text = "Memperbarui Profil WP: " . $data['npwp'];
        } else {
            $sql = "INSERT INTO profil_wp (npwp, nama, alamat, kelurahan, kecamatan, kota, propinsi, kode_kpp, telpon, email, klu, jenis_wp, is_umkm, tgl_daftar, tgl_pkp, lat_npwp, lng_npwp, pemilik_nama, pemilik_nik, pemilik_jabatan, is_pic, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($data));
            $msg_text = "Menambah Profil WP Baru: " . $data['npwp'];
        }
        
        catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Manajemen WP', $msg_text);
        header("Location: profil_wp.php?npwp=" . $data['npwp'] . "&status=success");
        exit;
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger shadow-sm'>Gagal simpan WP: " . $e->getMessage() . "</div>";
    }
}

// 2. Aksi Tambah Pemilik / Pengurus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pemilik'])) {
    try {
        $npwp_ref = $_POST['npwp_perusahaan'];
        $p_nama = $_POST['p_nama'];
        $p_nik = $_POST['p_nik'];
        $p_jabatan = $_POST['p_jabatan'];
        $p_telpon = $_POST['p_telpon'];
        $p_email = $_POST['p_email'] ?? '';
        $p_saham = $_POST['p_saham'] ?: 0;

        $sql = "INSERT INTO daftar_pemilik (npwp_perusahaan, nama, nik, jabatan, telpon, email, nilai_saham) VALUES (?,?,?,?,?,?,?)";
        $db->prepare($sql)->execute([$npwp_ref, $p_nama, $p_nik, $p_jabatan, $p_telpon, $p_email, $p_saham]);
        
        catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Manajemen WP', "Menambah Pemilik/Pengurus ($p_nama) pada WP: $npwp_ref");
        header("Location: manajemen_wp.php?edit=$npwp_ref&status=owner_added");
        exit;
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger shadow-sm'>Gagal tambah pemilik: " . $e->getMessage() . "</div>";
    }
}

// 3. Aksi Hapus WP
if (isset($_GET['delete_wp'])) {
    $target = $_GET['delete_wp'];
    $db->prepare("DELETE FROM profil_wp WHERE npwp = ?")->execute([$target]);
    catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Manajemen WP', "Menghapus Profil WP: $target");
    header("Location: manajemen_wp.php?status=deleted");
    exit;
}

// 4. Aksi Hapus Pemilik
if (isset($_GET['del_pemilik'])) {
    $id_p = $_GET['del_pemilik'];
    $ref = $_GET['ref'];
    $db->prepare("DELETE FROM daftar_pemilik WHERE id = ?")->execute([$id_p]);
    header("Location: manajemen_wp.php?edit=$ref&status=owner_deleted");
    exit;
}

// 5. Ambil Data untuk Tampilan
$edit_wp = null;
$list_pemilik = [];
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT p.*, k.nama_klasifikasi_usaha FROM profil_wp p
        LEFT JOIN benchmark_klu k ON p.klu = k.klu
        WHERE npwp = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_wp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_wp) {
        $stmtP = $db->prepare("SELECT * FROM daftar_pemilik WHERE npwp_perusahaan = ?");
        $stmtP->execute([$_GET['edit']]);
        $list_pemilik = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        
        // Update npwp_aktif for the Back button if not provided in GET
        if (empty($npwp_aktif)) $npwp_aktif = $edit_wp['npwp'];
    }
}

$benchmarks = $db->query("SELECT klu, nama_klasifikasi_usaha FROM benchmark_klu")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Wajib Pajak - SHARP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { 
            --primary: #0f172a; 
            --accent: #3b82f6;
            --bg-subtle: #f8fafc;
        }
        body { background-color: #f1f5f9; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        .main-content { padding: 24px; transition: margin-left 0.3s; }
        @media (min-width: 992px) { .main-content { margin-left: 280px; } }
        
        .glass-card { background: white; border-radius: 16px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .form-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .form-control-sm, .form-select-sm { border-radius: 8px; padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; }
        .form-control:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1); }
        
        #map { height: 350px; border-radius: 16px; border: 1px solid #e2e8f0; z-index: 1; }
        .btn-gps { background-color: #ecfdf5; color: #059669; border: 1px solid #10b981; border-radius: 8px; }
        .btn-gps:hover { background-color: #10b981; color: white; }
        
        .section-title { font-size: 0.9rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }
        .section-title i { width: 18px; height: 18px; color: var(--accent); }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="profil_wp.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-sm btn-light border me-3 shadow-sm" title="Kembali">
                    <i data-lucide="arrow-left"></i>
                </a>
                <div>
                    <h4 class="fw-800 m-0">Taxpayer Management</h4>
                    <p class="text-muted small m-0">
                        <?php if ($edit_wp): ?>
                            Editing: <span class="fw-bold text-primary"><?= htmlspecialchars($edit_wp['nama']); ?></span> (<?= $edit_wp['npwp']; ?>)
                        <?php else: ?>
                            Registration of New Taxpayer
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <?php echo $message; ?>

        <form method="POST" id="formWP">
            <input type="hidden" name="is_edit" value="<?= $edit_wp ? '1' : '0'; ?>">
            
            <div class="row g-4">
                <!-- LEFT COLUMN: Identity & Tax Admin -->
                <div class="col-lg-7">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="section-title">
                            <i data-lucide="user-check"></i> Basic Identity & Administration
                        </div>    
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_umkm" id="umkm" <?= ($edit_wp['is_umkm'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label small fw-bold" for="umkm">Wajib Pajak UMKM</label>
                            </div>
                            
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">NPWP (15/16 Digit)</label>
                                <input type="text" name="npwp" class="form-control form-control-sm" value="<?= $edit_wp['npwp'] ?? ''; ?>" <?= $edit_wp ? 'readonly' : 'required'; ?> placeholder="00.000.000.0-000.000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Wajib Pajak</label>
                                <input type="text" name="nama" class="form-control form-control-sm" value="<?= $edit_wp['nama'] ?? ''; ?>" required placeholder="Nama sesuai SKT/SPPKP">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">KLU</label>
                                <input list="klu_list" name="klu" class="form-control form-control-sm" value="<?= $edit_wp['klu'] ?? ''; ?>" onchange="updateKlasifikasiUsaha()" placeholder="Ketik kode atau nama KLU...">
                                <datalist id="klu_list">
                                    <?php foreach($benchmarks as $b): ?>
                                        <option value="<?= $b['klu']; ?>"> - <?= $b['nama_klasifikasi_usaha']; ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Klasifikasi Usaha</label>
                                <input disabled name="nama_klasifikasi_usaha" class="form-control form-control-sm bg-light" value="<?= $edit_wp['nama_klasifikasi_usaha'] ?? ''; ?>" placeholder="Nama KLU akan muncul otomatis...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kode KPP</label>
                                <input type="text" name="kode_kpp" class="form-control form-control-sm" value="<?= $edit_wp['kode_kpp'] ?? ''; ?>" placeholder="Contoh: 001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jenis WP</label>
                                <select name="jenis_wp" class="form-select form-select-sm">
                                    <option value="Badan" <?= ($edit_wp['jenis_wp'] ?? '') == 'Badan' ? 'selected' : ''; ?>>Badan</option>
                                    <option value="OP" <?= ($edit_wp['jenis_wp'] ?? '') == 'OP' ? 'selected' : ''; ?>>Orang Pribadi</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tgl Daftar</label>
                                <input type="date" name="tgl_daftar" class="form-control form-control-sm" value="<?= $edit_wp['tgl_daftar'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tgl PKP</label>
                                <input type="date" name="tgl_pkp" class="form-control form-control-sm" value="<?= $edit_wp['tgl_pkp'] ?? ''; ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">No. Telpon</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i data-lucide="phone" style="width:14px;"></i></span>
                                    <input type="text" name="telpon" class="form-control" value="<?= $edit_wp['telpon'] ?? ''; ?>" placeholder="0812...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Alamat Email</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i data-lucide="mail" style="width:14px;"></i></span>
                                    <input type="email" name="email" class="form-control" value="<?= $edit_wp['email'] ?? ''; ?>" placeholder="email@example.com">
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <div class="p-3 bg-light rounded-3 border border-dashed">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="section-title mb-2" style="font-size: 0.8rem;">
                                        <i data-lucide="user"></i> PIC / Pemilik Utama
                                    </div>
                                    
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_pic" id="pic" <?= ($edit_wp['is_pic'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small fw-bold" for="pic">PIC Kooperatif</label>
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Nama PIC</label>
                                            <input type="text" name="pemilik_nama" class="form-control form-control-sm" value="<?= $edit_wp['pemilik_nama'] ?? ''; ?>" placeholder="Nama Lengkap">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">NIK PIC</label>
                                            <input type="text" name="pemilik_nik" class="form-control form-control-sm" value="<?= $edit_wp['pemilik_nik'] ?? ''; ?>" placeholder="16 Digit NIK">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Jabatan</label>
                                            <input type="text" name="pemilik_jabatan" class="form-control form-control-sm" value="<?= $edit_wp['pemilik_jabatan'] ?? ''; ?>" placeholder="Direktur/Pemilik">
                                        </div>
                                  
                                        
                                        
                                    </div>
                                </div>
                            </div>
                            <?php if($edit_wp): ?>
                <!-- Additional Owners Section -->
                <div class="glass-card p-4 mt-4">
                    <h6 class="fw-bold mb-3 d-flex align-items-center">
                        <i data-lucide="users" class="me-2 text-warning"></i> Daftar Pemilik & Pengurus Lainnya
                    </h6>
                    
                    <form method="POST" class="row g-2 mb-3 bg-light p-3 rounded-3 border">
                        <input type="hidden" name="npwp_perusahaan" value="<?= $edit_wp['npwp']; ?>">
                        <div class="row g-2 bg-light rounded-3 border border-dashed">
                        <div class="col-md-5">
                            <input type="text" name="p_nama" class="form-control form-control-sm" placeholder="Nama Lengkap">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="p_nik" class="form-control form-control-sm" placeholder="NIK">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="p_jabatan" class="form-control form-control-sm" placeholder="Jabatan">
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="p_telpon" class="form-control form-control-sm" placeholder="Telpon">
                        </div>
                        <div class="col-md-3">
                            <input type="email" name="p_email" class="form-control form-control-sm" placeholder="Email">
                        </div>
                        
                        <div class="col-md-3">
                            <input type="number" name="p_saham" class="form-control form-control-sm" placeholder="Saham (Rp)">
                        </div>
                    
                        <div class="col-md-3 align-self-end">
                            <button type="submit" name="add_pemilik" class="btn btn-warning btn-sm w-100 fw-bold">Tambah</button>
                        </div>
                        </div>
       
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr class="small text-uppercase fw-bold text-muted">
                                    <th>Nama / Jabatan</th>
                                    <th>Kontak / NIK</th>
                                    <th class="text-end">Saham (Rp)</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($list_pemilik as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($p['nama']); ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($p['jabatan']); ?></div>
                                    </td>
                                    <td>
                                        <div class="small">Telp.<?= $p['telpon']; ?> / <?= $p['email']; ?></div>
                                        <div class="text-muted smaller">NIK: <?= $p['nik']; ?></div>
                                    </td>
                                    <td class="text-end fw-bold text-primary"><?= number_format($p['nilai_saham'],0,',','.'); ?></td>
                                    <td class="text-center">
                                        <a href="?del_pemilik=<?= $p['id']; ?>&ref=<?= $edit_wp['npwp']; ?>" class="btn btn-xs btn-outline-danger" onclick="return confirm('Hapus pemilik ini?')">
                                            <i data-lucide="trash-2" style="width:14px;"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; if(empty($list_pemilik)) echo "<tr><td colspan='4' class='text-center py-4 text-muted small'>Belum ada daftar pengurus tambahan.</td></tr>"; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Address & Location -->
                <div class="col-lg-5">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="section-title m-0">
                                <i data-lucide="map-pin"></i> Address & Geolocation
                            </div>
                            <button type="button" class="btn btn-sm btn-gps fw-bold" onclick="getLocation(this)">
                                <i data-lucide="refresh-cw" class="me-1 inline" style="width:14px;"></i> Sync Map
                            </button>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label">Alamat Lengkap (Jalan/No/Blok)</label>
                                <textarea name="alamat" class="form-control form-control-sm" rows="2" required placeholder="Masukkan alamat lengkap..."><?= $edit_wp['alamat'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Provinsi</label>
                                <select name="propinsi" id="sel_prov" class="form-select form-select-sm" data-selected="<?= $edit_wp['propinsi'] ?? ''; ?>" required>
                                    <option value="">-- Pilih Provinsi --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kota / Kabupaten</label>
                                <select name="kota" id="sel_kota" class="form-select form-select-sm" data-selected="<?= $edit_wp['kota'] ?? ''; ?>" disabled required>
                                    <option value="">-- Pilih Kota --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kecamatan</label>
                                <select name="kecamatan" id="sel_kec" class="form-select form-select-sm" data-selected="<?= $edit_wp['kecamatan'] ?? ''; ?>" disabled required>
                                    <option value="">-- Pilih Kecamatan --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kelurahan / Desa</label>
                                <select name="kelurahan" id="sel_kel" class="form-select form-select-sm" data-selected="<?= $edit_wp['kelurahan'] ?? ''; ?>" disabled required>
                                    <option value="">-- Pilih Kelurahan --</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="lat_npwp" id="lat_npwp" class="form-control form-control-sm bg-light" value="<?= $edit_wp['lat_npwp'] ?? ''; ?>" >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="lng_npwp" id="lng_npwp" class="form-control form-control-sm bg-light" value="<?= $edit_wp['lng_npwp'] ?? ''; ?>" >
                            </div>

                            <div class="col-12 mt-3">
                                <div id="map"></div>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" name="save_wp" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                                    <i data-lucide="save" class="me-2" style="width:18px;"></i> Simpan 
                                </button>
                                <?php if($edit_wp): ?>
                                    <br><a href="profil_wp.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline-danger w-100"><i data-lucide="x" class="me-2" style="width:18px;"></i> Batal</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    lucide.createIcons();
    const API_URL = "https://www.emsifa.com/api-wilayah-indonesia/api";

    function updateKlasifikasiUsaha() {
        const kluInput = document.querySelector('input[name="klu"]');
        const namaKluInput = document.querySelector('input[name="nama_klasifikasi_usaha"]');
        const kluValue = kluInput.value.trim();
        const option = document.querySelector(`#klu_list option[value="${kluValue}"]`);
        if (option) {
            const displayText = option.textContent || option.innerText;
            namaKluInput.value = displayText.replace(/^\s*-\s*/, '').trim();
        } else {
            namaKluInput.value = '';
        }
    }
    // --- Geocoding and Map Functionality ---
    let mapInstance = null;
    let marker = null;

    function initMap(lat, lng, addressText = "") {
        lat = parseFloat(lat);
        lng = parseFloat(lng);
        
        if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) return;

        const mapDiv = document.getElementById('map');
        mapDiv.style.display = 'block';

        if (!mapInstance) {
            mapInstance = L.map('map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(mapInstance);
        } else {
            mapInstance.setView([lat, lng], 15);
        }

        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(mapInstance);
        }
        
        if (addressText) {
            marker.bindPopup(addressText).openPopup();
        }
        
        mapInstance.invalidateSize();
    }

    async function geocodeAddress(address) {
        const queryAddress = address.toLowerCase().includes('indonesia') ? address : `${address}, Indonesia`;
        const nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&countrycodes=id&limit=1&q=${encodeURIComponent(queryAddress)}`;

        try {
            const response = await fetch(nominatimUrl);
            const data = await response.json();
            if (data && data.length > 0) {
                return { 
                    lat: data[0].lat, 
                    lng: data[0].lon, 
                    display_name: data[0].display_name 
                };
            }
        } catch (error) {
            console.error('Geocoding error:', error);
        }
        return null;
    }

    async function getLocation(buttonElement = null) {
        if (buttonElement) {
            buttonElement.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Syncing...';
            buttonElement.disabled = true;
        }

        const alamat = document.querySelector('textarea[name="alamat"]').value;
        const kel = document.getElementById('sel_kel').value;
        const kec = document.getElementById('sel_kec').value;
        const kota = document.getElementById('sel_kota').value;
        const prov = document.getElementById('sel_prov').value;

        const fullAddress = [alamat, kel, kec, kota, prov].filter(x => x && x.length > 2).join(', ');

        if (fullAddress.length < 10) {
            if (buttonElement) {
                alert('Alamat belum lengkap untuk sinkronisasi peta.');
                resetButton(buttonElement);
            }
            return;
        }

        const result = await geocodeAddress(fullAddress);
        if (result) {
            document.getElementById('lat_npwp').value = result.lat;
            document.getElementById('lng_npwp').value = result.lng;
            initMap(result.lat, result.lng, result.display_name);
        } else if (buttonElement) {
            alert('Lokasi tidak ditemukan. Coba perjelas alamat.');
        }

        if (buttonElement) resetButton(buttonElement);
    }

    function resetButton(btn) {
        btn.innerHTML = '<i data-lucide="refresh-cw" class="me-1 inline" style="width:14px;"></i> Sync Map';
        btn.disabled = false;
        lucide.createIcons();
    }

    // Fungsi Load Wilayah
    async function loadWilayah(endpoint, target, placeholder, selectedValue = "") {
        try {
            const res = await fetch(`${API_URL}/${endpoint}.json`);
            const data = await res.json();
            let html = `<option value="">-- ${placeholder} --</option>`;
            let foundId = null;

            data.forEach(item => {
                const isSelected = item.name.toUpperCase() === (selectedValue || "").toUpperCase();
                if (isSelected) foundId = item.id;
                html += `<option value="${item.name}" data-id="${item.id}" ${isSelected ? 'selected' : ''}>${item.name}</option>`;
            });

            target.innerHTML = html;
            target.disabled = false;
            return foundId; 
        } catch (e) {
            console.error("Gagal load wilayah:", endpoint);
            return null;
        }
    }

    document.addEventListener('DOMContentLoaded', async () => {
        const provSelect = document.getElementById('sel_prov');
        const kotaSelect = document.getElementById('sel_kota');
        const kecSelect = document.getElementById('sel_kec');
        const kelSelect = document.getElementById('sel_kel');

        // Initial Load
        const provId = await loadWilayah('provinces', provSelect, 'Pilih Provinsi', provSelect.dataset.selected);
        if (provId) {
            const kotaId = await loadWilayah(`regencies/${provId}`, kotaSelect, 'Pilih Kota', kotaSelect.dataset.selected);
            if (kotaId) {
                const kecId = await loadWilayah(`districts/${kotaId}`, kecSelect, 'Pilih Kecamatan', kecSelect.dataset.selected);
                if (kecId) {
                    await loadWilayah(`villages/${kecId}`, kelSelect, 'Pilih Kelurahan', kelSelect.dataset.selected);
                }
            }
        }

        const initialLat = document.getElementById('lat_npwp').value;
        const initialLng = document.getElementById('lng_npwp').value;
        if (initialLat && initialLng) {
            initMap(initialLat, initialLng, "Lokasi Terdaftar");
        }

        // Change Handlers
        provSelect.onchange = async function() {
            const id = this.options[this.selectedIndex].dataset.id;
            kotaSelect.innerHTML = '<option value="">Loading...</option>';
            kecSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
            kelSelect.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
            if(id) await loadWilayah(`regencies/${id}`, kotaSelect, 'Pilih Kota');
            getLocation();
        };

        kotaSelect.onchange = async function() {
            const id = this.options[this.selectedIndex].dataset.id;
            kecSelect.innerHTML = '<option value="">Loading...</option>';
            kelSelect.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
            if(id) await loadWilayah(`districts/${id}`, kecSelect, 'Pilih Kecamatan');
            getLocation();
        };

        kecSelect.onchange = async function() {
            const id = this.options[this.selectedIndex].dataset.id;
            kelSelect.innerHTML = '<option value="">Loading...</option>';
            if(id) await loadWilayah(`villages/${id}`, kelSelect, 'Pilih Kelurahan');
            getLocation();
        };

        kelSelect.onchange = () => getLocation();
    });
</script>
</body>
</html>