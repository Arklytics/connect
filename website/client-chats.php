<?php
include __DIR__ . '/../session.php';
include __DIR__ . '/../db_conn.php';

$biz_id = Auth::requireLogin();

function gdChatPhoneVariants(string $phone): array
{
    $normalized = ApiSupport::normalizePhone($phone);
    return array_values(array_unique(array_filter([
        trim($phone),
        $normalized,
        ltrim($normalized, '+'),
        ltrim(trim($phone), '+'),
    ])));
}

function gdChatBind(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($params === []) {
        return;
    }

    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = &$params[$index];
    }
    $stmt->bind_param(...$bind);
}

function gdChatHasTable(mysqli $db, string $table): bool
{
    try {
        $stmt = $db->prepare('SHOW TABLES LIKE ?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    } catch (Throwable $exception) {
        return false;
    }
}

function gdChatTimeline(mysqli $db, int $bizId, array $contact): array
{
    $phoneVariants = gdChatPhoneVariants((string) ($contact['phone_number'] ?? ''));
    $phoneWithoutPlus = array_values(array_unique(array_map(static fn ($phone) => ltrim($phone, '+'), $phoneVariants)));
    $timeline = [];

    if (gdChatHasTable($db, 'gd_webhook_logs')) {
        $where = ['contact_id = ?'];
        $types = 'i';
        $params = [(int) $contact['id']];

        if ($phoneVariants !== []) {
            $where[] = 'from_phone IN (' . implode(',', array_fill(0, count($phoneVariants), '?')) . ')';
            $types .= str_repeat('s', count($phoneVariants));
            array_push($params, ...$phoneVariants);
        }

        if ($phoneWithoutPlus !== []) {
            $where[] = 'REPLACE(from_phone, "+", "") IN (' . implode(',', array_fill(0, count($phoneWithoutPlus), '?')) . ')';
            $types .= str_repeat('s', count($phoneWithoutPlus));
            array_push($params, ...$phoneWithoutPlus);
        }

        $sql = '
            SELECT direction, message_text, delivery_status, notes, webhook_at, created_at
            FROM gd_webhook_logs
            WHERE biz_id = ? AND event_type = "message" AND (' . implode(' OR ', $where) . ')
        ';
        array_unshift($params, $bizId);
        $types = 'i' . $types;

        $stmt = $db->prepare($sql);
        gdChatBind($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $body = trim((string) ($row['message_text'] ?? ''));
            if ($body === '') {
                continue;
            }
            $direction = strtolower((string) ($row['direction'] ?? 'inbound')) === 'inbound' ? 'inbound' : 'outbound';
            $timeline[] = [
                'direction' => $direction,
                'source' => 'webhook',
                'title' => $direction === 'inbound' ? 'Client' : 'Business',
                'body' => $body,
                'status' => (string) ($row['delivery_status'] ?? ''),
                'time' => (string) ($row['webhook_at'] ?? $row['created_at'] ?? ''),
                'notes' => (string) ($row['notes'] ?? ''),
            ];
        }
    }

    $sentWhere = [];
    $sentTypes = '';
    $sentParams = [];
    if ($phoneVariants !== []) {
        $sentWhere[] = 'phone_number IN (' . implode(',', array_fill(0, count($phoneVariants), '?')) . ')';
        $sentTypes .= str_repeat('s', count($phoneVariants));
        array_push($sentParams, ...$phoneVariants);
    }
    if ($phoneWithoutPlus !== []) {
        $sentWhere[] = 'REPLACE(phone_number, "+", "") IN (' . implode(',', array_fill(0, count($phoneWithoutPlus), '?')) . ')';
        $sentTypes .= str_repeat('s', count($phoneWithoutPlus));
        array_push($sentParams, ...$phoneWithoutPlus);
    }

    if ($sentWhere !== []) {
        $sentColumns = Crm::tableColumns($db, 'gd_sent_messages');
        $deliverySelect = in_array('delivery_status', $sentColumns, true) ? 'delivery_status' : 'status AS delivery_status';
        $sql = '
            SELECT message_title, message_body, status, ' . $deliverySelect . ', error_message, sent_at, created_at
            FROM gd_sent_messages
            WHERE biz_id = ? AND (' . implode(' OR ', $sentWhere) . ')
        ';
        array_unshift($sentParams, $bizId);
        $stmt = $db->prepare($sql);
        gdChatBind($stmt, 'i' . $sentTypes, $sentParams);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $body = trim((string) ($row['message_body'] ?? ''));
            if ($body === '') {
                continue;
            }
            $title = (string) ($row['message_title'] ?? 'Business Reply');
            $timeline[] = [
                'direction' => 'outbound',
                'source' => strcasecmp($title, 'AI Auto Reply') === 0 ? 'ai' : 'sent',
                'title' => $title,
                'body' => $body,
                'status' => (string) ($row['delivery_status'] ?? $row['status'] ?? ''),
                'time' => (string) ($row['sent_at'] ?? $row['created_at'] ?? ''),
                'notes' => (string) ($row['error_message'] ?? ''),
            ];
        }
    }

    usort($timeline, static fn ($left, $right) => (strtotime((string) ($left['time'] ?? '')) ?: 0) <=> (strtotime((string) ($right['time'] ?? '')) ?: 0));
    return $timeline;
}

