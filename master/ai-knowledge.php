<?php
include 'db_conn.php';
include 'session.php';
include 'header.php';

$adminId = Auth::requireMaster();
$message = '';
$messageType = 'success';
$selectedBizId = max(0, (int) ($_GET['biz_id'] ?? $_POST['biz_id'] ?? 0));

function masterAiBusiness(mysqli $db, int $adminId, int $bizId): ?array
{
    if ($bizId <= 0) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM gd_orders WHERE id = ? AND admin_id = ? LIMIT 1');
    $stmt->bind_param('ii', $bizId, $adminId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc() ?: null;
}

try {
    AiAutoReply::ensureSchema($db);
} catch (Throwable $exception) {
    $message = 'Unable to prepare AI knowledge tables: ' . $exception->getMessage();
    $messageType = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $messageType !== 'danger') {
    Security::verifyCsrf();
    $business = masterAiBusiness($db, $adminId, $selectedBizId);

    if (!$business) {
        $message = 'Please choose a valid business.';
        $messageType = 'warning';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === 'save_settings') {
                $enabled = isset($_POST['ai_auto_reply_enabled']) ? 1 : 0;
                $fallback = trim((string) ($_POST['ai_fallback_reply'] ?? ''));

                $stmt = $db->prepare('UPDATE gd_orders SET ai_auto_reply_enabled = ?, ai_fallback_reply = ? WHERE id = ? AND admin_id = ?');
                $stmt->bind_param('isii', $enabled, $fallback, $selectedBizId, $adminId);
                $stmt->execute();
                $message = 'AI auto reply settings updated.';
            }

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
                    $stmt->bind_param('sssiii', $title, $content, $status, $sortOrder, $sectionId, $selectedBizId);
                    $stmt->execute();
                    $message = 'AI knowledge section updated.';
                } else {
                    $stmt = $db->prepare('INSERT INTO gd_ai_knowledge_sections (biz_id, title, content, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                    $stmt->bind_param('isssi', $selectedBizId, $title, $content, $status, $sortOrder);
                    $stmt->execute();
                    $message = 'AI knowledge section added.';
                }
            }

            if ($action === 'delete_section') {
                $sectionId = max(0, (int) ($_POST['section_id'] ?? 0));
                $stmt = $db->prepare('DELETE FROM gd_ai_knowledge_sections WHERE id = ? AND biz_id = ?');
                $stmt->bind_param('ii', $sectionId, $selectedBizId);
                $stmt->execute();
                $message = 'AI knowledge section deleted.';
            }
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $messageType = 'danger';
        }
    }
}

$businesses = [];
try {
    $stmt = $db->prepare('SELECT id, business_name, business_number, ai_auto_reply_enabled FROM gd_orders WHERE admin_id = ? ORDER BY business_name ASC');
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $businesses[] = $row;
    }
} catch (Throwable $exception) {
    $message = 'Unable to load businesses: ' . $exception->getMessage();
    $messageType = 'danger';
}

if ($selectedBizId <= 0 && $businesses) {
    $selectedBizId = (int) $businesses[0]['id'];
}

$selectedBusiness = masterAiBusiness($db, $adminId, $selectedBizId);
$sections = [];
$editingSection = null;

