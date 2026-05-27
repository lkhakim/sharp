<?php

// ==========================================
// 1. PENGATURAN HEADER UNTUK API (JSON & CORS)
// ==========================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
//header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-API-KEY");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");


// ======================================================================================

$nama_klu = isset($_GET['klu']) ? $_GET['klu'] : null;


// ==========================================
// 1. DEFINISI DATA BENCHMARK UTAMA
// ==========================================


$benchmark_data = [
    'wholesale' => ['gpm'=>[5, 15], 'opm'=>[3, 7], 'npm'=>[2, 5], 'cttor'=>[0.5, 2], 'gaji'=>[1, 3], 'der'=>[1, 4], 'cr'=>[1.2, 2.0]],
    'retail'    => ['gpm'=>[15, 25], 'opm'=>[5, 10], 'npm'=>[3, 7], 'cttor'=>[1, 3], 'gaji'=>[5, 10], 'der'=>[1, 4], 'cr'=>[1.0, 1.8]],
    'manufaktur'=> ['gpm'=>[20, 35], 'opm'=>[10, 20], 'npm'=>[5, 12], 'cttor'=>[1.5, 4], 'gaji'=>[10, 15], 'der'=>[1, 4], 'cr'=>[1.2, 2.0]],
    'konstruksi'=> ['gpm'=>[10, 20], 'opm'=>[6, 12], 'npm'=>[4, 10], 'cttor'=>[2, 4], 'gaji'=>[5, 8], 'der'=>[1.5, 4], 'cr'=>[1.1, 1.5]],
    'jasa_profesional' => ['gpm'=>[40, 70], 'opm'=>[25, 50], 'npm'=>[20, 40], 'cttor'=>[5, 10], 'gaji'=>[20, 40], 'der'=>[1, 4], 'cr'=>[2.0, 5.0]],
    'jasa_umum' => ['gpm'=>[30, 50], 'opm'=>[15, 30], 'npm'=>[10, 25], 'cttor'=>[2, 6], 'gaji'=>[15, 30], 'der'=>[0.5, 4], 'cr'=>[1.5, 2.5]],
    'hospitality'=>['gpm'=>[50, 70], 'opm'=>[15, 30], 'npm'=>[10, 20], 'cttor'=>[3, 8], 'gaji'=>[15, 25], 'der'=>[1, 4], 'cr'=>[1.0, 2.0]],
    'fnb'       => ['gpm'=>[40, 60], 'opm'=>[15, 25], 'npm'=>[10, 20], 'cttor'=>[2, 5], 'gaji'=>[15, 30], 'der'=>[0.5, 4], 'cr'=>[1.0, 2.0]],
    'logistik'  => ['gpm'=>[15, 25], 'opm'=>[8, 15], 'npm'=>[5, 10], 'cttor'=>[1, 3], 'gaji'=>[10, 20], 'der'=>[1.5, 4], 'cr'=>[1.0, 1.5]],
    'tambang'   => ['gpm'=>[30, 50], 'opm'=>[20, 40], 'npm'=>[15, 30], 'cttor'=>[3, 7], 'gaji'=>[5, 12], 'der'=>[1, 4], 'cr'=>[1.2, 2.5]],
    'properti'  => ['gpm'=>[30, 45], 'opm'=>[15, 25], 'npm'=>[10, 20], 'cttor'=>[2, 5], 'gaji'=>[3, 7], 'der'=>[1, 4], 'cr'=>[1.5, 3.0]]
];

// ==========================================
// 2. MENGHITUNG RATA-RATA UNTUK DEFAULT
// ==========================================
$total_sektor = count($benchmark_data);
$default_rasio = [
    'gpm' => [0, 0], 'opm' => [0, 0], 'npm' => [0, 0], 
    'cttor' => [0, 0], 'gaji' => [0, 0], 'der' => [0, 0], 'cr' => [0, 0]
];

// Menjumlahkan semua nilai min dan max
foreach ($benchmark_data as $sektor => $rasio) {
    foreach ($rasio as $jenis_rasio => $nilai) {
        $default_rasio[$jenis_rasio][0] += $nilai[0]; // Akumulasi Min
        $default_rasio[$jenis_rasio][1] += $nilai[1]; // Akumulasi Max
    }
}

// Membagi dengan total sektor untuk mendapat rata-rata (dibulatkan 2 desimal)
foreach ($default_rasio as $jenis_rasio => $nilai) {
    $benchmark_data['default'][$jenis_rasio][0] = number_format($nilai[0] / $total_sektor, 2, '.', '');
    $benchmark_data['default'][$jenis_rasio][1] = number_format($nilai[1] / $total_sektor, 2, '.', '');
}

