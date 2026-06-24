<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Config.php';
require_once __DIR__ . '/../app/Crm.php';

Config::load(__DIR__ . '/../.env');

date_default_timezone_set('Asia/Calcutta');

function demoConnect(): mysqli
{
    $host = Config::get('DB_HOST', 'localhost');
    $user = Config::get('DB_USER', 'root');
    $pass = Config::get('DB_PASSWORD', '');
    $name = Config::get('DB_NAME', 'growthlink');
    $ports = array_values(array_unique([
        Config::get('DB_PORT', '3306'),
        '3306',
        '3307',
    ]));

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $errors = [];
    foreach ($ports as $port) {
        try {
            $db = mysqli_init();
            $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
            if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
                $db->options(MYSQLI_OPT_READ_TIMEOUT, 3);
            }
            $db->real_connect($host, $user, $pass, $name, (int) $port);
            $db->set_charset('utf8mb4');

            return $db;
        } catch (Throwable $exception) {
            $errors[] = $port . ': ' . $exception->getMessage();
            continue;
        }
    }

    throw new RuntimeException(
        'Unable to connect to MySQL on any demo port. '
        . 'Checked ' . implode(', ', $ports) . ' for ' . $user . '@' . $host . ' / ' . $name . '. '
        . 'Errors: ' . implode(' | ', $errors)
    );
}

$db = demoConnect();
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Calcutta'));

function demoSqlValue(mysqli $db, mixed $value): string
{
    if ($value instanceof DateTimeInterface) {
        return "'" . $db->real_escape_string($value->format('Y-m-d H:i:s')) . "'";
    }

    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . $db->real_escape_string((string) $value) . "'";
}

function demoTableColumns(mysqli $db, string $table): array
{
    return Crm::tableColumns($db, $table);
}

function demoInsert(mysqli $db, string $table, array $row): bool
{
    $columns = demoTableColumns($db, $table);
    if (empty($columns)) {
        return false;
    }

    $payload = array_intersect_key($row, array_flip($columns));
    if (empty($payload)) {
        return false;
    }

    $fields = array_keys($payload);
    $values = array_map(fn ($value) => demoSqlValue($db, $value), array_values($payload));

    $sql = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table,
        implode('`, `', $fields),
        implode(', ', $values)
    );

    $db->query($sql);

    return true;
}

function demoUpdate(mysqli $db, string $table, string $whereClause, array $row): bool
{
    $columns = demoTableColumns($db, $table);
    if (empty($columns)) {
        return false;
    }

    $payload = array_intersect_key($row, array_flip($columns));
    if (empty($payload)) {
        return false;
    }

    $assignments = [];
    foreach ($payload as $field => $value) {
        $assignments[] = sprintf('`%s` = %s', $field, demoSqlValue($db, $value));
    }

    $db->query(sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $assignments), $whereClause));

    return true;
}

function demoFetchOne(mysqli $db, string $sql): ?array
{
    $result = $db->query($sql);
    $row = $result ? $result->fetch_assoc() : null;

    return $row ?: null;
}

function demoColumnExists(mysqli $db, string $table, string $column): bool
{
    return in_array($column, demoTableColumns($db, $table), true);
}

function demoTimestamp(DateTimeImmutable $base, string $modifier): string
{
    return $base->modify($modifier)->format('Y-m-d H:i:s');
}

function demoEnsureGroup(mysqli $db, int $bizId, string $name): ?int
{
    if (!demoColumnExists($db, 'gd_groups', 'biz_id') || !demoColumnExists($db, 'gd_groups', 'group_name')) {
        return null;
    }

    $escapedName = $db->real_escape_string($name);
    $existing = demoFetchOne($db, "SELECT id FROM gd_groups WHERE biz_id = {$bizId} AND group_name = '{$escapedName}' LIMIT 1");
    if ($existing) {
        return (int) $existing['id'];
    }

    demoInsert($db, 'gd_groups', [
        'biz_id' => $bizId,
        'group_name' => $name,
    ]);

    $created = demoFetchOne($db, "SELECT id FROM gd_groups WHERE biz_id = {$bizId} AND group_name = '{$escapedName}' LIMIT 1");

    return $created ? (int) $created['id'] : null;
}

