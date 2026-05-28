<?php
require_once '../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$npwp = $_POST['npwp'] ?? '';
$klu_name = $_POST['nama_klu'] ?? '';

if (empty($npwp) || empty($klu_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing NPWP or KLU Name']);
    exit;
}

$prompt = "Jelaskan proses bisnis dari kegiatan usaha " . $klu_name . " mulai dari Model Business Canvas dari Segmentasi Pasar, Proposisi Nilai, saluran, Hubungan Pelanggan, sumber pendapatan, sumber daya utama, mitra utama, struktur biaya dan pesaing. 
Output: JSON OBJECT dengan keys: segmentasi_pasar, proposisi_nilai, saluran, hubungan_pelanggan, sumber_pendapatan, sumber_daya_utama, mitra_utama, struktur_biaya, pesaing. 
HANYA KEMBALIKAN JSON.";

try {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $api_key_gemini;
    $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($response, true);
    $jsonText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // Clean markdown
    $jsonText = preg_replace('/```json|```/', '', $jsonText);
    $jsonText = trim($jsonText);
    
    // Validate JSON
    $decoded = json_decode($jsonText, true);
    if (!$decoded) {
        throw new Exception("AI returned invalid JSON: " . $jsonText);
    }

    // Save to Database
    $stmt = $db->prepare("UPDATE profil_wp SET proses_bisnis = ? WHERE npwp = ?");
    $stmt->execute([$jsonText, $npwp]);

    echo json_encode(['status' => 'success', 'data' => $decoded]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
