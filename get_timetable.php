<?php
session_start();
include 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'read';
$role   = strtolower($_SESSION['user']['role'] ?? '');

// ✅ Action 'read' boleh diakses tanpa login (publik)
// create / update / delete / cancel tetap butuh login + role admin
if ($action !== 'read') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['error' => 'Belum login']);
        exit;
    }
    if ($role !== 'admin') {
        echo json_encode(['error' => 'Tidak diizinkan']);
        exit;
    }
}

switch ($action) {

    case 'read':
        $q = $conn->query("
            SELECT
                id,
                judul,
                DATE(start_date) AS start,
                DATE(end_date)   AS end,
                kategori         AS pny,
                asrama,
                peserta,
                kelas,
                makan,
                status
            FROM agenda_kegiatan
            ORDER BY start_date ASC
        ");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'create':
        $stmt = $conn->prepare("
            INSERT INTO agenda_kegiatan
            (judul, start_date, end_date, kategori, asrama, peserta, kelas, makan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssiss",
            $_POST['judul'],
            $_POST['start'],
            $_POST['end'],
            $_POST['pny'],
            $_POST['asrama'],
            $_POST['peserta'],
            $_POST['kelas'],
            $_POST['makan']
        );
        $stmt->execute();
        echo json_encode(['status' => 'ok']);
        break;

    case 'update':
        $stmt = $conn->prepare("
            UPDATE agenda_kegiatan SET
            judul=?, start_date=?, end_date=?, kategori=?, asrama=?, peserta=?, kelas=?, makan=?
            WHERE id=?
        ");
        $stmt->bind_param(
            "sssssissi",
            $_POST['judul'],
            $_POST['start'],
            $_POST['end'],
            $_POST['pny'],
            $_POST['asrama'],
            $_POST['peserta'],
            $_POST['kelas'],
            $_POST['makan'],
            $_POST['id']
        );
        $stmt->execute();
        echo json_encode(['status' => 'updated']);
        break;

    case 'cancel':
        // Toggle: active → cancelled, cancelled → active (reaktivasi)
        $id         = intval($_POST['id']);
        $newStatus  = $_POST['new_status'] === 'cancelled' ? 'cancelled' : 'active';
        $stmt = $conn->prepare("UPDATE agenda_kegiatan SET status=? WHERE id=?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        echo json_encode(['status' => $newStatus]);
        break;

    case 'delete':
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM agenda_kegiatan WHERE id=$id");
        echo json_encode(['status' => 'deleted']);
        break;
}
