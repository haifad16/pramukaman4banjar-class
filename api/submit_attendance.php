<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Metode tidak valid.');
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) throw new Exception('Token keamanan tidak valid.');

    $user = current_user();
    $type = ($_POST['type'] ?? '') === 'out' ? 'out' : 'in';
    $lat = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $accuracy = filter_var($_POST['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
    $clientDescriptor = parse_descriptor($_POST['descriptor'] ?? '');
    if ($lat === false || $lng === false || !$clientDescriptor) throw new Exception('Data lokasi atau wajah tidak lengkap.');

    $distance = haversine_m((float)$lat, (float)$lng, office_lat(), office_lng());
    if ($distance > office_radius()) {
        throw new Exception('Absensi ditolak. Anda berada sekitar ' . round($distance) . ' meter dari titik pangkalan MAN 4 Banjar.');
    }
    if (empty($user['face_descriptor'])) throw new Exception('Data wajah Anda belum terdaftar. Hubungi admin/pembina.');
    $stored = parse_descriptor($user['face_descriptor']);
    $faceDistance = descriptor_distance($stored, $clientDescriptor);
    if ($faceDistance === null || $faceDistance > 0.52) {
        throw new Exception('Verifikasi wajah gagal. Wajah tidak cukup cocok dengan data anggota.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $existing = today_attendance((int)$user['id']);
    $now = timezone_now();
    $date = $now->format('Y-m-d');
    $time = $now->format('H:i:s');

    // Tentukan pertemuan_ke berdasarkan jadwal pertemuan pramuka
    $pertemuanKe = 1;
    try {
        $ptmStmt = $pdo->prepare('SELECT pertemuan_ke FROM pertemuan_pramuka WHERE work_date = ? LIMIT 1');
        $ptmStmt->execute([$date]);
        $foundPtm = $ptmStmt->fetchColumn();
        if ($foundPtm) {
            $pertemuanKe = (int)$foundPtm;
        } else {
            // Pemetaan tanggal otomatis (uji coba minggu pertama hingga 8 Nov dihitung Pertemuan 1)
            if ($date <= '2026-11-08') $pertemuanKe = 1;
            elseif ($date <= '2026-11-15') $pertemuanKe = 2;
            elseif ($date <= '2026-11-22') $pertemuanKe = 3;
            else $pertemuanKe = 4;
        }
    } catch (Throwable $e) {
        $pertemuanKe = 1;
    }

    // Tangani gambar tanda tangan perorangan (Base64 PNG)
    $signatureData = trim($_POST['signature_image'] ?? '');
    $storedSignature = 'TTD';
    if (!empty($signatureData) && str_starts_with($signatureData, 'data:image/')) {
        $storedSignature = $signatureData;

        // Simpan juga salinan fisik file gambar ke uploads/signatures/
        try {
            $sigDir = __DIR__ . '/../uploads/signatures';
            if (!is_dir($sigDir)) @mkdir($sigDir, 0777, true);
            $parts = explode(',', $signatureData);
            $decoded = base64_decode(end($parts));
            if ($decoded) {
                $filename = 'sig_' . $user['id'] . '_ptm' . $pertemuanKe . '_' . date('Ymd_His') . '.png';
                @file_put_contents($sigDir . '/' . $filename, $decoded);
            }
        } catch (Throwable $e) {}
    }

    if ($type === 'in') {
        if ($existing) throw new Exception('Anda sudah melakukan absen masuk hari ini.');
        $late = $time > attendance_end() . ':00';
        $status = $late ? 'terlambat' : 'hadir';
        $stmt = $pdo->prepare('
            INSERT INTO attendances (user_id, work_date, check_in, in_latitude, in_longitude, in_accuracy, in_distance, in_face_distance, status, device, ip_address, pertemuan_ke, signature) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), status=VALUES(status), in_face_distance=VALUES(in_face_distance), pertemuan_ke=VALUES(pertemuan_ke), signature=VALUES(signature)
        ');
        $stmt->execute([
            $user['id'], 
            $date, 
            $time, 
            $lat, 
            $lng, 
            $accuracy, 
            $distance, 
            $faceDistance, 
            $status, 
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250), 
            $_SERVER['REMOTE_ADDR'] ?? null,
            $pertemuanKe,
            $storedSignature
        ]);

        // Jika pelatih melakukan presensi, otomatis update juga ke tabel presensi_pelatih dengan TTD asli
        if ($user['role'] === 'pelatih') {
            try {
                $ptmHari = 'Kamis, ' . $now->format('d F Y');
                // Cari nama pelatih di database yang cocok (misal 'Muhammad Haifad Ilham' vs 'M. Haifad Ilham')
                $pSearch = str_contains(strtolower($user['name']), 'haifad') ? '%haifad%' : (str_contains(strtolower($user['name']), 'aulia') ? '%aulia%' : '%' . $user['name'] . '%');
                $stmtCheckPel = $pdo->prepare('SELECT pelatih_name FROM presensi_pelatih WHERE LOWER(pelatih_name) LIKE ? LIMIT 1');
                $stmtCheckPel->execute([$pSearch]);
                $exactPelName = $stmtCheckPel->fetchColumn() ?: $user['name'];

                $stmtPel = $pdo->prepare('
                    INSERT INTO presensi_pelatih (pelatih_name, pertemuan_ke, hari_tanggal, work_date, materi, tanda_tangan, status)
                    VALUES (?, ?, ?, ?, "Latihan Ekstrakurikuler Pramuka Penegak", ?, "hadir")
                    ON DUPLICATE KEY UPDATE status="hadir", tanda_tangan=VALUES(tanda_tangan), work_date=VALUES(work_date)
                ');
                $stmtPel->execute([$exactPelName, $pertemuanKe, $ptmHari, $date, $storedSignature]);
            } catch (Throwable $e) {}
        }
    } else {
        if (!$existing) throw new Exception('Anda belum melakukan absen masuk hari ini.');
        if (!empty($existing['check_out'])) throw new Exception('Anda sudah melakukan absen pulang hari ini.');
        $stmt = $pdo->prepare('UPDATE attendances SET check_out = ?, out_latitude = ?, out_longitude = ?, out_accuracy = ?, out_distance = ?, out_face_distance = ?, pertemuan_ke = COALESCE(pertemuan_ke, ?), signature = COALESCE(?, signature) WHERE id = ?');
        $stmt->execute([$time, $lat, $lng, $accuracy, $distance, $faceDistance, $pertemuanKe, $storedSignature, $existing['id']]);
    }
    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'Absensi berhasil dicatat pada ' . $time . ' WITA.', 'distance' => round($distance), 'face_distance' => round($faceDistance, 3)]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
