<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = DB::table('gd_groups as g')
            ->leftJoin('gd_group_contacts as gc', 'gc.group_id', '=', 'g.id')
            ->where('g.biz_id', $request->session()->get('biz_id'))
            ->select('g.*', DB::raw('COUNT(gc.id) as contacts_count'))
            ->groupBy('g.id')
            ->orderByDesc('g.id')
            ->get();

        return view('business.groups.index', compact('groups'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['group_name' => ['required', 'string', 'max:255']]);

        DB::table('gd_groups')->insert([
            'biz_id' => $request->session()->get('biz_id'),
            'group_name' => $data['group_name'],
        ]);

        return redirect()->route('business.groups.index')->with('success', 'Group saved successfully.');
    }
}
