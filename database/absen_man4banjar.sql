-- =========================================================
-- SISTEM PRESENSI EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR
-- DATABASE DUMP & SEED DATA
-- Generated for MAN 4 Banjar - Gudep Pramuka Penegak
-- =========================================================

-- (Baris CREATE DATABASE & USE dinonaktifkan untuk kompatibilitas Web Hosting)
-- CREATE DATABASE IF NOT EXISTS absen_man4banjar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE absen_man4banjar;

-- 1. TABEL PENGGUNA (USERS)
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  role ENUM('admin','pembina','pelatih','siswa','employee') NOT NULL DEFAULT 'siswa',
  kelas VARCHAR(50) NULL,
  gender ENUM('L','P') NULL,
  tingkat ENUM('X','XI','XII') NULL,
  sangga VARCHAR(50) NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  face_descriptor LONGTEXT NULL,
  face_registered_at DATETIME NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role),
  INDEX idx_tingkat_gender (tingkat, gender),
  INDEX idx_kelas (kelas)
) ENGINE=InnoDB;

-- 2. TABEL PENGATURAN (SETTINGS)
CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(80) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB;

-- 3. TABEL PERTEMUAN PRAMUKA (PERTEMUAN)
CREATE TABLE IF NOT EXISTS pertemuan_pramuka (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pertemuan_ke INT NOT NULL UNIQUE,
  hari_tanggal VARCHAR(100) NOT NULL,
  work_date DATE NOT NULL,
  materi_default VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. TABEL PRESENSI PELATIH
CREATE TABLE IF NOT EXISTS presensi_pelatih (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pelatih_name VARCHAR(150) NOT NULL,
  pertemuan_ke INT NOT NULL,
  hari_tanggal VARCHAR(100) NOT NULL,
  work_date DATE NOT NULL,
  materi VARCHAR(255) NULL,
  tanda_tangan MEDIUMTEXT NULL,
  status ENUM('hadir','izin','sakit','alpha') DEFAULT 'hadir',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pelatih_pertemuan (pelatih_name, pertemuan_ke)
) ENGINE=InnoDB;

-- 5. TABEL PRESENSI (ATTENDANCES)
CREATE TABLE IF NOT EXISTS attendances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  pertemuan_ke INT NULL,
  check_in TIME NULL,
  check_out TIME NULL,
  in_latitude DECIMAL(10,7) NULL,
  in_longitude DECIMAL(10,7) NULL,
  in_accuracy DECIMAL(10,2) NULL,
  in_distance DECIMAL(10,2) NULL,
  in_face_distance DECIMAL(10,5) NULL,
  out_latitude DECIMAL(10,7) NULL,
  out_longitude DECIMAL(10,7) NULL,
  out_accuracy DECIMAL(10,2) NULL,
  out_distance DECIMAL(10,2) NULL,
  out_face_distance DECIMAL(10,5) NULL,
  status ENUM('hadir','terlambat','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
  signature MEDIUMTEXT NULL,
  device VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_date (user_id, work_date),
  INDEX idx_pertemuan (pertemuan_ke),
  CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- INSERT DATA ADMIN, PEMBINA, DAN PELATIH
INSERT INTO users (username, name, role, password_hash, status) VALUES
('admin', 'Administrator Pramuka MAN 4 Banjar', 'admin', '$2y$10$xOAWBH1pydkqrr.9Eefs8esm.sRDuqZA6DjA8t9jDThX3MeaDCSjq', 1),
('pembina.putra', 'Fadlan Hidayat, S.Pd.', 'pembina', '$2y$10$TGmaJQCaoaHY.CcxMvdoL.ahbbnmvvQRbRSSELc3290tc3prwiZlS', 1),
('pembina.putri', 'Elda Riana Yaslitha, S.Pd.', 'pembina', '$2y$10$TGmaJQCaoaHY.CcxMvdoL.ahbbnmvvQRbRSSELc3290tc3prwiZlS', 1),
('wakamad.kesiswaan', 'Jiabus Sarury, S.Pd., M.A', 'pembina', '$2y$10$TGmaJQCaoaHY.CcxMvdoL.ahbbnmvvQRbRSSELc3290tc3prwiZlS', 1),
('pelatih.haifad', 'M. Haifad Ilham', 'pelatih', '$2y$10$ywSsGx595Q38rnvI8HIvieFfezR9gvzwd70sWM3gr1HbesOnJSI1y', 1),
('pelatih.aulia', 'Aulia Ika Noorjannah', 'pelatih', '$2y$10$ywSsGx595Q38rnvI8HIvieFfezR9gvzwd70sWM3gr1HbesOnJSI1y', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), password_hash=VALUES(password_hash), status=1;

-- INSERT DATA SETTINGS
INSERT INTO settings (setting_key, setting_value) VALUES
('school_name', 'MAN 4 BANJAR'),
('school_fullname', 'Madrasah Aliyah Negeri 4 Banjar'),
('gudep_number', '01.095 - 01.096'),
('ambalan_motto', 'Al Ittihadu Asasun Najah • Become a Legend • Ahlul Haq'),
('app_title', 'Sistem Presensi Ekstrakurikuler Pramuka MAN 4 Banjar'),
('pembina_putra', 'Fadlan Hidayat, S.Pd.'),
('nip_pembina_putra', '198710022019031003'),
('nip_pembina_putra_alt', '198903082019031008'),
('pembina_putri', 'Elda Riana Yaslitha, S.Pd.'),
('nip_pembina_putri', '199404082025212011'),
('wakamad_kesiswaan', 'Jiabus Sarury, S.Pd., M.A'),
('nip_wakamad_kesiswaan', '19930822201903100'),
('pelatih_putra', 'M. Haifad Ilham'),
('pelatih_putri', 'Aulia Ika Noorjannah'),
('office_latitude', '-3.3134000'),
('office_longitude', '115.0886000'),
('office_radius_meters', '150'),
('attendance_start', '15:30'),
('attendance_end', '17:30')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- INSERT DATA 4 PERTEMUAN BULAN NOVEMBER 2026
INSERT INTO pertemuan_pramuka (pertemuan_ke, hari_tanggal, work_date, materi_default) VALUES
(1, 'Kamis, 5 November 2026', '2026-11-05', 'Pengenalan & Orientasi Penegak Bantara'),
(2, 'Kamis, 12 November 2026', '2026-11-12', 'Tali Temali, Pionering & Simpul Dasar'),
(3, 'Kamis, 19 November 2026', '2026-11-19', 'Semaphore, Sandi Morse & Kompas Navigasi'),
(4, 'Kamis, 26 November 2026', '2026-11-26', 'PBB Variasi, Formasi Barisan & Upacara')
ON DUPLICATE KEY UPDATE hari_tanggal=VALUES(hari_tanggal), work_date=VALUES(work_date), materi_default=VALUES(materi_default);

-- INSERT DATA PRESENSI PELATIH (4 PERTEMUAN)
INSERT INTO presensi_pelatih (pelatih_name, pertemuan_ke, hari_tanggal, work_date, materi, tanda_tangan, status) VALUES
('M. Haifad Ilham', 1, 'Kamis, 5 November 2026', '2026-11-05', 'Pengenalan & Orientasi Penegak Bantara', 'TTD', 'hadir'),
('M. Haifad Ilham', 2, 'Kamis, 12 November 2026', '2026-11-12', 'Tali Temali, Pionering & Simpul Dasar', 'TTD', 'hadir'),
('M. Haifad Ilham', 3, 'Kamis, 19 November 2026', '2026-11-19', 'Semaphore, Sandi Morse & Kompas Navigasi', 'TTD', 'hadir'),
('M. Haifad Ilham', 4, 'Kamis, 26 November 2026', '2026-11-26', 'PBB Variasi, Formasi Barisan & Upacara', 'TTD', 'hadir'),
('Aulia Ika Noorjannah', 1, 'Kamis, 5 November 2026', '2026-11-05', 'Pengenalan & Orientasi Penegak Bantara', 'TTD', 'hadir'),
('Aulia Ika Noorjannah', 2, 'Kamis, 12 November 2026', '2026-11-12', 'Tali Temali, Pionering & Simpul Dasar', 'TTD', 'hadir'),
('Aulia Ika Noorjannah', 3, 'Kamis, 19 November 2026', '2026-11-19', 'Semaphore, Sandi Morse & Kompas Navigasi', 'TTD', 'hadir'),
('Aulia Ika Noorjannah', 4, 'Kamis, 26 November 2026', '2026-11-26', 'PBB Variasi, Formasi Barisan & Upacara', 'TTD', 'hadir')
ON DUPLICATE KEY UPDATE materi=VALUES(materi), tanda_tangan=VALUES(tanda_tangan), status=VALUES(status);

-- INSERT DATA 113 SISWA EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR
-- Password default siswa: pramuka123
INSERT INTO users (username, name, role, kelas, gender, tingkat, password_hash, status) VALUES
('X-PI-01', 'Ahlam Salimah Raharjo', 'siswa', 'X – C', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-02', 'Amanda Putri Ramadhani', 'siswa', 'X – E', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-03', 'Anisah Nabila Ramadhani', 'siswa', 'X – F', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-04', 'Arini Fahma Sania', 'siswa', 'X – C', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-05', 'Athaya Naura Musyaffa', 'siswa', 'X – F', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-06', 'Bilqis', 'siswa', 'X – C', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-07', 'Dita Fitri Azzahra', 'siswa', 'X – D', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-08', 'Fatimatuzzahra', 'siswa', 'X – D', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-09', 'Hani Oktaviani', 'siswa', 'X – E', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-10', 'Haudhina Hadziqoh', 'siswa', 'X – E', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-11', 'Hilyatul Aulia', 'siswa', 'X – C', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-12', 'Isma Safitri Ramadhani', 'siswa', 'X – D', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-13', 'Khairunnisa', 'siswa', 'X – G', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-14', 'Khalishah Aina', 'siswa', 'X – B', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-15', 'Laila Novia Azizah', 'siswa', 'X – E', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-16', 'Laisya Putri Rajabi', 'siswa', 'X – F', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-17', 'Lubna Huwayda', 'siswa', 'X – E', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-18', 'Marwah Azizah', 'siswa', 'X – B', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-19', 'Naira Rizky Ramadhani', 'siswa', 'X – D', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-20', 'Naila Rizkia', 'siswa', 'X – B', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-21', 'Nawa Rahma Ramadhani', 'siswa', 'X – F', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-22', 'Nabila Ramadhani', 'siswa', 'X – A', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-23', 'Nazwa Salsa Bella', 'siswa', 'X – C', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-24', 'Noor Mauleda', 'siswa', 'X – C', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-25', 'Nova Ayu Dea', 'siswa', 'X – F', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-26', 'Nur Elisa Rahma', 'siswa', 'X – C', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-27', 'Nur Kamalia', 'siswa', 'X – F', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-28', 'Nur Rosyda', 'siswa', 'X – B', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-29', 'Puspa Mulya Sari', 'siswa', 'X – E', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-30', 'Sarah Adifa', 'siswa', 'X – F', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-31', 'Shofia Liviana', 'siswa', 'X – A', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-32', 'Shofia Salsabila', 'siswa', 'X – A', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-33', 'Yuna Nurlia', 'siswa', 'X – D', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PI-34', 'Zahra Putri Kholifah', 'siswa', 'X – E', 'P', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-01', 'Muhammad Adib', 'siswa', 'X – F', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-02', 'Muhammad Adam Taufani', 'siswa', 'X – B', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-03', 'Muhammad Azam', 'siswa', 'X – G', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-04', 'Muhammad Dhafi', 'siswa', 'X – C', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-05', 'Muhammad Haikal Elfa Reski', 'siswa', 'X – C', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-06', 'Muhammad Maulana Yusuf', 'siswa', 'X – F', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-07', 'Muhammad Nabil Dzikri', 'siswa', 'X – D', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-08', 'Muhammad Nasuha', 'siswa', 'X – F', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-09', 'Muhammad Rofi', 'siswa', 'X – F', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-10', 'Muhammad Roffi Ramadhan', 'siswa', 'X – G', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-11', 'Muhammad Royan', 'siswa', 'X – F', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-12', 'Muhammad Sabian Apriandi', 'siswa', 'X – G', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-13', 'Muhammad Zulmi Ikhwan Khairi', 'siswa', 'X – G', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-14', 'Reza Anugrah Akbar', 'siswa', 'X – A', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-15', 'Yendy Kivan Pramudiya', 'siswa', 'X-PK PA', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('X-PA-16', 'Yusuf Raditya Wardana', 'siswa', 'X – A', 'L', 'X', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-01', 'Aira Safitri', 'siswa', 'XI – A', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-02', 'Aisya Ghina Azkia', 'siswa', 'XI – A', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-03', 'Almira Maryam Nuraisyah', 'siswa', 'XI – D', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-04', 'Alfina Mawaddah', 'siswa', 'XI – F', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-05', 'Amelia Nur Azzahra', 'siswa', 'XI – A', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-06', 'Aurelia Dwi Saskyara', 'siswa', 'XI – F', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-07', 'Danisa Salsabila', 'siswa', 'XI – F', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-08', 'Fatimatuzzahra', 'siswa', 'XI – E', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-09', 'Kasih Nabila Herawati', 'siswa', 'XI – B', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-10', 'Laila Najwa', 'siswa', 'XI – E', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-11', 'Nabilatul Muti’ah', 'siswa', 'XI – A', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-12', 'Naila Azizah', 'siswa', 'XI – D', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-13', 'Naila Salsabila', 'siswa', 'XI – C', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-14', 'Nawra Azzahra', 'siswa', 'XI – F', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-15', 'Nasywa Amira Hasana', 'siswa', 'XI – D', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-16', 'Noor Aina Khadijah', 'siswa', 'XI – E', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-17', 'Noor Syifa Humairoh', 'siswa', 'XI – D', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-18', 'Nur Camelia Annisa Rahmah', 'siswa', 'XI – A', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-19', 'Rafifa Humaira Djasida', 'siswa', 'XI – A', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-20', 'Rahmi Addini', 'siswa', 'XI – D', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-21', 'Safna Miskia', 'siswa', 'XI – B', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-22', 'Sanawiyah', 'siswa', 'XI – A', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-23', 'Syara Ashy Shaffa', 'siswa', 'XI – C', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-24', 'Siti Adelia Nur Khayla', 'siswa', 'XI – D', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-25', 'Syahira Hayati', 'siswa', 'XI – F', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-26', 'Ulfatuzzahra', 'siswa', 'XI – C', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PI-27', 'Zahrotussyifa', 'siswa', 'XI – A', 'P', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PA-01', 'Achmad Musthofa', 'siswa', 'XI – B', 'L', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PA-02', 'Maulana Ibrahim', 'siswa', 'XI – G', 'L', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PA-03', 'Muhammad', 'siswa', 'XI – G', 'L', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PA-04', 'Muhammad Alfarizi', 'siswa', 'XI – A', 'L', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PA-05', 'Muhammad Khoiril Ahyar', 'siswa', 'XI – D', 'L', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XI-PA-06', 'Muhammad Zaki Aditya', 'siswa', 'XI – D', 'L', 'XI', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-01', 'Amellia Zahra', 'siswa', 'XII – E', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-02', 'Aminah Lailatul Qomariah', 'siswa', 'XII – G', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-03', 'Bela Ramadayanti', 'siswa', 'XII – G', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-04', 'Ghina Humaira', 'siswa', 'XII – E', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-05', 'Gina Syafina', 'siswa', 'XII – E', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-06', 'Jihan Sa’bana', 'siswa', 'XII – D', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-07', 'Laily Mufidah', 'siswa', 'XII – E', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-08', 'Mahya Zakiya', 'siswa', 'XII – D', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-09', 'Nadia Rizky Amalia', 'siswa', 'XII – F', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-10', 'Nadien', 'siswa', 'XII – G', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-11', 'Noor Nayla', 'siswa', 'XII – D', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-12', 'Nur Haliza', 'siswa', 'XII – C', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-13', 'Putri Safitri', 'siswa', 'XII – G', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-14', 'Raisya Aura Khafifah', 'siswa', 'XII – F', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-15', 'Rayva Nabila', 'siswa', 'XII – D', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-16', 'Rizkia Fajarina', 'siswa', 'XII – A', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-17', 'Salsabila Fitriana', 'siswa', 'XII – G', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-18', 'Siti Maisarah', 'siswa', 'XII – D', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PI-19', 'Siti Munawarah', 'siswa', 'XII – B', 'P', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-01', 'Abimanyu Galih Istanto', 'siswa', 'XII – F', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-02', 'Akhmad Dairobi', 'siswa', 'XII – A', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-03', 'Muhammad Aqsha Al Anshar', 'siswa', 'XII – B', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-04', 'Muhammad Aulia Rahman', 'siswa', 'XII – G', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-05', 'Muhammad Haekal', 'siswa', 'XII – A', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-06', 'Muhammad Haikal Ridhoni', 'siswa', 'XII – B', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-07', 'Muhammad Iqbal Arlizan', 'siswa', 'XII – G', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-08', 'Muhammad Nabil Adabi', 'siswa', 'XII – A', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-09', 'Muhammad Sam’ani', 'siswa', 'XII – F', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-10', 'Muhammad Zein Amrullah Badali', 'siswa', 'XII – B', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1),
('XII-PA-11', 'Rasya Ahmada', 'siswa', 'XII – E', 'L', 'XII', '$2y$10$c.GR4Y/4YnJoJKQdW4eaYeVg5/7qkY5kJKiwLBD31qWEDwu94SvQ2', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), kelas=VALUES(kelas), gender=VALUES(gender), tingkat=VALUES(tingkat), role='siswa', status=1;
