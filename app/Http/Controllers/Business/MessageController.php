<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from_date', now()->subDays(7)->toDateString());
        $to = $request->query('to_date', now()->toDateString());

        $messages = DB::table('gd_sent_messages as s')
            ->leftJoin('gd_whatsapp_templates as t', 't.id', '=', 's.template_id')
            ->where('s.biz_id', $request->session()->get('biz_id'))
            ->whereBetween(DB::raw('DATE(s.sent_at)'), [$from, $to])
            ->select('s.*', 't.template_name')
            ->orderByDesc('s.id')
            ->get();

        return view('business.messages.index', compact('messages'));
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
}
