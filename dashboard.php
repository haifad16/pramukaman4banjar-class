<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$title = 'Beranda — Presensi Pramuka MAN 4 Banjar';
$user = current_user();
$att = today_attendance((int)$user['id']);

// Indonesian Day and Date Formatter
$now = timezone_now();
$dayNames = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$monthNames = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];

$dayName = $dayNames[$now->format('l')] ?? $now->format('l');
$dateFormatted = $dayName . ', ' . $now->format('d') . ' ' . ($monthNames[$now->format('m')] ?? $now->format('F')) . ' ' . $now->format('Y');

$pertemuan = get_pertemuan_pramuka();

require __DIR__ . '/includes/header.php';
?>

<!-- Executive Banner -->
<section class="office-clock-widget">
  <div class="clock-info-side">
    <p class="eyebrow" style="color: #93c5fd; margin-bottom: 4px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
      GUDEP PANGKALAN MAN 4 BANJAR &bull; EKSTRAKURIKULER PRAMUKA
    </p>
    <h2>Selamat Datang, <?= e($user['name']) ?></h2>
    <p>
      <span>
        <?php 
        if ($user['role'] === 'siswa') {
            echo 'Anggota Pramuka: <b>Kelas ' . e($user['kelas'] ?? 'X') . ' (' . ($user['gender'] === 'L' ? 'Penegak Putra' : 'Penegak Putri') . ')</b>';
        } elseif ($user['role'] === 'pelatih') {
            echo 'Penugasan: <b>Pelatih Ekstrakurikuler Pramuka</b>';
        } elseif ($user['role'] === 'pembina') {
            echo 'Penugasan: <b>Pembina Pramuka MAN 4 Banjar</b>';
        } else {
            echo 'Peran: <b>Administrator Gugus Depan</b>';
        }
        ?>
      </span>
      &bull;
      <span>Jam Latihan: <b><?= e(attendance_start()) ?> – <?= e(attendance_end()) ?> WITA</b></span>
    </p>
  </div>
  <div class="clock-display-side">
    <div class="digital-time" id="dashboardLiveTime">--:--:--</div>
    <div class="digital-zone"><?= e($dateFormatted) ?> &bull; WITA</div>
  </div>
</section>

<?php if (empty($user['face_descriptor'])): ?>
  <div class="card" style="margin-bottom: 20px; background: #fffbeb; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 14px;">
      <div style="font-size: 30px; flex-shrink: 0;">📸</div>
      <div>
        <strong style="color: #92400e; font-size: 15px; display: block;">Biometrik Wajah Anda Belum Didaftarkan</strong>
        <p style="margin: 3px 0 0; color: #b45309; font-size: 13px;">Agar dapat melakukan presensi kehadiran biometrik di pangkalan MAN 4 Banjar, daftarkan wajah Anda terlebih dahulu.</p>
      </div>
    </div>
    <button type="button" class="btn btn-primary enroll" data-id="<?= e($user['id']) ?>" data-name="<?= e($user['name']) ?>" style="white-space: nowrap;">
      <span>📸 Daftarkan Wajah Sekarang</span>
    </button>
  </div>
<?php endif; ?>

