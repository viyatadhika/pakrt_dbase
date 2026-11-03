<?php
session_start();
if (!isset($_SESSION['user'])) {
  header('Location: login.php');
  exit;
}
$user = $_SESSION['user'];
if (strtolower(trim((string)($user['role'] ?? ''))) !== 'admin') {
  http_response_code(403);
  exit('Akses ditolak. Halaman ini hanya untuk Admin.');
}
require_once 'config.php';
function h($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
  $old = trim((string)($_POST['nip_lama'] ?? ''));
  $nip = trim((string)($_POST['nip'] ?? ''));
  $nama = trim((string)($_POST['nama'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $role = trim((string)($_POST['role'] ?? ''));
  $p1 = (string)($_POST['password_baru'] ?? '');
  $p2 = (string)($_POST['konfirmasi_password'] ?? '');

  if ($old === '' || $nip === '' || $nama === '' || $role === '') {
    $msg = 'NIP, nama, dan role wajib diisi.';
    $msgType = 'error';
  } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = 'Format email tidak valid.';
    $msgType = 'error';
  } elseif ($p1 !== '' && strlen($p1) < 6) {
    $msg = 'Password baru minimal 6 karakter.';
    $msgType = 'error';
  } elseif ($p1 !== $p2) {
    $msg = 'Konfirmasi password tidak sama.';
    $msgType = 'error';
  } else {
    $cek = $conn->prepare('SELECT nip FROM users WHERE nip=? AND nip<>? LIMIT 1');
    if (!$cek) {
      $msg = 'Validasi NIP gagal.';
      $msgType = 'error';
    } else {
      $cek->bind_param('ss', $nip, $old);
      $cek->execute();
      $cek->store_result();
      if ($cek->num_rows > 0) {
        $msg = 'NIP sudah digunakan user lain.';
        $msgType = 'error';
        $cek->close();
      } else {
        $cek->close();
        if ($p1 !== '') {
          $hash = password_hash($p1, PASSWORD_DEFAULT);
          $u = $conn->prepare('UPDATE users SET nip=?,nama=?,email=?,phone=?,role=?,password=? WHERE nip=?');
          if ($u) $u->bind_param('sssssss', $nip, $nama, $email, $phone, $role, $hash, $old);
        } else {
          $u = $conn->prepare('UPDATE users SET nip=?,nama=?,email=?,phone=?,role=? WHERE nip=?');
          if ($u) $u->bind_param('ssssss', $nip, $nama, $email, $phone, $role, $old);
        }
        if (!$u) {
          $msg = 'Perubahan profil gagal diproses.';
          $msgType = 'error';
        } elseif ($u->execute()) {
          $msg = 'Profil ' . $nama . ' berhasil diperbarui.';
          $msgType = 'success';
          if ((string)($_SESSION['user']['nip'] ?? '') === $old) {
            $_SESSION['user']['nip'] = $nip;
            $_SESSION['user']['nama'] = $nama;
            $_SESSION['user']['role'] = $role;
          }
        } else {
          $msg = 'Profil gagal diperbarui.';
          $msgType = 'error';
        }
        if ($u) $u->close();
      }
    }
  }
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per = 15;
$offset = ($page - 1) * $per;

/* Query dibuat tanpa dynamic bind agar stabil di PHP 7/8. */
$where = '';
if ($q !== '') {
  $safeQ = $conn->real_escape_string($q);
  $like = "'%" . $safeQ . "%'";
  $where = " WHERE (nip LIKE " . $like . " OR nama LIKE " . $like . " OR email LIKE " . $like . ")";
}

$total = 0;
$countResult = $conn->query('SELECT COUNT(*) AS total FROM users' . $where);
if ($countResult) {
  $countRow = $countResult->fetch_assoc();
  $total = isset($countRow['total']) ? (int)$countRow['total'] : 0;
}
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) {
  $page = $pages;
  $offset = ($page - 1) * $per;
}

$rows = array();
$listSql = 'SELECT nip, nama, email, role, phone FROM users' . $where . ' ORDER BY nama ASC LIMIT ' . (int)$per . ' OFFSET ' . (int)$offset;
$listResult = $conn->query($listSql);
if ($listResult) {
  while ($r = $listResult->fetch_assoc()) {
    $rows[] = $r;
  }
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manajemen Profil</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      background: #f5f7fb;
      color: #172033;
      font-family: Arial, sans-serif
    }

    .head {
      position: sticky;
      top: 0;
      z-index: 10;
      background: #fff;
      border-bottom: 1px solid #e5e7eb
    }

    .headin {
      max-width: 1180px;
      margin: auto;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 12px
    }

    .back {
      width: 42px;
      height: 42px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      display: grid;
      place-items: center;
      color: #172033;
      text-decoration: none
    }

    .head h1 {
      font-size: 18px;
      margin: 0
    }

    .head p {
      font-size: 12px;
      color: #64748b;
      margin: 4px 0 0
    }

    .wrap {
      max-width: 1180px;
      margin: auto;
      padding: 22px 18px
    }

    .hero {
      background: linear-gradient(135deg, #172554, #1e3a8a);
      color: #fff;
      border-radius: 20px;
      padding: 22px;
      margin-bottom: 17px
    }

    .hero h2 {
      margin: 0 0 6px;
      font-size: 21px
    }

    .hero p {
      margin: 0;
      opacity: .8;
      font-size: 13px
    }

    .flash {
      padding: 13px 15px;
      border-radius: 13px;
      margin-bottom: 15px;
      font-size: 13px
    }

    .success {
      background: #ecfdf3;
      color: #166534
    }

    .error {
      background: #fff1f2;
      color: #b91c1c
    }

    .warn {
      background: #fffbeb;
      color: #92400e;
      padding: 12px 14px;
      border-radius: 13px;
      margin-bottom: 15px;
      font-size: 12px
    }

    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      overflow: hidden
    }

    .toolbar {
      padding: 16px;
      display: flex;
      justify-content: space-between;
      gap: 14px;
      align-items: center;
      border-bottom: 1px solid #e5e7eb
    }

    .toolbar strong {
      font-size: 14px
    }

    .search {
      display: flex;
      gap: 8px
    }

    .search input {
      width: 320px;
      max-width: 55vw;
      height: 40px;
      border: 1px solid #dbe2ea;
      border-radius: 11px;
      padding: 0 12px
    }

    .search button {
      border: 0;
      border-radius: 11px;
      background: #172554;
      color: #fff;
      padding: 0 14px
    }

    table {
      width: 100%;
      border-collapse: collapse
    }

    th,
    td {
      padding: 13px 15px;
      border-bottom: 1px solid #eef2f7;
      text-align: left;
      font-size: 12px
    }

    th {
      background: #f8fafc;
      color: #64748b;
      font-size: 10px;
      text-transform: uppercase
    }

    .name {
      font-weight: 700
    }

    .nip {
      color: #64748b;
      font-size: 11px;
      margin-top: 3px
    }

    .role {
      background: #f1f5f9;
      border-radius: 999px;
      padding: 5px 8px;
      font-size: 10px;
      font-weight: 700
    }

    .edit {
      border: 0;
      border-radius: 10px;
      padding: 9px 11px;
      background: #eff6ff;
      color: #1d4ed8;
      font-weight: 700;
      cursor: pointer
    }

    .pager {
      padding: 14px 16px;
      display: flex;
      gap: 6px;
      justify-content: flex-end
    }

    .pager a,
    .pager span {
      padding: 7px 10px;
      border: 1px solid #e5e7eb;
      border-radius: 9px;
      text-decoration: none;
      color: #475569;
      font-size: 11px
    }

    .pager .on {
      background: #172554;
      color: #fff
    }

    .modal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 50;
      background: #0008;
      align-items: center;
      justify-content: center;
      padding: 16px
    }

    .modal.show {
      display: flex
    }

    .box {
      background: #fff;
      width: min(450px, 100%);
      border-radius: 20px;
      overflow: hidden
    }

    .boxtop {
      padding: 17px 18px;
      border-bottom: 1px solid #e5e7eb;
      display: flex;
      justify-content: space-between
    }

    .boxtop h3 {
      margin: 0;
      font-size: 16px
    }

    .x {
      border: 0;
      background: #f1f5f9;
      width: 34px;
      height: 34px;
      border-radius: 9px
    }

    .body {
      padding: 18px
    }

    .target {
      padding: 12px;
      background: #f8fafc;
      border-radius: 12px;
      margin-bottom: 15px
    }

    .target b,
    .target small {
      display: block
    }

    .target small {
      color: #64748b;
      margin-top: 3px
    }

    label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      margin: 12px 0 6px
    }

    .field {
      position: relative
    }

    .field input {
      width: 100%;
      height: 44px;
      border: 1px solid #dbe2ea;
      border-radius: 11px;
      padding: 0 42px 0 12px
    }

    .eye {
      position: absolute;
      right: 5px;
      top: 5px;
      width: 34px;
      height: 34px;
      border: 0;
      background: transparent
    }

    .actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      margin-top: 18px
    }

    .btn {
      border: 0;
      border-radius: 10px;
      padding: 10px 14px;
      font-weight: 700
    }

    .cancel {
      background: #f1f5f9
    }

    .save {
      background: #172554;
      color: #fff
    }

    .empty {
      padding: 40px;
      text-align: center;
      color: #64748b
    }

    @media(max-width:700px) {
      .wrap {
        padding: 14px 11px
      }

      .toolbar {
        align-items: stretch;
        flex-direction: column
      }

      .search input {
        max-width: none;
        width: 100%
      }

      .search {
        width: 100%
      }

      .search button {
        height: 40px
      }

      table,
      thead,
      tbody,
      tr,
      th,
      td {
        display: block
      }

      thead {
        display: none
      }

      tr {
        padding: 14px;
        border-bottom: 1px solid #e5e7eb
      }

      td {
        border: 0;
        padding: 4px 0
      }

      td:last-child {
        padding-top: 10px
      }

      .edit {
        width: 100%
      }
    }

    /* Tema PAK RT */
    body {
      background: #fff
    }

    .headin,
    .wrap {
      max-width: none
    }

    .head h1 {
      color: #0369a1;
      font-weight: 800
    }

    .hero {
      background: #f0f9ff;
      color: #0c4a6e;
      border: 1px solid #e0f2fe;
      box-shadow: none
    }

    .hero p {
      color: #64748b;
      opacity: 1
    }

    .search button,
    .save,
    .pager .on {
      background: #0284c7
    }

    .profile-box {
      width: min(680px, 100%);
      max-height: 92vh;
      overflow-y: auto
    }

    .boxtop {
      align-items: center
    }

    .boxtop small {
      display: block;
      color: #64748b;
      margin-top: 4px;
      font-size: 11px
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0 12px
    }

    .form-grid .full {
      grid-column: 1/-1
    }

    .input,
    select.input {
      width: 100%;
      height: 44px;
      border: 1px solid #dbe2ea;
      border-radius: 11px;
      padding: 0 12px;
      background: #fff;
      color: #172033
    }

    .input:focus,
    .field input:focus {
      outline: none;
      border-color: #38bdf8;
      box-shadow: 0 0 0 3px rgba(56, 189, 248, .12)
    }

    .password-section {
      margin-top: 18px;
      padding: 14px;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      background: #f8fafc
    }

    .password-section strong {
      font-size: 12px;
      color: #334155
    }

    .password-section strong i {
      color: #d97706;
      margin-right: 6px
    }

    .password-section>p {
      margin: 5px 0 2px;
      color: #64748b;
      font-size: 11px
    }

    @media(max-width:700px) {
      .form-grid {
        grid-template-columns: 1fr
      }

      .form-grid .full {
        grid-column: auto
      }

      .profile-box {
        max-height: 94vh
      }
    }
  </style>
