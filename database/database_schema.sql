-- 1. Tabel Master User
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NULL, -- Ditambahkan untuk fitur lupa password
    nama_lengkap VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    role ENUM('auditor', 'supervisor', 'manager') DEFAULT 'auditor',
    kode_kpp VARCHAR(10),
    atasan_id INT NULL,
    reset_token VARCHAR(64) NULL, -- Token untuk reset password
    reset_token_expiry DATETIME NULL, -- Waktu kedaluwarsa token reset
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Profil Wajib Pajak
CREATE TABLE profil_wp (
    npwp VARCHAR(20) PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    alamat TEXT,
    kelurahan VARCHAR(100),
    kecamatan VARCHAR(100),
    kota VARCHAR(100),
    propinsi VARCHAR(100),
    kode_kpp VARCHAR(10),
    telpon VARCHAR(20),
    email VARCHAR(100),
    klu VARCHAR(10) INDEX,
    jenis_wp ENUM('Badan', 'OP'),
    is_umkm TINYINT(1) DEFAULT 0,
    tgl_daftar DATE,
    tgl_pkp DATE NULL,
    lat_npwp DECIMAL(10, 8),
    lng_npwp DECIMAL(11, 8),
    pemilik_nama VARCHAR(100),
    pemilik_nik VARCHAR(16),
    pemilik_jabatan VARCHAR(50),
    is_pic TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT
);

-- 3. Tabel Benchmark KLU
CREATE TABLE benchmark_klu (
    klu VARCHAR(10) PRIMARY KEY,
    nama_klasifikasi_usaha VARCHAR(255),
    npm DECIMAL(5,2),
    opm DECIMAL(5,2),
    gpm DECIMAL(5,2),
    rasio_gaji_omset DECIMAL(5,2),
    cttor DECIMAL(5,2),
    der DECIMAL(5,2),
    current_ratio DECIMAL(5,2)
);

-- 4. Tabel Mutasi Bank (Rekening Koran)
CREATE TABLE mutasi_bank (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npwp VARCHAR(20),
    tahun INT,
    tanggal DATE,
    keterangan TEXT,
    jenis ENUM('DEBIT', 'KREDIT'),
    nominal DECIMAL(15,2),
    saldo DECIMAL(15,2),
    kategori ENUM('PENJUALAN','PEMBELIAN','GAJI','JASA','OPERASIONAL','PAJAK','TRANSFER','LAINNYA') DEFAULT 'LAINNYA',
    sumber_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (npwp, tahun)
);

-- 5. Tabel Mapping Akun (Pembukuan)
CREATE TABLE mapping_akun (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npwp VARCHAR(20),
    tahun INT,
    kode_akun VARCHAR(20),
    nama_akun VARCHAR(100),
    jenis ENUM('DEBIT', 'KREDIT'),
    nominal DECIMAL(15,2),
    kategori_akun VARCHAR(50),
    kategori_arus_kas VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (npwp, tahun)
);






-- 7. Tabel Hasil Analisis Risiko
    CREATE TABLE IF NOT EXISTS hasil_analisis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        npwp VARCHAR(20) NOT NULL,
        tahun INT NOT NULL,
        data_json LONGTEXT,
        skor_risiko INT DEFAULT 0,
        skor_validasi INT DEFAULT 0,
        skor_final INT,
        level_risiko ENUM('RENDAH', 'SEDANG', 'TINGGI'),
        catatan_risiko TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        INDEX (npwp, tahun)
    );



-- 8. Tabel SPT Tahunan
CREATE TABLE spt_tahunan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npwp VARCHAR(20),
    tahun INT,
    peredaran_usaha DECIMAL(15,2) DEFAULT 0,
    persediaan_awal DECIMAL(15,2) DEFAULT 0,
    pembelian DECIMAL(15,2) DEFAULT 0,
    persediaan_akhir DECIMAL(15,2) DEFAULT 0,
    gaji DECIMAL(15,2) DEFAULT 0,
    sewa DECIMAL(15,2) DEFAULT 0,
    biaya_operasional DECIMAL(15,2) DEFAULT 0,
    biaya_penyusutan DECIMAL(15,2) DEFAULT 0,
    penghasilan_luar_usaha DECIMAL(15,2) DEFAULT 0,
    biaya_luar_usaha DECIMAL(15,2) DEFAULT 0,
    ptkp DECIMAL(15,2) DEFAULT 0,
    penghasilan_final DECIMAL(15,2) DEFAULT 0,
    penghasilan_bukan_pajak DECIMAL(15,2) DEFAULT 0,
    koreksi_fiskal_positif DECIMAL(15,2) DEFAULT 0,
    koreksi_fiskal_negatif DECIMAL(15,2) DEFAULT 0,
    kompensasi_kerugian DECIMAL(15,2) DEFAULT 0,
    kredit_bukti_potong DECIMAL(15,2) DEFAULT 0,
    kredit_pph_25 DECIMAL(15,2) DEFAULT 0,
    pajak_terutang DECIMAL(15,2) DEFAULT 0,
    setoran_pph_29 DECIMAL(15,2) DEFAULT 0,
    status_spt DECIMAL(15,2) DEFAULT 0 COMMENT 'NIHIL=0, Kurang Bayar > 0, Lebih Bayar < 0',
    norma DECIMAL(5,2) DEFAULT 0 COMMENT 'Persentase Norma Penghitungan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (npwp),
    INDEX (tahun)
);

