<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$title = 'Laporan Presensi Pramuka MAN 4 Banjar';

$report = $_GET['report'] ?? 'harian';
if ($report === 'karyawan') $report = 'anggota';
if ($report === 'loket') $report = 'kelas';
$allowed = ['harian','bulanan','keterlambatan','anggota','verifikasi','kelas'];
if (!in_array($report, $allowed, true)) $report = 'harian';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$userId = (int)($_GET['user_id'] ?? 0);
$kelasFilter = trim($_GET['kelas'] ?? $_GET['loket'] ?? '');

$pdo = db();
$employees = $pdo->query('SELECT id,name,username,COALESCE(kelas, "Pelatih/Pembina") kelas FROM users WHERE role IN ("siswa", "pelatih", "pembina", "admin") ORDER BY name')->fetchAll();
$kelasRows = $pdo->query('SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas <> "" ORDER BY kelas')->fetchAll();

$rows = [];
$summary = [];
$reportTitle = '';

switch ($report) {
    case 'harian':
        $reportTitle = 'Laporan Presensi Harian Latihan Pramuka';
        $stmt = $pdo->prepare('SELECT a.work_date,u.username,u.name,COALESCE(u.kelas, "Pelatih/Pembina") kelas,a.check_in,a.check_out,a.status,a.in_distance,a.in_face_distance,a.out_distance,a.out_face_distance FROM attendances a JOIN users u ON u.id=a.user_id WHERE a.work_date BETWEEN ? AND ? ORDER BY a.work_date DESC,a.check_in DESC,u.name');
        $stmt->execute([$from,$to]);
        $rows = $stmt->fetchAll();
        $s = $pdo->prepare('SELECT COUNT(*) total, SUM(status="hadir") hadir, SUM(status="terlambat") terlambat, SUM(status="izin") izin, SUM(status="sakit") sakit, SUM(status="alpha") alpha FROM attendances WHERE work_date BETWEEN ? AND ?');
        $s->execute([$from,$to]);
        $summary = $s->fetch() ?: [];
        break;

    case 'bulanan':
        $reportTitle = 'Laporan Rekapitulasi Presensi Bulanan';
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $stmt = $pdo->prepare('SELECT u.name,u.username,COALESCE(u.kelas, "Pelatih/Pembina") kelas,COUNT(a.id) total_catatan,SUM(a.status="hadir") hadir,SUM(a.status="terlambat") terlambat,SUM(a.status="izin") izin,SUM(a.status="sakit") sakit,SUM(a.status="alpha") alpha FROM users u LEFT JOIN attendances a ON a.user_id=u.id AND a.work_date BETWEEN ? AND ? WHERE u.role IN ("siswa", "pelatih", "pembina") GROUP BY u.id ORDER BY u.name');
        $stmt->execute([$start,$end]);
        $rows = $stmt->fetchAll();
        break;

    case 'keterlambatan':
        $reportTitle = 'Laporan Keterlambatan Hadir Latihan';
        $stmt = $pdo->prepare('SELECT a.work_date,u.username,u.name,COALESCE(u.kelas, "Pelatih/Pembina") kelas,a.check_in,a.status, TIMESTAMPDIFF(MINUTE, CONCAT(a.work_date," ",?), CONCAT(a.work_date," ",a.check_in)) AS terlambat_menit FROM attendances a JOIN users u ON u.id=a.user_id WHERE a.work_date BETWEEN ? AND ? AND a.check_in IS NOT NULL AND a.check_in > ? ORDER BY a.work_date DESC,terlambat_menit DESC');
        $stmt->execute([attendance_end(),$from,$to,attendance_end()]);
        $rows = $stmt->fetchAll();
        break;

    case 'anggota':
        $reportTitle = 'Laporan Kehadiran Per Anggota / Siswa';
        $stmt = $pdo->prepare('SELECT a.work_date,a.check_in,a.check_out,a.status,a.in_distance,a.in_face_distance FROM attendances a WHERE a.user_id=? AND a.work_date BETWEEN ? AND ? ORDER BY a.work_date DESC');
        $stmt->execute([$userId,$from,$to]);
        $rows = $stmt->fetchAll();
        if ($userId) {
            $s = $pdo->prepare('SELECT u.name,u.username,COALESCE(u.kelas, "Pelatih/Pembina") kelas,COUNT(a.id) total,SUM(a.status="hadir") hadir,SUM(status="terlambat") terlambat,SUM(a.status="izin") izin,SUM(a.status="sakit") sakit,SUM(a.status="alpha") alpha FROM users u LEFT JOIN attendances a ON a.user_id=u.id AND a.work_date BETWEEN ? AND ? WHERE u.id=? GROUP BY u.id');
            $s->execute([$from,$to,$userId]);
            $summary = $s->fetch() ?: [];
        }
        break;

    case 'verifikasi':
        $reportTitle = 'Laporan Audit Verifikasi GPS & Biometrik Wajah';
        $stmt = $pdo->prepare('SELECT a.work_date,u.name,u.username,COALESCE(u.kelas, "Pelatih/Pembina") kelas,a.check_in,a.in_distance,a.in_accuracy,a.in_face_distance,a.check_out,a.out_distance,a.out_accuracy,a.out_face_distance,a.status,a.device FROM attendances a JOIN users u ON u.id=a.user_id WHERE a.work_date BETWEEN ? AND ? ORDER BY a.work_date DESC,a.check_in DESC,u.name');
        $stmt->execute([$from,$to]);
        $rows = $stmt->fetchAll();
        break;

    case 'kelas':
        $reportTitle = 'Laporan Rekapitulasi Presensi Per Kelas';
        $stmt = $pdo->prepare('SELECT COALESCE(NULLIF(u.kelas,""),"Pelatih/Pembina") kelas, COUNT(a.id) total_catatan,SUM(a.status="hadir") hadir,SUM(a.status="terlambat") terlambat,SUM(a.status="izin") izin,SUM(a.status="sakit") sakit,SUM(a.status="alpha") alpha FROM users u LEFT JOIN attendances a ON a.user_id=u.id AND a.work_date BETWEEN ? AND ? WHERE u.role IN ("siswa", "pelatih", "pembina") GROUP BY COALESCE(NULLIF(u.kelas,""),"Pelatih/Pembina") ORDER BY kelas');
        $stmt->execute([$from,$to]);
        $rows = $stmt->fetchAll();
        break;
}

