<?php
require_once 'config.php';
$stmt = $db->query("SELECT link_foto_lokasi FROM validasi_lapangan ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Stored Path: " . ($row['link_foto_lokasi'] ?? 'EMPTY');
?>