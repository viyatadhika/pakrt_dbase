<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';
date_default_timezone_set('Asia/Jakarta');

$currentUserRoleRaw = $_SESSION['user']['role'] ?? ($_SESSION['user']['level'] ?? '');
$currentUserRole = strtolower(trim(str_replace(['_', '-'], ' ', (string)$currentUserRoleRaw)));
$currentUserRole = preg_replace('/\s+/', ' ', $currentUserRole);
$canManageSchedule = in_array($currentUserRole, ['admin', 'administrator', 'admin ob', 'supervisor', 'spv'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canManageSchedule) {
    http_response_code(403);
    $message = 'Akses ditolak. Akun OB hanya dapat melihat jadwal.';
    $type = 'error';
    $_POST = [];
}

/**
 * @param mixed $v
 * @return string
 */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * @param mixed $r
 * @return string
 */
function normRole($r)
{
    $r = strtolower(trim(str_replace(['_', '-'], ' ', (string)$r)));
    $r = preg_replace('/\s+/', ' ', $r);
    if (in_array($r, ['ob', 'office boy', 'officeboy', 'cleaningservice', 'cs'], true)) return 'ob';
    if (in_array($r, ['supervisor', 'spv'], true)) return 'supervisor';
    return $r;
}

/** @param mixed $value @return string */
function normName($value)
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/u', '', $value);
    return $value;
}

/**
 * @param mixed $role
 * @param mixed $date
 * @param mixed $shift
 * @return array<int,string>|null
 */
function shiftTimes($role, $date, $shift)
{
    $n = (int)date('N', strtotime((string)$date));
    $role = normRole($role);
    $shift = (string)$shift;
    if ($shift === 'L') return ['00:00:00', '00:00:00'];
    if ($role === 'ob') {
        $m = ['1' => [$n === 5 ? '07:00:00' : '07:00:00', $n === 5 ? '16:00:00' : '15:30:00'], '2' => ['16:00:00', '23:00:00'], '3' => ['23:00:00', '07:00:00']];
    } elseif ($role === 'supervisor') {
        $m = ['1' => [$n === 5 ? '08:00:00' : '08:00:00', $n === 5 ? '17:00:00' : '16:30:00'], '2' => ['16:30:00', '23:00:00']];
    } else return null;
    return isset($m[$shift]) ? $m[$shift] : null;
}

/**
 * @param array<int|string,array<string,string>> $data
 * @param string $month
 * @param int $days
 * @return bool
 */
function validateFiveTwo($data, $month, $days)
{
    foreach ($data as $uid => $dates) {
        $weeks = [];
        for ($d = 1; $d <= $days; $d++) {
            $date = $month . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
            $n = (int)date('N', strtotime($date));
            $monday = date('Y-m-d', strtotime($date . ' -' . ($n - 1) . ' days'));
            $sunday = date('Y-m-d', strtotime($monday . ' +6 days'));
            if (substr($monday, 0, 7) !== $month || substr($sunday, 0, 7) !== $month) continue;
            if (!isset($weeks[$monday])) $weeks[$monday] = ['work' => 0, 'off' => 0];
            $v = isset($dates[$date]) ? (string)$dates[$date] : '';
            if ($v === 'L') $weeks[$monday]['off']++;
            elseif ($v !== '') $weeks[$monday]['work']++;
        }
        foreach ($weeks as $week => $count) {
            if ($count['work'] !== 5 || $count['off'] !== 2) return false;
        }
    }
    return true;
}

