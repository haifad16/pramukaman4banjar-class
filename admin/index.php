<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$title = 'Panel Pengelola Pramuka MAN 4 Banjar';

// Statistik Data Pramuka
$totalSiswa = 0;
$totalPutra = 0;
$totalPutri = 0;
$totalPelatih = 2;
$totalPertemuan = 4;
$todayAttendance = 0;

try {
    $totalSiswa = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn();
    $totalPutra = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'siswa' AND gender = 'L'")->fetchColumn();
    $totalPutri = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'siswa' AND gender = 'P'")->fetchColumn();
    $todayDate = timezone_now()->format('Y-m-d');
    $stmtToday = db()->prepare("SELECT COUNT(*) FROM attendances WHERE work_date = ?");
    $stmtToday->execute([$todayDate]);
    $todayAttendance = (int)$stmtToday->fetchColumn();
} catch (Throwable $e) {
    $totalSiswa = 113;
    $totalPutra = 43;
    $totalPutri = 70;
}

require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <div>
    <p class="eyebrow">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      PANEL PENGELOLA &bull; PRAMUKA MAN 4 BANJAR
    </p>
    <h1>Dashboard Pengelola Presensi Pramuka</h1>
    <p class="muted">Monitoring kehadiran ekstrakurikuler pramuka, tarikan data resmi per kelompok kelas, presensi pelatih & pembina.</p>
  </div>
  <div>
    <span class="status-pill success">
      <span class="pulse-indicator" style="background: var(--success); width: 8px; height: 8px; border-radius: 50%; display: inline-block;"></span>
      Gudep 01.095 - 01.096 Aktif
    </span>
  </div>
</section>

<!-- KPI Metrics Pramuka -->
<div class="stats">
  <div class="stat-card">
    <div class="stat-card-header">
      <small>Total Anggota Pramuka</small>
      <div class="stat-icon" style="background: #eff6ff; color: #1d4ed8;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
    </div>
    <strong><?= e($totalSiswa) ?> Siswa</strong>
    <span class="muted" style="font-size: 12px; margin-top: 6px;">
      <?= e($totalPutra) ?> Penegak Putra &bull; <?= e($totalPutri) ?> Penegak Putri
    </span>
  </div>

  <div class="stat-card">
    <div class="stat-card-header">
      <small>Pelatih & Pembina</small>
      <div class="stat-icon" style="background: #fef3c7; color: #b45309;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
      </div>
    </div>
    <strong>2 Pelatih &bull; 3 Pembina</strong>
    <span class="muted" style="font-size: 12px; margin-top: 6px;">
      M. Haifad Ilham &amp; Aulia Ika Noorjannah
    </span>
  </div>

  <div class="stat-card">
    <div class="stat-card-header">
      <small>Jadwal Pertemuan</small>
      <div class="stat-icon" style="background: #ecfdf5; color: var(--success);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
    </div>
    <strong>4 Pertemuan</strong>
    <span class="muted" style="font-size: 12px; margin-top: 6px;">
      November 2026 (Kamis Sore)
    </span>
  </div>
</div>

<!-- Main Pramuka Admin Modules -->
<div class="admin-grid">
  <a class="menu-card" href="<?= BASE_URL ?>/admin/tarikan_data.php" style="border-left: 4px solid #1d4ed8;">
    <div class="menu-card-icon" style="background: #eff6ff; color: #1d4ed8;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
    </div>
    <strong>Tarikan Data Presensi Resmi</strong>
    <span>Lihat format cetak resmi presensi pelatih & presensi siswa (Kelas X, XI, XII Putra & Putri), ekspor ke file Excel (.xls) dan cetak langsung.</span>
  </a>

  <a class="menu-card" href="<?= BASE_URL ?>/admin/input_presensi.php" style="border-left: 4px solid #059669;">
    <div class="menu-card-icon" style="background: #ecfdf5; color: #059669;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    </div>
    <strong>Input Presensi Pertemuan</strong>
    <span>Pencatatan cepat kehadiran siswa (Hadir, Izin, Sakit, Alpha) per pertemuan mingguan 1, 2, 3, 4 dengan tombol 1-klik tanda hadir.</span>
  </a>

  <a class="menu-card" href="<?= BASE_URL ?>/admin/employees.php" style="border-left: 4px solid #d97706;">
    <div class="menu-card-icon" style="background: #fef3c7; color: #b45309;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <strong>Data Anggota Pramuka & Siswa</strong>
    <span>Daftar 113 siswa penegak (Kelas X-A s/d XII-G), akun pelatih, pembina, tambah anggota baru, dan manajemen biometrik wajah.</span>
  </a>

  <a class="menu-card" href="<?= BASE_URL ?>/admin/settings.php" style="border-left: 4px solid #7c3aed;">
    <div class="menu-card-icon" style="background: #f5f3ff; color: #7c3aed;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
    </div>
    <strong>Pengaturan Gudep & Geofence</strong>
    <span>Konfigurasi titik koordinat pangkalan MAN 4 Banjar, radius toleransi GPS, jam latihan pramuka, serta nama Pembina & NIP.</span>
  </a>

  <a class="menu-card" href="<?= BASE_URL ?>/admin/reports.php" style="border-left: 4px solid #be185d;">
    <div class="menu-card-icon" style="background: #fdf2f8; color: #be185d;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
    </div>
    <strong>Laporan & Evaluasi Presensi</strong>
    <span>Akses laporan harian, bulanan, log audit GPS perangkat, dan rekapitulasi data kehadiran dengan fitur ekspor CSV.</span>
  </a>
</div>

<!-- Parameter Operasional Pramuka -->
<section class="card" style="margin-top: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
    <h2 style="font-size: 16px; margin: 0; display: flex; align-items: center; gap: 8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Informasi Gugus Depan Pangkalan MAN 4 Banjar
    </h2>
    <span class="badge hadir">Periode Aktif: November 2026</span>
  </div>

  <div class="detail-grid">
    <div>
      <small>Pembina Pramuka Putra</small>
      <p><b><?= e(setting('pembina_putra', 'Fadlan Hidayat, S.Pd.')) ?></b><br><span style="font-size: 12px; color: var(--muted);">NIP <?= e(setting('nip_pembina_putra', '198710022019031003')) ?></span></p>
    </div>
    <div>
      <small>Pembina Pramuka Putri</small>
      <p><b><?= e(setting('pembina_putri', 'Elda Riana Yaslitha, S.Pd.')) ?></b><br><span style="font-size: 12px; color: var(--muted);">NIP <?= e(setting('nip_pembina_putri', '199404082025212011')) ?></span></p>
    </div>
    <div>
      <small>Wakamad Kesiswaan</small>
      <p><b><?= e(setting('wakamad_kesiswaan', 'Jiabus Sarury, S.Pd., M.A')) ?></b><br><span style="font-size: 12px; color: var(--muted);">NIP <?= e(setting('nip_wakamad_kesiswaan', '19930822201903100')) ?></span></p>
    </div>
    <div>
      <small>Jadwal Latihan Pramuka</small>
      <p><b>Setiap Kamis Sore</b><br><span style="font-size: 12px; color: var(--muted);"><?= e(attendance_start()) ?> – <?= e(attendance_end()) ?> WITA</span></p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
