<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SequenceController extends Controller
{
    public function index(Request $request)
    {
        $bizId = $request->session()->get('biz_id');

        return view('business.sequences.index', [
            'plans' => DB::table('gd_whatsapp_sequence_plans')
                ->where('biz_id', $bizId)
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'followUps' => DB::table('gd_contact_followups as f')
                ->join('gd_user_contacts as c', 'c.id', '=', 'f.contact_id')
                ->where('f.biz_id', $bizId)
                ->select('f.*', 'c.full_name', 'c.phone_number')
                ->orderByDesc('f.id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan_name' => ['required', 'string', 'max:150'],
            'audience' => ['nullable', 'string', 'max:150'],
            'objective' => ['nullable', 'string', 'max:255'],
            'sequence_type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'step_title' => ['array'],
            'step_title.*' => ['nullable', 'string', 'max:120'],
            'step_delay' => ['array'],
            'step_delay.*' => ['nullable', 'integer', 'min:0', 'max:30'],
            'step_message' => ['array'],
            'step_message.*' => ['nullable', 'string'],
        ]);

        $bizId = $request->session()->get('biz_id');
        $steps = [];

        foreach ((array) ($data['step_message'] ?? []) as $stepNo => $message) {
            $message = trim((string) $message);
            if ($message === '') {
                continue;
            }

            $steps[] = [
                'step_no' => (int) $stepNo,
                'title' => trim((string) ($data['step_title'][$stepNo] ?? 'Step ' . $stepNo)) ?: 'Step ' . $stepNo,
                'delay_days' => (int) ($data['step_delay'][$stepNo] ?? 0),
                'message' => $message,
            ];
        }

        if (empty($steps)) {
            return back()->with('warning', 'Add at least one step before saving the sequence.');
        }

        DB::table('gd_whatsapp_sequence_plans')->insert([
            'biz_id' => $bizId,
            'plan_name' => $data['plan_name'],
            'audience' => $data['audience'] ?? null,
            'objective' => $data['objective'] ?? null,
            'sequence_type' => $data['sequence_type'] ?? 'custom',
            'step_count' => count($steps),
            'default_gap_days' => (int) ($steps[0]['delay_days'] ?? 0),
            'structure_json' => json_encode([
                'plan_name' => $data['plan_name'],
                'audience' => $data['audience'] ?? null,
                'objective' => $data['objective'] ?? null,
                'sequence_type' => $data['sequence_type'] ?? 'custom',
                'steps' => $steps,
                'notes' => $data['notes'] ?? null,
                'response_rule' => [
                    'quick_reply_max_minutes' => 120,
                    'slow_reply_min_minutes' => 121,
                ],
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Sequence plan saved successfully.');
    }
}
