<?php
require_once __DIR__ . '/includes/functions.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) {
    http_response_code(403); exit('Reset admin hanya boleh dari komputer server lokal.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? 'admin123';
    if (strlen($password) < 6) exit('Password minimal 6 karakter.');
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('UPDATE users SET password_hash=? WHERE username="admin" AND role="admin"');
    $stmt->execute([$hash]);
    echo '<h2>Password admin berhasil di-reset.</h2><p>Hapus file <code>reset_admin.php</code> setelah selesai.</p><p><a href="index.php">Kembali ke login</a></p>';
    exit;
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Reset Admin</title><style>body{font-family:Arial;max-width:480px;margin:50px auto;padding:20px}input,button{width:100%;padding:12px;margin-top:8px}button{cursor:pointer}</style></head><body><h1>Reset Password Admin</h1><p>Khusus komputer server lokal. Setelah selesai, hapus file ini.</p><form method="post"><label>Password baru<input name="password" type="password" value="admin123" minlength="6" required></label><button type="submit">Reset Password</button></form></body></html>
