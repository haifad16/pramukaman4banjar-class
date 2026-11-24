<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$title = 'Input Presensi Pertemuan Pramuka';

$pertemuanList = get_pertemuan_pramuka();
$selectedP = (int)($_GET['p'] ?? 1);
if ($selectedP < 1 || $selectedP > 4) $selectedP = 1;

$selectedGroup = $_GET['group'] ?? 'Kelas X Putri';
$siswaGroups = get_siswa_pramuka_grouped();

$ptmInfo = $pertemuanList[$selectedP - 1] ?? [
    'pertemuan_ke' => $selectedP,
    'hari_tanggal' => 'Kamis, November 2026',
    'work_date' => '2026-11-05',
    'materi_default' => 'Latihan Pramuka'
];

$groupData = $siswaGroups[$selectedGroup] ?? reset($siswaGroups);
$students = $groupData['students'];

// Tangani simpan presensi pertemuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance_batch') {
    verify_csrf();
    $statuses = $_POST['status'] ?? [];
    $workDate = $ptmInfo['work_date'] ?? date('Y-m-d');

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO attendances (user_id, work_date, pertemuan_ke, status, signature, check_in)
            VALUES (?, ?, ?, ?, 'TTD', '16:00:00')
            ON DUPLICATE KEY UPDATE status = VALUES(status), check_in = '16:00:00'
        ");

        $count = 0;
        foreach ($statuses as $userId => $st) {
            $userId = (int)$userId;
            $st = in_array($st, ['hadir', 'izin', 'sakit', 'alpha'], true) ? $st : 'hadir';
            $stmt->execute([$userId, $workDate, $selectedP, $st]);
            $count++;
        }

        $pdo->commit();
        flash('success', "Berhasil menyimpan presensi Pertemuan {$selectedP} untuk {$count} siswa.");
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        flash('error', "Gagal menyimpan presensi: " . $e->getMessage());
    }
    redirect('/admin/input_presensi.php?p=' . $selectedP . '&group=' . urlencode($selectedGroup));
}

// Ambil status tersimpan untuk pertemuan ini
$currentStatus = [];
try {
    $stmtCurrent = db()->prepare("SELECT user_id, status FROM attendances WHERE pertemuan_ke = ?");
    $stmtCurrent->execute([$selectedP]);
    while ($row = $stmtCurrent->fetch()) {
        $currentStatus[$row['user_id']] = $row['status'];
    }
} catch (Throwable $e) {}

require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <div>
    <p class="eyebrow">
      <a href="<?= BASE_URL ?>/admin/index.php" style="color: inherit;">Panel Pengelola</a> &rsaquo; Presensi Pertemuan
    </p>
    <h1>Input Presensi Pertemuan Pramuka</h1>
    <p class="muted">Pencatatan kehadiran anggota pramuka per pertemuan mingguan (Kamis sore).</p>
  </div>
  <div style="display: flex; gap: 8px;">
    <a href="<?= BASE_URL ?>/admin/tarikan_data.php?mode=digital" class="btn btn-light btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
      <span>Lihat Rekap Tarikan Data</span>
    </a>
  </div>
</section>

