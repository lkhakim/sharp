<?php
/**
 * SHARP - Dashboard Monitoring Kolektif (Versi Mobile-Friendly)
 * Menampilkan daftar pengawasan WP dengan filter dan integrasi AI.
 */

require_once 'config.php';
session_start();

// Proteksi akses
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// === CATAT LOG AKSES MODUL ===
catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'] ?? 'Unknown', 'Monitoring Kolektif', 'Mengakses daftar pengawasan kolektif');

// Parameter Filter & Search
$tahun_filter  = $_GET['tahun'] ?? date('Y') - 1;
$risiko_filter = $_GET['risiko'] ?? 'ALL';
$klu_filter    = $_GET['klu'] ?? 'ALL';
$search        = $_GET['search'] ?? '';
$klu_list      = [];

try {
    // 1. Ambil Daftar KLU Unik untuk Dropdown
    $klu_list = $db->query("SELECT DISTINCT klu FROM profil_wp WHERE klu IS NOT NULL ORDER BY klu ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 2. Build Query Dinamis
    $query = "SELECT p.nama, p.npwp, p.klu, h.skor_risiko, h.level_risiko, h.tahun 
              FROM profil_wp p 
              JOIN hasil_analisis h ON p.npwp = h.npwp 
              WHERE h.tahun = :tahun";
    
    $params = [':tahun' => $tahun_filter];

    if ($risiko_filter !== 'ALL') {
        $query .= " AND h.level_risiko = :risiko";
        $params[':risiko'] = $risiko_filter;
    }
    if ($klu_filter !== 'ALL') {
        $query .= " AND p.klu = :klu";
        $params[':klu'] = $klu_filter;
    }
    if (!empty($search)) {
        $query .= " AND (p.nama LIKE :search OR p.npwp LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $query .= " ORDER BY h.skor_risiko DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $list_wp = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Hitung Statistik untuk Header Dashboard
    $total_wp = count($list_wp);
    $tinggi = 0; $sedang = 0; $rendah = 0;
    foreach ($list_wp as $wp) {
        if ($wp['level_risiko'] == 'TINGGI') $tinggi++;
        elseif ($wp['level_risiko'] == 'SEDANG') $sedang++;
        else $rendah++;
    }

} catch (Exception $e) {
    $list_wp = [];
    $total_wp = 0; $tinggi = 0; $sedang = 0; $rendah = 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Kolektif - SHARP</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #f59e0b;
            --bg-light: #f8fafc;
        }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Segoe UI', sans-serif; 
            padding-bottom: 85px; 
        }
        .card-sharp { border-radius: 15px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .risiko-TINGGI { border-left: 5px solid #ef4444; }
        .risiko-SEDANG { border-left: 5px solid #f59e0b; }
        .risiko-RENDAH { border-left: 5px solid #10b981; }
        
        .mobile-bottom-nav {
            position: fixed; bottom: 0; width: 100%; background: white;
            display: flex; justify-content: space-around; padding: 12px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 1000;
        }
        .btn-sharp { background-color: var(--primary-color); color: white; border: none; }
        .btn-sharp:hover { background-color: #172554; color: white; }
        
        /* Modal AI Style */
        #aiContent h3, #aiContent h4 { color: var(--primary-color); font-size: 1.1rem; font-weight: bold; margin-top: 1rem; }
        #aiContent ul { padding-left: 1.2rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>
<div class="main-content">
<div class="container my-4">
    <!-- Header & AI Button -->
    <div class="row mb-4 align-items-center">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold m-0">Monitoring Kepatuhan</h4>
            <button class="btn btn-warning btn-sm fw-bold shadow-sm" onclick="generateCollectiveInsight()">
                <i data-lucide="sparkles" class="me-1" style="width: 16px;"></i> Insight AI ✨
            </button>
        </div>
    </div>

    <div class="row g-2 mb-4">
        <div class="col-6 col-md-3">
            <div class="card card-sharp p-3 bg-white h-100">
                <small class="text-muted">Total Filtered</small>
                <h4 class="fw-bold mb-0" id="stat-total"><?php echo $total_wp; ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sharp p-3 bg-white h-100">
                <small class="text-muted text-danger">Risiko Tinggi</small>
                <h4 class="fw-bold mb-0 text-danger" id="stat-high"><?php echo $tinggi; ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sharp p-3 bg-white h-100 text-warning">
                <small class="text-muted">Risiko Sedang</small>
                <h4 class="fw-bold mb-0" id="stat-mid"><?php echo $sedang; ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sharp p-3 bg-white h-100 text-success">
                <small class="text-muted">Risiko Rendah</small>
                <h4 class="fw-bold mb-0" id="stat-low"><?php echo $rendah; ?></h4>
            </div>
        </div>
    </div>

    <div class="card card-sharp p-3 bg-white mb-4">
        <form method="GET" class="row g-2">
            <div class="col-6 col-md-2">
                <label class="small fw-bold text-muted">Tahun Pajak</label>
                <select name="tahun" class="form-select form-select-sm">
                    <?php for($y=date('Y'); $y>=2022; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $tahun_filter == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="small fw-bold text-muted">Level Risiko</label>
                <select name="risiko" class="form-select form-select-sm">
                    <option value="ALL">Semua Risiko</option>
                    <option value="TINGGI" <?php echo $risiko_filter == 'TINGGI' ? 'selected' : ''; ?>>Tinggi</option>
                    <option value="SEDANG" <?php echo $risiko_filter == 'SEDANG' ? 'selected' : ''; ?>>Sedang</option>
                    <option value="RENDAH" <?php echo $risiko_filter == 'RENDAH' ? 'selected' : ''; ?>>Rendah</option>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="small fw-bold text-muted">KLU (Sektor)</label>
                <select name="klu" class="form-select form-select-sm">
                    <option value="ALL">Semua Sektor</option>
                    <?php foreach($klu_list as $k): ?>
                        <option value="<?php echo $k; ?>" <?php echo $klu_filter == $k ? 'selected' : ''; ?>>KLU <?php echo $k; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="small fw-bold text-muted">Cari Nama/NPWP</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2 col-12 d-flex align-items-end">
                <button type="submit" class="btn btn-sharp btn-sm w-100">
                    <i data-lucide="search" class="me-1" style="width:14px;"></i> Terapkan
                </button>
            </div>
        </form>
    </div>

    <div class="card card-sharp bg-white overflow-hidden mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 small fw-bold">Identitas WP</th>
                        <th class="small fw-bold">KLU</th>
                        <th class="small fw-bold text-center">Skor</th>
                        <th class="small fw-bold text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($list_wp)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                    <?php else: foreach($list_wp as $row): ?>
                        <tr class="risiko-<?php echo $row['level_risiko']; ?>">
                            <td class="ps-3">
                                <span class="fw-bold d-block text-truncate text-dark" style="max-width: 180px;"><?php echo htmlspecialchars($row['nama']); ?></span>
                                <small class="text-muted"><?php echo $row['npwp']; ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo $row['klu']; ?></span></td>
                            <td class="text-center">
                                <?php 
                                    $badge = $row['level_risiko'] == 'TINGGI' ? 'bg-danger' : ($row['level_risiko'] == 'SEDANG' ? 'bg-warning text-dark' : 'bg-success');
                                ?>
                                <span class="badge <?php echo $badge; ?>"><?php echo $row['skor_risiko']; ?></span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="profil_wp.php?npwp=<?php echo $row['npwp']; ?>" class="btn btn-outline-secondary btn-sm rounded-pill" title="Buka Profil">
                                        <i data-lucide="user" class="inline" style="width:14px;"></i>
                                    </a>
                                    <a href="hasil_analisa.php?npwp=<?php echo $row['npwp']; ?>&tahun=<?php echo $row['tahun']; ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                                        Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="geminiModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="geminiTitle">AI Collective Analysis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div id="aiLoading" class="text-center py-5">
                    <div class="spinner-border text-warning mb-3"></div>
                    <h6 class="text-primary fw-bold">Gemini sedang merangkum profil risiko kolektif...</h6>
                </div>
                <div id="aiContent" class="bg-white p-3 rounded shadow-sm" style="display:none; font-size: 0.95rem;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();
    const gModal = new bootstrap.Modal(document.getElementById('geminiModal'));

    async function fetchGemini(promptText) {
        const apiKey = ""; // API Key disuntikkan runtime
        const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`;
        const payload = { contents: [{ parts: [{ text: promptText }] }] };

        try {
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await response.json();
            return result.candidates?.[0]?.content?.parts?.[0]?.text || "Error processing AI request.";
        } catch (e) { return "Koneksi ke AI terputus."; }
    }

    async function generateCollectiveInsight() {
        document.getElementById('aiLoading').style.display = 'block';
        document.getElementById('aiContent').style.display = 'none';
        gModal.show();

        const total = document.getElementById('stat-total').innerText;
        const high = document.getElementById('stat-high').innerText;
        const yr = "<?php echo $tahun_filter; ?>";

        const prompt = `Anda adalah Analis Risiko Pajak Senior. Berikut ringkasan populasi WP Tahun ${yr}:
        - Total WP Terdeteksi: ${total}
        - WP Risiko Tinggi: ${high} entitas
        - Filter Aktif: Risiko <?php echo $risiko_filter; ?>, KLU <?php echo $klu_filter; ?>.
        
        Tugas: Buat analisis singkat (2 paragraf) tentang profil risiko populasi ini. Berikan 3 prioritas tindakan pengawasan kolektif untuk Kantor Pelayanan Pajak. 
        Gunakan tag HTML (<h3>, <p>, <li>) tanpa markdown backticks.`;

        const responseHTML = await fetchGemini(prompt);
        document.getElementById('aiLoading').style.display = 'none';
        const content = document.getElementById('aiContent');
        content.style.display = 'block';
        content.innerHTML = responseHTML.replace(/```html/g, '').replace(/```/g, '');
    }
</script>

</body>
</html>