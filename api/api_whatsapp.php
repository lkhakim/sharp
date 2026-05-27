<?php
/**
 * SHARP - API WhatsApp Integration (Fonnte)
 * Mengirimkan ringkasan SP2DK ke WhatsApp Wajib Pajak.
 */

header('Content-Type: application/json');
require_once '../config.php';

// Token Fonnte (Ganti dengan token Anda)
$fonnte_token = "YOUR_FONNTE_TOKEN_HERE"; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']);
    exit;
}

$npwp = $_POST['npwp'] ?? '';
$tahun = $_POST['tahun'] ?? '';

if (empty($npwp) || empty($tahun)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

try {
    // 1. Ambil Data WP & Analisa
    $sql = "SELECT w.nama, w.telpon, h.data_json, h.skor_risiko, h.level_risiko 
            FROM profil_wp w 
            JOIN hasil_analisis h ON w.npwp = h.npwp 
            WHERE w.npwp = ? AND h.tahun = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$npwp, $tahun]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$res || empty($res['telpon'])) {
        throw new Exception("Nomor telepon WP tidak ditemukan");
    }

    $analisa_data = json_decode($res['data_json'] ?? '', true) ?? [];
    $selisih = $analisa_data['equalisasi']['gap_nominal'] ?? 0;
    
    // 2. Susun Pesan WhatsApp
    $selisih_fmt = number_format($selisih, 0, ',', '.');
    $message = "*SHARP ALERT - NOTIFIKASI RISIKO PAJAK*\n\n";
    $message .= "Yth. Pimpinan " . $res['nama'] . ",\n\n";
    $message .= "Berdasarkan analisa sistem SHARP, terdapat ketidaksesuaian data pada Tahun Pajak " . $tahun . ".\n";
    $message .= "- Skor Risiko: *" . $res['skor_risiko'] . "/100*\n";
    $message .= "- Level Risiko: *" . $res['level_risiko'] . "*\n";
    $message .= "- Indikasi Selisih Omset: *Rp " . $selisih_fmt . "*\n\n";
    $message .= "Kami telah menerbitkan Laporan Hasil Analisa Risiko. Mohon segera melakukan klarifikasi atau pembetulan SPT melalui akun DJP Online Anda.\n\n";
    $message .= "_Pesan ini dikirim secara otomatis oleh Sistem SHARP DJP._";

    // 3. Eksekusi Kirim via Fonnte
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'target' => $res['telpon'],
            'message' => $message,
            'countryCode' => '62', // Indonesia
        ),
        CURLOPT_HTTPHEADER => array(
            "Authorization: $fonnte_token"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    
    $res_data = json_decode($response, true);

    if ($res_data['status']) {
        echo json_encode(['status' => 'success', 'message' => 'Notifikasi WA berhasil dikirim ke ' . $res['telpon']]);
    } else {
        throw new Exception($res_data['reason'] ?? 'Gagal mengirim pesan via Fonnte');
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>