<!-- Pilihan Pertemuan & Kelompok -->
<div class="card" style="margin-bottom: 20px; padding: 16px 20px;">
  <form method="get" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
    <label style="margin: 0; min-width: 220px;">
      <span style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">Pilih Pertemuan:</span>
      <select name="p" onchange="this.form.submit()" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--line);">
        <?php foreach ($pertemuanList as $idx => $ptm): ?>
          <option value="<?= $idx + 1 ?>" <?= $selectedP === ($idx + 1) ? 'selected' : '' ?>>
            Pertemuan <?= $idx + 1 ?> &bull; <?= e($ptm['hari_tanggal'] ?? ($ptm['hari'] . ', ' . $ptm['tanggal'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label style="margin: 0; min-width: 220px;">
      <span style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">Pilih Kelompok Anggota:</span>
      <select name="group" onchange="this.form.submit()" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--line);">
        <?php foreach (array_keys($siswaGroups) as $gName): ?>
          <option value="<?= e($gName) ?>" <?= $selectedGroup === $gName ? 'selected' : '' ?>>
            <?= e($gName) ?> (<?= count($siswaGroups[$gName]['students']) ?> Siswa)
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div style="flex: 1; text-align: right; min-width: 200px;">
      <span class="badge hadir" style="font-size: 13px; padding: 8px 14px;">
        📅 <?= e($ptmInfo['hari_tanggal'] ?? ($ptmInfo['hari'] . ', ' . $ptmInfo['tanggal'])) ?>
      </span>
    </div>
  </form>
</div>

<!-- Form Checklist Presensi -->
<form method="post" class="card" style="padding: 24px;">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="save_attendance_batch">

  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
    <div>
      <h2 style="font-size: 17px; margin: 0;">Presensi Siswa: <?= e($selectedGroup) ?> (<?= count($students) ?> Siswa)</h2>
      <p class="muted" style="font-size: 13px; margin: 2px 0 0 0;">
        Materi: <b><?= e($ptmInfo['materi_default'] ?? $ptmInfo['materi'] ?? 'Latihan Rutin Pramuka') ?></b>
      </p>
    </div>
    <div style="display: flex; gap: 8px;">
      <button type="button" class="btn btn-sm btn-light" onclick="markAll('hadir')">✓ Set Semua Hadir</button>
      <button type="button" class="btn btn-sm btn-light" onclick="markAll('izin')">Set Semua Izin</button>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width: 45px; text-align: center;">No</th>
          <th>Nama Siswa</th>
          <th style="width: 90px; text-align: center;">Kelas</th>
          <th style="width: 80px; text-align: center;">Gender</th>
          <th style="width: 280px; text-align: center;">Status Kehadiran</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $i => $s): ?>
          <?php 
            $sId = $s['id'] ?? ($i + 1);
            $cur = $currentStatus[$sId] ?? 'hadir';
          ?>
          <tr>
            <td style="text-align: center;"><?= $i + 1 ?></td>
            <td style="font-weight: 600;"><?= e($s['name'] ?? $s['nama']) ?></td>
            <td style="text-align: center; font-weight: bold;"><?= e($s['kelas']) ?></td>
            <td style="text-align: center;">
              <span class="status-pill <?= ($s['gender'] ?? 'P') === 'L' ? 'primary' : 'warning' ?>" style="font-size: 11px; padding: 2px 8px;">
                <?= ($s['gender'] ?? 'P') === 'L' ? 'Putra' : 'Putri' ?>
              </span>
            </td>
            <td>
              <div style="display: flex; gap: 12px; justify-content: center;">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; margin: 0; font-size: 13px;">
                  <input type="radio" name="status[<?= $sId ?>]" value="hadir" class="st-radio" data-uid="<?= $sId ?>" <?= $cur === 'hadir' ? 'checked' : '' ?>>
                  <span style="color: var(--success); font-weight: 600;">Hadir</span>
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; margin: 0; font-size: 13px;">
                  <input type="radio" name="status[<?= $sId ?>]" value="izin" class="st-radio" data-uid="<?= $sId ?>" <?= $cur === 'izin' ? 'checked' : '' ?>>
                  <span style="color: var(--warning); font-weight: 500;">Izin</span>
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; margin: 0; font-size: 13px;">
                  <input type="radio" name="status[<?= $sId ?>]" value="sakit" class="st-radio" data-uid="<?= $sId ?>" <?= $cur === 'sakit' ? 'checked' : '' ?>>
                  <span style="color: #6366f1; font-weight: 500;">Sakit</span>
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; margin: 0; font-size: 13px;">
                  <input type="radio" name="status[<?= $sId ?>]" value="alpha" class="st-radio" data-uid="<?= $sId ?>" <?= $cur === 'alpha' ? 'checked' : '' ?>>
                  <span style="color: var(--danger); font-weight: 500;">Alpha</span>
                </label>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary btn-lg">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
      <span>Simpan Presensi Pertemuan Ini</span>
    </button>
  </div>
</form>

<script>
function markAll(status) {
  document.querySelectorAll('input[type="radio"][value="' + status + '"]').forEach(r => {
    r.checked = true;
  });
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

