<?php
/**
 * SHARP - Upload Utilities
 */

/**
 * Helper Auto-Kategorisasi berdasarkan teks keterangan (dari manajemen_bank.php)
 */
function autoKategorisasi(string $ket) {
    $ket = strtolower($ket);
    if (preg_match('/bayar|invoice|tagihan|customer|penjualan|pelunasan piutang|transfer masuk/i', $ket)) return 'PENJUALAN';
    if (preg_match('/supplier|vendor|beli|bayar barang|pelunasan hutang/i', $ket)) return 'PEMBELIAN';
    if (preg_match('/gaji|salary|upah|thr|bonus karyawan/i', $ket)) return 'GAJI';
    if (preg_match('/konsultan|fee|honor|komisi|jasa|professional/i', $ket)) return 'JASA';
    if (preg_match('/pph|ppn|ssp|pb1|pajak|skp/i', $ket)) return 'PAJAK';
    if (preg_match('/listrik|air|telp|sewa|internet|bbm|tol|parkir/i', $ket)) return 'OPERASIONAL';
    if (preg_match('/transfer ke|transfer dari|rtgs|kliring|antar rekening/i', $ket)) return 'TRANSFER';
    return 'LAINNYA';
}

/**
 * Logika Auto Klasifikasi Akun & Arus Kas
 */ 
