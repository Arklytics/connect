<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    private const PACKAGES = [
        'starter' => ['label' => 'Starter', 'marketing_limit' => 500, 'utility_limit' => 500, 'price' => 0],
        'growth' => ['label' => 'Growth', 'marketing_limit' => 2500, 'utility_limit' => 2500, 'price' => 0],
        'pro' => ['label' => 'Pro', 'marketing_limit' => 7500, 'utility_limit' => 7500, 'price' => 0],
    ];

    public function index(Request $request)
    {
        $orders = DB::table('gd_orders')
            ->where('admin_id', $request->session()->get('master_id'))
            ->orderByDesc('id')
            ->get();

        return view('admin.orders.index', compact('orders'));
    }

    public function create()
    {
        return view('admin.orders.create', [
            'packages' => self::PACKAGES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_number' => ['required', 'string', 'max:30'],
            'business_email' => ['required', 'email', 'max:255'],
            'business_location' => ['required', 'string', 'max:255'],
            'business_description' => ['nullable', 'string'],
            'business_logo' => ['nullable', 'image', 'max:2048'],
            'package_key' => ['nullable', 'string', 'max:50'],
            'custom_message_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'marketing_message_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'utility_message_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $logoPath = null;
        if ($request->hasFile('business_logo')) {
            $logoPath = $request->file('business_logo')->storeAs('business-logos', Str::uuid() . '.' . $request->file('business_logo')->extension(), 'public');
        }

        $packageKey = (string) ($data['package_key'] ?? 'starter');
        $package = self::PACKAGES[$packageKey] ?? self::PACKAGES['starter'];
        $marketingLimit = (int) ($data['marketing_message_limit'] ?? $package['marketing_limit']);
        $utilityLimit = (int) ($data['utility_message_limit'] ?? $package['utility_limit']);
        $messageLimit = $marketingLimit + $utilityLimit;
        if ($messageLimit <= 0) {
            $messageLimit = (int) ($data['custom_message_limit'] ?? ($package['marketing_limit'] + $package['utility_limit']));
        }

        $orderData = [
            'admin_id' => $request->session()->get('master_id'),
            'full_name' => $data['full_name'],
            'mobile_number' => preg_replace('/\D+/', '', $data['mobile_number']),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'business_name' => $data['business_name'],
            'business_number' => preg_replace('/\D+/', '', $data['business_number']),
            'business_email' => $data['business_email'],
            'business_location' => $data['business_location'],
            'business_description' => $data['business_description'] ?? '',
            'business_logo' => $logoPath,
        ];

        if (Schema::hasColumn('gd_orders', 'package_name')) {
            $orderData['package_name'] = $package['label'];
            $orderData['message_limit'] = $messageLimit;
            $orderData['messages_used'] = 0;
            $orderData['package_price'] = $package['price'];
            $orderData['package_started_at'] = now();
            $orderData['package_ends_at'] = null;
            $orderData['limit_request_status'] = 'none';

            foreach ([
                'marketing_message_limit' => $marketingLimit,
                'utility_message_limit' => $utilityLimit,
                'marketing_messages_used' => 0,
                'utility_messages_used' => 0,
                'marketing_package_price' => 0,
                'utility_package_price' => 0,
            ] as $column => $value) {
                if (Schema::hasColumn('gd_orders', $column)) {
                    $orderData[$column] = $value;
                }
            }
        }

        DB::table('gd_orders')->insert($orderData);

        return redirect()->route('admin.orders.index')->with('success', 'Order added successfully.');
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
        $marketingLimit = (int) ($data['marketing_message_limit'] ?? $package['marketing_limit']);
        $utilityLimit = (int) ($data['utility_message_limit'] ?? $package['utility_limit']);
        $limit = $marketingLimit + $utilityLimit;
        if ($limit <= 0) {
            $limit = (int) ($data['custom_message_limit'] ?? ($package['marketing_limit'] + $package['utility_limit']));
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

        return back()->with('success', 'Package updated for the business.');
    }

    public function destroy(Request $request, int $order)
    {
        $masterId = (int) $request->session()->get('master_id');
        $business = DB::table('gd_orders')
            ->where('id', $order)
            ->where('admin_id', $masterId)
            ->first();

        if (!$business) {
            return back()->with('warning', 'Business not found.');
        }

        DB::transaction(function () use ($order) {
            foreach ([
                'gd_contact_followups',
                'gd_api_call_logs',
                'gd_whatsapp_sequence_plans',
                'gd_sent_messages',
                'gd_whatsapp_templates',
                'gd_group_contacts',
                'gd_user_contacts',
                'gd_groups',
                'gd_package_requests',
            ] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('biz_id', $order)->delete();
                }
            }

            DB::table('gd_orders')->where('id', $order)->delete();
        });

        if (!empty($business->business_logo)) {
            Storage::disk('public')->delete((string) $business->business_logo);
        }

        return back()->with('success', 'Business deleted successfully.');
    }
}