function demoEnsureTemplate(mysqli $db, int $bizId, array $template): ?int
{
    if (!demoColumnExists($db, 'gd_whatsapp_templates', 'biz_id')) {
        return null;
    }

    $templateName = $db->real_escape_string((string) $template['template_name']);
    $existing = demoFetchOne($db, "SELECT id FROM gd_whatsapp_templates WHERE biz_id = {$bizId} AND template_name = '{$templateName}' LIMIT 1");

    if ($existing) {
        demoUpdate($db, 'gd_whatsapp_templates', 'id = ' . (int) $existing['id'], $template);
        return (int) $existing['id'];
    }

    demoInsert($db, 'gd_whatsapp_templates', $template + ['biz_id' => $bizId]);
    $created = demoFetchOne($db, "SELECT id FROM gd_whatsapp_templates WHERE biz_id = {$bizId} AND template_name = '{$templateName}' LIMIT 1");

    return $created ? (int) $created['id'] : null;
}

function demoEnsureContact(mysqli $db, int $bizId, array $contact): ?int
{
    if (!demoColumnExists($db, 'gd_user_contacts', 'biz_id')) {
        return null;
    }

    $phone = $db->real_escape_string((string) $contact['phone_number']);
    $existing = demoFetchOne($db, "SELECT id FROM gd_user_contacts WHERE biz_id = {$bizId} AND phone_number = '{$phone}' LIMIT 1");

    if ($existing) {
        demoUpdate($db, 'gd_user_contacts', 'id = ' . (int) $existing['id'], $contact);
        return (int) $existing['id'];
    }

    demoInsert($db, 'gd_user_contacts', $contact + ['biz_id' => $bizId]);
    $created = demoFetchOne($db, "SELECT id FROM gd_user_contacts WHERE biz_id = {$bizId} AND phone_number = '{$phone}' LIMIT 1");

    return $created ? (int) $created['id'] : null;
}

function demoEnsureGroupContact(mysqli $db, int $bizId, int $groupId, int $contactId): void
{
    if (!demoColumnExists($db, 'gd_group_contacts', 'biz_id')) {
        return;
    }

    $db->query(
        'DELETE FROM gd_group_contacts'
        . ' WHERE biz_id = ' . $bizId
        . ' AND group_id = ' . $groupId
        . ' AND contact_id = ' . $contactId
    );

    demoInsert($db, 'gd_group_contacts', [
        'biz_id' => $bizId,
        'group_id' => $groupId,
        'contact_id' => $contactId,
    ]);
}