</head>

<body>
  <header class="head">
    <div class="headin"><a class="back" href="menu.php"><i class="fa-solid fa-arrow-left"></i></a>
      <div>
        <h1>Manajemen Profil</h1>
        <p>Khusus Administrator</p>
      </div>
    </div>
  </header>
  <main class="wrap">
    <section class="hero">
      <h2>Kelola Profil Pengguna</h2>
      <p>Admin dapat memperbarui data profil pengguna dan mereset password bila diperlukan. Total <?= number_format($total, 0, ',', '.') ?> user.</p>
    </section>
    <?php if ($msg): ?><div class="flash <?= h($msgType) ?>"><?= h($msg) ?></div><?php endif; ?>

    <section class="card">
      <div class="toolbar"><strong>Daftar Pengguna</strong>
        <form class="search" method="get"><input name="q" value="<?= h($q) ?>" placeholder="Cari NIP, nama atau email"><button><i class="fa-solid fa-magnifying-glass"></i> Cari</button></form>
      </div>
      <?php if ($rows): ?><table>
          <thead>
            <tr>
              <th>Pengguna</th>
              <th>Email</th>
              <th>Role</th>
              <th>Telepon</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody><?php foreach ($rows as $r): ?><tr>
                <td>
                  <div class="name"><?= h($r['nama']) ?></div>
                  <div class="nip">NIP <?= h($r['nip']) ?></div>
                </td>
                <td><?= h($r['email'] ?? '-') ?></td>
                <td><span class="role"><?= h($r['role'] ?? '-') ?></span></td>
                <td><?= h($r['phone'] ?? '-') ?></td>
                <td><button class="edit" type="button" onclick='openM(<?= json_encode((string)$r["nip"]) ?>,<?= json_encode((string)$r["nama"]) ?>,<?= json_encode((string)($r["email"] ?? "")) ?>,<?= json_encode((string)($r["role"] ?? "")) ?>,<?= json_encode((string)($r["phone"] ?? "")) ?>)'><i class="fa-solid fa-user-pen"></i> Edit Profil</button></td>
              </tr><?php endforeach; ?></tbody>
        </table><?php else: ?><div class="empty">User tidak ditemukan.</div><?php endif; ?>
      <?php if ($pages > 1): ?><div class="pager"><?php if ($page > 1): ?><a href="?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">&lsaquo;</a><?php endif; ?><?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?><?php if ($i === $page): ?><span class="on"><?= $i ?></span><?php else: ?><a href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?><?php if ($page < $pages): ?><a href="?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">&rsaquo;</a><?php endif; ?></div><?php endif; ?>
    </section>
  </main>
  <div class="modal" id="m">
    <div class="box profile-box">
      <div class="boxtop">
        <div>
          <h3>Edit Profil Pengguna</h3><small>Data akun & reset password</small>
        </div><button type="button" class="x" onclick="closeM()">×</button>
      </div>
      <form method="post" onsubmit="return checkP()">
        <div class="body">
          <input type="hidden" name="action" value="update_profile"><input type="hidden" name="nip_lama" id="nip_lama">
          <div class="target"><b id="nm">-</b><small id="np">-</small></div>
          <div class="form-grid">
            <div><label>NIP / Username</label><input class="input" id="nip" name="nip" required></div>
            <div><label>Nama Lengkap</label><input class="input" id="nama" name="nama" required></div>
            <div><label>Email</label><input class="input" type="email" id="email" name="email"></div>
            <div><label>Nomor Telepon</label><input class="input" id="phone" name="phone"></div>
            <div class="full"><label>Role</label><select class="input" id="role" name="role" required>
                <option value="admin">Admin</option>
                <option value="pimpinan">Pimpinan</option>
                <option value="koordinator">Koordinator</option>
                <option value="supervisor">Supervisor</option>
                <option value="admin_ob">Admin OB</option>
                <option value="ob">OB / Cleaning Service</option>
                <option value="petugas">Petugas</option>
                <option value="ksp">KSP</option>
                <option value="kasir">Kasir</option>
                <option value="rental">Rental</option>
              </select></div>
          </div>
          <div class="password-section"><strong><i class="fa-solid fa-key"></i> Reset Password</strong>
            <p>Kosongkan jika password tidak ingin diubah.</p>
            <div class="form-grid">
              <div><label>Password Baru</label>
                <div class="field"><input type="password" id="p1" name="password_baru" minlength="6"><button type="button" class="eye" onclick="eye('p1',this)"><i class="fa-regular fa-eye"></i></button></div>
              </div>
              <div><label>Konfirmasi Password</label>
                <div class="field"><input type="password" id="p2" name="konfirmasi_password" minlength="6"><button type="button" class="eye" onclick="eye('p2',this)"><i class="fa-regular fa-eye"></i></button></div>
              </div>
            </div>
          </div>
          <div class="actions"><button type="button" class="btn cancel" onclick="closeM()">Batal</button><button class="btn save"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button></div>
        </div>
      </form>
    </div>
  </div>
  <script>
    const m = document.getElementById('m');

    function openM(nip, nama, email, role, phone) {
      document.getElementById('nip_lama').value = nip || '';
      document.getElementById('nip').value = nip || '';
      document.getElementById('nama').value = nama || '';
      document.getElementById('email').value = email || '';
      document.getElementById('phone').value = phone || '';
      document.getElementById('role').value = role || 'petugas';
      document.getElementById('nm').textContent = nama || '-';
      document.getElementById('np').textContent = 'NIP ' + (nip || '-');
      document.getElementById('p1').value = '';
      document.getElementById('p2').value = '';
      m.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    function closeM() {
      m.classList.remove('show');
      document.body.style.overflow = '';
    }

    function eye(id, b) {
      const i = document.getElementById(id);
      i.type = i.type === 'password' ? 'text' : 'password';
      b.firstElementChild.className = i.type === 'password' ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
    }

    function checkP() {
      if (document.getElementById('p1').value !== document.getElementById('p2').value) {
        alert('Konfirmasi password tidak sama.');
        return false;
      }
      return confirm('Simpan perubahan profil user ini?');
    }
    m.onclick = e => {
      if (e.target === m) closeM()
    };
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeM()
    });
  </script>
</body>

</html>