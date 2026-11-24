<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$title = 'Pengaturan Gugus Depan & Geofence';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = [
        'office_latitude' => trim($_POST['lat'] ?? '-3.3134'),
        'office_longitude' => trim($_POST['lng'] ?? '115.0886'),
        'office_radius_meters' => trim($_POST['radius'] ?? '150'),
        'attendance_start' => trim($_POST['start'] ?? '15:30'),
        'attendance_end' => trim($_POST['end'] ?? '17:30'),
        'school_name' => trim($_POST['school_name'] ?? 'MAN 4 BANJAR'),
        'pembina_putra' => trim($_POST['pembina_putra'] ?? 'Fadlan Hidayat, S.Pd.'),
        'nip_pembina_putra' => trim($_POST['nip_pembina_putra'] ?? '198710022019031003'),
        'pembina_putri' => trim($_POST['pembina_putri'] ?? 'Elda Riana Yaslitha, S.Pd.'),
        'nip_pembina_putri' => trim($_POST['nip_pembina_putri'] ?? '199404082025212011'),
        'wakamad_kesiswaan' => trim($_POST['wakamad_kesiswaan'] ?? 'Jiabus Sarury, S.Pd., M.A'),
        'nip_wakamad_kesiswaan' => trim($_POST['nip_wakamad_kesiswaan'] ?? '19930822201903100'),
        'pelatih_putra' => trim($_POST['pelatih_putra'] ?? 'M. Haifad Ilham'),
        'pelatih_putri' => trim($_POST['pelatih_putri'] ?? 'Aulia Ika Noorjannah'),
    ];
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($data as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    flash('success', 'Konfigurasi Gugus Depan Pramuka MAN 4 Banjar berhasil diperbarui.');
    redirect('/admin/settings.php');
}

require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <div>
    <p class="eyebrow">
      <a href="<?= BASE_URL ?>/admin/index.php" style="color: inherit;">Panel Pengelola</a> &rsaquo; Pengaturan Gugus Depan
    </p>
    <h1>Pengaturan Pangkalan &amp; Pejabat Pramuka</h1>
    <p class="muted">Konfigurasi nama Pembina, Pelatih, Wakamad Kesiswaan, titik GPS pangkalan MAN 4 Banjar, dan jadwal latihan.</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-light btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      <span>Kembali ke Admin</span>
    </a>
  </div>
</section>

