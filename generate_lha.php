<?php
/**
 * SHARP - Laporan Hasil Analisa Risiko (LHAR)
 * Refactored from SP2DK to a formal audit report structure.
 */

require_once 'config.php';
session_start();

$npwp = $_GET['npwp'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($npwp)) die("NPWP tidak boleh kosong.");

// === CATAT LOG AKSES ===
if (isset($_SESSION['user_id'])) {
    catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['nama'] ?? 'Unknown', 'LHAR', "Generate Laporan Hasil Analisa Risiko untuk NPWP: $npwp Tahun: $tahun");
}

try {
    // 1. Fetch Data Profil WP
    $stmtWp = $db->prepare("SELECT p.*, b.nama_klasifikasi_usaha FROM profil_wp p LEFT JOIN benchmark_klu b ON p.klu = b.klu WHERE p.npwp = ?");
    $stmtWp->execute([$npwp]);
    $wp = $stmtWp->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch Hasil Analisa
    $stmtAnalisa = $db->prepare("SELECT * FROM hasil_analisis WHERE npwp = ? AND tahun = ?");
    $stmtAnalisa->execute([$npwp, $tahun]);
    $analisa_raw = $stmtAnalisa->fetch(PDO::FETCH_ASSOC);
    $analisa_data = json_decode($analisa_raw['data_json'] ?? '', true) ?? [];

    // 3. Fetch Validasi Lapangan (Global/Single Record)
    $stmtAudit = $db->prepare("SELECT * FROM validasi_lapangan WHERE npwp = ? LIMIT 1");
    $stmtAudit->execute([$npwp]);
    $audit = $stmtAudit->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Variables for Report
$omzet_spt = $analisa_data['spt']['peredaran_usaha'] ?? 0;
$omzet_faktur = $analisa_data['total_faktur_keluaran'] ?? 0;
$omzet_bank = $analisa_data['total_bank_kredit'] ?? 0;
$omzet_ilap = $analisa_data['total_ilap'] ?? 0;
$npm_aktual = $analisa_data['profitabilitas']['npm_aktual'] ?? 0;
$benchmark_npm = $analisa_data['profitabilitas']['benchmark_npm'] ?? 0;

// getImageUrl() is now centrally managed in config_s3.php, which is included via config.php

function toRp($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>LHAR_<?= $npwp; ?>_<?= $tahun; ?></title>
    <style>
        @media print { .no-print { display: none; } body { background: white; padding: 0; } .page { box-shadow: none; margin: 0; border: none; } }
        body { font-family: "Arial", sans-serif; line-height: 1.6; background: #f4f4f4; padding: 40px; color: #333; }
        .page { background: white; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 25mm 20mm; box-sizing: border-box; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        .header-laporan { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; }
        .header-laporan h2 { margin: 0; text-transform: uppercase; font-size: 16pt; }
        .header-laporan h3 { margin: 5px 0; font-size: 12pt; font-weight: normal; }

        .bab-title { background: #f8f9fa; padding: 8px 15px; border-left: 5px solid #1e3a8a; font-weight: bold; text-transform: uppercase; margin: 25px 0 15px 0; font-size: 12pt; }
        .sub-bab { font-weight: bold; margin-top: 15px; text-decoration: underline; }
        
        .data-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 10pt; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .data-table th { background: #f2f2f2; color: #444; }
        
        .footer-sign { margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-end; }
        .floating-btns { position: fixed; top: 20px; right: 20px; display: flex; flex-direction: column; gap: 10px; }
        .btn { padding: 10px 20px; cursor: pointer; border: none; border-radius: 5px; font-weight: bold; color: white; text-decoration: none; text-align: center; }
        .btn-blue { background: #1e3a8a; }
        .btn-green { background: #10b981; }
        
        .alert-recommendation { background: #fffbeb; border: 1px solid #fef3c7; padding: 15px; border-radius: 8px; color: #92400e; }
    </style>
</head>
<body>

<div class="no-print floating-btns">
    <button class="btn btn-blue" onclick="window.print()">Cetak Laporan (PDF)</button>
    <a href="hasil_analisa.php?npwp=<?= $npwp ?>&tahun=<?= $tahun ?>" class="btn btn-green">Kembali ke Dashboard</a>
</div>

<div class="page">
    <div class="header-laporan">
        <h2>LAPORAN HASIL ANALISA RISIKO</h2>
        <h3>Sistem Hybrid Analisa Risiko Pajak (SHARP)</h3>
        <p>Nomor: LHAR-<?= rand(100,999); ?>/WPJ.XX/KP.XXXX/<?= date('Y'); ?></p>
    </div>

    <div class="wp-summary" style="margin-bottom: 20px;">
        <table style="width: 100%; font-size: 10pt;">
            <tr><td width="150">Nama Wajib Pajak</td><td>: <b><?= htmlspecialchars($wp['nama']); ?></b></td></tr>
            <tr><td>NPWP</td><td>: <?= $wp['npwp']; ?></td></tr>
            <tr><td>Tahun Pajak</td><td>: <b><?= $tahun; ?></b></td></tr>
            <tr><td>Klasifikasi Usaha</td><td>: <?= $wp['klu']; ?> - <?= htmlspecialchars($wp['nama_klasifikasi_usaha']); ?></td></tr>
        </table>
    </div>

    <!-- BAB I -->
    <div class="bab-title">BAB I : Profil Wajib Pajak dan Proses Bisnis</div>
    <div class="content">
        <p>Wajib Pajak (WP) terdaftar sebagai entitas <b><?= $wp['jenis_wp']; ?></b> pada tanggal <?= date('d M Y', strtotime($wp['tgl_daftar'])); ?>. WP menjalankan kegiatan usaha utamanya di sektor <b><?= htmlspecialchars($wp['nama_klasifikasi_usaha']); ?></b>.</p>
        
        <div class="sub-bab">1.1 Gambaran Umum Proses Bisnis</div>
        <p>Berdasarkan klasifikasi KLU, WP melakukan kegiatan operasional yang melibatkan siklus pendapatan dari <?= strtolower($wp['nama_klasifikasi_usaha']); ?>. WP menggunakan sistem pencatatan/pembukuan yang statusnya <b><?= ($audit['Ada_pembukuan'] ?? 0) ? 'Sudah Tersedia' : 'Belum Terverifikasi Fisik'; ?></b> saat dilakukan kunjungan lapangan.</p>
        
        <div class="sub-bab">1.2 Validasi Lokasi dan Aktivitas</div>
        <p>Tim pengawasan telah melakukan validasi lapangan dengan status aktivitas usaha tercatat sebagai: <b><?= ($audit['Ada_aktivitas'] ?? 0) ? 'Aktif Beroperasi' : 'Tidak Ada Aktivitas Terlihat'; ?></b>. Lokasi usaha berada pada titik koordinat (<?= $audit['lat_kegiatan'] ?? '-'; ?>, <?= $audit['lng_kegiatan'] ?? '-'; ?>).</p>
    </div>

    <!-- BAB II -->
    <div class="bab-title">BAB II : Hasil Analisa dan Temuan Perpajakan</div>
    <div class="content">
        <div class="sub-bab">2.1 Ekualisasi Peredaran Usaha</div>
        <p>Dilakukan pengujian kepatuhan dengan membandingkan pelaporan Peredaran Usaha pada SPT Tahunan terhadap data eksternal (Big Data) dengan rincian:</p>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sumber Data</th>
                    <th>Nilai Nominal</th>
                    <th>Selisih (Gap)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>SPT Tahunan (Dilaporkan)</td><td><?= toRp($omzet_spt); ?></td><td>-</td></tr>
                <tr><td>Data e-Faktur Keluaran</td><td><?= toRp($omzet_faktur); ?></td><td style="color:red"><?= toRp($omzet_faktur - $omzet_spt); ?></td></tr>
                <tr><td>Mutasi Kredit Bank</td><td><?= toRp($omzet_bank); ?></td><td style="color:red"><?= toRp($omzet_bank - $omzet_spt); ?></td></tr>
                <tr><td>Data Pihak Ketiga (ILAP)</td><td><?= toRp($omzet_ilap); ?></td><td style="color:red"><?= toRp($omzet_ilap - $omzet_spt); ?></td></tr>
            </tbody>
        </table>

        <div class="sub-bab">2.2 Analisa Profitabilitas (Benchmarking)</div>
        <p>Berdasarkan rasio industri untuk KLU <?= $wp['klu']; ?>, rata-rata <i>Net Profit Margin</i> (NPM) adalah sebesar <b><?= $benchmark_npm; ?>%</b>. Berdasarkan laporan WP, NPM aktual tercatat sebesar <b><?= $npm_aktual; ?>%</b>.</p>
        <p><b>Temuan:</b> <?= ($npm_aktual < ($benchmark_npm * 0.7)) ? 'Terdapat indikasi <i>understatement</i> laba (Low Margin) yang tidak wajar dibandingkan rata-rata industri sejenis.' : 'Margin keuntungan WP berada dalam batas kewajaran rasio industri.'; ?></p>
        
        <div class="sub-bab">2.3 Temuan Ketidakpatuhan</div>
        <ul style="font-size: 10pt;">
            <?php 
            $notes = explode(". ", $analisa_raw['catatan_risiko'] ?? '');
            foreach($notes as $note): if(trim($note) == "") continue;
            ?>
            <li><?= htmlspecialchars($note); ?>.</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- BAB III -->
    <div class="bab-title">BAB III : Kesimpulan dan Rekomendasi</div>
    <div class="content">
        <div class="sub-bab">3.1 Kesimpulan Risiko</div>
        <p>Berdasarkan hasil analisa di atas, Wajib Pajak dikategorikan memiliki tingkat risiko <b><?= $analisa_raw['level_risiko']; ?></b> dengan skor total <b><?= $analisa_raw['skor_risiko']; ?>/100</b>.</p>
        
        <div class="sub-bab">3.2 Rekomendasi Tindak Lanjut</div>
        <div class="alert-recommendation">
            <?php if($analisa_raw['level_risiko'] == 'TINGGI'): ?>
                <b>Tindakan Prioritas:</b> Segera menerbitkan SP2DK (Surat Permintaan Penjelasan atas Data dan/atau Keterangan) dan melakukan pemeriksaan lapangan mendalam terkait selisih omset dan margin laba yang rendah.
            <?php elseif($analisa_raw['level_risiko'] == 'SEDANG'): ?>
                <b>Tindakan Pengawasan:</b> Melakukan himbauan melalui Account Representative (AR) untuk klarifikasi data ekualisasi dan melakukan edukasi kepatuhan.
            <?php else: ?>
                <b>Tindakan:</b> Tetap dilakukan pemantauan berkala (<i>Monitoring</i>) tanpa perlu tindakan pemeriksaan khusus saat ini.
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-sign">
        <div style="font-size: 9pt; color: #666;">
            Dokumen ini dihasilkan secara otomatis oleh<br><b>SHARP (Sistem Hybrid Analisa Risiko Pajak)</b><br>
            Tanggal Cetak: <?= date('d/m/Y H:i'); ?>
        </div>
        <div style="text-align: center; width: 200px;">
            Analis Risiko / AR,<br><br><br><br>
            ( ................................. )<br>
            NIP. ............................
        </div>
    </div>
</div>

</body>
</html>