function klasifikasiAkun(string $nama_akun): array {
    $nama = strtoupper($nama_akun);
    
    $rules = [
                
                'piutang'=>['PIUTANG JANGKA PANJANG','BEBAN DIBAYAR DI MUKA','PIUTANG RAGU-RAGU','PIUTANG LAIN-LAIN','PIUTANG USAHA PIHAK YANG MEMPUNYAI HUBUNGAN ISTIMEWA','PIUTANG USAHA PIHAK KETIGA','PIUTANG','BAYAR DIMUKA','UANG MUKA PEMBELIAN','PIUTANG USAHA'],
                'utang_bank'=>['UTANG BANK','PINJAMAN BANK','HUTANG BANK','HUTANG BUNGA','BAGIAN HUTANG JANGKA PANJANG YANG JATUH TEMPO DALAM TAHUN BERJALAN'],
                'utang'=>['UTANG','HUTANG','KEWAJIBAN','PINJAMAN','HUTANG USAHA','HUTANG PAJAK','HUTANG DEVIDEN','BIAYA YANG MASIH HARUS DIBAYAR','UANG MUKA PELANGGAN','KEWAJIBAN LANCAR LAINNYA','KEWAJIBAN PAJAK','KEWAJIBAN TIDAK LANCAR LAINNYA'],
                'pembelian' => ['PEMBELIAN', 'PEMBELIAN BARANG', 'PEMBELIAN BAHAN', 'HARGA POKOK', 'HPP','BELI','BEBAN POKOK PENJUALAN','BEBAN POKOK PRODUKSI'],
                'persediaan_akhir' => ['PERSEDIAAN AKHIR', 'SALDO AKHIR PERSEDIAAN'],
                'persediaan_awal' => ['PERSEDIAAN AWAL', 'SALDO AWAL PERSEDIAAN'],
                'persediaan'=>['PERSEDIAAN'],
                'aset_tetap'=>['AKTIVA TIDAK LANCAR LAINNYA','AKTIVA TETAP LAINNYA','TANAH DAN BANGUNAN','AKUM. PENYUSUTAN','AKUMULASI PENYUSUTAN','TANAH','BANGUNAN','ASET TETAP','KENDARAAN'],
                'modal'=>['MODAL','LABA DITAHAN','SAHAM','DEVIDEN','MODAL SAHAM','AGIO','TAMBAHAN MODAL DISETOR','LABA DITAHAN','EKUITAS'],
                'pendapatan_lain'=>['PENDAPATAN LAIN','JASA GIRO','DILUAR USAHA','PENGHASILAN LAIN','MANFAAT PAJAK','PENGHASILAN/(BEBAN) LAIN','BAGIAN LABA (RUGI) PERUSAHAAN ASOSIASI','PENDAPATAN NON OPERASIONAL','PENDAPATAN BUNGA','PENDAPATAN SEWA','PENDAPATAN ROYALTI','PENDAPATAN DIVIDEN','PENGHASILAN DI LUAR USAHA','PENDAPATAN LUAR USAHA','PENGHASILAN LUAR USAHA'],
                'peredaran_usaha'=>['JUAL','PENJUALAN','OMSET','PENJUALAN BERSIH','PENJUALAN NETTO','PENJUALAN USAHA','PENJUALAN DAGANG','PENJUALAN PRODUK','PENJUALAN JASA','PENGHASILAN USAHA','PENGHASILAN JASA','PENDAPATAN USAHA','PENDAPATAN JASA','PENGHASILAN'],
                'beban_lain'=>['BEBAN BUNGA','BEBAN ADM','BEBAN PAJAK','BIAYA ADMINISTRASI','BIAYA ADM','BIAYA BUNGA','BIAYA LUAR USAHA','BEBAN DILUAR USAHA','BEBAN NON OPERASIONAL','BIAYA DILUAR USAHA','BEBAN DILUAR USAHA','PAJAK'],
                'beban_gaji'=>['GAJI','UPAH','HONOR','BONUS','TUNJANGAN','REMUNERASI','BIAYA KARYAWAN','BEBAN KARYAWAN'],
                'beban_usaha'=>['BEBAN USAHA','SEWA','LISTRIK','TELEPON','INTERNET','BIAYA','BEBAN UMUM DAN ADMINISTRASI'],
                'penyusutan'=>['PENYUSUTAN','AMORTISASI','DEPRESIASI'],
                'koreksi_fiskal_positif'=>['FISKAL POSITIF','KOREKSI PENYUSUTAN','KOREKSI PERSEDIAAN','KOREKSI ASET','KOREKSI AKTIVA','KOREKSI PENDAPATAN','KOREKSI OMSET'],
                'koreksi_fiskal_negatif'=>['FISKAL NEGATIF','PENGHASILAN FINAL','HIBAH','WARISAN','BUKAN OBJEK PAJAK','PTKP','KOREKSI BIAYA','KOREKSI BEBAN'],
                'kas'=>['KAS','BANK','KAS DAN SETARA KAS'],
                'aset_lancar'=>['AKTIVA LANCAR LAINNYA','ASET LANCAR','INVESTASI SEMENTARA'],
                'aset_tidak_berwujud'=>['AKUM. AMORTISASI','AKUMULASI AMORTISASI','HARTA TIDAK BERWUJUD','AKTIVA PAJAK TANGGUHAN']    
            ];
    

    $kategori = 'Lainnya';
    foreach ($rules as $key => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($nama, $kw) !== false) {
                $kategori = $key;
                break 2;
            }
        }
    }       

    $arus_kas = 'abaikan';
    $investasi = ['aset_tetap', 'akumulasi_penyusutan'];
    $pendanaan = ['utang_pendek', 'utang_panjang', 'modal'];
    $operasi_masuk = ['peredaran_usaha', 'pendapatan_luar_usaha','pendapatan_lain',];
    $operasi_keluar = [ 'pembelian', 'beban_gaji', 'beban_usaha', 'beban_luar_usaha','beban_lain'];

    if (in_array($kategori, $investasi)) $arus_kas = 'arus_kas_investasi';
    elseif (in_array($kategori, $pendanaan)) $arus_kas = 'arus_kas_pendanaan';
    elseif (in_array($kategori, $operasi_masuk)) $arus_kas = 'arus_kas_operasi_masuk';
    elseif (in_array($kategori, $operasi_keluar)) $arus_kas = 'arus_kas_operasi_keluar';
    else $arus_kas = 'abaikan';

    return ['kategori' => $kategori, 'arus_kas' => $arus_kas];
}

/**
 * Logika Klasifikasi Data ILAP
 */
