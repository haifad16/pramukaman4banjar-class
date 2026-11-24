<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$title = 'Tarikan Data Presensi Pramuka MAN 4 Banjar';

$tab = $_GET['tab'] ?? 'all';
$mode = $_GET['mode'] ?? 'cetak'; // 'cetak' (garis TTD) atau 'digital' (status hadir)

$pertemuan = get_pertemuan_pramuka();
$pelatihData = get_presensi_pelatih_data();
$siswaGroups = get_siswa_pramuka_grouped();

// Ambil data attendances yang tersimpan di DB & sinkronkan pertemuan_ke jika belum terisi
$attendanceMatrix = [];
$attendanceMatrixByUsername = [];
try {
    $rows = db()->query("
        SELECT a.id, a.user_id, a.pertemuan_ke, a.work_date, a.status, a.check_in, a.signature, u.username, u.name 
        FROM attendances a 
        LEFT JOIN users u ON a.user_id = u.id
    ")->fetchAll();

    foreach ($rows as $r) {
        $pKe = !empty($r['pertemuan_ke']) ? (int)$r['pertemuan_ke'] : null;
        
        // Jika pertemuan_ke masih NULL, petakan otomatis dari tanggal pelaksanaan (work_date)
        if (!$pKe && !empty($r['work_date'])) {
            if ($r['work_date'] <= '2026-11-08') $pKe = 1;
            elseif ($r['work_date'] <= '2026-11-15') $pKe = 2;
            elseif ($r['work_date'] <= '2026-11-22') $pKe = 3;
            else $pKe = 4;

            // Auto-update ke database agar pertemuan_ke tersimpan permanen
            try {
                db()->prepare("UPDATE attendances SET pertemuan_ke = ?, signature = 'TTD' WHERE id = ?")->execute([$pKe, $r['id']]);
            } catch (Throwable $e) {}
        }

        if ($pKe) {
            $r['pertemuan_ke'] = $pKe;
            $r['signature'] = $r['signature'] ?: 'TTD';
            $attendanceMatrix[$r['user_id']][$pKe] = $r;
            if (!empty($r['username'])) {
                $attendanceMatrixByUsername[$r['username']][$pKe] = $r;
            }
        }
    }
} catch (Throwable $e) {}

// Tangani simpan materi pelatih jika POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pelatih_materi') {
    verify_csrf();
    $pelatihName = trim($_POST['pelatih_name'] ?? '');
    $materis = $_POST['materi'] ?? [];
    try {
        $stmt = db()->prepare("
            INSERT INTO presensi_pelatih (pelatih_name, pertemuan_ke, hari_tanggal, work_date, materi, tanda_tangan, status)
            VALUES (?, ?, ?, ?, ?, 'TTD', 'hadir')
            ON DUPLICATE KEY UPDATE materi = VALUES(materi)
        ");
        foreach ($materis as $pKe => $materiVal) {
            $pKe = (int)$pKe;
            $ptm = $pertemuan[$pKe - 1] ?? null;
            $ht = $ptm ? ($ptm['hari_tanggal'] ?? ($ptm['hari'] . ', ' . $ptm['tanggal'])) : 'Kamis';
            $wd = $ptm ? $ptm['work_date'] : '2026-11-05';
            $stmt->execute([$pelatihName, $pKe, $ht, $wd, trim($materiVal)]);
        }
        flash('success', 'Materi pelatihan untuk ' . $pelatihName . ' berhasil diperbarui.');
    } catch (Throwable $e) {
        flash('error', 'Gagal menyimpan materi: ' . $e->getMessage());
    }
    redirect('/admin/tarikan_data.php?tab=' . urlencode($tab) . '&mode=' . urlencode($mode));
}

require __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar (Hidden when printing) -->
<div class="no-print" style="margin-bottom: 24px;">
  <section class="hero" style="margin-bottom: 18px;">
    <div>
      <p class="eyebrow">
        <a href="<?= BASE_URL ?>/admin/index.php" style="color: inherit;">Panel Pengelola</a> &rsaquo; Ekstrakurikuler Pramuka
      </p>
      <h1>Tarikan Data Presensi Resmi</h1>
      <p class="muted">Format resmi presensi pelatih & 113 siswa anggota Pramuka Gugus Depan Pangkalan MAN 4 Banjar.</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>/admin/export_tarikan_data.php?tab=<?= urlencode($tab) ?>&mode=<?= urlencode($mode) ?>" class="btn btn-success" style="background: #15803d; color: white;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Ekspor Excel (.xls)</span>
      </a>
      <button onclick="window.print()" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
        <span>Cetak Format Resmi / PDF</span>
      </button>
    </div>
  </section>

  <!-- Filter & Navigation Tabs -->
  <div class="card" style="padding: 14px 18px; margin-bottom: 16px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
      <!-- Group Tabs -->
      <div style="display: flex; gap: 6px; flex-wrap: wrap;">
        <a href="?tab=all&mode=<?= e($mode) ?>" class="btn btn-sm <?= $tab === 'all' ? 'btn-primary' : 'btn-light' ?>">Semua Format</a>
        <a href="?tab=pelatih&mode=<?= e($mode) ?>" class="btn btn-sm <?= $tab === 'pelatih' ? 'btn-primary' : 'btn-light' ?>">Presensi Pelatih (2)</a>
        <a href="?tab=X-PI&mode=<?= e($mode) ?>" class="btn btn-sm <?= $tab === 'X-PI' ? 'btn-primary' : 'btn-light' ?>">X Putri (34)</a>
        <a href="?tab=X-PA&mode=<?= e($mode) ?>" class="btn btn-sm <?= $tab === 'X-PA' ? 'btn-primary' : 'btn-light' ?>">X Putra (16)</a>
        <a href="?tab=XI-PI&mode=<?= e($mode) ?>" class="btn btn-sm <?= $tab === 'XI-PI' ? 'btn-primary' : 'btn-light' ?>">XI Putri (27)</a>
        <a href="?tab=XI-PA&mode=<?= e($mode) ?>" class="btn btn-sm <?= $tab === 'XI-PA' ? 'btn-primary' : 'btn-light' ?>">XI Putra (6)</a>
        <a href="?tab=XII-PI&mode=<?= e($mode) ?>" class="btn btn-sm <?= $tab === 'XII-PI' ? 'btn-primary' : 'btn-light' ?>">XII Putri (19)</a>
        <a href="?tab=XII-PA&mode=<?= e($mode) ?>" class="btn btn-sm <?= $tab === 'XII-PA' ? 'btn-primary' : 'btn-light' ?>">XII Putra (11)</a>
      </div>

      <!-- Mode Toggle -->
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 13px; font-weight: 500; color: var(--muted);">Tampilan Kolom:</span>
        <div style="background: var(--bg-alt, #f1f5f9); padding: 3px; border-radius: 8px; display: inline-flex;">
          <a href="?tab=<?= e($tab) ?>&mode=cetak" class="btn btn-sm <?= $mode === 'cetak' ? 'btn-primary' : 'btn-light' ?>" style="font-size: 12px; padding: 4px 10px;">Format TTD Basah</a>
          <a href="?tab=<?= e($tab) ?>&mode=digital" class="btn btn-sm <?= $mode === 'digital' ? 'btn-primary' : 'btn-light' ?>" style="font-size: 12px; padding: 4px 10px;">Status Hadir Digital</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- DOKUMEN RESMI TARIKAN DATA (DAPAT DICETAK LANGSUNG / DIJADIKAN PDF)        -->
<!-- ========================================================================= -->
<div class="official-print-document">

  <!-- ========================================================================= -->
  <!-- BAGIAN 1: PRESENSI PELATIH EKSTRAKURIKULER PRAMUKA                        -->
  <!-- ========================================================================= -->
  <?php if ($tab === 'all' || $tab === 'pelatih'): ?>
    <?php foreach ($pelatihData as $pNama => $rows): ?>
      <div class="print-page-wrapper sheet-section">
        <div class="official-doc-header">
          <div class="doc-header-kop">
            <img src="<?= BASE_URL ?>/assets/icons/logo_man4banjar.png" alt="Logo MAN 4 Banjar" class="kop-logo-left">
            <div class="kop-text">
              <h3>KEMENTERIAN AGAMA REPUBLIK INDONESIA</h3>
              <h4>KANTOR KEMENTERIAN AGAMA KABUPATEN BANJAR</h4>
              <h2>MADRASAH ALIYAH NEGERI 4 BANJAR</h2>
              <p>GUGUS DEPAN 01.095 - 01.096 PANGKALAN MAN 4 BANJAR</p>
            </div>
            <img src="<?= BASE_URL ?>/assets/icons/logo_pramuka_man4banjar.png" alt="Logo Pramuka MAN 4 Banjar" class="kop-logo-right">
          </div>
          <div class="kop-divider"></div>

          <h2 class="official-table-title">PRESENSI PELATIH EKSTRAKURIKULER</h2>
          <h3 class="official-table-subtitle">PRAMUKA MAN 4 BANJAR</h3>
          <p class="official-period-info">Bulan: November 2026 &bull; Tahun Ajaran 2026/2027</p>
        </div>

        <table class="official-table">
          <thead>
            <tr>
              <th style="width: 50px; text-align: center;">No.</th>
              <th style="width: 230px; text-align: left;">Nama Pelatih</th>
              <th style="width: 220px; text-align: left;">Hari, Tanggal</th>
              <th style="text-align: left;">Materi</th>
              <th style="width: 140px; text-align: center;">Tanda Tangan</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $no = 1; 
            foreach ($rows as $idx => $r): 
            ?>
              <tr>
                <td style="text-align: center; font-weight: 500;"><?= $no++ ?></td>
                <td style="font-weight: 600;"><?= e($r['pelatih_name']) ?></td>
                <td><?= e($r['hari_tanggal']) ?></td>
                <td>
                  <span class="materi-display"><?= e($r['materi'] ?: '-') ?></span>
                </td>
                <td style="text-align: center; height: 48px; vertical-align: middle;">
                  <?php 
                  $isPelatihHadir = ($r['status'] === 'hadir' && ($r['tanda_tangan'] === 'TTD' || !empty($r['tanda_tangan'])));
                  $pelatihSigImage = (!empty($r['tanda_tangan']) && str_starts_with($r['tanda_tangan'], 'data:image/')) ? $r['tanda_tangan'] : '';
                  foreach ($attendanceMatrix as $uid => $ptms) {
                      $pKe = $idx + 1;
                      if (isset($ptms[$pKe]) && isset($ptms[$pKe]['name'])) {
                          $pNameA = strtolower(str_replace(['.', ' '], '', $ptms[$pKe]['name']));
                          $pNameB = strtolower(str_replace(['.', ' '], '', $r['pelatih_name']));
                          $isMatch = ($pNameA === $pNameB) 
                            || (str_contains($pNameA, 'haifad') && str_contains($pNameB, 'haifad')) 
                            || (str_contains($pNameA, 'aulia') && str_contains($pNameB, 'aulia'))
                            || str_contains($pNameA, $pNameB) || str_contains($pNameB, $pNameA);
                          if ($isMatch) {
                              if (in_array($ptms[$pKe]['status'], ['hadir', 'terlambat'], true)) {
                                  $isPelatihHadir = true;
                                  if (empty($pelatihSigImage) && !empty($ptms[$pKe]['signature']) && str_starts_with($ptms[$pKe]['signature'], 'data:image/')) {
                                      $pelatihSigImage = $ptms[$pKe]['signature'];
                                  }
                                  break;
                              }
                          }
                      }
                  }
                  ?>
                  <?php if ($isPelatihHadir): ?>
                    <?php if (!empty($pelatihSigImage)): ?>
                      <div class="ttd-img-container" title="Tanda Tangan Pelatih: <?= e($r['pelatih_name']) ?>">
                        <img src="<?= e($pelatihSigImage) ?>" alt="TTD Pelatih" class="user-signature-img user-signature-pelatih">
                      </div>
                    <?php else: ?>
                      <div class="ttd-stamp" style="display: inline-flex;" title="Tanda Tangan Pelatih Terverifikasi">
                        <span class="ttd-badge" style="font-size: 11.5px; padding: 2px 8px;">✓ TTD Digital</span>
                        <span class="ttd-time" style="font-size: 9.5px;">Terverifikasi</span>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <?php if ($mode === 'digital'): ?>
                      <span style="color: #94a3b8; font-size: 11px;">Belum Hadir</span>
                    <?php else: ?>
                      <span class="signature-line"><?= $no - 1 ?>. ....................</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Signatures for Pelatih (Pembina Putra, Pembina Putri, and Wakamad Kesiswaan) -->
        <div class="official-signature-block">
          <p class="sig-heading" style="text-align: center; margin-bottom: 12px; font-weight: bold;">Mengetahui,</p>
          <div class="sig-row-2">
            <div class="sig-box">
              <p class="sig-role">Pembina Pramuka Putera</p>
              <div class="sig-space"></div>
              <p class="sig-name"><u>Fadlan Hidayat, S.Pd.</u></p>
              <p class="sig-nip">NIP 198903082019031008</p>
            </div>
            <div class="sig-box">
              <p class="sig-role">Pembina Pramuka Puteri</p>
              <div class="sig-space"></div>
              <p class="sig-name"><u>Elda Riana Yaslitha, S.Pd.</u></p>
              <p class="sig-nip">NIP 199404082025212011</p>
            </div>
          </div>

          <div class="sig-row-center" style="margin-top: 18px;">
            <div class="sig-box-center">
              <p class="sig-role">Wakamad Kesiswaan</p>
              <div class="sig-space"></div>
              <p class="sig-name"><u>Jiabus Sarury, S.Pd., M.A</u></p>
              <p class="sig-nip">NIP 19930822201903100</p>
            </div>
          </div>
        </div>
      </div>
      <div class="page-break"></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- ========================================================================= -->
  <!-- BAGIAN 2: PRESENSI SISWA EKSTRAKURIKULER PRAMUKA                          -->
  <!-- ========================================================================= -->
  <?php
  $activeTabs = [];
  if ($tab === 'all') {
      $activeTabs = ['Kelas X Putri', 'Kelas X Putra', 'Kelas XI Putri', 'Kelas XI Putra', 'Kelas XII Putri', 'Kelas XII Putra'];
  } elseif ($tab === 'X-PI') {
      $activeTabs = ['Kelas X Putri'];
  } elseif ($tab === 'X-PA') {
      $activeTabs = ['Kelas X Putra'];
  } elseif ($tab === 'XI-PI') {
      $activeTabs = ['Kelas XI Putri'];
  } elseif ($tab === 'XI-PA') {
      $activeTabs = ['Kelas XI Putra'];
  } elseif ($tab === 'XII-PI') {
      $activeTabs = ['Kelas XII Putri'];
  } elseif ($tab === 'XII-PA') {
      $activeTabs = ['Kelas XII Putra'];
  }
  ?>

  <?php foreach ($activeTabs as $groupKey): ?>
    <?php 
      $group = $siswaGroups[$groupKey] ?? null; 
      if (!$group) continue;
      $students = $group['students'];
    ?>
    <div class="print-page-wrapper sheet-section">
      <div class="official-doc-header">
        <div class="doc-header-kop">
          <img src="<?= BASE_URL ?>/assets/icons/logo_man4banjar.png" alt="Logo MAN 4 Banjar" class="kop-logo-left">
          <div class="kop-text">
            <h3>KEMENTERIAN AGAMA REPUBLIK INDONESIA</h3>
            <h4>KANTOR KEMENTERIAN AGAMA KABUPATEN BANJAR</h4>
            <h2>MADRASAH ALIYAH NEGERI 4 BANJAR</h2>
            <p>GUGUS DEPAN 01.095 - 01.096 PANGKALAN MAN 4 BANJAR</p>
          </div>
          <img src="<?= BASE_URL ?>/assets/icons/logo_pramuka_man4banjar.png" alt="Logo Pramuka MAN 4 Banjar" class="kop-logo-right">
        </div>
        <div class="kop-divider"></div>

        <h2 class="official-table-title">PRESENSI SISWA EKSTRAKURIKULER</h2>
        <h3 class="official-table-subtitle">PRAMUKA MAN 4 BANJAR</h3>
        <p class="official-period-info">
          Kelompok: <b><?= e($groupKey) ?></b> (<?= count($students) ?> Siswa) &bull; Bulan: November 2026
        </p>
      </div>

      <table class="official-table">
        <thead>
          <tr>
            <th rowspan="2" style="width: 42px; text-align: center; vertical-align: middle;">NO</th>
            <th rowspan="2" style="text-align: left; vertical-align: middle;">NAMA SISWA</th>
            <th rowspan="2" style="width: 85px; text-align: center; vertical-align: middle;">KELAS</th>
            <th colspan="4" style="text-align: center; border-bottom: 1px solid #1e293b;">PERTEMUAN</th>
          </tr>
          <tr>
            <th style="width: 80px; text-align: center; font-size: 11px;">1<br><span style="font-size: 10px; font-weight: normal; color: #475569;">TTD</span></th>
            <th style="width: 80px; text-align: center; font-size: 11px;">2<br><span style="font-size: 10px; font-weight: normal; color: #475569;">TTD</span></th>
            <th style="width: 80px; text-align: center; font-size: 11px;">3<br><span style="font-size: 10px; font-weight: normal; color: #475569;">TTD</span></th>
            <th style="width: 80px; text-align: center; font-size: 11px;">4<br><span style="font-size: 10px; font-weight: normal; color: #475569;">TTD</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $i => $s): ?>
            <tr>
              <td style="text-align: center;"><?= $i + 1 ?></td>
              <td style="font-weight: 500;"><?= e($s['name'] ?? $s['nama']) ?></td>
              <td style="text-align: center; font-weight: 600;"><?= e($s['kelas']) ?></td>
              
              <?php for ($p = 1; $p <= 4; $p++): ?>
                <td style="text-align: center; height: 34px; vertical-align: middle; padding: 3px;">
                  <?php 
                  $sId = $s['id'] ?? 0;
                  $uName = $s['username'] ?? '';
                  $rec = $attendanceMatrix[$sId][$p] ?? ($uName && isset($attendanceMatrixByUsername[$uName][$p]) ? $attendanceMatrixByUsername[$uName][$p] : null);
                  $isHadir = $rec && in_array($rec['status'], ['hadir', 'terlambat'], true);
                  ?>
                  <?php if ($isHadir): ?>
                    <?php if (!empty($rec['signature']) && str_starts_with($rec['signature'], 'data:image/')): ?>
                      <!-- Tanda Tangan Perorangan (Goresan Asli) -->
                      <div class="ttd-img-container" title="Tanda Tangan: <?= e($s['name'] ?? $s['nama']) ?> (<?= e(substr($rec['check_in'] ?? '', 0, 5)) ?> WITA)">
                        <img src="<?= e($rec['signature']) ?>" alt="TTD" class="user-signature-img">
                        <span class="ttd-time" style="display: block; font-size: 8.5px; color: #475569; line-height: 1; margin-top: 2px;"><?= e(substr($rec['check_in'] ?? '', 0, 5)) ?></span>
                      </div>
                    <?php else: ?>
                      <!-- Tanda Tangan / Paraf Digital Terverifikasi -->
                      <div class="ttd-stamp" title="Hadir Terverifikasi pada <?= e(substr($rec['check_in'] ?? '', 0, 5)) ?> WITA">
                        <span class="ttd-badge">✓ TTD</span>
                        <span class="ttd-time"><?= e(substr($rec['check_in'] ?? '', 0, 5)) ?></span>
                      </div>
                    <?php endif; ?>
                  <?php elseif ($rec && $rec['status'] === 'izin'): ?>
                    <span class="badge izin" style="font-size: 10px;">Izin</span>
                  <?php elseif ($rec && $rec['status'] === 'sakit'): ?>
                    <span class="badge sakit" style="font-size: 10px;">Sakit</span>
                  <?php elseif ($rec && $rec['status'] === 'alpha'): ?>
                    <span class="badge alpha" style="font-size: 10px;">Alpha</span>
                  <?php else: ?>
                    <?php if ($mode === 'digital'): ?>
                      <span style="color: #94a3b8; font-size: 11px;">—</span>
                    <?php else: ?>
                      <!-- Kolom Tanda Tangan Basah Manual (Belum Absen) -->
                      <span class="ttd-box"></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Signature Block for Students: Pembina Pramuka Putra & Putri -->
      <div class="official-signature-block">
        <p class="sig-heading" style="text-align: center; margin-bottom: 12px; font-weight: bold;">Mengetahui,</p>
        <div class="sig-row-2">
          <div class="sig-box">
            <p class="sig-role">Pembina Pramuka Putra</p>
            <div class="sig-space"></div>
            <p class="sig-name"><u>Fadlan Hidayat, S.Pd.</u></p>
            <p class="sig-nip">NIP 198710022019031003</p>
          </div>
          <div class="sig-box">
            <p class="sig-role">Pembina Pramuka Putri</p>
            <div class="sig-space"></div>
            <p class="sig-name"><u>Elda Riana Yaslitha, S.Pd.</u></p>
            <p class="sig-nip">NIP 199404082025212011</p>
          </div>
        </div>
      </div>
    </div>
    <div class="page-break"></div>
  <?php endforeach; ?>