$export = $_GET['export'] ?? '';
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_pramuka_'.$report.'_'.date('Ymd_His').'.csv"');
    $out = fopen('php://output','w');
    if ($report === 'harian') {
        fputcsv($out,['Tanggal','Username','Nama Siswa','Kelas','Masuk','Pulang','Status','Jarak Masuk(m)','Face Distance']);
        foreach($rows as $r) fputcsv($out,[$r['work_date'],$r['username'],$r['name'],$r['kelas'],$r['check_in'],$r['check_out'],$r['status'],$r['in_distance'],$r['in_face_distance']]);
    } elseif ($report === 'bulanan') {
        fputcsv($out,['Nama Siswa','Username','Kelas','Catatan','Hadir','Terlambat','Izin','Sakit','Alpha']);
        foreach($rows as $r) fputcsv($out,[$r['name'],$r['username'],$r['kelas'],$r['total_catatan'],$r['hadir'],$r['terlambat'],$r['izin'],$r['sakit'],$r['alpha']]);
    } elseif ($report === 'keterlambatan') {
        fputcsv($out,['Tanggal','Username','Nama Siswa','Kelas','Jam Masuk','Status','Terlambat (menit)']);
        foreach($rows as $r) fputcsv($out,[$r['work_date'],$r['username'],$r['name'],$r['kelas'],$r['check_in'],$r['status'],$r['terlambat_menit']]);
    } elseif ($report === 'anggota') {
        fputcsv($out,['Tanggal','Jam Masuk','Jam Pulang','Status','Jarak Masuk(m)','Face Distance']);
        foreach($rows as $r) fputcsv($out,[$r['work_date'],$r['check_in'],$r['check_out'],$r['status'],$r['in_distance'],$r['in_face_distance']]);
    } elseif ($report === 'verifikasi') {
        fputcsv($out,['Tanggal','Nama Siswa','Username','Kelas','Masuk','Jarak Masuk(m)','Akurasi GPS(m)','Face Distance','Pulang','Jarak Pulang(m)','Akurasi Pulang(m)','Face Pulang','Status','Perangkat']);
        foreach($rows as $r) fputcsv($out,[$r['work_date'],$r['name'],$r['username'],$r['kelas'],$r['check_in'],$r['in_distance'],$r['in_accuracy'],$r['in_face_distance'],$r['check_out'],$r['out_distance'],$r['out_accuracy'],$r['out_face_distance'],$r['status'],$r['device']]);
    } elseif ($report === 'kelas') {
        fputcsv($out,['Kelas','Catatan','Hadir','Terlambat','Izin','Sakit','Alpha']);
        foreach($rows as $r) fputcsv($out,[$r['kelas'],$r['total_catatan'],$r['hadir'],$r['terlambat'],$r['izin'],$r['sakit'],$r['alpha']]);
    }
    fclose($out);
    exit;
}

