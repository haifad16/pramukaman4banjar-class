<?php
require_once __DIR__ . '/functions.php';
$user = current_user();
$flash = get_flash();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

// Helper for user initials
$initials = 'P';
if ($user && !empty($user['name'])) {
    $parts = explode(' ', trim($user['name']));
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#1e3a8a">
  <meta name="description" content="Sistem Presensi Digital Terpadu Ekstrakurikuler Pramuka Penegak MAN 4 Banjar">
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <link rel="icon" href="<?= BASE_URL ?>/assets/icons/logo_pramuka_man4banjar.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : '1' ?>">
  <title><?= e(($title ?? 'Presensi') . ' — Pramuka MAN 4 Banjar') ?></title>
</head>
<body>
<header class="topbar">
  <a href="<?= $user ? BASE_URL . '/dashboard.php' : BASE_URL . '/index.php' ?>" class="brand" title="Beranda Pramuka MAN 4 Banjar">
    <div class="brand-mark">
      <img src="<?= BASE_URL ?>/assets/icons/logo_man4banjar.png" alt="Logo MAN 4 Banjar" style="height:38px; width:auto; max-height:38px; object-fit:contain; display:block;">
      <img src="<?= BASE_URL ?>/assets/icons/logo_pramuka_man4banjar.png" alt="Logo Pramuka MAN 4 Banjar" style="height:38px; width:38px; object-fit:contain; display:block; border-radius:50%;">
    </div>
    <div class="brand-text">
      <span class="brand-title">PRAMUKA MAN 4 BANJAR</span>
      <span class="brand-subtitle">GUDEP 01.095 - 01.096 &bull; PRESENSI RESMI</span>
    </div>
  </a>

  <div class="topbar-right">
    <div class="header-clock" id="topbarClockWidget">
      <span class="pulse-indicator"></span>
      <span id="topbarTimeText">--:--:-- WITA</span>
    </div>

    <?php if ($user): ?>
      <nav class="topnav">
        <a href="<?= BASE_URL ?>/dashboard.php" class="<?= str_ends_with($currentPath, 'dashboard.php') ? 'active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
          <span>Beranda</span>
        </a>

        <a href="<?= BASE_URL ?>/admin/tarikan_data.php" class="<?= str_contains($currentPath, 'tarikan_data.php') ? 'active' : '' ?>" title="Tarikan & Rekap Presensi Resmi">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          <span>Tarikan Data</span>
        </a>

        <?php if (in_array($user['role'], ['admin', 'pembina', 'pelatih'], true)): ?>
          <a href="<?= BASE_URL ?>/admin/index.php" class="<?= str_contains($currentPath, '/admin/') && !str_contains($currentPath, 'tarikan_data.php') ? 'active' : '' ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect></svg>
            <span>Panel Pengelola</span>
          </a>
        <?php endif; ?>

        <div class="user-profile-pill">
          <div class="user-avatar" title="<?= e($user['name']) ?>"><?= e($initials) ?></div>
          <div class="user-info-text">
            <span class="user-info-name"><?= e($user['name']) ?></span>
            <span class="user-info-role">
              <?php
              if ($user['role'] === 'admin') echo 'Administrator';
              elseif ($user['role'] === 'pembina') echo 'Pembina Pramuka';
              elseif ($user['role'] === 'pelatih') echo 'Pelatih Pramuka';
              elseif ($user['role'] === 'siswa') echo e('Siswa ' . ($user['kelas'] ?? ''));
              else echo e($user['kelas'] ?: 'Anggota');
              ?>
            </span>
          </div>
        </div>

        <a href="<?= BASE_URL ?>/logout.php" class="btn-nav-logout" title="Keluar dari akun">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
          <span>Keluar</span>
        </a>
      </nav>
    <?php else: ?>
      <div class="status-pill neutral">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>Portal Resmi Kehadiran Pramuka</span>
      </div>
    <?php endif; ?>
  </div>
</header>

<script>
// Topbar live clock updater (WITA = UTC+8)
(function updateHeaderClock() {
  function tick() {
    const el = document.getElementById('topbarTimeText');
    if (!el) return;
    const now = new Date();
    // UTC + 8 hours
    const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
    const wita = new Date(utc + (3600000 * 8));
    const h = String(wita.getHours()).padStart(2, '0');
    const m = String(wita.getMinutes()).padStart(2, '0');
    const s = String(wita.getSeconds()).padStart(2, '0');
    el.textContent = `${h}:${m}:${s} WITA`;
  }
  tick();
  setInterval(tick, 1000);
})();
</script>

<?php if ($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>" id="flashMessage">
    <div style="display: flex; align-items: center; gap: 8px;">
      <?php if ($flash['type'] === 'success'): ?>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <?php else: ?>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php endif; ?>
      <span><?= e($flash['message']) ?></span>
    </div>
    <button type="button" onclick="document.getElementById('flashMessage').remove()" style="background: none; border: none; cursor: pointer; color: inherit; font-size: 16px;">&times;</button>
  </div>
<?php endif; ?>
<main class="container">
