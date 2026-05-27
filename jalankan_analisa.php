<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$npwp = $_GET['npwp'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($npwp)) {
    header("Location: manajemen_wp.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Analysis Engine - SHARP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #0f172a; --accent: #3b82f6; }
        body { background-color: #f1f5f9; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        .glass-card { background: white; border-radius: 16px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .progress-step { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
        .progress-step:last-child { border-bottom: none; }
        .step-icon { width: 24px; height: 24px; color: #cbd5e1; }
        .step-icon.active { color: var(--accent); }
        .step-icon.done { color: #10b981; }
    </style>
</head>
<body>

<div class="container py-5" style="max-width: 600px;">
    <div class="glass-card p-4">
        <h4 class="fw-bold mb-3">Analysis Engine</h4>
        <p class="text-muted small">Running comprehensive risk analysis for NPWP: <b><?= htmlspecialchars($npwp) ?></b> (Tahun: <?= $tahun ?>)</p>
        
        <div class="progress mb-4" style="height: 8px;">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
        </div>

        <div id="stepContainer" class="border rounded-3 overflow-hidden">
            <!-- Steps will be injected here -->
        </div>
        
        <div id="resultAction" class="mt-4 text-center d-none">
            <a href="hasil_analisa.php?npwp=<?= urlencode($npwp) ?>&tahun=<?= $tahun ?>" class="btn btn-primary fw-bold shadow">View Results</a>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    const steps = [
        "Ambil Data Profile", "Ambil Data SPT Tahunan", "Ambil Data Mapping Akun",
        "Ambil Data Faktur Pajak", "Ambil Data Bukti Potong", "Ambil Data Setoran",
        "Ambil Data Mutasi Rekening", "Ambil Data ILAP", "Proses Menghitung Kewajaran Saldo",
        "Proses Equalisasi", "Proses penghitungan Arus kas", "Proses Penghitungan Potensi Pajak",
        "Penghitungan Rasio Benchmark", "Scoring Risk", "Simpan data"
    ];

    const container = document.getElementById('stepContainer');
    const progressBar = document.getElementById('progressBar');

    steps.forEach((step, index) => {
        container.innerHTML += `
            <div class="progress-step" id="step-${index}">
                <span class="small fw-semibold">${step}</span>
                <i data-lucide="circle" class="step-icon" id="icon-${index}"></i>
            </div>
        `;
    });
    lucide.createIcons();

    async function runAnalysis() {
        for(let i = 0; i < steps.length; i++) {
            // Update UI
            document.getElementById(`icon-${i}`).setAttribute('data-lucide', 'loader');
            document.getElementById(`icon-${i}`).classList.add('active');
            lucide.createIcons();
            
            // Simulate API calls (Wait for backend or simple timeout)
            await new Promise(r => setTimeout(r, 800));
            
            // Mark Done
            document.getElementById(`icon-${i}`).setAttribute('data-lucide', 'check-circle');
            document.getElementById(`icon-${i}`).classList.remove('active');
            document.getElementById(`icon-${i}`).classList.add('done');
            progressBar.style.width = `${((i + 1) / steps.length) * 100}%`;
            lucide.createIcons();
        }
        document.getElementById('resultAction').classList.remove('d-none');
    }

    runAnalysis();
</script>
</body>
</html>