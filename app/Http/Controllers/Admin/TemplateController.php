<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = DB::table('gd_whatsapp_templates as t')
            ->join('gd_orders as o', 'o.id', '=', 't.biz_id')
            ->where('o.admin_id', $request->session()->get('master_id'))
            ->select('t.*', 'o.business_name')
            ->orderByDesc('t.id')
            ->get();

        return view('admin.templates.index', compact('templates'));
    }
}
