<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$title = 'Data Anggota & Pengelola Pramuka';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? 'siswa');
        $kelas = trim($_POST['kelas'] ?? '');
        $gender = trim($_POST['gender'] ?? 'L');
        $tingkat = trim($_POST['tingkat'] ?? 'X');
        $username = trim($_POST['username'] ?? '');
        $phoneInput = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? 'pramuka123';

        try {
            if (!$name) throw new RuntimeException('Nama lengkap wajib diisi.');
            if (!$username) throw new RuntimeException('Username / ID anggota wajib diisi.');

            // Cek username unik
            $chk = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $chk->execute([$username]);
            if ($chk->fetch()) throw new RuntimeException('Username / ID sudah digunakan.');

            if (strlen($password) < 6) throw new RuntimeException('Password minimal 6 karakter.');

            $stmt = db()->prepare('
                INSERT INTO users (username, name, role, kelas, gender, tingkat, email, phone, password_hash, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ');
            $stmt->execute([
                $username,
                $name,
                $role,
                $kelas ?: null,
                $gender,
                $tingkat,
                $email ?: null,
                $phoneInput ?: null,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            flash('success', "Anggota {$name} ({$username}) berhasil ditambahkan.");
        } catch(Throwable $e) {
            flash('error', 'Gagal membuat akun: ' . $e->getMessage());
        }
        redirect('/admin/employees.php');
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? 'siswa');
        $kelas = trim($_POST['kelas'] ?? '');
        $gender = trim($_POST['gender'] ?? 'L');
        $tingkat = trim($_POST['tingkat'] ?? 'X');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        try {
            if (!$id || !$name || !$username) throw new RuntimeException('Nama dan Username wajib diisi.');
            
            // Cek username unik untuk id selain yang sedang diubah
            $chk = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $chk->execute([$username, $id]);
            if ($chk->fetch()) throw new RuntimeException('Username sudah digunakan oleh akun lain.');

            $stmt = db()->prepare('
                UPDATE users SET username=?, name=?, role=?, kelas=?, gender=?, tingkat=?, email=?, phone=?
                WHERE id=?
            ');
            $stmt->execute([$username, $name, $role, $kelas ?: null, $gender, $tingkat, $email ?: null, $phone ?: null, $id]);
            flash('success', 'Data anggota berhasil diperbarui.');
        } catch(Throwable $e) { 
            flash('error', 'Gagal memperbarui data: ' . $e->getMessage()); 
        }
        redirect('/admin/employees.php');
    }

    if ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $password = $_POST['new_password'] ?? 'pramuka123';
        try {
            if (!$id || strlen($password) < 6) throw new RuntimeException('Password baru minimal 6 karakter.');
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            flash('success', 'Password akun berhasil direset.');
        } catch(Throwable $e) { 
            flash('error', 'Gagal mengganti password: ' . $e->getMessage()); 
        }
        redirect('/admin/employees.php');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE users SET status = 1 - status WHERE id = ?')->execute([$id]);
        flash('success', 'Status aktifasi akun berhasil diubah.');
        redirect('/admin/employees.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)current_user()['id']) {
            flash('error', 'Tidak dapat menghapus akun Anda sendiri.');
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'Data akun berhasil dihapus.');
        }
        redirect('/admin/employees.php');
    }
}

