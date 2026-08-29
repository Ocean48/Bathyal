<?php

namespace App\Services;

use Exception;

class FileUploadService
{
    private const MAX_FILE_SIZE = 52428800; // 50MB in bytes

    private const ALLOWED_EXTENSIONS = [
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        // Documents & Presentations & Spreadsheets
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'rtf' => 'application/rtf',
        'zip' => 'application/zip',
    ];

    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'phps',
        'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bat', 'cmd', 'js', 'vbs', 'html', 'htm'
    ];

    /**
     * Store an uploaded file in the appropriate directory structure.
     * Task files are grouped inside public/uploads/tasks/{task_id}/
     * Standalone workspace files are stored inside public/uploads/documents/
     *
     * @param array $file Normalized file array from Request
     * @param int|null $taskId Optional task ID for folder grouping
     * @return array File storage details
     * @throws Exception On validation or write failure
     */
    public static function store(array $file, ?int $taskId = null): array
    {
        if (!isset($file['name'], $file['tmp_name'], $file['size'])) {
            throw new Exception('Invalid upload file data provided.');
        }

        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception(self::getUploadErrorMessage($file['error']));
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new Exception('File exceeds maximum allowed size of 50MB.');
        }

        $originalName = basename($file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (empty($extension) || in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new Exception("File type '.{$extension}' is not allowed for security reasons.");
        }

        if (!array_key_exists($extension, self::ALLOWED_EXTENSIONS)) {
            throw new Exception("Unsupported file extension '.{$extension}'. Allowed formats: " . implode(', ', array_keys(self::ALLOWED_EXTENSIONS)));
        }

        $mimeType = self::ALLOWED_EXTENSIONS[$extension] ?? ($file['type'] ?? 'application/octet-stream');

        // Target directory determination
        $publicPath = dirname(__DIR__, 2) . '/public';
        if ($taskId !== null && $taskId > 0) {
            $relativeDir = '/uploads/tasks/' . $taskId;
        } else {
            $relativeDir = '/uploads/documents';
        }

        $targetDir = $publicPath . $relativeDir;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                throw new Exception("Failed to initialize target upload directory: {$relativeDir}");
            }
        }

        // Generate sanitized unique disk name
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $safeBase = substr($safeBase, 0, 40);
        $diskName = sprintf('%s_%s_%s.%s', time(), bin2hex(random_bytes(4)), $safeBase, $extension);
        $targetFile = $targetDir . '/' . $diskName;
        $relativeFilePath = $relativeDir . '/' . $diskName;

        // Move or copy file
        if (is_uploaded_file($file['tmp_name'])) {
            if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
                throw new Exception('Failed to move uploaded file to destination.');
            }
        } else {
            // For unit/integration tests and CLI uploads
            if (!copy($file['tmp_name'], $targetFile)) {
                throw new Exception('Failed to copy file to destination.');
            }
        }

        return [
            'original_name' => $originalName,
            'file_name' => $diskName,
            'file_path' => $relativeFilePath,
            'file_size' => (int)$file['size'],
            'mime_type' => $mimeType,
            'file_extension' => $extension,
        ];
    }

    /**
     * Remove physical file from disk.
     */
    public static function delete(?string $relativeFilePath): bool
    {
        if (empty($relativeFilePath)) {
            return false;
        }

        $publicPath = dirname(__DIR__, 2) . '/public';
        $fullPath = $publicPath . '/' . ltrim($relativeFilePath, '/');

        if (file_exists($fullPath) && is_file($fullPath)) {
            return @unlink($fullPath);
        }

        return false;
    }

    /**
     * Translate PHP upload error codes into human-readable messages.
     */
    private static function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error occurred.',
        };
    }
}