$queryBase = $_GET;
unset($queryBase['export']);
function report_url(string $name): string {
    global $queryBase;
    $queryBase['report'] = $name;
    return BASE_URL . '/admin/reports.php?' . http_build_query($queryBase);
}
function status_badge($status): string {
    $class = match($status){'terlambat'=>'terlambat','izin'=>'izin','sakit'=>'sakit','alpha'=>'alpha',default=>'hadir'};
    return '<span class="badge '.e($class).'">'.e(ucfirst((string)$status)).'</span>';
}
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <div>
    <p class="eyebrow">
      <a href="<?= BASE_URL ?>/admin/index.php" style="color: inherit;">Panel Pengelola</a> &rsaquo; Evaluasi Kehadiran
    </p>
    <h1>Laporan Presensi &amp; Evaluasi Pramuka</h1>
    <p class="muted">Monitoring komprehensif kehadiran anggota pramuka penegak dan pelatih MAN 4 Banjar.</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-light btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      <span>Kembali ke Panel</span>
    </a>
  </div>
</section>

<!-- Sub Navigation Tabs for Reports -->
<nav class="report-menu">
  <a class="report-tab <?= $report==='harian'?'active':'' ?>" href="<?= e(report_url('harian')) ?>">
    <span>01.</span> Presensi Harian
  </a>
  <a class="report-tab <?= $report==='bulanan'?'active':'' ?>" href="<?= e(report_url('bulanan')) ?>">
    <span>02.</span> Rekap Bulanan
  </a>
  <a class="report-tab <?= $report==='keterlambatan'?'active':'' ?>" href="<?= e(report_url('keterlambatan')) ?>">
    <span>03.</span> Keterlambatan
  </a>
  <a class="report-tab <?= $report==='anggota'?'active':'' ?>" href="<?= e(report_url('anggota')) ?>">
    <span>04.</span> Per Anggota
  </a>
  <a class="report-tab <?= $report==='verifikasi'?'active':'' ?>" href="<?= e(report_url('verifikasi')) ?>">
    <span>05.</span> Audit GPS & Wajah
  </a>
  <a class="report-tab <?= $report==='kelas'?'active':'' ?>" href="<?= e(report_url('kelas')) ?>">
    <span>06.</span> Rekap Per Kelas
  </a>
</nav>