$selectedContactId = Security::intFrom($_GET['contact_id'] ?? null);
$pageError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Security::verifyCsrf();
        $selectedContactId = Security::intFrom($_POST['contact_id'] ?? null);
        $replyText = trim((string) ($_POST['message'] ?? ''));

        if ($replyText !== '' && $selectedContactId > 0) {
            $stmt = $db->prepare('SELECT * FROM gd_user_contacts WHERE biz_id = ? AND id = ? LIMIT 1');
            $stmt->bind_param('ii', $biz_id, $selectedContactId);
            $stmt->execute();
            $contact = $stmt->get_result()->fetch_assoc();

            $stmt = $db->prepare('SELECT phone_number_id, auth_token FROM gd_orders WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $biz_id);
            $stmt->execute();
            $business = $stmt->get_result()->fetch_assoc();

            $phone = $contact ? ApiSupport::normalizePhone((string) ($contact['phone_number'] ?? '')) : '';
            $token = trim((string) ($business['auth_token'] ?? ''));
            if ($token === '') {
                $token = AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', ''));
            }

            if ($contact && $phone !== '' && !empty($business['phone_number_id']) && $token !== '') {
                $payload = ApiSupport::whatsappTextPayload($phone, $replyText);
                $response = ApiSupport::whatsappSendRequest((string) $business['phone_number_id'], $token, $payload);
                $status = $response['ok'] ? 'sent' : 'failed';
                $error = $response['ok'] ? null : (string) ($response['failure_reason'] ?? $response['error'] ?? 'Unknown error');

                ApiSupport::storeSentMessage(
                    $db,
                    (int) $biz_id,
                    $phone,
                    null,
                    'Manual Chat Reply',
                    $replyText,
                    $status,
                    $status,
                    $error,
                    $response['message_id'] ?? null,
                    date('Y-m-d H:i:s'),
                    $response['request_json'] ?? ApiSupport::encodeJson($payload),
                    $response['response_json'] ?? null,
                    $response['http_code'] ?? null,
                    $error
                );

                if ($response['ok']) {
                    ApiSupport::consumeMessageCredit($db, (int) $biz_id);
                    $_SESSION['flash_success'] = 'Reply sent to client.';
                } else {
                    $_SESSION['flash_error'] = 'Reply failed: ' . $error;
                }
            } else {
                $_SESSION['flash_error'] = 'WhatsApp credentials or client phone number is missing.';
            }
        }
    } catch (Throwable $exception) {
        error_log('Client chat reply failed: ' . $exception->getMessage());
        $_SESSION['flash_error'] = 'Unable to send reply right now. Please check WhatsApp credentials and database tables.';
    }

    header('Location: ' . app_url('business/client-chats?contact_id=' . $selectedContactId));
    exit;
}

