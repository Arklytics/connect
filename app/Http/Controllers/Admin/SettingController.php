<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function tokens(Request $request)
    {
        $masterId = $request->session()->get('master_id');

        return view('admin.settings.tokens', [
            'pendingOrders' => DB::table('gd_orders')->where('admin_id', $masterId)->where('status', '0')->orderByDesc('id')->get(),
            'activeOrders' => DB::table('gd_orders')->where('admin_id', $masterId)->where('status', '1')->orderByDesc('id')->get(),
        ]);
    }

    public function storeToken(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer'],
            'auth_token' => ['required', 'string'],
            'whatsapp_id' => ['required', 'string'],
            'phonenumber_id' => ['required', 'string'],
            'webhook_url' => ['required', 'url'],
        ]);

        DB::table('gd_orders')
            ->where('id', $data['business_id'])
            ->where('admin_id', $request->session()->get('master_id'))
            ->update([
                'auth_token' => $data['auth_token'],
                'whatsapp_id' => $data['whatsapp_id'],
                'phone_number_id' => $data['phonenumber_id'],
                'webhook_url' => $data['webhook_url'],
                'status' => '1',
            ]);

        return redirect()->route('admin.settings.tokens')->with('success', 'API integrated successfully.');
    }
}
