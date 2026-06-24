<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        return $this->create($request);
    }

    public function create(Request $request)
    {
        $bizId = $request->session()->get('biz_id');
        $stage = $request->query('stage');
        $status = $request->query('status');
        $followUpOnly = $request->boolean('follow_up_only');

        $contactsQuery = DB::table('gd_user_contacts as c')
            ->leftJoin('gd_groups as g', 'g.id', '=', 'c.group_id')
            ->where('c.biz_id', $bizId)
            ->select('c.*', 'g.group_name');

        if ($stage) {
            $contactsQuery->where('c.lead_stage', $stage);
        }

        if ($status) {
            $contactsQuery->where('c.lead_status', $status);
        }

        if ($followUpOnly) {
            $contactsQuery->whereNotNull('c.next_follow_up_at')
                ->whereDate('c.next_follow_up_at', '<=', now()->toDateString());
        }

        $contacts = $contactsQuery
            ->orderByRaw('CASE WHEN c.next_follow_up_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('c.id')
            ->get();

        $stats = [
            'total' => DB::table('gd_user_contacts')->where('biz_id', $bizId)->count(),
            'leads' => DB::table('gd_user_contacts')->where('biz_id', $bizId)->where('lead_stage', 'lead')->count(),
            'won' => DB::table('gd_user_contacts')->where('biz_id', $bizId)->where('lead_status', 'won')->count(),
            'lost' => DB::table('gd_user_contacts')->where('biz_id', $bizId)->where('lead_status', 'lost')->count(),
            'due_followups' => DB::table('gd_user_contacts')
                ->where('biz_id', $bizId)
                ->whereNotNull('next_follow_up_at')
                ->whereDate('next_follow_up_at', '<=', now()->toDateString())
                ->count(),
            'opted_in' => DB::table('gd_user_contacts')->where('biz_id', $bizId)->where('whatsapp_opt_in', 1)->count(),
        ];

        return view('business.contacts.create', [
            'groups' => DB::table('gd_groups')->where('biz_id', $bizId)->orderBy('group_name')->get(),
            'contacts' => $contacts,
            'stats' => $stats,
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
            'group_id' => ['nullable', 'integer', 'exists:gd_groups,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'lead_stage' => ['nullable', Rule::in(['lead', 'opportunity', 'customer'])],
            'lead_status' => ['nullable', Rule::in(['new', 'contacted', 'qualified', 'won', 'lost'])],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'next_follow_up_at' => ['nullable', 'date'],
            'whatsapp_opt_in' => ['nullable', 'boolean'],
        ]);
        $bizId = $request->session()->get('biz_id');
        $phone = preg_replace('/[^\d+]/', '', $data['mobile_number']);
        $leadStage = $data['lead_stage'] ?? 'lead';
        $leadStatus = $data['lead_status'] ?? 'new';
        $nextFollowUpAt = !empty($data['next_follow_up_at']) ? Carbon::parse($data['next_follow_up_at']) : null;

        $contactId = DB::table('gd_user_contacts')->insertGetId([
            'biz_id' => $bizId,
            'group_id' => $data['group_id'] ?? null,
            'full_name' => $data['full_name'],
            'phone_number' => $phone,
            'email' => $data['email'] ?? '',
            'status' => $leadStatus,
            'lead_stage' => $leadStage,
            'lead_status' => $leadStatus,
            'source' => $data['source'] ?? 'Manual',
            'whatsapp_opt_in' => !empty($data['whatsapp_opt_in']) ? 1 : 0,
            'last_contacted_at' => now(),
            'next_follow_up_at' => $nextFollowUpAt,
            'lost_reason' => $data['lost_reason'] ?? null,
            'crm_notes' => $data['notes'] ?? null,
            'won_at' => $leadStatus === 'won' ? now() : null,
            'lost_at' => $leadStatus === 'lost' ? now() : null,
        ]);

        if (!empty($data['group_id'])) {
            DB::table('gd_group_contacts')->insertOrIgnore([
                'biz_id' => $bizId,
                'group_id' => $data['group_id'],
                'contact_id' => $contactId,
            ]);
        }

        return redirect()->route('business.contacts.index')->with('success', 'Contact saved successfully.');
    }

    public function importForm(Request $request)
    {
        $groups = DB::table('gd_groups')->where('biz_id', $request->session()->get('biz_id'))->orderBy('group_name')->get();
        return view('business.contacts.import', compact('groups'));
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:gd_groups,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $bizId = $request->session()->get('biz_id');
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = array_shift($rows);
        $headers = [];

        foreach ($headerRow as $column => $value) {
            $headers[$column] = $this->normalizeHeader((string) $value);
        }

        $imported = 0;
        foreach ($rows as $row) {
            $payload = $this->mapImportRow($headers, $row);
            if (!$this->hasUsefulContactData($payload)) {
                continue;
            }

            $this->upsertContact($bizId, (int) $data['group_id'], $payload);
            $imported++;
        }

        return redirect()->route('business.contacts.index')->with('success', "Imported {$imported} contacts successfully.");
    }

    public function group(Request $request, int $group)
    {
        $contacts = DB::table('gd_group_contacts as gc')
            ->join('gd_user_contacts as c', 'c.id', '=', 'gc.contact_id')
            ->where('gc.biz_id', $request->session()->get('biz_id'))
            ->where('gc.group_id', $group)
            ->select('c.*')
            ->get();

        return view('business.contacts.group', compact('contacts'));
    }

    public function updateStatus(Request $request, int $contact)
    {
        $data = $request->validate([
            'lead_stage' => ['required', Rule::in(['lead', 'opportunity', 'customer'])],
            'lead_status' => ['required', Rule::in(['new', 'contacted', 'qualified', 'won', 'lost'])],
            'next_follow_up_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $bizId = $request->session()->get('biz_id');
        $contactRow = DB::table('gd_user_contacts')->where('biz_id', $bizId)->where('id', $contact)->first();

        if (!$contactRow) {
            return back()->with('warning', 'Contact not found.');
        }

        DB::table('gd_user_contacts')->where('id', $contact)->update([
            'lead_stage' => $data['lead_stage'],
            'lead_status' => $data['lead_status'],
            'status' => $data['lead_status'],
            'next_follow_up_at' => !empty($data['next_follow_up_at']) ? Carbon::parse($data['next_follow_up_at']) : null,
            'lost_reason' => $data['lead_status'] === 'lost' ? ($data['lost_reason'] ?? null) : null,
            'crm_notes' => $data['notes'] ?? $contactRow->crm_notes,
            'won_at' => $data['lead_status'] === 'won' ? now() : $contactRow->won_at,
            'lost_at' => $data['lead_status'] === 'lost' ? now() : $contactRow->lost_at,
            'last_contacted_at' => now(),
        ]);

        return back()->with('success', 'Contact status updated.');
    }

    public function storeFollowUp(Request $request, int $contact)
    {
        $data = $request->validate([
            'sequence_name' => ['required', 'string', 'max:150'],
            'first_follow_up_at' => ['required', 'date'],
            'step_gap_days' => ['required', 'integer', 'min:1', 'max:30'],
            'steps' => ['required', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string'],
        ]);

        $bizId = $request->session()->get('biz_id');
        $contactRow = DB::table('gd_user_contacts')->where('biz_id', $bizId)->where('id', $contact)->first();

        if (!$contactRow) {
            return back()->with('warning', 'Contact not found.');
        }

        $startAt = Carbon::parse($data['first_follow_up_at']);
        $created = 0;

        for ($step = 1; $step <= (int) $data['steps']; $step++) {
            DB::table('gd_contact_followups')->insert([
                'biz_id' => $bizId,
                'contact_id' => $contact,
                'channel' => 'whatsapp',
                'sequence_name' => $data['sequence_name'],
                'step_no' => $step,
                'scheduled_at' => $startAt->copy()->addDays(max(0, $step - 1) * (int) $data['step_gap_days']),
                'status' => 'pending',
                'message' => null,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        DB::table('gd_user_contacts')->where('id', $contact)->update([
            'next_follow_up_at' => $startAt,
            'whatsapp_opt_in' => 1,
        ]);

        return back()->with('success', "{$created} WhatsApp follow-up steps queued for {$contactRow->full_name}.");
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim($value, '_');
    }

    private function mapImportRow(array $headers, array $row): array
    {
        $normalized = [];
        foreach ($headers as $column => $header) {
            $normalized[$header] = trim((string) ($row[$column] ?? ''));
        }

        return $normalized;
    }

    private function hasUsefulContactData(array $payload): bool
    {
        $phone = preg_replace('/[^\d+]/', '', (string) ($payload['phone_number'] ?? $payload['mobile_number'] ?? $payload['phone'] ?? ''));

        return !empty($phone);
    }

    private function upsertContact(int $bizId, int $groupId, array $payload): void
    {
        $fullName = $payload['full_name'] ?? $payload['name'] ?? 'Unnamed Contact';
        $phone = preg_replace('/[^\d+]/', '', (string) ($payload['phone_number'] ?? $payload['mobile_number'] ?? $payload['phone'] ?? ''));
        if ($phone === '') {
            return;
        }
        $email = $payload['email'] ?? '';
        $leadStage = $payload['lead_stage'] ?? 'lead';
        $leadStatus = $payload['lead_status'] ?? 'new';
        $source = $payload['source'] ?? 'Import';
        $nextFollowUpAt = !empty($payload['next_follow_up_at'] ?? '') ? Carbon::parse($payload['next_follow_up_at']) : null;
        $optIn = $this->truthy($payload['whatsapp_opt_in'] ?? $payload['opt_in'] ?? false);

        $existing = DB::table('gd_user_contacts')
            ->where('biz_id', $bizId)
            ->where('phone_number', $phone)
            ->first();

        $record = [
            'biz_id' => $bizId,
            'group_id' => $groupId,
            'full_name' => $fullName,
            'phone_number' => $phone,
            'email' => $email,
            'status' => $leadStatus,
            'lead_stage' => $leadStage,
            'lead_status' => $leadStatus,
            'source' => $source,
            'whatsapp_opt_in' => $optIn ? 1 : 0,
            'next_follow_up_at' => $nextFollowUpAt,
            'lost_reason' => $payload['lost_reason'] ?? null,
            'crm_notes' => $payload['notes'] ?? null,
            'last_contacted_at' => now(),
            'won_at' => $leadStatus === 'won' ? now() : null,
            'lost_at' => $leadStatus === 'lost' ? now() : null,
        ];

        if ($existing) {
            DB::table('gd_user_contacts')->where('id', $existing->id)->update($record);
            $contactId = $existing->id;
        } else {
            $record['created_at'] = now();
            $record['updated_at'] = now();
            $contactId = DB::table('gd_user_contacts')->insertGetId($record);
        }

        DB::table('gd_group_contacts')->insertOrIgnore([
            'biz_id' => $bizId,
            'group_id' => $groupId,
            'contact_id' => $contactId,
        ]);
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