$contacts = [];
$selectedContact = null;
$timeline = [];

try {
    $contactColumns = Crm::tableColumns($db, 'gd_user_contacts');
    $orderColumn = in_array('last_inbound_at', $contactColumns, true) ? 'last_inbound_at' : 'updated_at';
    $stmt = $db->prepare("SELECT * FROM gd_user_contacts WHERE biz_id = ? ORDER BY `$orderColumn` DESC, id DESC LIMIT 150");
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $contactsResult = $stmt->get_result();
    while ($row = $contactsResult->fetch_assoc()) {
        $contacts[] = $row;
    }

    if ($selectedContactId > 0) {
        foreach ($contacts as $contactRow) {
            if ((int) $contactRow['id'] === $selectedContactId) {
                $selectedContact = $contactRow;
                break;
            }
        }
    }

    if ($selectedContact === null && $contacts !== []) {
        $selectedContact = $contacts[0];
        $selectedContactId = (int) $selectedContact['id'];
    }

    $timeline = $selectedContact ? gdChatTimeline($db, (int) $biz_id, $selectedContact) : [];
} catch (Throwable $exception) {
    error_log('Client chats page failed: ' . $exception->getMessage());
    $pageError = 'Unable to load client chats right now. Please confirm the contact, sent message, and webhook log tables are available.';
}

include __DIR__ . '/header.php';
?>