</div>

<!-- CSS Styling for Screen and Print -->
<style>
/* Reset & Screen Layout */
.official-print-document {
  max-width: 950px;
  margin: 0 auto 40px auto;
}

.sheet-section {
  background: white;
  padding: 32px 36px;
  border-radius: 12px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.06);
  margin-bottom: 30px;
  border: 1px solid #e2e8f0;
}

/* KOP SURAT RESMI */
.doc-header-kop {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.kop-logo-left, .kop-logo-right {
  height: 68px;
  width: auto;
  object-fit: contain;
}

.kop-text {
  text-align: center;
  flex: 1;
  padding: 0 12px;
}

.kop-text h3 {
  font-size: 13px;
  margin: 0;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: #1e293b;
  font-family: Arial, sans-serif;
}

.kop-text h4 {
  font-size: 12px;
  margin: 2px 0;
  font-weight: 700;
  color: #1e293b;
  font-family: Arial, sans-serif;
}

.kop-text h2 {
  font-size: 18px;
  margin: 4px 0;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: 1px;
  font-family: Arial, sans-serif;
}

.kop-text p {
  font-size: 11px;
  margin: 0;
  font-weight: 600;
  color: #334155;
  letter-spacing: 0.5px;
}

.kop-divider {
  border-bottom: 3px double #0f172a;
  margin: 8px 0 16px 0;
}

.official-table-title {
  text-align: center;
  font-size: 16px;
  font-weight: 800;
  margin: 0;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: #0f172a;
}

.official-table-subtitle {
  text-align: center;
  font-size: 14px;
  font-weight: 700;
  margin: 2px 0 4px 0;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: #1e3a8a;
}

.official-period-info {
  text-align: center;
  font-size: 12px;
  color: #475569;
  margin: 0 0 16px 0;
}

/* TABEL FORMAT RESMI */
.official-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
  color: #0f172a;
  margin-bottom: 24px;
}