$conn->query("CREATE TABLE IF NOT EXISTS jadwal_shift_petugas (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,tanggal DATE NOT NULL,role_petugas VARCHAR(50) NOT NULL,shift VARCHAR(20) NOT NULL,jam_masuk TIME NOT NULL,jam_pulang TIME NOT NULL,status ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY unik_user_tanggal(user_id,tanggal),KEY idx_tanggal(tanggal)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS jadwal_area_petugas (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,area_spv VARCHAR(100) NOT NULL DEFAULT 'Belum Ditentukan',supervisor_id INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY unik_user_area(user_id),KEY idx_area(area_spv),KEY idx_supervisor(supervisor_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS jadwal_urutan_petugas (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,role_petugas VARCHAR(50) NOT NULL,urutan INT NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY unik_user_role(user_id,role_petugas),KEY idx_role_urutan(role_petugas,urutan)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$roleFilter = isset($_GET['role']) ? normRole($_GET['role']) : 'ob';
if (!in_array($roleFilter, ['ob', 'supervisor'], true)) $roleFilter = 'ob';
$first = $month . '-01';
$days = (int)date('t', strtotime($first));
$message = '';
$type = 'success';

/* Simpan susunan nama hasil drag pada tabel desktop. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_user_order') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$canManageSchedule) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $orderRole = normRole($_POST['role'] ?? 'ob');
    $orderData = json_decode((string)($_POST['order_json'] ?? ''), true);
    if (!in_array($orderRole, ['ob', 'supervisor'], true) || !is_array($orderData)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Data urutan tidak valid.']);
        exit;
    }

    $validIds = [];
    $uqOrder = $conn->query("SELECT id,role FROM users");
    if ($uqOrder) {
        while ($rowOrder = $uqOrder->fetch_assoc()) {
            if (normRole($rowOrder['role'] ?? '') === $orderRole) $validIds[(int)$rowOrder['id']] = true;
        }
    }

    $cleanOrder = [];
    foreach ($orderData as $uidOrder) {
        $uidOrder = (int)$uidOrder;
        if ($uidOrder > 0 && isset($validIds[$uidOrder]) && !isset($cleanOrder[$uidOrder])) {
            $cleanOrder[$uidOrder] = count($cleanOrder) + 1;
        }
    }

    $conn->begin_transaction();
    try {
        $delOrder = $conn->prepare("DELETE FROM jadwal_urutan_petugas WHERE role_petugas=?");
        $delOrder->bind_param('s', $orderRole);
        $delOrder->execute();
        $delOrder->close();

        $insOrder = $conn->prepare("INSERT INTO jadwal_urutan_petugas(user_id,role_petugas,urutan) VALUES(?,?,?)");
        foreach ($cleanOrder as $uidOrder => $positionOrder) {
            $insOrder->bind_param('isi', $uidOrder, $orderRole, $positionOrder);
            if (!$insOrder->execute()) throw new Exception($insOrder->error);
        }
        $insOrder->close();
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Susunan nama berhasil disimpan.']);
    } catch (Throwable $orderError) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan susunan: ' . $orderError->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_month') {
    $deleteMonth = isset($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month']) ? $_POST['month'] : date('Y-m');
    $deleteRole = normRole($_POST['role'] ?? 'ob');
    if (!in_array($deleteRole, ['ob', 'supervisor'], true)) $deleteRole = 'ob';
    $deleteFirst = $deleteMonth . '-01';
    $deleteLast = $deleteMonth . '-' . date('t', strtotime($deleteFirst));
    $delMonth = $conn->prepare("DELETE FROM jadwal_shift_petugas WHERE role_petugas=? AND tanggal BETWEEN ? AND ?");
    if ($delMonth) {
        $delMonth->bind_param('sss', $deleteRole, $deleteFirst, $deleteLast);
        $delMonth->execute();
        $delMonth->close();
        header('Location: jadwal_shift.php?month=' . urlencode($deleteMonth) . '&role=' . urlencode($deleteRole) . '&deleted=1');
        exit;
    }
    $message = 'Gagal menghapus jadwal bulanan: ' . $conn->error;
    $type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_excel') {
    $payload = json_decode((string)($_POST['import_json'] ?? ''), true);
    if (!is_array($payload) || !$payload) {
        $message = 'Data preview Excel kosong atau tidak valid.';
        $type = 'error';
    } else {
        /* Peta pengguna dibuat untuk dua role agar jenis file dapat dikenali otomatis. */
        $userMap = ['ob' => [], 'supervisor' => []];
        $uq = $conn->query("SELECT id,nama,role FROM users");
        if ($uq) {
            while ($ur = $uq->fetch_assoc()) {
                $mappedRole = normRole($ur['role'] ?? '');
                $nameKey = normName($ur['nama'] ?? '');
                if ($nameKey !== '') {
                    foreach (['ob', 'supervisor'] as $targetRole) {
                        if (!isset($userMap[$targetRole][$nameKey]) || $mappedRole === $targetRole) {
                            $userMap[$targetRole][$nameKey] = ['id' => (int)$ur['id'], 'nama' => (string)$ur['nama']];
                        }
                    }
                }
            }
        }

        $conn->begin_transaction();
        try {
            $up = $conn->prepare("INSERT INTO jadwal_shift_petugas(user_id,tanggal,role_petugas,shift,jam_masuk,jam_pulang,status) VALUES(?,?,?,?,?,?,'Aktif') ON DUPLICATE KEY UPDATE role_petugas=VALUES(role_petugas),shift=VALUES(shift),jam_masuk=VALUES(jam_masuk),jam_pulang=VALUES(jam_pulang),status='Aktif'");
            if (!$up) throw new Exception('Query impor gagal: ' . $conn->error);

            $savedCount = 0;
            $skipped = 0;
            $importMonths = [];
            $importRoles = [];

            foreach ($payload as $item) {
                if (!is_array($item)) continue;

                $nama = trim((string)($item['nama'] ?? ''));
                $date = trim((string)($item['tanggal'] ?? ''));
                $itemRole = normRole($item['role'] ?? ($_POST['role'] ?? 'ob'));
                $s = strtoupper(trim((string)($item['shift'] ?? '')));

                if (!in_array($itemRole, ['ob', 'supervisor'], true)) {
                    $skipped++;
                    continue;
                }
                if (strpos($s, 'SHIFT') === 0) $s = preg_replace('/\D/', '', $s);
                if (in_array($s, ['LIBUR', 'OFF', 'L'], true)) $s = 'L';

                $key = normName($nama);
                if ($nama === '' || !isset($userMap[$itemRole][$key]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $skipped++;
                    continue;
                }

                $times = shiftTimes($itemRole, $date, $s);
                if (!$times) {
                    $skipped++;
                    continue;
                }

                $uid = $userMap[$itemRole][$key]['id'];
                $label = $s === 'L' ? 'Libur' : 'Shift ' . $s;
                $up->bind_param('isssss', $uid, $date, $itemRole, $label, $times[0], $times[1]);
                if (!$up->execute()) throw new Exception($up->error);

                $savedCount++;
                $importMonths[substr($date, 0, 7)] = true;
                $importRoles[$itemRole] = true;
            }

            $up->close();
            if ($savedCount < 1) throw new Exception('Tidak ada data yang tersimpan. Periksa kecocokan nama petugas, role, dan format shift.');
            $conn->commit();

            $redirectMonth = array_key_first($importMonths) ?: date('Y-m');
            $redirectRole = array_key_first($importRoles) ?: 'ob';
            $monthCount = count($importMonths);
            $_SESSION['jadwal_import_message'] = $savedCount . ' data jadwal berhasil diimpor dari ' . $monthCount . ' bulan.' . ($skipped ? ' ' . $skipped . ' data dilewati karena nama, role, tanggal, atau shift tidak cocok.' : '');
            header('Location: jadwal_shift.php?month=' . urlencode($redirectMonth) . '&role=' . urlencode($redirectRole) . '&imported=1');
            exit;
        } catch (Throwable $ex) {
            $conn->rollback();
            $message = 'Gagal menyimpan hasil impor: ' . $ex->getMessage();
            $type = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_matrix') {
    $month = isset($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month']) ? $_POST['month'] : date('Y-m');
    $roleFilter = normRole($_POST['role'] ?? 'ob');
    if (!in_array($roleFilter, ['ob', 'supervisor'], true)) $roleFilter = 'ob';
    /*
   * Matrix dikirim sebagai satu JSON agar tidak terpotong oleh batas
   * max_input_vars PHP ketika jumlah petugas x tanggal melebihi 1000 input.
   */
    $data = [];
    $matrixJson = (string)($_POST['matrix_json'] ?? '');
    if ($matrixJson !== '') {
        $decoded = json_decode($matrixJson, true);
        if (is_array($decoded)) $data = $decoded;
    }
    if (!$data && isset($_POST['shift']) && is_array($_POST['shift'])) $data = $_POST['shift'];

    $days = (int)date('t', strtotime($month . '-01'));
    $conn->begin_transaction();
    try {
        $del = $conn->prepare("DELETE FROM jadwal_shift_petugas WHERE user_id=? AND tanggal=?");
        $up = $conn->prepare("INSERT INTO jadwal_shift_petugas(user_id,tanggal,role_petugas,shift,jam_masuk,jam_pulang,status) VALUES(?,?,?,?,?,?,'Aktif') ON DUPLICATE KEY UPDATE role_petugas=VALUES(role_petugas),shift=VALUES(shift),jam_masuk=VALUES(jam_masuk),jam_pulang=VALUES(jam_pulang),status='Aktif'");
        if (!$del || !$up) throw new Exception('Query penyimpanan tidak dapat disiapkan: ' . $conn->error);

        $savedCount = 0;
        $deletedCount = 0;
        foreach ($data as $uid => $dates) {
            $uid = (int)$uid;
            if ($uid < 1 || !is_array($dates)) continue;
            foreach ($dates as $date => $s) {
                $s = strtoupper(trim((string)$s));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) continue;
                if (substr((string)$date, 0, 7) !== $month) continue;
                if ($s === 'LIBUR' || $s === 'OFF') $s = 'L';
                if (strpos($s, 'SHIFT') === 0) $s = (string)preg_replace('/\D/', '', $s);

                if ($s === '') {
                    $del->bind_param('is', $uid, $date);
                    if (!$del->execute()) throw new Exception('Gagal menghapus jadwal: ' . $del->error);
                    $deletedCount += max(0, (int)$del->affected_rows);
                    continue;
                }

                $times = shiftTimes($roleFilter, $date, $s);
                if (!$times) throw new Exception('Shift tidak valid pada tanggal ' . $date . '.');
                $label = $s === 'L' ? 'Libur' : 'Shift ' . $s;
                $up->bind_param('isssss', $uid, $date, $roleFilter, $label, $times[0], $times[1]);
                if (!$up->execute()) throw new Exception('Gagal menyimpan jadwal tanggal ' . $date . ': ' . $up->error);
                $savedCount++;
            }
        }
        $del->close();
        $up->close();
        $conn->commit();
        $_SESSION['jadwal_save_message'] = 'Jadwal berhasil disimpan. ' . $savedCount . ' sel diproses' . ($deletedCount > 0 ? ' dan ' . $deletedCount . ' jadwal dikosongkan.' : '.');
        header('Location: jadwal_shift.php?month=' . urlencode($month) . '&role=' . urlencode($roleFilter) . '&saved=1');
        exit;
    } catch (Throwable $ex) {
        $conn->rollback();
        $message = 'Gagal menyimpan: ' . $ex->getMessage();
        $type = 'error';
    }
}
if (isset($_GET['saved'])) {
    $message = (string)($_SESSION['jadwal_save_message'] ?? 'Jadwal satu bulan berhasil disimpan.');
    unset($_SESSION['jadwal_save_message']);
}
if (isset($_GET['deleted'])) $message = 'Seluruh jadwal pada bulan dan role tersebut berhasil dihapus.';
if (isset($_GET['imported'])) {
    $message = (string)($_SESSION['jadwal_import_message'] ?? 'Data Excel berhasil diimpor.');
    unset($_SESSION['jadwal_import_message']);
}

$supervisors = [];
$sq = $conn->query("SELECT id,nama,role FROM users ORDER BY nama");
if ($sq) {
    while ($sr = $sq->fetch_assoc()) {
        if (normRole($sr['role'] ?? '') === 'supervisor') $supervisors[] = $sr;
    }
}

$users = [];
$q = $conn->query("SELECT u.id,u.nama,u.role,COALESCE(a.area_spv,'Belum Ditentukan') area_spv,a.supervisor_id,COALESCE(s.nama,'Belum Ada SPV') supervisor_nama FROM users u LEFT JOIN jadwal_area_petugas a ON a.user_id=u.id LEFT JOIN users s ON s.id=a.supervisor_id ORDER BY area_spv,supervisor_nama,u.nama");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        if (normRole($r['role'] ?? '') === $roleFilter) $users[] = $r;
    }
}
/* Susunan Supervisor dipaksa mengikuti urutan baris pada file Excel. */
if ($roleFilter === 'supervisor' && !empty($users)) {
    $excelOrder = [
        'saiful',
        'veli yandra',
        'sumantri',
        'cecep adang jaelani',
        'engkos kosasih',
        'anwar sadat',
        'sarhindi',
        'saepuloh',
        'ahmad saripudin',
        'latip'
    ];

    $cleanSupervisorName = function ($value) {
        $value = mb_strtolower(trim((string)$value), 'UTF-8');
        $value = str_replace(['syarifudin', 'sarifudin'], 'saripudin', $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim(preg_replace('/\s+/u', ' ', $value));
    };

    $orderedUsers = [];
    $usedIds = [];
    foreach ($excelOrder as $wantedName) {
        $wanted = $cleanSupervisorName($wantedName);
        foreach ($users as $user) {
            $userId = (int)($user['id'] ?? 0);
            if (isset($usedIds[$userId])) continue;
            $actual = $cleanSupervisorName($user['nama'] ?? '');
            if ($actual === $wanted || strpos(' ' . $actual . ' ', ' ' . $wanted . ' ') !== false || strpos(' ' . $wanted . ' ', ' ' . $actual . ' ') !== false) {
                $orderedUsers[] = $user;
                $usedIds[$userId] = true;
                break;
            }
        }
    }

    /* Nama tambahan yang tidak ada di Excel tetap ditampilkan paling bawah. */
    foreach ($users as $user) {
        $userId = (int)($user['id'] ?? 0);
        if (!isset($usedIds[$userId])) $orderedUsers[] = $user;
    }
    $users = $orderedUsers;
}

/* Terapkan urutan manual. Untuk OB, urutan tetap dibatasi di dalam kelompok gedung/SPV. */
$manualOrder = [];
$orderStmt = $conn->prepare("SELECT user_id,urutan FROM jadwal_urutan_petugas WHERE role_petugas=? ORDER BY urutan");
if ($orderStmt) {
    $orderStmt->bind_param('s', $roleFilter);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    while ($orderRow = $orderResult->fetch_assoc()) $manualOrder[(int)$orderRow['user_id']] = (int)$orderRow['urutan'];
    $orderStmt->close();
}
if ($manualOrder && $users) {
    $originalPosition = [];
    foreach ($users as $position => $orderUser) $originalPosition[(int)$orderUser['id']] = $position;
    usort($users, function ($a, $b) use ($manualOrder, $originalPosition, $roleFilter) {
        if ($roleFilter === 'ob') {
            $groupA = (string)$a['area_spv'] . '|' . (string)$a['supervisor_nama'];
            $groupB = (string)$b['area_spv'] . '|' . (string)$b['supervisor_nama'];
            if ($groupA !== $groupB) return $originalPosition[(int)$a['id']] <=> $originalPosition[(int)$b['id']];
        }
        $posA = $manualOrder[(int)$a['id']] ?? (100000 + $originalPosition[(int)$a['id']]);
        $posB = $manualOrder[(int)$b['id']] ?? (100000 + $originalPosition[(int)$b['id']]);
        return $posA <=> $posB;
    });
}
$importUsers = [];
$iuq = $conn->query("SELECT nama,role FROM users ORDER BY nama");
if ($iuq) {
    while ($iur = $iuq->fetch_assoc()) {
        $ir = normRole($iur['role'] ?? '');
        if (in_array($ir, ['ob', 'supervisor'], true)) $importUsers[] = ['nama' => (string)$iur['nama'], 'role' => $ir];
    }
}
$schedules = [];
$stmt = $conn->prepare("SELECT user_id,tanggal,shift FROM jadwal_shift_petugas WHERE tanggal BETWEEN ? AND ?");
$last = $month . '-' . $days;
$stmt->bind_param('ss', $first, $last);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rawShift = (string)$r['shift'];
    $schedules[(int)$r['user_id']][$r['tanggal']] = ($rawShift === 'Libur' ? 'L' : preg_replace('/\D/', '', $rawShift));
}
$stmt->close();
$title = 'Penjadwalan Shift';
include 'header.php';
$prev = date('Y-m', strtotime($first . ' -1 month'));
$next = date('Y-m', strtotime($first . ' +1 month'));
$months = ['01' => 'JANUARI', '02' => 'FEBRUARI', '03' => 'MARET', '04' => 'APRIL', '05' => 'MEI', '06' => 'JUNI', '07' => 'JULI', '08' => 'AGUSTUS', '09' => 'SEPTEMBER', '10' => 'OKTOBER', '11' => 'NOVEMBER', '12' => 'DESEMBER'];
?>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    * {
        box-sizing: border-box
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #fff;
        color: #0f172a;
        overflow-x: hidden
    }

    .page {
        padding: 0 16px 100px;
        max-width: 1900px;
        margin: auto
    }

    .header-container {
        position: sticky;
        top: 0;
        z-index: 60;
        background: rgba(255, 255, 255, .92);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid #e0f2fe
    }

    .head {
        padding: 16px 4px;
        display: flex;
        align-items: center;
        justify-content: space-between
    }

    .head-left {
        display: flex;
        align-items: center;
        gap: 14px
    }

    .back {
        width: 40px;
        height: 40px;
        border: 0;
        border-radius: 999px;
        background: #f0f9ff;
        color: #0284c7
    }

    .title {
        font-size: 18px;
        font-weight: 800;
        color: #0284c7
    }

    .sub {
        font-size: 12px;
        color: #64748b;
        font-weight: 500
    }

    .toolbar,
    .import-card,
    .notice,
    .legend {
        background: #fff;
        border: 1px solid #e0f2fe;
        border-radius: 16px;
        padding: 16px;
        margin: 16px 0;
        box-shadow: 0 1px 3px rgba(2, 132, 199, .05)
    }

    .toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: end
    }

    .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 160px
    }

    .field label {
        font-size: 11px;
        font-weight: 700;
        color: #475569
    }

    .field input,
    .field select,
    .import-grid input {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 11px 13px;
        background: #f8fafc;
        font-weight: 600
    }

    .btn,
    .toolbar button,
    .import-grid button,
    .savebar button,
    .danger,
    .btn-import-save,
    .btn-cancel {
        border: 0;
        border-radius: 14px;
        padding: 12px 16px;
        font-weight: 800;
        cursor: pointer
    }

    .toolbar button,
    .import-grid button,
    .savebar button,
    .btn-import-save {
        background: #0284c7;
        color: #fff
    }

    .danger {
        background: #fff1f2;
        color: #e11d48;
        border: 1px solid #fecdd3
    }

    .table-wrap {
        overflow: auto;
        border: 1px solid #111;
        border-radius: 0;
        background: #fff;
        box-shadow: none;
        max-height: 72vh
    }

    .matrix {
        border-collapse: separate;
        border-spacing: 0;
        min-width: max-content;
        width: 100%
    }

    .matrix th,
    .matrix td {
        border-right: 1px solid #111;
        border-bottom: 1px solid #111;
        text-align: center;
        padding: 0;
        background: #fff;
        height: 40px
    }

    .matrix thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #fff;
        color: #111;
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 14px;
        font-weight: 400
    }

    .matrix .month-title {
        font-size: 15px;
        background: #fff;
        color: #111;
        letter-spacing: .5px
    }

    .matrix .name {
        position: sticky;
        left: 42px;
        z-index: 12;
        min-width: 225px;
        text-align: left;
        padding: 0 6px !important;
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 14px
    }

    .matrix .no-stick {
        position: sticky;
        left: 0;
        z-index: 13;
        min-width: 42px;
        width: 42px;
        font-family: Georgia, 'Times New Roman', serif
    }

    .matrix tbody .name,
    .matrix tbody .no-stick {
        background: #fff
    }

    .matrix select {
        width: 44px;
        height: 39px;
        border: 0;
        border-radius: 0;
        background: transparent;
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 14px;
        text-align: center;
        appearance: none
    }

    .matrix td.changed {
        background: #fef9c3
    }

    .weekend {
        background: inherit !important
    }

    .matrix td.off-cell {
        background: #a6a6a6 !important
    }

    .week-summary {
        display: block;
        font-size: 9px;
        color: #94a3b8;
        margin-top: 4px
    }

    .week-summary.ok {
        color: #059669
    }

    .week-summary.bad {
        color: #dc2626
    }

    .row-clear {
        border: 0;
        background: #fff1f2;
        color: #e11d48;
        border-radius: 10px;
        padding: 8px 10px
    }

    .savebar {
        position: sticky;
        bottom: 14px;
        display: flex;
        justify-content: flex-end;
        margin-top: 16px;
        z-index: 30
    }

    .savebar button {
        box-shadow: 0 10px 25px rgba(2, 132, 199, .25)
    }

    .import-grid {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px
    }

    .preview-wrap {
        display: none;
        overflow: auto;
        margin-top: 14px
    }

    .preview-table {
        width: 100%;
        border-collapse: collapse
    }

    .preview-table th,
    .preview-table td {
        border-bottom: 1px solid #e2e8f0;
        padding: 10px;
        font-size: 12px
    }

    .preview-actions {
        display: none;
        gap: 10px;
        margin-top: 12px
    }

    .notice.success {
        background: #ecfdf5;
        color: #047857
    }

    .notice.error {
        background: #fff1f2;
        color: #be123c
    }

    .legend {
        font-size: 12px;
        color: #64748b;
        line-height: 1.6
    }

    @media(max-width:900px) {
        .page {
            padding: 0 10px 90px
        }

        .toolbar {
            display: grid;
            grid-template-columns: 1fr 1fr
        }

        .field {
            min-width: 0
        }

        .matrix .name {
            min-width: 170px
        }

        .table-wrap {
            max-height: 64vh
        }
    }

    @media(max-width:600px) {
        .head {
            padding: 12px 2px
        }

        .title {
            font-size: 16px
        }

        .sub {
            font-size: 10px
        }

        .toolbar {
            grid-template-columns: 1fr
        }

        .import-grid {
            grid-template-columns: 1fr
        }

        .matrix .name {
            left: 42px;
            min-width: 145px;
            max-width: 145px
        }

        .matrix .no-stick {
            min-width: 42px
        }

        .matrix th,
        .matrix td {
            padding: 4px
        }

        .matrix select {
            width: 40px;
            height: 34px
        }

        .savebar {
            bottom: 8px
        }

        .savebar button {
            width: 100%
        }

        .preview-actions {
            flex-direction: column
        }
    }

    .group-card {
        background: #fff;
        border: 1px solid #e0f2fe;
        border-radius: 18px;
        padding: 16px;
        margin: 16px 0;
        box-shadow: 0 4px 18px rgba(2, 132, 199, .06)
    }

    .group-title {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px
    }

    .group-title strong {
        font-size: 14px;
        color: #0369a1
    }

    .group-title p {
        margin: 4px 0 0;
        font-size: 11px;
        color: #64748b
    }

    .group-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px
    }

    .group-item {
        display: grid;
        grid-template-columns: minmax(120px, 1.2fr) minmax(120px, 1fr) minmax(140px, 1fr);
        gap: 8px;
        align-items: center;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #f8fafc
    }

    .group-name {
        font-size: 12px;
        font-weight: 800;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .group-input,
    .group-select {
        width: 100%;
        height: 40px;
        border: 1px solid #dbeafe;
        border-radius: 12px;
        background: #fff;
        padding: 8px 10px;
        font-size: 11px;
        font-weight: 700
    }

    .group-save-wrap {
        display: flex;
        justify-content: flex-end;
        margin-top: 12px
    }

    .group-save {
        border: 0;
        border-radius: 13px;
        background: #0284c7;
        color: #fff;
        padding: 11px 15px;
        font-weight: 800;
        cursor: pointer
    }

    .group-row td {
        background: #d9eaf7 !important;
        color: #111 !important;
        text-align: left !important;
        padding: 7px 10px !important;
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 13px;
        font-weight: 700;
        position: relative !important;
        left: auto !important;
        z-index: 1 !important
    }

    .group-row .group-count {
        float: right;
        background: #fff;
        color: #0284c7;
        border-radius: 999px;
        padding: 3px 8px;
        font-size: 9px
    }

    .user-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 5px
    }

    .meta-pill {
        font-size: 8px;
        font-weight: 800;
        padding: 3px 6px;
        border-radius: 999px;
        background: #e0f2fe;
        color: #0369a1
    }

    .meta-pill.spv {
        background: #eef2ff;
        color: #4338ca
    }

    .table-wrap {
        scrollbar-width: thin;
        scrollbar-color: #7dd3fc #f8fafc
    }

    .matrix tbody tr:hover td {
        background: #f8fdff
    }

    .matrix tbody tr:hover .name,
    .matrix tbody tr:hover .no-stick {
        background: #f8fdff
    }

    @media(max-width:1200px) {
        .group-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }

    @media(max-width:760px) {
        .group-grid {
            grid-template-columns: 1fr
        }

        .group-item {
            grid-template-columns: 1fr 1fr
        }

        .group-name {
            grid-column: 1/-1
        }

        .toolbar {
            gap: 8px
        }

        .toolbar button,
        .danger {
            width: 100%
        }

        .table-wrap {
            max-height: 60vh
        }
    }

    @media(max-width:480px) {
        .group-item {
            grid-template-columns: 1fr
        }

        .group-name {
            grid-column: auto
        }

        .group-save {
            width: 100%
        }

        .group-title {
            flex-direction: column
        }

        .matrix .name {
            min-width: 130px;
            max-width: 130px
        }

        .week-summary {
            font-size: 8px
        }
    }

    .simple-group-tools {
        display: grid;
        grid-template-columns: minmax(220px, 1.5fr) minmax(160px, 1fr) minmax(180px, 1fr) auto;
        gap: 10px;
        align-items: end;
        margin-bottom: 14px
    }

    .search-box {
        position: relative
    }

    .search-box i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #38bdf8
    }

    .search-box input {
        width: 100%;
        height: 44px;
        border: 1px solid #dbeafe;
        border-radius: 13px;
        background: #f8fafc;
        padding: 10px 12px 10px 38px;
        font-weight: 700
    }

    .ob-list {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 9px;
        max-height: 320px;
        overflow: auto;
        padding: 2px
    }

    .ob-card {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        padding: 11px;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        background: #fff;
        cursor: pointer;
        transition: .2s
    }

    .ob-card:hover {
        border-color: #7dd3fc;
        background: #f0f9ff
    }

    .ob-card.selected {
        border-color: #0284c7;
        background: #e0f2fe;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, .08)
    }

    .ob-card input {
        margin-top: 3px
    }

    .ob-card-name {
        font-size: 12px;
        font-weight: 800;
        color: #0f172a
    }

    .ob-card-meta {
        font-size: 9px;
        color: #64748b;
        margin-top: 4px;
        line-height: 1.45
    }

    .group-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 14px
    }

    .selection-count {
        font-size: 11px;
        font-weight: 800;
        color: #0369a1
    }

    .empty-search {
        display: none;
        grid-column: 1/-1;
        text-align: center;
        padding: 22px;
        color: #94a3b8;
        font-size: 12px
    }

    .select-visible {
        border: 0;
        background: #e0f2fe;
        color: #0369a1;
        border-radius: 12px;
        padding: 11px 13px;
        font-weight: 800;
        cursor: pointer
    }

    .target-field label {
        display: block;
        font-size: 10px;
        font-weight: 800;
        color: #475569;
        margin-bottom: 6px
    }

    .target-field select {
        width: 100%;
        height: 44px;
        border: 1px solid #dbeafe;
        border-radius: 13px;
        background: #fff;
        padding: 9px 11px;
        font-weight: 700
    }

    .apply-group {
        border: 0;
        background: #0284c7;
        color: #fff;
        border-radius: 13px;
        height: 44px;
        padding: 0 16px;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap
    }

    @media(max-width:1100px) {
        .simple-group-tools {
            grid-template-columns: 1fr 1fr
        }

        .ob-list {
            grid-template-columns: repeat(3, minmax(0, 1fr))
        }

        .apply-group {
            width: 100%
        }
    }

    @media(max-width:760px) {
        .simple-group-tools {
            grid-template-columns: 1fr
        }

        .ob-list {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            max-height: 360px
        }

        .group-footer {
            flex-direction: column;
            align-items: stretch
        }

        .select-visible,
        .apply-group {
            width: 100%
        }
    }

    @media(max-width:480px) {
        .ob-list {
            grid-template-columns: 1fr
        }

        .ob-card {
            padding: 10px
        }
    }

    /* SUPER APP UI OVERRIDES */
    body {
        background: #f4f7fb
    }

    .header-container {
        position: sticky;
        top: 0;
        z-index: 100;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 1px 0 #e5e7eb
    }

    .header-inner {
        max-width: 1900px;
        margin: auto;
        padding: 0 18px
    }

    .head {
        min-height: 64px;
        padding: 10px 0
    }

    .back {
        width: 34px;
        height: 34px;
        border-radius: 10px
    }

    .title {
        font-size: 16px
    }

    .sub {
        font-size: 10px
    }

    .app-content {
        padding-top: 12px
    }

    .toolbar {
        display: grid;
        grid-template-columns: auto minmax(320px, 1fr) auto auto auto;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        margin: 0 0 12px;
        border-radius: 14px
    }

    .toolbar>a {
        height: 34px;
        display: inline-flex;
        align-items: center;
        padding: 0 10px;
        border-radius: 9px;
        background: #f1f5f9;
        color: #334155;
        text-decoration: none;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap
    }

    .toolbar form {
        display: flex !important;
        align-items: center;
        gap: 7px !important;
        flex-wrap: nowrap !important
    }

    .toolbar input,
    .toolbar select {
        height: 34px;
        border: 1px solid #dbe3ed;
        border-radius: 9px;
        padding: 0 10px;
        background: #fff;
        font-size: 11px;
        font-weight: 700
    }

    .toolbar button,
    .danger {
        height: 34px !important;
        padding: 0 11px !important;
        border-radius: 9px !important;
        font-size: 10px !important
    }

    .group-card,
    .import-card,
    .legend {
        border-color: #e5eaf1;
        border-radius: 14px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, .04)
    }

    .group-card {
        padding: 12px
    }

    .group-title {
        margin-bottom: 10px
    }

    .simple-group-tools {
        grid-template-columns: minmax(220px, 1.25fr) minmax(220px, 1fr) minmax(180px, .8fr) auto;
        gap: 8px
    }

    .search-box,
    .target-field select {
        height: 38px !important;
        border-radius: 10px !important
    }

    .search-box input {
        font-size: 11px
    }

    .target-field label {
        font-size: 9px;
        margin-bottom: 4px
    }

    .target-field small {
        font-size: 9px
    }

    .apply-group,
    .select-visible {
        height: 38px;
        padding: 0 12px;
        border-radius: 10px;
        font-size: 10px
    }

    .ob-list {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 7px;
        max-height: 250px;
        padding: 8px
    }

    .ob-card {
        min-height: 58px;
        padding: 8px;
        border-radius: 10px
    }

    .ob-card-name {
        font-size: 11px
    }

    .ob-card-meta {
        font-size: 8px
    }

    .group-footer {
        margin-top: 9px
    }

    .import-card {
        padding: 12px
    }

    .import-grid input {
        height: 36px;
        padding: 7px 10px;
        border-radius: 9px
    }

    .import-grid button,
    .btn-import-save,
    .btn-cancel {
        height: 36px;
        padding: 0 12px;
        border-radius: 9px;
        font-size: 10px
    }

    .table-wrap {
        max-height: 68vh;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        box-shadow: 0 4px 18px rgba(15, 23, 42, .05)
    }

    .matrix th,
    .matrix td {
        height: 34px;
        border-color: #cbd5e1
    }

    .matrix thead th {
        height: 34px;
        background: #f8fafc;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 10px;
        font-weight: 800
    }

    .matrix thead tr:nth-child(1) th {
        top: 0
    }

    .matrix thead tr:nth-child(2) th {
        top: 34px
    }

    .matrix thead tr:nth-child(3) th {
        top: 68px
    }

    .matrix .month-title {
        font-size: 12px;
        background: #eff6ff
    }

    .matrix .no-stick {
        min-width: 38px;
        width: 38px
    }

    .matrix .name {
        left: 38px;
        min-width: 205px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 11px
    }

    .matrix select {
        width: 38px;
        height: 33px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 11px
    }

    .matrix .action {
        position: sticky;
        right: 0;
        z-index: 14;
        min-width: 86px;
        background: #fff
    }

    .matrix thead .action {
        z-index: 30;
        background: #f8fafc
    }

    .matrix thead .no-stick,
    .matrix thead .name {
        z-index: 31;
        background: #f8fafc
    }

    .row-clear {
        padding: 6px 8px;
        border-radius: 7px;
        font-size: 9px
    }

    .group-row td {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 10px;
        padding: 6px 9px !important;
        background: #eaf4ff !important
    }

    .meta-pill {
        font-size: 7px
    }

    .week-summary {
        font-size: 8px
    }

    .legend {
        padding: 10px 12px;
        font-size: 10px
    }

    .savebar button {
        height: 38px;
        padding: 0 14px;
        border-radius: 10px;
        font-size: 10px
    }

    @media(max-width:1200px) {
        .toolbar {
            grid-template-columns: auto 1fr auto
        }

        .toolbar>form:last-child {
            grid-column: 1/-1;
            margin-left: 0 !important
        }

        .simple-group-tools {
            grid-template-columns: 1fr 1fr
        }

        .ob-list {
            grid-template-columns: repeat(3, 1fr)
        }
    }

    @media(max-width:700px) {
        .header-inner {
            padding: 0 10px
        }

        .sub {
            display: none
        }

        .toolbar {
            grid-template-columns: 1fr 1fr
        }

        .toolbar form {
            grid-column: 1/-1
        }

        .toolbar>a,
        .toolbar button,
        .danger {
            width: 100%;
            justify-content: center
        }

        .simple-group-tools {
            grid-template-columns: 1fr
        }

        .ob-list {
            grid-template-columns: 1fr 1fr
        }

        .matrix .name {
            min-width: 150px;
            max-width: 150px
        }

        .matrix .action {
            min-width: 72px
        }
    }

    /* ===== FINAL SUPER APP VISUAL OVERRIDE ===== */
    :root {
        --sa-blue: #0b84d8;
        --sa-blue-dark: #0369a1;
        --sa-border: #dbe5ef;
        --sa-bg: #f4f7fb;
        --sa-text: #0f172a;
        --sa-muted: #64748b
    }

    body {
        background: var(--sa-bg) !important;
        color: var(--sa-text) !important
    }

    .page {
        max-width: none !important;
        padding-left: 14px !important;
        padding-right: 14px !important
    }

    .header-container {
        position: relative !important;
        top: auto !important;
        background: transparent !important;
        border: 0 !important;
        backdrop-filter: none !important;
        padding: 14px 14px 0
    }

    .header-inner {
        max-width: none !important;
        background: #fff;
        border: 1px solid #edf1f5;
        border-radius: 15px;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .05);
        padding: 0 18px !important
    }

    .head {
        min-height: 88px !important;
        padding: 14px 0 !important
    }

    .head-left {
        gap: 18px !important
    }

    .back {
        width: 48px !important;
        height: 48px !important;
        border-radius: 12px !important;
        background: #f8fbff !important;
        border: 1px solid #dce8f3 !important;
        font-size: 18px !important
    }

    .title {
        font-size: 21px !important;
        line-height: 1.25 !important;
        color: #0f172a !important
    }

    .sub {
        font-size: 12px !important;
        margin-top: 6px !important
    }

    .app-content {
        padding-top: 14px !important
    }

    .toolbar-card {
        display: grid !important;
        grid-template-columns: minmax(0, 1.35fr) 1px minmax(0, 1fr) !important;
        gap: 22px !important;
        align-items: stretch !important;
        padding: 18px 20px !important;
        margin: 0 0 14px !important;
        border-radius: 15px !important;
        border: 1px solid #e4eaf0 !important;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .05) !important
    }

    .toolbar-group {
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-width: 0
    }

    .toolbar-label {
        font-size: 11px;
        font-weight: 800;
        color: #1e3a5f;
        letter-spacing: .01em
    }

    .toolbar-label i {
        color: var(--sa-blue);
        margin-right: 7px
    }

    .toolbar-divider {
        background: #e2e8f0
    }

    .toolbar-controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap
    }

    .filter-form {
        display: flex !important;
        gap: 10px !important;
        align-items: center !important;
        flex-wrap: wrap !important
    }

    .quick-controls {
        justify-content: flex-end
    }

    .toolbar a,
    .toolbar button,
    .toolbar input,
    .toolbar select {
        height: 40px !important;
        border-radius: 8px !important;
        font-size: 11px !important
    }

    .toolbar a {
        padding: 0 18px !important;
        background: #fff !important;
        border: 1px solid #d7e0ea !important;
        color: #334155 !important
    }

    .toolbar input {
        width: 226px !important;
        background: #fff !important
    }

    .toolbar select {
        width: 145px !important;
        background: #fff !important
    }

    .toolbar button {
        padding: 0 17px !important
    }

    .outline-btn {
        background: #fff !important;
        color: #0878c9 !important;
        border: 1px solid #0b84d8 !important
    }

    .danger {
        background: #fff !important;
        color: #ef4444 !important;
        border: 1px solid #ff6b6b !important
    }

    .group-card {
        padding: 20px !important;
        border-radius: 15px !important;
        border: 1px solid #e4eaf0 !important;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .05) !important;
        margin: 0 0 14px !important
    }

    .group-title {
        margin-bottom: 20px !important
    }

    .group-title strong {
        font-size: 18px !important;
        color: #0f172a !important
    }

    .group-title strong i {
        color: var(--sa-blue);
        margin-right: 8px
    }

    .group-title p {
        font-size: 12px !important;
        margin-top: 7px !important
    }

    .simple-group-tools {
        display: grid !important;
        grid-template-columns: minmax(300px, .9fr) minmax(420px, 1.15fr) minmax(260px, .75fr) auto !important;
        gap: 18px !important;
        align-items: end !important;
        background: #fbfdff;
        border: 1px solid #e4eaf0;
        border-radius: 12px;
        padding: 18px !important
    }

    .search-box,
    .target-field select {
        height: 48px !important;
        border-radius: 9px !important;
        background: #fff !important
    }

    .search-box {
        border: 2px solid #111827 !important
    }

    .search-box input {
        font-size: 12px !important
    }

    .search-box i {
        font-size: 15px !important
    }

    .target-field label {
        font-size: 10px !important;
        color: #334155 !important
    }

    .target-field small {
        font-size: 9px !important;
        margin-top: 7px !important
    }

    .apply-group {
        height: 46px !important;
        padding: 0 20px !important;
        border-radius: 9px !important;
        font-size: 11px !important;
        white-space: nowrap
    }

    .ob-list-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin: 22px 0 10px;
        font-size: 11px
    }

    .ob-list-head>div:first-child strong {
        color: #0878c9;
        font-size: 13px
    }

    .ob-list-head>div:first-child span {
        color: #64748b
    }

    .ob-list-actions {
        display: flex;
        align-items: center;
        gap: 30px
    }

    .ob-list-actions label {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #334155
    }

    .ob-list-actions strong {
        color: #0878c9
    }

    .ob-list {
        grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        gap: 14px !important;
        padding: 0 !important;
        max-height: 310px !important;
        border: 0 !important;
        background: transparent !important
    }

    .ob-card {
        min-height: 120px !important;
        padding: 18px !important;
        border: 1px solid #dce4ec !important;
        border-radius: 11px !important;
        background: #fff !important;
        align-items: flex-start !important
    }

    .ob-card:hover {
        border-color: #78bff0 !important;
        box-shadow: 0 5px 15px rgba(11, 132, 216, .09)
    }

    .ob-card input {
        width: 18px;
        height: 18px;
        margin-top: 2px
    }

    .ob-card-name {
        font-size: 12px !important;
        line-height: 1.35 !important
    }

    .ob-card-meta {
        font-size: 10px !important;
        line-height: 1.8 !important;
        margin-top: 10px !important;
        color: #64748b !important
    }

    .info-strip {
        margin-top: 20px !important;
        min-height: 56px;
        border-radius: 10px;
        background: #eef7ff !important;
        color: #0878c9 !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 0 20px !important
    }

    .legacy-select,
    .legacy-count {
        display: none !important
    }

    .import-card,
    .legend {
        border-radius: 15px !important;
        border-color: #e4eaf0 !important;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .04) !important
    }

    .table-wrap {
        border-radius: 12px !important;
        border: 1px solid #cfd9e3 !important;
        background: #fff !important
    }

    .matrix thead th {
        background: #f7fafc !important;
        color: #1e293b !important
    }

    .matrix .month-title {
        background: #eef6ff !important;
        color: #0369a1 !important
    }

    .matrix .action {
        box-shadow: -2px 0 5px rgba(15, 23, 42, .04)
    }

    @media(max-width:1450px) {
        .simple-group-tools {
            grid-template-columns: minmax(260px, 1fr) minmax(330px, 1.2fr) minmax(220px, .8fr) auto !important
        }

        .ob-list {
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important
        }
    }

    @media(max-width:1100px) {
        .toolbar-card {
            grid-template-columns: 1fr !important
        }

        .toolbar-divider {
            display: none
        }

        .quick-controls {
            justify-content: flex-start
        }

        .simple-group-tools {
            grid-template-columns: 1fr 1fr !important
        }

        .apply-group {
            width: 100%
        }

        .ob-list {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important
        }
    }

    @media(max-width:700px) {
        .header-container {
            padding: 8px 8px 0
        }

        .header-inner {
            padding: 0 12px !important
        }

        .head {
            min-height: 70px !important
        }

        .back {
            width: 40px !important;
            height: 40px !important
        }

        .title {
            font-size: 16px !important
        }

        .sub {
            display: block !important;
            font-size: 9px !important
        }

        .page {
            padding-left: 8px !important;
            padding-right: 8px !important
        }

        .toolbar-card {
            padding: 14px !important
        }

        .toolbar-controls,
        .filter-form {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            width: 100%
        }

        .toolbar input,
        .toolbar select,
        .toolbar a,
        .toolbar button {
            width: 100% !important
        }

        .filter-form button {
            grid-column: 1/-1
        }

        .simple-group-tools {
            grid-template-columns: 1fr !important;
            padding: 14px !important
        }

        .ob-list-head {
            align-items: flex-start;
            flex-direction: column
        }

        .ob-list-actions {
            width: 100%;
            justify-content: space-between
        }

        .ob-list {
            grid-template-columns: 1fr !important
        }

        .ob-card {
            min-height: auto !important
        }

        .group-card {
            padding: 14px !important
        }
    }

    .schedule-nav-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        background: #fff;
        border: 1px solid #e4eaf0;
        border-radius: 14px;
        padding: 14px 16px;
        margin: 0 0 14px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, .04)
    }

    .schedule-nav-card div {
        display: flex;
        flex-direction: column;
        gap: 4px
    }

    .schedule-nav-card strong {
        font-size: 13px;
        color: #0f172a
    }

    .schedule-nav-card strong i {
        color: #0284c7;
        margin-right: 6px
    }

    .schedule-nav-card span {
        font-size: 10px;
        color: #64748b
    }

    .schedule-nav-card a {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 34px;
        padding: 0 12px;
        border-radius: 9px;
        background: #eff6ff;
        color: #0369a1;
        text-decoration: none;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap
    }

    @media(max-width:700px) {
        .schedule-nav-card {
            align-items: flex-start;
            flex-direction: column
        }

        .schedule-nav-card a {
            width: 100%;
            justify-content: center
        }
    }

    /* FINAL MODERN SUPER APP UI */
    :root {
        --p: #0b84d8;
        --pd: #0869ad;
        --soft: #edf7ff;
        --bg: #f3f6fa;
        --card: #fff;
        --bd: #dfe7f0;
        --tx: #162033;
        --mut: #6b778c;
        --danger: #e5484d;
        --shadow: 0 10px 30px rgba(22, 32, 51, .07)
    }

    body {
        background: var(--bg) !important;
        color: var(--tx) !important
    }

    .page {
        max-width: 1920px !important;
        padding: 18px 20px 110px !important
    }

    .header-container {
        background: rgba(255, 255, 255, .96) !important;
        border-bottom: 1px solid var(--bd) !important;
        box-shadow: 0 3px 14px rgba(22, 32, 51, .05) !important
    }

    .header-inner {
        max-width: 1920px !important;
        padding: 0 20px !important
    }

    .head {
        min-height: 70px !important
    }

    .back {
        width: 38px !important;
        height: 38px !important;
        border-radius: 12px !important;
        background: var(--soft) !important;
        color: var(--p) !important
    }

    .title {
        font-size: 18px !important;
        color: var(--tx) !important
    }

    .sub {
        font-size: 11px !important;
        color: var(--mut) !important
    }

    .toolbar-card {
        display: grid !important;
        grid-template-columns: minmax(0, 1.2fr) 1px minmax(0, 1fr) !important;
        gap: 20px !important;
        padding: 16px !important;
        margin: 0 0 14px !important;
        border: 1px solid var(--bd) !important;
        border-radius: 18px !important;
        background: #fff !important;
        box-shadow: var(--shadow) !important
    }

    .toolbar-group {
        gap: 10px !important
    }

    .toolbar-label {
        font-size: 10px !important;
        letter-spacing: .08em !important
    }

    .toolbar-controls,
    .filter-form,
    .quick-controls {
        gap: 8px !important
    }

    .toolbar a,
    .toolbar button,
    .toolbar input,
    .toolbar select {
        height: 38px !important;
        border-radius: 10px !important;
        font-size: 10px !important;
        font-weight: 800 !important;
        box-shadow: none !important;
        white-space: nowrap !important
    }

    .toolbar a {
        padding: 0 13px !important;
        border: 1px solid var(--bd) !important;
        background: #fff !important;
        color: #42536a !important
    }

    .toolbar input {
        width: 190px !important
    }

    .toolbar select {
        width: 128px !important
    }

    .toolbar button {
        padding: 0 14px !important;
        background: var(--p) !important;
        color: #fff !important;
        border: 1px solid var(--p) !important
    }

    .toolbar .outline-btn {
        background: #fff !important;
        color: var(--p) !important;
        border-color: #9dc9e8 !important
    }

    .toolbar .danger {
        background: #fff7f7 !important;
        color: var(--danger) !important;
        border-color: #f1b7b9 !important
    }

    .schedule-nav-card,
    .import-card,
    .legend {
        border: 1px solid var(--bd) !important;
        background: #fff !important;
        box-shadow: var(--shadow) !important;
        border-radius: 18px !important
    }

    .schedule-nav-card {
        padding: 14px 16px !important;
        margin-bottom: 14px !important
    }

    .schedule-nav-card a {
        height: 36px !important;
        border-radius: 10px !important;
        padding: 0 13px !important;
        background: var(--soft) !important;
        font-size: 10px !important
    }

    .import-card {
        padding: 16px !important;
        margin: 0 0 14px !important
    }

    .import-card>strong {
        font-size: 13px !important
    }

    .import-grid input,
    .import-grid button,
    .btn-import-save,
    .btn-cancel {
        height: 40px !important;
        border-radius: 10px !important;
        font-size: 10px !important
    }

    .table-wrap {
        max-height: 70vh !important;
        border: 1px solid #cfd9e5 !important;
        border-radius: 16px !important;
        box-shadow: var(--shadow) !important;
        background: #fff !important
    }

    .matrix th,
    .matrix td {
        height: 36px !important;
        border-color: #d7e0ea !important
    }

    .matrix thead th {
        background: #f7f9fc !important;
        font-size: 10px !important;
        color: #405069 !important
    }

    .matrix .month-title {
        background: #eaf5ff !important;
        color: #0869ad !important;
        font-size: 12px !important
    }

    .matrix .name {
        min-width: 220px !important;
        padding: 6px 8px !important
    }

    .matrix tbody .name strong {
        font-size: 11px !important
    }

    .matrix select {
        width: 40px !important;
        height: 35px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        color: #26364c !important
    }

    .matrix td.off-cell {
        background: #b8bec6 !important;
        color: #172033 !important
    }

    .matrix td.changed {
        box-shadow: inset 0 0 0 2px #f4c95d !important;
        background: #fff8dc !important
    }

    .row-clear {
        height: 30px !important;
        padding: 0 9px !important;
        border-radius: 8px !important;
        font-size: 9px !important;
        white-space: nowrap !important
    }

    .matrix .action {
        min-width: 96px !important
    }

    .meta-pill {
        font-size: 7px !important;
        padding: 3px 6px !important
    }

    .week-summary {
        font-size: 8px !important
    }

    .legend {
        padding: 11px 14px !important;
        margin: 12px 0 !important;
        font-size: 10px !important;
        line-height: 1.55 !important
    }

    .savebar {
        bottom: 12px !important;
        margin-top: 12px !important
    }

    .savebar button {
        height: 42px !important;
        padding: 0 18px !important;
        border-radius: 12px !important;
        font-size: 11px !important;
        background: var(--p) !important;
        box-shadow: 0 10px 24px rgba(11, 132, 216, .24) !important
    }

    .savebar button:hover {
        background: var(--pd) !important
    }

    @media(max-width:1100px) {
        .toolbar-card {
            grid-template-columns: 1fr !important
        }

        .toolbar-divider {
            display: none
        }

        .quick-controls {
            justify-content: flex-start !important
        }

        .toolbar input {
            width: 170px !important
        }

        .table-wrap {
            max-height: 66vh !important
        }
    }

    @media(max-width:700px) {
        .page {
            padding: 12px 10px 92px !important
        }

        .header-inner {
            padding: 0 10px !important
        }

        .head {
            min-height: 60px !important
        }

        .toolbar-card {
            padding: 12px !important;
            border-radius: 14px !important
        }

        .toolbar-controls,
        .filter-form,
        .quick-controls {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            width: 100% !important
        }

        .toolbar input,
        .toolbar select,
        .toolbar a,
        .toolbar button,
        .toolbar form {
            width: 100% !important
        }

        .filter-form {
            grid-column: 1/-1 !important
        }

        .schedule-nav-card {
            padding: 12px !important
        }

        .import-grid {
            grid-template-columns: 1fr !important
        }

        .matrix .name {
            min-width: 160px !important;
            max-width: 160px !important
        }

        .matrix .action {
            min-width: 78px !important
        }

        .savebar button {
            width: 100% !important
        }
    }


    .matrix,
    .matrix th,
    .matrix td,
    .matrix select,
    .matrix button,
    .matrix .name,
    .matrix .no-stick,
    .matrix .month-title,
    .group-row td {
        font-family: 'Plus Jakarta Sans', 'Inter', Arial, sans-serif !important
    }

    .matrix tbody .name strong {
        display: block;
        font-size: 11px !important;
        font-weight: 800 !important;
        line-height: 1.25;
        color: #172033 !important
    }

    .matrix tbody .name .week-summary {
        margin-top: 4px !important;
        font-family: 'Plus Jakarta Sans', 'Inter', Arial, sans-serif !important
    }

    /* ===== HEADER FINAL: SAMA DENGAN HALAMAN TIMETABLE ===== */
    .header-container {
        position: relative !important;
        top: auto !important;
        z-index: 100 !important;
        width: calc(100% - 20px) !important;
        margin: 0 10px !important;
        padding: 0 !important;
        background: transparent !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        backdrop-filter: none !important;
    }

    .header-inner {
        width: 100% !important;
        max-width: none !important;
        min-height: 68px !important;
        margin: 0 !important;
        padding: 0 18px !important;
        background: #ffffff !important;
        border: 0 !important;
        border-radius: 0 0 20px 20px !important;
        box-shadow: none !important;
    }

    .head {
        min-height: 68px !important;
        padding: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 16px !important;
    }

    .head-left {
        display: flex !important;
        align-items: center !important;
        gap: 13px !important;
        min-width: 0 !important;
    }

    .back {
        flex: 0 0 auto !important;
        width: 40px !important;
        height: 40px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border: 0 !important;
        border-radius: 50% !important;
        background: #eef8ff !important;
        color: #0584cc !important;
        font-size: 15px !important;
        cursor: pointer !important;
        box-shadow: none !important;
    }

    .back:hover,
    .header-action:hover {
        background: #e3f3ff !important;
    }

    .title {
        margin: 0 !important;
        color: #087fc1 !important;
        font-size: 18px !important;
        font-weight: 800 !important;
        line-height: 1.15 !important;
        letter-spacing: -.02em !important;
    }

    .sub {
        margin-top: 3px !important;
        color: #8b97aa !important;
        font-size: 11px !important;
        font-weight: 500 !important;
        line-height: 1.25 !important;
    }

    .header-action {
        flex: 0 0 auto !important;
        width: 40px !important;
        height: 40px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border: 0 !important;
        border-radius: 50% !important;
        background: transparent !important;
        color: #0584cc !important;
        font-size: 17px !important;
        cursor: pointer !important;
    }

    .app-content {
        padding-top: 14px !important;
    }

    @media (max-width: 700px) {
        .header-container {
            width: calc(100% - 12px) !important;
            margin: 0 6px !important;
        }

        .header-inner {
            min-height: 64px !important;
            padding: 0 12px !important;
            border-radius: 0 0 16px 16px !important;
        }

        .head {
            min-height: 64px !important;
        }

        .head-left {
            gap: 10px !important;
        }

        .back,
        .header-action {
            width: 36px !important;
            height: 36px !important;
        }

        .title {
            font-size: 15px !important;
        }

        .sub {
            display: block !important;
            font-size: 9px !important;
        }
    }