<style>
  .wg-chat-list { max-height: 72vh; overflow-y: auto; }
  .wg-chat-thread { height: 58vh; overflow-y: auto; background: #eef6f2; }
  .wg-chat-item.active { border-left: 4px solid #0f766e; background: #ecfdf5; }
  .wg-chat-bubble { max-width: min(78%, 620px); border-radius: 8px; white-space: pre-wrap; overflow-wrap: anywhere; }
  .wg-chat-bubble.inbound { background: #fff; border: 1px solid #d9e8e1; }
  .wg-chat-bubble.outbound { background: #dcf8c6; border: 1px solid #bde8a6; }
  .wg-chat-meta { font-size: 12px; color: #64748b; }
  .wg-chat-compose textarea { resize: none; min-height: 52px; max-height: 120px; }
</style>

<div class="container-fluid">
  <div class="row bg-light">
    <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
      <?php include __DIR__ . '/sidebar.php'; ?>
    </div>

    <div class="col-lg-10 col-md-9 wg-main">
      <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-3">
        <div>
          <h4 class="mb-1"><i class="bi bi-chat-dots-fill"></i> Client Chats</h4>
          <div class="text-muted">Watch client messages, AI replies, broadcasts, and manual replies in one window.</div>
        </div>
        <a class="btn btn-outline-success" href="<?php echo h(app_url('business/send-messages')); ?>">
          <i class="bi bi-send me-1"></i> Send Broadcast
        </a>
      </div>

      <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success mt-3"><?php echo h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger mt-3"><?php echo h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
      <?php endif; ?>
      <?php if ($pageError !== null): ?>
        <div class="alert alert-warning mt-3"><?php echo h($pageError); ?></div>
      <?php endif; ?>

      <div class="row g-3 mt-2">
        <div class="col-lg-4 col-xl-3">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><strong>Clients</strong></div>
            <div class="list-group list-group-flush wg-chat-list">
              <?php foreach ($contacts as $contact): ?>
                <?php
                  $isActive = $selectedContact && (int) $selectedContact['id'] === (int) $contact['id'];
                  $replyPath = trim((string) ($contact['reply_path'] ?? ''));
                  $lastReply = trim((string) ($contact['last_reply_text'] ?? ''));
                ?>
                <a href="<?php echo h(app_url('business/client-chats?contact_id=' . (int) $contact['id'])); ?>" class="list-group-item list-group-item-action wg-chat-item <?php echo $isActive ? 'active' : ''; ?>">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                      <div class="fw-semibold text-truncate"><?php echo h($contact['full_name'] ?? 'Client'); ?></div>
                      <div class="small text-muted"><?php echo h($contact['phone_number'] ?? ''); ?></div>
                    </div>
                    <?php if ($replyPath !== ''): ?>
                      <span class="badge bg-success text-uppercase"><?php echo h(str_replace('_', ' ', $replyPath)); ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted text-truncate mt-2"><?php echo h($lastReply !== '' ? $lastReply : 'No inbound reply yet'); ?></div>
                </a>
              <?php endforeach; ?>
              <?php if ($contacts === []): ?>
                <div class="p-4 text-center text-muted">No contacts found.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-8 col-xl-9">
          <div class="card shadow-sm border-0 h-100">
            <?php if ($selectedContact): ?>
              <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                  <strong><?php echo h($selectedContact['full_name'] ?? 'Client'); ?></strong>
                  <div class="small text-muted"><?php echo h($selectedContact['phone_number'] ?? ''); ?></div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <?php if (!empty($selectedContact['lead_status'])): ?>
                    <span class="badge bg-secondary text-uppercase"><?php echo h($selectedContact['lead_status']); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($selectedContact['lead_temperature'])): ?>
                    <span class="badge bg-warning text-dark text-uppercase"><?php echo h($selectedContact['lead_temperature']); ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="card-body p-0">
                <div class="wg-chat-thread p-3" id="chatThread">
                  <?php foreach ($timeline as $message): ?>
                    <?php
                      $isInbound = ($message['direction'] ?? 'inbound') === 'inbound';
                      $source = (string) ($message['source'] ?? '');
                      $label = $isInbound ? 'Client' : ($source === 'ai' ? 'AI Auto Reply' : 'Business');
                    ?>
                    <div class="d-flex mb-3 <?php echo $isInbound ? 'justify-content-start' : 'justify-content-end'; ?>">
                      <div class="wg-chat-bubble <?php echo $isInbound ? 'inbound' : 'outbound'; ?> p-3 shadow-sm">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1 wg-chat-meta">
                          <span class="fw-semibold"><?php echo h($label); ?></span>
                          <?php if ($source === 'ai'): ?><span class="badge bg-primary">AI</span><?php endif; ?>
                        </div>
                        <div><?php echo h($message['body'] ?? ''); ?></div>
                        <div class="wg-chat-meta text-end mt-2">
                          <?php echo h($message['time'] ?? ''); ?>
                          <?php if (!$isInbound && !empty($message['status'])): ?>
                            <span class="ms-2 text-uppercase"><?php echo h($message['status']); ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <?php if ($timeline === []): ?>
                    <div class="h-100 d-flex align-items-center justify-content-center text-muted text-center">
                      <div>
                        <div class="fs-2 mb-2"><i class="bi bi-chat-square-text"></i></div>
                        <div>No chat messages yet for this client.</div>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="card-footer bg-white">
                <form action="<?php echo h(app_url('business/client-chats')); ?>" method="post" class="wg-chat-compose">
                  <?php echo Security::csrfField(); ?>
                  <input type="hidden" name="contact_id" value="<?php echo h($selectedContactId); ?>">
                  <div class="input-group">
                    <textarea name="message" class="form-control" rows="2" maxlength="1200" required placeholder="Type a WhatsApp reply"></textarea>
                    <button class="btn btn-success px-4" type="submit"><i class="bi bi-send-fill"></i></button>
                  </div>
                </form>
              </div>
            <?php else: ?>
              <div class="card-body d-flex align-items-center justify-content-center text-muted text-center" style="min-height: 72vh;">
                <div>
                  <div class="fs-2 mb-2"><i class="bi bi-person-lines-fill"></i></div>
                  <div>Add contacts first, then client conversations will appear here.</div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  var chatThread = document.getElementById('chatThread');
  if (chatThread) {
    chatThread.scrollTop = chatThread.scrollHeight;
  }
</script>
