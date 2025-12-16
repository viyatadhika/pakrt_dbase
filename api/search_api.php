<?php
include '../config.php';


$q = $_GET['q'] ?? '';

$q = $conn->real_escape_string($q);

$sql = "
SELECT id, form_type, nama_petugas, area_kerja, area_gedung, lantai, rumah, pos_jaga, tanggal
FROM checklist_forms
WHERE 
    nama_petugas LIKE '%$q%' OR
    form_type LIKE '%$q%' OR
    area_kerja LIKE '%$q%' OR
    area_gedung LIKE '%$q%' OR
    rumah LIKE '%$q%' OR
    pos_jaga LIKE '%$q%'
ORDER BY tanggal DESC
LIMIT 40
";

$res = $conn->query($sql);

$data = [];

while ($row = $res->fetch_assoc()) {

    // Lokasi rapi
    $loc = [];
    foreach (['area_kerja', 'area_gedung', 'lantai', 'rumah', 'pos_jaga'] as $k) {
        if (!empty($row[$k])) $loc[] = $row[$k];
    }

    $data[] = [
        "id"         => $row['id'],
        "form_type"  => ucwords(str_replace(['_', '-'], ' ', $row['form_type'])),
        "nama_petugas" => $row['nama_petugas'],
        "lokasi"     => implode(" • ", $loc),
        "tanggal"    => date("d M Y", strtotime($row['tanggal']))
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
