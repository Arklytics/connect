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
    if (ApiSupport::templateMediaHandleError($handle, $type === 'IMAGE' ? 'VIDEO' : 'IMAGE') === '') {
        throw new RuntimeException('Mismatched media accepted');
    }
    $checks += 2;
}
$result = ApiSupport::metaUploadMediaHandle('', '', __DIR__ . '/missing-media-file', 'file.jpg', 'image/jpeg', 10);
if ($result['ok'] || empty($result['error'])) {
    throw new RuntimeException('Missing upload file accepted');
}
echo ($checks + 1) . " media handle checks passed.\n";
