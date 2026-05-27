<?php
ob_start();
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


$message = "";

$npwp_aktif = $_GET['npwp'] ?? '';
$tahun_aktif = $_GET['tahun'] ?? date('Y');
$current_file = basename($_SERVER['PHP_SELF']);

if (empty($npwp_aktif)) {
    header("Location: manajemen_wp.php");
    exit;
}

try {
    $stmtWp = $db->prepare("SELECT nama, jenis_wp FROM profil_wp WHERE npwp = ?");
    $stmtWp->execute([$npwp_aktif]);
    $wp = $stmtWp->fetch();
} catch (Exception $e) {
    $wp = ['nama' => 'WP Tidak Ditemukan', 'jenis_wp' => 'Badan'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_spt'])) {
    try {
        $data = [
            $_POST['peredaran_usaha'] ?? 0,
            $_POST['persediaan_awal'] ?? 0,
            $_POST['pembelian'] ?? 0,
            $_POST['persediaan_akhir'] ?? 0,
            $_POST['gaji'] ?? 0,
            $_POST['setoran_pph_final'] ?? 0,
            $_POST['biaya_operasional'] ?? 0,
            $_POST['biaya_penyusutan'] ?? 0,
            $_POST['penghasilan_luar_usaha'] ?? 0,
            $_POST['biaya_luar_usaha'] ?? 0,
            $_POST['ptkp'] ?? 0,
            $_POST['penghasilan_final'] ?? 0,
            $_POST['penghasilan_bukan_pajak'] ?? 0,
            $_POST['koreksi_fiskal_positif'] ?? 0,
            $_POST['koreksi_fiskal_negatif'] ?? 0,
            $_POST['kompensasi_kerugian'] ?? 0,
            $_POST['kredit_bukti_potong'] ?? 0,
            $_POST['kredit_pph_25'] ?? 0,
            $_POST['pajak_terutang'] ?? 0,
            $_POST['setoran_pph_29'] ?? 0,
            $_POST['status_spt'] ?? 'NIHIL',
            $_POST['norma'] ?? 0
        ];

        // Check if SPT exists
        $stmtCek = $db->prepare("SELECT id FROM spt_tahunan WHERE npwp = ? AND tahun = ?");
        $stmtCek->execute([$npwp_aktif, $tahun_aktif]);
        $exists = $stmtCek->fetch();

        if ($exists) {
            $sql = "UPDATE spt_tahunan SET peredaran_usaha=?, persediaan_awal=?, pembelian=?, persediaan_akhir=?, gaji=?, setoran_pph_final=?, biaya_operasional=?, biaya_penyusutan=?, penghasilan_luar_usaha=?, biaya_luar_usaha=?, ptkp=?, penghasilan_final=?, penghasilan_bukan_pajak=?, koreksi_fiskal_positif=?, koreksi_fiskal_negatif=?, kompensasi_kerugian=?, kredit_bukti_potong=?, kredit_pph_25=?, pajak_terutang=?, setoran_pph_29=?, status_spt=?, norma=? WHERE npwp=? AND tahun=?";
            $params = array_merge($data, [$npwp_aktif, $tahun_aktif]);
            $db->prepare($sql)->execute($params);
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=updated");
        } else {
            $sql = "INSERT INTO spt_tahunan (peredaran_usaha, persediaan_awal, pembelian, persediaan_akhir, gaji, setoran_pph_final, biaya_operasional, biaya_penyusutan, penghasilan_luar_usaha, biaya_luar_usaha, ptkp, penghasilan_final, penghasilan_bukan_pajak, koreksi_fiskal_positif, koreksi_fiskal_negatif, kompensasi_kerugian, kredit_bukti_potong, kredit_pph_25, pajak_terutang, setoran_pph_29, status_spt, norma, npwp, tahun, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
            $params = array_merge($data, [$npwp_aktif, $tahun_aktif]);
            $db->prepare($sql)->execute($params);
            header("Location: $current_file?npwp=$npwp_aktif&tahun=$tahun_aktif&status=saved");
        }
        exit;
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Gagal menyimpan: " . $e->getMessage() . "</div>";
    }
}

try {
    $stmt = $db->prepare("SELECT * FROM spt_tahunan WHERE npwp = ? AND tahun = ?");
    $stmt->execute([$npwp_aktif, $tahun_aktif]);
    $spt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$spt) {
        // Init empty array
        $spt = array_fill_keys(['peredaran_usaha', 'persediaan_awal', 'pembelian', 'persediaan_akhir', 'gaji', 'setoran_pph_final', 'biaya_operasional', 'biaya_penyusutan', 'penghasilan_luar_usaha', 'biaya_luar_usaha', 'ptkp', 'penghasilan_final', 'penghasilan_bukan_pajak', 'koreksi_fiskal_positif', 'koreksi_fiskal_negatif', 'kompensasi_kerugian', 'kredit_bukti_potong', 'kredit_pph_25', 'pajak_terutang', 'setoran_pph_29', 'norma'], 0);
        $spt['status_spt'] = 'NIHIL';
    }
} catch (Exception $e) {
    die("Database Error.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen SPT - <?= htmlspecialchars($wp['nama'] ?? $npwp_aktif) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --bg: #f8fafc; --accent: #f59e0b; }
        body { background-color: var(--bg); font-family: 'Inter', sans-serif; padding-bottom: 85px; }
        .main-content { margin-left: 260px; padding: 20px; transition: margin-left 0.3s; }
        body.sidebar-mini .main-content { margin-left: 75px; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 2px; }
        .section-title { font-size: 0.9rem; font-weight: bold; color: var(--primary); border-bottom: 2px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 15px; margin-top: 10px; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding-bottom: 90px; } }
    </style>
</head>
<body class="sidebar-mini">

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid px-4 mt-3">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="profil_wp.php?npwp=<?= $npwp_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-sm btn-outline-secondary me-3" title="Kembali">
                    <i data-lucide="arrow-left"></i>
                </a>
                <div>
                    <h4 class="fw-bold m-0 text-primary">Form SPT Tahunan</h4>
                    <span class="text-muted small">NPWP: <?= $npwp_aktif; ?> | <?= htmlspecialchars($wp['nama']); ?></span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <div class="btn-group">
                    <button class="btn btn-outline-success btn-sm dropdown-toggle fw-bold" data-bs-toggle="dropdown">
                        <i data-lucide="download" class="inline me-1" style="width:14px"></i> Template
                    </button>
                    <ul class="dropdown-menu shadow border-0">
                        <li><a class="dropdown-item small" href="download_template.php?type=spt&format=csv"><i data-lucide="file-text" class="me-2" style="width:14px"></i> CSV</a></li>
                        <li><a class="dropdown-item small" href="download_template.php?type=spt&format=xls"><i data-lucide="file-spreadsheet" class="me-2" style="width:14px"></i> XLS</a></li>
                    </ul>
                </div>
                <select class="form-select fw-bold border-primary text-primary" onchange="location.href='?npwp=<?= $npwp_aktif ?>&tahun='+this.value">
                    <?php for($y=date('Y'); $y>=2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $tahun_aktif==$y?'selected':'' ?>>Tahun Pajak <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <?= $message ?>
        <?php if(isset($_GET['status'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i data-lucide="check-circle" class="me-2 inline"></i> <strong>Penyimpanan Berhasil!</strong> Data SPT Tahun <?= $tahun_aktif ?> telah disimpan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="formSpt">
            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="card card-custom p-4 bg-white mb-4">
                        
                        <div class="section-title"><i data-lucide="briefcase" class="inline me-1" style="width:16px"></i> A. Penghasilan & HPP</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label">Peredaran Usaha (Omzet)</label>
                                <input type="number" step="0.01" name="peredaran_usaha" id="peredaran_usaha" class="form-control form-control-sm border-primary" value="<?= $spt['peredaran_usaha'] ?>" oninput="kalkulasiSPT()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Persediaan Awal</label>
                                <input type="number" step="0.01" name="persediaan_awal" id="persediaan_awal" class="form-control form-control-sm" value="<?= $spt['persediaan_awal'] ?>" oninput="kalkulasiSPT()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pembelian</label>
                                <input type="number" step="0.01" name="pembelian" id="pembelian" class="form-control form-control-sm" value="<?= $spt['pembelian'] ?>" oninput="kalkulasiSPT()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Persediaan Akhir</label>
                                <input type="number" step="0.01" name="persediaan_akhir" id="persediaan_akhir" class="form-control form-control-sm" value="<?= $spt['persediaan_akhir'] ?>" oninput="kalkulasiSPT()">
                            </div>
                        </div>

                        <div class="section-title"><i data-lucide="trending-down" class="inline me-1" style="width:16px"></i> B. Biaya Operasional</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Biaya Gaji</label>
                                <input type="number" step="0.01" name="gaji" id="gaji" class="form-control form-control-sm" value="<?= $spt['gaji'] ?>" oninput="kalkulasiSPT()">
                            </div>
                
                            <div class="col-md-4">
                                <label class="form-label">Biaya Operasional Lainnya</label>
                                <input type="number" step="0.01" name="biaya_operasional" id="biaya_operasional" class="form-control form-control-sm" value="<?= $spt['biaya_operasional'] ?>" oninput="kalkulasiSPT()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Biaya Penyusutan</label>
                                <input type="number" step="0.01" name="biaya_penyusutan" id="biaya_penyusutan" class="form-control form-control-sm" value="<?= $spt['biaya_penyusutan'] ?>" oninput="kalkulasiSPT()">
                            </div>
                        </div>

                        <div class="section-title"><i data-lucide="layers" class="inline me-1" style="width:16px"></i> C. Luar Usaha & Koreksi Fiskal</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Penghasilan Luar Usaha</label>
                                <input type="number" step="0.01" name="penghasilan_luar_usaha" id="penghasilan_luar_usaha" class="form-control form-control-sm" value="<?= $spt['penghasilan_luar_usaha'] ?>" oninput="kalkulasiSPT()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Biaya Luar Usaha</label>
                                <input type="number" step="0.01" name="biaya_luar_usaha" id="biaya_luar_usaha" class="form-control form-control-sm" value="<?= $spt['biaya_luar_usaha'] ?>" oninput="kalkulasiSPT()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Koreksi Fiskal Positif (+)</label>
                                <input type="number" step="0.01" name="koreksi_fiskal_positif" id="koreksi_fiskal_positif" class="form-control form-control-sm" value="<?= $spt['koreksi_fiskal_positif'] ?>" oninput="kalkulasiSPT()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Koreksi Fiskal Negatif (-)</label>
                                <input type="number" step="0.01" name="koreksi_fiskal_negatif" id="koreksi_fiskal_negatif" class="form-control form-control-sm" value="<?= $spt['koreksi_fiskal_negatif'] ?>" oninput="kalkulasiSPT()">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-custom p-4 bg-white sticky-top" style="top: 20px;">
                        <h6 class="fw-bold mb-3 text-primary"><i data-lucide="calculator" class="inline me-1"></i> Finalisasi & Pajak</h6>
                        <div class="mb-2">
                            <label class="form-label">Penghasilan bersifat Final</label>
                            <input type="number" step="0.01" name="penghasilan_final" id="penghasilan_final" class="form-control form-control-sm" value="<?= $spt['penghasilan_final'] ?>" oninput="kalkulasiSPT()">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Penghasilan Bukan Objek Pajak</label>
                            <input type="number" step="0.01" name="penghasilan_bukan_pajak" id="bukan_objek_pajak" class="form-control form-control-sm" value="<?= $spt['penghasilan_bukan_pajak'] ?>" oninput="kalkulasiSPT()">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Kompensasi Kerugian</label>
                            <input type="number" step="0.01" name="kompensasi_kerugian" id="kompensasi_kerugian" class="form-control form-control-sm" value="<?= $spt['kompensasi_kerugian'] ?>" oninput="kalkulasiSPT()">
                        </div>
                        <?php if($wp['jenis_wp'] == 'OP'): ?>
                        <div class="mb-2">
                            <label class="form-label">PTKP (WP Orang Pribadi)</label>
                            <input type="number" step="0.01" name="ptkp" id="ptkp" class="form-control form-control-sm" value="<?= $spt['ptkp'] ?>" oninput="kalkulasiSPT()">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Norma Penghitungan (%)</label>
                            <input type="number" step="0.01" name="norma" id="norma" class="form-control form-control-sm" value="<?= $spt['norma'] ?>" placeholder="Cth: 50">
                        </div>
                        <?php endif; ?>

                        <hr>
                        <div class="mb-2">
                            <label class="form-label">Kredit Pajak (Bupot Pihak Lain)</label>
                            <input type="number" step="0.01" name="kredit_bukti_potong" id="kredit_bukti_potong" class="form-control form-control-sm text-success" value="<?= $spt['kredit_bukti_potong'] ?>" oninput="kalkulasiSPT()">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Kredit PPh 25 (Angsuran)</label>
                            <input type="number" step="0.01" name="kredit_pph_25" id="kredit_pph_25" class="form-control form-control-sm text-success" value="<?= $spt['kredit_pph_25'] ?>" oninput="kalkulasiSPT()">
                        </div>
                        <hr>
                        <div class="mb-2">
                            <label class="form-label text-danger fw-bold">Pajak Terutang</label>
                            <input type="number" step="0.01" name="pajak_terutang" id="pajak_terutang" class="form-control border-danger fw-bold" value="<?= $spt['pajak_terutang'] ?>" oninput="kalkulasiSPT()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-primary fw-bold">Setoran PPh 29 (Kurang Bayar)</label>
                            <input type="number" step="0.01" name="setoran_pph_29" id="setoran_pph_29" class="form-control border-primary fw-bold" value="<?= $spt['setoran_pph_29'] ?>">
                        </div>
                        <div class="mb-3">
                                <label class="form-label">Setoran PPh Final</label>
                                <input type="number" step="0.01" name="setoran_pph_final" id="setoran_pph_final" class="form-control form-control-sm" value="<?= $spt['setoran_pph_final'] ?>">
                            </div>
                        <div class="mb-4">
                            <label class="form-label">Status SPT</label>
                            <select name="status_spt" id="status_spt" class="form-select fw-bold">
                                <option value="NIHIL" <?= $spt['status_spt']=='NIHIL'?'selected':'' ?>>NIHIL</option>
                                <option value="Kurang Bayar" <?= $spt['status_spt']=='Kurang Bayar'?'selected':'' ?>>KURANG BAYAR</option>
                                <option value="Lebih Bayar" <?= $spt['status_spt']=='Lebih Bayar'?'selected':'' ?>>LEBIH BAYAR</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="save_spt" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                            <i data-lucide="save" class="inline me-1" style="width:18px"></i> Simpan Laporan SPT
                        </button>

                        <div class="mt-3 p-3 bg-light rounded border text-center">
                            <span class="d-block text-muted small fw-bold mb-1">Estimasi Laba Bersih Fiskal</span>
                            <h5 class="m-0 text-dark fw-bold" id="lblLabaBersih">Rp 0</h5>
                            <hr class="my-2">
                            <span class="d-block text-muted small fw-bold mb-1">Estimasi Pajak Wajar</span>
                            <h5 class="m-0 text-danger fw-bold" id="lblPajakWajar">Rp 0</h5>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    lucide.createIcons();

    function formatRp(num) {
        return "Rp " + num.toLocaleString('id-ID');
    }

    function val(id) {
        let e = document.getElementById(id);
        if(!e) return 0;
        let v = parseFloat(e.value);
        return isNaN(v) ? 0 : v;
    }

    // Fungsi Kalkulasi Real-time pembantu User (Hanya Estimasi Visual)

    function kalkulasiSPT() {
        let omzet = val('peredaran_usaha');
        let hpp = val('persediaan_awal') + val('pembelian') - val('persediaan_akhir');
        let biayaOps = val('gaji') + val('biaya_operasional') + val('biaya_penyusutan');
        let luarUsaha = val('penghasilan_luar_usaha') - val('biaya_luar_usaha');
        let labaKomersial = omzet - hpp - biayaOps + luarUsaha;
        let koreksiPositif = val('koreksi_fiskal_positif');
        let koreksiNegatif = val('koreksi_fiskal_negatif');
        let penghasilanFinal = val('penghasilan_final');
        let bukanObjekPajak = val('bukan_objek_pajak');
        let kompensasiKerugian = val('kompensasi_kerugian');
        let labaFiskal = labaKomersial + koreksiPositif - koreksiNegatif - penghasilanFinal - bukanObjekPajak - kompensasiKerugian;
        document.getElementById('lblLabaBersih').innerText = formatRp(labaFiskal);

        // --- Pajak Wajar ---
        let jenisWP = "<?= $wp['jenis_wp'] ?>";
        let ptkp = val('ptkp');
        let norma = val('norma');
        let pkp = 0;
        let pajakWajar = 0;
        if (jenisWP === 'OP') {
            // Jika pakai norma, gunakan norma
            if (norma > 0) {
                let penghasilanNetoNorma = omzet * (norma/100);
                pkp = Math.max(0, penghasilanNetoNorma - ptkp);
            } else {
                pkp = Math.max(0, labaFiskal - ptkp);
            }
            // Tarif progresif OP (2024): 5% s.d. 35%
            let sisa = pkp;
            let lapis = [60000000, 250000000, 500000000, 5000000000];
            let tarif = [0.05, 0.15, 0.25, 0.30, 0.35];
            let batas = [0].concat(lapis);
            pajakWajar = 0;
            for (let i = 0; i < tarif.length; i++) {
                let ambil = Math.min(sisa, (batas[i+1]||Infinity)-(batas[i]));
                if (ambil > 0) pajakWajar += ambil * tarif[i];
                sisa -= ambil;
                if (sisa <= 0) break;
            }
        } else {
            // Badan: flat 22%
            pkp = Math.max(0, labaFiskal);
            pajakWajar = pkp * 0.22;
        }
        document.getElementById('lblPajakWajar').innerText = formatRp(Math.round(pajakWajar));

        // Auto Status (Hanya visual hint)
        let pajakTerutang = val('pajak_terutang');
        let kredit = val('kredit_bukti_potong') + val('kredit_pph_25');
        let selectStatus = document.getElementById('status_spt');
        if (pajakTerutang > kredit) {
            selectStatus.value = 'Kurang Bayar';
            selectStatus.className = "form-select fw-bold bg-danger text-white border-danger";
        } else if (pajakTerutang < kredit) {
            selectStatus.value = 'Lebih Bayar';
            selectStatus.className = "form-select fw-bold bg-warning text-dark border-warning";
        } else {
            selectStatus.value = 'NIHIL';
            selectStatus.className = "form-select fw-bold bg-success text-white border-success";
        }
    }

    // Panggil saat halaman diload
    document.addEventListener('DOMContentLoaded', kalkulasiSPT);
</script>
</body>
</html>