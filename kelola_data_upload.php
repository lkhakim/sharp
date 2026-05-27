<?php
require_once 'config.php';
session_start();

// Proteksi Halaman
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

//$db = getConnection();
$message = "";

// Parameter Global
$npwp_aktif  = $_GET['npwp'] ?? '';
$tahun_aktif = $_GET['tahun'] ?? date('Y') - 1;
$modul_aktif = $_GET['modul'] ?? 'spt';

if (empty($npwp_aktif)) {
    header("Location: manajemen_wp.php");
    exit;
}

// Ambil Nama WP untuk Header
$stmtWp = $db->prepare("SELECT nama FROM profil_wp WHERE npwp = ?");
$stmtWp->execute([$npwp_aktif]);
$wp = $stmtWp->fetch();

// Mapping tipe untuk template
$template_type = $modul_aktif;
if($modul_aktif == 'akun') $template_type = 'akun';

if (isset($_GET['delete_id']) && isset($_GET['table'])) {
    try {
        $table = $_GET['table'];
        $id = $_GET['delete_id'];
        
        // Whitelist tabel untuk keamanan
        $allowed_tables = ['spt_tahunan', 'mutasi_bank', 'mapping_akun', 'bukti_potong', 'setoran_pajak', 'faktur_pajak', 'data_ilap'];
        if (in_array($table, $allowed_tables)) {
            $stmt = $db->prepare("DELETE FROM $table WHERE id = ? AND npwp = ?");
            $stmt->execute([$id, $npwp_aktif]);
            
            catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Kelola Data', "Menghapus data di tabel $table ID: $id (NPWP: $npwp_aktif)");
            header("Location: kelola_data_upload.php?npwp=$npwp_aktif&tahun=$tahun_aktif&modul=$modul_aktif&status=deleted");
            exit;
        }
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menghapus data: " . $e->getMessage() . "</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. SAVE SPT TAHUNAN
        if (isset($_POST['save_spt'])) {
            $id = $_POST['id'] ?? '';
            $data = [
                $npwp_aktif, $tahun_aktif, $_POST['peredaran_usaha'], $_POST['pembelian'], 
                $_POST['gaji'], $_POST['pajak_terutang'], $_SESSION['user_id']
            ];
            
            if (empty($id)) {
                $sql = "INSERT INTO spt_tahunan (npwp, tahun, peredaran_usaha, pembelian, gaji, pajak_terutang, created_by) VALUES (?,?,?,?,?,?,?)";
            } else {
                $sql = "UPDATE spt_tahunan SET npwp=?, tahun=?, peredaran_usaha=?, pembelian=?, gaji=?, pajak_terutang=?, created_by=? WHERE id=?";
                $data[] = $id;
            }
            $db->prepare($sql)->execute($data);
            $msg_text = "Menyimpan/Update SPT Tahunan WP: $npwp_aktif Tahun: $tahun_aktif";
        }

        // 2. SAVE MUTASI BANK
        if (isset($_POST['save_bank'])) {
            $id = $_POST['id'] ?? '';
            $data = [
                $npwp_aktif, $tahun_aktif, $_POST['tanggal'], $_POST['keterangan'], 
                $_POST['jenis'], $_POST['nominal'], $_POST['kategori']
            ];
            
            if (empty($id)) {
                $sql = "INSERT INTO mutasi_bank (npwp, tahun, tanggal, keterangan, jenis, nominal, kategori) VALUES (?,?,?,?,?,?,?)";
            } else {
                $sql = "UPDATE mutasi_bank SET npwp=?, tahun=?, tanggal=?, keterangan=?, jenis=?, nominal=?, kategori=? WHERE id=?";
                $data[] = $id;
            }
            $db->prepare($sql)->execute($data);
            $msg_text = "Menyimpan/Update Mutasi Bank WP: $npwp_aktif";
        }

        // 3. SAVE MAPPING AKUN
        if (isset($_POST['save_akun'])) {
            $id = $_POST['id'] ?? '';
            $data = [
                $npwp_aktif, $tahun_aktif, $_POST['kode_akun'], $_POST['nama_akun'], 
                $_POST['jenis'], $_POST['nominal']
            ];
            
            if (empty($id)) {
                $sql = "INSERT INTO mapping_akun (npwp, tahun, kode_akun, nama_akun, jenis, nominal) VALUES (?,?,?,?,?,?)";
            } else {
                $sql = "UPDATE mapping_akun SET npwp=?, tahun=?, kode_akun=?, nama_akun=?, jenis=?, nominal=? WHERE id=?";
                $data[] = $id;
            }
            $db->prepare($sql)->execute($data);
            $msg_text = "Menyimpan/Update Mapping Akun WP: $npwp_aktif";
        }

        // 4. SAVE BUKTI POTONG
        if (isset($_POST['save_bupot'])) {
            $id = $_POST['id'] ?? '';
            $data = [
                $npwp_aktif, $tahun_aktif, $_POST['no_bupot'], $_POST['kode_objek_pajak'], 
                $_POST['nama_lawan'], $_POST['dpp_bupot'], $_POST['nilai_pph'], $_POST['sifat_bupot']
            ];
            
            if (empty($id)) {
                $sql = "INSERT INTO bukti_potong (npwp, tahun, no_bupot, kode_objek_pajak, nama_lawan, dpp_bupot, nilai_pph, sifat_bupot) VALUES (?,?,?,?,?,?,?,?)";
            } else {
                $sql = "UPDATE bukti_potong SET npwp=?, tahun=?, no_bupot=?, kode_objek_pajak=?, nama_lawan=?, dpp_bupot=?, nilai_pph=?, sifat_bupot=? WHERE id=?";
                $data[] = $id;
            }
            $db->prepare($sql)->execute($data);
            $msg_text = "Menyimpan/Update Bukti Potong WP: $npwp_aktif";
        }

        // 5. SAVE SETORAN PAJAK
        if (isset($_POST['save_setoran'])) {
            $id = $_POST['id'] ?? '';
            $data = [
                $npwp_aktif, $tahun_aktif, $_POST['ntpn'], $_POST['jenis_pajak'], 
                $_POST['map'], $_POST['kjs'], $_POST['tgl_setor'], $_POST['nilai_setoran']
            ];
            
            if (empty($id)) {
                $sql = "INSERT INTO setoran_pajak (npwp, tahun, ntpn, jenis_pajak, map, kjs, tgl_setor, nilai_setoran) VALUES (?,?,?,?,?,?,?,?)";
            } else {
                $sql = "UPDATE setoran_pajak SET npwp=?, tahun=?, ntpn=?, jenis_pajak=?, map=?, kjs=?, tgl_setor=?, nilai_setoran=? WHERE id=?";
                $data[] = $id;
            }
            $db->prepare($sql)->execute($data);
            $msg_text = "Menyimpan/Update Setoran Pajak WP: $npwp_aktif";
        }

        // 6. SAVE DATA ILAP
        if (isset($_POST['save_ilap'])) {
            $id = $_POST['id'] ?? '';
            require_once 'functions_upload.php';
            $kat_data = klasifikasiIlap($_POST['keterangan'] ?? '');
            
            $data = [
                $npwp_aktif, $tahun_aktif, $_POST['tanggal'], $_POST['kategori'], 
                $_POST['jenis_saldo'], $_POST['nominal'], $_POST['saldo'], 
                $kat_data, 'MANUAL', $_SESSION['user_id']
            ];
            
            if (empty($id)) {
                $sql = "INSERT INTO data_ilap (npwp, tahun, tanggal, kategori, jenis_saldo, nominal, saldo, kategori_data, sumber_data, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)";
            } else {
                $sql = "UPDATE data_ilap SET npwp=?, tahun=?, tanggal=?, kategori=?, jenis_saldo=?, nominal=?, saldo=?, kategori_data=?, sumber_data=?, created_by=? WHERE id=?";
                $data[] = $id;
            }
            $db->prepare($sql)->execute($data);
            $msg_text = "Menyimpan/Update Data ILAP WP: $npwp_aktif";
        }

        catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Input Data', $msg_text);
        header("Location: kelola_data_upload.php?npwp=$npwp_aktif&tahun=$tahun_aktif&modul=$modul_aktif&status=success");
        exit;
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Kesalahan Database: " . $e->getMessage() . "</div>";
    }
}

