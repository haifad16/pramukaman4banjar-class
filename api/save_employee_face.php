<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Metode tidak valid.');
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) throw new Exception('Token keamanan tidak valid.');
    
    $user = current_user();
    $id = (int)($_POST['id'] ?? 0);
    $desc = parse_descriptor($_POST['descriptor'] ?? '');

    if ($id <= 0 || !$desc) {
        throw new Exception('Template wajah tidak valid atau belum lengkap.');
    }

    // Hanya admin yang bisa mendaftarkan wajah orang lain; user biasa hanya bisa mendaftarkan wajahnya sendiri
    if ($user['role'] !== 'admin' && (int)$user['id'] !== $id) {
        throw new Exception('Anda hanya memiliki izin untuk merekam data wajah akun Anda sendiri.');
    }

    $stmt = db()->prepare('UPDATE users SET face_descriptor = ?, face_registered_at = ? WHERE id = ?');
    $stmt->execute([
        json_encode($desc, JSON_UNESCAPED_SLASHES), 
        timezone_now()->format('Y-m-d H:i:s'), 
        $id
    ]);

    // Jika yang update adalah user yang sedang login, update session cache-nya juga
    if ((int)$user['id'] === $id) {
        $_SESSION['user_face_descriptor'] = json_encode($desc, JSON_UNESCAPED_SLASHES);
    }

    echo json_encode(['ok' => true, 'message' => 'Template biometrik wajah berhasil direkam dan disimpan.']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