<div class="grid-2">
  <!-- Presensi Hari Ini (Mandiri) -->
  <section class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <h2 style="margin: 0; font-size: 18px;">Presensi Kehadiran Mandiri</h2>
      <?php if ($att && $att['check_out']): ?>
        <span class="status-pill success">● Presensi Lengkap</span>
      <?php elseif ($att): ?>
        <span class="status-pill warning">● Sedang Mengikuti Latihan</span>
      <?php else: ?>
        <span class="status-pill neutral">● Belum Presensi Hari Ini</span>
      <?php endif; ?>
    </div>

    <div class="attendance-times">
      <div>
        <small>Presensi Masuk Latihan</small>
        <strong><?= e($att['check_in'] ?? '--:--') ?></strong>
        <?php if ($att && $att['check_in']): ?>
          <div style="margin-top: 6px;">
            <span class="badge <?= e($att['status']) ?>"><?= e($att['status']) ?></span>
            <?php if ($att['in_distance'] !== null): ?>
              <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 3px;">±<?= round($att['in_distance']) ?> m dari pangkalan</span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <span style="font-size: 11.5px; color: var(--muted); display: block; margin-top: 6px;">Belum tercatat</span>
        <?php endif; ?>
      </div>

      <div>
        <small>Presensi Selesai Latihan</small>
        <strong><?= e($att['check_out'] ?? '--:--') ?></strong>
        <?php if ($att && $att['check_out']): ?>
          <div style="margin-top: 6px;">
            <span class="badge hadir">Selesai</span>
            <?php if ($att['out_distance'] !== null): ?>
              <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 3px;">±<?= round($att['out_distance']) ?> m dari pangkalan</span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <span style="font-size: 11.5px; color: var(--muted); display: block; margin-top: 6px;">Belum tercatat</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="action-stack" style="margin-top: 20px;">
      <?php if (!$att): ?>
        <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/attendance.php?type=in">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
          <span>Lakukan Presensi Hadir (Kamera & GPS)</span>
        </a>
      <?php elseif (!$att['check_out']): ?>
        <a class="btn btn-dark btn-lg" href="<?= BASE_URL ?>/attendance.php?type=out">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
          <span>Lakukan Presensi Pulang / Selesai</span>
        </a>
      <?php else: ?>
        <div class="success-box" style="padding: 12px; background: #ecfdf5; border-radius: 8px; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <div>
            <strong style="color: #065f46;">Presensi Latihan Hari Ini Lengkap</strong>
            <p style="margin: 2px 0 0; font-size: 12px; color: #047857;">Terima kasih atas partisipasi aktif dalam latihan Pramuka MAN 4 Banjar.</p>
          </div>
        </div>
      <?php endif; ?>

      <div style="display: flex; gap: 8px; margin-top: 10px;">
        <a class="btn btn-light" href="<?= BASE_URL ?>/admin/tarikan_data.php" style="flex: 1; justify-content: center;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
          <span>Lihat Tarikan Data Resmi</span>
        </a>
        <?php if (in_array($user['role'], ['admin', 'pembina', 'pelatih'], true)): ?>
          <a class="btn btn-light" href="<?= BASE_URL ?>/admin/input_presensi.php" style="flex: 1; justify-content: center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span>Input Presensi Cepat</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Jadwal 4 Pertemuan Bulan November 2026 -->
  <section class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <h2 style="margin: 0; font-size: 18px;">Jadwal 4 Pertemuan (November 2026)</h2>
      <span class="badge hadir">Kamis Sore</span>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width: 40px; text-align: center;">Ptm</th>
            <th>Hari, Tanggal</th>
            <th>Materi Pokok Latihan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pertemuan as $ptm): ?>
            <tr>
              <td style="text-align: center; font-weight: bold;"><?= e($ptm['pertemuan_ke'] ?? $ptm['ke']) ?></td>
              <td style="font-weight: 600; white-space: nowrap;">
                <?= e($ptm['hari_tanggal'] ?? ($ptm['hari'] . ', ' . $ptm['tanggal'])) ?>
              </td>
              <td style="font-size: 12.5px; color: var(--text-body);">
                <?= e($ptm['materi_default'] ?? $ptm['materi']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top: 14px; padding: 10px 14px; background: var(--bg-alt, #f8fafc); border-radius: 8px; font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; align-items: center;">
      <span>Pelatih: <b>M. Haifad Ilham</b> &amp; <b>Aulia Ika Noorjannah</b></span>
      <span>Pangkalan MAN 4 Banjar</span>
    </div>
  </section>
</div>

<script>
// Dashboard live clock updater
(function updateLiveTime() {
  function tick() {
    const el = document.getElementById('dashboardLiveTime');
    if (!el) return;
    const now = new Date();
    const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
    const wita = new Date(utc + (3600000 * 8));
    const h = String(wita.getHours()).padStart(2, '0');
    const m = String(wita.getMinutes()).padStart(2, '0');
    const s = String(wita.getSeconds()).padStart(2, '0');
    el.textContent = `${h}:${m}:${s}`;
  }
  tick();
  setInterval(tick, 1000);
})();
</script>

<input type="hidden" name="csrf" id="csrf" value="<?= e(csrf_token()) ?>">

<!-- Modal Perekaman Wajah Biometrik AI -->
<div class="modal" id="faceModal">
  <div class="modal-card">
    <button type="button" class="modal-close" onclick="closeEnroll()">&times;</button>
    <h3 style="margin: 0; font-size: 17px;">📸 Perekaman Wajah Biometrik AI</h3>
    <p class="muted" style="margin: 4px 0 10px; font-size: 13px;">Anggota: <b id="enrollName" style="color: var(--text-main);">-</b></p>
    <video id="enrollVideo" autoplay muted playsinline></video>
    <div id="enrollStatus" class="result-box info" style="font-size: 12.5px; margin-bottom: 12px;">Memuat modul AI biometrik wajah...</div>
    <div style="display: flex; gap: 8px;">
      <button type="button" class="btn btn-light" onclick="closeEnroll()" style="flex: 1;">Batal</button>
      <button type="button" id="captureFace" class="btn btn-primary" style="flex: 2;">📸 Ambil &amp; Simpan Wajah</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.js"></script>
<script src="<?= BASE_URL ?>/assets/js/enroll.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
