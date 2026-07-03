<?php

declare(strict_types=1);

require_once __DIR__ . '/../db_conn.php';

ApiSupport::jsonResponse([
    'ok' => true,
    'name' => 'WhatsApp API',
    'auth' => [
        'header' => 'Authorization: Bearer wpi_live_your_business_api_key',
        'alt_header' => 'X-API-KEY: wpi_live_your_business_api_key',
        'note' => 'Generate a business API key from Business > WhatsApp Connection.',
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
            'examples' => [
                'authentication_otp' => [
                    'kind' => 'authentication',
                    'template_name' => 'login_otp',
                    'language' => 'en_US',
                    'to' => '+919876543210',
                    'otp' => '123456',
                ],
                'utility_or_marketing_template' => [
                    'kind' => 'utility',
                    'template_name' => 'order_update',
                    'recipients' => ['+919876543210'],
                    'parameters' => ['A10045', 'Shipped'],
                ],
            ],
        ],
    ],
], 200);