function klasifikasiIlap(string $ket): string {
    $ket = strtolower($ket);
    
    if (preg_match('/income|gaji|bonus|tunjangan|renumerasi|penghasilan|pendapatan|royalti|jasa|penjualan|dasar pengenaan|terima uang|pemasukan/i', $ket)) return 'INCOME';
    if (preg_match('/cost|biaya|tagihan|beli|bayar|pelunasan|beban|pengeluaran/i', $ket)) return 'COST';
    if (preg_match('/aset|asset|investasi|tabungan|deposito|obligasi|mobil|tanah|rumah|kapal|motor|inventaris|aktiva/i', $ket)) return 'ASSET';
    if (preg_match('/liability|utang|hutang|pinjaman|kredit|agunan|angsuran|hipotik|leasing/i', $ket)) return 'LIABILITY';
    if (preg_match('/modal|saham|equity|penyertaan|ahu/i', $ket)) return 'EQUITY';
    
    return 'INCOME'; // Default
}

/**
 * Parsing file CSV ke Array
 */
function parseCSV(string $file) {
    $data = [];
    if (($handle = fopen($file, "r")) !== FALSE) {
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $data[] = $row;
        }
        fclose($handle);
    }
    return $data;
}

/**
 * Parsing file XLSX/XLS ke Array
 */
function parseExcel(string $file, string $ext) {
    if ($ext === 'xlsx') {
        return parseXLSX($file);
    } elseif ($ext === 'xls') {
        return parseXLS_XML($file);
    }
    return [];
}

/**
 * Parsing XLSX (ZIP based)
 */
function parseXLSX(string $file) {
    if (!class_exists('ZipArchive')) {
        throw new Exception("Ekstensi PHP ZipArchive tidak aktif. Silakan hubungi administrator atau gunakan format CSV.");
    }

    $zip = new ZipArchive;
    if ($zip->open($file) === TRUE) {
        $sharedStrings = [];
        $ssPart = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssPart) {
            $xml = simplexml_load_string($ssPart);
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $t = "";
                    foreach($si->r as $r) { $t .= (string)$r->t; }
                    $sharedStrings[] = $t;
                }
            }
        }

        $sheetPart = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetPart) return [];

        $xml = simplexml_load_string($sheetPart);
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            $cellIdx = 0;
            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];
                $col = preg_replace('/[0-9]/', '', $ref);
                $colIdx = 0;
                $len = strlen($col);
                for($i=0; $i<$len; $i++) {
                    $colIdx = $colIdx * 26 + (ord($col[$i]) - 64);
                }
                $colIdx--; 

                while($cellIdx < $colIdx) {
                    $rowData[] = "";
                    $cellIdx++;
                }

                $v = (string)$cell->v;
                if (isset($cell['t']) && $cell['t'] == 's') {
                    $v = $sharedStrings[$v] ?? $v;
                }
                $rowData[] = $v;
                $cellIdx++;
            }
            $rows[] = $rowData;
        }
        $zip->close();
        return $rows;
    }
    return [];
}

/**
 * Parsing XLS (XML Spreadsheet 2003 format)
 */
function parseXLS_XML(string $file): array {
    $content = file_get_contents($file);
    // Cek apakah ini format XML Spreadsheet 2003 (yang digunakan oleh template kita)
    if (strpos($content, '<?xml') === false || strpos($content, 'schemas-microsoft-com:office:spreadsheet') === false) {
        throw new Exception("Format .xls (binary) tidak didukung secara langsung. Harap simpan file Anda sebagai <b>.xlsx</b> (Excel Modern) atau gunakan format <b>.csv</b>.");
    }

    $xml = simplexml_load_string($content);
    if (!$xml) throw new Exception("Gagal membaca struktur XML file .xls.");
    
    $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
    
    $rows = [];
    $worksheets = $xml->xpath('//ss:Worksheet');
    if (empty($worksheets)) {
        // Fallback jika tidak menggunakan prefix ss di root tapi ada namespace default
        $xml->registerXPathNamespace('default', 'urn:schemas-microsoft-com:office:spreadsheet');
        $worksheets = $xml->xpath('//Worksheet');
        if (empty($worksheets)) throw new Exception("Data tidak ditemukan di dalam file Excel (Sheet kosong).");
    }

    $worksheet = $worksheets[0];
    foreach ($worksheet->Table->Row as $row) {
        $rowData = [];
        foreach ($row->Cell as $cell) {
            $rowData[] = (string)$cell->Data;
        }
        $rows[] = $rowData;
    }
    return $rows;
}

