<?php
declare(strict_types=1);

include '../session.php';
include '../db_conn.php';

$bizId = Auth::requireLogin();
$message = '';
$messageType = 'success';

include 'header.php';

try {
    AiAutoReply::ensureSchema($db);
} catch (Throwable $exception) {
    $message = 'Unable to prepare AI knowledge settings: ' . $exception->getMessage();
    $messageType = 'danger';
}

$business = [];
try {
    $stmt = $db->prepare('SELECT business_name, ai_auto_reply_enabled FROM gd_orders WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $bizId);
    $stmt->execute();
    $business = $stmt->get_result()->fetch_assoc() ?: [];
} catch (Throwable $exception) {
    $message = 'Unable to load AI feature status: ' . $exception->getMessage();
    $messageType = 'danger';
}

$featureEnabled = (int) ($business['ai_auto_reply_enabled'] ?? 0) === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $featureEnabled && $messageType !== 'danger') {
    Security::verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_section') {
            $sectionId = max(0, (int) ($_POST['section_id'] ?? 0));
            $title = trim((string) ($_POST['title'] ?? ''));
            $content = trim((string) ($_POST['content'] ?? ''));
            $status = (string) ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

            if ($title === '' || $content === '') {
                throw new RuntimeException('Section title and content are required.');
            }

            if ($sectionId > 0) {
                $stmt = $db->prepare('UPDATE gd_ai_knowledge_sections SET title = ?, content = ?, status = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND biz_id = ?');
                $stmt->bind_param('sssiii', $title, $content, $status, $sortOrder, $sectionId, $bizId);
                $stmt->execute();
                $message = 'AI knowledge section updated.';
            } else {
                $stmt = $db->prepare('INSERT INTO gd_ai_knowledge_sections (biz_id, title, content, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                $stmt->bind_param('isssi', $bizId, $title, $content, $status, $sortOrder);
                $stmt->execute();
                $message = 'AI knowledge section added.';
            }
        }

        if ($action === 'delete_section') {
            $sectionId = max(0, (int) ($_POST['section_id'] ?? 0));
            $stmt = $db->prepare('DELETE FROM gd_ai_knowledge_sections WHERE id = ? AND biz_id = ?');
            $stmt->bind_param('ii', $sectionId, $bizId);
            $stmt->execute();
            $message = 'AI knowledge section deleted.';
        }
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        $messageType = 'danger';
    }
}

$sections = [];
$editingSection = null;

if ($featureEnabled) {
    try {
        $stmt = $db->prepare('SELECT * FROM gd_ai_knowledge_sections WHERE biz_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->bind_param('i', $bizId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
            if ((int) ($_GET['edit'] ?? 0) === (int) $row['id']) {
                $editingSection = $row;
            }
        }
    } catch (Throwable $exception) {
        $message = 'Unable to load AI sections: ' . $exception->getMessage();
        $messageType = 'danger';
    }
}
?>

<div class="container-fluid wg-shell">
    <div class="row bg-light">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="col-lg-10 col-md-9 wg-main">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo h($messageType); ?> mt-3"><?php echo h($message); ?></div>
            <?php endif; ?>

            <div class="wg-page-title mt-3">
                <h1>AI Knowledge</h1>
                <p>Train auto replies with accurate business, product, pricing, launch, and FAQ details.</p>
            </div>

            <?php if (!$featureEnabled): ?>
                <div class="wg-card p-4">
                    <h5 class="mb-2">AI auto replies are not enabled</h5>
                    <p class="text-muted mb-0">Ask your master admin to enable AI Auto Replies for this business workspace.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-lg-5">
                        <form method="post" class="form-panel">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="save_section">
                            <input type="hidden" name="section_id" value="<?php echo h((string) ($editingSection['id'] ?? 0)); ?>">

                            <h5><?php echo $editingSection ? 'Edit Training Section' : 'Add Training Section'; ?></h5>
                            <p class="text-muted small">Write facts exactly how the AI should understand them. Active sections are used for webhook replies.</p>

                            <div class="mb-3">
                                <label class="form-label" for="title">Section title</label>
                                <input class="form-control" id="title" name="title" value="<?php echo h($editingSection['title'] ?? ''); ?>" placeholder="Example: New product launch date" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="content">Business knowledge</label>
                                <textarea class="form-control" id="content" name="content" rows="9" placeholder="Example: We are launching Elldy Basket on 15 July 2026. It helps stores manage product bundles..." required><?php echo h($editingSection['content'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label" for="sort_order">Order</label>
                                    <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" value="<?php echo h((string) ($editingSection['sort_order'] ?? 0)); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="status">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo (($editingSection['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo (($editingSection['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <button class="btn btn-success mt-3" type="submit"><i class="bi bi-database-add me-1"></i> Save Section</button>
                            <?php if ($editingSection): ?>
                                <a class="btn btn-light mt-3" href="<?php echo h(app_url('business/ai-knowledge')); ?>">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="col-lg-7">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Training Section</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$sections): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No sections added yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($sections as $section): ?>
                                        <tr>
                                            <td><?php echo h((string) $section['sort_order']); ?></td>
                                            <td>
                                                <strong><?php echo h($section['title']); ?></strong>
                                                <div class="text-muted small"><?php echo h(substr(trim((string) $section['content']), 0, 150)); ?><?php echo strlen((string) $section['content']) > 150 ? '...' : ''; ?></div>
                                            </td>
                                            <td><span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo h($section['status']); ?></span></td>
                                            <td>
                                                <a class="btn btn-sm btn-light" href="<?php echo h(app_url('business/ai-knowledge?edit=' . (int) $section['id'])); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this AI section?');">
                                                    <?php echo Security::csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete_section">
                                                    <input type="hidden" name="section_id" value="<?php echo h((string) $section['id']); ?>">
                                                    <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
