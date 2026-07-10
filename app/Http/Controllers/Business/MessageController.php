<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $bizId = $request->session()->get('biz_id');
        [$from, $to] = $this->resolveDateRange($request);
        $messageStatus = strtolower(trim((string) $request->query('message_status', 'all')));
        $deliveryStatus = strtolower(trim((string) $request->query('delivery_status', 'all')));
        $leadStatus = strtolower(trim((string) $request->query('lead_status', 'all')));
        $leadTemperature = strtolower(trim((string) $request->query('lead_temperature', 'all')));
        $contactColumns = $this->contactColumns();
        $hasTemperature = in_array('lead_temperature', $contactColumns, true);

        $messagesBase = DB::table('gd_sent_messages as s')
            ->leftJoin('gd_whatsapp_templates as t', 't.id', '=', 's.template_id')
            ->where('s.biz_id', $bizId)
            ->whereBetween(DB::raw('DATE(COALESCE(s.sent_at, s.created_at))'), [$from, $to]);

        $messages = (clone $messagesBase)
            ->when($messageStatus !== 'all', function ($query) use ($messageStatus) {
                if ($messageStatus === 'failed') {
                    $query->where(function ($inner) {
                        $inner->whereRaw('LOWER(COALESCE(s.status, "")) = "failed"')
                            ->orWhereRaw('LOWER(COALESCE(s.delivery_status, "")) = "failed"');
                    });
                    return;
                }

                $query->whereRaw('LOWER(COALESCE(s.status, "")) IN ("success", "sent")');
            })
            ->when($deliveryStatus !== 'all', function ($query) use ($deliveryStatus) {
                $query->whereRaw('LOWER(COALESCE(s.delivery_status, "")) = ?', [$deliveryStatus]);
            })
            ->select('s.*', 't.template_name')
            ->orderByDesc('s.id')
            ->get();

        $messageTotalsRow = (clone $messagesBase)
            ->selectRaw('COUNT(*) AS total_messages')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(s.status, "")) IN ("success", "sent") THEN 1 ELSE 0 END) AS sent_messages')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(s.delivery_status, "")) = "sent" THEN 1 ELSE 0 END) AS queued_messages')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(s.delivery_status, "")) = "delivered" THEN 1 ELSE 0 END) AS delivered_messages')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(s.delivery_status, "")) = "read" THEN 1 ELSE 0 END) AS read_messages')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(s.status, "")) = "failed" OR LOWER(COALESCE(s.delivery_status, "")) = "failed" THEN 1 ELSE 0 END) AS failed_messages')
            ->first();

        $contactsBase = DB::table('gd_user_contacts as c')
            ->where('c.biz_id', $bizId)
            ->whereBetween(DB::raw('DATE(COALESCE(c.created_at, c.updated_at))'), [$from, $to]);

        if ($leadStatus !== 'all') {
            $contactsBase->whereRaw('LOWER(COALESCE(c.lead_status, c.status, "")) = ?', [$leadStatus]);
        }

        if ($leadTemperature !== 'all' && $hasTemperature) {
            $contactsBase->whereRaw('LOWER(COALESCE(c.lead_temperature, "")) = ?', [$leadTemperature]);
        }

        $leads = (clone $contactsBase)
            ->orderByDesc('c.id')
            ->get(['c.*']);

        $leadTotalsRow = (clone $contactsBase)
            ->selectRaw('COUNT(*) AS total_leads')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(c.lead_status, c.status, "")) = "won" THEN 1 ELSE 0 END) AS won_leads')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(c.lead_status, c.status, "")) = "lost" THEN 1 ELSE 0 END) AS lost_leads')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(c.lead_temperature, "")) = "hot" THEN 1 ELSE 0 END) AS hot_leads')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(c.lead_temperature, "")) = "warm" THEN 1 ELSE 0 END) AS warm_leads')
            ->selectRaw('SUM(CASE WHEN LOWER(COALESCE(c.lead_temperature, "")) = "cold" THEN 1 ELSE 0 END) AS cold_leads')
            ->first();

        return view('business.messages.index', [
            'from' => $from,
            'to' => $to,
            'messageStatus' => $messageStatus,
            'deliveryStatus' => $deliveryStatus,
            'leadStatus' => $leadStatus,
            'leadTemperature' => $leadTemperature,
            'hasTemperature' => $hasTemperature,
            'messages' => $messages,
            'messageTotals' => $messageTotalsRow,
            'leads' => $leads,
            'leadTotals' => $leadTotalsRow,
        ]);
    }

    public function create(Request $request)
    {
        $bizId = $request->session()->get('biz_id');

        return view('business.messages.create', [
            'templates' => DB::table('gd_whatsapp_templates')->where('biz_id', $bizId)->orderByDesc('id')->get(),
            'groups' => DB::table('gd_groups')->where('biz_id', $bizId)->orderBy('group_name')->get(),
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:gd_whatsapp_templates,id'],
            'group_id' => ['required', 'integer', 'exists:gd_groups,id'],
        ]);

        $bizId = (int) $request->session()->get('biz_id');
        $template = DB::table('gd_whatsapp_templates')
            ->where('id', $data['template_id'])
            ->where('biz_id', $bizId)
            ->first();

        if (!$template) {
            return back()->with('warning', 'Template not found for this business.');
        }

        $templateMeta = json_decode((string) ($template->placeholders ?? ''), true);
        $languageCode = is_array($templateMeta) ? (string) ($templateMeta['payload']['language'] ?? 'en_US') : 'en_US';
        if ($languageCode === '') {
            $languageCode = 'en_US';
        }

        $templateComponents = \ApiSupport::buildTemplateSendComponents((array) $template);
        if (!empty($templateComponents['error'])) {
            return back()->with('warning', (string) $templateComponents['error']);
        }
        $templateSendComponents = is_array($templateComponents['components'] ?? null) ? $templateComponents['components'] : [];

        $group = DB::table('gd_groups')
            ->where('id', $data['group_id'])
            ->where('biz_id', $bizId)
            ->first();

        if (!$group) {
            return back()->with('warning', 'Group not found for this business.');
        }

        $business = DB::table('gd_orders')->where('id', $bizId)->first();
        if (!$business || empty($business->phone_number_id)) {
            return back()->with('warning', 'WhatsApp credentials are missing. Please save the phone number ID first.');
        }

        $contacts = DB::table('gd_user_contacts as c')
            ->leftJoin('gd_group_contacts as gc', 'gc.contact_id', '=', 'c.id')
            ->where('c.biz_id', $bizId)
            ->where(function ($query) use ($group) {
                $query->where('c.group_id', $group->id)
                    ->orWhere('gc.group_id', $group->id);
            })
            ->select('c.id', 'c.full_name', 'c.phone_number')
            ->distinct()
            ->get();

        if ($contacts->isEmpty()) {
            return back()->with('warning', 'No contacts found in the selected group.');
        }

        $whatsappToken = trim((string) ($business->auth_token ?? ''));
        if ($whatsappToken === '') {
            $whatsappToken = (string) (DB::table('gd_app_settings')
                ->where('admin_id', 0)
                ->where('setting_key', 'META_ACCESS_TOKEN')
                ->value('setting_value') ?: '');
        }
        if ($whatsappToken === '') {
            $whatsappToken = (string) \Config::get('META_ACCESS_TOKEN', '');
        }

        if ($whatsappToken === '') {
            return back()->with('warning', 'WhatsApp access token is missing.');
        }

        $hasPackageColumns = Schema::hasColumn('gd_orders', 'message_limit') && Schema::hasColumn('gd_orders', 'messages_used');
        $messageLimit = (int) ($business->message_limit ?? 0);
        $messagesUsed = (int) ($business->messages_used ?? 0);
        $successCount = 0;
        $errorMessages = [];

        foreach ($contacts as $contact) {
            if ($hasPackageColumns && $messageLimit > 0 && $messagesUsed >= $messageLimit) {
                $errorMessages[] = 'Message limit exhausted. Please request a package upgrade.';
                break;
            }

            $phone = \ApiSupport::normalizePhone((string) ($contact->phone_number ?? ''));
            if ($phone === '') {
                $errorMessages[] = 'Skipping contact with empty phone number.';
                continue;
            }

            $payload = \ApiSupport::whatsappTemplatePayload($phone, (string) $template->template_name, $languageCode, $templateSendComponents);
            $response = \ApiSupport::whatsappSendRequest((string) $business->phone_number_id, $whatsappToken, $payload);

            $status = $response['ok'] ? 'success' : 'failed';
            $deliveryStatus = $response['ok'] ? 'sent' : 'failed';
            $errorMessage = $response['ok'] ? null : (string) ($response['failure_reason'] ?? $response['error'] ?? 'Unknown error');
            $messageId = $response['message_id'] !== null ? (string) $response['message_id'] : null;

            $this->storeSentMessage([
                'biz_id' => $bizId,
                'phone_number' => $phone,
                'template_id' => (int) $template->id,
                'message_title' => (string) $template->message_title,
                'message_body' => (string) $template->message_body,
                'status' => $status,
                'delivery_status' => $deliveryStatus,
                'error_message' => $errorMessage,
                'message_id' => $messageId,
                'sent_at' => now(),
                'request_json' => $response['request_json'] ?? \ApiSupport::encodeJson($payload),
                'response_json' => $response['response_json'] ?? null,
                'http_status_code' => $response['http_code'] ?? null,
                'failure_reason' => $response['failure_reason'] ?? null,
            ]);

            if ($response['ok']) {
                $successCount++;
                if ($hasPackageColumns) {
                    DB::table('gd_orders')
                        ->where('id', $bizId)
                        ->increment('messages_used');
                    $messagesUsed++;
                }
            } else {
                $errorMessages[] = "Failed to send to {$phone} - Error: {$errorMessage}";
            }
        }

        if ($successCount > 0 && empty($errorMessages)) {
            return back()->with('success', "Messages sent successfully to {$successCount} recipients.");
        }

        if ($successCount > 0) {
            return back()->with('warning', "Messages sent successfully to {$successCount} recipients, but some failed.");
        }

        return back()->with('error', $errorMessages[0] ?? 'Unable to send messages.');
    }

    private function resolveDateRange(Request $request): array
    {
        $period = strtolower(trim((string) $request->query('period', 'this_month')));

        return match ($period) {
            'today' => [now()->toDateString(), now()->toDateString()],
            'this_week' => [now()->startOfWeek()->toDateString(), now()->toDateString()],
            'this_month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'last_30_days' => [now()->subDays(30)->toDateString(), now()->toDateString()],
            'custom' => [
                Carbon::parse($request->query('from_date', now()->startOfMonth()->toDateString()))->toDateString(),
                Carbon::parse($request->query('to_date', now()->toDateString()))->toDateString(),
            ],
            default => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        };
    }

    private function contactColumns(): array
    {
        $rows = DB::select('SHOW COLUMNS FROM gd_user_contacts');
        return array_map(static fn ($row) => $row->Field ?? '', $rows);
    }

    private function storeSentMessage(array $data): void
    {
        $payload = [
            'biz_id' => $data['biz_id'],
            'phone_number' => $data['phone_number'],
            'template_id' => $data['template_id'],
            'message_title' => $data['message_title'],
            'message_body' => $data['message_body'],
            'status' => $data['status'],
            'error_message' => $data['error_message'],
            'message_id' => $data['message_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('gd_sent_messages', 'delivery_status')) {
            $payload['delivery_status'] = $data['delivery_status'];
        }

        if (Schema::hasColumn('gd_sent_messages', 'sent_at')) {
            $payload['sent_at'] = $data['sent_at'] ?? now();
        }

        if (Schema::hasColumn('gd_sent_messages', 'delivered_at')) {
            $payload['delivered_at'] = $data['delivery_status'] === 'delivered' ? now() : null;
        }

        if (Schema::hasColumn('gd_sent_messages', 'read_at')) {
            $payload['read_at'] = null;
        }

        if (Schema::hasColumn('gd_sent_messages', 'request_json')) {
            $payload['request_json'] = $data['request_json'] ?? null;
        }

        if (Schema::hasColumn('gd_sent_messages', 'response_json')) {
            $payload['response_json'] = $data['response_json'] ?? null;
        }

        if (Schema::hasColumn('gd_sent_messages', 'http_status_code')) {
            $payload['http_status_code'] = $data['http_status_code'] ?? null;
        }

        if (Schema::hasColumn('gd_sent_messages', 'failure_reason')) {
            $payload['failure_reason'] = $data['failure_reason'] ?? null;
        }

        DB::table('gd_sent_messages')->insert($payload);
    }
}
