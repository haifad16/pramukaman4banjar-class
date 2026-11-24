<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$type = ($_GET['type'] ?? 'in') === 'out' ? 'out' : 'in';
$title = $type === 'in' ? 'Presensi Masuk' : 'Presensi Pulang';
$user = current_user();
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div>
    <p class="eyebrow">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      TERMINAL PRESENSI PRAMUKA MAN 4 BANJAR
    </p>
    <h1><?= $type === 'in' ? 'Presensi Masuk Latihan' : 'Presensi Selesai Latihan' ?></h1>
    <p class="muted">Nama: <b><?= e($user['name']) ?></b> &bull; Kelas/Peran: <b><?= e($user['kelas'] ?: ucfirst($user['role'])) ?></b></p>
  </div>
  <div>
    <span class="status-pill <?= $type === 'in' ? 'success' : 'warning' ?>">
      <?= $type === 'in' ? '🕒 Presensi Masuk Latihan' : '🏁 Presensi Selesai Latihan' ?>
    </span>
  </div>
</section>

<div class="card attendance-card">
  <!-- Stepper -->
  <div id="step-location" class="verification-step">
    <div class="step-icon">📍</div>
    <div style="flex: 1;">
      <strong>1. Validasi Lokasi Geofence Pangkalan MAN 4 Banjar</strong>
      <p id="locationStatus">Meminta izin dan membaca lokasi GPS perangkat...</p>
    </div>
  </div>

  <div id="step-face" class="verification-step locked">
    <div class="step-icon">👤</div>
    <div style="flex: 1;">
      <strong>2. Verifikasi Wajah Biometrik AI</strong>
      <p id="faceStatus">Menunggu lokasi pangkalan valid.</p>
    </div>
  </div>

  <div id="step-signature" class="verification-step locked">
    <div class="step-icon">✍️</div>
    <div style="flex: 1;">
      <strong>3. Tanda Tangan Perorangan (Digital Signature)</strong>
      <p id="signatureStepStatus">Goreskan tanda tangan langsung pada bidang tanda tangan di bawah.</p>
    </div>
  </div>

  <!-- Camera Viewport with Biometric Overlay HUD -->
  <div class="camera-box">
    <video id="video" autoplay muted playsinline></video>
    <canvas id="overlay"></canvas>
    <div class="camera-guide"></div>
  </div>

  <!-- Interactive Signature Pad -->
  <div class="signature-box" style="margin-top: 14px; background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 12px; padding: 14px; text-align: left;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
      <label style="font-weight: 700; font-size: 13px; color: #1e293b; display: flex; align-items: center; gap: 6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
        Goreskan Tanda Tangan Anda (Jari / Mouse):
      </label>
      <button type="button" id="clearSignatureBtn" class="btn btn-sm btn-light" style="padding: 3px 10px; font-size: 11px;">
        🔄 Hapus / Ulangi TTD
      </button>
    </div>
    
    <div style="position: relative; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden;">
      <canvas id="signatureCanvas" width="500" height="170" style="width: 100%; height: 160px; touch-action: none; cursor: crosshair; display: block; background: #ffffff;"></canvas>
      <div id="signatureWatermark" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
        ✍️ Tanda Tangan di Sini
      </div>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px; font-size: 11px; color: #64748b;">
      <span>Tanda tangan: <b><?= e($user['name']) ?></b> (<?= e($user['kelas'] ?: ucfirst($user['role'])) ?>)</span>
      <span id="signatureStatusText" style="color: #e11d48; font-weight: 600;">● Belum ditandatangani</span>
    </div>
  </div>

  <!-- Realtime Result Status Box -->
  <div id="result" class="result-box info" style="margin-top: 14px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <span>Persiapan inisialisasi sensor GPS &amp; modul biometrik...</span>
  </div>

  <!-- Action Controls -->
  <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px;">
    <button id="startCamera" class="btn btn-dark" style="flex: 1;" disabled>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
      <span>Buka Kamera</span>
    </button>
    <button id="submitAttendance" class="btn btn-primary" style="flex: 1.5;" disabled>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <span>Konfirmasi Presensi</span>
    </button>
    <a class="btn btn-light" href="<?= BASE_URL ?>/dashboard.php">
      <span>Kembali</span>
    </a>
  </div>

  <input type="hidden" id="attendanceType" value="<?= e($type) ?>">
  <input type="hidden" id="userId" value="<?= e($user['id']) ?>">
  <input type="hidden" id="csrf" value="<?= e(csrf_token()) ?>">
</div>

<script>
window.OFFICE_LAT = <?= json_encode(office_lat()) ?>;
window.OFFICE_LNG = <?= json_encode(office_lng()) ?>;
window.OFFICE_RADIUS = <?= json_encode(office_radius()) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.js"></script>
<script src="<?= BASE_URL ?>/assets/js/attendance.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
