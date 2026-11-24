<?php
// Konfigurasi dasar aplikasi SISTEM PRESENSI EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR

// Tampilkan error agar tidak menghasilkan HTTP ERROR 500 jika ada kendala
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'PRESENSI PRAMUKA MAN 4 BANJAR');
define('APP_VERSION', '2.0.0-PRAMUKA');

// Otomatis deteksi apakah aplikasi diletakkan di subfolder /absen_man4banjar atau di root domain hosting
if (!defined('BASE_URL')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_starts_with($script, '/absen_man4banjar')) {
        define('BASE_URL', '/absen_man4banjar');
    } else {
        define('BASE_URL', '');
    }
}

// Otomatis pilih kredensial: Jika di hosting InfinityFree (kesug.com) gunakan database hosting
$isHosting = isset($_SERVER['HTTP_HOST']) && (
    str_contains($_SERVER['HTTP_HOST'], 'kesug.com') || 
    str_contains($_SERVER['HTTP_HOST'], 'infinityfree') || 
    str_contains($_SERVER['HTTP_HOST'], 'byet')
);

if ($isHosting) {
    define('DB_HOST', 'sql103.byetcluster.com');
    define('DB_NAME', 'if0_43364197_absen');
    define('DB_USER', 'if0_43364197');
    define('DB_PASS', '71A8FJdTL2');
} else {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'absen_man4banjar');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Titik lokasi MAN 4 Banjar (Simpang Empat / Kabupaten Banjar)
define('DEFAULT_LATITUDE', -3.3134);
define('DEFAULT_LONGITUDE', 115.0886);
define('DEFAULT_RADIUS_METERS', 150);

require_once __DIR__ . '/../includes/database_setup.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsnWithDb = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsnWithDb, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $e) {
            // Coba sambung tanpa dbname untuk localhost XAMPP baru
            try {
                $dsnWithoutDb = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
                $pdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `" . DB_NAME . "`");
            } catch (Throwable $e2) {
                http_response_code(500);
                exit('<div style="font-family:sans-serif;padding:30px;max-width:650px;margin:50px auto;border:1.5px solid #f87171;border-radius:12px;background:#fef2f2;color:#991b1b;box-shadow:0 10px 25px rgba(0,0,0,0.1);">'
                    . '<h2 style="margin-top:0;">⚠️ Gagal Terhubung ke Database MySQL</h2>'
                    . '<p><b>Error:</b> ' . htmlspecialchars($e->getMessage()) . '</p>'
                    . '<hr style="border:0;border-top:1px solid #fca5a5;margin:15px 0;">'
                    . '<p style="font-size:14px;color:#7f1d1d;">Silakan periksa kembali kecocokan password di <code>config/config.php</code>:<br>'
                    . '&bull; <b>Host:</b> ' . htmlspecialchars(DB_HOST) . '<br>'
                    . '&bull; <b>Database:</b> ' . htmlspecialchars(DB_NAME) . '<br>'
                    . '&bull; <b>User:</b> ' . htmlspecialchars(DB_USER) . '</p>'
                    . '</div>');
            }
        }

        // Jalankan migrasi dan inisialisasi otomatis jika diperlukan
        try {
            ensure_database_migrated($pdo);
        } catch (Throwable $e) {}
    }
    return $pdo;
}
