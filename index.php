<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect('/dashboard.php');
$title = 'Masuk — Portal Presensi Pramuka MAN 4 Banjar';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <section class="card auth-card">
    <div class="auth-header">
      <div class="auth-logo" style="display: flex; justify-content: center; align-items: center; gap: 14px; margin-bottom: 12px;">
        <img src="<?= BASE_URL ?>/assets/icons/logo_man4banjar.png" alt="Logo MAN 4 Banjar" width="40" height="54" style="height:54px; width:auto; max-height:54px; object-fit:contain; display:block;">
        <div style="width: 1px; height: 38px; background: var(--line);"></div>
        <img src="<?= BASE_URL ?>/assets/icons/logo_pramuka_man4banjar.png" alt="Logo Pramuka MAN 4 Banjar" width="52" height="52" style="height:52px; width:52px; object-fit:contain; display:block; border-radius:50%;">
      </div>
      <p class="eyebrow" style="justify-content: center; margin-bottom: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        MAN 4 BANJAR &bull; GUDEP 01.095 - 01.096
      </p>
      <h1>Portal Presensi Pramuka</h1>
      <p class="muted">Ekstrakurikuler Pramuka Penegak MAN 4 Banjar<br>Presensi Geofencing, Biometrik & Tarikan Data Resmi</p>
    </div>

    <form method="post" action="<?= BASE_URL ?>/login.php" class="form-grid" id="loginForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <label>
        <span>Nama Lengkap Anda</span>
        <div class="input-with-icon">
          <span class="input-icon-prefix">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </span>
          <input required id="loginUsername" name="username" list="namesList" autocomplete="off" placeholder="Ketik atau pilih nama lengkap Anda (atau admin)..." autofocus>
          <datalist id="namesList">
            <?php 
            try {
              $allNames = db()->query("SELECT name FROM users WHERE status = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
              foreach ($allNames as $nm) {
                echo '<option value="' . e($nm) . '">';
              }
            } catch (Throwable $e) {}
            ?>
          </datalist>
        </div>
      </label>

      <label>
        <span>Password Akun</span>
        <div class="input-with-icon">
          <span class="input-icon-prefix">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input required type="password" id="loginPassword" name="password" autocomplete="current-password" placeholder="Masukkan password Anda">
          <button type="button" id="togglePasswordBtn" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--muted); padding: 4px; display: flex; align-items: center; justify-content: center;" title="Tampilkan/Sembunyikan password">
            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </label>

      <?php
      $cNum1 = rand(2, 9);
      $cNum2 = rand(1, 9);
      $_SESSION['login_captcha_ans'] = $cNum1 + $cNum2;
      ?>
      <label>
        <span>Kode Keamanan (Captcha: Berapa <?= $cNum1 ?> + <?= $cNum2 ?>?)</span>
        <div class="input-with-icon">
          <span class="input-icon-prefix">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </span>
          <input required type="number" id="loginCaptcha" name="captcha" placeholder="Tulis hasil angka (contoh: <?= $cNum1 + $cNum2 ?>)" autocomplete="off">
        </div>
      </label>

      <button class="btn btn-primary btn-lg" type="submit" style="margin-top: 6px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
        <span>Masuk ke Portal Presensi</span>
      </button>
    </form>

    <div style="margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--line); font-size: 13px; text-align: center;">
      <a href="<?= BASE_URL ?>/register.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">
        ✨ Anggota Baru? Daftarkan Wajah &amp; Akun Pramuka &rarr;
      </a>
    </div>
  </section>
</div>

<script>
const toggleBtn = document.getElementById('togglePasswordBtn');
const passInput = document.getElementById('loginPassword');
if (toggleBtn && passInput) {
  toggleBtn.addEventListener('click', () => {
    const isPass = passInput.type === 'password';
    passInput.type = isPass ? 'text' : 'password';
    toggleBtn.innerHTML = isPass 
      ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9.88 9.88 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>'
      : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  });
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