<form method="post" class="form-grid" id="settingsForm">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="grid-2">
    <!-- Panel 1: Pejabat & Tanda Tangan Resmi -->
    <section class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="font-size: 17px; margin: 0;">Pejabat Penandatangan Presensi</h2>
        <span class="badge hadir">Kop & TTD Resmi</span>
      </div>

      <div class="form-grid">
        <label>
          <span>Nama Madrasah</span>
          <input required name="school_name" value="<?= e(setting('school_name', 'MAN 4 BANJAR')) ?>">
        </label>

        <div style="padding-top: 8px; border-top: 1px solid var(--line);">
          <strong style="display: block; font-size: 13.5px; margin-bottom: 8px; color: var(--primary);">
            Pembina Pramuka Putera
          </strong>
          <div class="cols-2">
            <label>
              <span>Nama Lengkap & Gelar</span>
              <input required name="pembina_putra" value="<?= e(setting('pembina_putra', 'Fadlan Hidayat, S.Pd.')) ?>">
            </label>
            <label>
              <span>NIP Pembina Putera</span>
              <input required name="nip_pembina_putra" value="<?= e(setting('nip_pembina_putra', '198710022019031003')) ?>">
            </label>
          </div>
        </div>

        <div style="padding-top: 8px; border-top: 1px solid var(--line);">
          <strong style="display: block; font-size: 13.5px; margin-bottom: 8px; color: #db2777;">
            Pembina Pramuka Puteri
          </strong>
          <div class="cols-2">
            <label>
              <span>Nama Lengkap & Gelar</span>
              <input required name="pembina_putri" value="<?= e(setting('pembina_putri', 'Elda Riana Yaslitha, S.Pd.')) ?>">
            </label>
            <label>
              <span>NIP Pembina Puteri</span>
              <input required name="nip_pembina_putri" value="<?= e(setting('nip_pembina_putri', '199404082025212011')) ?>">
            </label>
          </div>
        </div>

        <div style="padding-top: 8px; border-top: 1px solid var(--line);">
          <strong style="display: block; font-size: 13.5px; margin-bottom: 8px; color: #059669;">
            Wakamad Kesiswaan
          </strong>
          <div class="cols-2">
            <label>
              <span>Nama Lengkap & Gelar</span>
              <input required name="wakamad_kesiswaan" value="<?= e(setting('wakamad_kesiswaan', 'Jiabus Sarury, S.Pd., M.A')) ?>">
            </label>
            <label>
              <span>NIP Wakamad Kesiswaan</span>
              <input required name="nip_wakamad_kesiswaan" value="<?= e(setting('nip_wakamad_kesiswaan', '19930822201903100')) ?>">
            </label>
          </div>
        </div>

        <div style="padding-top: 8px; border-top: 1px solid var(--line);">
          <strong style="display: block; font-size: 13.5px; margin-bottom: 8px; color: var(--text-main);">
            Pelatih Ekstrakurikuler
          </strong>
          <div class="cols-2">
            <label>
              <span>Pelatih Putra</span>
              <input required name="pelatih_putra" value="<?= e(setting('pelatih_putra', 'M. Haifad Ilham')) ?>">
            </label>
            <label>
              <span>Pelatih Putri</span>
              <input required name="pelatih_putri" value="<?= e(setting('pelatih_putri', 'Aulia Ika Noorjannah')) ?>">
            </label>
          </div>
        </div>
      </div>
    </section>

    <!-- Panel 2: Geofence & Jam Latihan -->
    <section class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="font-size: 17px; margin: 0;">Lokasi Pangkalan & Jadwal</h2>
        <span class="badge hadir">Geofencing GPS</span>
      </div>

      <div class="form-grid">
        <div>
          <strong style="display: block; font-size: 13.5px; margin-bottom: 8px; color: var(--primary);">
            📍 Titik Koordinat Pangkalan MAN 4 Banjar
          </strong>
          <div class="cols-2">
            <label>
              <span>Latitude Pangkalan</span>
              <input required id="officeLat" name="lat" step="any" value="<?= e(office_lat()) ?>">
            </label>
            <label>
              <span>Longitude Pangkalan</span>
              <input required id="officeLng" name="lng" step="any" value="<?= e(office_lng()) ?>">
            </label>
          </div>

          <label style="margin-top: 10px;">
            <span>Radius Toleransi GPS (meter)</span>
            <input required type="number" min="20" max="1000" name="radius" value="<?= e(office_radius()) ?>">
            <small class="muted">Toleransi area lapangan/madrasah untuk presensi mandiri siswa via GPS.</small>
          </label>

          <button type="button" class="btn btn-sm btn-light" onclick="detectCurrentLocation()" style="margin-top: 8px; width: 100%;">
            🎯 Gunakan Lokasi GPS Saya Saat Ini
          </button>
        </div>

        <div style="padding-top: 14px; border-top: 1px solid var(--line);">
          <strong style="display: block; font-size: 13.5px; margin-bottom: 8px; color: var(--primary);">
            ⏰ Waktu Latihan Pramuka
          </strong>
          <div class="cols-2">
            <label>
              <span>Jam Mulai Latihan (WITA)</span>
              <input required type="time" name="start" value="<?= e(attendance_start()) ?>">
            </label>
            <label>
              <span>Jam Selesai Latihan (WITA)</span>
              <input required type="time" name="end" value="<?= e(attendance_end()) ?>">
            </label>
          </div>
        </div>

        <div style="margin-top: 20px;">
          <button class="btn btn-primary btn-lg" type="submit" style="width: 100%;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <span>Simpan Semua Pengaturan</span>
          </button>
        </div>
      </div>
    </section>
  </div>
</form>

<script>
function detectCurrentLocation() {
  if (!navigator.geolocation) {
    alert('Browser tidak mendukung geolokasi.');
    return;
  }
  navigator.geolocation.getCurrentPosition(
    pos => {
      document.getElementById('officeLat').value = pos.coords.latitude.toFixed(7);
      document.getElementById('officeLng').value = pos.coords.longitude.toFixed(7);
      alert('Koordinat GPS berhasil diperbarui: ' + pos.coords.latitude.toFixed(6) + ', ' + pos.coords.longitude.toFixed(6));
    },
    err => {
      alert('Gagal mendeteksi lokasi GPS: ' + err.message);
    },
    { enableHighAccuracy: true }
  );
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
