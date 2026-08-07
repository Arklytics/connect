<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $bizId = (int) $request->session()->get('biz_id');
        $this->ensureHierarchyColumn();

        $groupsQuery = DB::table('gd_groups as g')
            ->leftJoin('gd_group_contacts as gc', 'gc.group_id', '=', 'g.id')
            ->leftJoin('gd_groups as parent', 'parent.id', '=', 'g.parent_id')
            ->where('g.biz_id', $bizId)
            ->select('g.*', 'parent.group_name as parent_name', DB::raw('COUNT(DISTINCT gc.contact_id) as contacts_count'))
            ->groupBy('g.id', 'parent.group_name')
            ->orderByRaw('CASE WHEN g.parent_id IS NULL THEN g.id ELSE g.parent_id END DESC')
            ->orderByRaw('CASE WHEN g.parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('g.group_name');

        $groups = $groupsQuery
            ->paginate(12)
            ->withQueryString();

        $allGroups = DB::table('gd_groups as g')
            ->leftJoin('gd_groups as parent', 'parent.id', '=', 'g.parent_id')
            ->where('g.biz_id', $bizId)
            ->select('g.*', 'parent.group_name as parent_name')
            ->orderBy('g.group_name')
            ->get();

        $rollupCounts = [];
        foreach ($groups as $group) {
            $targetIds = $this->groupTargetIds($bizId, (int) $group->id);
            $rollupCounts[(int) $group->id] = empty($targetIds)
                ? 0
                : DB::table('gd_group_contacts')
                    ->where('biz_id', $bizId)
                    ->whereIn('group_id', $targetIds)
                    ->distinct('contact_id')
                    ->count('contact_id');
        }

        $parentGroups = $allGroups->whereNull('parent_id')->values();
        $subgroups = $allGroups->whereNotNull('parent_id')->values();
        $totalGroups = $allGroups->count();
        $totalContacts = DB::table('gd_user_contacts')->where('biz_id', $bizId)->count();

        return view('business.groups.index', compact('groups', 'parentGroups', 'subgroups', 'rollupCounts', 'totalGroups', 'totalContacts'));
    }

    public function store(Request $request)
    {
        $this->ensureHierarchyColumn();
        $bizId = (int) $request->session()->get('biz_id');
        $data = $request->validate([
            'group_name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parentExists = DB::table('gd_groups')
                ->where('biz_id', $bizId)
                ->where('id', $parentId)
                ->whereNull('parent_id')
                ->exists();

            if (!$parentExists) {
                return back()->with('warning', 'Select a valid parent group for this subgroup.');
            }
        }

        DB::table('gd_groups')->insert([
            'biz_id' => $bizId,
            'parent_id' => $parentId > 0 ? $parentId : null,
            'group_name' => $data['group_name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('business.groups.index')->with('success', $parentId > 0 ? 'Subgroup saved successfully.' : 'Group saved successfully.');
    }

    public function storeContacts(Request $request)
    {
        $this->ensureHierarchyColumn();
        $bizId = (int) $request->session()->get('biz_id');
        $data = $request->validate([
            'parent_group_id' => ['required', 'integer'],
            'subgroup_id' => ['required', 'integer'],
            'bulk_contacts' => ['required', 'string'],
            'source' => ['nullable', 'string', 'max:120'],
            'whatsapp_opt_in' => ['nullable', 'boolean'],
        ]);

        $subgroupId = (int) $data['subgroup_id'];
        $parentId = (int) $data['parent_group_id'];
        if (!$this->isSubgroup($bizId, $subgroupId, $parentId)) {
            return back()->withInput()->with('warning', 'Please select a valid subgroup under the selected parent group.');
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $data['bulk_contacts']) ?: [];
        $source = trim((string) ($data['source'] ?? 'Bulk Group Add')) ?: 'Bulk Group Add';
        $optIn = !empty($data['whatsapp_opt_in']) ? 1 : 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $parsed = $this->parseContactLine($line);
            if ($parsed['phone'] === '') {
                if (trim($line) !== '') {
                    $skipped++;
                }
                continue;
            }

            $existing = DB::table('gd_user_contacts')
                ->where('biz_id', $bizId)
                ->where('phone_number', $parsed['phone'])
                ->first();

            $payload = [
                'biz_id' => $bizId,
                'group_id' => $subgroupId,
                'full_name' => $parsed['name'] !== '' ? $parsed['name'] : 'Unnamed Contact',
                'phone_number' => $parsed['phone'],
                'email' => $parsed['email'],
                'status' => 'new',
                'lead_stage' => 'lead',
                'lead_status' => 'new',
                'source' => $source,
                'whatsapp_opt_in' => $optIn,
                'last_contacted_at' => now(),
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('gd_user_contacts')->where('id', $existing->id)->update($payload);
                $contactId = (int) $existing->id;
                $updated++;
            } else {
                $payload['created_at'] = now();
                $contactId = DB::table('gd_user_contacts')->insertGetId($payload);
                $created++;
            }

            DB::table('gd_group_contacts')->insertOrIgnore([
                'biz_id' => $bizId,
                'group_id' => $subgroupId,
                'contact_id' => $contactId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()
            ->route('business.groups.index')
            ->with('success', "Contacts processed. Created {$created}, updated {$updated}, skipped {$skipped}.");
    }

    private function ensureHierarchyColumn(): void
    {
        if (!Schema::hasColumn('gd_groups', 'parent_id')) {
            DB::statement('ALTER TABLE gd_groups ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER biz_id');
        }
    }

    private function groupTargetIds(int $bizId, int $groupId): array
    {
        $ids = [$groupId];
        $pending = [$groupId];

        while (!empty($pending)) {
            $parentId = array_shift($pending);
            $children = DB::table('gd_groups')
                ->where('biz_id', $bizId)
                ->where('parent_id', $parentId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($children as $childId) {
                if (!in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $pending[] = $childId;
                }
            }
        }

        return $ids;
    }

    private function isSubgroup(int $bizId, int $subgroupId, int $parentId): bool
    {
        return DB::table('gd_groups')
            ->where('biz_id', $bizId)
            ->where('id', $subgroupId)
            ->where('parent_id', $parentId)
            ->exists();
    }

    private function parseContactLine(string $line): array
    {
        $line = trim($line);
        if ($line === '') {
            return ['name' => '', 'phone' => '', 'email' => ''];
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/,|\t|\|/', $line) ?: []), static fn ($part) => $part !== ''));
        $email = '';
        $phone = '';
        $nameParts = [];

        foreach ($parts as $part) {
            if ($email === '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $email = $part;
                continue;
            }

            $normalizedPhone = \ApiSupport::normalizePhone($part);
            $digits = preg_replace('/\D+/', '', $normalizedPhone) ?? '';
            if ($phone === '' && strlen($digits) >= 10) {
                $phone = $normalizedPhone;
                continue;
            }

            $nameParts[] = $part;
        }

        if ($phone === '') {
            $normalizedPhone = \ApiSupport::normalizePhone($line);
            $digits = preg_replace('/\D+/', '', $normalizedPhone) ?? '';
            if (strlen($digits) >= 10) {
                $phone = $normalizedPhone;
            }
        }

        return [
            'name' => trim(implode(' ', $nameParts)),
            'phone' => $phone,
            'email' => $email,
        ];
    }
}
