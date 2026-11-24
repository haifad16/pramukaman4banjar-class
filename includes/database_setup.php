<?php
// Otomatisasi inisialisasi & migrasi database Absensi Pramuka MAN 4 Banjar

function ensure_database_migrated(PDO $pdo): void {
    static $migrated = false;
    if ($migrated) return;

    try {
        // 1. Pastikan tabel users ada dengan struktur lengkap
        $pdo->exec("
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
        ");

        // Periksa dan sesuaikan kolom struktur tabel users jika diperlukan
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $hasCol = array_flip($cols);

        if (!isset($hasCol['kelas'])) $pdo->exec("ALTER TABLE users ADD COLUMN kelas VARCHAR(50) NULL AFTER role");
        if (!isset($hasCol['gender'])) $pdo->exec("ALTER TABLE users ADD COLUMN gender ENUM('L','P') NULL AFTER kelas");
        if (!isset($hasCol['tingkat'])) $pdo->exec("ALTER TABLE users ADD COLUMN tingkat ENUM('X','XI','XII') NULL AFTER gender");
        if (!isset($hasCol['sangga'])) $pdo->exec("ALTER TABLE users ADD COLUMN sangga VARCHAR(50) NULL AFTER tingkat");
        if (!isset($hasCol['email'])) $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL");
        if (!isset($hasCol['phone'])) $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email");
        if (!isset($hasCol['face_registered_at'])) $pdo->exec("ALTER TABLE users ADD COLUMN face_registered_at DATETIME NULL AFTER face_descriptor");

        // 2. Tabel settings
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(80) NOT NULL UNIQUE,
                setting_value TEXT NOT NULL
            ) ENGINE=InnoDB;
        ");

        // 3. Tabel pertemuan pramuka
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pertemuan_pramuka (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                pertemuan_ke INT NOT NULL UNIQUE,
                hari_tanggal VARCHAR(100) NOT NULL,
                work_date DATE NOT NULL,
                materi_default VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;
        ");

        // 4. Tabel presensi pelatih
        $pdo->exec("
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
        ");

        // 5. Tabel attendances
        $pdo->exec("
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
        ");

        // Periksa kolom attendances jika tabel lama
        $attCols = $pdo->query("SHOW COLUMNS FROM attendances")->fetchAll(PDO::FETCH_COLUMN);
        $hasAttCol = array_flip($attCols);
        if (!isset($hasAttCol['pertemuan_ke'])) $pdo->exec("ALTER TABLE attendances ADD COLUMN pertemuan_ke INT NULL AFTER work_date");
        if (!isset($hasAttCol['signature'])) {
            $pdo->exec("ALTER TABLE attendances ADD COLUMN signature MEDIUMTEXT NULL AFTER status");
        } else {
            // Upgrade signature column ke MEDIUMTEXT jika masih VARCHAR
            try { $pdo->exec("ALTER TABLE attendances MODIFY COLUMN signature MEDIUMTEXT NULL"); } catch (Throwable $e) {}
        }

        // Upgrade presensi_pelatih tanda_tangan ke MEDIUMTEXT
        try { $pdo->exec("ALTER TABLE presensi_pelatih MODIFY COLUMN tanda_tangan MEDIUMTEXT NULL"); } catch (Throwable $e) {}

        // Auto-sinkronkan pertemuan_ke & signature untuk data attendances lama yang masih NULL
        try {
            $pdo->exec("
                UPDATE attendances 
                SET pertemuan_ke = CASE 
                    WHEN work_date <= '2026-11-05' THEN 1
                    WHEN work_date <= '2026-11-12' THEN 2
                    WHEN work_date <= '2026-11-19' THEN 3
                    ELSE 4
                END,
                signature = 'TTD'
                WHERE pertemuan_ke IS NULL
            ");
        } catch (Throwable $e) {}

        // 6. Cek apakah data master siswa sudah ada
        $studentCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn();
        if ($studentCount < 100) {
            $master = require __DIR__ . '/pramuka_data.php';

            // Hash passwords
            $pwAdmin = password_hash('admin123', PASSWORD_DEFAULT);
            $pwPembina = password_hash('pembina123', PASSWORD_DEFAULT);
            $pwPelatih = password_hash('pelatih123', PASSWORD_DEFAULT);
            $pwSiswa = password_hash('pramuka123', PASSWORD_DEFAULT);

            // Seed Admin, Pembina, Pelatih
            $usersSeed = [
                ['admin', 'Administrator Pramuka MAN 4 Banjar', 'admin', null, null, null, $pwAdmin],
                ['pembina.putra', 'Fadlan Hidayat, S.Pd.', 'pembina', null, 'L', null, $pwPembina],
                ['pembina.putri', 'Elda Riana Yaslitha, S.Pd.', 'pembina', null, 'P', null, $pwPembina],
                ['wakamad.kesiswaan', 'Jiabus Sarury, S.Pd., M.A', 'pembina', null, 'L', null, $pwPembina],
                ['pelatih.haifad', 'M. Haifad Ilham', 'pelatih', null, 'L', null, $pwPelatih],
                ['pelatih.aulia', 'Aulia Ika Noorjannah', 'pelatih', null, 'P', null, $pwPelatih],
            ];

            $stmtUser = $pdo->prepare("
                INSERT INTO users (username, name, role, kelas, gender, tingkat, password_hash, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), password_hash=VALUES(password_hash), status=1
            ");
            foreach ($usersSeed as $u) {
                $stmtUser->execute($u);
            }

            // Seed Settings
            $settingsData = [
                'school_name' => 'MAN 4 BANJAR',
                'school_fullname' => 'Madrasah Aliyah Negeri 4 Banjar',
                'gudep_number' => '01.095 - 01.096',
                'ambalan_motto' => 'Al Ittihadu Asasun Najah • Become a Legend • Ahlul Haq',
                'app_title' => 'Sistem Presensi Ekstrakurikuler Pramuka MAN 4 Banjar',
                'pembina_putra' => 'Fadlan Hidayat, S.Pd.',
                'nip_pembina_putra' => '198710022019031003',
                'nip_pembina_putra_alt' => '198903082019031008',
                'pembina_putri' => 'Elda Riana Yaslitha, S.Pd.',
                'nip_pembina_putri' => '199404082025212011',
                'wakamad_kesiswaan' => 'Jiabus Sarury, S.Pd., M.A',
                'nip_wakamad_kesiswaan' => '19930822201903100',
                'pelatih_putra' => 'M. Haifad Ilham',
                'pelatih_putri' => 'Aulia Ika Noorjannah',
                'office_latitude' => '-3.3134000',
                'office_longitude' => '115.0886000',
                'office_radius_meters' => '150',
                'attendance_start' => '15:30',
                'attendance_end' => '17:30',
            ];
            $stmtSet = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            foreach ($settingsData as $k => $v) {
                $stmtSet->execute([$k, $v]);
            }

            // Seed Pertemuan
            $stmtPertemuan = $pdo->prepare("
                INSERT INTO pertemuan_pramuka (pertemuan_ke, hari_tanggal, work_date, materi_default)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE hari_tanggal=VALUES(hari_tanggal), work_date=VALUES(work_date), materi_default=VALUES(materi_default)
            ");
            foreach ($master['pertemuan'] as $p) {
                $stmtPertemuan->execute([$p['ke'], $p['hari'] . ', ' . $p['tanggal'], $p['work_date'], $p['materi']]);
            }

            // Seed Presensi Pelatih
            $stmtPelatih = $pdo->prepare("
                INSERT INTO presensi_pelatih (pelatih_name, pertemuan_ke, hari_tanggal, work_date, materi, tanda_tangan, status)
                VALUES (?, ?, ?, ?, ?, 'TTD', 'hadir')
                ON DUPLICATE KEY UPDATE materi=VALUES(materi), tanda_tangan=VALUES(tanda_tangan), status=VALUES(status)
            ");
            foreach ($master['pelatih'] as $pl) {
                foreach ($master['pertemuan'] as $ke => $pm) {
                    $materi = $pl['materi'][$ke] ?? $pm['materi'];
                    $stmtPelatih->execute([$pl['nama'], $ke, $pm['hari'] . ', ' . $pm['tanggal'], $pm['work_date'], $materi]);
                }
            }

            // Seed 113 Siswa
            $counters = [
                'X_P' => 1, 'X_L' => 1,
                'XI_P' => 1, 'XI_L' => 1,
                'XII_P' => 1, 'XII_L' => 1,
            ];
            $stmtSiswa = $pdo->prepare("
                INSERT INTO users (username, name, role, kelas, gender, tingkat, password_hash, status)
                VALUES (?, ?, 'siswa', ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE name=VALUES(name), kelas=VALUES(kelas), gender=VALUES(gender), tingkat=VALUES(tingkat), role='siswa', status=1
            ");

            foreach ($master['siswa'] as $s) {
                $key = $s['tingkat'] . '_' . $s['gender'];
                $idx = $counters[$key]++;
                $genderCode = $s['gender'] === 'L' ? 'PA' : 'PI';
                $username = sprintf("%s-%s-%02d", $s['tingkat'], $genderCode, $idx);
                $stmtSiswa->execute([$username, $s['nama'], $s['kelas'], $s['gender'], $s['tingkat'], $pwSiswa]);
            }
        }

        $migrated = true;
    } catch (Throwable $e) {
        // Abaikan error perizinan jika koneksi terbatas
    }
}

