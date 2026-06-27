<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $bizId = $request->session()->get('biz_id');
        $contactsTable = DB::table('gd_user_contacts')->where('biz_id', $bizId);
        $business = DB::table('gd_orders')
            ->where('id', $bizId)
            ->first();

        $messageUsage = DB::table('gd_sent_messages')
            ->where('biz_id', $bizId)
            ->where('status', 'success')
            ->count();

        $messageLimit = (int) ($business?->message_limit ?? 0);
        $messagesUsed = (int) ($business?->messages_used ?? $messageUsage);
        $messagesUsed = max($messagesUsed, $messageUsage);
        $messagesRemaining = max(0, $messageLimit - $messagesUsed);

        return view('business.dashboard', [
            'bizId' => $bizId,
            'business' => $business,
            'packageName' => (string) ($business?->package_name ?? 'No Package'),
            'messageLimit' => $messageLimit,
            'messagesUsed' => $messagesUsed,
            'messagesRemaining' => $messagesRemaining,
            'totalContacts' => (clone $contactsTable)->count(),
            'openLeads' => (clone $contactsTable)->whereIn('lead_status', ['new', 'contacted', 'qualified'])->count(),
            'wonLeads' => (clone $contactsTable)->where('lead_status', 'won')->count(),
            'lostLeads' => (clone $contactsTable)->where('lead_status', 'lost')->count(),
            'dueFollowUps' => (clone $contactsTable)->whereNotNull('next_follow_up_at')->whereDate('next_follow_up_at', '<=', now()->toDateString())->count(),
            'whatsappOptedIn' => (clone $contactsTable)->where('whatsapp_opt_in', 1)->count(),
            'limitRequestStatus' => (string) ($business?->limit_request_status ?? 'none'),
            'limitRequestNote' => (string) ($business?->limit_request_note ?? ''),
            'packageStartedAt' => $business?->package_started_at ?? null,
            'packageEndsAt' => $business?->package_ends_at ?? null,
        ]);
    }

    public function requestLimitIncrease(Request $request)
    {
        $bizId = $request->session()->get('biz_id');
        $data = $request->validate([
            'requested_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::table('gd_orders')
            ->where('id', $bizId)
            ->update([
                'limit_request_status' => 'pending',
                'limit_request_note' => trim((string) ($data['reason'] ?? '')),
                'limit_request_at' => now(),
            ]);

        if (Schema::hasTable('gd_package_requests')) {
            DB::table('gd_package_requests')->insert([
                'biz_id' => $bizId,
                'requested_limit' => (int) $data['requested_limit'],
                'current_package' => (string) (DB::table('gd_orders')->where('id', $bizId)->value('package_name') ?? ''),
                'reason' => trim((string) ($data['reason'] ?? '')),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Limit increase request sent to master admin.');
    }
}
