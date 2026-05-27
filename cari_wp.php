<?php
require_once 'config.php';
session_start();

// Proteksi akses
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";

// Aksi Hapus WP
if (isset($_GET['delete_wp'])) {
    $target = $_GET['delete_wp'];
    $db->prepare("DELETE FROM profil_wp WHERE npwp = ?")->execute([$target]);
    catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'], 'Master File WP', "Menghapus Profil WP: $target");
    header("Location: cari_wp.php?status=deleted");
    exit;
}

// Ambil Data untuk Tampilan
$all_wp = $db->query("SELECT * FROM profil_wp ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$benchmarks = $db->query("SELECT klu, nama_klasifikasi_usaha FROM benchmark_klu")->fetchAll(PDO::FETCH_ASSOC);
$benchmarks_klu = array_column($benchmarks, 'nama_klasifikasi_usaha', 'klu');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master File Wajib Pajak - SHARP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
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
        .table-mini { font-size: 0.75rem; }
        .fw-800 { font-weight: 800; }
        
        .search-container { position: relative; }
        .search-container i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 16px; }
        .search-input { padding-left: 40px !important; border-radius: 10px; border: 1px solid #e2e8f0; }
        
        .wp-row:hover { background-color: #f8fafc; cursor: pointer; }
        .badge-soft { padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.7rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-800 m-0">Master File Wajib Pajak</h3>
                <p class="text-muted">Manajemen basis data dan pencarian profil Wajib Pajak</p>
            </div>
            <a href="manajemen_wp.php" class="btn btn-primary shadow-sm fw-bold px-4">
                <i data-lucide="user-plus" class="me-2"></i> Tambah WP Baru
            </a>
        </div>

        <?php if(isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm glass-card border-0 mb-4" role="alert">
                <i data-lucide="check-circle" class="me-2"></i> Data Wajib Pajak berhasil dihapus.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Card -->
        <div class="glass-card p-4 mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-6 col-lg-4">
                    <div class="search-container">
                        <i data-lucide="search"></i>
                        <input type="text" id="searchWP" class="form-control search-input" placeholder="Cari Nama, NPWP, atau Alamat...">
                    </div>
                </div>
                <div class="col-md-6 col-lg-8 text-md-end">
                    <span class="text-muted small">Total: <strong><?= count($all_wp) ?></strong> Wajib Pajak terdaftar</span>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="glass-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="wpTable">
                    <thead class="bg-light">
                        <tr class="text-muted small fw-700 text-uppercase">
                            <th class="ps-4 py-3">Wajib Pajak</th>
                            <th class="py-3">KLU / Klasifikasi</th>
                            <th class="py-3">Jenis / Status</th>
                            <th class="py-3">Wilayah & Alamat</th>
                            <th class="pe-4 py-3 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_wp as $row): ?>
                            <tr class="wp-row" onclick="window.location='profil_wp.php?npwp=<?= $row['npwp'] ?>'">
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($row['nama']); ?></div>
                                    <div class="small text-muted fw-500"><?= $row['npwp']; ?></div>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-blue-soft text-accent mb-1" style="background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe;"><?= $row['klu']; ?></span>
                                    <div class="small text-muted text-truncate" style="max-width: 250px;">
                                        <?= $benchmarks_klu[$row['klu']] ?? 'KLU tidak terdaftar'; ?>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="mb-1">
                                        <?php if($row['jenis_wp'] == 'OP'): ?>
                                            <span class="badge-soft text-info bg-info-subtle" style="background: #e0f2fe; color: #0369a1;">Orang Pribadi</span>
                                        <?php else: ?>
                                            <span class="badge-soft text-success bg-success-subtle" style="background: #dcfce7; color: #15803d;">Badan Usaha</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($row['is_umkm']): ?>
                                        <span class="badge-soft text-warning bg-warning-subtle" style="background: #fef9c3; color: #a16207;">UMKM</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <div class="small fw-500" style="line-height:1.4;">
                                        <i data-lucide="map-pin" class="me-1 inline-block" style="width:12px; vertical-align: middle;"></i>
                                        <?= $row['kota']; ?>, <?= $row['propinsi']; ?>
                                        <div class="text-muted smaller"><?= htmlspecialchars($row['alamat']); ?></div>
                                    </div>
                                </td>
                                <td class="pe-4 py-3 text-end" onclick="event.stopPropagation()">
                                    <div class="btn-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                        <a href="profil_wp.php?npwp=<?= urlencode($row['npwp']); ?>" class="btn btn-white btn-sm border" title="Lihat Profil">
                                            <i data-lucide="external-link" style="width:14px; color: #64748b;"></i>
                                        </a>
                                        <a href="manajemen_wp.php?edit=<?= $row['npwp']; ?>" class="btn btn-white btn-sm border" title="Edit Data">
                                            <i data-lucide="edit-3" style="width:14px; color: #3b82f6;"></i>
                                        </a>
                                        <a href="?delete_wp=<?= $row['npwp']; ?>" class="btn btn-white btn-sm border" onclick="return confirm('Hapus permanen WP ini?')" title="Hapus">
                                            <i data-lucide="trash-2" style="width:14px; color: #ef4444;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($all_wp)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i data-lucide="database" class="mb-2" style="width:40px; height:40px; opacity:0.2;"></i>
                                    <p>Belum ada data Wajib Pajak yang terdaftar.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    // Realtime Search Filter
    document.getElementById('searchWP').onkeyup = function() {
        const filter = this.value.toUpperCase();
        const rows = document.querySelectorAll('.wp-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.innerText.toUpperCase();
            if (text.includes(filter)) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });
    };
</script>
</body>
</html>