<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$tab = $_GET['tab'] ?? 'all';
$mode = $_GET['mode'] ?? 'cetak';

$pertemuan = get_pertemuan_pramuka();
$pelatihData = get_presensi_pelatih_data();
$siswaGroups = get_siswa_pramuka_grouped();

// Ambil matriks presensi & sinkronkan pertemuan_ke jika belum terisi
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
        if (!$pKe && !empty($r['work_date'])) {
            if ($r['work_date'] <= '2026-11-08') $pKe = 1;
            elseif ($r['work_date'] <= '2026-11-15') $pKe = 2;
            elseif ($r['work_date'] <= '2026-11-22') $pKe = 3;
            else $pKe = 4;
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

$filename = 'TARIKAN_DATA_PRESENSI_PRAMUKA_MAN4BANJAR_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Presensi Pramuka</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
echo '<body style="font-family: Arial, sans-serif;">';

// Style
echo '<style>
table { border-collapse: collapse; margin-bottom: 30px; font-size: 11pt; }
th { background-color: #e2e8f0; border: 1px solid #000; font-weight: bold; text-align: center; vertical-align: middle; padding: 6px; }
td { border: 1px solid #000; padding: 5px 8px; vertical-align: middle; }
.header-title { font-size: 14pt; font-weight: bold; text-align: center; }
.header-subtitle { font-size: 12pt; font-weight: bold; text-align: center; }
.sig-title { font-size: 11pt; font-weight: bold; text-align: center; }
.sig-name { font-size: 11pt; font-weight: bold; text-decoration: underline; text-align: center; }
.sig-nip { font-size: 10pt; text-align: center; }
</style>';

// 1. PELATIH
if ($tab === 'all' || $tab === 'pelatih') {
    foreach ($pelatihData as $pNama => $rows) {
        echo '<table>';
        echo '<tr><td colspan="5" class="header-title">PRESENSI PELATIH EKSTRAKURIKULER</td></tr>';
        echo '<tr><td colspan="5" class="header-subtitle">PRAMUKA MAN 4 BANJAR</td></tr>';
        echo '<tr><td colspan="5" style="text-align: center; font-size: 10pt;">Bulan: November 2026</td></tr>';
        echo '<tr><td colspan="5" style="height: 15px; border: none;"></td></tr>';

        echo '<tr>
                <th style="width: 45px;">No.</th>
                <th style="width: 220px;">Nama Pelatih</th>
                <th style="width: 200px;">Hari, Tanggal</th>
                <th style="width: 320px;">Materi</th>
                <th style="width: 130px;">Tanda Tangan</th>
              </tr>';

        $no = 1;
        foreach ($rows as $idx => $r) {
            $isPelatihHadir = ($r['status'] === 'hadir' && ($r['tanda_tangan'] === 'TTD' || !empty($r['tanda_tangan'])));
            $pelatihSigImage = (!empty($r['tanda_tangan']) && str_starts_with($r['tanda_tangan'], 'data:image/')) ? $r['tanda_tangan'] : '';
            if (!$isPelatihHadir || empty($pelatihSigImage)) {
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
            }
            if ($isPelatihHadir) {
                if (!empty($pelatihSigImage)) {
                    $ttdCell = '<td style="text-align: center; vertical-align: middle;"><img src="' . htmlspecialchars($pelatihSigImage) . '" height="36" style="max-width: 90px; height: 36px;"></td>';
                } else {
                    $ttdCell = '<td style="text-align: center; color: #15803d; font-weight: bold;">✓ TTD Digital</td>';
                }
            } else {
                $ttdText = ($mode === 'digital' ? '-' : ($no) . '. ............');
                $ttdCell = '<td style="text-align: center; color: #000;">' . htmlspecialchars($ttdText) . '</td>';
            }

            echo '<tr>
                    <td style="text-align: center;">' . ($no++) . '</td>
                    <td style="font-weight: bold;">' . htmlspecialchars($r['pelatih_name']) . '</td>
                    <td>' . htmlspecialchars($r['hari_tanggal']) . '</td>
                    <td>' . htmlspecialchars($r['materi'] ?: '-') . '</td>
                    ' . $ttdCell . '
                  </tr>';
        }

        // Tanda tangan
        echo '<tr><td colspan="5" style="height: 20px; border: none;"></td></tr>';
        echo '<tr><td colspan="5" style="text-align: center; border: none; font-weight: bold;">Mengetahui,</td></tr>';
        echo '<tr>
                <td colspan="2" style="border: none; text-align: center; font-weight: bold;">Pembina Pramuka Putera</td>
                <td style="border: none;"></td>
                <td colspan="2" style="border: none; text-align: center; font-weight: bold;">Pembina Pramuka Puteri</td>
              </tr>';
        echo '<tr><td colspan="5" style="height: 45px; border: none;"></td></tr>';
        echo '<tr>
                <td colspan="2" class="sig-name" style="border: none;"><u>Fadlan Hidayat, S.Pd.</u></td>
                <td style="border: none;"></td>
                <td colspan="2" class="sig-name" style="border: none;"><u>Elda Riana Yaslitha, S.Pd.</u></td>
              </tr>';
        echo '<tr>
                <td colspan="2" class="sig-nip" style="border: none;">NIP 198903082019031008</td>
                <td style="border: none;"></td>
                <td colspan="2" class="sig-nip" style="border: none;">NIP 199404082025212011</td>
              </tr>';
        echo '<tr><td colspan="5" style="height: 20px; border: none;"></td></tr>';
        echo '<tr>
                <td colspan="5" style="border: none; text-align: center; font-weight: bold;">Wakamad Kesiswaan</td>
              </tr>';
        echo '<tr><td colspan="5" style="height: 45px; border: none;"></td></tr>';
        echo '<tr>
                <td colspan="5" class="sig-name" style="border: none;"><u>Jiabus Sarury, S.Pd., M.A</u></td>
              </tr>';
        echo '<tr>
                <td colspan="5" class="sig-nip" style="border: none;">NIP 19930822201903100</td>
              </tr>';
        echo '<tr><td colspan="5" style="height: 30px; border: none;"></td></tr>';
        echo '</table><br><br>';
    }
}

// 2. SISWA
$activeTabs = [];
if ($tab === 'all') {
    $activeTabs = ['Kelas X Putri', 'Kelas X Putra', 'Kelas XI Putri', 'Kelas XI Putra', 'Kelas XII Putri', 'Kelas XII Putra'];
} elseif ($tab === 'X-PI') { $activeTabs = ['Kelas X Putri']; }
elseif ($tab === 'X-PA') { $activeTabs = ['Kelas X Putra']; }
elseif ($tab === 'XI-PI') { $activeTabs = ['Kelas XI Putri']; }
elseif ($tab === 'XI-PA') { $activeTabs = ['Kelas XI Putra']; }
elseif ($tab === 'XII-PI') { $activeTabs = ['Kelas XII Putri']; }
elseif ($tab === 'XII-PA') { $activeTabs = ['Kelas XII Putra']; }

foreach ($activeTabs as $groupKey) {
    $group = $siswaGroups[$groupKey] ?? null;
    if (!$group) continue;
    $students = $group['students'];

    echo '<table>';
    echo '<tr><td colspan="7" class="header-title">PRESENSI SISWA EKSTRAKURIKULER</td></tr>';
    echo '<tr><td colspan="7" class="header-subtitle">PRAMUKA MAN 4 BANJAR</td></tr>';
    echo '<tr><td colspan="7" style="text-align: center; font-size: 10pt;">Kelompok: ' . htmlspecialchars($groupKey) . ' (' . count($students) . ' Siswa) &bull; Periode: November 2026</td></tr>';
    echo '<tr><td colspan="7" style="height: 15px; border: none;"></td></tr>';

    echo '<tr>
            <th rowspan="2" style="width: 40px;">No.</th>
            <th rowspan="2" style="width: 260px;">NAMA SISWA</th>
            <th rowspan="2" style="width: 90px;">KELAS</th>
            <th colspan="4">PERTEMUAN</th>
          </tr>
          <tr>
            <th style="width: 80px;">1<br>TTD</th>
            <th style="width: 80px;">2<br>TTD</th>
            <th style="width: 80px;">3<br>TTD</th>
            <th style="width: 80px;">4<br>TTD</th>
          </tr>';

    foreach ($students as $i => $s) {
        echo '<tr>
                <td style="text-align: center;">' . ($i + 1) . '</td>
                <td>' . htmlspecialchars($s['name'] ?? $s['nama']) . '</td>
                <td style="text-align: center; font-weight: bold;">' . htmlspecialchars($s['kelas']) . '</td>';

        for ($p = 1; $p <= 4; $p++) {
            $sId = $s['id'] ?? 0;
            $uName = $s['username'] ?? '';
            $rec = $attendanceMatrix[$sId][$p] ?? ($uName && isset($attendanceMatrixByUsername[$uName][$p]) ? $attendanceMatrixByUsername[$uName][$p] : null);
            $isHadir = $rec && in_array($rec['status'], ['hadir', 'terlambat'], true);

            if ($isHadir) {
                $checkInTime = !empty($rec['check_in']) ? substr($rec['check_in'], 0, 5) : '';
                if (!empty($rec['signature']) && str_starts_with($rec['signature'], 'data:image/')) {
                    echo '<td style="text-align: center; vertical-align: middle;"><img src="' . htmlspecialchars($rec['signature']) . '" height="32" style="max-width: 65px; height: 32px;"><br><span style="font-size: 8pt; color: #475569;">' . htmlspecialchars($checkInTime) . '</span></td>';
                } else {
                    $ttdVal = '✓ TTD' . ($checkInTime ? ' (' . $checkInTime . ')' : '');
                    echo '<td style="text-align: center; color: #15803d; font-weight: bold;">' . htmlspecialchars($ttdVal) . '</td>';
                }
            } elseif ($rec && in_array($rec['status'], ['izin', 'sakit', 'alpha'], true)) {
                echo '<td style="text-align: center;">' . htmlspecialchars(ucfirst($rec['status'])) . '</td>';
            } else {
                echo '<td style="text-align: center;">' . ($mode === 'digital' ? '-' : '') . '</td>';
            }
        }
        echo '</tr>';
    }

    // Tanda tangan Pembina Putra & Putri
    echo '<tr><td colspan="7" style="height: 20px; border: none;"></td></tr>';
    echo '<tr><td colspan="7" style="text-align: center; border: none; font-weight: bold;">Mengetahui,</td></tr>';
    echo '<tr>
            <td colspan="3" style="border: none; text-align: center; font-weight: bold;">Pembina Pramuka Putra</td>
            <td style="border: none;"></td>
            <td colspan="3" style="border: none; text-align: center; font-weight: bold;">Pembina Pramuka Putri</td>
          </tr>';
    echo '<tr><td colspan="7" style="height: 45px; border: none;"></td></tr>';
    echo '<tr>
            <td colspan="3" class="sig-name" style="border: none;"><u>Fadlan Hidayat, S.Pd.</u></td>
            <td style="border: none;"></td>
            <td colspan="3" class="sig-name" style="border: none;"><u>Elda Riana Yaslitha, S.Pd.</u></td>
          </tr>';
    echo '<tr>
            <td colspan="3" class="sig-nip" style="border: none;">NIP 198710022019031003</td>
            <td style="border: none;"></td>
            <td colspan="3" class="sig-nip" style="border: none;">NIP 199404082025212011</td>
          </tr>';
    echo '<tr><td colspan="7" style="height: 30px; border: none;"></td></tr>';
    echo '</table><br><br>';
}

echo '</body></html>';
exit;

