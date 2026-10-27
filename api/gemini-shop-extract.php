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

function postJson(string $url, array $payload, array $headers = []): array
{
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Could not initialize the AI request.');
    }

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($response === false) {
        throw new RuntimeException('Could not reach the identity or Gemini service: ' . $error);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('The identity or Gemini service returned an invalid response.');
    }
    return [$status, $decoded];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Use POST to scan a visiting card.']);
}

if (!extension_loaded('curl') || !extension_loaded('fileinfo')) {
    error_log('Gemini extraction requires the PHP cURL and fileinfo extensions.');
    respond(503, ['error' => 'The AI scan service is not configured on this server.']);
}

$config = require __DIR__ . '/config.php';
if (
    !is_array($config)
    || empty($config['gemini_api_key'])
    || empty($config['firebase_web_api_key'])
    || str_starts_with((string) $config['gemini_api_key'], 'PASTE_')
    || str_starts_with((string) $config['firebase_web_api_key'], 'PASTE_')
) {
    error_log('Gemini extraction requires GEMINI_API_KEY and FIREBASE_WEB_API_KEY on the web server.');
    respond(503, ['error' => 'AI scanning is not configured. Set GEMINI_API_KEY and FIREBASE_WEB_API_KEY on the server.']);
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authorization = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+([A-Za-z0-9._-]{100,4096})$/i', trim($authorization), $matches)) {
    respond(401, ['error' => 'Sign in again before scanning a card.']);
}

try {
    [$identityStatus, $identity] = postJson(
        'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='
            . rawurlencode((string) $config['firebase_web_api_key']),
        ['idToken' => $matches[1]]
    );
} catch (Throwable $error) {
    error_log('Firebase ID token verification failed: ' . $error->getMessage());
    respond(503, ['error' => 'Could not verify your sign-in. Please retry.']);
}

$account = $identity['users'][0] ?? null;
if ($identityStatus !== 200 || !is_array($account)) {
    respond($identityStatus === 400 ? 401 : 503, [
        'error' => $identityStatus === 400
            ? 'Your sign-in expired. Sign in again before scanning.'
            : 'Could not verify your sign-in. Please retry.',
    ]);
}
$isVerifiedAdmin = strtolower((string) ($account['email'] ?? '')) === 'sapaharitsolution@gmail.com'
    && filter_var($account['emailVerified'] ?? false, FILTER_VALIDATE_BOOLEAN)
    && isset($account['localId'])
    && is_string($account['localId']);
if (!$isVerifiedAdmin) {
    respond(403, ['error' => 'Only the verified Sapahar Xpress admin can use card scanning.']);
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])) {
    respond(400, ['error' => 'Choose a visiting card image or PDF.']);
}
if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    respond(400, ['error' => 'The visiting card upload did not complete.']);
}
if ((int) $file['size'] <= 0 || (int) $file['size'] > 10 * 1024 * 1024) {
    respond(413, ['error' => 'Images and PDFs must be 10 MB or smaller.']);
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!is_string($mimeType) || !in_array($mimeType, $allowedTypes, true)) {
    respond(415, ['error' => 'Use a JPG, PNG, WebP image, or PDF file.']);
}
$contents = file_get_contents($file['tmp_name']);
if ($contents === false) {
    respond(400, ['error' => 'The visiting card file could not be read.']);
}

$prompt = 'Read this business visiting card and extract the shop information. '
    . 'Choose exactly one shop category from Medicine, Mobile, Grocery, Food, Dealer, Other. '
    . 'Use Other if the business is unclear or does not fit. Do not guess missing details; use empty strings. '
    . 'Return phone numbers as one short string, and include a Google Maps URL only if it is actually present.';
$payload = [
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode($contents)]],
        ],
    ]],
    'generationConfig' => [
        'temperature' => 0.2,
        'maxOutputTokens' => 512,
        'responseMimeType' => 'application/json',
        'responseSchema' => [
            'type' => 'OBJECT',
            'properties' => [
                'title' => ['type' => 'STRING'],
                'category' => ['type' => 'STRING', 'enum' => ['Medicine', 'Mobile', 'Grocery', 'Food', 'Dealer', 'Other']],
                'area' => ['type' => 'STRING'],
                'address' => ['type' => 'STRING'],
                'phone' => ['type' => 'STRING'],
                'whatsapp' => ['type' => 'STRING'],
                'price_range' => ['type' => 'STRING'],
                'google_maps_link' => ['type' => 'STRING'],
            ],
            'required' => ['title', 'category', 'area', 'address', 'phone', 'whatsapp', 'price_range', 'google_maps_link'],
            'propertyOrdering' => ['title', 'category', 'area', 'address', 'phone', 'whatsapp', 'price_range', 'google_maps_link'],
        ],
    ],
];

