<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) redirect('/dashboard.php');

$title = 'Pendaftaran Anggota Pramuka MAN 4 Banjar';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_registration_csrf();

        $name = trim($_POST['name'] ?? '');
        $kelas = trim($_POST['kelas'] ?? '');
        $tingkat = trim($_POST['tingkat'] ?? 'X');
        $gender = trim($_POST['gender'] ?? 'L');
        $email = trim($_POST['email'] ?? '');
        $phoneInput = trim($_POST['phone'] ?? '');
        $phone = normalize_whatsapp_phone($phoneInput);
        $username = $phone ?: trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $descriptor = parse_descriptor($_POST['face_descriptor'] ?? '');
        $faceAgree = ($_POST['face_agreement'] ?? '') === '1';

        if ($name === '' || $password === '') {
            throw new RuntimeException('Nama lengkap dan password wajib diisi.');
        }
        if (!$username) {
            throw new RuntimeException('Nomor WhatsApp atau Username wajib diisi.');
        }
        if (strlen($password) < 6) {
            throw new RuntimeException('Password minimal 6 karakter.');
        }
        if ($password !== $passwordConfirm) {
            throw new RuntimeException('Konfirmasi password tidak sama.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Format email tidak valid.');
        }
        if (!$descriptor) {
            throw new RuntimeException('Wajah belum berhasil direkam. Pastikan wajah terbaca lalu tekan "Ambil & Daftarkan Wajah".');
        }
        if (!$faceAgree) {
            throw new RuntimeException('Anda harus menyetujui proses pendaftaran data biometrik wajah.');
        }

        // Cek username
        $chk = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $chk->execute([$username]);
        if ($chk->fetch()) {
            throw new RuntimeException('Username / Nomor WhatsApp tersebut sudah terdaftar.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('
                INSERT INTO users (username, name, kelas, tingkat, gender, email, phone, password_hash, role, status, face_descriptor, face_registered_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, "siswa", 1, ?, ?)
            ');
            $stmt->execute([
                $username,
                $name,
                $kelas ?: null,
                $tingkat,
                $gender,
                $email ?: null,
                $phone ?: null,
                password_hash($password, PASSWORD_DEFAULT),
                json_encode($descriptor, JSON_UNESCAPED_SLASHES),
                timezone_now()->format('Y-m-d H:i:s'),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        flash('success', 'Akun dan template biometrik wajah anggota Pramuka berhasil didaftarkan. Silakan masuk ke akun Anda.');
        redirect('/index.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap register-page">
  <section class="card auth-card register-card">
    <div class="auth-header">
      <div class="auth-logo" style="display: flex; justify-content: center; align-items: center; gap: 12px; margin-bottom: 8px;">
        <img src="<?= BASE_URL ?>/assets/icons/logo_man4banjar.png" alt="Logo MAN 4 Banjar" width="36" height="48" style="height:48px; width:auto; max-height:48px; object-fit:contain; display:block;">
        <div style="width: 1px; height: 32px; background: var(--line);"></div>
        <img src="<?= BASE_URL ?>/assets/icons/logo_pramuka_man4banjar.png" alt="Logo Pramuka MAN 4 Banjar" width="46" height="46" style="height:46px; width:46px; object-fit:contain; display:block; border-radius:50%;">
      </div>
      <p class="eyebrow" style="justify-content: center; margin-bottom: 4px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        MAN 4 BANJAR &bull; GUDEP 01.095 - 01.096
      </p>
      <h1>Pendaftaran Anggota Pramuka</h1>
      <p class="muted">Ekstrakurikuler Pramuka Penegak MAN 4 Banjar<br>Perekaman Biometrik Wajah &amp; Verifikasi Kehadiran</p>
    </div>

    <?php if ($error): ?>
      <div class="alert error" style="text-align: left;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Ketentuan Foto Biometrik -->
    <div class="face-guidelines">
      <strong>📋 Standar Kualitas Perekaman Biometrik Wajah</strong>
      <div class="guideline-grid">
        <div><span>✅</span> Lepas masker & penutup wajah</div>
        <div><span>✅</span> Tanpa kacamata hitam</div>
        <div><span>✅</span> Wajah tegak menghadap kamera</div>
        <div><span>✅</span> Pencahayaan cukup & merata</div>
        <div><span>✅</span> 1 orang di dalam kamera</div>
        <div><span>✅</span> Jarak optimal 30–60 cm</div>
      </div>
      <p class="hint" style="margin-top: 10px;">
        🔒 <i>Sistem mengonversi kontur wajah menjadi template numerik terenkripsi (128 descriptor) dan tidak menyimpan foto secara mentah.</i>
      </p>
    </div>

    <form method="post" class="form-grid" id="registerForm" autocomplete="off">
      <input type="hidden" name="registration_csrf" value="<?= e(registration_csrf_token()) ?>">
      <input type="hidden" name="face_descriptor" id="faceDescriptor">
      <input type="hidden" name="face_agreement" id="faceAgreement" value="0">

      <div style="border-top: 1px solid var(--line); padding-top: 16px;">
        <strong style="display: block; font-size: 14.5px; margin-bottom: 12px; color: var(--primary);">
          1. Data Akun & Kelas Anggota Pramuka
        </strong>
        <div class="form-grid">
          <label>
            Nama Lengkap Siswa / Anggota
            <input id="nameInput" required type="text" name="name" maxlength="150" autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="Nama lengkap siswa">
          </label>

          <div class="cols-3">
            <label>
              Kelas
              <input name="kelas" maxlength="50" value="<?= e($_POST['kelas'] ?? '') ?>" placeholder="Contoh: X – C">
            </label>

            <label>
              Tingkatan
              <select name="tingkat">
                <option value="X">Kelas X</option>
                <option value="XI">Kelas XI</option>
                <option value="XII">Kelas XII</option>
              </select>
            </label>

            <label>
              Jenis Kelamin
              <select name="gender">
                <option value="L">Putra (L)</option>
                <option value="P">Putri (P)</option>
              </select>
            </label>
          </div>

          <div class="cols-2">
            <label>
              Nomor WhatsApp / ID Login
              <input id="phoneInput" required type="text" name="phone" maxlength="30" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="Contoh: 081234567890 atau NIS">
              <span class="hint">ID ini menjadi username login Anda ke sistem presensi Pramuka.</span>
            </label>

            <label>
              Email (Opsional)
              <input type="email" name="email" maxlength="150" value="<?= e($_POST['email'] ?? '') ?>" placeholder="siswa@man4banjar.sch.id">
            </label>
          </div>

          <div class="cols-2">
            <label>
              Password
              <input required type="password" name="password" minlength="6" autocomplete="new-password" placeholder="Minimal 6 karakter">
            </label>

            <label>
              Konfirmasi Password
              <input required type="password" name="password_confirm" minlength="6" autocomplete="new-password" placeholder="Ulangi password di atas">
            </label>
          </div>
        </div>
      </div>

      <!-- Kotak Perekaman Wajah -->
      <div style="border-top: 1px solid var(--line); padding-top: 16px;">
        <strong style="display: block; font-size: 14.5px; margin-bottom: 12px; color: var(--primary);">
          2. Perekaman Template Biometrik Wajah
        </strong>

        <div class="face-register-box">
          <div class="face-register-header">
            <div>
              <strong style="font-size: 14px;">Kamera Perekaman Wajah</strong>
              <p id="faceRegisterHint" class="hint" style="margin: 2px 0 0;">Kamera belum diaktifkan.</p>
            </div>
            <span id="faceReadyBadge" class="status-pill neutral">Belum Terdaftar</span>
          </div>

          <div class="register-camera-box">
            <video id="registerVideo" autoplay muted playsinline></video>
            <div class="camera-guide register-guide"></div>
          </div>

          <div id="registerFaceStatus" class="result-box info">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>Tekan tombol "Buka Kamera" di bawah untuk memulai.</span>
          </div>

          <div class="face-register-actions">
            <button type="button" class="btn btn-dark" id="openRegisterCamera">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              <span>Buka Kamera</span>
            </button>
            <button type="button" class="btn btn-primary" id="captureRegisterFace" disabled>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <span>Ambil & Daftarkan Wajah</span>
            </button>
          </div>

          <label class="agreement-row">
            <input type="checkbox" id="faceAgreementCheck">
            <span>Saya menyatakan bahwa wajah pada kamera adalah wajah saya sendiri dan saya menyetujui ekstraksi template biometrik untuk keperluan presensi resmi Pramuka MAN 4 Banjar.</span>
          </label>
        </div>
      </div>

      <button class="btn btn-primary btn-lg" type="submit" id="createAccountBtn" disabled style="margin-top: 8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
        <span>Daftarkan Anggota Pramuka & Selesai</span>
      </button>
    </form>

    <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--line); font-size: 13px;">
      <span class="muted">Sudah memiliki akun anggota pramuka? </span>
      <a href="<?= BASE_URL ?>/index.php" style="font-weight: 700;">Kembali ke Halaman Login</a>
    </div>
  </section>
</div>

<script src="<?= BASE_URL ?>/assets/js/register_face.js" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
