<?php
// api/upload.php
require_once '../core/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $uploadDir = '../assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];
        $msg = isset($errorMessages[$file['error']]) ? $errorMessages[$file['error']] : 'Unknown upload error.';
        http_response_code(400);
        echo json_encode(['error' => $msg]);
        exit;
    }

    // Ensure filename is safe and has no weird characters
    $originalName = basename($file['name']);
    $safeName = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $originalName);
    $safeName = rtrim($safeName, '.');
    if (empty($safeName)) {
        $safeName = 'upload';
    }
    $filename = uniqid() . '_' . $safeName;
    $dest = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['location' => 'assets/uploads/' . $filename]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file. Check directory permissions.']);
    }
}
