<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'businessCount' => DB::table('gd_orders')->count(),
            'activeBusinessCount' => DB::table('gd_orders')->where('status', '1')->count(),
        ]);
    }
}