$data_list = [];
if ($modul_aktif == 'spt') {
    $stmt = $db->prepare("SELECT * FROM spt_tahunan WHERE npwp = ? AND tahun = ?");
} elseif ($modul_aktif == 'bank') {
    $stmt = $db->prepare("SELECT * FROM mutasi_bank WHERE npwp = ? AND tahun = ? ORDER BY tanggal DESC");
} elseif ($modul_aktif == 'akun') {
    $stmt = $db->prepare("SELECT * FROM mapping_akun WHERE npwp = ? AND tahun = ? ORDER BY kode_akun ASC");
} elseif ($modul_aktif == 'bupot') {
    $stmt = $db->prepare("SELECT * FROM bukti_potong WHERE npwp = ? AND tahun = ? ORDER BY created_at DESC");
} elseif ($modul_aktif == 'setoran') {
    $stmt = $db->prepare("SELECT * FROM setoran_pajak WHERE npwp = ? AND tahun = ? ORDER BY tgl_setor DESC");
} elseif ($modul_aktif == 'ilap') {
    $stmt = $db->prepare("SELECT * FROM data_ilap WHERE npwp = ? AND tahun = ? ORDER BY tanggal DESC");
}
$stmt->execute([$npwp_aktif, $tahun_aktif]);
$data_list = $stmt->fetchAll();