/**
 * S3 Upload Helper (Minimal Implementation for S3-compatible Storage)
 */
/**
 * S3 Upload Helper (Presigned URL Support)
 */
function uploadToS3(string $filePath, string $fileName): bool {
    $bucket = S3_BUCKET;
    $accessKey = S3_KEY;
    $secretKey = S3_SECRET;
    $endpoint = S3_ENDPOINT;
    
    $region = 'id-jakarta';
    $service = 's3';
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    $content = file_get_contents($filePath);
    $payloadHash = hash('sha256', $content);
    
    // Path-style: /bucket/filename
    $canonicalUri = '/' . $bucket . '/' . $fileName;
    $canonicalQueryString = '';
    $host = $endpoint;
    
    $canonicalHeaders = "host:" . $host . "\n" . "x-amz-content-sha256:" . $payloadHash . "\n" . "x-amz-date:" . $timestamp . "\n";
    $signedHeaders = "host;x-amz-content-sha256;x-amz-date";
    
    $canonicalRequest = "PUT\n" . $canonicalUri . "\n" . $canonicalQueryString . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
    
    $credentialScope = $date . "/" . $region . "/" . $service . "/aws4_request";
    $stringToSign = "AWS4-HMAC-SHA256\n" . $timestamp . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
    
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    $authorizationHeader = "AWS4-HMAC-SHA256 Credential=" . $accessKey . "/" . $credentialScope . ", SignedHeaders=" . $signedHeaders . ", Signature=" . $signature;
    
    $headers = [
        "Host: " . $host,
        "x-amz-content-sha256: " . $payloadHash,
        "x-amz-date: " . $timestamp,
        "Authorization: " . $authorizationHeader,
        "Content-Type: " . (mime_content_type($filePath) ?: 'application/octet-stream')
    ];
    
    $url = "https://" . $host . $canonicalUri;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return ($httpCode == 200 || $httpCode == 201) ? true : false;
    }

// getPresignedUrl() is now centrally managed in config_s3.php, which is included via config.php

/**
 * Memproses data ke database sesuai modul
 */
