<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $admin = DB::table('gd_admin')->where('admin_number', preg_replace('/\D+/', '', $data['mobile']))->first();
        $valid = $admin && (Hash::check($data['password'], $admin->password) || hash_equals((string) $admin->password, $data['password']));

        if (!$valid) {
            return back()->with('error', 'Invalid mobile number or password.')->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('master_id', $admin->id);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('master_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