<!-- Filter Section -->
<section class="card">
  <div class="report-head">
    <div>
      <h2 style="font-size: 18px; margin: 0 0 4px;"><?= e($reportTitle) ?></h2>
      <p class="muted">Tentukan rentang tanggal atau parameter filter di bawah ini</p>
    </div>
    <div class="report-actions">
      <button class="btn btn-light" type="button" onclick="window.print()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
        <span>Cetak Laporan</span>
      </button>
      <a class="btn btn-dark" href="<?= e(report_url($report)) ?>&export=csv">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Ekspor CSV</span>
      </a>
    </div>
  </div>

  <form method="get" class="form-grid cols-3 report-filter" style="margin-top: 10px;">
    <input type="hidden" name="report" value="<?= e($report) ?>">
    <?php if ($report === 'bulanan'): ?>
      <label>
        Pilih Bulan
        <input type="month" name="month" value="<?= e($month) ?>">
      </label>
    <?php elseif ($report === 'anggota'): ?>
      <label>
        Pilih Anggota Siswa / Pelatih
        <select name="user_id">
          <option value="0">Pilih Anggota...</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?= e($emp['id']) ?>" <?= $userId === (int)$emp['id'] ? 'selected' : '' ?>>
              <?= e($emp['name']) ?><?= $emp['kelas'] ? ' (' . e($emp['kelas']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Periode Dari
        <input type="date" name="from" value="<?= e($from) ?>">
      </label>
      <label>
        Sampai Dengan
        <input type="date" name="to" value="<?= e($to) ?>">
      </label>
    <?php else: ?>
      <label>
        Periode Dari
        <input type="date" name="from" value="<?= e($from) ?>">
      </label>
      <label>
        Sampai Dengan
        <input type="date" name="to" value="<?= e($to) ?>">
      </label>
      <?php if ($report === 'kelas'): ?>
        <label>
          Filter Kelas
          <select name="kelas">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasRows as $l): ?>
              <option value="<?= e($l['kelas']) ?>" <?= $kelasFilter === $l['kelas'] ? 'selected' : '' ?>>
                <?= e($l['kelas']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
    <?php endif; ?>

    <div style="display: flex; align-items: flex-end;">
      <button class="btn btn-primary" type="submit" style="width: 100%; height: 42px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <span>Tampilkan Data</span>
      </button>
    </div>
  </form>
</section>

<!-- Report Data Rendering -->
<?php if ($report === 'harian'): ?>
  <section class="stats report-stats">
    <div class="stat-card">
      <small>Total Presensi Tercatat</small>
      <strong><?= e($summary['total'] ?? 0) ?></strong>
    </div>
    <div class="stat-card">
      <small>Hadir Tepat Waktu</small>
      <strong style="color: var(--success);"><?= e($summary['hadir'] ?? 0) ?></strong>
    </div>
    <div class="stat-card">
      <small>Terlambat</small>
      <strong style="color: var(--warning);"><?= e($summary['terlambat'] ?? 0) ?></strong>
    </div>
    <div class="stat-card">
      <small>Izin / Sakit / Alpha</small>
      <strong><?= e((int)($summary['izin'] ?? 0) + (int)($summary['sakit'] ?? 0) + (int)($summary['alpha'] ?? 0)) ?></strong>
    </div>
  </section>

  <section class="card">
    <div class="table-wrap">
      <table class="report-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Nama Anggota</th>
            <th>Kelas</th>
            <th>Jam Masuk</th>
            <th>Jam Pulang</th>
            <th>Status</th>
            <th>Jarak GPS</th>
            <th>Match Wajah</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="text-align:center; padding:24px; color:var(--muted);">Tidak ada catatan presensi pada periode tanggal ini.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><strong><?= e($r['work_date']) ?></strong></td>
                <td><?= e($r['name']) ?></td>
                <td><span class="badge" style="background:var(--bg); border:1px solid var(--line); color:var(--text-body);"><?= e($r['kelas'] ?: '-') ?></span></td>
                <td><span style="font-family:var(--font-mono);"><?= e($r['check_in'] ?: '-') ?></span></td>
                <td><span style="font-family:var(--font-mono);"><?= e($r['check_out'] ?: '-') ?></span></td>
                <td><?= status_badge($r['status']) ?></td>
                <td><?= $r['in_distance'] !== null ? e(round($r['in_distance']) . ' m') : '-' ?></td>
                <td><?= $r['in_face_distance'] !== null ? e(number_format((float)$r['in_face_distance'], 3)) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php elseif ($report === 'bulanan'): ?>
  <section class="card">
    <div class="table-wrap">
      <table class="report-table">
        <thead>
          <tr>
            <th>Nama Anggota</th>
            <th>Kelas</th>
            <th>Total Kehadiran</th>
            <th>Tepat Waktu</th>
            <th>Terlambat</th>
            <th>Izin</th>
            <th>Sakit</th>
            <th>Alpha</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="text-align:center; padding:24px; color:var(--muted);">Tidak ada data pada bulan yang dipilih.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><strong><?= e($r['name']) ?></strong></td>
                <td><?= e($r['kelas'] ?: '-') ?></td>
                <td><strong><?= e($r['total_catatan']) ?> hari</strong></td>
                <td><span class="badge hadir"><?= e($r['hadir'] ?? 0) ?></span></td>
                <td><span class="badge terlambat"><?= e($r['terlambat'] ?? 0) ?></span></td>
                <td><?= e($r['izin'] ?? 0) ?></td>
                <td><?= e($r['sakit'] ?? 0) ?></td>
                <td><?= e($r['alpha'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="muted small-note">Catatan: Rekapitulasi bulanan didasarkan pada catatan presensi aktual yang tersimpan di sistem Pramuka MAN 4 Banjar.</p>
  </section>

<?php elseif ($report === 'keterlambatan'): ?>
  <section class="card">
    <div class="table-wrap">
      <table class="report-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Nama Anggota</th>
            <th>Kelas</th>
            <th>Jam Masuk</th>
            <th>Batas Masuk</th>
            <th>Durasi Terlambat</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="text-align:center; padding:24px; color:var(--muted);">Tidak ada riwayat keterlambatan pada periode ini. Semua hadir tepat waktu!</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><strong><?= e($r['work_date']) ?></strong></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['kelas'] ?: '-') ?></td>
                <td><span style="font-family:var(--font-mono);"><?= e($r['check_in']) ?></span></td>
                <td><?= e(attendance_end()) ?> WITA</td>
                <td><strong style="color:var(--warning);"><?= e($r['terlambat_menit']) ?> menit</strong></td>
                <td><?= status_badge($r['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php elseif ($report === 'anggota'): ?>
  <?php if ($userId && $summary): ?>
    <section class="stats report-stats">
      <div class="stat-card">
        <small>Nama Anggota</small>
        <strong><?= e($summary['name']) ?></strong>
        <span class="muted" style="font-size:12px; margin-top:4px;">Kelas: <?= e($summary['kelas'] ?: 'Umum') ?></span>
      </div>
      <div class="stat-card">
        <small>Total Hadir</small>
        <strong><?= e($summary['total'] ?? 0) ?></strong>
      </div>
      <div class="stat-card">
        <small>Tepat Waktu</small>
        <strong style="color:var(--success);"><?= e($summary['hadir'] ?? 0) ?></strong>
      </div>
      <div class="stat-card">
        <small>Terlambat</small>
        <strong style="color:var(--warning);"><?= e($summary['terlambat'] ?? 0) ?></strong>
      </div>
    </section>
  <?php endif; ?>

  <section class="card">
    <div class="table-wrap">
      <table class="report-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Jam Masuk</th>
            <th>Jam Pulang</th>
            <th>Status</th>
            <th>Jarak Presensi</th>
            <th>Match Wajah (Face Distance)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="6" style="text-align:center; padding:24px; color:var(--muted);">
              <?= $userId ? 'Tidak ada catatan presensi untuk anggota ini pada periode yang dipilih.' : 'Silakan pilih nama anggota pada filter di atas.' ?>
            </td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><strong><?= e($r['work_date']) ?></strong></td>
                <td><span style="font-family:var(--font-mono);"><?= e($r['check_in'] ?: '-') ?></span></td>
                <td><span style="font-family:var(--font-mono);"><?= e($r['check_out'] ?: '-') ?></span></td>
                <td><?= status_badge($r['status']) ?></td>
                <td><?= $r['in_distance'] !== null ? e(round($r['in_distance']) . ' m') : '-' ?></td>
                <td><?= $r['in_face_distance'] !== null ? e(number_format((float)$r['in_face_distance'], 3)) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php elseif ($report === 'verifikasi'): ?>
  <section class="card">
    <div class="table-wrap">
      <table class="report-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Nama Anggota</th>
            <th>Masuk</th>
            <th>Jarak GPS</th>
            <th>Akurasi</th>
            <th>Face Masuk</th>
            <th>Pulang</th>
            <th>Jarak Pulang</th>
            <th>Face Pulang</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="10" style="text-align:center; padding:24px; color:var(--muted);">Tidak ada data audit verifikasi pada periode ini.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><strong><?= e($r['work_date']) ?></strong></td>
                <td><?= e($r['name']) ?></td>
                <td><span style="font-family:var(--font-mono);"><?= e($r['check_in'] ?: '-') ?></span></td>
                <td><?= $r['in_distance'] !== null ? e(round($r['in_distance']) . ' m') : '-' ?></td>
                <td><?= $r['in_accuracy'] !== null ? e(round($r['in_accuracy']) . ' m') : '-' ?></td>
                <td><?= $r['in_face_distance'] !== null ? e(number_format((float)$r['in_face_distance'], 3)) : '-' ?></td>
                <td><span style="font-family:var(--font-mono);"><?= e($r['check_out'] ?: '-') ?></span></td>
                <td><?= $r['out_distance'] !== null ? e(round($r['out_distance']) . ' m') : '-' ?></td>
                <td><?= $r['out_face_distance'] !== null ? e(number_format((float)$r['out_face_distance'], 3)) : '-' ?></td>
                <td><?= status_badge($r['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="muted small-note">Face Distance: Nilai Euclidean distance antara descriptor wajah live dengan template registrasi. Nilai lebih rendah menunjukkan kecocokan lebih tinggi.</p>
  </section>

<?php elseif ($report === 'kelas'): ?>
  <section class="card">
    <div class="table-wrap">
      <table class="report-table">
        <thead>
          <tr>
            <th>Kelas</th>
            <th>Total Kehadiran</th>
            <th>Tepat Waktu</th>
            <th>Terlambat</th>
            <th>Izin</th>
            <th>Sakit</th>
            <th>Alpha</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="text-align:center; padding:24px; color:var(--muted);">Tidak ada data per kelas pada periode yang dipilih.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><strong style="color:var(--primary);"><?= e($r['kelas']) ?></strong></td>
                <td><?= e($r['total_catatan']) ?></td>
                <td><span class="badge hadir"><?= e($r['hadir'] ?? 0) ?></span></td>
                <td><span class="badge terlambat"><?= e($r['terlambat'] ?? 0) ?></span></td>
                <td><?= e($r['izin'] ?? 0) ?></td>
                <td><?= e($r['sakit'] ?? 0) ?></td>
                <td><?= e($r['alpha'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
