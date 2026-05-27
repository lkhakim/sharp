<?php
require_once '../config.php';
require_once '../functions_upload.php';

if (isset($_GET['nama'])) {
    $klas = klasifikasiAkun($_GET['nama']);
    
    // Auto-generate code based on category
    $mapping = [
        'kas' => '1-1000', 'piutang' => '1-2000', 'aset_lancar' => '1-3000',
        'aset_tetap' => '1-4000', 'aset_tidak_berwujud' => '1-5000',
        'utang' => '2-1000', 'utang_bank' => '2-2000',
        'modal' => '3-1000',
        'peredaran_usaha' => '4-1000', 'pendapatan_lain' => '4-2000',
        'pembelian' => '5-1000', 'beban_gaji' => '6-1000', 'beban_usaha' => '6-2000', 'beban_lain' => '6-3000',
        'penyusutan' => '6-4000'
    ];
    
    $klas['kode_suggest'] = $mapping[$klas['kategori']] ?? '9-9999';
    
    header('Content-Type: application/json');
    echo json_encode($klas);
}