// Filter Data
$roleFilter = $_GET['role'] ?? 'all';
$tingkatFilter = $_GET['tingkat'] ?? 'all';
$genderFilter = $_GET['gender'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$query = "SELECT id, username, name, role, kelas, gender, tingkat, email, phone, status, face_descriptor, face_registered_at, created_at FROM users WHERE 1=1";
$params = [];

if ($roleFilter !== 'all') {
    $query .= " AND role = ?";
    $params[] = $roleFilter;
}
if ($tingkatFilter !== 'all') {
    $query .= " AND tingkat = ?";
    $params[] = $tingkatFilter;
}
if ($genderFilter !== 'all') {
    $query .= " AND gender = ?";
    $params[] = $genderFilter;
}
if ($search !== '') {
    $query .= " AND (name LIKE ? OR username LIKE ? OR kelas LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY FIELD(role, 'admin', 'pembina', 'pelatih', 'siswa', 'employee'), tingkat ASC, gender ASC, name ASC";

$stmt = db()->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Statistik Cepat
$totalSiswa = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn();
$totalPutra = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'siswa' AND gender = 'L'")->fetchColumn();
$totalPutri = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'siswa' AND gender = 'P'")->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <div>
    <p class="eyebrow">
      <a href="<?= BASE_URL ?>/admin/index.php" style="color: inherit;">Panel Pengelola</a> &rsaquo; Manajemen Anggota
    </p>
    <h1>Data Anggota & Pengelola Pramuka</h1>
    <p class="muted">Daftar lengkap 113 siswa penegak (Kelas X, XI, XII), pelatih ekstrakurikuler, pembina pramuka, dan administrator.</p>
  </div>
  <div style="display: flex; gap: 8px;">
    <a href="<?= BASE_URL ?>/admin/tarikan_data.php" class="btn btn-primary btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
      <span>Buka Tarikan Data Presensi</span>
    </a>
  </div>
</section>

<!-- Form Pendaftaran Anggota Baru -->
<section class="card" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
    <div>
      <h2 style="font-size: 17px; margin: 0;">Tambah Anggota / Siswa Baru</h2>
      <p class="muted" style="margin: 2px 0 0 0; font-size: 13px;">Daftarkan siswa atau pelatih baru ke dalam sistem Gugus Depan MAN 4 Banjar</p>
    </div>
    <span class="badge hadir">Total: <?= $totalSiswa ?> Siswa (<?= $totalPutra ?> PA &bull; <?= $totalPutri ?> PI)</span>
  </div>

  <form method="post" class="form-grid cols-3" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <label>
      <span>Nama Lengkap *</span>
      <input required name="name" maxlength="150" placeholder="Contoh: Muhammad Ilham">
    </label>

    <label>
      <span>Peran / Role *</span>
      <select name="role" required>
        <option value="siswa" selected>Siswa (Anggota Pramuka)</option>
        <option value="pelatih">Pelatih Ekstrakurikuler</option>
        <option value="pembina">Pembina Pramuka</option>
        <option value="admin">Administrator Gudep</option>
      </select>
    </label>

    <label>
      <span>Username / ID Anggota *</span>
      <input required name="username" maxlength="50" placeholder="Contoh: X-PA-17 atau NIS">
    </label>

    <label>
      <span>Kelas (Misal: X – C)</span>
      <input name="kelas" maxlength="50" placeholder="Contoh: X – C, XI – A, XII – B">
    </label>

    <label>
      <span>Tingkatan</span>
      <select name="tingkat">
        <option value="X">Kelas X</option>
        <option value="XI">Kelas XI</option>
        <option value="XII">Kelas XII</option>
      </select>
    </label>

    <label>
      <span>Jenis Kelamin</span>
      <select name="gender">
        <option value="L">Laki-laki (Putra)</option>
        <option value="P">Perempuan (Putri)</option>
      </select>
    </label>

    <label>
      <span>No. WhatsApp (Opsional)</span>
      <input type="tel" name="phone" maxlength="30" placeholder="08xxxxxxxxxx">
    </label>

    <label>
      <span>Password Login</span>
      <input name="password" type="text" minlength="6" value="pramuka123" placeholder="Minimal 6 karakter">
    </label>

    <div style="display: flex; align-items: flex-end;">
      <button class="btn btn-primary" type="submit" style="width: 100%; height: 42px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
        <span>Simpan Anggota Baru</span>
      </button>
    </div>
  </form>
</section>

<!-- Filter & Search Toolbar -->
<section class="card" style="margin-bottom: 24px; padding: 14px 18px;">
  <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
      <select name="role" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--line); font-size: 13px;">
        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>Semua Peran</option>
        <option value="siswa" <?= $roleFilter === 'siswa' ? 'selected' : '' ?>>Siswa Saja</option>
        <option value="pelatih" <?= $roleFilter === 'pelatih' ? 'selected' : '' ?>>Pelatih Saja</option>
        <option value="pembina" <?= $roleFilter === 'pembina' ? 'selected' : '' ?>>Pembina Saja</option>
        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin Saja</option>
      </select>

      <select name="tingkat" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--line); font-size: 13px;">
        <option value="all" <?= $tingkatFilter === 'all' ? 'selected' : '' ?>>Semua Tingkat</option>
        <option value="X" <?= $tingkatFilter === 'X' ? 'selected' : '' ?>>Kelas X</option>
        <option value="XI" <?= $tingkatFilter === 'XI' ? 'selected' : '' ?>>Kelas XI</option>
        <option value="XII" <?= $tingkatFilter === 'XII' ? 'selected' : '' ?>>Kelas XII</option>
      </select>

      <select name="gender" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--line); font-size: 13px;">
        <option value="all" <?= $genderFilter === 'all' ? 'selected' : '' ?>>Semua Gender</option>
        <option value="L" <?= $genderFilter === 'L' ? 'selected' : '' ?>>Putra (L)</option>
        <option value="P" <?= $genderFilter === 'P' ? 'selected' : '' ?>>Putri (P)</option>
      </select>
    </div>

    <div style="display: flex; gap: 8px; align-items: center;">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama, kelas, ID..." style="padding: 6px 12px; border-radius: 6px; border: 1px solid var(--line); font-size: 13px; width: 200px;">
      <button type="submit" class="btn btn-sm btn-primary">Cari</button>
      <?php if ($roleFilter !== 'all' || $tingkatFilter !== 'all' || $genderFilter !== 'all' || $search !== ''): ?>
        <a href="<?= BASE_URL ?>/admin/employees.php" class="btn btn-sm btn-light">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</section>

