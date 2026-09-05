<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/ApiSupport.php';

$checks = 0;
foreach (['IMAGE', 'VIDEO', 'DOCUMENT'] as $headerType) {
    foreach ([
        ['https://example.com/stored', '', '', 'https://example.com/stored'],
        ['https://example.com/stored', 'https://example.com/linked', '', 'https://example.com/linked'],
        ['https://example.com/stored', 'https://example.com/linked', 'https://example.com/chosen', 'https://example.com/chosen'],
        ['https://example.com/stored', '   ', '   ', 'https://example.com/stored'],
        ['', '', '', null],
    ] as [$stored, $linked, $chosen, $expected]) {
        $template = [
            'media_url' => $stored,
            'placeholders' => json_encode(['header_type' => $headerType, 'header_media_url' => $linked]),
            'message_body' => 'Hello',
        ];
        $result = ApiSupport::buildTemplateSendComponents(
            $template,
            ApiSupport::templateSendValuesFromInput(['header_media_url' => $chosen])
        );
        $actual = $result['components'][0]['parameters'][0][strtolower($headerType)]['link'] ?? null;
        if ($actual !== $expected || ($expected === null && empty($result['error']))) {
            throw new RuntimeException('Media URL fallback failed for ' . $headerType);
        }
        $checks++;
    }
}
echo $checks . " media send checks passed.\n";