function formatRp($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data - <?= htmlspecialchars($wp['nama'] ?? 'NPWP ' . $npwp_aktif); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; transition: margin-left 0.3s; }
        body.sidebar-mini .main-content { margin-left: 75px; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding-bottom: 90px; } }
        
        .header-section { background: white; border-radius: 12px; border-left: 5px solid var(--primary); box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .nav-pills-custom .nav-link { color: #64748b; font-weight: 600; border-radius: 8px; margin-right: 5px; }
        .nav-pills-custom .nav-link.active { background-color: var(--primary); color: white; }
        .table-custom { font-size: 0.85rem; }
        .card-table { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="sidebar-mini">

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        
        <!-- Header Info -->
        <div class="header-section p-3 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold m-0 text-primary"><?= htmlspecialchars($wp['nama']); ?></h5>
                <small class="text-muted">NPWP: <?= $npwp_aktif; ?> | Mengelola Data Input Analisa</small>
            </div>
            <a href="profil_wp.php?npwp=<?= $npwp_aktif; ?>" class="btn btn-outline-secondary btn-sm">
                <i data-lucide="arrow-left" class="inline me-1" style="width:14px;"></i> Kembali ke Profil
            </a>
        </div>
        <select class="form-select form-select-sm w-auto" onchange="window.location.href='?npwp=<?= $npwp_aktif ?>&modul=<?= $modul_aktif ?>&tahun=' + this.value">
                            <?php for($y=date('Y'); $y>=2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $tahun_aktif==$y?'selected':'' ?>>Tahun <?= $y ?></option>
                            <?php endfor; ?>
        </select>

        <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i data-lucide="check-circle" class="inline me-2"></i> Data berhasil disimpan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter & Navigation -->
        <div class="card p-3 mb-4 border-0 shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <ul class="nav nav-pills nav-pills-custom">
                        <li class="nav-item">
                            <a class="nav-link <?= $modul_aktif=='spt'?'active':'' ?>" href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&modul=spt">SPT Tahunan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $modul_aktif=='bank'?'active':'' ?>" href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&modul=bank">Mutasi Bank</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $modul_aktif=='akun'?'active':'' ?>" href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&modul=akun">Mapping Akun</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $modul_aktif=='bupot'?'active':'' ?>" href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&modul=bupot">Bukti Potong</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $modul_aktif=='setoran'?'active':'' ?>" href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&modul=setoran">Setoran SSP</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $modul_aktif=='ilap'?'active':'' ?>" href="?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>&modul=ilap">Data ILAP</a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="d-flex gap-2 justify-content-md-end">
                        
                        <!-- Tombol Download Template -->
                        <a href="manajemen_bupot.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline-success btn-sm fw-bold">
                            <i data-lucide="download" class="inline me-1" style="width:14px;"></i> BUPOT
                        </a>
                        <a href="manajemen_setoran.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline-success btn-sm fw-bold">
                            <i data-lucide="download" class="inline me-1" style="width:14px;"></i> SETORAN
                        </a>
                        <a href="manajemen_bank.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline-success btn-sm fw-bold">
                            <i data-lucide="download" class="inline me-1" style="width:14px;"></i> BANK
                        </a>
                        <a href="manajemen_faktur.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline-success btn-sm fw-bold">
                            <i data-lucide="download" class="inline me-1" style="width:14px;"></i> FAKTUR
                        </a>
                        <a href="manajemen_akun.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline-success btn-sm fw-bold">
                            <i data-lucide="download" class="inline me-1" style="width:14px;"></i> AKUN
                        </a>
                        <a href="manajemen_spt.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline-success btn-sm fw-bold">
                            <i data-lucide="download" class="inline me-1" style="width:14px;"></i> SPT
                        </a>
                        <!--
                        <button class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAdd">
                            <i data-lucide="plus" class="inline me-1" style="width:14px;"></i> Tambah Data
                        </button>
                            -->
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-table bg-white overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom">
                    <thead class="table-light">
                        <?php if($modul_aktif == 'spt'): ?>
                            <tr>
                                <th class="ps-3">Tahun</th>
                                <th class="text-end">Peredaran Usaha</th>
                                <th class="text-end">Pembelian</th>
                                <th class="text-end">Biaya Gaji</th>
                                <th class="text-end">Pajak Terutang</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        <?php elseif($modul_aktif == 'bank'): ?>
                            <tr>
                                <th class="ps-3">Tanggal</th>
                                <th>Keterangan</th>
                                <th>Jenis</th>
                                <th>Kategori</th>
                                <th class="text-end">Nominal</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        <?php elseif($modul_aktif == 'akun'): ?>
                            <tr>
                                <th class="ps-3">Kode Akun</th>
                                <th>Nama Akun</th>
                                <th>Jenis</th>
                                <th class="text-end">Nominal</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        <?php elseif($modul_aktif == 'bupot'): ?>
                            <tr>
                                <th class="ps-3">No. Bupot</th>
                                <th>Kode Objek</th>
                                <th>Lawan Transaksi</th>
                                <th class="text-end">DPP</th>
                                <th class="text-end">PPh</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        <?php elseif($modul_aktif == 'setoran'): ?>
                            <tr>
                                <th class="ps-3">NTPN</th>
                                <th>Jenis Pajak</th>
                                <th>MAP-KJS</th>
                                <th>Tgl Setor</th>
                                <th class="text-end">Nilai Setoran</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        <?php elseif($modul_aktif == 'ilap'): ?>
                            <tr>
                                <th class="ps-3">Tanggal & Deskripsi</th>
                                <th>Kategori Sumber</th>
                                <th>Jenis Saldo</th>
                                <th class="text-end">Nominal</th>
                                <th>Klasifikasi Data</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php if(empty($data_list)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada data untuk kriteria ini.</td></tr>
                        <?php else: foreach($data_list as $row): ?>
                            <tr>
                                <?php if($modul_aktif == 'spt'): ?>
                                    <td class="ps-3 fw-bold"><?= $row['tahun'] ?></td>
                                    <td class="text-end"><?= formatRp($row['peredaran_usaha']) ?></td>
                                    <td class="text-end"><?= formatRp($row['pembelian']) ?></td>
                                    <td class="text-end"><?= formatRp($row['gaji']) ?></td>
                                    <td class="text-end fw-bold text-danger"><?= formatRp($row['pajak_terutang']) ?></td>
                                <?php elseif($modul_aktif == 'bank'): ?>
                                    <td class="ps-3"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                                    <td class="text-truncate" style="max-width: 250px;"><?= htmlspecialchars($row['keterangan']) ?></td>
                                    <td><span class="badge <?= $row['jenis']=='KREDIT'?'bg-success':'bg-danger' ?>"><?= $row['jenis'] ?></span></td>
                                    <td><span class="badge bg-light text-dark border"><?= $row['kategori'] ?></span></td>
                                    <td class="text-end fw-bold"><?= formatRp($row['nominal']) ?></td>
                                <?php elseif($modul_aktif == 'akun'): ?>
                                    <td class="ps-3 fw-bold"><?= $row['kode_akun'] ?></td>
                                    <td><?= htmlspecialchars($row['nama_akun']) ?></td>
                                    <td><?= $row['jenis'] ?></td>
                                    <td class="text-end fw-bold"><?= formatRp($row['nominal']) ?></td>
                                <?php elseif($modul_aktif == 'bupot'): ?>
                                    <td class="ps-3 fw-bold text-primary"><?= $row['no_bupot'] ?></td>
                                    <td><small><?= $row['kode_objek_pajak'] ?></small></td>
                                    <td><?= htmlspecialchars($row['nama_lawan']) ?></td>
                                    <td class="text-end"><?= formatRp($row['dpp_bupot']) ?></td>
                                    <td class="text-end fw-bold text-danger"><?= formatRp($row['nilai_pph']) ?></td>
                                <?php elseif($modul_aktif == 'setoran'): ?>
                                    <td class="ps-3 fw-bold text-success"><?= $row['ntpn'] ?></td>
                                    <td><?= $row['jenis_pajak'] ?></td>
                                    <td><?= $row['map'] ?>-<?= $row['kjs'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['tgl_setor'])) ?></td>
                                    <td class="text-end fw-bold"><?= formatRp($row['nilai_setoran']) ?></td>
                                <?php elseif($modul_aktif == 'ilap'): ?>
                                    <td class="ps-3">
                                        <div class="fw-bold"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                                        <div class="text-muted small"><?= $row['kategori_data'] ?></div>
                                    </td>
                                    <td><?= $row['kategori'] ?></td>
                                    <td><span class="badge <?= $row['jenis_saldo']=='KREDIT'?'bg-success':'bg-danger' ?>"><?= $row['jenis_saldo'] ?></span></td>
                                    <td class="text-end fw-bold"><?= formatRp($row['nominal']) ?></td>
                                    <td><?= $row['kategori_data'] ?></td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)"><i data-lucide="edit-2" style="width:14px;"></i></button>
                                        <?php 
                                            $table_name = '';
                                            if($modul_aktif == 'spt') $table_name = 'spt_tahunan';
                                            elseif($modul_aktif == 'bank') $table_name = 'mutasi_bank';
                                            elseif($modul_aktif == 'akun') $table_name = 'mapping_akun';
                                            elseif($modul_aktif == 'bupot') $table_name = 'bukti_potong';
                                            elseif($modul_aktif == 'setoran') $table_name = 'setoran_pajak';
                                            elseif($modul_aktif == 'setoran') $table_name = 'setoran_pajak';
                                            elseif($modul_aktif == 'ilap') $table_name = 'data_ilap';
                                            ?>
                                        <a href="?npwp=<?= $npwp_aktif ?>&modul=<?= $modul_aktif ?>&tahun=<?= $tahun_aktif ?>&delete_id=<?= $row['id'] ?>&table=<?= $table_name ?>" 
                                           class="btn btn-outline-danger" onclick="return confirm('Hapus baris data ini?')">
                                           <i data-lucide="trash-2" style="width:14px;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- MODAL ADD/EDIT -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold" id="modalTitle">Tambah Data <?= strtoupper($modul_aktif) ?></h6>
                <button type="button" class="btn btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="row_id">
                <div class="modal-body">
                    <?php if($modul_aktif == 'spt'): ?>
                        <div class="mb-2"><label class="small fw-bold">Peredaran Usaha</label><input type="number" name="peredaran_usaha" id="f_peredaran" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Pembelian</label><input type="number" name="pembelian" id="f_pembelian" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Biaya Gaji</label><input type="number" name="gaji" id="f_gaji" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Pajak Terutang</label><input type="number" name="pajak_terutang" id="f_pajak" class="form-control" required></div>
                        <input type="hidden" name="save_spt" value="1">
                    
                    <?php elseif($modul_aktif == 'bank'): ?>
                        <div class="mb-2"><label class="small fw-bold">Tanggal</label><input type="date" name="tanggal" id="f_tanggal" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Keterangan</label><textarea name="keterangan" id="f_keterangan" class="form-control" required></textarea></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="small fw-bold">Jenis</label><select name="jenis" id="f_jenis" class="form-select"><option value="KREDIT">KREDIT</option><option value="DEBIT">DEBIT</option></select></div>
                            <div class="col-6"><label class="small fw-bold">Kategori</label><select name="kategori" id="f_kategori" class="form-select"><option value="PENJUALAN">PENJUALAN</option><option value="PEMBELIAN">PEMBELIAN</option><option value="GAJI">GAJI</option><option value="OPERASIONAL">OPERASIONAL</option><option value="LAINNYA">LAINNYA</option></select></div>
                        </div>
                        <div class="mt-2"><label class="small fw-bold">Nominal</label><input type="number" name="nominal" id="f_nominal" class="form-control" required></div>
                        <input type="hidden" name="save_bank" value="1">

                    <?php elseif($modul_aktif == 'akun'): ?>
                        <div class="mb-2"><label class="small fw-bold">Kode Akun</label><input type="text" name="kode_akun" id="f_kode" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Nama Akun</label><input type="text" name="nama_akun" id="f_nama" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Jenis Saldo</label><select name="jenis" id="f_jenis_akun" class="form-select"><option value="DEBIT">DEBIT</option><option value="KREDIT">KREDIT</option></select></div>
                        <div class="mb-2"><label class="small fw-bold">Nominal</label><input type="number" name="nominal" id="f_nominal_akun" class="form-control" required></div>
                        <input type="hidden" name="save_akun" value="1">

                    <?php elseif($modul_aktif == 'bupot'): ?>
                        <div class="mb-2"><label class="small fw-bold">No. Bupot</label><input type="text" name="no_bupot" id="f_no_bupot" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Kode Objek</label><input type="text" name="kode_objek_pajak" id="f_objek" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Lawan Transaksi</label><input type="text" name="nama_lawan" id="f_lawan" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="small fw-bold">DPP</label><input type="number" name="dpp_bupot" id="f_dpp" class="form-control" required></div>
                            <div class="col-6"><label class="small fw-bold">PPh</label><input type="number" name="nilai_pph" id="f_pph" class="form-control" required></div>
                        </div>
                        <div class="mt-2"><label class="small fw-bold">Sifat</label><select name="sifat_bupot" id="f_sifat" class="form-select"><option value="Tidak Final">Tidak Final</option><option value="Final">Final</option></select></div>
                        <input type="hidden" name="save_bupot" value="1">

                    <?php elseif($modul_aktif == 'setoran'): ?>
                        <div class="mb-2"><label class="small fw-bold">NTPN</label><input type="text" name="ntpn" id="f_ntpn" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Jenis Pajak</label><input type="text" name="jenis_pajak" id="f_jenis_pajak" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="small fw-bold">MAP</label><input type="text" name="map" id="f_map" class="form-control" required></div>
                            <div class="col-6"><label class="small fw-bold">KJS</label><input type="text" name="kjs" id="f_kjs" class="form-control" required></div>
                        </div>
                        <div class="mt-2"><label class="small fw-bold">Tgl Setor</label><input type="date" name="tgl_setor" id="f_tgl_setor" class="form-control" required></div>
                        <div class="mt-2"><label class="small fw-bold">Nilai Setoran</label><input type="number" name="nilai_setoran" id="f_nilai_setoran" class="form-control" required></div>
                        <input type="hidden" name="save_setoran" value="1">

                    <?php elseif($modul_aktif == 'ilap'): ?>
                        <div class="mb-2"><label class="small fw-bold">Tanggal</label><input type="date" name="tanggal" id="f_tgl_ilap" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">Keterangan</label><textarea name="keterangan" id="f_ket_ilap" class="form-control" required></textarea></div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="small fw-bold">Kategori Sumber</label>
                                <select name="kategori" id="f_kat_ilap" class="form-select">
                                    <option value="INSTANSI">INSTANSI</option>
                                    <option value="LEMBAGA">LEMBAGA</option>
                                    <option value="ASOSIASI">ASOSIASI</option>
                                    <option value="PIHAK_LAIN">PIHAK LAIN</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold">Jenis Saldo</label>
                                <select name="jenis_saldo" id="f_jns_ilap" class="form-select">
                                    <option value="KREDIT">KREDIT (In)</option>
                                    <option value="DEBIT">DEBIT (Out)</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mt-2">
                            <div class="col-6"><label class="small fw-bold">Nominal</label><input type="number" name="nominal" id="f_nom_ilap" class="form-control" required></div>
                            <div class="col-6"><label class="small fw-bold">Saldo</label><input type="number" name="saldo" id="f_sld_ilap" class="form-control"></div>
                        </div>
                        <input type="hidden" name="save_ilap" value="1">
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    const modal = new bootstrap.Modal(document.getElementById('modalAdd'));

    function editRow(data) {
        document.getElementById('modalTitle').innerText = 'Edit Data <?= strtoupper($modul_aktif) ?>';
        document.getElementById('row_id').value = data.id;

        <?php if($modul_aktif == 'spt'): ?>
            document.getElementById('f_peredaran').value = data.peredaran_usaha;
            document.getElementById('f_pembelian').value = data.pembelian;
            document.getElementById('f_gaji').value = data.gaji;
            document.getElementById('f_pajak').value = data.pajak_terutang;
        <?php elseif($modul_aktif == 'bank'): ?>
            document.getElementById('f_tanggal').value = data.tanggal;
            document.getElementById('f_keterangan').value = data.keterangan;
            document.getElementById('f_jenis').value = data.jenis;
            document.getElementById('f_kategori').value = data.kategori;
            document.getElementById('f_nominal').value = data.nominal;
        <?php elseif($modul_aktif == 'akun'): ?>
            document.getElementById('f_kode').value = data.kode_akun;
            document.getElementById('f_nama').value = data.nama_akun;
            document.getElementById('f_jenis_akun').value = data.jenis;
            document.getElementById('f_nominal_akun').value = data.nominal;
        <?php elseif($modul_aktif == 'bupot'): ?>
            document.getElementById('f_no_bupot').value = data.no_bupot;
            document.getElementById('f_objek').value = data.kode_objek_pajak;
            document.getElementById('f_lawan').value = data.nama_lawan;
            document.getElementById('f_dpp').value = data.dpp_bupot;
            document.getElementById('f_pph').value = data.nilai_pph;
            document.getElementById('f_sifat').value = data.sifat_bupot;
        <?php elseif($modul_aktif == 'setoran'): ?>
            document.getElementById('f_ntpn').value = data.ntpn;
            document.getElementById('f_jenis_pajak').value = data.jenis_pajak;
            document.getElementById('f_map').value = data.map;
            document.getElementById('f_kjs').value = data.kjs;
            document.getElementById('f_tgl_setor').value = data.tgl_setor;
            document.getElementById('f_nilai_setoran').value = data.nilai_setoran;
        <?php elseif($modul_aktif == 'ilap'): ?>
            document.getElementById('f_tgl_ilap').value = data.tanggal;
            document.getElementById('f_ket_ilap').value = data.keterangan || data.kategori_data;
            document.getElementById('f_kat_ilap').value = data.kategori;
            document.getElementById('f_jns_ilap').value = data.jenis_saldo;
            document.getElementById('f_nom_ilap').value = data.nominal;
            document.getElementById('f_sld_ilap').value = data.saldo;
        <?php endif; ?>
          
        modal.show();
    }

    // Reset ID when modal is closed
    document.getElementById('modalAdd').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').innerText = 'Tambah Data <?= strtoupper($modul_aktif) ?>';
        document.getElementById('row_id').value = '';
        document.querySelector('form').reset();
    });
</script>
</body>
</html>