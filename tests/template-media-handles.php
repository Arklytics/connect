<?php

declare(strict_types=1);
require_once __DIR__ . '/../app/ApiSupport.php';

$checks = 0;
foreach (['IMAGE' => 'image/jpeg', 'VIDEO' => 'video/mp4', 'DOCUMENT' => 'application/pdf'] as $type => $mime) {
    $handle = '4:ZmlsZQ==:' . $mime . ':opaque:e:' . (time() + 3600) . ':signature';
    if (ApiSupport::templateMediaHandleError($handle, $type) !== '') {
        throw new RuntimeException('Valid handle rejected for ' . $type);
    }
    foreach (['123456789', 'https://example.com/file', '', str_replace((string) (time() + 3600), '1', $handle)] as $invalid) {
        if (ApiSupport::templateMediaHandleError($invalid, $type) === '') {
            throw new RuntimeException('Invalid or expired handle accepted for ' . $type);
        }
        $checks++;
    }
    if (ApiSupport::templateMediaHandleError($handle, $type === 'IMAGE' ? 'VIDEO' : 'IMAGE', $mime) === '') {
        throw new RuntimeException('Mismatched media accepted');
    }
    $checks += 2;
}
// Opaque and encoded fields must never be mistaken for the file MIME type.
foreach (['4::aW1hZ2UvanBlZw==:opaque:signature', '4:filename:aW1hZ2UvcG5n:opaque:signature', 'future-opaque-handle'] as $handle) {
    foreach (['IMAGE', 'VIDEO', 'DOCUMENT'] as $type) {
        if (ApiSupport::templateMediaHandleError($handle, $type) !== '') {
            throw new RuntimeException('Opaque handle rejected');
        }
        $checks++;
    }
}
$first = '4::aW1hZ2UvanBlZw==:signatureA:e:1999999999:app:user:signedA';
$second = '4::aW1hZ2UvanBlZw==:signatureB:e:1999999999:app:user:signedB';
foreach ([$first, $first . "\n" . $second, $first . "\r\n" . $second, $first . $second, $first . ' ' . $second] as $raw) {
    if (ApiSupport::normalizeTemplateMediaHandle($raw) !== $first) {
        throw new RuntimeException('Multiple upload handles were not reduced to one intact handle');
    }
    $checks++;
}
foreach (['2:ZmlsZQ==:image/jpeg:signature', '', 'opaque-handle'] as $raw) {
    if (ApiSupport::normalizeTemplateMediaHandle($raw) !== $raw) {
        throw new RuntimeException('Single handle changed');
    }
    $checks++;
}
$result = ApiSupport::metaUploadMediaHandle('', '', __DIR__ . '/missing-media-file', 'file.jpg', 'image/jpeg', 10);
if ($result['ok'] || empty($result['error'])) {
    throw new RuntimeException('Missing upload file accepted');
}
echo ($checks + 1) . " media handle checks passed.\n";