.official-table th, .official-table td {
  border: 1px solid #1e293b;
  padding: 6px 8px;
}

.official-table thead th {
  background-color: #f8fafc;
  font-weight: 700;
  color: #0f172a;
  text-transform: uppercase;
  font-size: 11px;
}

.official-table tbody tr:nth-child(even) {
  background-color: #fbfcfe;
}

.signature-line {
  display: inline-block;
  color: #64748b;
  font-size: 11px;
}

.ttd-box {
  display: inline-block;
  min-height: 22px;
  width: 100%;
}

/* STAMP TANDA TANGAN / PARAF DIGITAL */
.ttd-img-container {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  max-width: 100%;
}

.user-signature-img {
  max-height: 36px;
  max-width: 72px;
  object-fit: contain;
  display: block;
  margin: 0 auto;
  filter: contrast(1.15);
}

.user-signature-pelatih {
  max-height: 44px;
  max-width: 110px;
}

.ttd-stamp {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2px 6px;
  background: #ecfdf5;
  border: 1px solid #10b981;
  border-radius: 6px;
  line-height: 1.15;
}

.ttd-badge {
  font-size: 11px;
  font-weight: 700;
  color: #047857;
  letter-spacing: 0.3px;
  font-family: Arial, sans-serif;
}

.ttd-time {
  font-size: 9px;
  color: #059669;
  font-family: monospace;
}

