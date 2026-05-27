<?php
/**
 * SHARP - API Validasi Lapangan
 * Menangani upload foto, koordinat GPS, dan data checklist audit fisik.
 */

header('Content-Type: application/json');
error_reporting(E_ALL); ini_set('display_errors', 0); // Log errors but don't display to break JSON
require_once '../config.php'; // Koneksi database PDO dari root

// Pastikan request menggunakan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan']);
    exit;
}

// 1. Ambil Data dari Form
$npwp = $_POST['npwp'] ?? '';
$tahun = $_POST['tahun'] ?? date('Y');

if (empty($npwp)) {
    echo json_encode(['status' => 'error', 'message' => 'NPWP Wajib diisi']);
    exit;
}

// 2. Ambil Data Eksisting (Jika ada) untuk fallback & update
// Catatan: validasi_lapangan hanya 1 record per WP (Tanpa tahun di schema terbaru)
$stmtOld = $db->prepare("SELECT * FROM validasi_lapangan WHERE npwp = ? LIMIT 1");
$stmtOld->execute([$npwp]);
$oldData = $stmtOld->fetch(PDO::FETCH_ASSOC) ?: [];

$exists = !empty($oldData);

// Fallback GPS jika tidak dikirim dari form
$lat_lokasi = !empty($_POST['lat_lokasi']) ? $_POST['lat_lokasi'] : ($oldData['lat_lokasi'] ?? null);
$lng_lokasi = !empty($_POST['lng_lokasi']) ? $_POST['lng_lokasi'] : ($oldData['lng_lokasi'] ?? null);
$lat_kegiatan = !empty($_POST['lat_kegiatan']) ? $_POST['lat_kegiatan'] : ($oldData['lat_kegiatan'] ?? null);
$lng_kegiatan = !empty($_POST['lng_kegiatan']) ? $_POST['lng_kegiatan'] : ($oldData['lng_kegiatan'] ?? null);

// Mengambil Checkboxes (TINYINT 1/0)
$fields = [
    'Alamat_sesuai', 'Ada_papan_nama', 'Ada_aktivitas', 'Jam_operasional_wajar',
    'Aset_terlihat', 'Ada_pembukuan', 'Pembukuan_rapi', 'Faktur_tersimpan',
    'PIC_menguasai', 'Penjelasan_wajar', 'Pegawai_sesuai_SPT', 'Alamat_fiktif',
    'Kantor_virtual_sewa', 'Tidak_kooperatif'
];

$checklist_data = [];
foreach ($fields as $f) {
    $checklist_data[$f] = isset($_POST[$f]) ? 1 : 0;
}

$jumlah_pegawai = (int)($_POST['Jumlah_Pegawai'] ?? 0);
$catatan = $_POST['catatan'] ?? '';

// 3. Upload Foto ke S3 (IDCloudHost is3)
require_once '../functions_upload.php'; 

// Fallback Link Foto jika tidak diupload baru
$uploaded_urls = [
    'foto_lokasi' => $oldData['link_foto_lokasi'] ?? null,
    'foto_kegiatan' => $oldData['link_foto_kegiatan'] ?? null
];

$foto_fields = ['foto_lokasi', 'foto_kegiatan'];

foreach ($foto_fields as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $file_ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
        $file_name = "AUDIT_" . $npwp . "_" . $field . "_" . time() . "." . $file_ext;
        
        // Upload langsung ke S3 
        if (uploadToS3($_FILES[$field]['tmp_name'], $file_name)) {
            $uploaded_urls[$field] = $file_name; // Simpan nama file saja
        } else {
            echo json_encode(['status' => 'error', 'message' => "Gagal mengunggah foto $field ke Cloud Storage."]);
            exit;
        }
    }
}

// 4. Hitung Skor Validasi Lapangan (0-100, Semakin tinggi semakin berisiko)
$skor_v_l = 0;
$positive_fields = [
    'Alamat_sesuai', 'Ada_papan_nama', 'Ada_aktivitas', 'Jam_operasional_wajar',
    'Aset_terlihat', 'Ada_pembukuan', 'Pembukuan_rapi', 'Faktur_tersimpan',
    'PIC_menguasai', 'Penjelasan_wajar', 'Pegawai_sesuai_SPT'
];
foreach ($positive_fields as $f) {
    if ($checklist_data[$f] == 0) $skor_v_l += 7;
}
if ($checklist_data['Alamat_fiktif'] == 1) $skor_v_l += 23;
if ($checklist_data['Tidak_kooperatif'] == 1) $skor_v_l += 15;
if ($checklist_data['Kantor_virtual_sewa'] == 1) $skor_v_l += 10;
$skor_v_l = min(100, $skor_v_l);

