<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('business.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $business = DB::table('gd_orders')->where('mobile_number', preg_replace('/\D+/', '', $data['mobile']))->first();

        if (!$business || !Hash::check($data['password'], $business->password)) {
            return back()->with('error', 'Invalid mobile number or password.')->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('biz_id', $business->id);

        return redirect()->route('business.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('biz_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('business.login');
    }
}