function demoEnsureFollowUp(mysqli $db, int $bizId, int $contactId, array $followUp): void
{
    if (!demoColumnExists($db, 'gd_contact_followups', 'biz_id')) {
        return;
    }

    $sequence = $db->real_escape_string((string) $followUp['sequence_name']);
    $stepNo = (int) $followUp['step_no'];
    $existing = demoFetchOne($db, "SELECT id FROM gd_contact_followups WHERE biz_id = {$bizId} AND contact_id = {$contactId} AND sequence_name = '{$sequence}' AND step_no = {$stepNo} LIMIT 1");

    $row = $followUp + [
        'biz_id' => $bizId,
        'contact_id' => $contactId,
        'channel' => 'whatsapp',
        'status' => 'pending',
        'created_at' => $followUp['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $followUp['updated_at'] ?? date('Y-m-d H:i:s'),
    ];

    if ($existing) {
        demoUpdate($db, 'gd_contact_followups', 'id = ' . (int) $existing['id'], $row);
        return;
    }

    demoInsert($db, 'gd_contact_followups', $row);
}

function demoEnsureMessage(mysqli $db, int $bizId, array $message): void
{
    if (!demoColumnExists($db, 'gd_sent_messages', 'biz_id')) {
        return;
    }

    $messageId = $db->real_escape_string((string) ($message['message_id'] ?? ''));
    if ($messageId !== '') {
        $existing = demoFetchOne($db, "SELECT id FROM gd_sent_messages WHERE message_id = '{$messageId}' LIMIT 1");
        if ($existing) {
            demoUpdate($db, 'gd_sent_messages', 'id = ' . (int) $existing['id'], $message);
            return;
        }
    }

    demoInsert($db, 'gd_sent_messages', $message + ['biz_id' => $bizId]);
}

echo "Seeding demo workspace...\n";

$demoMobile = '9990001111';
$demoPassword = 'Demo@1234';
$passwordHash = password_hash($demoPassword, PASSWORD_BCRYPT);

$existingBusiness = demoFetchOne(
    $db,
    "SELECT id FROM gd_orders WHERE mobile_number = '" . $db->real_escape_string($demoMobile) . "' LIMIT 1"
);

$businessRow = [
    'admin_id' => 1,
    'full_name' => 'Demo Manager',
    'mobile_number' => $demoMobile,
    'email' => 'demo@whatsgrow.test',
    'password' => $passwordHash,
    'business_name' => 'Acme Demo Studio',
    'business_number' => '919990001112',
    'business_email' => 'hello@acmedemo.test',
    'business_location' => 'Hyderabad, India',
    'business_description' => 'Demo workspace for testing Connect, templates, sequences, and lead stages.',
    'business_logo' => '',
    'status' => 1,
    'auth_token' => 'demo-meta-token',
    'whatsapp_id' => '123456789012345',
    'phone_number_id' => '987654321098765',
    'webhook_url' => 'https://example.com/webhook/demo',
];

if ($existingBusiness) {
    demoUpdate($db, 'gd_orders', 'id = ' . (int) $existingBusiness['id'], $businessRow);
    $bizId = (int) $existingBusiness['id'];
} else {
    demoInsert($db, 'gd_orders', $businessRow);
    $newBusiness = demoFetchOne(
        $db,
        "SELECT id FROM gd_orders WHERE mobile_number = '" . $db->real_escape_string($demoMobile) . "' LIMIT 1"
    );
    $bizId = $newBusiness ? (int) $newBusiness['id'] : 0;
}

if ($bizId === 0) {
    throw new RuntimeException('Unable to create or locate the demo business account.');
}

$groupIds = [];
foreach ([
    'Demo Hot Leads',
    'Demo Warm Leads',
    'Demo Cold Leads',
    'Demo Won Deals',
    'Demo Lost Deals',
] as $groupName) {
    $groupId = demoEnsureGroup($db, $bizId, $groupName);
    if ($groupId !== null) {
        $groupIds[$groupName] = $groupId;
    }
}

foreach ([
    [
        'template_id' => 'demo_tpl_intro_offer',
        'template_name' => 'demo_intro_offer',
        'message_title' => 'Demo Intro Offer',
        'message_body' => 'Hi {{1}}, thanks for showing interest in Acme Demo Studio. I can share pricing, a sample workflow, and a quick walkthrough.',
        'placeholders' => '{"1":"Customer Name"}',
        'subtitle' => 'First touch template for new leads',
        'media_url' => '',
        'status' => 'APPROVED',
        'category' => 'MARKETING',
        'buttons' => json_encode([
            ['type' => 'QUICK_REPLY', 'text' => 'Send Pricing'],
            ['type' => 'QUICK_REPLY', 'text' => 'Book Demo'],
        ], JSON_UNESCAPED_SLASHES),
    ],
    [
        'template_id' => 'demo_tpl_followup_day_2',
        'template_name' => 'demo_followup_day_2',
        'message_title' => '2 Day Follow-Up',
        'message_body' => 'Hi {{1}}, just checking in on my last message. If you still need help, I am here. No rush at all.',
        'placeholders' => '{"1":"Customer Name"}',
        'subtitle' => 'Use when a lead has not replied after two days',
        'media_url' => '',
        'status' => 'APPROVED',
        'category' => 'UTILITY',
        'buttons' => json_encode([
            ['type' => 'QUICK_REPLY', 'text' => 'Reply Now'],
        ], JSON_UNESCAPED_SLASHES),
    ],
    [
        'template_id' => 'demo_tpl_followup_day_5',
        'template_name' => 'demo_followup_day_5',
        'message_title' => '5 Day Follow-Up',
        'message_body' => 'Hi {{1}}, sharing one more helpful note in case you are still considering it. Happy to answer any questions.',
        'placeholders' => '{"1":"Customer Name"}',
        'subtitle' => 'Middle of the sequence',
        'media_url' => '',
        'status' => 'PENDING',
        'category' => 'MARKETING',
        'buttons' => json_encode([], JSON_UNESCAPED_SLASHES),
    ],
    [
        'template_id' => 'demo_tpl_final_followup',
        'template_name' => 'demo_final_followup',
        'message_title' => 'Final Follow-Up',
        'message_body' => 'Hi {{1}}, I do not want to crowd your inbox, so I will pause here. If you want to continue later, just reply anytime.',
        'placeholders' => '{"1":"Customer Name"}',
        'subtitle' => 'Final sequence step',
        'media_url' => '',
        'status' => 'PENDING',
        'category' => 'MARKETING',
        'buttons' => json_encode([], JSON_UNESCAPED_SLASHES),
    ],
] as $template) {
    $templateId = demoEnsureTemplate($db, $bizId, $template);
    if ($templateId !== null) {
        $templateIds[$template['template_name']] = $templateId;
    }
}

$templateIds = [];

$leadBlueprints = [
    ['name' => 'Aarav Sharma', 'phone' => '+91990001001', 'group' => 'Demo Hot Leads', 'temperature' => 'hot', 'lead_stage' => 'opportunity', 'lead_status' => 'contacted', 'source' => 'Instagram Ads', 'last' => '-20 minutes', 'next' => '+1 day', 'notes' => 'Replied quickly and asked for pricing.'],
    ['name' => 'Meera Iyer', 'phone' => '+91990001002', 'group' => 'Demo Hot Leads', 'temperature' => 'hot', 'lead_stage' => 'opportunity', 'lead_status' => 'qualified', 'source' => 'Website Chat', 'last' => '-35 minutes', 'next' => '+1 day', 'notes' => 'Needs a demo and wants package comparison.'],
    ['name' => 'Kabir Khan', 'phone' => '+91990001003', 'group' => 'Demo Hot Leads', 'temperature' => 'hot', 'lead_stage' => 'customer', 'lead_status' => 'won', 'source' => 'Referral', 'last' => '-50 minutes', 'next' => null, 'notes' => 'Converted immediately after a quick call.'],
    ['name' => 'Nisha Patel', 'phone' => '+91990001004', 'group' => 'Demo Hot Leads', 'temperature' => 'hot', 'lead_stage' => 'lead', 'lead_status' => 'new', 'source' => 'WhatsApp', 'last' => '-75 minutes', 'next' => '+1 day', 'notes' => 'Opened messages twice and may reply today.'],
    ['name' => 'Arjun Verma', 'phone' => '+91990001005', 'group' => 'Demo Hot Leads', 'temperature' => 'hot', 'lead_stage' => 'opportunity', 'lead_status' => 'contacted', 'source' => 'Facebook Lead Ad', 'last' => '-90 minutes', 'next' => '+1 day', 'notes' => 'Requested a callback later today.'],
    ['name' => 'Priya Desai', 'phone' => '+91990001006', 'group' => 'Demo Hot Leads', 'temperature' => 'hot', 'lead_stage' => 'opportunity', 'lead_status' => 'qualified', 'source' => 'Landing Page', 'last' => '-100 minutes', 'next' => '+1 day', 'notes' => 'Asked for implementation timeline.'],
    ['name' => 'Rohan Nair', 'phone' => '+91990001007', 'group' => 'Demo Hot Leads', 'temperature' => 'hot', 'lead_stage' => 'customer', 'lead_status' => 'won', 'source' => 'Referral', 'last' => '-1 hour', 'next' => null, 'notes' => 'Paid and booked onboarding.'],
    ['name' => 'Sanya Gupta', 'phone' => '+91990001008', 'group' => 'Demo Hot Leads', 'temperature' => 'hot', 'lead_stage' => 'lead', 'lead_status' => 'contacted', 'source' => 'Instagram Ads', 'last' => '-110 minutes', 'next' => '+1 day', 'notes' => 'Fast response and wants a brochure.'],

    ['name' => 'Ishaan Rao', 'phone' => '+91990001009', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'lead', 'lead_status' => 'contacted', 'source' => 'Website Form', 'last' => '-4 hours', 'next' => '+2 days', 'notes' => 'Good interest but not ready today.'],
    ['name' => 'Aditi Menon', 'phone' => '+91990001010', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'opportunity', 'lead_status' => 'qualified', 'source' => 'Organic Search', 'last' => '-6 hours', 'next' => '+2 days', 'notes' => 'Needs internal approval before buying.'],
    ['name' => 'Rahul Singh', 'phone' => '+91990001011', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'lead', 'lead_status' => 'new', 'source' => 'LinkedIn', 'last' => '-8 hours', 'next' => '+2 days', 'notes' => 'Viewed the pricing page twice.'],
    ['name' => 'Kavya Joshi', 'phone' => '+91990001012', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'opportunity', 'lead_status' => 'contacted', 'source' => 'Referral', 'last' => '-10 hours', 'next' => '+2 days', 'notes' => 'Will reply after the weekend.'],
    ['name' => 'Neha Yadav', 'phone' => '+91990001013', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'opportunity', 'lead_status' => 'qualified', 'source' => 'Facebook', 'last' => '-12 hours', 'next' => '+2 days', 'notes' => 'Wants onboarding support details.'],
    ['name' => 'Dev Shah', 'phone' => '+91990001014', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'lead', 'lead_status' => 'contacted', 'source' => 'WhatsApp', 'last' => '-14 hours', 'next' => '+2 days', 'notes' => 'Asked for case studies.'],
    ['name' => 'Ananya Bose', 'phone' => '+91990001015', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'lead', 'lead_status' => 'lost', 'source' => 'Website', 'last' => '-16 hours', 'next' => null, 'notes' => 'Not the right time right now.', 'lost_reason' => 'Budget not approved yet.'],
    ['name' => 'Varun Malik', 'phone' => '+91990001016', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'opportunity', 'lead_status' => 'contacted', 'source' => 'Instagram', 'last' => '-18 hours', 'next' => '+2 days', 'notes' => 'Prefers a message instead of a call.'],
    ['name' => 'Pooja Reddy', 'phone' => '+91990001017', 'group' => 'Demo Warm Leads', 'temperature' => 'warm', 'lead_stage' => 'lead', 'lead_status' => 'qualified', 'source' => 'Walk-in', 'last' => '-20 hours', 'next' => '+2 days', 'notes' => 'Interested in a monthly plan.'],

    ['name' => 'Siddharth Jain', 'phone' => '+91990001018', 'group' => 'Demo Cold Leads', 'temperature' => 'cold', 'lead_stage' => 'lead', 'lead_status' => 'new', 'source' => 'Import', 'last' => '-2 days', 'next' => '-1 day', 'notes' => 'No response after initial outreach.'],
    ['name' => 'Tanya Kapoor', 'phone' => '+91990001019', 'group' => 'Demo Cold Leads', 'temperature' => 'cold', 'lead_stage' => 'lead', 'lead_status' => 'contacted', 'source' => 'Import', 'last' => '-3 days', 'next' => '-1 day', 'notes' => 'Open rate is low, needs a softer follow-up.'],
    ['name' => 'Mohit Bansal', 'phone' => '+91990001020', 'group' => 'Demo Cold Leads', 'temperature' => 'cold', 'lead_stage' => 'lead', 'lead_status' => 'lost', 'source' => 'Import', 'last' => '-4 days', 'next' => null, 'notes' => 'Did not engage.', 'lost_reason' => 'Already using another provider.'],
    ['name' => 'Simran Kaur', 'phone' => '+91990001021', 'group' => 'Demo Cold Leads', 'temperature' => 'cold', 'lead_stage' => 'lead', 'lead_status' => 'new', 'source' => 'Import', 'last' => '-5 days', 'next' => '-1 day', 'notes' => 'No replies after two reminders.'],
    ['name' => 'Deepak Mehta', 'phone' => '+91990001022', 'group' => 'Demo Cold Leads', 'temperature' => 'cold', 'lead_stage' => 'opportunity', 'lead_status' => 'contacted', 'source' => 'Import', 'last' => '-6 days', 'next' => '-1 day', 'notes' => 'Maybe later in the quarter.'],
    ['name' => 'Riya Kulkarni', 'phone' => '+91990001023', 'group' => 'Demo Cold Leads', 'temperature' => 'cold', 'lead_stage' => 'lead', 'lead_status' => 'qualified', 'source' => 'Import', 'last' => '-7 days', 'next' => '-1 day', 'notes' => 'Lead needs long-term nurture.'],
    ['name' => 'Aman Sethi', 'phone' => '+91990001024', 'group' => 'Demo Cold Leads', 'temperature' => 'cold', 'lead_stage' => 'lead', 'lead_status' => 'lost', 'source' => 'Import', 'last' => '-8 days', 'next' => null, 'notes' => 'No longer looking.', 'lost_reason' => 'No budget this month.'],
    ['name' => 'Anjali Thomas', 'phone' => '+91990001025', 'group' => 'Demo Cold Leads', 'temperature' => 'cold', 'lead_stage' => 'lead', 'lead_status' => 'new', 'source' => 'Import', 'last' => '-9 days', 'next' => '-1 day', 'notes' => 'Best treated with a long-gap follow-up.'],
];

$contactIds = [];
foreach ($leadBlueprints as $index => $lead) {
    $groupName = (string) $lead['group'];
    $groupId = $groupIds[$groupName] ?? null;
    $contactRow = [
        'group_id' => $groupId,
        'full_name' => $lead['name'],
        'phone_number' => $lead['phone'],
        'email' => strtolower(str_replace(' ', '.', $lead['name'])) . '@demo.test',
        'status' => $lead['lead_status'],
        'lead_stage' => $lead['lead_stage'],
        'lead_status' => $lead['lead_status'],
        'lead_temperature' => $lead['temperature'],
        'source' => $lead['source'],
        'whatsapp_opt_in' => 1,
        'last_contacted_at' => demoTimestamp($now, (string) $lead['last']),
        'next_follow_up_at' => $lead['next'] ? demoTimestamp($now, (string) $lead['next']) : null,
        'won_at' => $lead['lead_status'] === 'won' ? demoTimestamp($now, '-3 hours') : null,
        'lost_at' => $lead['lead_status'] === 'lost' ? demoTimestamp($now, '-1 day') : null,
        'lost_reason' => $lead['lost_reason'] ?? null,
        'crm_notes' => $lead['notes'],
        'created_at' => demoTimestamp($now, '-' . (30 + $index) . ' days'),
        'updated_at' => demoTimestamp($now, '-' . (1 + ($index % 5)) . ' hours'),
    ];

    $contactId = demoEnsureContact($db, $bizId, $contactRow);
    if ($contactId !== null) {
        $contactIds[$lead['phone']] = $contactId;
        if ($groupId !== null) {
            demoEnsureGroupContact($db, $bizId, $groupId, $contactId);
        }
    }
}

$sequenceLeadId = $contactIds['+91990001018'] ?? null;
if ($sequenceLeadId !== null) {
    $sequenceName = 'Demo 3-Step WhatsApp Sequence';
    $steps = Crm::noResponseSequence('Siddharth Jain', 'Thanks for checking the demo.');
    foreach ($steps as $step) {
        demoEnsureFollowUp($db, $bizId, $sequenceLeadId, [
            'sequence_name' => $sequenceName,
            'step_no' => (int) $step['step_no'],
            'scheduled_at' => demoTimestamp($now, '+' . (int) $step['delay_days'] . ' days'),
            'status' => 'pending',
            'channel' => 'whatsapp',
            'message' => $step['message'],
            'notes' => 'Demo 3-step sequence for a cold lead.',
            'created_at' => demoTimestamp($now, '-1 day'),
            'updated_at' => demoTimestamp($now, '-1 day'),
        ]);
    }

    if (demoColumnExists($db, 'gd_user_contacts', 'next_follow_up_at')) {
        demoUpdate($db, 'gd_user_contacts', 'id = ' . $sequenceLeadId, [
            'next_follow_up_at' => demoTimestamp($now, '+2 days'),
        ]);
    }
}

$quickReplyLeadId = $contactIds['+91990001001'] ?? null;
if ($quickReplyLeadId !== null) {
    demoEnsureFollowUp($db, $bizId, $quickReplyLeadId, [
        'sequence_name' => 'Demo Quick Reply Sequence',
        'step_no' => 1,
        'scheduled_at' => demoTimestamp($now, '+1 hour'),
        'status' => 'pending',
        'channel' => 'whatsapp',
        'message' => 'Hi Aarav, thanks for replying so quickly. I will help with the next step now.',
        'notes' => 'Quick-reply demo step.',
        'created_at' => demoTimestamp($now, '-2 hours'),
        'updated_at' => demoTimestamp($now, '-2 hours'),
    ]);
}

foreach ([
    [
        'phone_number' => '+91990001001',
        'template_id' => $templateIds['demo_intro_offer'] ?? null,
        'message_title' => 'Demo Intro Offer',
        'message_body' => 'Hi Aarav, thanks for showing interest in Acme Demo Studio. I can share pricing and a walkthrough.',
        'status' => 'success',
        'error_message' => null,
        'message_id' => 'demo-msg-001',
        'sent_at' => demoTimestamp($now, '-1 hour'),
        'created_at' => demoTimestamp($now, '-1 hour'),
        'updated_at' => demoTimestamp($now, '-1 hour'),
    ],
    [
        'phone_number' => '+91990001009',
        'template_id' => $templateIds['demo_followup_day_2'] ?? null,
        'message_title' => '2 Day Follow-Up',
        'message_body' => 'Hi Ishaan, just checking in on my last message. If you still need help, I am here.',
        'status' => 'success',
        'error_message' => null,
        'message_id' => 'demo-msg-002',
        'sent_at' => demoTimestamp($now, '-3 hours'),
        'created_at' => demoTimestamp($now, '-3 hours'),
        'updated_at' => demoTimestamp($now, '-3 hours'),
    ],
    [
        'phone_number' => '+91990001018',
        'template_id' => $templateIds['demo_followup_day_5'] ?? null,
        'message_title' => '5 Day Follow-Up',
        'message_body' => 'Hi Siddharth, sharing one more helpful note in case you are still considering it.',
        'status' => 'failed',
        'error_message' => 'Demo delivery issue for testing',
        'message_id' => 'demo-msg-003',
        'sent_at' => demoTimestamp($now, '-5 hours'),
        'created_at' => demoTimestamp($now, '-5 hours'),
        'updated_at' => demoTimestamp($now, '-5 hours'),
    ],
    [
        'phone_number' => '+91990001021',
        'template_id' => $templateIds['demo_final_followup'] ?? null,
        'message_title' => 'Final Follow-Up',
        'message_body' => 'Hi Simran, I do not want to crowd your inbox, so I will pause here.',
        'status' => 'success',
        'error_message' => null,
        'message_id' => 'demo-msg-004',
        'sent_at' => demoTimestamp($now, '-1 day'),
        'created_at' => demoTimestamp($now, '-1 day'),
        'updated_at' => demoTimestamp($now, '-1 day'),
    ],
    [
        'phone_number' => '+91990001024',
        'template_id' => $templateIds['demo_followup_day_2'] ?? null,
        'message_title' => '2 Day Follow-Up',
        'message_body' => 'Hi Aman, just checking in on my last message. If you still need help, I am here.',
        'status' => 'failed',
        'error_message' => 'Demo rate limit hit',
        'message_id' => 'demo-msg-005',
        'sent_at' => demoTimestamp($now, '-2 days'),
        'created_at' => demoTimestamp($now, '-2 days'),
        'updated_at' => demoTimestamp($now, '-2 days'),
    ],
    [
        'phone_number' => '+91990001003',
        'template_id' => $templateIds['demo_intro_offer'] ?? null,
        'message_title' => 'Demo Intro Offer',
        'message_body' => 'Hi Kabir, welcome aboard. We are ready for onboarding.',
        'status' => 'success',
        'error_message' => null,
        'message_id' => 'demo-msg-006',
        'sent_at' => demoTimestamp($now, '-30 minutes'),
        'created_at' => demoTimestamp($now, '-30 minutes'),
        'updated_at' => demoTimestamp($now, '-30 minutes'),
    ],
] as $message) {
    demoEnsureMessage($db, $bizId, $message);
}

echo "Demo account ready.\n";
echo "Login mobile: {$demoMobile}\n";
echo "Login password: {$demoPassword}\n";
echo "Business name: Acme Demo Studio\n";
echo "Seeded 25 leads, 4 templates, groups, follow-ups, and sample message history.\n";