try {
    $db->beginTransaction();

    if ($exists) {
        // UPDATE record validasi_lapangan
        $sqlAudit = "UPDATE validasi_lapangan SET 
            skor = ?, lat_lokasi = ?, lng_lokasi = ?, lat_kegiatan = ?, lng_kegiatan = ?, 
            link_foto_lokasi = ?, link_foto_kegiatan = ?, Alamat_sesuai = ?, Ada_papan_nama = ?, 
            Ada_aktivitas = ?, Jam_operasional_wajar = ?, Aset_terlihat = ?, Ada_pembukuan = ?, 
            Pembukuan_rapi = ?, Faktur_tersimpan = ?, PIC_menguasai = ?, Penjelasan_wajar = ?, 
            Pegawai_sesuai_SPT = ?, Jumlah_Pegawai = ?, Alamat_fiktif = ?, Kantor_virtual_sewa = ?, 
            Tidak_kooperatif = ?, catatan = ?
            WHERE npwp = ?";
        
        $stmt = $db->prepare($sqlAudit);
        $stmt->execute([
            $skor_v_l, $lat_lokasi, $lng_lokasi, $lat_kegiatan, $lng_kegiatan,
            $uploaded_urls['foto_lokasi'],
            $uploaded_urls['foto_kegiatan'],
            $checklist_data['Alamat_sesuai'], $checklist_data['Ada_papan_nama'], 
            $checklist_data['Ada_aktivitas'], $checklist_data['Jam_operasional_wajar'],
            $checklist_data['Aset_terlihat'], $checklist_data['Ada_pembukuan'], 
            $checklist_data['Pembukuan_rapi'], $checklist_data['Faktur_tersimpan'],
            $checklist_data['PIC_menguasai'], $checklist_data['Penjelasan_wajar'], 
            $checklist_data['Pegawai_sesuai_SPT'], $jumlah_pegawai, 
            $checklist_data['Alamat_fiktif'], $checklist_data['Kantor_virtual_sewa'], 
            $checklist_data['Tidak_kooperatif'], $catatan,
            $npwp
        ]);
    } else {
        // INSERT record baru validasi_lapangan
        $sqlAudit = "INSERT INTO validasi_lapangan 
            (npwp, skor, lat_lokasi, lng_lokasi, lat_kegiatan, lng_kegiatan, 
            link_foto_lokasi, link_foto_kegiatan, Alamat_sesuai, Ada_papan_nama, 
            Ada_aktivitas, Jam_operasional_wajar, Aset_terlihat, Ada_pembukuan, 
            Pembukuan_rapi, Faktur_tersimpan, PIC_menguasai, Penjelasan_wajar, 
            Pegawai_sesuai_SPT, Jumlah_Pegawai, Alamat_fiktif, Kantor_virtual_sewa, 
            Tidak_kooperatif, catatan, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($sqlAudit);
        $stmt->execute([
            $npwp, $skor_v_l, $lat_lokasi, $lng_lokasi, $lat_kegiatan, $lng_kegiatan,
            $uploaded_urls['foto_lokasi'],
            $uploaded_urls['foto_kegiatan'],
            $checklist_data['Alamat_sesuai'], $checklist_data['Ada_papan_nama'], 
            $checklist_data['Ada_aktivitas'], $checklist_data['Jam_operasional_wajar'],
            $checklist_data['Aset_terlihat'], $checklist_data['Ada_pembukuan'], 
            $checklist_data['Pembukuan_rapi'], $checklist_data['Faktur_tersimpan'],
            $checklist_data['PIC_menguasai'], $checklist_data['Penjelasan_wajar'], 
            $checklist_data['Pegawai_sesuai_SPT'], $jumlah_pegawai, 
            $checklist_data['Alamat_fiktif'], $checklist_data['Kantor_virtual_sewa'], 
            $checklist_data['Tidak_kooperatif'], $catatan
        ]);
    }

    // 5. Update Skor Risiko & Skor Final di Tabel hasil_analisis
    // Ambil skor_risiko saat ini
    $stmtSkor = $db->prepare("SELECT skor_risiko FROM hasil_analisis WHERE npwp = ? AND tahun = ? ORDER BY id DESC LIMIT 1");
    $stmtSkor->execute([$npwp, $tahun]);
    $rowSkor = $stmtSkor->fetch(PDO::FETCH_ASSOC);
    $skor_risiko = $rowSkor['skor_risiko'] ?? 0;

    // Hitung Skor Final (75% skor_risiko + 25% skor_validasi)
    $skor_final = ($skor_risiko * 0.75) + ($skor_v_l * 0.25);
    $skor_final = round($skor_final);
    
    // Tentukan Level Risiko Baru berdasarkan Skor Final
    $level_baru = 'RENDAH';
    if ($skor_final >= 70) $level_baru = 'TINGGI';
    elseif ($skor_final >= 40) $level_baru = 'SEDANG';

    // Update tabel hasil_analisis
    $sqlUpdate = "UPDATE hasil_analisis SET 
                  skor_final = ?, 
                  skor_validasi = ?,
                  level_risiko = ? 
                  WHERE npwp = ? AND tahun = ?";
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->execute([$skor_final, $skor_v_l, $level_baru, $npwp, $tahun]);

    // Jika record belum ada (untuk WP baru yang belum pernah di-engine), buat record dasar
    if ($stmtUpdate->rowCount() == 0) {
        $stmtCheck = $db->prepare("SELECT id FROM hasil_analisis WHERE npwp = ? AND tahun = ?");
        $stmtCheck->execute([$npwp, $tahun]);
        if (!$stmtCheck->fetch()) {
            $stmtInsert = $db->prepare("INSERT INTO hasil_analisis 
                (npwp, tahun, skor_risiko, skor_validasi, skor_final, level_risiko, catatan_risiko, created_by, data_json) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $npwp, $tahun, 0, $skor_v_l, $skor_final, $level_baru, 
                "Inisialisasi dari Validasi Lapangan", 
                $_SESSION['nama'] ?? 'System',
                json_encode(['analysis' => ['skor' => 0, 'level' => $level_baru, 'catatan' => ["Inisialisasi dari Validasi Lapangan"]]])
            ]);
        }
    }

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Data validasi lapangan berhasil dikirim dan dianalisis.'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal menyimpan data: ' . $e->getMessage()
    ]);
}
?>