-- 9. Tabel Validasi Lapangan (Pengganti audit_lapangan)
CREATE TABLE validasi_lapangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npwp VARCHAR(20),
    skor INT,
    lat_lokasi DECIMAL(10,8),
    lng_lokasi DECIMAL(11,8),
    lat_kegiatan DECIMAL(10,8),
    lng_kegiatan DECIMAL(11,8),
    link_foto_lokasi VARCHAR(255),
    link_foto_kegiatan VARCHAR(255),
    Alamat_sesuai TINYINT(1) DEFAULT 0,
    Ada_papan_nama TINYINT(1) DEFAULT 0,
    Ada_aktivitas TINYINT(1) DEFAULT 0,
    Jam_operasional_wajar TINYINT(1) DEFAULT 0,
    Aset_terlihat TINYINT(1) DEFAULT 0,
    Ada_pembukuan TINYINT(1) DEFAULT 0,
    Pembukuan_rapi TINYINT(1) DEFAULT 0,
    Faktur_tersimpan TINYINT(1) DEFAULT 0,
    PIC_menguasai TINYINT(1) DEFAULT 0,
    Penjelasan_wajar TINYINT(1) DEFAULT 0,
    Pegawai_sesuai_SPT TINYINT(1) DEFAULT 0,
    Jumlah_Pegawai INT DEFAULT 0,
    Alamat_fiktif TINYINT(1) DEFAULT 0,
    Kantor_virtual_sewa TINYINT(1) DEFAULT 0,
    Tidak_kooperatif TINYINT(1) DEFAULT 0,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (npwp)
);

-- 10. Tabel Daftar Pemilik / Pengurus Saham
CREATE TABLE daftar_pemilik (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npwp_perusahaan VARCHAR(20),
    nama VARCHAR(100) NOT NULL,
    nik VARCHAR(16),
    jabatan VARCHAR(50),
    telpon VARCHAR(20),
    email VARCHAR(100),
    nilai_saham DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (npwp_perusahaan),
    FOREIGN KEY (npwp_perusahaan) REFERENCES profil_wp(npwp) ON DELETE CASCADE
);

-- 11. Tabel Log Aktivitas User (Audit Trail)
CREATE TABLE log_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100),
    modul VARCHAR(100),
    aksi TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (created_at)
);


CREATE TABLE bukti_potong (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npwp VARCHAR(20) NOT NULL, -- NPWP Pemilik Data
    tahun INT NOT NULL,
    kode_objek_pajak VARCHAR(20),
    no_bupot VARCHAR(50),
    jenis_penerbitan ENUM('BPPU','BPNR','Penyetoran_Sendiri','Pemotongan_Digunggung','BP21_Selain_Pegawai','BP26_Asing','BPA1_Tahunan','BPA2_Tahunan','Bupot_Bulanan_Pegawai'),
    npwp_lawan VARCHAR(20),
    nama_lawan VARCHAR(100),
    dpp_bupot DECIMAL(15,2),
    nilai_pph DECIMAL(15,2),
    jenis_pajak ENUM('PPh_21','PPh_22','PPh_23','PPh_Final'),
    sifat_bupot ENUM('Final','Tidak Final') DEFAULT 'Tidak Final',
    fasilitas ENUM('ya','Tidak') DEFAULT 'Tidak',
    dilapor_spt ENUM('ya','tidak') DEFAULT 'tidak',
    dikreditkan ENUM('ya','tidak') DEFAULT 'tidak',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (npwp),
    INDEX (tahun)
);

-- 1. Tabel Faktur Pajak
CREATE TABLE IF NOT EXISTS faktur_pajak (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npwp VARCHAR(20) NOT NULL,
    tahun INT NOT NULL,
    jenis_faktur ENUM('MASUKAN', 'KELUARAN') NOT NULL,
    no_faktur VARCHAR(50) NOT NULL,
    tgl_faktur DATE,
    status VARCHAR(50) DEFAULT 'approved', -- draft, created, submited, dll
    masa_pajak INT,
    npwp_lawan VARCHAR(20),
    nama_lawan VARCHAR(150),
    dilaporkan_spt ENUM('ya', 'tidak') DEFAULT 'tidak',
    masa_kredit INT,
    dikreditkan ENUM('ya', 'tidak') DEFAULT 'ya',
    dpp DECIMAL(18,2) DEFAULT 0,
    ppn DECIMAL(18,2) DEFAULT 0,
    ppnbm DECIMAL(18,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (npwp),
    INDEX (tahun)
);

-- 14. Tabel Setoran Pajak (SSP/PBK)
CREATE TABLE IF NOT EXISTS setoran_pajak (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npwp VARCHAR(20) NOT NULL,
    tahun INT NOT NULL,
    jenis_pajak VARCHAR(50),
    jenis_setoran VARCHAR(50),
    map VARCHAR(10),
    kjs VARCHAR(10),
    nilai_setoran DECIMAL(15,2) DEFAULT 0,
    tgl_setor DATE,
    ntpn VARCHAR(20),
    dikreditkan ENUM('ya', 'tidak') DEFAULT 'tidak',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (npwp, tahun)
);

    