// ==========================================
// 3. FUNGSI DETEKSI SEKTOR BERDASARKAN KLU
// ==========================================

function deteksiSektor($nama_klu) {
    $nama_klu = strtolower($nama_klu);

    if (strpos($nama_klu, 'perdagangan besar') !== false || strpos($nama_klu, 'distributor') !== false) return 'wholesale';
    if (strpos($nama_klu, 'eceran') !== false || strpos($nama_klu, 'toko') !== false || strpos($nama_klu, 'kios') !== false || strpos($nama_klu, 'warung') !== false) return 'retail';
    if (strpos($nama_klu, 'industri') !== false || strpos($nama_klu, 'pabrik') !== false || strpos($nama_klu, 'pembuatan') !== false || strpos($nama_klu, 'pengolahan') !== false) return 'manufaktur';
    if (strpos($nama_klu, 'hotel') !== false || strpos($nama_klu, 'penginapan') !== false || strpos($nama_klu, 'akomodasi') !== false || strpos($nama_klu, 'wisma') !== false || strpos($nama_klu, 'motel') !== false) return 'hospitality';
    if (strpos($nama_klu, 'restoran') !== false || strpos($nama_klu, 'rumah makan') !== false || strpos($nama_klu, 'katering') !== false || strpos($nama_klu, 'makanan dan minuman') !== false || strpos($nama_klu, 'kafe') !== false) return 'fnb';
    if (strpos($nama_klu, 'konstruksi') !== false || strpos($nama_klu, 'pembangunan') !== false || strpos($nama_klu, 'instalasi') !== false) return 'konstruksi';
    if (strpos($nama_klu, 'jasa hukum') !== false || strpos($nama_klu, 'konsultan') !== false || strpos($nama_klu, 'akuntansi') !== false || strpos($nama_klu, 'arsitektur') !== false || strpos($nama_klu, 'medis') !== false || strpos($nama_klu, 'kesehatan manusia') !== false) return 'jasa_profesional';
    if (strpos($nama_klu, 'jasa') !== false || strpos($nama_klu, 'perawatan') !== false || strpos($nama_klu, 'reparasi') !== false || strpos($nama_klu, 'penyewaan') !== false || strpos($nama_klu, 'agen') !== false || strpos($nama_klu, 'biro') !== false) return 'jasa_umum';
    if (strpos($nama_klu, 'angkutan') !== false || strpos($nama_klu, 'transportasi') !== false || strpos($nama_klu, 'logistik') !== false || strpos($nama_klu, 'ekspedisi') !== false) return 'logistik';
    if (strpos($nama_klu, 'pertambangan') !== false || strpos($nama_klu, 'penggalian') !== false) return 'tambang';
    if (strpos($nama_klu, 'real estate') !== false || strpos($nama_klu, 'properti') !== false || strpos($nama_klu, 'developer') !== false) return 'properti';

    return 'default';
}

// ==========================================
// 4. PROSES BACA DAN UPDATE DATA KE MYSQL
// ==========================================
$kode_sektor = deteksiSektor($nama_klu);
$rasio = $benchmark_data[$kode_sektor];

if ($rasio) {
        $response = [
            "status" => "success",
            "message" => "Data benchmark berhasil ditemukan",
            "data" => [      
                "klu" => $nama_klu,
                "sektor_terdeteksi" => $kode_sektor,
                "benchmark" => [
                    "gpm"   => ["min" => $rasio['gpm'][0],   "max" => $rasio['gpm'][1]],
                    "opm"   => ["min" => $rasio['opm'][0],   "max" => $rasio['opm'][1]],
                    "npm"   => ["min" => $rasio['npm'][0],   "max" => $rasio['npm'][1]],
                    "cttor" => ["min" => $rasio['cttor'][0], "max" => $rasio['cttor'][1]],
                    "gaji"  => ["min" => $rasio['gaji'][0],  "max" => $rasio['gaji'][1]],
                    "der"   => ["min" => $rasio['der'][0],   "max" => $rasio['der'][1]],
                    "cr"    => ["min" => $rasio['cr'][0],    "max" => $rasio['cr'][1]]
                ]
            ]
        ];
        
        http_response_code(200); // OK
        echo json_encode($response, JSON_PRETTY_PRINT);
} else {
        http_response_code(404); // Not Found
        echo json_encode(["status" => "error", "message" => "Data KLU tidak ditemukan."]);
}
?>


