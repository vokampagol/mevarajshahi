<?php
declare(strict_types=1);

$localSecrets = __DIR__ . '/secrets.local.php';
$secrets = is_file($localSecrets) ? require $localSecrets : [];
if (!is_array($secrets)) {
    throw new RuntimeException('The local API secrets file must return an array.');
}

return array_merge($secrets, [
    'storage_bucket' => 'sapahar-xpress.firebasestorage.app',
    'gemini_api_key' => trim((string) (getenv('GEMINI_API_KEY') ?: ($secrets['gemini_api_key'] ?? ''))),
    'firebase_web_api_key' => trim((string) (getenv('FIREBASE_WEB_API_KEY') ?: ($secrets['firebase_web_api_key'] ?? ''))),
]);
