<?php
ob_start();
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Sesi berakhir, silakan login kembali.");
}

$npwp = $_POST['npwp'] ?? '';
$tahun = $_POST['tahun'] ?? date('Y');

if (empty($npwp)) die("Akses ditolak: NPWP Kosong");

// Helper functions for formatting
if (!function_exists('persen')) {
    function persen(float $n): string { return round($n, 2) . '%'; }
}
if (!function_exists('angka')) {
    function angka(float $n) { return number_format($n, 0, ',', '.'); }
}

/**
 * ENGINE PROSES ANALISA RISIKO (VERSI 3.0 - ADVANCED SCORING & FISKAL)
 */
$catatan = [];
$skor = 0;

function hitungPajakWajar(float $laba_fiskal, string $jenis_wp, float $omzet, int $is_umkm): array {
    if($laba_fiskal <= 0){
        return [0, "NIHIL", 0];
    }
    $pph_wajar = 0;
    $tarif_detail = '';
    
    // Logic UMKM 0.5%
    if ($is_umkm == 1 && $omzet <= 4800000000) {
        $pph_wajar = $omzet * 0.005;
        $tarif_detail = '0.5% Final UMKM';
        return [$pph_wajar, $tarif_detail, 0];
    }

    if ($jenis_wp == 'OP') {
        $pkp =  $laba_fiskal;
        if ($pkp <= 60000000) {
            $pph_wajar = $pkp * 0.05;
            $tarif_detail = '5% - Pasal 17 Tarif Progresif';
        } elseif ($pkp <= 250000000) {
            $pph_wajar = 3000000 + ($pkp - 60000000) * 0.15;
            $tarif_detail = '5% s/d 15% - Pasal 17 Tarif Progresif';
        } elseif ($pkp <= 500000000) {
            $pph_wajar = 31500000 + ($pkp - 250000000) * 0.25;
            $tarif_detail = '5% s/d 25% - Pasal 17 Tarif Progresif';
        } elseif ($pkp <= 5000000000) {
            $pph_wajar = 94000000 + ($pkp - 500000000) * 0.30;
            $tarif_detail = '5% s/d 30% - Pasal 17 Tarif Progresif';
        } else {
            $pph_wajar = 1444000000 + ($pkp - 5000000000) * 0.35;
            $tarif_detail = '5% s/d 35% - Pasal 17 Tarif Progresif';
        }
        if ($pkp == 0) $tarif_detail = 'Di bawah PTKP';
    
    } else {
        // Badan
        if ($omzet >= 50000000000) {
            $pph_wajar = $laba_fiskal * 0.22;
            $tarif_detail = '22% - Pasal 17 Tarif Umum';
        } else {
            // Pasal 31E
            if ($omzet <= 4800000000){
                $pph_wajar = $laba_fiskal * 0.11;
                $tarif_detail = '11% - Pasal 31E';
            } else {
                $pkp_fasilitas = (4800000000/$omzet) * $laba_fiskal;
                $pkp_non_fasilitas = $laba_fiskal - $pkp_fasilitas;
                $pph_wajar = (0.11 * $pkp_fasilitas) + (0.22 * $pkp_non_fasilitas);
                $tarif_detail = round(($pph_wajar/max(1,$laba_fiskal))*100, 2)."% - Pasal 31E";  
            }
        }
    }
    return [$pph_wajar, $tarif_detail];
}

