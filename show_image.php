<?php
// show_image.php
// Serve image dari luar public_html

$BASE_DIR = '/home/develintegrasi/wargart/'; // PATH FISIK FOTO

if (!isset($_GET['f'])) {
    http_response_code(400);
    exit;
}

$rel = ltrim($_GET['f'], '/');

// cegah directory traversal
$rel = str_replace(['../', '..\\'], '', $rel);

$fullPath = realpath($BASE_DIR . $rel);

if (!$fullPath || !file_exists($fullPath)) {
    http_response_code(404);
    exit;
}

// MIME TYPE
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $fullPath);
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=86400');

readfile($fullPath);
exit;
