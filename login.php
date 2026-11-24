<?php
require_once __DIR__ . '/includes/functions.php';
verify_csrf();

// 0. Validasi Captcha
$captcha = trim($_POST['captcha'] ?? '');
$expectedCaptcha = $_SESSION['login_captcha_ans'] ?? null;
if ($captcha === '' || $expectedCaptcha === null || (int)$captcha !== (int)$expectedCaptcha) {
    flash('error', 'Kode keamanan (Captcha) salah atau belum diisi. Silakan coba lagi.');
    redirect('/index.php');
}
unset($_SESSION['login_captcha_ans']);

$identifier = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$user = null;

if ($identifier !== '') {
    // 1. Prioritas Utama: Cocokkan Nama Lengkap persis (case-insensitive)
    $stmt = db()->prepare('SELECT * FROM users WHERE LOWER(TRIM(name)) = LOWER(?) AND status = 1 LIMIT 1');
    $stmt->execute([$identifier]);
    $user = $stmt->fetch() ?: null;

    // 2. Jika belum cocok, cari dengan toleransi tanda titik dan koma (misal gelar/singkatan)
    if (!$user) {
        $cleanId = preg_replace('/\s+/', ' ', trim(str_replace(['.', ','], ' ', $identifier)));
        $stmt = db()->prepare('SELECT * FROM users WHERE REPLACE(REPLACE(LOWER(name), ".", " "), ",", " ") = LOWER(?) AND status = 1 LIMIT 1');
        $stmt->execute([$cleanId]);
        $user = $stmt->fetch() ?: null;
    }

    // 3. Fallback: Cari berdasarkan Username (misal admin, pelatih.haifad, X-PI-01)
    if (!$user) {
        $stmt = db()->prepare('SELECT * FROM users WHERE LOWER(TRIM(username)) = LOWER(?) AND status = 1 LIMIT 1');
        $stmt->execute([$identifier]);
        $user = $stmt->fetch() ?: null;
    }

    // 4. Fallback: Cari jika nomor WhatsApp yang diinputkan
    if (!$user) {
        $canonicalPhone = normalize_whatsapp_phone($identifier);
        if ($canonicalPhone !== null) {
            $stmt = db()->prepare('SELECT * FROM users WHERE (username = ? OR phone = ?) AND status = 1 LIMIT 1');
            $stmt->execute([$canonicalPhone, $canonicalPhone]);
            $user = $stmt->fetch() ?: null;
        }
    }
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    flash('error', 'Nama lengkap atau password salah. Pastikan ejaan nama sesuai daftar anggota.');
    redirect('/index.php');
}
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['csrf'] = bin2hex(random_bytes(24));
redirect('/dashboard.php');
