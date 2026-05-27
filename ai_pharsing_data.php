<?php
/**
 * SHARP - Modul Upload & AI Parsing
 * Menangani unggahan berkas (PDF/XLS/CSV) dan simulasi ekstraksi data menggunakan AI.
 */

require_once 'config.php';

// Inisialisasi variabel respons
$response = ['status' => 'idle', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $type = $_POST['doc_type'] ?? 'unknown';
    $npwp = $_POST['npwp'] ?? '01.234.567.8-001.000';
    $tahun = $_POST['tahun'] ?? date('Y');
    
    $file = $_FILES['document'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Simulasi Proses AI (Delay 2 detik untuk kesan pemrosesan)
    // Dalam produksi nyata, di sini kita memanggil Python Script atau API OCR
    
    try {
        $db->beginTransaction();

        if ($type === 'spt') {
            // Simulasi Parsing SPT Tahunan dari PDF Coretax
            $sql = "INSERT INTO spt_tahunan (npwp, tahun, peredaran_usaha, persediaan_awal, pembelian, gaji, biaya_operasional, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            // Data dummy hasil "AI Parsing"
            $stmt->execute([$npwp, $tahun, 15000000000, 2000000000, 8000000000, 1200000000, 1500000000]);
            $msg = "AI Berhasil membaca SPT: Peredaran Usaha Rp 15M terdeteksi.";
            
        } elseif ($type === 'bank') {
            // Simulasi Parsing Rekening Koran (Mutasi Bank)
            // Jika file CSV, kita bisa melakukan parsing nyata
            if (strtolower($ext) === 'csv') {
                $handle = fopen($file['tmp_name'], "r");
                $rowCount = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if ($rowCount > 0) { // Lewati header
                        $sql = "INSERT INTO mutasi_bank (npwp, tahun, tanggal, keterangan, jenis, nominal, kategori) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$npwp, $tahun, $data[0], $data[1], $data[2], $data[3], 'LAINNYA']);
                    }
                    $rowCount++;
                }
                fclose($handle);
                $msg = "Berhasil memproses $rowCount baris mutasi bank.";
            } else {
                // Simulasi AI untuk PDF Bank
                $msg = "AI Berhasil melakukan OCR pada PDF Bank. 124 transaksi ditemukan.";
            }
        } elseif ($type === 'mapping_akun') {
            // Simulasi Auto Mapping Akun dari Trial Balance
            $msg = "AI Berhasil memetakan 45 akun ke kategori standar SHARP.";
        }

        $db->commit();
        $response = ['status' => 'success', 'message' => $msg];
    } catch (Exception $e) {
        $db->rollBack();
        $response = ['status' => 'error', 'message' => 'Gagal parsing: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Upload & Parsing - SHARP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .upload-card { border-radius: 15px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            background: #fff;
            transition: 0.3s;
        }
        .drop-zone:hover { border-color: var(--primary); background: #eff6ff; }
        .ai-status { display: none; }
        .progress { height: 8px; border-radius: 10px; }
        .step-icon { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
        .step-active .step-icon { background: var(--primary); color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="mb-4 d-flex align-items-center">
                <a href="profil_wp.html" class="btn btn-sm btn-outline-secondary me-3"><i data-lucide="arrow-left"></i></a>
                <h4 class="fw-bold m-0">AI Data Importer</h4>
            </div>

            <div class="card upload-card p-4 mb-4">
                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="npwp" value="01.234.567.8-001.000">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">1. PILIH MODUL DATA</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="doc_type" id="type_spt" value="spt" checked onchange="updateTemplateLink()">
                                <label class="btn btn-outline-primary w-100 py-3" for="type_spt">
                                    <i data-lucide="file-text" class="d-block mx-auto mb-1"></i> SPT Tahunan
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="doc_type" id="type_bank" value="bank" onchange="updateTemplateLink()">
                                <label class="btn btn-outline-primary w-100 py-3" for="type_bank">
                                    <i data-lucide="landmark" class="d-block mx-auto mb-1"></i> Rek. Koran
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="doc_type" id="type_mapping" value="mapping_akun" onchange="updateTemplateLink()">
                                <label class="btn btn-outline-primary w-100 py-3" for="type_mapping">
                                    <i data-lucide="book" class="d-block mx-auto mb-1"></i> Pembukuan
                                </label>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="download_template.php?type=spt" id="templateDownloadBtn" class="btn btn-sm btn-success bg-opacity-10 text-success border-success w-100 d-flex justify-content-center align-items-center">
                                <i data-lucide="download" class="me-2" style="width: 16px;"></i> Unduh Template CSV Modul Terpilih
                            </a>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">2. UNGGAH BERKAS (PDF/XLS/CSV)</label>
                        <div class="drop-zone" onclick="document.getElementById('fileInput').click()">
                            <i data-lucide="upload-cloud" class="mb-2 text-primary" style="width:48px; height:48px;"></i>
                            <h6 class="fw-bold">Klik atau Tarik File Ke Sini</h6>
                            <p class="text-muted small">Sistem AI akan otomatis mendeteksi struktur tabel dalam file Anda.</p>
                            <input type="file" name="document" id="fileInput" class="d-none" onchange="startAIParsing()">
                        </div>
                    </div>

                    <div id="aiProcessing" class="ai-status">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small fw-bold" id="statusText">AI sedang menganalisa struktur dokumen...</span>
                            <span class="small" id="progressPct">0%</span>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="pb" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="p-3 bg-light rounded border small">
                            <div id="logArea">
                                <div><i data-lucide="check" class="text-success inline me-2" style="width:14px"></i> Menghubungkan ke engine SHARP...</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($response['status'] !== 'idle'): ?>
            <div class="alert alert-<?php echo $response['status'] === 'success' ? 'success' : 'danger'; ?> d-flex align-items-center shadow-sm">
                <i data-lucide="<?php echo $response['status'] === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="me-3"></i>
                <div>
                    <h6 class="fw-bold mb-0">Hasil Pemrosesan AI</h6>
                    <small><?php echo $response['message']; ?></small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function updateTemplateLink() {
        const type = document.querySelector('input[name="doc_type"]:checked').value;
        const btn = document.getElementById('templateDownloadBtn');
        let templateType = type;
        if (type === 'mapping_akun') templateType = 'trial_balance';
        btn.href = 'download_template.php?type=' + templateType;
    }

    function startAIParsing() {
        const proc = document.getElementById('aiProcessing');
        const pb = document.getElementById('pb');
        const log = document.getElementById('logArea');
        const status = document.getElementById('statusText');
        const pct = document.getElementById('progressPct');

        proc.style.display = 'block';
        
        let progress = 0;
        const steps = [
            "Membaca koordinat teks dalam PDF...",
            "Mendeteksi tabel transaksi...",
            "Melakukan normalisasi data...",
            "Memetakan ke tabel MySQL SHARP...",
            "Hampir selesai..."
        ];

        const interval = setInterval(() => {
            progress += 5;
            pb.style.width = progress + '%';
            pct.innerText = progress + '%';

            if (progress % 20 === 0) {
                const stepIdx = (progress / 20) - 1;
                if (steps[stepIdx]) {
                    status.innerText = steps[stepIdx];
                    const div = document.createElement('div');
                    div.innerHTML = `<i data-lucide="check" class="text-success inline me-2" style="width:14px"></i> ${steps[stepIdx]}`;
                    log.appendChild(div);
                    lucide.createIcons();
                }
            }

            if (progress >= 100) {
                clearInterval(interval);
                setTimeout(() => {
                    document.getElementById('uploadForm').submit();
                }, 500);
            }
        }, 150);
    }
</script>
</body>
</html>