if ($selectedBusiness) {
    $sections = AiAutoReply::activeSections($db, $selectedBizId);

    $stmt = $db->prepare('SELECT * FROM gd_ai_knowledge_sections WHERE biz_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->bind_param('i', $selectedBizId);
    $stmt->execute();
    $allSections = $stmt->get_result();
    $sections = [];
    while ($row = $allSections->fetch_assoc()) {
        $sections[] = $row;
        if ((int) ($_GET['edit'] ?? 0) === (int) $row['id']) {
            $editingSection = $row;
        }
    }
}

$openAiConfigured = trim((string) Config::get('OPENAI_API_KEY', '')) !== '';
?>

<div class="container-fluid wg-shell">
    <div class="row bg-light">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo h($messageType); ?> mt-3"><?php echo h($message); ?></div>
            <?php endif; ?>

            <?php if (!$openAiConfigured): ?>
                <div class="alert alert-warning mt-3">
                    Add <strong>OPENAI_API_KEY</strong> to your <strong>.env</strong> file before webhook auto replies can send AI answers.
                </div>
            <?php endif; ?>

            <h4 class="mt-3"><i class="bi bi-robot"></i> AI Auto Replies</h4>

            <form method="get" class="form-panel">
                <label class="form-label" for="biz_id">Assign AI knowledge to business</label>
                <div class="row align-items-end">
                    <div class="col-md-8">
                        <select class="form-select" id="biz_id" name="biz_id" onchange="this.form.submit()">
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo h((string) $business['id']); ?>" <?php echo (int) $business['id'] === $selectedBizId ? 'selected' : ''; ?>>
                                    <?php echo h($business['business_name']); ?><?php echo !empty($business['business_number']) ? ' - ' . h($business['business_number']) : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-success w-100" type="submit"><i class="bi bi-arrow-repeat me-1"></i> Load Business</button>
                    </div>
                </div>
            </form>

            <?php if ($selectedBusiness): ?>
                <form method="post" class="form-panel">
                    <?php echo Security::csrfField(); ?>
                    <input type="hidden" name="biz_id" value="<?php echo h((string) $selectedBizId); ?>">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1"><?php echo h($selectedBusiness['business_name']); ?></h5>
                            <p class="text-muted mb-0">Webhook questions will be answered from active sections only.</p>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="ai_auto_reply_enabled" name="ai_auto_reply_enabled" <?php echo ((int) ($selectedBusiness['ai_auto_reply_enabled'] ?? 0) === 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="ai_auto_reply_enabled">Enable auto reply</label>
                        </div>
                    </div>

                    <label class="form-label" for="ai_fallback_reply">Fallback reply when sections do not answer</label>
                    <textarea class="form-control" id="ai_fallback_reply" name="ai_fallback_reply" rows="2" placeholder="Thanks for your message. Our team will share the right details shortly."><?php echo h($selectedBusiness['ai_fallback_reply'] ?? ''); ?></textarea>

                    <button class="btn btn-success mt-3" type="submit"><i class="bi bi-check2-circle me-1"></i> Save Settings</button>
                </form>

                <div class="row">
                    <div class="col-lg-5">
                        <form method="post" class="form-panel">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="biz_id" value="<?php echo h((string) $selectedBizId); ?>">
                            <input type="hidden" name="action" value="save_section">
                            <input type="hidden" name="section_id" value="<?php echo h((string) ($editingSection['id'] ?? 0)); ?>">

                            <h5><?php echo $editingSection ? 'Edit Section' : 'Add Section'; ?></h5>
                            <div class="mb-3">
                                <label class="form-label" for="title">Section title</label>
                                <input class="form-control" id="title" name="title" value="<?php echo h($editingSection['title'] ?? ''); ?>" placeholder="Example: Launching new product on 15 July" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="content">Training content</label>
                                <textarea class="form-control" id="content" name="content" rows="8" placeholder="Add exact facts, product details, dates, FAQs, pricing, or policies..." required><?php echo h($editingSection['content'] ?? ''); ?></textarea>
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
                                <a class="btn btn-light mt-3" href="<?php echo h(app_url('master/ai-knowledge?biz_id=' . $selectedBizId)); ?>">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="col-lg-7">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$sections): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No AI sections added yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($sections as $section): ?>
                                        <tr>
                                            <td><?php echo h((string) $section['sort_order']); ?></td>
                                            <td>
                                                <strong><?php echo h($section['title']); ?></strong>
                                                <div class="text-muted small"><?php echo h(substr(trim((string) $section['content']), 0, 140)); ?><?php echo strlen((string) $section['content']) > 140 ? '...' : ''; ?></div>
                                            </td>
                                            <td><span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo h($section['status']); ?></span></td>
                                            <td>
                                                <a class="btn btn-sm btn-light" href="<?php echo h(app_url('master/ai-knowledge?biz_id=' . $selectedBizId . '&edit=' . (int) $section['id'])); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this AI section?');">
                                                    <?php echo Security::csrfField(); ?>
                                                    <input type="hidden" name="biz_id" value="<?php echo h((string) $selectedBizId); ?>">
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
            <?php else: ?>
                <div class="alert alert-info">Create a business first, then assign AI knowledge sections.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
