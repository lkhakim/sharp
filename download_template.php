<?php
/**
 * SHARP - Download Template CSV/XLS
 */
require_once 'functions_upload.php';

$type = $_GET['type'] ?? 'spt';
$tahun = $_GET['tahun'] ?? date('Y');
$format = $_GET['format'] ?? 'csv'; // csv atau xls

// Definisi Header dan Contoh Data
$templates = [
    'bupot' => [
        'header' => ['tahun', 'no_bupot', 'kode_objek_pajak', 'jenis_penerbitan', 'npwp_lawan', 'nama_lawan', 'dpp_bupot', 'nilai_pph'],
        'sample' => [$tahun, 'BUPOT-001', '23-104-01', 'BPPU', '01.222.333.4-005.000', 'PT KONSULTAN AHLI', '10000000', '200000']
    ],
    'spt' => [
        'header' => ['tahun', 'peredaran_usaha', 'pembelian', 'gaji', 'pajak_terutang'],
        'sample' => [$tahun, '1000000000', '600000000', '150000000', '25000000']
    ],
    'bank' => [
        'header' => ['tanggal', 'keterangan', 'jenis', 'nominal', 'saldo', 'kategori'],
        'sample' => [$tahun . '-01-02', 'TRANSFER MASUK DARI PT ABC', 'KREDIT', '5000000', '15000000', 'PENJUALAN']
    ],
    'faktur' => [
        'header' => ['jenis_faktur', 'no_faktur', 'tgl_faktur', 'status', 'masa_pajak', 'npwp_lawan', 'nama_lawan', 'dpp', 'ppn'],
        'sample' => ['KELUARAN', '010.000-24.00000001', $tahun . '-01-10', 'approved', '1', '01.234.567.8-001.000', 'PT PEMBELI SUKSES', '10000000', '1100000']
    ],
    'akun' => [
        'header' => ['tahun', 'kode_akun', 'nama_akun', 'jenis', 'nominal'],
        'sample' => [$tahun, '1-1100', 'KAS DAN BANK', 'DEBIT', '50000000']
    ],
    'setoran' => [
        'header' => ['tahun', 'ntpn', 'jenis_pajak', 'jenis_setoran', 'map', 'kjs', 'tgl_setor', 'nilai_setoran'],
        'sample' => [$tahun, '1234567890123456', 'PPh_25(BADAN)', 'SSP', '411126', '100', $tahun . '-01-15', '5000000']
    ],
    'ilap' => [
        'header' => ['tahun', 'tanggal', 'keterangan', 'kategori', 'jenis_saldo', 'nominal', 'saldo'],
        'sample' => [$tahun, $tahun . '-05-10', 'PENJUALAN BARANG KE PT XYZ', 'PIHAK_LAIN', 'KREDIT', '50000000', '150000000']
    ]
];

if (!isset($templates[$type])) {
    die("Tipe template tidak valid.");
}

$filename = "template_" . $type . "_" . $tahun . "_" . date('tYmd_His');

if ($format === 'xls') {
    downloadExcelTemplate($filename, $templates[$type]['header'], $templates[$type]['sample']);
} else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename . ".csv");
    $output = fopen('php://output', 'w');
    fputcsv($output, $templates[$type]['header']);
    fputcsv($output, $templates[$type]['sample']);
    fclose($output);
}
exit;
?>