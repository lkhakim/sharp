<?php
// Konfigurasi Database
// Konfigurasi Database
//define('DB_HOST', 'localhost');
//define('DB_USER', 'ruufilem_user_audit');
//define('DB_PASS', 'ruufilem_user_audit');
//define('DB_NAME', 'ruufilem_db_audit_pajak');

// JWT Config
define('JWT_SECRET', 'Coretax@803'); 

//function getConnection() {
//    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
//    if ($db->connect_error) {
//        die(json_encode(["error" => "Koneksi database gagal"]));
//    }
//    return $conn;
//}


$host = 'localhost';
$db_name = 'ruufilem_db_audit_pajak';
$db_user = 'ruufilem_sharp';
$db_pass = 'Coretax@803';


try {
    // Pastikan nama variabelnya adalah $db
    $db = new PDO("mysql:host=$host;dbname=$db_name", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi Gagal: " . $e->getMessage());
}



// Fungsi helper respons JSON
function sendResponse($status, $message, $data = null) {
    header("Content-Type: application/json");
    http_response_code($status);
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

// Validasi Token (Simple Bearer Check)
function validateAuth() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        sendResponse(401, "Token tidak ditemukan");
    }
    
    $token = str_replace("Bearer ", "", $headers['Authorization']);
    // Logika verifikasi JWT diletakkan di sini
    // Untuk pengembangan awal, kita kembalikan object user dummy
    return (object)[
        "id" => 1,
        "role" => "auditor",
        "kode_kpp" => "001"
    ];
}

// Fungsi Pencatat Log Aktivitas (Audit Trail)
function catatLogAktivitas($db, $user_id, $username, $modul, $aksi) {
    // Menangkap IP Address dan Detail Browser
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    
    try {
        $stmt = $db->prepare("INSERT INTO log_activity (user_id, username, modul, aksi, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $username, $modul, $aksi, $ip_address, $user_agent]);
    } catch (Exception $e) {
        // Silently fail: Abaikan error log DB agar tidak menghentikan fungsi utama aplikasi
        error_log("Gagal mencatat log aktivitas SHARP: " . $e->getMessage());
    }
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Konfigurasi S3 (IDCloudHost is3)
define('S3_KEY', 'UU9PYJ0J5EFS9B73DK9E');
define('S3_SECRET', 'FrDPLkHGzUCfLJIrtRhervJtisBUb1qyFPf0vOtb');
define('S3_BUCKET', 'sharp'); 
define('S3_ENDPOINT', 'is3.cloudhost.id');
define('S3_URL', 'https://' . S3_ENDPOINT . '/' . S3_BUCKET . '/'); // Path-style URL

$api_key_gemini = "AIzaSyA11_p2V0x0EyGSgEfq5OvaOXw7Ky3AoK4";

// Include S3 Helper
require_once 'config_s3.php';
?>