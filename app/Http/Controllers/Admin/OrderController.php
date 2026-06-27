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
        'starter' => ['label' => 'Starter', 'limit' => 1000, 'price' => 0],
        'growth' => ['label' => 'Growth', 'limit' => 5000, 'price' => 0],
        'pro' => ['label' => 'Pro', 'limit' => 15000, 'price' => 0],
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
        ]);

        $logoPath = null;
        if ($request->hasFile('business_logo')) {
            $logoPath = $request->file('business_logo')->storeAs('business-logos', Str::uuid() . '.' . $request->file('business_logo')->extension(), 'public');
        }

        $packageKey = (string) ($data['package_key'] ?? 'starter');
        $package = self::PACKAGES[$packageKey] ?? self::PACKAGES['starter'];
        $messageLimit = (int) ($data['custom_message_limit'] ?? $package['limit']);

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
            'package_price' => ['nullable', 'numeric', 'min:0'],
            'package_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $package = self::PACKAGES[$data['package_key']] ?? self::PACKAGES['starter'];
        $limit = (int) ($data['custom_message_limit'] ?? $package['limit']);
        $days = (int) ($data['package_days'] ?? 30);
        $price = (float) ($data['package_price'] ?? $package['price']);

        if (!Schema::hasColumn('gd_orders', 'package_name')) {
            return back()->with('warning', 'Run the package migration before assigning packages.');
        }

        DB::table('gd_orders')
            ->where('id', $data['business_id'])
            ->where('admin_id', $request->session()->get('master_id'))
            ->update([
                'package_name' => $package['label'],
                'message_limit' => $limit,
                'messages_used' => 0,
                'package_price' => $price,
                'package_started_at' => now(),
                'package_ends_at' => now()->addDays($days),
                'limit_request_status' => 'approved',
                'limit_request_note' => null,
                'limit_request_at' => now(),
            ]);

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
