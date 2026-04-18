<?php
// api/upload.php
require_once '../core/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $uploadDir = '../assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $dest = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['location' => 'assets/uploads/' . $filename]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Upload failed']);
    }
}