</style>
<header class="header-container">
    <div class="header-inner">
        <div class="head">
            <div class="head-left">
                <button type="button" class="back" onclick="history.back()" aria-label="Kembali"><i class="fa-solid fa-arrow-left"></i></button>
                <div>
                    <div class="title">Penjadwalan Shift Bulanan</div>
                    <div class="sub">Atur dan kelola jadwal shift OB serta Supervisor</div>
                </div>
            </div>
            <button type="button" class="header-action" onclick="window.print()" aria-label="Cetak atau unduh jadwal" title="Cetak atau unduh jadwal"><i class="fa-solid fa-download"></i></button>
        </div>
    </div>
</header>
<main class="page app-content">
    <?php if ($message !== ''): ?><div class="notice <?= e($type) ?>"><?= e($message) ?></div><?php endif; ?>

    <style>
        /* Toolbar filter dan aksi cepat - versi lebih ringkas dan konsisten */
        .toolbar-card {
            display: grid !important;
            grid-template-columns: minmax(620px, 1.45fr) minmax(430px, 1fr) !important;
            gap: 18px !important;
            align-items: end !important;
            padding: 16px 18px !important;
        }

        .toolbar-divider {
            display: none !important
        }

        .toolbar-group {
            min-width: 0 !important;
            gap: 8px !important;
        }

        .toolbar-label {
            display: flex !important;
            align-items: center !important;
            min-height: 18px !important;
            margin: 0 !important;
            font-size: 10px !important;
            line-height: 1 !important;
        }

        .toolbar-controls {
            display: flex !important;
            align-items: center !important;
            flex-wrap: nowrap !important;
            gap: 8px !important;
            min-height: 40px !important;
        }

        .filter-form {
            display: grid !important;
            grid-template-columns: 190px 132px 92px !important;
            gap: 8px !important;
            align-items: center !important;
            flex: 0 0 auto !important;
        }

        .quick-controls {
            display: grid !important;
            grid-template-columns: 112px 142px 158px !important;
            justify-content: end !important;
            align-items: center !important;
            gap: 8px !important;
        }

        .toolbar a,
        .toolbar button,
        .toolbar input,
        .toolbar select {
            height: 40px !important;
            min-height: 40px !important;
            margin: 0 !important;
            border-radius: 10px !important;
            font-size: 10px !important;
            line-height: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 5px !important;
        }

        .toolbar .nav-month {
            padding: 0 12px !important;
            min-width: 88px !important
        }

        .toolbar input {
            width: 190px !important;
            padding: 0 12px !important
        }

        .toolbar select {
            width: 132px !important;
            padding: 0 32px 0 12px !important
        }

        .filter-form button {
            width: 92px !important;
            padding: 0 12px !important
        }

        .quick-controls .nav-month {
            width: 112px !important
        }

        .quick-controls .outline-btn {
            width: 142px !important;
            padding: 0 10px !important
        }

        .quick-controls form {
            margin: 0 !important;
            width: 158px !important
        }

        .quick-controls form .danger {
            width: 100% !important;
            padding: 0 10px !important
        }

        @media(max-width:1250px) {
            .toolbar-card {
                grid-template-columns: 1fr !important;
                align-items: stretch !important
            }

            .quick-controls {
                justify-content: start !important
            }
        }

        @media(max-width:760px) {
            .toolbar-card {
                padding: 12px !important;
                gap: 14px !important
            }

            .toolbar-controls {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 8px !important
            }

            .filter-form {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                width: 100% !important
            }

            .filter-form input {
                width: 100% !important
            }

            .filter-form select {
                width: 100% !important
            }

            .filter-form button {
                grid-column: 1/-1 !important;
                width: 100% !important
            }

            .quick-controls {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                width: 100% !important
            }

            .quick-controls .nav-month,
            .quick-controls .outline-btn {
                width: 100% !important
            }

            .quick-controls form {
                grid-column: 1/-1 !important;
                width: 100% !important
            }
        }

        @media(max-width:460px) {

            .filter-form,
            .quick-controls {
                grid-template-columns: 1fr !important
            }

            .filter-form button,
            .quick-controls form {
                grid-column: auto !important
            }
        }

        /* Periode dan filter digabung secara visual dengan tabel jadwal. */
        .schedule-table-card {
            margin: 0 0 14px;
            background: #fff;
            border: 1px solid #dfe7f0;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(22, 32, 51, .07);
            overflow: hidden;
        }

        .schedule-table-card .toolbar-card {
            margin: 0 !important;
            border: 0 !important;
            border-bottom: 1px solid #dfe7f0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: #f8fbff !important;
        }

        .schedule-table-card .table-wrap {
            margin: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .schedule-table-card .legend {
            margin: 0 !important;
            border: 0 !important;
            border-top: 1px solid #dfe7f0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .schedule-table-card .savebar {
            padding: 0 14px 14px;
        }

        @media(max-width:1024px) {
            .page {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }

            .schedule-table-card .table-wrap {
                max-height: 68vh !important;
                overflow: auto !important;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }

            .matrix .name {
                min-width: 180px !important;
                max-width: 180px !important;
            }

            .matrix select {
                min-width: 42px !important;
                min-height: 40px !important;
                font-size: 12px !important;
            }
        }

        /* Mobile memakai kartu per petugas agar seluruh tanggal terbaca tanpa tabel melebar. */
        @media(max-width:700px) {
            .schedule-table-card {
                border-radius: 14px;
                overflow: visible;
                background: transparent;
                border: 0;
                box-shadow: none;
            }

            .schedule-table-card .toolbar-card {
                border: 1px solid #dfe7f0 !important;
                border-radius: 14px !important;
                margin-bottom: 10px !important;
                background: #fff !important;
            }

            .schedule-table-card .table-wrap {
                overflow: visible !important;
                max-height: none !important;
                background: transparent !important;
            }

            .matrix,
            .matrix tbody {
                display: block !important;
                width: 100% !important;
                min-width: 0 !important;
            }

            .matrix thead {
                display: none !important;
            }

            .matrix tbody tr[data-user-row] {
                display: grid !important;
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 6px;
                width: 100%;
                margin: 0 0 10px;
                padding: 12px;
                background: #fff;
                border: 1px solid #dfe7f0;
                border-radius: 14px;
                box-shadow: 0 5px 16px rgba(22, 32, 51, .06);
            }

            .matrix tbody tr[data-user-row]>td {
                position: static !important;
                display: flex !important;
                flex-direction: column;
                align-items: stretch;
                justify-content: center;
                min-width: 0 !important;
                width: auto !important;
                height: auto !important;
                padding: 0 !important;
                border: 0 !important;
                background: transparent !important;
            }

            .matrix tbody tr[data-user-row]>.no-stick {
                grid-column: 1;
                grid-row: 1;
                align-items: center;
                justify-content: center;
                width: 34px !important;
                height: 34px !important;
                border-radius: 10px !important;
                background: #edf7ff !important;
                color: #0878c9;
                font-weight: 800;
            }

            .matrix tbody tr[data-user-row]>.name {
                grid-column: 2 / -1;
                grid-row: 1;
                min-width: 0 !important;
                max-width: none !important;
                padding: 0 4px !important;
                justify-content: center;
            }

            .matrix tbody tr[data-user-row]>.name strong {
                font-size: 13px !important;
                line-height: 1.35 !important;
            }

            .matrix tbody tr[data-user-row]>.schedule-day-cell {
                min-height: 58px !important;
                padding: 5px !important;
                border: 1px solid #dfe7f0 !important;
                border-radius: 10px !important;
                background: #f8fafc !important;
            }

            .matrix tbody tr[data-user-row]>.schedule-day-cell::before {
                content: attr(data-label);
                display: block;
                margin-bottom: 3px;
                color: #64748b;
                font-size: 8px;
                font-weight: 800;
                text-align: center;
                white-space: nowrap;
            }

            .matrix tbody tr[data-user-row]>.schedule-day-cell.off-cell {
                background: #d1d5db !important;
            }

            .matrix tbody tr[data-user-row]>.schedule-day-cell.changed {
                background: #fff8dc !important;
                box-shadow: inset 0 0 0 2px #f4c95d !important;
            }

            .matrix tbody tr[data-user-row] select {
                width: 100% !important;
                min-width: 0 !important;
                height: 30px !important;
                min-height: 30px !important;
                border-radius: 7px !important;
                background: #fff !important;
                font-size: 12px !important;
                font-weight: 800 !important;
                text-align-last: center;
            }

            .matrix tbody tr[data-user-row] select:disabled {
                opacity: 1;
                color: #162033;
                -webkit-text-fill-color: #162033;
            }

            .matrix tbody tr[data-user-row]>.action {
                grid-column: 1 / -1;
                margin-top: 2px;
            }

            .matrix tbody tr[data-user-row]>.action .row-clear {
                width: 100%;
                height: 36px !important;
            }

            .matrix .group-row {
                display: block !important;
                margin: 14px 0 7px;
            }

            .matrix .group-row td {
                display: block !important;
                width: 100% !important;
                padding: 10px 12px !important;
                border: 0 !important;
                border-radius: 12px;
                font-size: 10px !important;
            }

            .matrix .group-row .group-count {
                float: none;
                display: inline-flex;
                margin-left: 6px;
            }

            .schedule-table-card .legend {
                border: 1px solid #dfe7f0 !important;
                border-radius: 12px !important;
                margin-top: 10px !important;
                background: #fff !important;
            }

            .schedule-table-card .savebar {
                position: sticky;
                bottom: 8px !important;
                padding: 8px 0 0 !important;
            }
        }

        @media(max-width:420px) {
            .matrix tbody tr[data-user-row] {
                grid-template-columns: repeat(5, minmax(0, 1fr));
                padding: 10px;
                gap: 5px;
            }

            .matrix tbody tr[data-user-row]>.name {
                grid-column: 2 / -1;
            }
        }

        /* Drag & drop susunan nama hanya pada desktop. */
        .drag-handle {
            display: inline-flex;
            width: 26px;
            height: 28px;
            margin-right: 7px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 7px;
            background: #edf7ff;
            color: #0878c9;
            cursor: grab;
            vertical-align: middle;
            touch-action: none
        }

        .drag-handle:active {
            cursor: grabbing
        }

        .matrix tbody tr.dragging {
            opacity: .45
        }

        .matrix tbody tr.drag-over td {
            box-shadow: inset 0 2px 0 #0b84d8
        }

        .order-save-toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 9999;
            padding: 11px 15px;
            border-radius: 11px;
            background: #0f172a;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .22)
        }

        @media(max-width:1024px) {
            .drag-handle {
                display: none !important
            }
        }


        /* Desktop modern Super App table */
        @media (min-width:1025px) {
            .schedule-table-card {
                border: 1px solid #d9e4ef !important;
                border-radius: 22px !important;
                box-shadow: 0 18px 45px rgba(15, 23, 42, .08) !important;
                background: #fff !important
            }

            .schedule-table-card .table-wrap {
                position: relative !important;
                overflow: auto !important;
                isolation: isolate !important;
                padding: 10px 10px 12px !important;
                background: #f4f8fc !important;
                max-height: 72vh !important;
                scrollbar-color: #b7c7d8 transparent;
                scrollbar-width: thin
            }

            /* Header jadwal tetap terlihat saat tabel di-scroll ke bawah. */
            .matrix thead {
                position: relative !important;
                z-index: 80 !important
            }

            .matrix thead th {
                position: sticky !important;
                background-clip: padding-box !important
            }

            .matrix thead tr:nth-child(1) th {
                top: 0 !important;
                z-index: 84 !important
            }

            .matrix thead tr:nth-child(2) th {
                top: 38px !important;
                z-index: 83 !important
            }

            .matrix thead tr:nth-child(3) th {
                top: 76px !important;
                z-index: 82 !important
            }

            .matrix thead th[rowspan="3"] {
                top: 0 !important;
                z-index: 90 !important
            }

            .matrix thead .no-stick {
                left: 0 !important;
                z-index: 94 !important
            }

            .matrix thead .name {
                left: 46px !important;
                z-index: 93 !important
            }

            .matrix thead .action {
                right: 0 !important;
                z-index: 92 !important
            }

            .schedule-table-card .table-wrap::-webkit-scrollbar {
                width: 10px;
                height: 10px
            }

            .schedule-table-card .table-wrap::-webkit-scrollbar-thumb {
                background: #c3d1df;
                border: 2px solid #f4f8fc;
                border-radius: 999px
            }

            .matrix {
                border-collapse: separate !important;
                border-spacing: 0 7px !important;
                background: transparent !important
            }

            .matrix thead {
                filter: drop-shadow(0 7px 10px rgba(15, 23, 42, .06))
            }

            .matrix thead th {
                height: 38px !important;
                background: #fff !important;
                border-color: #e3ebf3 !important;
                color: #4b5f77 !important;
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                font-size: 10px !important;
                font-weight: 800 !important
            }

            .matrix thead tr:first-child th {
                border-top: 1px solid #dfe8f1 !important
            }

            .matrix thead tr:first-child th:first-child {
                border-top-left-radius: 13px
            }

            .matrix thead tr:first-child th:last-child {
                border-top-right-radius: 13px
            }

            .matrix .month-title {
                background: linear-gradient(135deg, #e8f5ff, #f2f8ff) !important;
                color: #0669ad !important;
                font-size: 13px !important;
                letter-spacing: .02em !important
            }

            .matrix tbody tr[data-user-row] td {
                height: 48px !important;
                background: #fff !important;
                border-top: 1px solid #e2eaf2 !important;
                border-bottom: 1px solid #e2eaf2 !important;
                border-right: 1px solid #edf2f7 !important;
                transition: background .18s ease, box-shadow .18s ease, transform .18s ease
            }

            .matrix tbody tr[data-user-row] td:first-child {
                border-left: 1px solid #e2eaf2 !important;
                border-radius: 12px 0 0 12px !important
            }

            .matrix tbody tr[data-user-row] td:last-child {
                border-radius: 0 12px 12px 0 !important
            }

            .matrix tbody tr[data-user-row]:hover td {
                background: #f8fbff !important;
                box-shadow: 0 7px 16px rgba(15, 23, 42, .055)
            }

            .matrix tbody tr[data-user-row]:hover .name,
            .matrix tbody tr[data-user-row]:hover .no-stick,
            .matrix tbody tr[data-user-row]:hover .action {
                background: #f8fbff !important
            }

            .matrix .no-stick {
                min-width: 46px !important;
                width: 46px !important;
                color: #8190a3 !important;
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                font-size: 11px !important;
                font-weight: 800 !important
            }

            .matrix .name {
                left: 46px !important;
                min-width: 245px !important;
                padding: 0 12px !important;
                background: #fff !important;
                box-shadow: 8px 0 16px -16px rgba(15, 23, 42, .55)
            }

            .matrix tbody .name strong {
                display: inline-block !important;
                max-width: 172px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                color: #17283d !important;
                font-size: 11px !important;
                font-weight: 800 !important;
                vertical-align: middle
            }

            .drag-handle {
                width: 30px !important;
                height: 30px !important;
                margin-right: 9px !important;
                border: 1px solid #d8ebfa !important;
                border-radius: 9px !important;
                background: #eff8ff !important;
                color: #0b79c8 !important;
                box-shadow: 0 3px 9px rgba(2, 132, 199, .08) !important;
                transition: transform .16s ease, background .16s ease, box-shadow .16s ease !important
            }

            .drag-handle:hover {
                transform: translateY(-1px) scale(1.04);
                background: #dff2ff !important;
                box-shadow: 0 7px 14px rgba(2, 132, 199, .15) !important
            }

            .drag-handle:active {
                transform: scale(.96)
            }

            .matrix select {
                width: 40px !important;
                height: 32px !important;
                border: 1px solid transparent !important;
                border-radius: 8px !important;
                background: #f7fafc !important;
                color: #26384f !important;
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                font-size: 11px !important;
                font-weight: 800 !important;
                cursor: pointer !important;
                transition: border-color .16s ease, background .16s ease, box-shadow .16s ease !important
            }

            .matrix select:hover {
                border-color: #b9d9ef !important;
                background: #fff !important
            }

            .matrix select:focus {
                outline: 0 !important;
                border-color: #2494d2 !important;
                background: #fff !important;
                box-shadow: 0 0 0 3px rgba(36, 148, 210, .12) !important
            }

            .matrix td.off-cell {
                background: #eef1f5 !important
            }

            .matrix td.off-cell select {
                background: #dfe4ea !important;
                color: #526171 !important
            }

            .matrix td.changed {
                background: #fff9e8 !important;
                box-shadow: inset 0 0 0 2px #f2c14e !important
            }

            .matrix .action {
                min-width: 108px !important;
                background: #fff !important;
                box-shadow: -8px 0 16px -16px rgba(15, 23, 42, .55)
            }

            .row-clear {
                height: 31px !important;
                padding: 0 11px !important;
                border: 1px solid #ffd8df !important;
                border-radius: 9px !important;
                background: #fff2f4 !important;
                color: #e33154 !important;
                font-size: 9px !important;
                font-weight: 800 !important;
                transition: transform .15s ease, background .15s ease !important
            }

            .row-clear:hover {
                transform: translateY(-1px);
                background: #ffe5ea !important
            }

            .matrix .group-row td {
                height: 38px !important;
                padding: 0 12px !important;
                border: 1px solid #d7e8f7 !important;
                border-radius: 12px !important;
                background: linear-gradient(135deg, #eaf5ff, #f4f9ff) !important;
                color: #174d78 !important;
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                font-size: 10px !important;
                font-weight: 900 !important;
                letter-spacing: .01em !important;
                box-shadow: 0 7px 18px rgba(43, 105, 154, .07) !important
            }

            .matrix .group-row .group-count {
                display: inline-flex !important;
                align-items: center !important;
                min-height: 24px !important;
                padding: 0 9px !important;
                border: 1px solid #d4eafa !important;
                border-radius: 999px !important;
                background: #fff !important;
                color: #0c74bd !important;
                font-size: 8px !important;
                font-weight: 900 !important
            }

            .matrix tbody tr.dragging td {
                opacity: .72 !important;
                background: #eaf6ff !important;
                box-shadow: 0 18px 30px rgba(2, 132, 199, .18) !important
            }

            .matrix tbody tr.dragging {
                transform: scale(1.003)
            }

            .matrix tbody tr.drag-over td {
                box-shadow: inset 0 3px 0 #168bd0, 0 7px 16px rgba(15, 23, 42, .05) !important
            }

            .order-save-toast {
                border: 1px solid rgba(255, 255, 255, .16) !important;
                border-radius: 13px !important;
                background: rgba(15, 23, 42, .94) !important;
                backdrop-filter: blur(10px);
                font-size: 10px !important
            }
        }



        /* FINAL FIX: header tabel sticky tetap sejajar saat scroll vertikal */
        @media (min-width:1025px) {
            .schedule-table-card .table-wrap {
                --head-row-h: 38px;
            }

            /* Jadikan seluruh THEAD satu blok sticky agar rowspan NO/NAMA/AKSI
               selalu sama tinggi dengan tiga baris tanggal. */
            .matrix thead {
                position: sticky !important;
                top: 0 !important;
                z-index: 120 !important;
                filter: drop-shadow(0 7px 10px rgba(15, 23, 42, .07)) !important;
            }

            .matrix thead tr {
                height: var(--head-row-h) !important;
                min-height: var(--head-row-h) !important;
                max-height: var(--head-row-h) !important;
            }

            .matrix thead th {
                position: relative !important;
                top: auto !important;
                height: var(--head-row-h) !important;
                min-height: var(--head-row-h) !important;
                max-height: var(--head-row-h) !important;
                padding: 0 8px !important;
                line-height: 1.15 !important;
                vertical-align: middle !important;
                box-sizing: border-box !important;
                white-space: nowrap !important;
            }

            .matrix thead th[rowspan="3"] {
                position: sticky !important;
                top: auto !important;
                height: calc(var(--head-row-h) * 3) !important;
                min-height: calc(var(--head-row-h) * 3) !important;
                max-height: calc(var(--head-row-h) * 3) !important;
                vertical-align: middle !important;
                background: #fff !important;
            }

            /* Sticky horizontal tetap aktif tanpa menghitung ulang posisi vertikal. */
            .matrix thead .no-stick {
                left: 0 !important;
                z-index: 126 !important;
            }

            .matrix thead .name {
                left: 46px !important;
                z-index: 125 !important;
            }

            .matrix thead .action {
                right: 0 !important;
                z-index: 124 !important;
            }

            /* Hilangkan celah antartiga baris header yang menyebabkan tinggi berbeda. */
            .matrix thead tr:nth-child(1) th,
            .matrix thead tr:nth-child(2) th,
            .matrix thead tr:nth-child(3) th {
                top: auto !important;
                border-bottom: 1px solid #e3ebf3 !important;
            }

            .matrix thead tr:nth-child(3) th {
                border-bottom: 1px solid #d8e3ed !important;
            }
        }



        /* FINAL POLISH: seluruh header tabel putih solid dan teks lebih tegas */
        @media (min-width:1025px) {

            .matrix thead,
            .matrix thead tr,
            .matrix thead th,
            .matrix thead th[rowspan="3"],
            .matrix thead .month-title,
            .matrix thead .no-stick,
            .matrix thead .name,
            .matrix thead .action {
                background: #ffffff !important;
                background-image: none !important;
                opacity: 1 !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                background-clip: border-box !important;
            }

            .matrix thead th {
                color: #243b53 !important;
                font-weight: 900 !important;
                text-shadow: none !important;
            }

            .matrix thead .month-title {
                color: #0569ad !important;
                font-size: 13px !important;
                font-weight: 900 !important;
                letter-spacing: .02em !important;
            }

            .matrix thead .no-stick,
            .matrix thead .name,
            .matrix thead .action {
                color: #334e68 !important;
                font-weight: 900 !important;
            }

            .matrix thead tr:nth-child(1) th,
            .matrix thead tr:nth-child(2) th,
            .matrix thead tr:nth-child(3) th {
                border-color: #dce6ef !important;
            }
        }

        .mobile-calendar-nav,
        .mobile-week-head {
            display: none
        }

        @media(max-width:700px) {
            .mobile-calendar-nav {
                display: grid;
                grid-template-columns: 42px 1fr 42px;
                gap: 10px;
                align-items: center;
                background: #fff;
                border: 1px solid #dfe7f0;
                border-radius: 14px;
                padding: 10px;
                margin-bottom: 8px;
                box-shadow: 0 5px 16px rgba(22, 32, 51, .06)
            }

            .mobile-calendar-nav button {
                width: 42px;
                height: 42px;
                border: 0;
                border-radius: 12px;
                background: #edf7ff;
                color: #0878c9;
                font-size: 14px
            }

            .mobile-calendar-nav div {
                text-align: center;
                min-width: 0
            }

            .mobile-calendar-nav strong {
                display: block;
                font-size: 13px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis
            }

            .mobile-calendar-nav span {
                display: block;
                margin-top: 3px;
                font-size: 9px;
                color: #64748b
            }

            .mobile-week-head {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 6px;
                padding: 0 12px 6px;
                color: #64748b;
                font-size: 8px;
                font-weight: 800;
                text-align: center
            }

            .matrix .group-row {
                display: none !important
            }

            .matrix tbody tr[data-user-row] {
                display: none !important;
                grid-template-columns: repeat(7, minmax(0, 1fr)) !important
            }

            .matrix tbody tr[data-user-row].mobile-active {
                display: grid !important
            }

            .matrix tbody tr[data-user-row]>.no-stick {
                display: none !important
            }

            .matrix tbody tr[data-user-row]>.name {
                grid-column: 1/-1 !important;
                grid-row: 1 !important;
                text-align: center !important;
                padding: 4px 0 8px !important
            }

            .matrix tbody tr[data-user-row]>.schedule-day-cell.first-calendar-day {
                grid-column-start: var(--calendar-start)
            }
        }
    </style>

    <?php if ($canManageSchedule): ?><div class="schedule-nav-card">
            <div><strong><i class="fa-solid fa-users-gear"></i> Penempatan OB</strong><span>Kelola gedung dan supervisor pada halaman terpisah agar jadwal lebih fokus.</span></div><a href="penempatan_ob.php"><i class="fa-solid fa-arrow-up-right-from-square"></i> Atur Penempatan OB</a>
        </div>
        <div class="import-card"><strong><i class="fa-solid fa-file-excel"></i> Import Jadwal dari Excel</strong>
            <div class="import-grid"><input type="file" id="excelFile" accept=".xlsx,.xls,.csv"><button type="button" onclick="previewExcel()"><i class="fa-solid fa-eye"></i> Preview Excel</button></div>
            <div id="previewWrap" class="preview-wrap">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Tanggal</th>
                            <th>Shift</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="previewBody"></tbody>
                </table>
            </div>
            <div id="previewActions" class="preview-actions">
                <form method="post" id="importForm"><input type="hidden" name="action" value="import_excel"><input type="hidden" name="month" id="importSaveMonth" value="<?= e($month) ?>"><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><input type="hidden" name="import_json" id="importJson"><button type="submit" class="btn-import-save"><i class="fa-solid fa-database"></i> Simpan Preview ke Database</button></form><button type="button" class="btn-cancel" onclick="cancelPreview()">Batal</button>
            </div>
        </div><?php endif; ?>
    <section class="schedule-table-card">
        <section class="toolbar toolbar-card">
            <div class="toolbar-group period-group">
                <div class="toolbar-label"><i class="fa-regular fa-calendar"></i> PERIODE &amp; FILTER</div>
                <div class="toolbar-controls">
                    <a class="nav-month" href="?month=<?= e($prev) ?>&role=<?= e($roleFilter) ?>">‹ Bulan lalu</a>
                    <form method="get" class="filter-form">
                        <input type="month" name="month" id="importMonth" value="<?= e($month) ?>">
                        <select name="role">
                            <option value="ob" <?= $roleFilter === 'ob' ? 'selected' : '' ?>>OB</option>
                            <option value="supervisor" <?= $roleFilter === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
                        </select>
                        <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Tampilkan</button>
                    </form>
                </div>
            </div>
            <div class="toolbar-divider"></div>
            <?php if ($canManageSchedule): ?><div class="toolbar-group quick-group">
                    <div class="toolbar-label">AKSI CEPAT</div>
                    <div class="toolbar-controls quick-controls">
                        <a class="nav-month" href="?month=<?= e($next) ?>&role=<?= e($roleFilter) ?>">Bulan berikut ›</a>
                        <button type="button" class="muted outline-btn" onclick="clearAllCells()"><i class="fa-solid fa-rotate"></i> Kosongkan tampilan</button>
                        <form method="post" onsubmit="return confirm('Hapus seluruh jadwal bulan ini untuk role <?= e($roleFilter) ?>?');">
                            <input type="hidden" name="action" value="delete_month">
                            <input type="hidden" name="month" value="<?= e($month) ?>">
                            <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
                            <button type="submit" class="danger"><i class="fa-regular fa-trash-can"></i> Hapus Jadwal Bulan Ini</button>
                        </form>
                    </div>
                </div><?php endif; ?>
        </section>
        <form method="post" id="matrixSaveForm"><input type="hidden" name="action" value="save_matrix"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><input type="hidden" name="matrix_json" id="matrixJson" value="">
            <div class="mobile-calendar-nav" id="mobileCalendarNav">
                <button type="button" id="mobilePrevUser"><i class="fa-solid fa-chevron-left"></i></button>
                <div><strong id="mobileCalendarName">Petugas</strong><span id="mobileCalendarCounter">1 dari 1</span></div>
                <button type="button" id="mobileNextUser"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
            <div class="mobile-week-head"><span>SN</span><span>SL</span><span>R</span><span>K</span><span>J</span><span>SB</span><span>M</span></div>
            <div class="table-wrap">
                <table class="matrix">
                    <thead>
                        <tr>
                            <th rowspan="3" class="no no-stick">NO</th>
                            <th rowspan="3" class="name">NAMA</th>
                            <th colspan="<?= $days ?>" class="month-title"><?= e($months[date('m', strtotime($first))] . ' ' . date('Y', strtotime($first))) ?></th>
                            <?php if ($canManageSchedule): ?><th rowspan="3" class="action">AKSI</th><?php endif; ?>
                        </tr>
                        <tr><?php for ($d = 1; $d <= $days; $d++): $date = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT); ?><th class="day"><?= $d ?></th><?php endfor; ?></tr>
                        <tr><?php $abbr = ['1' => 'SN', '2' => 'SL', '3' => 'R', '4' => 'K', '5' => 'J', '6' => 'SB', '7' => 'M'];
                            for ($d = 1; $d <= $days; $d++): $date = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                                $n = date('N', strtotime($date)); ?><th class="day"><?= $abbr[$n] ?></th><?php endfor; ?></tr>
                    </thead>
                    <tbody>
                        <?php $lastGroup = '';
                        foreach ($users as $i => $u): if ($roleFilter !== 'supervisor'): $groupKey = $u['area_spv'] . '|' . $u['supervisor_nama'];
                                if ($groupKey !== $lastGroup): $lastGroup = $groupKey;
                                    $groupCount = 0;
                                    foreach ($users as $cu) {
                                        if (($cu['area_spv'] . '|' . $cu['supervisor_nama']) === $groupKey) $groupCount++;
                                    } ?><tr class="group-row">
                                        <td colspan="<?= $days + 3 ?>"><i class="fa-solid fa-building"></i> GEDUNG: <?= e($u['area_spv']) ?> &nbsp;•&nbsp; SPV: <?= e($u['supervisor_nama']) ?><span class="group-count"><?= $groupCount ?> petugas</span></td>
                                    </tr><?php endif;
                                    endif; ?><tr data-user-row data-user-id="<?= (int)$u['id'] ?>" data-group-key="<?= e($roleFilter === 'ob' ? ($u['area_spv'] . '|' . $u['supervisor_nama']) : 'supervisor') ?>">
                                <td class="no-stick row-number"><?= $i + 1 ?></td>
                                <td class="name"><?php if ($canManageSchedule): ?><button type="button" class="drag-handle" title="Geser untuk mengatur susunan nama" aria-label="Geser <?= e($u['nama']) ?>"><i class="fa-solid fa-grip-vertical"></i></button><?php endif; ?><strong><?= e($u['nama']) ?></strong></td><?php for ($d = 1; $d <= $days; $d++): $date = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                                                                                                                                                                                                                                                                                                                                $n = (int)date('N', strtotime($date));
                                                                                                                                                                                                                                                                                                                                $val = $schedules[(int)$u['id']][$date] ?? '';
                                                                                                                                                                                                                                                                                                                                $max = $roleFilter === 'ob' ? 3 : 2;
                                                                                                                                                                                                                                                                                                                                $mobileDay = $abbr[(string)$n] . ', ' . $d; ?><td class="schedule-day-cell <?= $val === 'L' ? 'off-cell' : '' ?>" data-label="<?= e($mobileDay) ?>" data-weekday="<?= $n ?>"><select name="shift[<?= (int)$u['id'] ?>][<?= e($date) ?>]" data-date="<?= e($date) ?>" aria-label="Jadwal <?= e($u['nama']) ?> tanggal <?= e($date) ?>" <?= $canManageSchedule ? '' : 'disabled' ?>>
                                            <option value="" <?= $val === '' ? 'selected' : '' ?>>-</option>
                                            <option value="L" <?= $val === 'L' ? 'selected' : '' ?>>L</option><?php for ($s = 1; $s <= $max; $s++): $allowed = shiftTimes($roleFilter, $date, (string)$s) !== null;
                                                                                                                                                                                                                                                                                                                                    if (!$allowed) continue; ?><option value="<?= $s ?>" <?= $val == (string)$s ? 'selected' : '' ?>><?= $s ?></option><?php endfor; ?>
                                        </select></td><?php endfor; ?><?php if ($canManageSchedule): ?><td class="action"><button type="button" class="row-clear" onclick="clearRow(this)"><i class="fa-solid fa-eraser"></i> Kosongkan</button></td><?php endif; ?>
                            </tr><?php endforeach; ?>
                        <?php if (!$users): ?><tr>
                                <td colspan="<?= $days + 3 ?>" style="padding:24px">Tidak ada pengguna dengan role ini.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canManageSchedule): ?><div class="legend">Tambah: pilih angka pada sel kosong. Edit: ganti angka shift. Hapus: pilih opsi kosong atau tombol Kosongkan. Klik Simpan Jadwal Bulanan untuk menyimpan semua perubahan. 1 = Shift 1, 2 = Shift 2, 3 = Shift 3, L = Libur. Hari libur dapat dipilih pada hari apa pun.</div>
                <div class="savebar"><button type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Jadwal Bulanan</button></div><?php else: ?><div class="legend"><i class="fa-solid fa-eye"></i> Mode lihat saja. Akun OB tidak dapat menambah, mengubah, menghapus, atau mengimpor jadwal.</div><?php endif; ?>
        </form>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    const IMPORT_MONTH = <?= json_encode($month) ?>;
    const IMPORT_USERS = <?= json_encode($importUsers) ?>;
    let importRows = [];
    let activeImportMonth = IMPORT_MONTH;

    function normalizeText(v) {
        return String(v || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function parseImportMonth(v) {
        const t = normalizeText(v).replace(/[._-]+/g, ' ');
        const map = {
            januari: 1,
            februari: 2,
            maret: 3,
            april: 4,
            mei: 5,
            juni: 6,
            juli: 7,
            agustus: 8,
            september: 9,
            oktober: 10,
            november: 11,
            desember: 12,
            january: 1,
            february: 2,
            march: 3,
            may: 5,
            june: 6,
            july: 7,
            august: 8,
            october: 10,
            december: 12
        };
        const m = t.match(/(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember|january|february|march|may|june|july|august|october|december)\s+(20\d{2})/);
        return m ? m[2] + '-' + String(map[m[1]]).padStart(2, '0') : '';
    }

    function excelDate(v) {
        if (typeof v === 'number') {
            const d = XLSX.SSF.parse_date_code(v);
            return d ? d.y + '-' + String(d.m).padStart(2, '0') + '-' + String(d.d).padStart(2, '0') : '';
        }
        const t = String(v || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(t)) return t;
        const m = t.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
        return m ? m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0') : '';
    }
    async function previewExcel() {
        if (typeof XLSX === 'undefined') {
            alert('Library pembaca Excel gagal dimuat. Pastikan perangkat terhubung internet.');
            return;
        }
        const file = document.getElementById('excelFile').files[0];
        if (!file) {
            alert('Pilih file Excel terlebih dahulu.');
            return;
        }

        const data = await file.arrayBuffer();
        const workbook = XLSX.read(data, {
            type: 'array',
            cellDates: true
        });
        importRows = [];
        const warnings = [];

        function cleanCell(value) {
            return String(value === null || typeof value === 'undefined' ? '' : value).trim();
        }

        function isHeaderRow(row) {
            return normalizeText(row[0]) === 'no' && ['nama', 'nama petugas'].includes(normalizeText(row[1]));
        }

        function isSupervisorTitle(value) {
            return /\(\s*spv\s*\)|\bsupervisor\b/i.test(cleanCell(value));
        }

        function isAreaRow(row) {
            const first = cleanCell(row[0]);
            const second = cleanCell(row[1]);
            if (!first || second) return false;
            if (/^\d+$/.test(first)) return false;
            if (isSupervisorTitle(first)) return false;
            if (normalizeText(first) === 'no') return false;
            return true;
        }

        function normalizedShift(value) {
            const raw = cleanCell(value).toUpperCase();
            if (raw === '' || raw === '-' || raw === 'L' || raw === 'LIBUR' || raw === 'OFF') return 'L';
            const match = raw.match(/(?:SHIFT\s*)?([123])/i);
            return match ? match[1] : '';
        }

        function addSchedule(name, day, shift, sheetName, area, rowRole, sheetMonth) {
            const year = parseInt(sheetMonth.slice(0, 4), 10);
            const monthNo = parseInt(sheetMonth.slice(5, 7), 10);
            const maxDay = new Date(year, monthNo, 0).getDate();
            const numericDay = parseInt(day, 10);
            if (!name || !numericDay || numericDay < 1 || numericDay > maxDay) return;
            const value = normalizedShift(shift);
            if (!value) {
                warnings.push('Shift tidak dikenali: ' + name + ' tanggal ' + numericDay + ' pada sheet ' + sheetName + '.');
                return;
            }
            importRows.push({
                nama: name,
                tanggal: sheetMonth + '-' + String(numericDay).padStart(2, '0'),
                shift: value,
                role: rowRole,
                area: area || '',
                sheet: sheetName
            });
        }

        workbook.SheetNames.forEach(function(sheetName) {
            const rows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {
                header: 1,
                defval: '',
                raw: true
            });
            let sheetMonth = parseImportMonth(sheetName);
            if (!sheetMonth) {
                for (let r = 0; r < Math.min(rows.length, 20) && !sheetMonth; r++) {
                    for (let c = 0; c < (rows[r] || []).length; c++) {
                        sheetMonth = parseImportMonth(rows[r][c]);
                        if (sheetMonth) break;
                    }
                }
            }
            if (!sheetMonth) {
                warnings.push('Bulan tidak ditemukan pada sheet ' + sheetName + '.');
                return;
            }

            for (let header = 0; header < rows.length; header++) {
                if (!isHeaderRow(rows[header] || [])) continue;

                /* Format Supervisor: baris NO/NAMA, baris berikutnya tanggal, lalu baris hari. */
                let dateRowIndex = header;
                let dateRow = rows[header] || [];
                let dayColumns = [];
                for (let col = 2; col < dateRow.length; col++) {
                    const day = parseInt(dateRow[col], 10);
                    if (day >= 1 && day <= 31) dayColumns.push({
                        col: col,
                        day: day
                    });
                }
                if (!dayColumns.length && rows[header + 1]) {
                    dateRowIndex = header + 1;
                    dateRow = rows[dateRowIndex] || [];
                    for (let col = 2; col < dateRow.length; col++) {
                        const day = parseInt(dateRow[col], 10);
                        if (day >= 1 && day <= 31) dayColumns.push({
                            col: col,
                            day: day
                        });
                    }
                }
                if (!dayColumns.length) continue;

                const titleRow = header > 0 ? rows[header - 1] || [] : [];
                const compactSupervisorFormat = (dateRowIndex === header + 1 && dayColumns.length >= 28);
                const supervisorBlock = compactSupervisorFormat || isSupervisorTitle(titleRow[0]);
                const rowRole = supervisorBlock ? 'supervisor' : 'ob';
                let currentArea = '';
                let dataFound = false;
                const dataStart = dateRowIndex + 2;

                for (let rowIndex = dataStart; rowIndex < rows.length; rowIndex++) {
                    const row = rows[rowIndex] || [];
                    if (isHeaderRow(row)) break;
                    const first = cleanCell(row[0]);
                    const name = cleanCell(row[1]);

                    if (!first && !name) {
                        if (dataFound) break;
                        continue;
                    }
                    if (isSupervisorTitle(first) && !name) break;
                    if (!supervisorBlock && isAreaRow(row)) {
                        currentArea = first;
                        continue;
                    }
                    if (!name || !/^\d+$/.test(first)) continue;
                    dataFound = true;

                    dayColumns.forEach(function(info) {
                        addSchedule(name, info.day, row[info.col], sheetName, currentArea, rowRole, sheetMonth);
                    });
                }
            }
        });

        const unique = {};
        importRows.forEach(function(item) {
            unique[item.role + '|' + normalizeText(item.nama) + '|' + item.tanggal] = item;
        });
        importRows = Object.keys(unique).map(function(key) {
            return unique[key];
        });
        importRows.sort(function(a, b) {
            return a.tanggal.localeCompare(b.tanggal) || a.role.localeCompare(b.role) || a.nama.localeCompare(b.nama, 'id');
        });

        if (!importRows.length) {
            alert('Tidak ada jadwal yang terbaca. Pastikan format file memiliki kolom NO, NAMA, tanggal, dan data shift.');
            cancelPreview();
            return;
        }

        activeImportMonth = importRows[0].tanggal.slice(0, 7);
        document.getElementById('importMonth').value = activeImportMonth;
        const importSaveMonth = document.getElementById('importSaveMonth');
        if (importSaveMonth) importSaveMonth.value = activeImportMonth;
        if (warnings.length) console.warn('Peringatan import Excel:', warnings);
        renderPreview();
    }

    function renderPreview() {
        const namesByRole = {
            ob: new Set(),
            supervisor: new Set()
        };
        IMPORT_USERS.forEach(function(x) {
            if (namesByRole[x.role]) namesByRole[x.role].add(normalizeText(x.nama));
        });
        const body = document.getElementById('previewBody');
        body.innerHTML = '';
        importRows.forEach(function(r, i) {
            const ok = namesByRole[r.role] && namesByRole[r.role].has(normalizeText(r.nama)) && /^\d{4}-\d{2}-\d{2}$/.test(r.tanggal) && /^(1|2|3|L|Libur|Shift \d)$/i.test(r.shift);
            const roleLabel = r.role === 'supervisor' ? 'Supervisor' : 'OB';
            body.insertAdjacentHTML('beforeend', '<tr><td>' + (i + 1) + '</td><td>' + escapeHtml(r.nama) + '<div style="font-size:9px;color:#64748b;margin-top:3px">' + roleLabel + '</div></td><td>' + r.tanggal + '</td><td>' + escapeHtml(r.shift) + '</td><td>' + (ok ? 'Siap disimpan' : 'Periksa nama/role') + '</td></tr>');
        });
        document.getElementById('importJson').value = JSON.stringify(importRows);
        const saveMonth = document.getElementById('importSaveMonth');
        if (saveMonth) saveMonth.value = activeImportMonth;
        document.getElementById('previewWrap').style.display = 'block';
        document.getElementById('previewActions').style.display = 'flex';
    }

    function escapeHtml(v) {
        return String(v).replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [m];
        });
    }

    function cancelPreview() {
        importRows = [];
        document.getElementById('previewBody').innerHTML = '';
        document.getElementById('previewWrap').style.display = 'none';
        document.getElementById('previewActions').style.display = 'none';
    }
    (function() {
        const selects = document.querySelectorAll('.matrix tbody select');
        selects.forEach(function(select) {
            select.dataset.original = select.value;
            select.addEventListener('change', function() {
                const td = this.closest('td');
                if (td) {
                    td.classList.toggle('changed', this.value !== this.dataset.original);
                    td.classList.toggle('off-cell', this.value === 'L');
                }
                updateRowSummary(this.closest('tr'));
            });
        });
        document.querySelectorAll('[data-user-row]').forEach(updateRowSummary);
        const form = document.getElementById('matrixSaveForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const invalid = [];
                document.querySelectorAll('[data-user-row]').forEach(function(row) {
                    const result = validateRowWeeks(row);
                    if (!result.valid) invalid.push(row.querySelector('.name strong')?.textContent || 'Petugas');
                });
                if (invalid.length && !confirm('Beberapa jadwal mingguan belum sesuai untuk: ' + invalid.join(', ') + '. Tetap simpan?')) {
                    e.preventDefault();
                    return;
                }

                /* Kirim seluruh matrix sebagai satu field JSON untuk menghindari max_input_vars. */
                const matrix = {};
                form.querySelectorAll('select[name^="shift["]]').forEach(function(sel) {
                    const match = sel.name.match(/^shift\[(\d+)\]\[(\d{4}-\d{2}-\d{2})\]$/);
                    if (!match) return;
                    if (!matrix[match[1]]) matrix[match[1]] = {};
                    matrix[match[1]][match[2]] = sel.value;
                });
                const jsonField = document.getElementById('matrixJson');
                if (!jsonField) {
                    e.preventDefault();
                    alert('Field penyimpanan jadwal tidak ditemukan. Muat ulang halaman lalu coba lagi.');
                    return;
                }
                jsonField.value = JSON.stringify(matrix);
                if (jsonField.value === '{}') {
                    e.preventDefault();
                    alert('Data jadwal tidak ditemukan. Muat ulang halaman lalu coba lagi.');
                    return;
                }

                /* Nama select dinonaktifkan agar browser tidak mengirim ribuan input terpisah. */
                form.querySelectorAll('select[name^="shift["]]').forEach(function(sel) {
                    sel.disabled = true;
                });
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
                }
            });
        }
    })();

    function getWeekKey(dateText) {
        const d = new Date(dateText + 'T00:00:00');
        const day = (d.getDay() + 6) % 7;
        d.setDate(d.getDate() - day);
        return d.toISOString().slice(0, 10);
    }

    function validateRowWeeks(row) {
        const weeks = {};
        row.querySelectorAll('select[data-date]').forEach(function(sel) {
            const key = getWeekKey(sel.dataset.date);
            if (!weeks[key]) weeks[key] = {
                work: 0,
                off: 0,
                total: 0
            };
            if (sel.value !== '') weeks[key].total++;
            if (sel.value === 'L') weeks[key].off++;
            else if (sel.value !== '') weeks[key].work++;
        });
        let valid = true,
            complete = 0;
        Object.keys(weeks).forEach(function(k) {
            const w = weeks[k];
            if (w.total === 7) {
                complete++;
                if (w.work !== 5 || w.off !== 2) valid = false;
            }
        });
        return {
            valid: valid,
            complete: complete,
            weeks: weeks
        };
    }

    function updateRowSummary(row) {
        if (!row) return;
        const box = row.querySelector('.week-summary');
        if (!box) return;
        const r = validateRowWeeks(row);
        box.classList.remove('ok', 'bad');
        if (r.complete === 0) {
            box.textContent = '';
            return;
        }
        box.textContent = '';
        box.classList.add(r.valid ? 'ok' : 'bad');
    }

    function clearRow(button) {
        if (!confirm('Kosongkan seluruh jadwal petugas ini pada bulan yang sedang ditampilkan?')) return;
        const row = button.closest('tr');
        row.querySelectorAll('select').forEach(function(select) {
            select.value = '';
            select.dispatchEvent(new Event('change'));
        });
    }

    function clearAllCells() {
        if (!confirm('Kosongkan semua sel pada tampilan? Perubahan baru tersimpan setelah tombol Simpan ditekan.')) return;
        document.querySelectorAll('.matrix tbody select').forEach(function(select) {
            select.value = '';
            select.dispatchEvent(new Event('change'));
        });
    }

    (function initDesktopRowOrdering() {
        if (window.innerWidth <= 1024 || <?= $canManageSchedule ? 'false' : 'true' ?>) return;
        const tbody = document.querySelector('.matrix tbody');
        if (!tbody) return;
        let draggedRow = null;

        const rows = () => Array.from(tbody.querySelectorAll('tr[data-user-row]'));
        rows().forEach(function(row) {
            const handle = row.querySelector('.drag-handle');
            if (!handle) return;
            handle.addEventListener('mousedown', function() {
                row.setAttribute('draggable', 'true');
            });
            handle.addEventListener('mouseup', function() {
                if (!row.classList.contains('dragging')) row.removeAttribute('draggable');
            });
            row.addEventListener('dragstart', function(event) {
                if (!row.getAttribute('draggable')) {
                    event.preventDefault();
                    return;
                }
                draggedRow = row;
                row.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'move';
            });
            row.addEventListener('dragend', function() {
                row.classList.remove('dragging');
                row.removeAttribute('draggable');
                rows().forEach(r => r.classList.remove('drag-over'));
                draggedRow = null;
                updateDesktopRowNumbers();
                saveDesktopRowOrder();
            });
            row.addEventListener('dragover', function(event) {
                if (!draggedRow || draggedRow === row) return;
                if (draggedRow.dataset.groupKey !== row.dataset.groupKey) return;
                event.preventDefault();
                rows().forEach(r => r.classList.remove('drag-over'));
                row.classList.add('drag-over');
                const rect = row.getBoundingClientRect();
                const after = event.clientY > rect.top + rect.height / 2;
                tbody.insertBefore(draggedRow, after ? row.nextSibling : row);
            });
        });

        function updateDesktopRowNumbers() {
            rows().forEach(function(row, index) {
                const number = row.querySelector('.row-number');
                if (number) number.textContent = index + 1;
            });
        }

        async function saveDesktopRowOrder() {
            const order = rows().map(row => Number(row.dataset.userId)).filter(Boolean);
            const data = new FormData();
            data.append('action', 'save_user_order');
            data.append('role', <?= json_encode($roleFilter) ?>);
            data.append('order_json', JSON.stringify(order));
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();
                showOrderToast(result.message || (result.success ? 'Susunan tersimpan.' : 'Susunan gagal disimpan.'), !result.success);
            } catch (error) {
                showOrderToast('Susunan belum tersimpan. Periksa koneksi lalu coba lagi.', true);
            }
        }

        function showOrderToast(message, isError) {
            document.querySelector('.order-save-toast')?.remove();
            const toast = document.createElement('div');
            toast.className = 'order-save-toast';
            if (isError) toast.style.background = '#b91c1c';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2400);
        }
    })();

    (function initMobileCalendar() {
        const rows = Array.from(document.querySelectorAll('[data-user-row]'));
        if (!rows.length) return;
        rows.forEach(row => {
            const cells = row.querySelectorAll('.schedule-day-cell');
            if (cells.length) {
                const first = cells[0];
                first.classList.add('first-calendar-day');
                first.style.setProperty('--calendar-start', first.dataset.weekday || '1');
            }
        });
        let index = 0;
        const name = document.getElementById('mobileCalendarName'),
            counter = document.getElementById('mobileCalendarCounter');

        function show(i) {
            index = (i + rows.length) % rows.length;
            rows.forEach((r, n) => r.classList.toggle('mobile-active', n === index));
            const n = rows[index].querySelector('.name strong');
            if (name) name.textContent = n ? n.textContent : 'Petugas';
            if (counter) counter.textContent = (index + 1) + ' dari ' + rows.length;
        }
        document.getElementById('mobilePrevUser')?.addEventListener('click', () => show(index - 1));
        document.getElementById('mobileNextUser')?.addEventListener('click', () => show(index + 1));
        show(0);
    })();
</script>
<?php include 'footer.php'; ?>