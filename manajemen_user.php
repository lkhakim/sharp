<?php
/**
 * SHARP - Modul Manajemen User (Pegawai)
 * Fitur: CRUD User, Role Management, & Responsive UI.
 * Akses: Hanya Admin
 */
require_once 'config.php';
session_start();

// Proteksi Halaman (Hanya Admin yang bisa akses)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Aktifkan baris di bawah ini untuk produksi agar melempar user selain admin
    // header("Location: monitoring_kolektif.php");
    // exit;
}

$message = "";

// 1. Logika Hapus User
if (isset($_GET['delete'])) {
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = "<div class='alert alert-success'>Pegawai berhasil dihapus.</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menghapus: " . $e->getMessage() . "</div>";
    }
}

// 2. Logika Tambah / Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $id = $_POST['id'] ?? '';
    $username = $_POST['username'];
    $nama = $_POST['nama_lengkap'];
    $role = $_POST['role'];
    $kode_kpp = $_POST['kode_kpp'];
    $atasan_id = !empty($_POST['atasan_id']) ? $_POST['atasan_id'] : null;
    
    try {
        if (empty($id)) {
            // Tambah Baru
            $password = $_POST['password']; // Sebaiknya di-hash: password_hash($_POST['password'], PASSWORD_DEFAULT)
            $sql = "INSERT INTO users (username, nama_lengkap, password, role, kode_kpp, atasan_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([$username, $nama, $password, $role, $kode_kpp, $atasan_id]);
            $message = "<div class='alert alert-success'>Pegawai baru berhasil ditambahkan.</div>";
        } else {
            // Edit Data
            $sql = "UPDATE users SET username = ?, nama_lengkap = ?, role = ?, kode_kpp = ?, atasan_id = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$username, $nama, $role, $kode_kpp, $atasan_id, $id]);
            
            // Update Password jika diisi
            if (!empty($_POST['password'])) {
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$_POST['password'], $id]);
            }
            $message = "<div class='alert alert-success'>Data pegawai berhasil diperbarui.</div>";
        }
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menyimpan: " . $e->getMessage() . "</div>";
    }
}

// 3. Ambil Daftar User & Atasan
try {
    $users = $db->query("SELECT u.*, a.nama_lengkap as nama_atasan FROM users u LEFT JOIN users a ON u.atasan_id = a.id ORDER BY u.role DESC, u.nama_lengkap ASC")->fetchAll(PDO::FETCH_ASSOC);
    $managers = $db->query("SELECT id, nama_lengkap FROM users WHERE role IN ('manager', 'supervisor')")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silently handle error for preview environment or display it
    $users = [];
    $managers = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pegawai - SHARP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; }
        body { 
            background-color: var(--bg); 
            font-family: 'Inter', sans-serif; 
            padding-bottom: 85px;
        }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .badge-role { font-size: 0.7rem; padding: 5px 10px; border-radius: 20px; font-weight: bold; }
        
        /* Pewarnaan Role */
        .role-admin { background-color: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .role-manager { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .role-supervisor { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .role-auditor { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>
<div class="main-content">
<div class="container mt-4">
            <i data-lucide="users" class="me-2 text-warning"></i> MANAJEMEN PEGAWAI (ADMIN)
</div>


<div class="container">
    <div class="row g-4">
        <!-- Panel Kiri: Form -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px; z-index: 100;">
                <h6 class="fw-bold mb-3 d-flex align-items-center text-primary" id="formTitle">
                    <i data-lucide="user-plus" class="me-2" style="width:18px;"></i> Tambah Pegawai Baru
                </h6>
                <hr>
                <form method="POST" action="" id="userForm">
                    <input type="hidden" name="id" id="userId">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Username / NIP</label>
                        <input type="text" name="username" id="username" class="form-control form-control-sm" required placeholder="Contoh: 198012122005011001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control form-control-sm" required placeholder="Nama Lengkap Pegawai">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" id="password" class="form-control form-control-sm" placeholder="Kosongkan jika edit & tidak diubah" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Role Akses</label>
                            <select name="role" id="role" class="form-select form-select-sm">
                                <option value="auditor">Auditor</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Kode KPP</label>
                            <input type="text" name="kode_kpp" id="kode_kpp" class="form-control form-control-sm" placeholder="Contoh: 001">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Atasan Langsung</label>
                        <select name="atasan_id" id="atasan_id" class="form-select form-select-sm">
                            <option value="">-- Tanpa Atasan --</option>
                            <?php foreach($managers as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nama_lengkap']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="save_user" class="btn btn-primary btn-sm fw-bold">
                            <i data-lucide="save" class="inline me-1" style="width:16px;"></i> Simpan Data Pegawai
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetForm()">Batal / Reset Form</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Panel Kanan: Tabel -->
        <div class="col-lg-8">
            <?php echo $message; ?>
            <div class="card card-custom bg-white h-100">
                <div class="card-header bg-transparent py-3">
                    <h6 class="fw-bold m-0 text-primary">Daftar Pegawai Terdaftar</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Identitas Pegawai</th>
                                <th>Role</th>
                                <th>Kode KPP</th>
                                <th>Atasan Langsung</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada data pegawai.</td>
                            </tr>
                            <?php else: foreach($users as $u): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($u['nama_lengkap']); ?></div>
                                    <small class="text-muted">NIP: <?php echo htmlspecialchars($u['username']); ?></small>
                                </td>
                                <td>
                                    <span class="badge-role role-<?php echo $u['role']; ?>">
                                        <?php echo strtoupper($u['role']); ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($u['kode_kpp']); ?></span></td>
                                <td><small class="text-muted fw-semibold"><?php echo htmlspecialchars($u['nama_atasan'] ?: '-'); ?></small></td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2" 
                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="Edit Pegawai">
                                        <i data-lucide="edit-2" style="width:14px"></i>
                                    </button>
                                    <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-2" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus user ini?')" title="Hapus Pegawai">
                                        <i data-lucide="trash" style="width:14px"></i>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function editUser(data) {
        document.getElementById('formTitle').innerHTML = '<i data-lucide="user-cog" class="me-2" style="width:18px;"></i> Edit Data Pegawai';
        lucide.createIcons();
        
        document.getElementById('userId').value = data.id;
        document.getElementById('username').value = data.username;
        document.getElementById('nama_lengkap').value = data.nama_lengkap;
        document.getElementById('role').value = data.role;
        document.getElementById('kode_kpp').value = data.kode_kpp;
        document.getElementById('atasan_id').value = data.atasan_id || "";
        document.getElementById('password').required = false; // Password tidak wajib saat edit
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formTitle').innerHTML = '<i data-lucide="user-plus" class="me-2" style="width:18px;"></i> Tambah Pegawai Baru';
        lucide.createIcons();
        
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = "";
        document.getElementById('password').required = true; // Wajib diisi saat form baru
    }
</script>
</body>
</html>