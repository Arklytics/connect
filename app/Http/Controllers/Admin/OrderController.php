<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrderController extends Controller
{
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
        return view('admin.orders.create');
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
        ]);

        $logoPath = null;
        if ($request->hasFile('business_logo')) {
            $logoPath = $request->file('business_logo')->storeAs('business-logos', Str::uuid() . '.' . $request->file('business_logo')->extension(), 'public');
        }

        DB::table('gd_orders')->insert([
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
        ]);

        return redirect()->route('admin.orders.index')->with('success', 'Order added successfully.');
    }
}
