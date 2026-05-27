<?php
require_once 'config.php';
session_start();

$npwp = $_GET['npwp'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($npwp)) {
    header("Location: cari_wp.php");
    exit;
}

// Ambil Profil WP & Skor Terakhir
$stmt = $db->prepare("SELECT ha.skor_risiko, ha.skor_final, ha.skor_validasi, p.nama, p.lat_npwp, p.lng_npwp,  p.jenis_wp, p.is_umkm, p.tgl_pkp, p.klu, k.nama_klasifikasi_usaha, p.alamat, p.kelurahan, p.kecamatan, p.kota, p.propinsi
                     FROM profil_wp p 
                     LEFT JOIN benchmark_klu k ON p.klu = k.klu
                     LEFT JOIN hasil_analisis ha ON p.npwp = ha.npwp AND ha.tahun = ?
                     WHERE p.npwp = ?");
$stmt->execute([$tahun, $npwp]);
$wp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wp) {
    die("<div class='alert alert-danger'>Data WP tidak ditemukan. Pastikan NPWP benar.</div>");
}

// Ambil Data Validasi Lapangan Eksisting
$stmtV = $db->prepare("SELECT * FROM validasi_lapangan WHERE npwp = ?  LIMIT 1");
$stmtV->execute([$npwp]);
$v = $stmtV->fetch(PDO::FETCH_ASSOC) ?: [];

