<?php
declare(strict_types=1);

$template = file_get_contents(__DIR__ . '/index.html');
if ($template === false) {
    http_response_code(500);
    exit('The sign-in page is unavailable.');
}

header('Content-Type: text/html; charset=utf-8');
echo str_replace('./mobile.html', './mobile.php', $template);
