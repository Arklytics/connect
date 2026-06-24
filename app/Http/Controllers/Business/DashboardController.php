<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $bizId = $request->session()->get('biz_id');
        $totalMessages = DB::table('messages')->count();
        $availableMessages = DB::table('messages')->where('status', 'available')->count();
        $contactsTable = DB::table('gd_user_contacts')->where('biz_id', $bizId);

        return view('business.dashboard', [
            'totalMessages' => $totalMessages,
            'availableMessages' => $availableMessages,
            'usedMessages' => $totalMessages - $availableMessages,
            'bizId' => $bizId,
            'totalContacts' => (clone $contactsTable)->count(),
            'openLeads' => (clone $contactsTable)->whereIn('lead_status', ['new', 'contacted', 'qualified'])->count(),
            'wonLeads' => (clone $contactsTable)->where('lead_status', 'won')->count(),
            'lostLeads' => (clone $contactsTable)->where('lead_status', 'lost')->count(),
            'dueFollowUps' => (clone $contactsTable)->whereNotNull('next_follow_up_at')->whereDate('next_follow_up_at', '<=', now()->toDateString())->count(),
            'whatsappOptedIn' => (clone $contactsTable)->where('whatsapp_opt_in', 1)->count(),
        ]);
    }
}
