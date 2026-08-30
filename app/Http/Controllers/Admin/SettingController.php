<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingController extends Controller
{
    private const PACKAGES = [
        'starter' => ['label' => 'Starter', 'marketing_limit' => 500, 'utility_limit' => 500, 'price' => 0],
        'growth' => ['label' => 'Growth', 'marketing_limit' => 2500, 'utility_limit' => 2500, 'price' => 0],
        'pro' => ['label' => 'Pro', 'marketing_limit' => 7500, 'utility_limit' => 7500, 'price' => 0],
    ];

    public function tokens(Request $request)
    {
        $masterId = $request->session()->get('master_id');
        $defaultWebhookUrl = url('/incoming.php');

        $storedSettings = DB::table('gd_app_settings')
            ->where('admin_id', 0)
            ->pluck('setting_value', 'setting_key');

        $packageRequests = Schema::hasTable('gd_package_requests')
            ? DB::table('gd_package_requests')->orderByDesc('id')->get()
            : collect();

        return view('admin.settings.tokens', [
            'pendingOrders' => DB::table('gd_orders')->where('admin_id', $masterId)->where('status', '0')->orderByDesc('id')->get(),
            'activeOrders' => DB::table('gd_orders')->where('admin_id', $masterId)->where('status', '1')->orderByDesc('id')->get(),
            'allOrders' => DB::table('gd_orders')->where('admin_id', $masterId)->orderByDesc('id')->get(),
            'packageRequests' => $packageRequests,
            'defaultWebhookUrl' => $defaultWebhookUrl,
            'packages' => self::PACKAGES,
            'appSettings' => [
                'connect_app_id' => (string) ($storedSettings['META_APP_ID'] ?? ''),
                'connect_app_secret' => (string) ($storedSettings['META_APP_SECRET'] ?? ''),
                'connect_config_id' => (string) ($storedSettings['META_CONFIG_ID'] ?? ''),
                'connect_verify_token' => (string) ($storedSettings['META_VERIFY_TOKEN'] ?? ''),
                'whatsapp_access_token' => (string) ($storedSettings['META_ACCESS_TOKEN'] ?? ''),
                'api_token' => (string) ($storedSettings['API_TOKEN'] ?? ''),
            ],
        ]);
    }

    public function packages(Request $request)
    {
        $masterId = $request->session()->get('master_id');

        $packageRequests = Schema::hasTable('gd_package_requests')
            ? DB::table('gd_package_requests')->orderByDesc('id')->get()
            : collect();

        return view('admin.settings.packages', [
            'businesses' => DB::table('gd_orders')
                ->where('admin_id', $masterId)
                ->orderByDesc('id')
                ->get(),
            'packageRequests' => $packageRequests,
            'packages' => self::PACKAGES,
        ]);
    }

    public function storeAppSettings(Request $request)
    {
        $data = $request->validate([
            'connect_app_id' => ['nullable', 'string', 'max:255'],
            'connect_app_secret' => ['nullable', 'string', 'max:255'],
            'connect_config_id' => ['nullable', 'string', 'max:255'],
            'connect_verify_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:255'],
        ]);

        $values = [
            'META_APP_ID' => trim((string) ($data['connect_app_id'] ?? '')),
            'META_APP_SECRET' => trim((string) ($data['connect_app_secret'] ?? '')),
            'META_CONFIG_ID' => trim((string) ($data['connect_config_id'] ?? '')),
            'META_VERIFY_TOKEN' => trim((string) ($data['connect_verify_token'] ?? '')),
            'META_ACCESS_TOKEN' => trim((string) ($data['whatsapp_access_token'] ?? '')),
            'API_TOKEN' => trim((string) ($data['api_token'] ?? '')),
        ];

        foreach ($values as $key => $value) {
            DB::table('gd_app_settings')->updateOrInsert(
                ['admin_id' => 0, 'setting_key' => $key],
                ['setting_value' => $value]
            );
        }

        return redirect()->route('admin.settings.tokens')->with('success', 'App settings saved successfully.');
    }

    public function storeToken(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer'],
            'auth_token' => ['required', 'string'],
            'whatsapp_id' => ['required', 'string'],
            'phonenumber_id' => ['required', 'string'],
            'webhook_url' => ['nullable', 'url'],
        ]);

        $webhookUrl = trim((string) ($data['webhook_url'] ?? '')) ?: url('/incoming.php');

        DB::table('gd_orders')
            ->where('id', $data['business_id'])
            ->where('admin_id', $request->session()->get('master_id'))
            ->update([
                'auth_token' => $data['auth_token'],
                'whatsapp_id' => $data['whatsapp_id'],
                'phone_number_id' => $data['phonenumber_id'],
                'webhook_url' => $webhookUrl,
                'status' => '1',
            ]);

        return redirect()->route('admin.settings.tokens')->with('success', 'API integrated successfully.');
    }

    public function storePackage(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer'],
            'package_key' => ['required', 'string', 'max:50'],
            'custom_message_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'marketing_message_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'utility_message_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'package_price' => ['nullable', 'numeric', 'min:0'],
            'marketing_package_price' => ['nullable', 'numeric', 'min:0'],
            'utility_package_price' => ['nullable', 'numeric', 'min:0'],
            'package_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $package = self::PACKAGES[$data['package_key']] ?? self::PACKAGES['starter'];
        $defaultMarketingLimit = (int) ($package['marketing_limit'] ?? 0);
        $defaultUtilityLimit = (int) ($package['utility_limit'] ?? 0);
        $marketingLimit = (int) ($data['marketing_message_limit'] ?? $defaultMarketingLimit);
        $utilityLimit = (int) ($data['utility_message_limit'] ?? $defaultUtilityLimit);
        $limit = $marketingLimit + $utilityLimit;
        if ($limit <= 0) {
            $limit = (int) ($data['custom_message_limit'] ?? ($defaultMarketingLimit + $defaultUtilityLimit));
        }
        $days = (int) ($data['package_days'] ?? 30);
        $marketingPrice = (float) ($data['marketing_package_price'] ?? 0);
        $utilityPrice = (float) ($data['utility_package_price'] ?? 0);
        $price = $marketingPrice + $utilityPrice;
        if ($price <= 0) {
            $price = (float) ($data['package_price'] ?? $package['price']);
        }

        if (!Schema::hasColumn('gd_orders', 'package_name')) {
            return back()->with('warning', 'Run the package migration before assigning packages.');
        }

        $updates = [
            'package_name' => $package['label'],
            'message_limit' => $limit,
            'messages_used' => 0,
            'package_price' => $price,
            'package_started_at' => now(),
            'package_ends_at' => now()->addDays($days),
            'limit_request_status' => 'approved',
            'limit_request_note' => null,
            'limit_request_at' => now(),
        ];

        foreach ([
            'marketing_message_limit' => $marketingLimit,
            'utility_message_limit' => $utilityLimit,
            'marketing_messages_used' => 0,
            'utility_messages_used' => 0,
            'marketing_package_price' => $marketingPrice,
            'utility_package_price' => $utilityPrice,
        ] as $column => $value) {
            if (Schema::hasColumn('gd_orders', $column)) {
                $updates[$column] = $value;
            }
        }

        DB::table('gd_orders')
            ->where('id', $data['business_id'])
            ->where('admin_id', $request->session()->get('master_id'))
            ->update($updates);

        return back()->with('success', 'Package updated successfully.');
    }
}
