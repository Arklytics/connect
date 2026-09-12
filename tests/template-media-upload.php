<?php
// Run with php -n: isolate cURL behind a fake transport; no network requests.
declare(strict_types=1);
if (extension_loaded('curl')) {
    throw new RuntimeException('Run this test with php -n to disable real cURL.');
}
foreach (['CURLOPT_POST', 'CURLOPT_RETURNTRANSFER', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_HTTPHEADER', 'CURLOPT_POSTFIELDS', 'CURLOPT_CUSTOMREQUEST', 'CURLINFO_HTTP_CODE'] as $index => $constant) {
    define($constant, $index + 1);
}
$requests = [];
$first = '4::aW1hZ2UvanBlZw==:opaque:e:1999999999:app:user:signature';
$second = '4::aW1hZ2UvanBlZw==:opaque2:e:1999999999:app:user:signature2';
function curl_init($url) { return (object) ['url' => $url, 'options' => []]; }
function curl_setopt_array($ch, $options) { $ch->options = $options; return true; }
function curl_exec($ch) {
    global $requests, $first, $second;
    $requests[] = $ch;
    return count($requests) === 1 ? json_encode(['id' => 'upload:session?sig=signed']) : json_encode(['h' => $first . "\n" . $second]);
}
function curl_getinfo($ch, $option) { return 200; }
function curl_error($ch) { return ''; }
function curl_close($ch) {}
require_once __DIR__ . '/../app/ApiSupport.php';
$file = tempnam(sys_get_temp_dir(), 'media-test-');
try {
    $bytes = "image bytes\x00\xff";
    file_put_contents($file, $bytes);
    $result = ApiSupport::metaUploadMediaHandle('app', 'test-token', $file, 'image.jpg', 'image/jpeg', 999);
    if (!$result['ok'] || $result['handle'] !== $first || count($requests) !== 2) {
        throw new RuntimeException('Upload must return exactly one complete handle');
    }
    parse_str($requests[0]->options[CURLOPT_POSTFIELDS], $session);
    if ($session['file_length'] != strlen($bytes) || $session['file_type'] !== 'image/jpeg') {
        throw new RuntimeException('Session metadata does not match uploaded bytes');
    }
    if ($requests[1]->options[CURLOPT_POSTFIELDS] !== $bytes || !in_array('file_offset: 0', $requests[1]->options[CURLOPT_HTTPHEADER], true)) {
        throw new RuntimeException('Binary upload changed the file or omitted the offset');
    }
    echo "Upload transport checks passed (no network).\n";
} finally {
    unlink($file);
}