$geminiModels = ['gemini-3.8-flash', 'gemini-3.6-flash'];
$geminiStatus = 0;
$geminiResponse = [];
$usedGeminiModel = '';
foreach ($geminiModels as $index => $model) {
    try {
        [$geminiStatus, $geminiResponse] = postJson(
            'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key='
                . rawurlencode((string) $config['gemini_api_key']),
            $payload
        );
    } catch (Throwable $error) {
        error_log('Gemini visiting card extraction failed for ' . $model . ': ' . $error->getMessage());
        respond(502, ['error' => 'Could not reach Gemini. Please retry.']);
    }

    $usedGeminiModel = $model;
    if ($geminiStatus >= 200 && $geminiStatus < 300) {
        break;
    }

    if (
        in_array($geminiStatus, [500, 502, 503, 504], true)
        && $index < count($geminiModels) - 1
    ) {
        error_log('Gemini model ' . $model . ' returned HTTP ' . $geminiStatus . '; trying fallback model.');
        continue;
    }
    break;
}

if ($geminiStatus < 200 || $geminiStatus >= 300) {
    $upstreamError = $geminiResponse['error'] ?? [];
    $upstreamStatus = is_array($upstreamError) && is_string($upstreamError['status'] ?? null)
        ? strtoupper($upstreamError['status'])
        : 'UNKNOWN';
    $upstreamMessage = is_array($upstreamError) && is_string($upstreamError['message'] ?? null)
        ? strtolower($upstreamError['message'])
        : '';
    $isKeyError = str_contains($upstreamStatus, 'API_KEY')
        || str_contains($upstreamMessage, 'api key')
        || str_contains($upstreamMessage, 'api_key');

    error_log('Gemini visiting card extraction failed for ' . $usedGeminiModel
        . ' with HTTP ' . $geminiStatus . ' (' . $upstreamStatus . ').');

    if ($isKeyError || $geminiStatus === 403) {
        respond(502, [
            'error' => 'Gemini rejected the server API key (HTTP ' . $geminiStatus
                . '). Check that the key is current, the Generative Language API is enabled, and key restrictions allow this server.',
        ]);
    }

    respond($geminiStatus === 429 ? 429 : 502, [
        'error' => $geminiStatus === 429
            ? 'Gemini quota or rate limit reached (HTTP 429). Check the API project quota and billing, then retry.'
            : ($geminiStatus === 400
                ? 'Gemini rejected the request (HTTP 400). Check the selected file and server API configuration.'
                : ($geminiStatus === 404
                    ? 'Gemini model or API endpoint was not found (HTTP 404). Check model availability for this API project.'
                    : (in_array($geminiStatus, [500, 502, 503, 504], true)
                        ? 'Gemini is temporarily unavailable (HTTP ' . $geminiStatus
                            . ') on the primary and fallback models. Wait briefly and try again.'
                        : 'Gemini request failed (HTTP ' . $geminiStatus . '). Check cPanel error logs and retry.'))),
    ]);
}

$responseText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
$extracted = is_string($responseText) ? json_decode($responseText, true) : null;
if (!is_array($extracted)) {
    error_log('Gemini returned no valid visiting card JSON.');
    respond(502, ['error' => 'Gemini returned an invalid result. Try a clearer image or PDF.']);
}

$stringField = static function (string $field, int $maxLength) use ($extracted): string {
    $value = $extracted[$field] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }
    preg_match('/^.{0,' . $maxLength . '}/us', $value, $matches);
    return $matches[0] ?? '';
};
$categories = ['Medicine', 'Mobile', 'Grocery', 'Food', 'Dealer', 'Other'];
$category = $stringField('category', 20);
respond(200, [
    'title' => $stringField('title', 120),
    'category' => in_array($category, $categories, true) ? $category : 'Other',
    'area' => $stringField('area', 120),
    'address' => $stringField('address', 240),
    'phone' => $stringField('phone', 40),
    'whatsapp' => $stringField('whatsapp', 40),
    'price_range' => $stringField('price_range', 80),
    'google_maps_link' => $stringField('google_maps_link', 500),
]);