try {
    $db->beginTransaction();

    // 1. DATA GATHERING (EXPANDED)
    $stmtWp = $db->prepare("SELECT * FROM profil_wp WHERE npwp = ?");
    $stmtWp->execute([$npwp]);
    $client = $stmtWp->fetch(PDO::FETCH_ASSOC);

    // --- FAKTUR DATA ---
    $stmtFaktur = $db->prepare("SELECT jenis_faktur, SUM(dpp) as total_dpp FROM faktur_pajak WHERE npwp = ? AND tahun = ? GROUP BY jenis_faktur");
    $stmtFaktur->execute([$npwp, $tahun]);
    $faktur_data = $stmtFaktur->fetchAll(PDO::FETCH_KEY_PAIR);
    $faktur_keluaran = $faktur_data['KELUARAN'] ?? 0;
    $faktur_masukan = $faktur_data['MASUKAN'] ?? 0;

    // --- BANK DATA ---
    $stmtBank = $db->prepare("SELECT jenis, SUM(nominal) as total FROM mutasi_bank WHERE npwp = ? AND tahun = ? GROUP BY jenis");
    $stmtBank->execute([$npwp, $tahun]);
    $bank_data = $stmtBank->fetchAll(PDO::FETCH_KEY_PAIR);
    $bank_in = $bank_data['KREDIT'] ?? 0;
    $bank_out = $bank_data['DEBIT'] ?? 0;

    // --- ILAP DATA ---
    $stmtIlap = $db->prepare("SELECT kategori_data, SUM(nominal) as total FROM data_ilap WHERE npwp = ? AND tahun = ? GROUP BY kategori_data");
    $stmtIlap->execute([$npwp, $tahun]);
    $ilap_data = $stmtIlap->fetchAll(PDO::FETCH_KEY_PAIR);


    // --- BUKTI POTONG DATA  ---
    $stmtBupot = $db->prepare("SELECT jenis_pajak, SUM(dpp_bupot) as total_dpp FROM bukti_potong WHERE npwp = ? AND tahun = ? GROUP BY jenis_pajak");
    $stmtBupot->execute([$npwp, $tahun]);
    $bupot_data = $stmtBupot->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmtBupotPph = $db->prepare("SELECT jenis_pajak, SUM(nilai_pph) as total_pph FROM bukti_potong WHERE npwp = ? AND tahun = ? AND dikreditkan = 'ya' GROUP BY jenis_pajak");
    $stmtBupotPph->execute([$npwp, $tahun]);
    $bupot_pph_data = $stmtBupotPph->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $bupot_kredit = array_sum($bupot_pph_data);

    // --- SETORAN PAJAK ---
    $stmtSetoran = $db->prepare("SELECT jenis_pajak, map, kjs, SUM(nilai_setoran) as total_setoran FROM setoran_pajak WHERE npwp = ? AND tahun = ? GROUP BY jenis_pajak, map, kjs");
    $stmtSetoran->execute([$npwp, $tahun]);
    $setoran_data = $stmtSetoran->fetchAll(PDO::FETCH_ASSOC);
    
    $pph25_setor = 0;
    $pph29_setor = 0;
    $pphfinal_setor = 0;
    foreach ($setoran_data as $s) {
        $jp = strtoupper($s['jenis_pajak'] ?? '');
        $map = $s['map'] ?? '';
        $kjs = $s['kjs'] ?? '';
        
        if ((strpos($jp, 'FINAL') !== false || $map == '411128') && $kjs == '420') {
            $pphfinal_setor += $s['total_setoran'];
        } elseif ((strpos($jp, '25') !== false || $map == '411125' || $map == '411126') && $kjs == '100') {
            $pph25_setor += $s['total_setoran'];
        } elseif ((strpos($jp, '29') !== false || $map == '411126' || $map == '411125') && $kjs == '200') {
            $pph29_setor += $s['total_setoran'];
        }
    }

    $total_kredit_pajak = $bupot_kredit + $pph25_setor;

    // --- SPT DATA ---
    $stmtSpt = $db->prepare("SELECT * FROM spt_tahunan WHERE npwp = ? AND tahun = ?");
    $stmtSpt->execute([$npwp, $tahun]);
    $spt = $stmtSpt->fetch(PDO::FETCH_ASSOC) ?: [];

    
    // === AMBIL DATA SPT TAHUNAN ===
    $peredaran_usaha_spt = $spt["peredaran_usaha"] ?? 0;
    $persediaan_awal_spt = $spt["persediaan_awal"] ?? 0;
    $pembelian_spt = $spt["pembelian"] ?? 0;
    $persediaan_akhir_spt = $spt["persediaan_akhir"] ?? 0;
    $gaji_spt = $spt["gaji"] ?? 0;
    $biaya_operasional_spt = $spt["biaya_operasional"] ?? 0;
    $biaya_penyusutan_spt = $spt["biaya_penyusutan"] ?? 0;
    $penghasilan_luar_spt = $spt["penghasilan_luar_usaha"] ?? 0;
    $biaya_luar_spt = $spt["biaya_luar_usaha"] ?? 0;
    $ptkp = $spt["ptkp"] ?? 0;
    $bukan_objek_spt = $spt["penghasilan_bukan_pajak"] ?? 0;
    $koreksi_positif_spt = $spt["koreksi_fiskal_positif"] ?? 0;
    $koreksi_negatif_spt = $spt["koreksi_fiskal_negatif"] ?? 0;
    $kompensasi_spt = $spt["kompensasi_kerugian"] ?? 0;
    $norma_spt = $spt["norma"] ?? 0;
    $penghasilan_final_spt = $spt["penghasilan_final"] ?? 0;
    $kredit_bupot_spt = $spt["kredit_bukti_potong"] ?? 0;
    $kredit_setroan25_spt = $spt["kredit_pph_25"] ?? 0;
    $setoran29_spt = $spt["setoran_pph_29"] ?? 0;
    $setoran_pph_final_spt = $spt["setoran_pph_final"] ?? 0;
    $pph_terutang_spt = $spt["pajak_terutang"] ?? 0;

    $hpp_spt = $persediaan_awal_spt + $pembelian_spt - $persediaan_akhir_spt;
    $penghasilan_bruto_spt = $peredaran_usaha_spt-$hpp_spt;
    $beban_usaha_spt =  $biaya_operasional_spt + $biaya_penyusutan_spt + $gaji_spt;
    $penghasilan_netto_spt = $penghasilan_bruto_spt - $beban_usaha_spt+ $penghasilan_luar_spt - $biaya_luar_spt;
    
    if ($client['jenis_wp'] == 'OP' && $norma_spt > 0){
        $penghasilan_netto_spt = $pphfinal_setor*($norma_spt/100);
    }
    
    $koreksi_fiskal = $bukan_objek_spt + $penghasilan_final_spt + $koreksi_positif_spt - $koreksi_negatif_spt + $kompensasi_spt;
    
    $pkp_spt = $penghasilan_netto_spt - $koreksi_fiskal;


    // --- BOOKKEEPING DATA (from mapping_akun) ---
    $stmtBook = $db->prepare("
        SELECT kategori_akun, jenis, SUM(nominal) as total 
        FROM mapping_akun 
        WHERE npwp = ? AND tahun = ? 
        GROUP BY kategori_akun, jenis
    ");
    $stmtBook->execute([$npwp, $tahun]);
    $book_raw = $stmtBook->fetchAll(PDO::FETCH_ASSOC);
    $book = [];
    foreach($book_raw as $b) {
        $book[$b['kategori_akun']] = ($book[$b['kategori_akun']] ?? 0) + $b['total'];
    }

    // LABA RUGI
    
    $omset = $book['peredaran_usaha'] ?? 0;
    $pembelian = $book['pembelian'] ?? 0;
    $persediaan_akhir = $book['persediaan_akhir'] ?? 0;
    $persediaan_awal = $book['persediaan_awal'] ?? 0;
    $hpp =  $persediaan_awal + $pembelian - $persediaan_akhir;
    $laba_kotor = $omset - $hpp;
    $beban_gaji = $book['beban_gaji'] ?? 0;
    $beban_usaha = $book['beban_usaha'] ?? 0;
    $penyusutan = $book['beban_penyusutan'] ?? 0;
    $biaya_operasi =  $beban_usaha + $penyusutan + $beban_gaji; 
    $laba_usaha = $laba_kotor - $biaya_operasi;
    $pendapatan_luar_usaha = $book['pendapatan_lain'] ?? 0;
    $beban_luar_usaha = $book['beban_lain'] ?? 0;
    $diluar_usaha = $pendapatan_luar_usaha - $beban_luar_usaha;
    $laba_bersih = $laba_usaha + $diluar_usaha; 
    $laba_fiskal = $laba_bersih - $koreksi_fiskal;

     
    // NERACA 
    $kas_bank = $book['kas'] ?? 0;
    $piutang = $book['piutang'] ?? 0;
    $persediaan = $book['persediaan'] ?? 0;
    $aset_tetap = $book['aset_tetap'] ?? 0;
    $total_aktiva = $kas_bank + $piutang + $persediaan + $aset_tetap;
    
    $utang = $book['utang'] ?? 0;
    $utang_bank = $book['utang_bank'] ?? 0;
    $modal = $book['modal'] ?? 0;
    $total_utang = $utang + $utang_bank;
    $total_pasiva = $total_utang + $modal + $laba_bersih;

    $selisih_neraca = $total_aktiva - $total_pasiva;
    
    if ($selisih_neraca != 0) {
        $skor += 5;
        $catatan[] = "Terdapat selisih antara total aktiva dan pasiva menunjukkan ketidakseimbangan laporan keuangan";
    }


    $akumulasi_penyusutan = $book['akumulasi_penyusutan'] ?? 0;
    



    // --- BOOKKEEPING DATA LAST YEAR (from mapping_akun) ---
    $stmtBookLastYear = $db->prepare("
        SELECT kategori_akun, jenis, SUM(nominal) as total 
        FROM mapping_akun 
        WHERE npwp = ? AND tahun = ? 
        GROUP BY kategori_akun, jenis
    ");
    $stmtBookLastYear->execute([$npwp, $tahun - 1]);
    $book_last_year_raw = $stmtBookLastYear->fetchAll(PDO::FETCH_ASSOC);
    $book_last_year = [];
    foreach($book_last_year_raw as $b) {
        $book_last_year[$b['kategori_akun']] = ($book_last_year[$b['kategori_akun']] ?? 0) + $b['total'];
    }

    // PERSEDIAAN LAST YEAR
    $persediaan_akhir_last = $book_last_year['persediaan_akhir'] ?? 0;

    // NERACA 
    $kas_bank_last = $book_last_year['kas'] ?? 0;
    $piutang_last = $book_last_year['piutang'] ?? 0;
    $persediaan_last = $book_last_year['persediaan'] ?? 0;
    $aset_tetap_last = $book_last_year['aset_tetap'] ?? 0;
    $total_aktiva_last = $kas_bank_last + $piutang_last + $persediaan_last + $aset_tetap_last;
    
    $utang_last = $book_last_year['utang'] ?? 0;
    $utang_bank_last = $book_last_year['utang_bank'] ?? 0;
    $modal_last = $book_last_year['modal'] ?? 0;
    $total_utang_last = $utang_last + $utang_bank_last;
    $total_pasiva_last = $total_utang_last + $modal_last + $laba_bersih;
    $akumulasi_penyusutan_last = $book_last_year['akumulasi_penyusutan'] ?? 0;
    $gap_saldo_persediaan = $persediaan_awal - $persediaan_akhir_last;
    $gap_akumulasi_penyusutan = $akumulasi_penyusutan - $penyusutan - $akumulasi_penyusutan_last;
    
    // --- NETT CASHFLOW (from mapping_akun) ---
    $stmtCashFlow = $db->prepare("
        SELECT kategori_arus_kas, jenis, SUM(nominal) as total 
        FROM mapping_akun 
        WHERE npwp = ? AND tahun = ? 
        GROUP BY kategori_arus_kas, jenis
    ");
    $stmtCashFlow->execute([$npwp, $tahun]);
    $cash_flow_raw = $stmtCashFlow->fetchAll(PDO::FETCH_ASSOC);

    $arus_kas_operasi_keluar = 0;
    $arus_kas_operasi_masuk = 0; 
    $arus_kas_investasi = 0;
    $arus_kas_pendanaan = 0;

    $cash_flow = [];
    foreach($cash_flow_raw as $b) {
        $cash_flow[$b['kategori_arus_kas']] = ($cash_flow[$b['kategori_arus_kas']] ?? 0) + $b['total'];
    }
    $arus_kas_operasi_keluar = $cash_flow['arus_kas_operasi_keluar'] ?? 0;
    $arus_kas_operasi_masuk = $cash_flow['arus_kas_operasi_masuk'] ?? 0;
    $arus_kas_investasi = $cash_flow['arus_kas_investasi'] ?? 0;
    $arus_kas_pendanaan = $cash_flow['arus_kas_pendanaan'] ?? 0;

    
    // HITUNG ARUS KAS
    $kas_operasi = $arus_kas_operasi_masuk - $arus_kas_operasi_keluar;
    $kas_investasi =  $arus_kas_investasi -  $total_aktiva_last;
    $kas_pendanaan =  $arus_kas_pendanaan - ($total_utang_last + $modal_last);
    $kas_bersih = $kas_operasi + $kas_investasi + $kas_pendanaan;
    $kas_awal = $kas_bank - $kas_bersih; // Koreksi dengan saldo kas akhir untuk validasi
    $gap_saldo_kas =   $kas_bank_last - $kas_awal; // Selisih antara saldo kas awal yang dihitung dengan saldo kas akhir tahun lalu

    $m_kas = [
        'saldo_kas' => $kas_bank,
        'kas_operasi' => $kas_operasi,
        'kas_investasi' => $kas_investasi,
        'kas_pendanaan' => $kas_pendanaan,
        'kas_bersih' => $kas_bersih,
        'kas_awal_ini' => $kas_awal,
        'kas_akhir_lalu' => $kas_bank_last,
        'selisih_kas' => $gap_saldo_kas
    ];


    if ($m_kas['selisih_kas'] != 0) {
        $skor += 5;
        $catatan[] = "Tedapat selisih saldo kas dengan arus kas bersih menunjukkan potensi manipulasi laporan keuangan";
    }
    
    // 2. DATA MATCHING & RECONCILIATION LOGIC
    
    //  MATCHING OMSET
    $m_omset = [
        'spt' => $peredaran_usaha_spt ?? 0,
        'pembukuan' => $book['peredaran_usaha'] ?? 0,
        'faktur' => $faktur_keluaran,
        'bank' => $bank_in,
        'ilap' => $ilap_data['INCOME'] ?? 0,
        'bupot' => ($bupot_data['PPh_22'] ?? 0) + ($bupot_data['PPh_Final'] ?? 0),
        'adjusment_saldo_kas' => $peredaran_usaha_spt + $gap_saldo_kas // Adjusted omset considering cash flow gap
    ];
    $omset_max = max($m_omset);
    $omset_gap = $omset_max - $m_omset['spt'];
    $omset_v = $m_omset['spt'] + $omset_gap; // Adjusted omset for further calculations
    if ($omset_gap > 0) {
        $skor += 25;
        $catatan[] = "Potensi omset tidak dilaporkan : ".angka($omset_gap);
    }

    //  MATCHING PERSEDIAAN AWAL
    $m_persediaan_awal = [
        'spt' => $persediaan_awal_spt ?? 0,
        'pembukuan' => $persediaan_awal ?? 0,
        'saldo_akhir_lalu' => $persediaan_akhir_last ?? 0,
    ];
    $persediaan_awal_min = min($m_persediaan_awal);
    $persediaan_awal_gap = $persediaan_awal_min - $m_persediaan_awal['spt'];
    $persediaan_awal_v = $m_persediaan_awal['spt'] + $persediaan_awal_gap; 
    if ($persediaan_awal_gap < 0) {
        $skor += 5;
        $catatan[] = "Terdapat selisih saldo persediaan awal : ".angka($persediaan_awal_gap);
    }

    //  MATCHING PEMBELIAN
    $m_pembelian = [
        'spt' => $pembelian_spt ?? 0,
        'pembukuan' => $pembelian ?? 0,
    ];
    $pembelian_min = min($m_pembelian);
    $pembelian_gap =  $pembelian_min - $m_pembelian['spt'];
    $pembelian_v = $m_pembelian['spt'] + $pembelian_gap; // Adjusted pembelian for further calculations
        if ($pembelian_gap < 0) {
            $skor += 5;
            $catatan[] = "Indikasi pembelian tidak tercatat (potensi omset tidak dilaporkan) : ".angka($pembelian_gap);
        }
    $m_pembelian = [
        'spt' => $pembelian_spt ?? 0,
        'pembukuan' => $pembelian ?? 0,
        'faktur' => $faktur_masukan ?? 0,
        'bank' => $bank_out,
        'ilap' => $ilap_data['COST'] ?? 0
    ];
    

    //  MATCHING PERSEDIAAN AKHIR
    $m_persediaan_akhir = [
        'spt' => $persediaan_akhir_spt ?? 0,
        'pembukuan' => $persediaan_akhir ?? 0,
        'max_value' => $persediaan_awal_v + $pembelian_v // Adjusted for better matching
    ];
    $persediaan_akhir_min = min($m_persediaan_akhir);
    $persediaan_akhir_gap = $persediaan_akhir_min - $m_persediaan_akhir['spt'];
    $persediaan_akhir_v = $m_persediaan_akhir['spt'] + $persediaan_akhir_gap; // Adjusted persediaan akhir for further calculations
        if ($persediaan_akhir_gap < 0) {
            $skor += 5;
            $catatan[] = "Terdapat selisih saldo persediaan akhir : ".angka($persediaan_akhir_gap);
        }
    //  MATCHING HPP
    $m_hpp = [
        'spt' => $hpp_spt ?? 0,
        'pembukuan' => $hpp ?? 0,
        'faktur' => $persediaan_awal + $faktur_masukan - $persediaan_akhir,
    ];
    $hpp_min = min($m_hpp);
    $hpp_gap = $hpp_min - $m_hpp['spt'];
    $hpp_v = ($persediaan_awal_v + $pembelian_v - $persediaan_akhir_v) < 0 ? 0 : ($persediaan_awal_v + $pembelian_v - $persediaan_akhir_v); // Adjusted HPP for further calculations
        if ($hpp_gap < 0) {
            $skor += 5;
            $catatan[] = "Terdapat selisih HPP : ".angka($hpp_gap);
        }
    $m_hpp = [
        'spt' => $hpp_spt ?? 0,
        'pembukuan' => $hpp ?? 0,
        'faktur' => $persediaan_awal + $faktur_masukan - $persediaan_akhir,
        'bank' => $persediaan_awal + $bank_out - $persediaan_akhir,
        'ilap' => $persediaan_awal + ($ilap_data['COST'] ?? 0) - $persediaan_akhir
    ]; 

    // MATCHING LABA KOTOR
    $m_laba_kotor = [
        'spt' => $penghasilan_bruto_spt ?? 0,
        'pembukuan' => $laba_kotor ?? 0,
        'faktur' => $faktur_keluaran - $faktur_masukan,
    ];
    $laba_kotor_max = max($m_laba_kotor);
    $laba_kotor_gap = $laba_kotor_max - $m_laba_kotor['spt'];
    $laba_kotor_v = $omset_v - $hpp_v; // Adjusted laba kotor for further calculations
        if ($laba_kotor_gap > 0) {
            //$skor += 5;
            $catatan[] = "Terdapat selisih Laba Kotor : ".angka($laba_kotor_gap);
        }


    //  MATCHING BEBAN USAHA
    $m_beban_usaha = [
        'spt' => $beban_usaha_spt ?? 0,
        'pembukuan' => $biaya_operasi ?? 0
        ];
    $beban_usaha_min = min($m_beban_usaha['spt'], $m_beban_usaha['pembukuan']);
    $beban_usaha_gap = $beban_usaha_min - $m_beban_usaha['spt'];
    $beban_usaha_v = $m_beban_usaha['spt'] + $beban_usaha_gap; 
    if ($beban_usaha_gap > 0) {
        $skor += 10;
        $catatan[] = "Terdapat selisih saldo beban usaha : ".angka($beban_usaha_gap);
    }
    $m_beban_usaha = [
        'spt' => [
            'sum'=> $beban_usaha_spt ?? 0,
            'gaji' => $gaji_spt ?? 0,
            'biaya_operasional' => $biaya_operasional_spt ?? 0,
            'biaya_penyusutan' => $biaya_penyusutan_spt ?? 0
        ],
        'pembukuan' => [
            'sum' => $biaya_operasi ?? 0,
            'gaji' => $beban_gaji ?? 0,
            'biaya_operasional' => $beban_usaha ?? 0,
            'biaya_penyusutan' => $penyusutan ?? 0
        ],
        'bupot' => [
            'sum' => ($bupot_data['PPh_21'] ?? 0) + ($bupot_data['PPh_23'] ?? 0) + ($bupot_data['PPh_Final'] ?? 0),
            'gaji' => $bupot_data['PPh_21'] ?? 0,
            'jasa' => $bupot_data['PPh_23'] ?? 0,
            'sewa' => $bupot_data['PPh_Final'] ?? 0
        ]
    ];

    //  MATCHING PENGHASILAN DAN BEBAN LUAR USAHA

    $m_luar_usaha = [
        'spt' => $penghasilan_luar_spt- $biaya_luar_spt ?? 0,
        'pembukuan' => $diluar_usaha ?? 0
    ];

    $luar_usaha_max = max($m_luar_usaha['spt'], $m_luar_usaha['pembukuan']);
    $luar_usaha_gap = $luar_usaha_max - $m_luar_usaha['spt'];
    $luar_usaha_v = $m_luar_usaha['spt'] + $luar_usaha_gap; 
    if ($luar_usaha_gap != 0) {
        $skor += 5;
        $catatan[] = "Terdapat selisih saldo beban usaha : ".angka($luar_usaha_gap);
    }
    $m_luar_usaha = [
        'spt' => [
            'sum'=> $penghasilan_luar_spt- $biaya_luar_spt ?? 0,
            'penghasilan_luar' => $penghasilan_luar_spt ?? 0,
            'biaya_luar' => $biaya_luar_spt ?? 0
        ],
        'pembukuan' => [
            'sum' => $diluar_usaha ?? 0,
            'penghasilan_luar' => $pendapatan_luar_usaha ?? 0,
            'biaya_luar' => $beban_luar_usaha ?? 0
        ]   
    ];

    // MATCHING LABA BERSIH / PENGHASILAN NETTO
    $laba_bersih_v = $laba_kotor_v - $beban_usaha_v - $luar_usaha_v; // Adjusted laba kotor for further calculations
    if ($client['jenis_wp'] == 'OP' && $norma_spt > 0){
        $laba_bersih_v = $laba_bersih_v*($norma_spt/100); // Adjusted laba bersih dengan norma jika berlaku
    }
    $m_laba_bersih = [
        'spt' => $penghasilan_netto_spt ?? 0,
        'pembukuan' => $laba_bersih ?? 0,
    ];
    $laba_bersih_max = max($m_laba_bersih);
    $laba_bersih_gap = $laba_bersih_max - $m_laba_bersih['spt'];
    
        if ($laba_bersih_gap > 0) {
            //$skor += 5;
            $catatan[] = "Terdapat selisih Laba Bersih : ".angka($laba_bersih_gap);
        }

    // MATCHING KOREKSI FISKAL
    
    $m_koreksi_fiskal = [
        'sum' => $koreksi_fiskal ?? 0,
        'koreksi_positif' => $koreksi_positif_spt ?? 0,
        'koreksi_negatif' => $koreksi_negatif_spt ?? 0,
        'penghasilan_final' => $penghasilan_final_spt ?? 0,
        'penghasilan_bukan_objek' => $bukan_objek_spt ?? 0,
        'kompensasi_kerugian' => $kompensasi_spt ?? 0
    ];
    $koreksi_fiskal_v = $koreksi_fiskal; // Koreksi fiskal tidak diadjust karena sudah merupakan hasil akhir dari penyesuaian-penyesuaian sebelumnya

    // MATCHING PENGHASILA KENA PAJAK
    if($client['is_umkm'] == 1){
        if ($client['jenis_wp'] == 'OP'){
            $penghasilan_kena_pajak_v = $omset_v-500000000; // Penghasilan kena pajak umkm disesuaikan dengan omset - 500 juta karena menggunakan tarif final
        } else {
            $penghasilan_kena_pajak_v = $omset_v; // Penghasilan kena pajak umkm disesuaikan dengan omset karena menggunakan tarif final
        }
        
    } else {
        if ($client['jenis_wp'] == 'OP'){
            $penghasilan_kena_pajak_v = $laba_bersih_v - $koreksi_fiskal_v - $ptkp; // Penghasilan kena pajak disesuaikan dengan norma jika berlaku
        }else {
            $penghasilan_kena_pajak_v = $laba_bersih_v - $koreksi_fiskal_v; // Penghasilan kena pajak disesuaikan dengan laba bersih dan koreksi fiskal yang sudah diadjust
        }
    }

    if ($penghasilan_kena_pajak_v < 0) {
        $penghasilan_kena_pajak_v = 0; // Penghasilan kena pajak tidak boleh negatif
    }

    $m_penghasilan_kena_pajak = [
        'spt' => $pkp_spt ?? 0,
        'pembukuan' => $laba_bersih ?? 0
    ];
    
    // MATCHING KREDIT PAJAK 
    $pph25_setor_v = $pph25_setor; // Kredit pajak dari setoran pph 25 tidak diadjust karena sudah merupakan hasil akhir dari penyesuaian-penyesuaian sebelumnya
    $bupot_kredit_v = $bupot_kredit; // Kredit pajak dari bukti potong tidak diadjust karena sudah merupakan hasil akhir dari penyesuaian-penyesuaian sebelumnya
    $kredit_pajak_v = $total_kredit_pajak; // Total kredit pajak tidak diadjust karena sudah merupakan hasil akhir dari penyesuaian-penyesuaian sebelumnya
    

    $m_kredit_pajak = [
        'spt' => [
            'sum' => $kredit_bupot_spt+$kredit_setroan25_spt ?? 0,
            'bukti_potong' => $kredit_bupot_spt ?? 0,
            'pph_25' => $kredit_setroan25_spt ?? 0
        ],
        'setoran' => [
            'sum' => $total_kredit_pajak_v ?? 0,
            'bukti_potong' => $bupot_kredit_v ?? 0,
            'pph_25' => $pph25_setor_v
        ]
    ];
    $kredit_pajak_gap =  $m_kredit_pajak['spt']['sum'] - $kredit_pajak_v; // Gap antara kredit pajak di SPT dengan hasil penyesuaian kredit pajak
        
    // MATCHING SETORAN PAJAK
    if ($client['is_umkm'] == 1){
        $setoran_pajak_v = $pphfinal_setor ?? 0;
    }else {
        $setoran_pajak_v = $pph29_setor ?? 0;    
    }
    
    $m_setoran_pajak = [
        'spt' => [
            'pph_29' => $pph29_setor ?? 0,
            'pph_final' => $setoran_pph_final_spt ?? 0
        ],
        'setoran' => [
            'pph_29' => $pph29_setor ?? 0,
            'pph_final' => $pphfinal_setor ?? 0
        ]
    ];


    // HITUNG PAJAK WAJAR & POTENSI KURANG BAYAR
    $pajak_terutang_spt = $pph_terutang_spt ?? 0;
    list($pph_wajar, $tarif_ket) = hitungPajakWajar($penghasilan_kena_pajak_v, $client['jenis_wp'], $omset_v, $client['is_umkm']);
    $pph_kb_lb = $pph_wajar - $kredit_pajak_v - $setoran_pajak_v;
    if ($pph_kb_lb > 0) {
            $skor += 50;
            $catatan[] = "Terdapat potensi pajak yang belum disetorkan : ".angka($pph_kb_lb);
        }

    // C. Kewajaran Saldo ASET
    $m_persediaan = [
        'akhir_tahun_Lalu' => $book_last_year['persediaan_akhir'] ?? 0,
        'awal_tahun_ini' => $book['persediaan_awal'] ?? 0,
        'gap' => $book['persediaan_awal'] ?? 0 - $book_last_year['persediaan_akhir'] ?? 0
    ];

    if ($m_persediaan['gap'] != 0) {
        $skor += 5;
        $catatan[] = "Terdapat selisish Saldo Persediaan Awal dengan Persediaan Akhir tahun lalu menunjukkan potensi manipulasi laporan keuangan";
    }

    $m_penyusutan = [
        'akm_tahun_Lalu' => $book_last_year['akumulasi_penyusutan'] ?? 0,
        'penyusutan_tahun_ini' => $book['beban_penyusutan'] ?? 0,
        'akm_tahun_ini' => $book['akumulasi_penyusutan'] ?? 0,
        'gap' => $book['akumulasi_penyusutan'] ?? 0 - ($book_last_year['akumulasi_penyusutan'] ?? 0 + $book['beban_penyusutan'] ?? 0)
    ];

    if ($m_penyusutan['gap'] != 0) {
        $skor += 5;
        $catatan[] = "Terdapat selisish Saldo Akumulasi Penyusutan dengan Beban Penyusutan tahun ini menunjukkan potensi manipulasi laporan keuangan";
    }
    
    $m_aset = [
        'tahun_Lalu' => $book_last_year['aset_tetap'] ?? 0,
        'tahun_ini' => $book['aset_tetap'] ?? 0,
        'arus_kas_investasi' => $arus_kas_investasi ?? 0,
        'gap' => $book['aset_tetap'] ?? 0 - $book_last_year['aset_tetap']  ?? 0 - $arus_kas_investasi ?? 0
    ];
    
    $m_utang = [
        'tahun_Lalu' => $total_utang_last,
        'tahun_ini' => $total_utang,
        'arus_kas_pendanaan' => $arus_kas_pendanaan,
        'gap' => $total_utang - $total_utang_last - $arus_kas_pendanaan
    ];

    $m_modal = [
        'tahun_Lalu' => $modal_last,
        'tahun_ini' => $modal,
        'laba_tahun_ini' => $laba_bersih,
        'gap' => $modal - $laba_bersih - $modal_last
    ];

    

    if ($m_aset['gap'] > 0 || $m_utang['gap'] > 0 || $m_modal['gap'] > 0) {
        $skor += 5;
        $catatan[] = "Ketidaksesuaian Saldo Asset, Utang dan Modal menunjukkan potensi manipulasi laporan keuangan";
    }

    

    
    


//=== HITUNG RASIO =====
    $stmtBenchmark = $db->prepare("SELECT * FROM benchmark_klu WHERE klu = ?");
    $stmtBenchmark->execute([$client['klu']]);
    $benchmark = $stmtBenchmark->fetch(PDO::FETCH_ASSOC) ?: [];

    $gpm = $omset > 0 ? ($laba_kotor / $omset) * 100 : 0;
    $opm = $omset > 0 ? ($laba_usaha / $omset) * 100 : 0;
    $npm = $omset > 0 ? ($laba_bersih_v  / $omset) * 100 : 0;
    $cttor = $omset > 0 ? (($total_kredit_pajak + $pph_wajar) / $omset) * 100 : 0;
    $cttor_wajar = $omset > 0 ? ($pph_wajar / $omset) * 100 : 0;
    $rasio_gaji_omset = $omset > 0 ? ($beban_gaji / $omset) * 100 : 0;
    $der = $modal > 0 ? ($utang / $modal) : 0;
    $current_ratio = $total_utang > 0 ? ($total_aktiva / $total_utang) : 0;

    
    $gpm_bench = $benchmark['gpm'] ?? 20;
    $opm_bench = $benchmark['opm'] ?? 10;
    $npm_bench = $benchmark['npm'] ?? 5;
    $cttor_bench  = $benchmark['cttor'] ?? 1;
    $rasio_gaji_omset_bench = $benchmark['ppm'] ?? 20;
    $der_bench = $benchmark['der'] ?? 4;
    $current_ratio_bench = $benchmark['current_ratio'] ?? 1;
    
    // 3. SCORING & FISCAL RECOMMENDATION
    //$skor = 0;
    //$catatan = [];
    
    
    
    
    if ($m_beban_usaha['pembukuan']['gaji'] > ($m_beban_usaha['bupot']['gaji']) && $m_beban_usaha['pembukuan']['gaji'] > 0) {
        $skor += 10;
        $catatan[] = "Selisih Biaya Gaji belum Dilaporkan SPT PPh 21";
    }
    if ($m_beban_usaha['pembukuan']['jasa'] > ($m_beban_usaha['bupot']['jasa'] * 0.1) && $m_beban_usaha['pembukuan']['jasa'] > 0) {
        $skor += 10;
        $catatan[] = "Selisih Biaya Jasa belum Dilaporkan SPT PPh Unifikasi";
    }
    if ($gap_akumulasi_penyusutan != 0) {
        $skor += 10;
        $catatan[] = "Selisih saldo Akumulasi Penyusutan menunjukkan potensi manipulasi aset tetap";
    }
    if ($gap_saldo_persediaan != 0) {
        $skor += 10;
        $catatan[] = "Selisih saldo Persediaan menunjukkan potensi manipulasi persediaan";
    }
    if ($gap_saldo_kas != 0) {
        $skor += 10;
        $catatan[] = "Selisih saldo Kas menunjukkan potensi manipulasi kas atau transaksi tidak tercatat";
    }


     //penghitungan akhir skor dan level risiko

    // Ambil skor_risiko saat ini
    $stmtSkor = $db->prepare("SELECT skor_validasi FROM hasil_analisis WHERE npwp = ? AND tahun = ? ORDER BY id DESC LIMIT 1");
    $stmtSkor->execute([$npwp, $tahun]);
    $rowSkor = $stmtSkor->fetch(PDO::FETCH_ASSOC);
    $skor_validasi = $rowSkor['skor_validasi'] ?? 0;

    // Hitung Skor Final (75% skor_risiko + 25% skor_validasi)
    $skor_final = ($skor * 0.75) + ($skor_validasi * 0.25);
    $skor_final = round($skor_final);
    
    $skor_final = min(100, $skor_final);
    $level = $skor_final >= 70 ? 'TINGGI' : ($skor_final >= 40 ? 'SEDANG' : 'RENDAH');
    
    // 4. CONSTRUCT RESULT DATA (EXPANDED)
    $result_data = [
        'profil' => $client,
        'spt' => $spt,
        'faktur' => $faktur_data,
        'bupot' => $bupot_data,
        'bupot_pph' => $bupot_pph_data,
        'setoran' => $setoran_data,
        'bank' => $bank_data,
        'ilap' => $ilap_data,
        'book' => $book,
        'book_last_year' => $book_last_year,
        'kewajaran_saldo' => [
            'arus_kas' => $m_kas,
            'persediaan' => $m_persediaan,
            'penyusutan' => $m_penyusutan,
            'aset' => $m_aset,
            'utang' => $m_utang,
            'modal' => $m_modal,
            'total_aktiva' => $total_aktiva,
            'total_pasiva' => $total_pasiva,
            'selisih' => $selisih_neraca
        ],
        'simulation' => [
            'penjualan' => [
                'value' => $omset_v, 
                'matching' => $m_omset,
                'gap' => $omset_gap
            ],
            'persediaan_awal' => [
                'value' => $persediaan_awal_v,
                'matching' => $m_persediaan_awal,
                'gap' => $persediaan_awal_gap
            ],
            'pembelian' => [
                'value' => $pembelian_v,
                'matching' => $m_pembelian,
                'gap' => $pembelian_gap
            ],
            'persediaan_akhir' => [
                'value' => $persediaan_akhir_v,
                'matching' => $m_persediaan_akhir,
                'gap' => $persediaan_akhir_gap
            ],
            'hpp' => [
                'value' => $hpp_v,
                'matching' => $m_hpp,
                'gap' => $hpp_gap
            ],
            'laba_kotor' => [
                'value' => $laba_kotor_v,
                'matching' => $m_laba_kotor,
                'gap' => $laba_kotor_gap
            ],  
            'beban_usaha' => [
                'value' => $beban_usaha_v,
                'matching' => $m_beban_usaha,
                'gap' => $beban_usaha_gap
            ],
            'luar_usaha' => [
                'value' => $luar_usaha_v,
                'matching' => $m_luar_usaha,
                'gap' => $luar_usaha_gap
            ],
            'laba_bersih' => [
                'value' => $laba_bersih_v,
                'matching' => $m_laba_bersih,
                'gap' => $laba_bersih_gap
            ],
            'penghasilan_netto' => [
                'value' => $laba_bersih_v,
                'matching' => $m_laba_bersih
            ],
            'koreksi_fiskal' => [
                'value' => $koreksi_fiskal_v,
                'matching' => $m_koreksi_fiskal
            ],
            'norma' => [
                'value' => $norma_spt,
                'matching' => $norma_spt
            ],
            'ptkp' => [
                'value' => $client['jenis_wp'] == 'OP' ? $ptkp : 0,
                'matching' => $ptkp
            ],
            'pkp' => [
                'value' => $penghasilan_kena_pajak_v,
                'matching' => $m_penghasilan_kena_pajak
            ],
            'pajak_terutang' => [
                'value' => $pph_wajar,
                'tarif_ket' => $tarif_ket,
                'pph_terutang_spt' => $pph_terutang_spt ?? 0
            ],
            'kredit_pajak' => [
                'value' => $kredit_pajak_v,
                'matching' => $m_kredit_pajak
            ],
            'setoran' => [
                'value' => $setoran_pajak_v,
                'matching' => $m_setoran_pajak
            ],
            'pph_kb_lb' => $pph_kb_lb
        ],
        'rasio' => [
            'gpm' => [
                'value' => $gpm,
                'benchmark' => $gpm_bench,
                'ket' => 'Gross Profit Margin (GPM) menunjukkan seberapa besar margin laba kotor dari penjualan. GPM yang sangat tinggi atau rendah dibandingkan dengan benchmark industri dapat menunjukkan potensi manipulasi penjualan atau biaya.'
            ],
            'opm' => [
                'value' => $opm,
                'benchmark' => $opm_bench,
                'ket' => 'Operating Profit Margin (OPM) menunjukkan seberapa besar margin laba operasional dari penjualan. OPM yang sangat tinggi atau rendah dibandingkan dengan benchmark industri dapat menunjukkan potensi manipulasi biaya operasional.'
            ],
            'npm' => [
                'value' => $npm,
                'benchmark' => $npm_bench,
                'ket' => 'Net Profit Margin (NPM) menunjukkan seberapa besar margin laba bersih dari penjualan. NPM yang sangat tinggi atau rendah dibandingkan dengan benchmark industri dapat menunjukkan potensi manipulasi pendapatan atau biaya.'
            ],
            'cttor' => [
                'value' => $cttor,
                'benchmark' => $cttor_bench,
                'ket' => 'Corporate Tax to Turn Over Ratio adalah metrik perpajakan yang mengukur rasio antara Pajak Penghasilan (PPh) terutang dengan total penjualan (omzet) suatu perusahaan. Semakin rendah nilai CTTOR dibanding benchmark industri semakin besar potensi pajak yang tidak dilaporkan atau kurang bayar.'
            ],
            'cttor_wajar' => [
                'value' => $cttor_wajar,
                'benchmark' => $cttor_bench,
                'ket' => 'CTTOR Wajar adalah  rasio antara Pajak Penghasilan (PPh) yang sudah dibayar dengan total penjualan (omzet). Semakin rendah nilai CTTOR dibanding benchmark industri semakin besar potensi pajak yang masih harus dibayar.'
            ],
            'rasio_gaji' => [
                'value' => $rasio_gaji_omset,
                'benchmark' => $rasio_gaji_omset_bench,
                'ket' => 'Rasio Gaji terhadap Omset menunjukkan seberapa besar beban gaji dibandingkan dengan omset perusahaan. Rasio yang sangat tinggi atau rendah dibandingkan dengan benchmark industri dapat menunjukkan kewajaran beban tenaga kerja.'
            ],
            'der' => [
                'value' => $der,
                'benchmark' => $der_bench,
                'ket' => 'Debt to Equity Ratio (DER) menunjukkan tingkat ketergantungan perusahaan terhadap utang. Rasio yang sangat tinggi atau rendah dibandingkan dengan benchmark industri dapat menunjukkan potensi masalah solvabilitas.'
            ],
            'cr' => [
                'value' => $current_ratio,
                'benchmark' => $current_ratio_bench,
                'ket' => 'Current Ratio menunjukkan kemampuan perusahaan untuk membayar kewajiban jangka pendek dengan aset lancar. Rasio yang sangat tinggi atau rendah dibandingkan dengan benchmark industri dapat menunjukkan potensi masalah likuiditas.'
            ]
        ],
        'analysis' => [
            'skor_risiko' => $skor,
            'skor_validasi' => $skor_validasi,
            'skor_final' => $skor_final,
            'level' => $level,
            'catatan' => $catatan
        ]
    ];

   


    // SIMPAN HASIL
    $db->prepare("DELETE FROM hasil_analisis WHERE npwp=? AND tahun=?")->execute([$npwp, $tahun]);
    $stmtSave = $db->prepare("INSERT INTO hasil_analisis 
        (npwp, tahun, data_json, skor_validasi,skor_risiko, skor_final, level_risiko, catatan_risiko, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

   

    $stmtSave->execute([
        $npwp, 
        $tahun, 
        json_encode($result_data), 
        $skor_validasi, 
        $skor, // Initial skor_final sama dengan skor_risiko sebelum ada validasi lapangan
        $skor_final,
        $level, 
        implode(". ", $catatan) ?: "Data dalam batas kewajaran", 
        $_SESSION['nama'] ?? 'System'
    ]);

    $db->commit();

    catatLogAktivitas($db, $_SESSION['user_id'], $_SESSION['username'] ?? 'System', 'Engine Analisa', "Analisa Risiko NPWP: $npwp Tahun: $tahun. Hasil: $level ($skor)");

    header("Location: hasil_analisa.php?npwp=$npwp&tahun=$tahun");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Error Engine: " . $e->getMessage());
}