<!-- Tabel Daftar Anggota -->
<section class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
    <div>
      <h2 style="font-size: 17px; margin: 0;">Daftar Anggota & Pengelola Terdaftar</h2>
      <p class="muted">Ditemukan: <?= count($rows) ?> data</p>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width: 45px; text-align: center;">No</th>
          <th>Nama Lengkap</th>
          <th>Username / ID</th>
          <th style="text-align: center;">Peran</th>
          <th style="text-align: center;">Kelas</th>
          <th style="text-align: center;">Gender</th>
          <th style="text-align: center;">Wajah Biometrik</th>
          <th style="text-align: center;">Status</th>
          <th style="text-align: right;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 28px; color: var(--muted);">Tidak ada data yang sesuai filter.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $idx => $r): 
            $initials = 'P';
            if (!empty($r['name'])) {
                $p = explode(' ', trim($r['name']));
                $initials = strtoupper(substr($p[0], 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : ''));
            }
          ?>
            <tr>
              <td style="text-align: center; color: var(--muted);"><?= $idx + 1 ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div class="user-avatar" style="width: 32px; height: 32px; font-size: 11px;"><?= e($initials) ?></div>
                  <div>
                    <strong style="color: var(--text-main);"><?= e($r['name']) ?></strong>
                    <div style="font-size: 11.5px; color: var(--muted);"><?= e($r['email'] ?: ($r['phone'] ? 'WA: ' . $r['phone'] : 'ID: ' . $r['username'])) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span style="font-family: var(--font-mono); font-weight: 600; color: var(--primary);">
                  <?= e($r['username']) ?>
                </span>
              </td>
              <td style="text-align: center;">
                <span class="status-pill <?= $r['role'] === 'admin' ? 'primary' : ($r['role'] === 'pembina' ? 'success' : ($r['role'] === 'pelatih' ? 'warning' : 'neutral')) ?>" style="font-size: 11px;">
                  <?= ucfirst($r['role']) ?>
                </span>
              </td>
              <td style="text-align: center; font-weight: 600;">
                <?= e($r['kelas'] ?: '—') ?>
              </td>
              <td style="text-align: center;">
                <?php if ($r['gender'] === 'L'): ?>
                  <span style="color: #2563eb; font-weight: 600;">Putra (L)</span>
                <?php elseif ($r['gender'] === 'P'): ?>
                  <span style="color: #db2777; font-weight: 600;">Putri (P)</span>
                <?php else: ?>
                  <span style="color: var(--muted);">—</span>
                <?php endif; ?>
              </td>
              <td style="text-align: center;">
                <?php if (!empty($r['face_descriptor'])): ?>
                  <div style="display: inline-flex; align-items: center; gap: 4px;">
                    <span class="badge hadir" style="font-size: 11px;">✓ Terdaftar</span>
                    <button type="button" class="btn btn-sm btn-light enroll" data-id="<?= e($r['id']) ?>" data-name="<?= e($r['name']) ?>" title="Rekam Ulang Wajah" style="padding: 2px 6px; font-size: 11px;">
                      🔄
                    </button>
                  </div>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-primary enroll" data-id="<?= e($r['id']) ?>" data-name="<?= e($r['name']) ?>" title="Daftarkan Biometrik Wajah" style="padding: 3px 8px; font-size: 11px; white-space: nowrap;">
                    📸 Rekam Wajah
                  </button>
                <?php endif; ?>
              </td>
              <td style="text-align: center;">
                <form method="post" style="display: inline;">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                  <button type="submit" class="status-pill <?= (int)$r['status'] === 1 ? 'success' : 'danger' ?>" style="cursor: pointer; border: none; font-size: 11px;" title="Klik untuk mengubah status">
                    <?= (int)$r['status'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                  </button>
                </form>
              </td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 4px; justify-content: flex-end;">
                  <button type="button" class="btn btn-sm btn-light" onclick='openEditModal(<?= json_encode($r) ?>)' title="Edit Profil">
                    ✏️
                  </button>
                  <button type="button" class="btn btn-sm btn-light" onclick="openResetPasswordModal(<?= e($r['id']) ?>, '<?= e(addslashes($r['name'])) ?>')" title="Reset Password">
                    🔑
                  </button>
                  <?php if ((int)$r['id'] !== (int)current_user()['id']): ?>
                    <form method="post" onsubmit="return confirm('Yakin ingin menghapus anggota ini?')" style="display: inline;">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                      <button type="submit" class="btn btn-sm btn-light" style="color: var(--danger);" title="Hapus Akun">
                        🗑️
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Modal Edit Anggota -->
<dialog id="editModal" style="border: none; border-radius: 12px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
  <form method="post" id="editForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" id="editId">

    <h3 style="margin-top: 0; font-size: 18px;">Edit Profil Anggota</h3>

    <div class="form-grid">
      <label>
        <span>Nama Lengkap</span>
        <input required name="name" id="editName">
      </label>

      <label>
        <span>Username / ID</span>
        <input required name="username" id="editUsername">
      </label>

      <div class="cols-2">
        <label>
          <span>Peran</span>
          <select name="role" id="editRole">
            <option value="siswa">Siswa</option>
            <option value="pelatih">Pelatih</option>
            <option value="pembina">Pembina</option>
            <option value="admin">Admin</option>
          </select>
        </label>
        <label>
          <span>Kelas</span>
          <input name="kelas" id="editKelas" placeholder="Misal: X – C">
        </label>
      </div>

      <div class="cols-2">
        <label>
          <span>Tingkatan</span>
          <select name="tingkat" id="editTingkat">
            <option value="X">Kelas X</option>
            <option value="XI">Kelas XI</option>
            <option value="XII">Kelas XII</option>
          </select>
        </label>
        <label>
          <span>Jenis Kelamin</span>
          <select name="gender" id="editGender">
            <option value="L">Laki-laki (Putra)</option>
            <option value="P">Perempuan (Putri)</option>
          </select>
        </label>
      </div>

      <label>
        <span>No. WhatsApp</span>
        <input type="tel" name="phone" id="editPhone">
      </label>

      <label>
        <span>Email</span>
        <input type="email" name="email" id="editEmail">
      </label>
    </div>

    <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 18px;">
      <button type="button" class="btn btn-light" onclick="document.getElementById('editModal').close()">Batal</button>
      <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </div>
  </form>
</dialog>

<!-- Modal Reset Password -->
<dialog id="resetPasswordModal" style="border: none; border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="id" id="resetUserId">

    <h3 style="margin-top: 0; font-size: 18px;">Reset Password</h3>
    <p class="muted" id="resetUserNameText" style="font-size: 13px;"></p>

    <label style="display: block; margin-top: 12px;">
      <span>Password Baru (Min. 6 Karakter)</span>
      <input required type="text" name="new_password" value="pramuka123" style="width: 100%; margin-top: 6px;">
    </label>

    <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 18px;">
      <button type="button" class="btn btn-light" onclick="document.getElementById('resetPasswordModal').close()">Batal</button>
      <button type="submit" class="btn btn-primary">Reset Password</button>
    </div>
  </form>
</dialog>

<script>
function openEditModal(user) {
  document.getElementById('editId').value = user.id;
  document.getElementById('editName').value = user.name || '';
  document.getElementById('editUsername').value = user.username || '';
  document.getElementById('editRole').value = user.role || 'siswa';
  document.getElementById('editKelas').value = user.kelas || '';
  document.getElementById('editTingkat').value = user.tingkat || 'X';
  document.getElementById('editGender').value = user.gender || 'L';
  document.getElementById('editPhone').value = user.phone || '';
  document.getElementById('editEmail').value = user.email || '';
  document.getElementById('editModal').showModal();
}

function openResetPasswordModal(id, name) {
  document.getElementById('resetUserId').value = id;
  document.getElementById('resetUserNameText').textContent = 'Reset password untuk ' + name;
  document.getElementById('resetPasswordModal').showModal();
}
</script>

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

<?php require __DIR__ . '/../includes/footer.php'; ?>
