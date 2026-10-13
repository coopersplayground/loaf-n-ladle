<?php
// Simple file server for uploads
$filename = get('file');
if (!$filename) {
    http_response_code(404);
    exit('File not found');
}

// Security: only allow specific file types and prevent directory traversal
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    http_response_code(403);
    exit('File type not allowed');
}

// Prevent directory traversal
$filename = basename($filename);
$filepath = APP_ROOT . '/uploads/' . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('File not found');
}

// Set appropriate content type
$contentTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('Content-Length: ' . filesize($filepath));

readfile($filepath);
exit;
