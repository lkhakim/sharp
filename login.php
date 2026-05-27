<?php
error_reporting(E_ALL); ini_set('display_errors', 1); // HAPUS KALO UDAH LIVE

/**
 * SHARP - Halaman Login & Lupa Password
 * Fitur: Validasi Role (Auditor/Manager), Session Management, Lupa Password via Email.
 */
require_once 'config.php';
session_start();

// Jika sudah login, tendang ke dashboard sesuai role
if (isset($_SESSION['user_id'])) {
//    if ($_SESSION['role'] == 'manager') {
//        header("Location: dashboard_manager.php");
//    } else {
        header("Location: dashboard");
//    } 
    exit; 
} 

$error = "";
$success = "";  

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Query cek user (Password menggunakan password_verify untuk keamanan)
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Simulasi login (Ganti password_verify($password, $user['password']) untuk produksi)
        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama']    = $user['nama_lengkap'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['kpp']     = $user['kode_kpp'];

            // === CATAT LOG AKTIVITAS LOGIN BERHASIL ===
            catatLogAktivitas($db, $user['id'], $user['username'], 'Autentikasi', 'Login berhasil ke dalam sistem SHARP');

            
                header("Location: index");
            
            exit; 
        } else {
            // === CATAT LOG AKTIVITAS GAGAL LOGIN ===
            catatLogAktivitas($db, null, $username, 'Autentikasi', 'Percobaan login gagal (Password salah / User tidak ditemukan)');
            $error = "Username atau Password salah!";
        }
    } 
    elseif ($action === 'forgot_password') {
        $email = trim($_POST['email'] ?? '');
        
        // Cari user berdasarkan email
        $stmt = $db->prepare("SELECT id, nama_lengkap FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate token acak (aman)
            $token = bin2hex(random_bytes(32));
            // Set waktu kedaluwarsa 1 jam dari sekarang
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Simpan token ke database
            $updateStmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $updateStmt->execute([$token, $expiry, $user['id']]);

            // Simulasi pengiriman email
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
            $emailSubject = "Reset Password Akun SHARP";
            $emailBody = "Halo " . htmlspecialchars($user['nama_lengkap']) . ",\n\nKami menerima permintaan untuk mereset password akun SHARP Anda. Klik link di bawah ini untuk membuat password baru:\n\n" . $resetLink . "\n\nAbaikan email ini jika Anda tidak merasa meminta reset password.\n\nSalam,\nTim Admin SHARP";
            
            // Catatan: Di server nyata Anda akan menggunakan fungsi mail() atau library PHPMailer.
            // mail($email, $emailSubject, $emailBody);

            $success = "Tautan reset password telah dikirim ke alamat email Anda. <br><small>(Simulasi Link: <a href='$resetLink' class='alert-link'>Klik di sini untuk Reset</a>)</small>";
        } else {
            $error = "Alamat email tidak ditemukan di sistem kami.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login SHARP - Sistem Hybrid Analisa Risiko Pajak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --accent: #f59e0b; }
        body { 
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
        }
        .logo-box {
            width: 70px; height: 70px;
            background: var(--primary);
            border-radius: 15px;
            display: flex;
            align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .form-control { border-radius: 10px; padding: 12px; border: 1px solid #ddd; }
        .btn-primary-custom { 
            background: var(--primary); 
            border: none; border-radius: 10px; padding: 12px;
            font-weight: bold; transition: 0.3s; color: white;
        }
        .btn-primary-custom:hover { background: #172554; transform: translateY(-2px); color: white;}
        .link-forgot {
            cursor: pointer;
            text-decoration: none;
            color: #64748b;
            transition: color 0.2s;
        }
        .link-forgot:hover { color: var(--primary); text-decoration: underline; }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="logo-box shadow">
                <i data-lucide="shield-check" class="text-warning" style="width: 40px; height: 40px;"></i>
            </div>
            <h4 class="fw-bold mb-0">SHARP LOGIN</h4>
            <p class="text-muted small">Sistem Hybrid Analisa Risiko Pajak</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger small py-2 d-flex align-items-center">
                <i data-lucide="alert-circle" class="me-2" style="width: 16px;"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success small py-2 d-flex align-items-center">
                <i data-lucide="check-circle" class="me-2" style="width: 24px;"></i> <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Login Utama -->
        <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label class="form-label small fw-bold">Username / NIP</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="user" style="width:16px"></i></span>
                    <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="Masukkan username" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label small fw-bold">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="lock" style="width:16px"></i></span>
                    <input type="password" name="password" id="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePass">
                        <i data-lucide="eye" id="iconPass" style="width:16px"></i> 
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 mb-3">
                Masuk ke Sistem
            </button>
            
            <div class="text-center">
                <span class="small link-forgot" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Lupa password? Klik di sini</span>
            </div>
        </form>
    </div>
</div>

<!-- Modal Lupa Password -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="forgotPasswordModalLabel">
                    <i data-lucide="key-round" class="me-2 text-primary"></i> Reset Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="forgot_password">
                    <p class="text-muted small mb-3">Masukkan alamat email yang terdaftar pada akun Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi Anda.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i data-lucide="mail" style="width:16px"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="contoh@pajak.go.id" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Kirim Link Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    
    document.getElementById('togglePass').addEventListener('click', function() {
    const pass = document.getElementById('password');
    if (pass.type === 'password') {
        pass.type = 'text';
    } else {
        pass.type = 'password';
    } 
    });
    lucide.createIcons();
</script>
</body>
</html>