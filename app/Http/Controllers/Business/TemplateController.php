<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = DB::table('gd_whatsapp_templates')
            ->where('biz_id', $request->session()->get('biz_id'))
            ->orderByDesc('id')
            ->get();

        return view('business.templates.index', compact('templates'));
    }

    public function create()
    {
        return view('business.templates.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'template_name' => ['required', 'string', 'max:255'],
            'message_title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'media_url' => ['nullable', 'url'],
            'buttons' => ['nullable', 'array'],
        ]);

        DB::table('gd_whatsapp_templates')->insert([
            'biz_id' => $request->session()->get('biz_id'),
            'template_name' => $data['template_name'],
            'message_title' => $data['message_title'],
            'message_body' => $data['message'] ?? '',
            'placeholders' => $data['placeholder'] ?? '',
            'subtitle' => $data['subtitle'] ?? '',
            'media_url' => $data['media_url'] ?? '',
            'status' => 'Pending',
            'buttons' => json_encode($data['buttons'] ?? []),
        ]);

        return redirect()->route('business.templates.index')->with('success', 'Template saved successfully.');
    }

    public function fetch(Request $request, int $template)
    {
        $template = DB::table('gd_whatsapp_templates')
            ->where('id', $template)
            ->where('biz_id', $request->session()->get('biz_id'))
            ->first();

        abort_if(!$template, 404);

        return response()->json([
            'message_title' => $template->message_title,
            'message_body' => $template->message_body,
            'subtitle' => $template->subtitle,
            'media_url' => $template->media_url,
            'buttons' => json_decode($template->buttons ?: '[]', true),
        ]);
    }
}
