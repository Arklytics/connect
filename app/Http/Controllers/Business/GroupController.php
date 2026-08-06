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

        $groups = DB::table('gd_groups as g')
            ->leftJoin('gd_group_contacts as gc', 'gc.group_id', '=', 'g.id')
            ->leftJoin('gd_groups as parent', 'parent.id', '=', 'g.parent_id')
            ->where('g.biz_id', $bizId)
            ->select('g.*', 'parent.group_name as parent_name', DB::raw('COUNT(DISTINCT gc.contact_id) as contacts_count'))
            ->groupBy('g.id', 'parent.group_name')
            ->orderByRaw('CASE WHEN g.parent_id IS NULL THEN g.id ELSE g.parent_id END DESC')
            ->orderByRaw('CASE WHEN g.parent_id IS NULL THEN 0 ELSE 1 END')
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

        $parentGroups = $groups->whereNull('parent_id')->values();

        return view('business.groups.index', compact('groups', 'parentGroups', 'rollupCounts'));
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
}
