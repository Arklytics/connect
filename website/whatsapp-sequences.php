<?php
include '../session.php';
include '../db_conn.php';

function gdTableExists(mysqli $db, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $escapedTable = $db->real_escape_string($table);
    $result = $db->query("SHOW TABLES LIKE '{$escapedTable}'");

    return $result ? (bool) $result->num_rows : false;
}

$biz_id = Auth::requireLogin();
$sequencePlansTableExists = gdTableExists($db, 'gd_whatsapp_sequence_plans');
$followupColumns = Crm::tableColumns($db, 'gd_contact_followups');

include 'header.php';

$message = '';
$messageType = 'info';
$form = [
    'plan_name' => 'WhatsApp nurture sequence',
    'audience' => 'Warm leads',
    'objective' => 'Move the contact toward a reply',
    'sequence_type' => 'no_response',
    'notes' => '',
    'steps' => [
        1 => ['title' => 'Quick Reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for the quick reply. I am sending the next step now.'],
        2 => ['title' => 'Delayed Reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for getting back to me. Here is the next step based on the delay.'],
        3 => ['title' => 'Follow-Up', 'delay_days' => 2, 'message' => 'Hi {{name}}, sharing one more helpful follow-up before I pause here.'],
    ],
];

$presets = [
    'no_response' => [
        'label' => 'No Response',
        'audience' => 'Cold or unresponsive leads',
        'objective' => 'Follow up after no reply and gently close the loop',
        'steps' => [
            1 => ['title' => 'Quick Reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for the quick reply. I am sending the next step now.'],
            2 => ['title' => 'Delayed Reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for getting back to me. Here is the next step based on the delay.'],
            3 => ['title' => 'Follow-Up', 'delay_days' => 2, 'message' => 'Hi {{name}}, sharing one more helpful follow-up before I pause here.'],
        ],
    ],
    'quick_reply' => [
        'label' => 'Quick Reply',
        'audience' => 'Hot leads who replied fast',
        'objective' => 'Move the conversation to a demo, quote, or next step',
        'steps' => [
            1 => ['title' => 'Quick Reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for the quick reply. I am sending the next step now.'],
            2 => ['title' => 'Delayed Reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for getting back to me. Here is the next step based on the delay.'],
            3 => ['title' => 'Follow-Up', 'delay_days' => 2, 'message' => 'Hi {{name}}, sharing one more helpful follow-up before I pause here.'],
        ],
    ],
    'custom' => [
        'label' => 'Custom',
        'audience' => 'Any audience',
        'objective' => 'Build your own sequence structure',
        'steps' => [
            1 => ['title' => 'Quick Reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for the quick reply. I am sending the next step now.'],
            2 => ['title' => 'Delayed Reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for getting back to me. Here is the next step based on the delay.'],
            3 => ['title' => 'Follow-Up', 'delay_days' => 2, 'message' => 'Hi {{name}}, sharing one more helpful follow-up before I pause here.'],
        ],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $form['plan_name'] = trim((string) ($_POST['plan_name'] ?? $form['plan_name']));
    $form['audience'] = trim((string) ($_POST['audience'] ?? $form['audience']));
    $form['objective'] = trim((string) ($_POST['objective'] ?? $form['objective']));
    $form['sequence_type'] = trim((string) ($_POST['sequence_type'] ?? $form['sequence_type']));
    $form['notes'] = trim((string) ($_POST['notes'] ?? ''));

    for ($step = 1; $step <= 3; $step++) {
        $form['steps'][$step]['title'] = trim((string) ($_POST['step_title'][$step] ?? $form['steps'][$step]['title']));
        $form['steps'][$step]['delay_days'] = max(0, (int) ($_POST['step_delay'][$step] ?? $form['steps'][$step]['delay_days']));
        $form['steps'][$step]['message'] = trim((string) ($_POST['step_message'][$step] ?? $form['steps'][$step]['message']));
    }

    $action = (string) ($_POST['action'] ?? 'save');
    $steps = [];
    foreach ($form['steps'] as $stepNo => $stepData) {
        if ($stepData['message'] === '') {
            continue;
        }

        $steps[] = [
            'step_no' => (int) $stepNo,
            'title' => $stepData['title'] !== '' ? $stepData['title'] : 'Step ' . $stepNo,
            'delay_days' => (int) $stepData['delay_days'],
            'message' => $stepData['message'],
        ];
    }

    if ($form['plan_name'] === '') {
        $message = 'Sequence plan name is required.';
        $messageType = 'warning';
    } elseif (empty($steps)) {
        $message = 'Add at least one sequence step before saving.';
        $messageType = 'warning';
    } else {
        $sequenceType = $form['sequence_type'] !== '' ? $form['sequence_type'] : 'custom';
        $structure = [
            'plan_name' => $form['plan_name'],
            'audience' => $form['audience'],
            'objective' => $form['objective'],
            'sequence_type' => $sequenceType,
            'steps' => $steps,
            'notes' => $form['notes'],
            'response_rule' => [
                'quick_reply_max_minutes' => 120,
                'slow_reply_min_minutes' => 121,
            ],
        ];
        $savedPlan = false;

        if ($sequencePlansTableExists) {
            $structureJson = json_encode($structure, JSON_UNESCAPED_UNICODE);
            $now = date('Y-m-d H:i:s');
            $status = 'active';
            $stepCount = count($steps);
            $defaultGapDays = (int) ($steps[0]['delay_days'] ?? 0);
            $stmt = $db->prepare('INSERT INTO gd_whatsapp_sequence_plans (biz_id, plan_name, audience, objective, sequence_type, step_count, default_gap_days, structure_json, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param(
                'issssiissss',
                $biz_id,
                $form['plan_name'],
                $form['audience'],
                $form['objective'],
                $sequenceType,
                $stepCount,
                $defaultGapDays,
                $structureJson,
                $status,
                $now,
                $now
            );
            $savedPlan = (bool) $stmt->execute();
        }

        if ($savedPlan) {
            $message = 'Sequence plan saved successfully.';
            $messageType = 'success';
        } else {
            $message = 'Sequence structure prepared, but nothing was saved because the sequence plans table is not installed yet.';
            $messageType = 'warning';
        }
    }
}

$contacts = [];
$stmt = $db->prepare('SELECT id, full_name, phone_number FROM gd_user_contacts WHERE biz_id = ? ORDER BY full_name');
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$contactResult = $stmt->get_result();
while ($row = mysqli_fetch_assoc($contactResult)) {
    $contacts[] = $row;
}

$sequencePlans = [];
if ($sequencePlansTableExists) {
    $stmt = $db->prepare('SELECT * FROM gd_whatsapp_sequence_plans WHERE biz_id = ? ORDER BY id DESC LIMIT 8');
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $planResult = $stmt->get_result();
    while ($row = mysqli_fetch_assoc($planResult)) {
        $sequencePlans[] = $row;
    }
}

$recentFollowUps = [];
if (in_array('scheduled_at', $followupColumns, true)) {
    $stmt = $db->prepare('SELECT f.*, c.full_name, c.phone_number FROM gd_contact_followups f INNER JOIN gd_user_contacts c ON c.id = f.contact_id WHERE f.biz_id = ? ORDER BY f.id DESC LIMIT 10');
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $followResult = $stmt->get_result();
    while ($row = mysqli_fetch_assoc($followResult)) {
        $recentFollowUps[] = $row;
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-7 col-md-9 wg-main">
            <div class="wg-page-title">
                <h1>WhatsApp Sequence Planner</h1>
                <p>Build the structure first, then queue it to a contact when you are ready. Sending templates stays on the Send Messages page.</p>
            </div>

            <div class="position-fixed top-0 end-0 p-3" style="z-index: 5;">
                <?php if (!empty($message)): ?>
                    <div class="toast align-items-center text-bg-<?php echo h($messageType); ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <?php echo h($message); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="wg-card p-4 mb-4">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="mb-1">Sequence Blueprint</h5>
                        <p class="text-muted mb-0">Choose a pattern, shape the step delays, and write the actual message flow.</p>
                    </div>
                    <span class="badge bg-light text-dark border">Planner only</span>
                </div>

                <form action="" method="POST" class="row g-3">
                    <?php echo Security::csrfField(); ?>
                    <div class="col-md-4">
                        <label class="form-label">Plan Name</label>
                        <input type="text" class="form-control p-2 shadow" name="plan_name" value="<?php echo h($form['plan_name']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Audience</label>
                        <input type="text" class="form-control p-2 shadow" name="audience" value="<?php echo h($form['audience']); ?>" placeholder="Warm leads, demo requests, etc.">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Goal</label>
                        <input type="text" class="form-control p-2 shadow" name="objective" value="<?php echo h($form['objective']); ?>" placeholder="What the sequence should achieve">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Structure Type</label>
                        <select class="form-control p-2 shadow" name="sequence_type" id="sequencePreset">
                            <?php foreach ($presets as $key => $preset): ?>
                                <option value="<?php echo h($key); ?>" <?php echo $form['sequence_type'] === $key ? 'selected' : ''; ?>>
                                    <?php echo h($preset['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php for ($step = 1; $step <= 3; $step++): ?>
                        <div class="col-12">
                            <div class="border rounded-4 p-3 bg-white">
                                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                                    <strong>Step <?php echo $step; ?></strong>
                                    <span class="text-muted small"><?php echo $step === 1 ? 'Quick reply path' : ($step === 2 ? 'Delayed reply path' : 'Follow-up path'); ?></span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Step Label</label>
                                        <input type="text" class="form-control" name="step_title[<?php echo $step; ?>]" value="<?php echo h($form['steps'][$step]['title']); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Delay Days</label>
                                        <input type="number" min="0" max="30" class="form-control" name="step_delay[<?php echo $step; ?>]" value="<?php echo h((string) $form['steps'][$step]['delay_days']); ?>">
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" rows="3" name="step_message[<?php echo $step; ?>]"><?php echo h($form['steps'][$step]['message']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control p-2 shadow" name="notes" rows="3" placeholder="Strategy notes, handoff notes, tone guidance"><?php echo h($form['notes']); ?></textarea>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-success" type="submit" name="action" value="save"><i class="bi bi-save me-1"></i> Save Plan</button>
                    </div>
                </form>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="wg-card p-4 h-100">
                        <h5 class="mb-3">Plan Preview</h5>
                        <div class="border rounded-4 p-3 bg-light">
                            <div class="fw-bold mb-2"><?php echo h($form['plan_name']); ?></div>
                            <div class="text-muted small mb-1">Audience: <?php echo h($form['audience']); ?></div>
                            <div class="text-muted small mb-3">Goal: <?php echo h($form['objective']); ?></div>
                            <?php foreach ($form['steps'] as $stepNo => $step): ?>
                                <?php if ($step['message'] === '') { continue; } ?>
                                <div class="border rounded-3 bg-white p-3 mb-2">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong>Step <?php echo h((string) $stepNo); ?>: <?php echo h($step['title']); ?></strong>
                                        <span class="badge bg-secondary"><?php echo h((string) $step['delay_days']); ?> day(s)</span>
                                    </div>
                                    <div class="small text-muted mt-2"><?php echo nl2br(h($step['message'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="wg-card p-4 h-100">
                        <h5 class="mb-3">Sequence Library</h5>
                        <div class="row g-3">
                            <?php foreach ($presets as $key => $preset): ?>
                                <div class="col-md-6">
                                    <div class="border rounded-4 p-3 h-100 bg-white">
                                        <div class="fw-bold mb-1"><?php echo h($preset['label']); ?></div>
                                        <div class="small text-muted mb-2"><?php echo h($preset['objective']); ?></div>
                                        <div class="small">Best for: <?php echo h($preset['audience']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="alert alert-light border mt-3 mb-0">
                            The planner saves a reusable blueprint. Incoming replies decide which step starts first, and the cron sender delivers the rest automatically.
                        </div>
                    </div>
                </div>
            </div>

            <div class="wg-card p-4 mt-4">
                <h5 class="mb-3">Saved Plans</h5>
                <?php if ($sequencePlansTableExists && !empty($sequencePlans)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <tr>
                                <th>#</th>
                                <th>Plan</th>
                                <th>Audience</th>
                                <th>Objective</th>
                                <th>Steps</th>
                                <th>Status</th>
                            </tr>
                            <?php foreach ($sequencePlans as $i => $plan): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo h($plan['plan_name']); ?></td>
                                    <td><?php echo h($plan['audience'] ?? '-'); ?></td>
                                    <td><?php echo h($plan['objective'] ?? '-'); ?></td>
                                    <td><?php echo h($plan['step_count']); ?></td>
                                    <td><?php echo h($plan['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        Sequence plans will appear here after the new table migration is installed.
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($followupColumns && !empty($recentFollowUps)): ?>
                <div class="wg-card p-4 mt-4">
                    <h5 class="mb-3">Recent Follow-Ups</h5>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <tr>
                                <th>#</th>
                                <th>Contact</th>
                                <th>Sequence</th>
                                <th>Step</th>
                                <th>Scheduled</th>
                                <th>Status</th>
                            </tr>
                            <?php foreach ($recentFollowUps as $i => $followUp): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo h($followUp['full_name']); ?><div class="text-muted small"><?php echo h($followUp['phone_number']); ?></div></td>
                                    <td><?php echo h($followUp['sequence_name']); ?></td>
                                    <td><?php echo h($followUp['step_no']); ?></td>
                                    <td><?php echo h($followUp['scheduled_at']); ?></td>
                                    <td><?php echo h($followUp['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-3 d-none d-lg-block">
            <div class="wg-card p-4 mt-4">
                <h5 class="mb-3">How It Works</h5>
                <ol class="small text-muted ps-3 mb-0">
                    <li>Pick a preset or custom sequence structure.</li>
                    <li>Set the delays and step copy for reply-based automation.</li>
                    <li>Incoming replies trigger the matching step sequence.</li>
                    <li>Use Send Messages for one-off bulk templates.</li>
                </ol>
            </div>

            <div class="wg-card p-4 mt-4">
                <h5 class="mb-3">Reply Verification</h5>
                <div class="alert alert-info">
                    The WhatsApp webhook verifies replies by phone number, stamps the contact with a reply path, and records when the reply arrived.
                </div>
                <ul class="small text-muted ps-3 mb-0">
                    <li>Quick reply: within 120 minutes.</li>
                    <li>Delayed reply: after 120 minutes.</li>
                    <li>Verified path: saved on the contact record for reporting.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
  const sequencePreset = document.getElementById('sequencePreset');
  const presetData = <?php echo json_encode(array_map(function ($preset) {
      return $preset['steps'];
  }, $presets), JSON_UNESCAPED_UNICODE); ?>;
  const audienceMap = <?php echo json_encode(array_map(function ($preset) {
      return [
          'audience' => $preset['audience'],
          'objective' => $preset['objective'],
      ];
  }, $presets), JSON_UNESCAPED_UNICODE); ?>;
  const audienceField = document.querySelector('input[name="audience"]');
  const objectiveField = document.querySelector('input[name="objective"]');
  const stepTitles = document.querySelectorAll('input[name^="step_title"]');
  const stepDelays = document.querySelectorAll('input[name^="step_delay"]');
  const stepMessages = document.querySelectorAll('textarea[name^="step_message"]');

  function applyPreset(key) {
    const steps = presetData[key];
    const meta = audienceMap[key];
    if (audienceField && meta && meta.audience) {
      audienceField.value = meta.audience;
    }
    if (objectiveField && meta && meta.objective) {
      objectiveField.value = meta.objective;
    }
    if (!steps) {
      return;
    }
    steps.forEach(function (step, index) {
      if (stepTitles[index]) stepTitles[index].value = step.title || '';
      if (stepDelays[index]) stepDelays[index].value = step.delay_days ?? 0;
      if (stepMessages[index]) stepMessages[index].value = step.message || '';
    });
  }

  if (sequencePreset) {
    sequencePreset.addEventListener('change', function () {
      applyPreset(this.value);
    });
  }
</script>

<?php include 'footer.php'; ?>
