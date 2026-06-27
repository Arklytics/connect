<?php

declare(strict_types=1);

require_once __DIR__ . '/../db_conn.php';

ApiSupport::jsonResponse([
    'ok' => true,
    'name' => 'WhatsApp API',
    'auth' => [
        'header' => 'Authorization: Bearer ' . (Config::get('API_TOKEN', '') !== '' ? 'your_api_token' : 'configure_api_token_first'),
        'alt_header' => 'X-API-KEY: your_api_token',
    ],
    'endpoints' => [
        [
            'method' => 'POST',
            'path' => '/api/contacts/import',
            'description' => 'Import one or many contacts into a business workspace.',
        ],
        [
            'method' => 'POST',
            'path' => '/api/whatsapp/send',
            'description' => 'Send WhatsApp text or template messages for authentication, utility, and marketing use cases.',
        ],
    ],
], 200);