$skor_v_l = $v['skor'] ?? $wp['skor_validasi'] ?? 0;
$level = $skor_v_l < 40 ? 'RENDAH' : ($skor_v_l < 70 ? 'SEDANG' : 'TINGGI');
$color_map = ['TINGGI' => 'danger', 'SEDANG' => 'warning', 'RENDAH' => 'success'];
$badge_color = $color_map[$level] ?? 'secondary';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Validation - <?= htmlspecialchars($wp['nama']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { 
            --primary: #0f172a; 
            --accent: #3b82f6;
            --bg-subtle: #f8fafc;
        }
        body { background-color: #f1f5f9; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        .main-content { padding: 24px; transition: margin-left 0.3s; }
        @media (min-width: 992px) { .main-content { margin-left: 280px; } }
        
        .glass-card { background: white; border-radius: 16px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .form-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        
        .score-circle {
            width: 100px; height: 100px;
            border-radius: 50%;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            border: 6px solid;
            background: white;
            margin: 0 auto;
        }
        
        .photo-box {
            width: 100%;
            aspect-ratio: 4/3;
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.2s;
        }
        .photo-box:hover { border-color: var(--accent); background: #f0f9ff; }
        .photo-preview { width: 100%; height: 100%; object-fit: cover; display: none; }
        
        #map { height: 300px; border-radius: 12px; border: 1px solid #e2e8f0; z-index: 1; }
        
        .section-title { font-size: 0.9rem; font-weight: 800; color: var(--primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px; }
        .section-title i { width: 18px; height: 18px; color: var(--accent); }
        
        .checklist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
        .checklist-item { padding: 10px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
        
        .sticky-bottom-bar {
            position: fixed; bottom: 0; right: 0; left: 0;
            background: white; padding: 16px;
            border-top: 1px solid #e2e8f0; z-index: 1050;
        }
        @media (min-width: 992px) { .sticky-bottom-bar { left: 280px; } }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="container-fluid mb-5 pb-5">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="profil_wp.php?npwp=<?= $npwp ?>&tahun=<?= $tahun ?>" class="btn btn-sm btn-light border me-3 shadow-sm">
                    <i data-lucide="arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-800 m-0">Field Validation</h3>
                    <p class="text-muted small m-0">Audit & Physical Verification for Taxpayer Profile</p>
                </div>
            </div>
        </div>

        <form id="auditForm">
            <input type="hidden" name="npwp" value="<?= htmlspecialchars($npwp) ?>">
            <input type="hidden" name="tahun" value="<?= htmlspecialchars($tahun) ?>">
            <input type="hidden" name="lat_lokasi" id="lat_lokasi" value="<?= $v['lat_lokasi'] ?? '' ?>">
            <input type="hidden" name="lng_lokasi" id="lng_lokasi" value="<?= $v['lng_lokasi'] ?? '' ?>">
            <input type="hidden" name="lat_kegiatan" id="lat_kegiatan" value="<?= $v['lat_kegiatan'] ?? '' ?>">
            <input type="hidden" name="lng_kegiatan" id="lng_kegiatan" value="<?= $v['lng_kegiatan'] ?? '' ?>">

            <div class="row g-4">
                <!-- Left: WP Info & Checklist -->
                <div class="col-lg-7">
                    <!-- WP Card -->
                    <div class="glass-card p-4 mb-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i data-lucide="building-2"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold m-0"><?= htmlspecialchars($wp['nama']); ?></h5>
                                <span class="text-muted small">NPWP: <?= $npwp ?> | Tahun: <?= $tahun ?></span>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Alamat (Coretax)</label>
                                <div class="fw-bold small text-primary"><?= strtoupper(htmlspecialchars($wp['alamat'])) ?></div>
                                <div class="text-muted smaller"><?= $wp['kelurahan'] ?>, <?= $wp['kecamatan'] ?>, <?= $wp['kota'] ?>, <?= $wp['propinsi'] ?></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Jenis</label>
                                <div class="fw-bold small"><?= $wp['jenis_wp'] ?></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">UMKM</label>
                                <div class="fw-bold small"><?= $wp['is_umkm'] ? 'Yes' : 'No' ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Klasifikasi Usaha (KLU)</label>
                                <div class="fw-bold small text-truncate"><?= $wp['klu'] ?> - <?= strtoupper(htmlspecialchars($wp['nama_klasifikasi_usaha'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Checklist -->
                    <div class="glass-card p-4">
                        <div class="section-title">
                            <i data-lucide="clipboard-list"></i> Verification Checklist
                        </div>
                        <div class="checklist-grid">
                            <?php 
                            $fields = [
                                'Alamat_sesuai' => 'Alamat Sesuai dengan Coretax',
                                'Ada_papan_nama' => 'Papan Nama Terpasang',
                                'Ada_aktivitas' => 'Ada Kegiatan Usaha',
                                'Jam_operasional_wajar' => 'Jam Operasional Normal',
                                'Aset_terlihat' => 'Terdapat Aset Usaha',
                                'Ada_pembukuan' => 'Melakukan Pembukuan/Pencatatan',
                                'Pembukuan_rapi' => 'Pencatatan Transaksi Rapi',
                                'Faktur_tersimpan' => 'Faktur Tersimpan dengan Baik',
                                'PIC_menguasai' => 'Pemilik Menguasai Proses Bisnis',
                                'Penjelasan_wajar' => 'Penjelasan Kondisi Usaha Wajar',
                                'Pegawai_sesuai_SPT' => 'Jumlah Pegawai Sesuai SPT'
                            ];
                            foreach($fields as $key => $label):
                                $checked = ($v[$key] ?? 0) == 1 ? 'checked' : '';
                            ?>
                            <div class="checklist-item">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="<?= $key ?>" value="1" <?= $checked ?>>
                                    <label class="form-check-label small fw-bold" for="<?= $key ?>"><?= $label ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="col-md-6">
                                <label class="form-label">Jumlah Pegawai</label>
                                <input type="number" name="Jumlah_Pegawai" class="form-control form-control-sm" value="<?= $v['Jumlah_Pegawai'] ?? 0 ?>">
                            </div>
                        </div>

                        <div class="row g-3 mt-3 border-top pt-3">
                            <div class="col-12">
                                <div class="p-3 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-20">
                                    <div class="section-title mb-2 text-danger" style="font-size: 0.8rem;">
                                        <i data-lucide="alert-triangle" class="text-danger"></i> High Risk Indicators
                                    </div>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="Alamat_fiktif" id="Alamat_fiktif" value="1" <?= ($v['Alamat_fiktif'] ?? 0) == 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold text-danger" for="Alamat_fiktif">ALamat Fiktif</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="Kantor_virtual_sewa" id="Kantor_virtual_sewa" value="1" <?= ($v['Kantor_virtual_sewa'] ?? 0) == 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold text-warning" for="Kantor_virtual_sewa">Sewa Kantor Virtual</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="Tidak_kooperatif" id="Tidak_kooperatif" value="1" <?= ($v['Tidak_kooperatif'] ?? 0) == 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold text-danger" for="Tidak_kooperatif">Wajib Pajak Resisten</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="form-label">Catatan Temuan Hasil Validasi Lapangan </label>
                                <textarea name="catatan" class="form-control form-control-sm" rows="3" placeholder="Jelaskan kegiatan usaha secara rinci, sesuai kondisi di lapangan..."><?= htmlspecialchars($v['catatan'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Photos & Map -->
                <div class="col-lg-5">
                    <!-- Current Score Card -->
                    <div class="glass-card p-4 mb-4 text-center">
                        <div class="stat-label mb-2">Field Validation Score</div>
                        <div class="score-circle border-<?= $badge_color ?> mb-2">
                            <h2 class="fw-bold m-0 text-<?= $badge_color ?>"><?= $skor_v_l ?></h2>
                            <span class="small fw-bold text-muted" style="font-size: 10px;">POINTS</span>
                        </div>
                        <div class="badge bg-<?= $badge_color ?> fs-6 badge-pill"><?= $level ?> RISK</div>
                    </div>

                    <!-- Photos -->
                    <div class="glass-card p-4 mb-4">
                        <div class="section-title">
                            <i data-lucide="camera"></i> Documentation
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="photo-box" onclick="triggerPhoto('foto_lokasi')">
                                    <?php if(!empty($v['link_foto_lokasi'])): ?>
                                        <img id="prev_foto_lokasi" class="photo-preview" src="<?= getImageUrl($v['link_foto_lokasi']) ?>" alt="Preview" style="display:block;">
                                    <?php else: ?>
                                        <i data-lucide="map-pin" class="text-muted mb-2"></i>
                                        <span class="small text-muted fw-bold">Location Photo</span>
                                        <img id="prev_foto_lokasi" class="photo-preview" src="#" alt="Preview">
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="foto_lokasi" id="foto_lokasi" class="d-none" accept="image/*" capture="environment">
                                <div id="status_gps_lokasi" class="small <?= !empty($v['lat_lokasi']) ? 'text-success' : 'text-danger' ?> text-center mt-2" style="font-size:10px;">
                                    <i data-lucide="navigation" style="width:10px;"></i> GPS: <?= !empty($v['lat_lokasi']) ? 'RECORED' : 'PENDING' ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="photo-box" onclick="triggerPhoto('foto_kegiatan')">
                                    <?php if(!empty($v['link_foto_kegiatan'])): ?>
                                        <img id="prev_foto_kegiatan" class="photo-preview" src="<?= getImageUrl($v['link_foto_kegiatan']) ?>" alt="Preview" style="display:block;">
                                    <?php else: ?>
                                        <i data-lucide="briefcase" class="text-muted mb-2"></i>
                                        <span class="small text-muted fw-bold">Activity Photo</span>
                                        <img id="prev_foto_kegiatan" class="photo-preview" src="#" alt="Preview">
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="foto_kegiatan" id="foto_kegiatan" class="d-none" accept="image/*" capture="environment">
                                <div id="status_gps_kegiatan" class="small <?= !empty($v['lat_kegiatan']) ? 'text-success' : 'text-danger' ?> text-center mt-2" style="font-size:10px;">
                                    <i data-lucide="navigation" style="width:10px;"></i> GPS: <?= !empty($v['lat_kegiatan']) ? 'RECORDED' : 'PENDING' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map -->
                    <div class="glass-card p-4">
                        <div class="section-title">
                            <i data-lucide="map-pinned"></i> Audit Mapping
                        </div>
                        <div id="map"></div>
                        <div id="distance_info" class="mt-3 text-center small fw-bold text-muted">Waiting for photo documentation to calculate distance...</div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bottom Action Bar -->
<div class="sticky-bottom-bar shadow-lg">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12 offset-md-12">
                <button type="button" class="btn btn-primary w-100 fw-bold py-3 shadow" onclick="saveAudit()" id="btnSave">
                    <i data-lucide="save" class="me-2" style="width:20px;"></i> Finalize Validation & Sync Risk
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    lucide.createIcons();

    let map, markerNPWP, markerAuditor, polyline;
    const latNpwp = <?= $wp['lat_npwp'] ?: -6.2088 ?>; 
    const lngNpwp = <?= $wp['lng_npwp'] ?: 106.8456 ?>;

    document.addEventListener("DOMContentLoaded", function() {
        map = L.map('map').setView([latNpwp, lngNpwp], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        markerNPWP = L.marker([latNpwp, lngNpwp]).addTo(map).bindPopup("Registered Location");

        <?php if(!empty($v['lat_kegiatan']) && !empty($v['lng_kegiatan'])): ?>
            const latHist = <?= $v['lat_kegiatan'] ?>;
            const lngHist = <?= $v['lng_kegiatan'] ?>;
            
            const histIcon = L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
            });
            
            markerAuditor = L.marker([latHist, lngHist], {icon: histIcon}).addTo(map).bindPopup("Last Validation Point").openPopup();
            polyline = L.polyline([[latNpwp, lngNpwp], [latHist, lngHist]], {color: '#ef4444', weight: 2, dashArray: '5, 10'}).addTo(map);
            map.fitBounds(polyline.getBounds(), {padding: [50, 50]});

            const distKm = calculateDistance(latNpwp, lngNpwp, latHist, lngHist);
            document.getElementById('distance_info').innerHTML = `Last validation distance: <span class="badge bg-secondary ms-1">${distKm < 1 ? Math.round(distKm * 1000) + " m" : distKm.toFixed(2) + " km"}</span>`;
        <?php endif; ?>
    });

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c; 
    }

    function triggerPhoto(inputId) {
        document.getElementById(inputId).click();
    }

    function setupImageAndGPS(inputId, imgId, type) {
        document.getElementById(inputId).onchange = evt => {
            const [file] = document.getElementById(inputId).files;
            if (file) {
                document.getElementById(imgId).src = URL.createObjectURL(file);
                document.getElementById(imgId).style.display = 'block';
                const placeholder = document.getElementById(imgId).parentElement;
                placeholder.querySelectorAll('i, span').forEach(el => el.style.display = 'none');

                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition((pos) => {
                        const latAuditor = pos.coords.latitude;
                        const lngAuditor = pos.coords.longitude;
                        
                        document.getElementById('lat_' + type).value = latAuditor;
                        document.getElementById('lng_' + type).value = lngAuditor;
                        
                        let statusText = document.getElementById('status_gps_' + type);
                        statusText.innerHTML = `<i data-lucide="check-circle" style="width:10px;"></i> GPS: RECORDED`;
                        statusText.className = "small text-success text-center mt-2";
                        lucide.createIcons();

                        if (markerAuditor) map.removeLayer(markerAuditor);
                        if (polyline) map.removeLayer(polyline);

                        const currentIcon = L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
                        });
                        
                        markerAuditor = L.marker([latAuditor, lngAuditor], {icon: currentIcon}).addTo(map).bindPopup("Actual Location (" + type + ")").openPopup();
                        polyline = L.polyline([[latNpwp, lngNpwp], [latAuditor, lngAuditor]], {color: '#3b82f6', weight: 2, dashArray: '5, 10'}).addTo(map);
                        
                        map.fitBounds(polyline.getBounds(), {padding: [50, 50]});

                        const jarakKm = calculateDistance(latNpwp, lngNpwp, latAuditor, lngAuditor);
                        let textJarak = jarakKm < 1 ? Math.round(jarakKm * 1000) + " m" : jarakKm.toFixed(2) + " km";
                        let badgeClass = jarakKm > 1 ? 'bg-danger' : 'bg-success';
                        document.getElementById('distance_info').innerHTML = `Current capture distance: <span class="badge ${badgeClass} ms-1">${textJarak}</span>`;

                    }, (err) => {
                        alert("Geolocation failed: " + err.message);
                    }, { enableHighAccuracy: true });
                }
            }
        }
    }
    
    setupImageAndGPS('foto_lokasi', 'prev_foto_lokasi', 'lokasi');
    setupImageAndGPS('foto_kegiatan', 'prev_foto_kegiatan', 'kegiatan');

    async function saveAudit() {
        const btn = document.getElementById('btnSave');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Finalizing...';

        const form = document.getElementById('auditForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('api/api_audit_lapangan.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                alert("✓ Success: " + result.message);
                window.location.href = "profil_wp.php?npwp=<?= urlencode($npwp) ?>&tahun=<?= $tahun ?>";
            } else {
                alert("✗ Error: " + result.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (error) {
            alert("Connection error to API server.");
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
</script>
</body>
</html>