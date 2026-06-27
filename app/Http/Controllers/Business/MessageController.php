<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function send()
    {
        return back()->with('warning', 'WhatsApp sending should be connected through a Laravel service class after installation.');
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
}
