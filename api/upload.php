<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Use POST to upload a file.']);
}

if (!extension_loaded('curl') || !extension_loaded('fileinfo')) {
    error_log('Firebase upload requires the PHP cURL and fileinfo extensions.');
    respond(503, ['error' => 'The upload service is not configured on this server.']);
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    respond(503, ['error' => 'The upload service configuration is missing.']);
}

$config = require $configPath;
if (
    !is_array($config)
    || empty($config['storage_bucket'])
) {
    respond(503, ['error' => 'The upload service configuration is incomplete.']);
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authorization = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+([A-Za-z0-9._-]{100,4096})$/i', trim($authorization), $matches)) {
    respond(401, ['error' => 'Sign in again before uploading.']);
}
$idToken = $matches[1];

$type = $_POST['type'] ?? '';
$entityId = $_POST['entityId'] ?? '';
$uploadTargets = [
    'shop-logo' => 'shops',
    'product-image' => 'products',
    'document' => 'documents',
];
if (!is_string($type) || !isset($uploadTargets[$type])) {
    respond(400, ['error' => 'Choose a supported upload type.']);
}
if (!is_string($entityId) || !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $entityId)) {
    respond(400, ['error' => 'The shop or product ID is invalid.']);
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])) {
    respond(400, ['error' => 'Choose a file to upload.']);
}
if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    respond(400, ['error' => 'The file upload did not complete.']);
}

$maximumBytes = $type === 'document' ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
if ((int) $file['size'] <= 0 || (int) $file['size'] > $maximumBytes) {
    respond(413, ['error' => $type === 'document' ? 'Documents must be 10 MB or smaller.' : 'Images must be 5 MB or smaller.']);
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowedTypes = $type === 'document'
    ? ['application/pdf' => 'pdf']
    : ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
    respond(415, ['error' => $type === 'document' ? 'Only PDF documents are supported.' : 'Use a JPG, PNG, or WebP image.']);
}

$contents = file_get_contents($file['tmp_name']);
if ($contents === false) {
    respond(400, ['error' => 'The uploaded file could not be read.']);
}

$objectName = sprintf(
    'uploads/%s/%s/%s.%s',
    $uploadTargets[$type],
    $entityId,
    bin2hex(random_bytes(16)),
    $allowedTypes[$mimeType]
);
$storageUrl = sprintf(
    'https://firebasestorage.googleapis.com/v0/b/%s/o?uploadType=media&name=%s',
    rawurlencode((string) $config['storage_bucket']),
    rawurlencode($objectName)
);
$storageRequest = curl_init($storageUrl);
if ($storageRequest === false) {
    respond(503, ['error' => 'Could not initialize the storage upload.']);
}
curl_setopt_array($storageRequest, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $contents,
    CURLOPT_HTTPHEADER => [
        'Authorization: Firebase ' . $idToken,
        'Content-Type: ' . $mimeType,
        'Content-Length: ' . strlen($contents),
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
]);
$storageResponse = curl_exec($storageRequest);
$storageStatus = (int) curl_getinfo($storageRequest, CURLINFO_RESPONSE_CODE);
$storageError = curl_error($storageRequest);
curl_close($storageRequest);

if ($storageResponse === false) {
    error_log('Firebase Storage upload failed: ' . $storageError);
    respond(503, ['error' => 'Could not reach Firebase Storage. Please retry.']);
}
if ($storageStatus < 200 || $storageStatus >= 300) {
    error_log('Firebase Storage rejected an upload with HTTP ' . $storageStatus . ': ' . $storageResponse);
    respond($storageStatus === 401 || $storageStatus === 403 ? 403 : 502, [
        'error' => 'Firebase Storage rejected the upload. Check that Storage is enabled and its rules allow the verified admin.',
    ]);
}

respond(201, [
    'path' => $objectName,
    'contentType' => $mimeType,
    'size' => (int) $file['size'],
]);