@media print {
  .user-signature-img {
    max-height: 32px !important;
    max-width: 68px !important;
    filter: grayscale(100%) contrast(1.3) !important;
  }
  .user-signature-pelatih {
    max-height: 40px !important;
    max-width: 100px !important;
  }
  .ttd-stamp {
    background: transparent !important;
    border: 1px solid #000 !important;
  }
  .ttd-badge, .ttd-time {
    color: #000 !important;
  }
}

/* BLOK TANDA TANGAN */
.official-signature-block {
  margin-top: 24px;
  page-break-inside: avoid;
}

.sig-row-2 {
  display: flex;
  justify-content: space-around;
  text-align: center;
}

.sig-box {
  width: 260px;
}

.sig-box-center {
  width: 280px;
  margin: 0 auto;
  text-align: center;
}

.sig-role {
  font-weight: 600;
  font-size: 12px;
  margin: 0 0 48px 0;
}

.sig-space {
  height: 48px;
}

.sig-name {
  font-weight: 700;
  font-size: 12.5px;
  margin: 0;
}

.sig-nip {
  font-size: 11.5px;
  color: #334155;
  margin: 2px 0 0 0;
}

/* ========================================================================= */
/* CSS KHUSUS PENCETAKAN (PRINT / PDF)                                       */
/* ========================================================================= */
@media print {
  /* Sembunyikan elemen UI website saat mencetak */
  .topbar, .topnav, .btn, .no-print, header, footer, #flashMessage, #topbarClockWidget {
    display: none !important;
  }

  body {
    background: white !important;
    color: black !important;
    font-family: "Times New Roman", Times, serif !important;
    font-size: 11pt !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .container {
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .official-print-document {
    max-width: 100% !important;
    margin: 0 !important;
  }

  .sheet-section {
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .official-table {
    border-collapse: collapse !important;
    width: 100% !important;
    font-size: 9.5pt !important;
  }

  .official-table th, .official-table td {
    border: 1px solid black !important;
    color: black !important;
    padding: 4px 6px !important;
  }

  .official-table thead th {
    background-color: #f0f0f0 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .kop-divider {
    border-bottom: 2px solid black !important;
  }

  .sig-role, .sig-name, .sig-nip {
    color: black !important;
  }

  .page-break {
    page-break-after: always !important;
    break-after: page !important;
  }

  @page {
    size: auto;
    margin: 12mm 10mm 12mm 10mm;
  }
}
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>

