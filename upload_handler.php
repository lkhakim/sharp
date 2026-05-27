<?php
/**
 * SHARP - Centralized Upload Handler
 * Menangani berbagai format file (CSV, XLSX) untuk berbagai modul.
 */
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 403, 'message' => 'Unauthorized']));
}

$npwp = $_POST['npwp'] ?? '';
$tahun = $_POST['tahun'] ?? date('Y');
$modul = $_POST['modul'] ?? '';

if (empty($npwp) || empty($modul)) {
    die(json_encode(['status' => 400, 'message' => 'NPWP dan Modul harus diisi']));
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
    die(json_encode(['status' => 400, 'message' => 'File tidak ditemukan atau error']));
}

$file = $_FILES['file'];
$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

require_once 'functions_upload.php';

try {
    $data = [];
    if ($ext === 'csv') {
        $data = parseCSV($file['tmp_name']);
    } elseif ($ext === 'xlsx' || $ext === 'xls') {
        // Jika XLS, kita butuh library khusus. Untuk XLSX kita bisa gunakan SimpleXLSX atau custom parser.
        $data = parseExcel($file['tmp_name'], $ext);
    } else {
        throw new Exception("Format file tidak didukung: $ext");
    }

    if (empty($data)) {
        throw new Exception("Data kosong atau format tidak sesuai.");
    }

    // Routing berdasarkan modul
    $result = processUploadData($db, $modul, $npwp, $tahun, $data);

    if ($result) {
        echo json_encode(['status' => 200, 'message' => 'Upload berhasil', 'count' => count($data)]);
    } else {
        throw new Exception("Gagal memproses data ke database.");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
}