function processUploadData(PDO $db, string $modul, string $npwp, string $tahun, array $rows) {
    if (empty($rows)) {
        throw new Exception("Data kosong atau file tidak dapat dibaca.");
    }

    // Ambil Header dan bersihkan (hapus whitespace, lowercase, hapus underscore/spasi untuk matching fleksibel)
    $rawHeader = array_shift($rows);
    $header = array_map(function(string $h): string {
        return strtolower(str_replace([' ', '_'], '', trim($h)));
    }, $rawHeader);

    // Helper untuk mencari index header secara fleksibel
    $findIdx = function(array $targets) use ($header) {
        foreach ($targets as $t) {
            $normalizedT = strtolower(str_replace([' ', '_'], '', $t));
            $idx = array_search($normalizedT, $header);
            if ($idx !== false) return $idx;
        }
        return false;
    };

    $db->beginTransaction();
    try {
        $processedCount = 0;

        if ($modul === 'bank') {
            $thnIdx = $findIdx(['tahun', 'year']);
            $tglIdx = $findIdx(['tanggal', 'tgl', 'date']);
            $ketIdx = $findIdx(['keterangan', 'desc', 'description']);
            $jnsIdx = $findIdx(['jenis', 'type']);
            $nomIdx = $findIdx(['nominal', 'amount', 'jumlah']);
            $sldIdx = $findIdx(['saldo', 'balance']);

            if ($tglIdx === false || $nomIdx === false) {
                throw new Exception("Kolom wajib 'tanggal' atau 'nominal' tidak ditemukan. Header terbaca: " . implode(', ', $rawHeader));
            }

            $stmt = $db->prepare("INSERT INTO mutasi_bank (npwp, tahun, tanggal, keterangan, jenis, nominal, saldo, kategori, sumber_file, created_at) VALUES (?,?,?,?,?,?,?,?,'UPLOAD_CENTRAL',NOW())");

            foreach ($rows as $data) {
                if (empty($data[$tglIdx]) || !isset($data[$nomIdx])) continue;
                
                // Fallback Tahun
                $itemTahun = ($thnIdx !== false && !empty($data[$thnIdx])) ? $data[$thnIdx] : $tahun;
                
                $tgl = date('Y-m-d', strtotime(str_replace('/', '-', $data[$tglIdx])));
                $kategori = autoKategorisasi($data[$ketIdx] ?? '');
                $stmt->execute([
                    $npwp, $itemTahun, $tgl, $data[$ketIdx] ?? '', 
                    strtoupper($data[$jnsIdx] ?? 'KREDIT'), 
                    str_replace([',', ' '], '', $data[$nomIdx]), 
                    str_replace([',', ' '], '', $data[$sldIdx] ?? 0), 
                    $kategori
                ]);
                $processedCount++;
            }
        } elseif ($modul === 'faktur') {
            $thnIdx   = $findIdx(['tahun', 'year']);
            $noIdx    = $findIdx(['no_faktur', 'nomor_faktur', 'invoice_no']);
            $jnsIdx   = $findIdx(['jenis_faktur', 'jenis', 'faktur_type']);
            $tglIdx   = $findIdx(['tgl_faktur', 'tanggal', 'date']);
            $statIdx  = $findIdx(['status']);
            $masaIdx  = $findIdx(['masa_pajak', 'masa']);
            $npwpIdx  = $findIdx(['npwp_lawan', 'npwp_pembeli', 'npwp_penjual']);
            $namaIdx  = $findIdx(['nama_lawan', 'nama_pembeli', 'nama_penjual']);
            $dppIdx   = $findIdx(['dpp', 'dasar_pengenaan_pajak']);
            $ppnIdx   = $findIdx(['ppn', 'pajak_pertambahan_nilai']);

            if ($noIdx === false) throw new Exception("Kolom 'no_faktur' tidak ditemukan.");

            $stmt = $db->prepare("INSERT INTO faktur_pajak (npwp, tahun, jenis_faktur, no_faktur, tgl_faktur, status, masa_pajak, npwp_lawan, nama_lawan, dpp, ppn, dilaporkan_spt, dikreditkan, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'ya', 'ya', NOW())");

            foreach ($rows as $data) {
                if (empty($data[$noIdx])) continue;
                $tglStr = str_replace('/', '-', $data[$tglIdx] ?? '');
                $tgl = !empty($tglStr) ? date('Y-m-d', strtotime($tglStr)) : date('Y-m-d');
                
                // Prioritas Tahun: Kolom File > Tahun dari Tanggal > Parameter Global
                $itemTahun = ($thnIdx !== false && !empty($data[$thnIdx])) ? $data[$thnIdx] : date('Y', strtotime($tgl));
                if (empty($itemTahun)) $itemTahun = $tahun;

                $itemMasa = (int)date('m', strtotime($tgl));

                $stmt->execute([
                    $npwp, $itemTahun, 
                    strtoupper($data[$jnsIdx] ?? 'KELUARAN'), 
                    $data[$noIdx], 
                    $tgl, 
                    $data[$statIdx] ?? 'approved', 
                    $data[$masaIdx] ?? $itemMasa,
                    $data[$npwpIdx] ?? '', 
                    $data[$namaIdx] ?? '', 
                    str_replace([',', ' '], '', $data[$dppIdx] ?? 0), 
                    str_replace([',', ' '], '', $data[$ppnIdx] ?? 0)
                ]);
                $processedCount++;
            }
        } elseif ($modul === 'bupot') {
            $noIdx    = $findIdx(['no_bupot', 'nomor_bukti']);
            $thnIdx   = $findIdx(['tahun', 'year']);
            $objIdx   = $findIdx(['kode_objek_pajak', 'kode_objek']);
            $jnsIdx   = $findIdx(['jenis_penerbitan']);
            $npwpIdx  = $findIdx(['npwp_lawan']);
            $namaIdx  = $findIdx(['nama_lawan']);
            $dppIdx   = $findIdx(['dpp_bupot', 'dpp']);
            $pphIdx   = $findIdx(['nilai_pph', 'pph', 'jumlah_pph']);

            if ($noIdx === false) throw new Exception("Kolom 'no_bupot' tidak ditemukan.");

            $stmt = $db->prepare("INSERT INTO bukti_potong (npwp, tahun, kode_objek_pajak, no_bupot, jenis_penerbitan, npwp_lawan, nama_lawan, dpp_bupot, nilai_pph, jenis_pajak, sifat_bupot, dikreditkan, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");

            foreach ($rows as $data) {
                if (empty($data[$noIdx])) continue;
                $itemTahun = ($thnIdx !== false && !empty($data[$thnIdx])) ? $data[$thnIdx] : $tahun;
                $prefix = substr($data[$objIdx] ?? '', 0, 2);
                $jp = 'PPh_Final';
                if ($prefix == '21') $jp = 'PPh_21';
                elseif ($prefix == '22') $jp = 'PPh_22';
                elseif ($prefix == '24' || $prefix == '23') $jp = 'PPh_23';
                $kredit = (in_array($jp, ['PPh_21', 'PPh_22', 'PPh_23']) && (($data[$npwpIdx] ?? '') !== $npwp)) ? 'ya' : 'tidak';
                $sifat = ($jp == 'PPh_Final') ? 'Final' : 'Tidak Final';

                $stmt->execute([
                    $npwp, $itemTahun, $data[$objIdx] ?? '', $data[$noIdx], $data[$jnsIdx] ?? 'BPPU', 
                    $data[$npwpIdx] ?? '', $data[$namaIdx] ?? '', 
                    str_replace([',', ' '], '', $data[$dppIdx] ?? 0), 
                    str_replace([',', ' '], '', $data[$pphIdx] ?? 0),
                    $jp, $sifat, $kredit
                ]);
                $processedCount++;
            }
        } elseif ($modul === 'setoran') {
            $ntpnIdx  = $findIdx(['ntpn', 'nomor_transaksi']);
            $jpIdx    = $findIdx(['jenis_pajak', 'jenis']);
            $jsIdx    = $findIdx(['jenis_setoran']);
            $mapIdx   = $findIdx(['map', 'kode_map']);
            $kjsIdx   = $findIdx(['kjs', 'kode_jenis_setoran']);
            $tglIdx   = $findIdx(['tgl_setor', 'tanggal_setor', 'tanggal']);
            $nilaiIdx = $findIdx(['nilai_setoran', 'nominal', 'jumlah']);
            $thnIdx   = $findIdx(['tahun', 'year']);

            if ($ntpnIdx === false || $nilaiIdx === false) throw new Exception("Kolom 'ntpn' atau 'nilai_setoran' tidak ditemukan.");

            $stmt = $db->prepare("INSERT INTO setoran_pajak (npwp, tahun, jenis_pajak, jenis_setoran, map, kjs, nilai_setoran, tgl_setor, ntpn, dikreditkan) VALUES (?,?,?,?,?,?,?,?,?,?)");
            foreach ($rows as $data) {
                if (empty($data[$ntpnIdx])) continue;
                
                $itemTahun = ($thnIdx !== false && !empty($data[$thnIdx])) ? $data[$thnIdx] : $tahun;
                $jenis_p = $data[$jpIdx] ?? 'PPh_25(BADAN)';
                $map = $data[$mapIdx] ?? '';
                $kjs = $data[$kjsIdx] ?? '';
                $dikreditkan = (in_array($jenis_p, ['PPh_25(OP)', 'PPh_25(BADAN)']) && in_array($map, ['411125', '411126']) && $kjs == '100') ? 'ya' : 'tidak';

                $stmt->execute([
                    $npwp, $itemTahun, $jenis_p, $data[$jsIdx] ?? 'SSP', $map, $kjs,
                    str_replace([',', ' '], '', $data[$nilaiIdx] ?? 0),
                    date('Y-m-d', strtotime(str_replace('/', '-', $data[$tglIdx] ?? date('Y-m-d')))),
                    $data[$ntpnIdx], $dikreditkan
                ]);
                $processedCount++;
            }
        } elseif ($modul === 'akun') {
            $kodeIdx = $findIdx(['kode_akun', 'kode']);
            $namaIdx = $findIdx(['nama_akun', 'nama']);
            $jnsIdx  = $findIdx(['jenis', 'type']);
            $nomIdx  = $findIdx(['nominal', 'jumlah', 'amount']);
            $thnIdx  = $findIdx(['tahun', 'year']);

            if ($kodeIdx === false || $namaIdx === false) throw new Exception("Kolom 'kode_akun' atau 'nama_akun' tidak ditemukan.");

            $stmt = $db->prepare("INSERT INTO mapping_akun (npwp, tahun, kode_akun, nama_akun, jenis, nominal, kategori_akun, kategori_arus_kas) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($rows as $data) {
                if (empty($data[$kodeIdx])) continue;
                
                $itemTahun = ($thnIdx !== false && !empty($data[$thnIdx])) ? $data[$thnIdx] : $tahun;
                $klas = klasifikasiAkun($data[$namaIdx] ?? '');
                
                $stmt->execute([
                    $npwp, $itemTahun, $data[$kodeIdx], $data[$namaIdx] ?? '',
                    strtoupper($data[$jnsIdx] ?? 'DEBIT'), 
                    str_replace([',', ' '], '', $data[$nomIdx] ?? 0),
                    $klas['kategori'], $klas['arus_kas']
                ]);
                $processedCount++;
            }
        } elseif ($modul === 'ilap') {
            $tglIdx = $findIdx(['tanggal', 'tgl', 'date']);
            $katIdx = $findIdx(['kategori', 'category']);
            $jnsIdx = $findIdx(['jenis_saldo', 'jenis', 'type']);
            $nomIdx = $findIdx(['nominal', 'amount', 'jumlah']);
            $sldIdx = $findIdx(['saldo', 'balance']);
            $ketIdx = $findIdx(['keterangan', 'desc', 'description']); // Untuk klasifikasi
            $thnIdx = $findIdx(['tahun', 'year']);

            if ($tglIdx === false || $nomIdx === false) {
                throw new Exception("Kolom wajib 'tanggal' atau 'nominal' tidak ditemukan.");
            }

            $stmt = $db->prepare("INSERT INTO data_ilap (npwp, tahun, tanggal, kategori, jenis_saldo, nominal, saldo, kategori_data, sumber_data, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,'UPLOAD_CENTRAL',?,NOW())");

            foreach ($rows as $data) {
                if (empty($data[$tglIdx]) || !isset($data[$nomIdx])) continue;
                
                $itemTahun = ($thnIdx !== false && !empty($data[$thnIdx])) ? $data[$thnIdx] : $tahun;
                $tgl = date('Y-m-d', strtotime(str_replace('/', '-', $data[$tglIdx])));
                
                // Klasifikasi kategori_data berdasarkan keterangan atau kategori mentah
                $kategori_data = klasifikasiIlap($data[$ketIdx] ?? $data[$katIdx] ?? '');
                
                $stmt->execute([
                    $npwp, $itemTahun, $tgl, 
                    strtoupper($data[$katIdx] ?? 'PIHAK_LAIN'),
                    strtoupper($data[$jnsIdx] ?? 'KREDIT'),
                    str_replace([',', ' '], '', $data[$nomIdx]),
                    str_replace([',', ' '], '', $data[$sldIdx] ?? 0),
                    $kategori_data,
                    $_SESSION['user_id'] ?? null
                ]);
                $processedCount++;
            }
        }

        if ($processedCount === 0) {
            throw new Exception("Tidak ada data yang valid untuk diproses. Periksa apakah baris data kosong atau format kolom salah.");
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Generate Excel (Minimalist XML) - Helper untuk download template
 */
function downloadExcelTemplate(string $filename, array $header, array $sample) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="'.$filename.'.xls"');

    echo '<?xml version="1.0"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:microsoft:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
    echo '<Worksheet ss:Name="Template">';
    echo '<Table>';
    echo '<Row>';
    foreach($header as $h) echo '<Cell><Data ss:Type="String">'.htmlspecialchars($h).'</Data></Cell>';
    echo '</Row>';
    echo '<Row>';
    foreach($sample as $s) echo '<Cell><Data ss:Type="String">'.htmlspecialchars($s).'</Data></Cell>';
    echo '</Row>';
    echo '</Table></Worksheet></Workbook>';
    exit;
}

// getImageUrl() is now centrally managed in config_s3.php, which is included via config.php