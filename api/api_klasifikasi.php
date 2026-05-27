<?php
require_once 'config.php';
require_once 'functions_upload.php';

if (isset($_GET['nama'])) {
    $klas = klasifikasiAkun($_GET['nama']);
    header('Content-Type: application/json');
    echo json_encode($klas);
}
