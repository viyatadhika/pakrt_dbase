<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Belum login']);
    exit;
}

require 'config.php';

$nip  = $_SESSION['user']['nip']  ?? '';
$role = $_SESSION['user']['role'] ?? '';

// ✅ Hanya pimpinan, admin, supervisor yang boleh react
$allowedRoles = ['admin', 'pimpinan', 'supervisor'];
if (!in_array(strtolower($role), $allowedRoles)) {
    echo json_encode(['error' => 'Tidak diizinkan']);
    exit;
}

$form_id = (int)($_POST['form_id'] ?? 0);
$foto_id = (int)($_POST['foto_id'] ?? 0);
$emoji   = trim($_POST['emoji']   ?? '');

if (!$form_id || !$foto_id || !$emoji) {
    echo json_encode(['error' => 'Data tidak lengkap']);
    exit;
}

/* === CEK REACTION USER DI FOTO INI === */
$stmt = $conn->prepare(
    "SELECT id, emoji FROM checklist_reactions
     WHERE form_id=? AND foto_id=? AND nip_user=?"
);
$stmt->bind_param("iis", $form_id, $foto_id, $nip);
$stmt->execute();
$exist = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* === TOGGLE LOGIC === */
if ($exist) {
    if ($exist['emoji'] === $emoji) {
        // Emoji sama → hapus (toggle off)
        $del = $conn->prepare("DELETE FROM checklist_reactions WHERE id=?");
        $del->bind_param("i", $exist['id']);
        $del->execute();
        $del->close();
    } else {
        // Emoji beda → ganti
        $up = $conn->prepare(
            "UPDATE checklist_reactions SET emoji=?, is_read=0 WHERE id=?"
        );
        $up->bind_param("si", $emoji, $exist['id']);
        $up->execute();
        $up->close();
    }
} else {
    // Belum pernah react → insert baru
    $ins = $conn->prepare(
        "INSERT INTO checklist_reactions
         (form_id, foto_id, nip_user, emoji, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())
         ON DUPLICATE KEY UPDATE emoji=VALUES(emoji), is_read=0"
    );
    $ins->bind_param("iiss", $form_id, $foto_id, $nip, $emoji);
    $ins->execute();
    $ins->close();
}

/* === SUMMARY (emoji → jumlah) === */
$summary = [];
$stmt = $conn->prepare(
    "SELECT emoji, COUNT(*) AS total
     FROM checklist_reactions
     WHERE form_id=? AND foto_id=?
     GROUP BY emoji"
);
$stmt->bind_param("ii", $form_id, $foto_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $summary[$r['emoji']] = (int)$r['total'];
}
$stmt->close();

/* === SIMPAN SUMMARY KE checklist_fotos === */
$summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE);
$upFoto = $conn->prepare(
    "UPDATE checklist_fotos SET reactions=? WHERE id=?"
);
$upFoto->bind_param("si", $summaryJson, $foto_id);
$upFoto->execute();
$upFoto->close();

/* === USERS (siapa saja yang react) === */
$users = [];
$stmt = $conn->prepare(
    "SELECT u.nama, r.nip_user, r.emoji
     FROM checklist_reactions r
     JOIN users u ON u.nip = r.nip_user
     WHERE r.form_id=? AND r.foto_id=?
     ORDER BY r.created_at ASC"
);
$stmt->bind_param("ii", $form_id, $foto_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $users[] = $r;
}
$stmt->close();

echo json_encode([
    'summary' => $summary,
    'users'   => $users
]);
exit;
