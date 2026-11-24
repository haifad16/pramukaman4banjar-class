<?php
require_once __DIR__ . '/../config/config.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}

function registration_csrf_token(): string {
    if (empty($_SESSION['registration_csrf'])) {
        $_SESSION['registration_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['registration_csrf'];
}

function verify_registration_csrf(): void {
    $token = $_POST['registration_csrf'] ?? '';
    if (!hash_equals($_SESSION['registration_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Token pendaftaran sudah kedaluwarsa. Silakan muat ulang halaman pendaftaran lalu isi kembali data.');
    }
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user !== null) return $user;
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = 1 LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login(): void {
    if (!current_user()) redirect('/index.php');
}

function require_admin(): void {
    require_login();
    $role = current_user()['role'] ?? '';
    if (!in_array($role, ['admin', 'pembina', 'pelatih'], true)) {
        http_response_code(403);
        exit('Akses ditolak. Halaman khusus Administrator / Pembina / Pelatih Pramuka.');
    }
}

function is_admin(): bool {
    return (current_user()['role'] ?? '') === 'admin';
}

function is_pembina_or_admin(): bool {
    $role = current_user()['role'] ?? '';
    return in_array($role, ['admin', 'pembina'], true);
}

function setting(string $key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
        } catch (Throwable $e) {
            return $default;
        }
    }
    return $settings[$key] ?? $default;
}

function office_lat(): float { return (float) setting('office_latitude', DEFAULT_LATITUDE); }
function office_lng(): float { return (float) setting('office_longitude', DEFAULT_LONGITUDE); }
function office_radius(): float { return (float) setting('office_radius_meters', DEFAULT_RADIUS_METERS); }
function attendance_start(): string { return setting('attendance_start', '07:30'); }
function attendance_end(): string { return setting('attendance_end', '08:00'); }
function timezone_now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar')); }

function haversine_m(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earth = 6371000;
    $phi1 = deg2rad($lat1); $phi2 = deg2rad($lat2);
    $dPhi = deg2rad($lat2 - $lat1); $dLambda = deg2rad($lon2 - $lon1);
    $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
    return 2 * $earth * asin(min(1, sqrt($a)));
}


/** Normalize Indonesian WhatsApp numbers to a digits-only 62-prefixed format. */
function normalize_whatsapp_phone($value): ?string {
    if (!is_string($value) && !is_numeric($value)) return null;
    $digits = preg_replace('/\D+/', '', (string)$value);
    if (!$digits) return null;

    if (substr($digits, 0, 2) === '00') {
        $digits = substr($digits, 2);
    }
    if (substr($digits, 0, 3) === '628') {
        // Already in international format.
    } elseif (substr($digits, 0, 1) === '0') {
        $digits = '62' . substr($digits, 1);
    } elseif (substr($digits, 0, 1) === '8') {
        $digits = '62' . $digits;
    }

    // Indonesian mobile numbers generally start with 628 and contain 10-14 digits total.
    if (!preg_match('/^628[0-9]{7,11}$/', $digits)) return null;
    return $digits;
}

/** Return true if this WhatsApp number is already used as a username or phone. */
function whatsapp_number_exists(string $canonicalPhone, ?int $excludeUserId = null): bool {
    $rows = db()->query('SELECT id, username, phone FROM users')->fetchAll();
    foreach ($rows as $row) {
        if ($excludeUserId !== null && (int)$row['id'] === $excludeUserId) continue;
        $usernamePhone = normalize_whatsapp_phone((string)($row['username'] ?? ''));
        $storedPhone = normalize_whatsapp_phone((string)($row['phone'] ?? ''));
        if ($usernamePhone === $canonicalPhone || $storedPhone === $canonicalPhone) return true;
    }
    return false;
}

function parse_descriptor($value): ?array {
    if (!is_string($value) || strlen($value) > 20000) return null;
    $decoded = json_decode($value, true);
    if (!is_array($decoded) || count($decoded) !== 128) return null;
    foreach ($decoded as $n) {
        if (!is_numeric($n)) return null;
    }
    return array_map('floatval', $decoded);
}

function descriptor_distance(array $a, array $b): ?float {
    if (count($a) !== count($b) || !$a || !$b) return null;
    $sum = 0.0;
    foreach ($a as $i => $v) {
        $d = $v - $b[$i];
        $sum += $d * $d;
    }
    return sqrt($sum);
}

function today_attendance(int $userId): ?array {
    $date = timezone_now()->format('Y-m-d');
    
    // 1. Cek berdasarkan tanggal WITA atau CURDATE server
    $stmt = db()->prepare('
        SELECT * FROM attendances 
        WHERE user_id = ? AND (work_date = ? OR work_date = CURDATE()) 
        ORDER BY id DESC LIMIT 1
    ');
    $stmt->execute([$userId, $date]);
    $row = $stmt->fetch();
    if ($row) return $row;

    // 2. Fallback: jika ada selisih zona waktu server hosting luar negeri, ambil absensi dalam rentang 1 hari
    $stmtFallback = db()->prepare('SELECT * FROM attendances WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmtFallback->execute([$userId]);
    $latest = $stmtFallback->fetch();
    if ($latest && !empty($latest['work_date'])) {
        $diffDays = (strtotime($date) - strtotime($latest['work_date'])) / 86400;
        if (abs($diffDays) <= 1) {
            return $latest;
        }
    }
    return null;
}

/** Ambil daftar 4 pertemuan wajib Pramuka */
function get_pertemuan_pramuka(): array {
    try {
        $rows = db()->query('SELECT * FROM pertemuan_pramuka ORDER BY pertemuan_ke ASC')->fetchAll();
        if ($rows) return $rows;
    } catch (Throwable $e) {}
    $master = require __DIR__ . '/pramuka_data.php';
    return array_values($master['pertemuan']);
}

/** Ambil presensi pelatih */
function get_presensi_pelatih_data(): array {
    try {
        $rows = db()->query('SELECT * FROM presensi_pelatih ORDER BY pelatih_name ASC, pertemuan_ke ASC')->fetchAll();
        if ($rows) {
            $grouped = [];
            foreach ($rows as $r) {
                $grouped[$r['pelatih_name']][] = $r;
            }
            return $grouped;
        }
    } catch (Throwable $e) {}
    $master = require __DIR__ . '/pramuka_data.php';
    $grouped = [];
    foreach ($master['pelatih'] as $p) {
        $list = [];
        foreach ($master['pertemuan'] as $ke => $pm) {
            $list[] = [
                'pelatih_name' => $p['nama'],
                'pertemuan_ke' => $ke,
                'hari_tanggal' => $pm['hari'] . ', ' . $pm['tanggal'],
                'work_date' => $pm['work_date'],
                'materi' => $p['materi'][$ke] ?? $pm['materi'],
                'tanda_tangan' => 'TTD',
                'status' => 'hadir',
            ];
        }
        $grouped[$p['nama']] = $list;
    }
    return $grouped;
}

/** Ambil daftar siswa dikelompokkan berdasarkan tingkatan dan gender */
function get_siswa_pramuka_grouped(): array {
    $groups = [
        'Kelas X Putri' => ['tingkat' => 'X', 'gender' => 'P', 'title' => 'PRESENSI SISWA EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR - KELAS X PUTRI'],
        'Kelas X Putra' => ['tingkat' => 'X', 'gender' => 'L', 'title' => 'PRESENSI SISWA EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR - KELAS X PUTRA'],
        'Kelas XI Putri' => ['tingkat' => 'XI', 'gender' => 'P', 'title' => 'PRESENSI SISWA EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR - KELAS XI PUTRI'],
        'Kelas XI Putra' => ['tingkat' => 'XI', 'gender' => 'L', 'title' => 'PRESENSI SISWA EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR - KELAS XI PUTRA'],
        'Kelas XII Putri' => ['tingkat' => 'XII', 'gender' => 'P', 'title' => 'PRESENSI SISWA EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR - KELAS XII PUTRI'],
        'Kelas XII Putra' => ['tingkat' => 'XII', 'gender' => 'L', 'title' => 'PRESENSI SISWA EKSTRAKURIKULER PRAMUKA MAN 4 BANJAR - KELAS XII PUTRA'],
    ];

    $result = [];
    try {
        $stmt = db()->query("SELECT id, username, name, kelas, gender, tingkat, status FROM users WHERE role = 'siswa' ORDER BY id ASC");
        $allStudents = $stmt->fetchAll();
        if ($allStudents) {
            foreach ($groups as $gName => $meta) {
                $filtered = array_values(array_filter($allStudents, function($s) use ($meta) {
                    return ($s['tingkat'] === $meta['tingkat'] && $s['gender'] === $meta['gender']);
                }));
                $result[$gName] = [
                    'meta' => $meta,
                    'students' => $filtered,
                ];
            }
            return $result;
        }
    } catch (Throwable $e) {}

    // Fallback dari master data jika DB belum terkoneksi
    $master = require __DIR__ . '/pramuka_data.php';
    foreach ($groups as $gName => $meta) {
        $filtered = array_values(array_filter($master['siswa'], function($s) use ($gName) {
            return $s['kategori'] === $gName;
        }));
        $result[$gName] = [
            'meta' => $meta,
            'students' => $filtered,
        ];
    }
    return $result;
}

