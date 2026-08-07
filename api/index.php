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
            'method' => 'GET/POST',
            'path' => '/api/groups',
            'description' => 'List groups and subgroups, or create a main group/subgroup with parent_id.',
        ],
        [
            'method' => 'POST',
            'path' => '/api/contacts/import',
            'description' => 'Import one or many contacts into a business workspace.',
        ],
        [
            'method' => 'POST',
            'path' => '/api/templates/create',
            'description' => 'Create a WhatsApp Cloud API message template and save it to the business template library.',
            'supported_headers' => ['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'],
            'example' => [
                'template_name' => 'order_update',
                'category' => 'UTILITY',
                'language' => 'en_US',
                'header_type' => 'TEXT',
                'header_text' => 'Order update',
                'body_text' => 'Hi {{1}}, your order {{2}} is {{3}}.',
                'body_samples' => ['1' => 'Nisha', '2' => 'A10045', '3' => 'Shipped'],
                'footer_text' => 'Thank you',
            ],
        ],
        [
            'method' => 'POST',
            'path' => '/api/whatsapp/send',
            'description' => 'Send WhatsApp text or template messages for authentication, utility, and marketing use cases.',
            'recipient_fields' => [
                'to',
                'phone_numbers',
                'recipients',
                'contact_ids',
                'subgroup_id',
                'subgroup_ids',
            ],
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
                    'subgroup_id' => 18,
                    'parameters' => ['A10045', 'Shipped'],
                ],
            ],
        ],
        [
            'method' => 'GET/POST/DELETE',
            'path' => '/api/webhooks/config',
            'description' => 'Configure a customer webhook URL for inbound message and delivery status events.',
            'events' => [
                'message.received',
                'message.status',
            ],
            'headers_sent' => [
                'X-Arklytics-Event',
                'X-Arklytics-Delivery',
                'X-Arklytics-Timestamp',
                'X-Arklytics-Signature' => 'sha256 HMAC of "timestamp.body" when a secret is configured.',
            ],
        ],
    ],
], 200);
