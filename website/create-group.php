<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';

$message = '';
$message_type = 'success';

try {
    ApiSupport::ensureGroupHierarchyColumns($db);
} catch (Throwable $exception) {
    error_log('Group hierarchy ensure failed: ' . $exception->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $action = trim((string) ($_POST['action'] ?? 'create_group'));
    $group = trim((string) ($_POST['group_name'] ?? $_POST['parent_group_name'] ?? $_POST['subgroup_name'] ?? ''));
    $parentId = $action === 'create_subgroup' ? Security::intFrom($_POST['parent_id'] ?? null) : 0;
    $groupId = Security::intFrom($_POST['group_id'] ?? null);

    if ($action === 'delete_group') {
        if ($groupId <= 0) {
            $message = 'Select a valid group to delete.';
            $message_type = 'danger';
        } else {
            $groupStmt = $db->prepare('SELECT id, parent_id FROM gd_groups WHERE id = ? AND biz_id = ? LIMIT 1');
            $groupStmt->bind_param('ii', $groupId, $biz_id);
            $groupStmt->execute();
            $deleteGroup = $groupStmt->get_result()->fetch_assoc();

            if (!$deleteGroup) {
                $message = 'Group not found.';
                $message_type = 'danger';
            } else {
                $deleteIds = [(int) $deleteGroup['id']];
                if (empty($deleteGroup['parent_id'])) {
                    $childStmt = $db->prepare('SELECT id FROM gd_groups WHERE parent_id = ? AND biz_id = ?');
                    $childStmt->bind_param('ii', $groupId, $biz_id);
                    $childStmt->execute();
                    $children = $childStmt->get_result();
                    while ($child = $children->fetch_assoc()) {
                        $deleteIds[] = (int) ($child['id'] ?? 0);
                    }
                }

                $deleteIds = array_values(array_filter(array_unique($deleteIds)));
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                $types = 'i' . str_repeat('i', count($deleteIds));
                $values = array_merge([(int) $biz_id], $deleteIds);

                $contactStmt = $db->prepare('DELETE FROM gd_group_contacts WHERE biz_id = ? AND group_id IN (' . $placeholders . ')');
                $bind = [$types];
                foreach ($values as $index => $value) {
                    $bind[] = &$values[$index];
                }
                $contactStmt->bind_param(...$bind);
                $contactStmt->execute();

                $directContactStmt = $db->prepare('UPDATE gd_user_contacts SET group_id = NULL WHERE biz_id = ? AND group_id IN (' . $placeholders . ')');
                $bind = [$types];
                foreach ($values as $index => $value) {
                    $bind[] = &$values[$index];
                }
                $directContactStmt->bind_param(...$bind);
                $directContactStmt->execute();

                $groupTypes = str_repeat('i', count($deleteIds)) . 'i';
                $groupValues = array_merge($deleteIds, [(int) $biz_id]);
                $deleteStmt = $db->prepare('DELETE FROM gd_groups WHERE id IN (' . $placeholders . ') AND biz_id = ?');
                $bind = [$groupTypes];
                foreach ($groupValues as $index => $value) {
                    $bind[] = &$groupValues[$index];
                }
                $deleteStmt->bind_param(...$bind);
                $deleteStmt->execute();

                $message = empty($deleteGroup['parent_id']) ? 'Parent group and its subgroups deleted.' : 'Subgroup deleted.';
                $message_type = 'success';
            }
        }
    } elseif ($action === 'update_group') {
        if ($groupId <= 0 || $group === '') {
            $message = 'Group name is required.';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare('UPDATE gd_groups SET group_name = ?, updated_at = NOW() WHERE id = ? AND biz_id = ?');
            $stmt->bind_param('sii', $group, $groupId, $biz_id);
            if ($stmt->execute()) {
                $message = 'Group updated.';
                $message_type = 'success';
                $_GET['parent_id'] = (string) ($parentId > 0 ? $parentId : $groupId);
            } else {
                $message = 'Unable to update group.';
                $message_type = 'danger';
            }
        }
    } elseif ($parentId > 0) {
        $parentStmt = $db->prepare('SELECT id FROM gd_groups WHERE id = ? AND biz_id = ? AND parent_id IS NULL LIMIT 1');
        $parentStmt->bind_param('ii', $parentId, $biz_id);
        $parentStmt->execute();
        if (!$parentStmt->get_result()->fetch_assoc()) {
            $message = 'Select a valid parent group before adding a subgroup.';
            $message_type = 'danger';
        }
    }

    if ($message === '' && !in_array($action, ['delete_group', 'update_group'], true)) {
        if ($parentId > 0) {
            $stmt = $db->prepare('INSERT INTO gd_groups (biz_id, parent_id, group_name, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
            $stmt->bind_param('iis', $biz_id, $parentId, $group);
        } else {
            $stmt = $db->prepare('INSERT INTO gd_groups (biz_id, parent_id, group_name, created_at, updated_at) VALUES (?, NULL, ?, NOW(), NOW())');
            $stmt->bind_param('is', $biz_id, $group);
        }

        if ($group !== '' && $stmt->execute()) {
            $message = $parentId > 0 ? 'New subgroup saved!' : 'New parent group saved!';
            $message_type = 'success';
            if ($parentId > 0) {
                $_GET['parent_id'] = (string) $parentId;
            } else {
                $_GET['parent_id'] = (string) mysqli_insert_id($db);
            }
        } else {
            $message = 'Unable to save group.';
            $message_type = 'danger';
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$selectedParentId = Security::intFrom($_GET['parent_id'] ?? null);
$parentGroups = [];
$subgroupsByParent = [];
$contactCounts = [];

$stmt = $db->prepare('
    SELECT g.id, g.parent_id, g.group_name, parent.group_name AS parent_name
    FROM gd_groups g
    LEFT JOIN gd_groups parent ON parent.id = g.parent_id
    WHERE g.biz_id = ?
    ORDER BY g.parent_id IS NOT NULL, g.group_name
');
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$groupQuery = $stmt->get_result();

while ($groupRow = $groupQuery->fetch_assoc()) {
    $groupId = (int) ($groupRow['id'] ?? 0);
    if ($groupId <= 0) {
        continue;
    }

    $targetIds = ApiSupport::groupTargetIds($db, (int) $biz_id, $groupId, true);
    $contactCounts[$groupId] = 0;
    if (!empty($targetIds)) {
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $types = 'i' . str_repeat('i', count($targetIds));
        $values = array_merge([(int) $biz_id], $targetIds);
        $countStmt = $db->prepare('SELECT COUNT(DISTINCT contact_id) AS total FROM gd_group_contacts WHERE biz_id = ? AND group_id IN (' . $placeholders . ')');
        $bind = [$types];
        foreach ($values as $index => $value) {
            $bind[] = &$values[$index];
        }
        $countStmt->bind_param(...$bind);
        $countStmt->execute();
        $contactCounts[$groupId] = (int) (($countStmt->get_result()->fetch_assoc()['total'] ?? 0));
    }

    if (empty($groupRow['parent_id'])) {
        $parentGroups[] = $groupRow;
    } else {
        $subgroupsByParent[(int) $groupRow['parent_id']][] = $groupRow;
    }
}

if ($selectedParentId <= 0 && !empty($parentGroups)) {
    $selectedParentId = (int) ($parentGroups[0]['id'] ?? 0);
}

$filteredParentGroups = array_values(array_filter($parentGroups, static function (array $parent) use ($search): bool {
    return $search === '' || stripos((string) ($parent['group_name'] ?? ''), $search) !== false;
}));

$selectedParent = null;
foreach ($parentGroups as $parent) {
    if ((int) ($parent['id'] ?? 0) === $selectedParentId) {
        $selectedParent = $parent;
        break;
    }
}
$relatedSubgroups = $subgroupsByParent[$selectedParentId] ?? [];
?>

<div class="position-fixed bottom-0 end-0 p-3 wg-footer-toast-container">
    <?php if ($message !== ''): ?>
        <div class="toast align-items-center text-bg-<?php echo h($message_type); ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?php echo h($message); ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <div>
                    <h1>Groups & Subgroups</h1>
                    <p>Select a parent group first, review related subgroups, then add contacts or create new segments.</p>
                </div>
                <button class="btn btn-success" type="button" data-bs-toggle="offcanvas" data-bs-target="#groupOffcanvas" aria-controls="groupOffcanvas">
                    <i class="bi bi-plus-circle me-1"></i> Add Group/Subgroup
                </button>
            </div>

            <div class="wg-group-layout">
                <section class="wg-card p-0 overflow-hidden">
                    <div class="wg-table-toolbar">
                        <div>
                            <h5>Parent Groups</h5>
                            <p>Search and select the list you want to manage.</p>
                        </div>
                    </div>
                    <form method="get" class="wg-group-search">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" name="q" value="<?php echo h($search); ?>" placeholder="Search parent groups">
                        </div>
                    </form>

                    <div class="wg-parent-list">
                        <?php if (empty($filteredParentGroups)): ?>
                            <div class="wg-empty-state py-4">
                                <i class="bi bi-diagram-3"></i>
                                <h5>No parent groups found</h5>
                                <p>Create a parent group to organize subgroups and contacts.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($filteredParentGroups as $parent): ?>
                                <?php
                                $parentId = (int) ($parent['id'] ?? 0);
                                $isSelected = $parentId === $selectedParentId;
                                $subgroupCount = count($subgroupsByParent[$parentId] ?? []);
                                ?>
                                <div class="wg-parent-row-wrap">
                                <a class="wg-parent-row <?php echo $isSelected ? 'active' : ''; ?>" href="<?php echo h(app_url('business/create-group?q=' . rawurlencode($search) . '&parent_id=' . $parentId)); ?>">
                                    <span class="wg-parent-icon"><i class="bi bi-folder2-open"></i></span>
                                    <span>
                                        <strong><?php echo h($parent['group_name']); ?></strong>
                                        <small><?php echo h((string) $subgroupCount); ?> subgroups | <?php echo h((string) ($contactCounts[$parentId] ?? 0)); ?> contacts</small>
                                    </span>
                                </a>
                                <div class="wg-row-actions">
                                    <button class="btn btn-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#editGroupOffcanvas" data-group-id="<?php echo h((string) $parentId); ?>" data-group-name="<?php echo h($parent['group_name']); ?>" data-parent-id="0">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-light btn-sm text-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteGroupModal" data-group-id="<?php echo h((string) $parentId); ?>" data-group-name="<?php echo h($parent['group_name']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="wg-card p-0 overflow-hidden">
                    <div class="wg-table-toolbar">
                        <div>
                            <h5><?php echo h((string) ($selectedParent['group_name'] ?? 'Select Parent Group')); ?></h5>
                            <p><?php echo h((string) count($relatedSubgroups)); ?> related subgroups | <?php echo h((string) ($contactCounts[$selectedParentId] ?? 0)); ?> contacts including subgroups</p>
                        </div>
                        <?php if ($selectedParentId > 0): ?>
                            <a class="btn btn-light btn-sm" href="<?php echo h(app_url('business/view-contacts?group_id=' . $selectedParentId)); ?>">
                                <i class="bi bi-eye me-1"></i> View Contacts
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($selectedParentId <= 0): ?>
                        <div class="wg-empty-state">
                            <i class="bi bi-folder-plus"></i>
                            <h5>Select a parent group</h5>
                            <p>Choose a parent group from the left to view and manage its subgroups.</p>
                        </div>
                    <?php elseif (empty($relatedSubgroups)): ?>
                        <div class="wg-empty-state">
                            <i class="bi bi-folder-plus"></i>
                            <h5>No subgroups yet</h5>
                            <p>Add a subgroup under this parent before importing segmented contacts.</p>
                            <button class="btn btn-success" type="button" data-bs-toggle="offcanvas" data-bs-target="#groupOffcanvas">Add Subgroup</button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table wg-template-table">
                                <thead>
                                    <tr>
                                        <th>Subgroup</th>
                                        <th>Contacts</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($relatedSubgroups as $subgroup): ?>
                                        <?php $subgroupId = (int) ($subgroup['id'] ?? 0); ?>
                                        <tr>
                                            <td>
                                                <div class="wg-template-name"><?php echo h($subgroup['group_name']); ?></div>
                                                <div class="text-muted small">Subgroup of <?php echo h((string) ($selectedParent['group_name'] ?? '')); ?></div>
                                            </td>
                                            <td><span class="wg-pill"><?php echo h((string) ($contactCounts[$subgroupId] ?? 0)); ?> contacts</span></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="<?php echo h(app_url('business/add-contacts-group?group_id=' . $subgroupId)); ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i> Add</a>
                                                    <a href="<?php echo h(app_url('business/view-contacts?group_id=' . $subgroupId)); ?>" class="btn btn-light btn-sm"><i class="bi bi-eye me-1"></i> View</a>
                                                    <button class="btn btn-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#editGroupOffcanvas" data-group-id="<?php echo h((string) $subgroupId); ?>" data-group-name="<?php echo h($subgroup['group_name']); ?>" data-parent-id="<?php echo h((string) $selectedParentId); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-light btn-sm text-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteGroupModal" data-group-id="<?php echo h((string) $subgroupId); ?>" data-group-name="<?php echo h($subgroup['group_name']); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</div>

<div class="offcanvas offcanvas-end wg-offcanvas" tabindex="-1" id="groupOffcanvas" aria-labelledby="groupOffcanvasLabel">
    <div class="offcanvas-header">
        <div>
            <span class="wg-kicker">Group Manager</span>
            <h5 class="offcanvas-title" id="groupOffcanvasLabel">Add Group/Subgroup</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form action="" method="POST" class="wg-offcanvas-form">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="create_group">
            <div class="wg-form-section">
                <div class="wg-section-heading">
                    <span><i class="bi bi-folder-plus"></i></span>
                    <div>
                        <h5>Create Parent Group</h5>
                        <p>Use parent groups as broad lists such as Customers, Leads, or Dealers.</p>
                    </div>
                </div>
                <label class="form-label" for="parent_group_name">Parent Group Name</label>
                <input type="text" class="form-control" id="parent_group_name" name="parent_group_name" placeholder="Example: Retail Leads" required>
                <button class="btn btn-success w-100 mt-3" type="submit"><i class="bi bi-check2 me-1"></i> Create Parent Group</button>
            </div>
        </form>

        <form action="" method="POST" class="wg-offcanvas-form mt-3">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="create_subgroup">
            <div class="wg-form-section">
                <div class="wg-section-heading">
                    <span><i class="bi bi-diagram-3"></i></span>
                    <div>
                        <h5>Create Subgroup</h5>
                        <p>Select the parent group first, then add a focused subgroup.</p>
                    </div>
                </div>
                <label class="form-label" for="parent_id">Parent Group</label>
                <select class="form-control" id="parent_id" name="parent_id" required>
                    <option value="">Select parent group</option>
                    <?php foreach ($parentGroups as $parent): ?>
                        <option value="<?php echo h((string) $parent['id']); ?>" <?php echo (int) $parent['id'] === $selectedParentId ? 'selected' : ''; ?>>
                            <?php echo h($parent['group_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label mt-3" for="subgroup_name">Subgroup Name</label>
                <input type="text" class="form-control" id="subgroup_name" name="subgroup_name" placeholder="Example: Hot Leads" required>
                <button class="btn btn-primary w-100 mt-3" type="submit"><i class="bi bi-check2 me-1"></i> Create Subgroup</button>
            </div>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-end wg-offcanvas" tabindex="-1" id="editGroupOffcanvas" aria-labelledby="editGroupOffcanvasLabel">
    <div class="offcanvas-header">
        <div>
            <span class="wg-kicker">Edit Segment</span>
            <h5 class="offcanvas-title" id="editGroupOffcanvasLabel">Edit Group</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form action="" method="POST" class="wg-offcanvas-form">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="update_group">
            <input type="hidden" name="group_id" id="edit_group_id">
            <input type="hidden" name="parent_id" id="edit_parent_id">
            <div class="wg-form-section">
                <div class="wg-section-heading">
                    <span><i class="bi bi-pencil-square"></i></span>
                    <div>
                        <h5>Group Details</h5>
                        <p>Update the group or subgroup name used across contacts and campaigns.</p>
                    </div>
                </div>
                <label class="form-label" for="edit_group_name">Group Name</label>
                <input type="text" class="form-control" id="edit_group_name" name="group_name" required>
                <button class="btn btn-success w-100 mt-3" type="submit"><i class="bi bi-check2 me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="delete_group">
                <input type="hidden" name="group_id" id="delete_group_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteGroupModalLabel">Delete Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Delete <strong id="delete_group_name">this group</strong>?</p>
                    <p class="text-muted mb-0">Deleting a parent group also deletes its subgroups and removes their group-contact links.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editGroupOffcanvas')?.addEventListener('show.bs.offcanvas', function (event) {
    const button = event.relatedTarget;
    if (!button) {
        return;
    }

    document.getElementById('edit_group_id').value = button.getAttribute('data-group-id') || '';
    document.getElementById('edit_group_name').value = button.getAttribute('data-group-name') || '';
    document.getElementById('edit_parent_id').value = button.getAttribute('data-parent-id') || '';
});

document.getElementById('deleteGroupModal')?.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) {
        return;
    }

    document.getElementById('delete_group_id').value = button.getAttribute('data-group-id') || '';
    document.getElementById('delete_group_name').textContent = button.getAttribute('data-group-name') || 'this group';
});
</script>

<?php include 'footer.php'; ?>
