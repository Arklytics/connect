<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$logoUrl = app_url('website/uploads/connect-logo.png');
$baseUrl = rtrim(app_public_url(''), '/');
$loginUrl = app_url('business/login');
$signupUrl = app_url('business/signup');
$homeUrl = app_url('');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Arklytics Connect public API documentation for importing contacts and sending WhatsApp messages from external applications.">
    <title>Arklytics Connect API Documentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
      body { background: #f7faf9; color: #142126; font-family: Inter, "Segoe UI", sans-serif; }
      .api-nav { position: sticky; top: 0; z-index: 20; background: rgba(255, 255, 255, 0.94); border-bottom: 1px solid #dce8e7; backdrop-filter: blur(12px); }
      .api-logo { height: 44px; max-width: 210px; object-fit: contain; }
      .api-hero { background: #0e2430; color: #fff; }
      .api-hero p { color: #cfe0df; }
      .api-card { background: #fff; border: 1px solid #dce8e7; border-radius: 8px; box-shadow: 0 14px 30px rgba(20, 33, 38, 0.06); }
      .api-sidebar { position: sticky; top: 84px; }
      .api-sidebar a { display: block; color: #526366; text-decoration: none; padding: 8px 0; font-weight: 700; }
      .api-sidebar a:hover { color: #12825c; }
      pre { background: #101820; color: #e8f3f1; border-radius: 8px; padding: 18px; overflow-x: auto; font-size: 0.9rem; }
      code { color: #0b5f45; }
      pre code { color: inherit; }
      .method { display: inline-flex; align-items: center; min-width: 58px; justify-content: center; border-radius: 6px; background: #12825c; color: #fff; font-weight: 800; font-size: 0.8rem; padding: 4px 8px; }
      .table code { white-space: nowrap; }
      .nav-pills .nav-link { color: #0b5f45; font-weight: 700; }
      .nav-pills .nav-link.active { background: #12825c; }
    </style>
  </head>
  <body>
    <nav class="api-nav">
      <div class="container py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <a href="<?php echo h($homeUrl); ?>" class="d-inline-flex align-items-center">
          <img src="<?php echo h($logoUrl); ?>" class="api-logo" alt="Arklytics Connect">
        </a>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-success" href="<?php echo h($loginUrl); ?>"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
          <a class="btn btn-success" href="<?php echo h($signupUrl); ?>"><i class="bi bi-whatsapp me-1"></i> Connect Business</a>
        </div>
      </div>
    </nav>

    <header class="api-hero">
      <div class="container py-5">
        <div class="row align-items-center g-4">
          <div class="col-lg-8">
            <span class="badge bg-success mb-3">Public API Docs</span>
            <h1 class="display-5 fw-bold mb-3">Connect Arklytics WhatsApp CRM to your application</h1>
            <p class="lead mb-0">Import contacts, sync leads, and send WhatsApp text or approved template messages from PHP, Python, JavaScript, Java, C#, Go, or any HTTP client.</p>
          </div>
          <div class="col-lg-4">
            <div class="api-card p-3 text-dark">
              <div class="small text-muted mb-1">Base URL</div>
              <code><?php echo h($baseUrl); ?></code>
              <div class="small text-muted mt-3 mb-1">Authentication</div>
              <code>Authorization: Bearer YOUR_BUSINESS_API_KEY</code>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="container py-5">
      <div class="row g-4">
        <aside class="col-lg-3">
          <div class="api-card api-sidebar p-3">
            <a href="#quick-start">Quick Start</a>
            <a href="#auth">Authentication</a>
            <a href="#templates">Create Template</a>
            <a href="#send">Send WhatsApp Message</a>
            <a href="#groups">Groups</a>
            <a href="#contacts">Import Contacts</a>
            <a href="#webhooks">Webhooks</a>
            <a href="#examples">Language Examples</a>
            <a href="#responses">Responses</a>
            <a href="#errors">Errors</a>
          </div>
        </aside>

        <div class="col-lg-9">
          <section id="quick-start" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Quick Start</h2>
            <ol class="mb-0">
              <li>Login to your business account.</li>
              <li>Open <strong>Settings -> WhatsApp Connection</strong>.</li>
              <li>Generate a <strong>Business API Key</strong> and copy it once.</li>
              <li>Use the key in the <code>Authorization</code> header.</li>
              <li>Call <code>/api/contacts/import</code> to sync leads, or <code>/api/whatsapp/send</code> to send messages.</li>
            </ol>
          </section>

          <section id="auth" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Authentication</h2>
            <p>Every API request must include the business API key. You can send it in either header:</p>
<pre><code>Authorization: Bearer YOUR_BUSINESS_API_KEY
X-API-KEY: YOUR_BUSINESS_API_KEY</code></pre>
            <p class="mb-0">If you manage multiple business workspaces, include <code>biz_id</code> in the JSON body. The API rejects the request if the key does not belong to that business.</p>
          </section>

          <section id="templates" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Create WhatsApp Template</h2>
            <p><span class="method">POST</span> <code>/api/templates/create</code></p>
            <p>Create a WhatsApp Cloud API message template and save the submitted template in the business template library after Meta accepts it for review.</p>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                  <tr><td><code>template_name</code></td><td>string</td><td>Yes</td><td>Template name. It is normalized to lowercase letters, numbers, and underscores.</td></tr>
                  <tr><td><code>category</code></td><td>string</td><td>No</td><td><code>MARKETING</code> or <code>UTILITY</code>. Default is <code>MARKETING</code>. Authentication templates are not supported by this endpoint yet.</td></tr>
                  <tr><td><code>language</code></td><td>string</td><td>No</td><td>WhatsApp template language, for example <code>en_US</code>. Default is <code>en_US</code>.</td></tr>
                  <tr><td><code>header_type</code></td><td>string</td><td>No</td><td><code>NONE</code>, <code>TEXT</code>, <code>IMAGE</code>, <code>VIDEO</code>, or <code>DOCUMENT</code>. Default is <code>NONE</code>.</td></tr>
                  <tr><td><code>header_text</code></td><td>string</td><td>For text header</td><td>Header content. Text headers can include only one variable.</td></tr>
                  <tr><td><code>header_sample</code></td><td>string</td><td>When header has variable</td><td>Example value for the header variable.</td></tr>
                  <tr><td><code>header_media_handle</code></td><td>string</td><td>For media header*</td><td>WhatsApp upload handle used for Meta template review.</td></tr>
                  <tr><td><code>header_media_url</code></td><td>string</td><td>No</td><td>Public URL saved locally for sending media-header templates later.</td></tr>
                  <tr><td><code>body_text</code></td><td>string</td><td>Yes</td><td>Template body. Variables can be sent as <code>{{1}}</code>, <code>[1]</code>, or <code>{1}</code>.</td></tr>
                  <tr><td><code>body_samples</code></td><td>object/array</td><td>When body has variables</td><td>Example values for every body variable, keyed by placeholder number.</td></tr>
                  <tr><td><code>footer_text</code></td><td>string</td><td>No</td><td>Optional footer text.</td></tr>
                  <tr><td><code>buttons</code></td><td>array</td><td>No</td><td>Supports <code>URL</code>, <code>PHONE_NUMBER</code>, and <code>QUICK_REPLY</code> buttons.</td></tr>
                </tbody>
              </table>
            </div>
            <p class="small text-muted">*Instead of <code>header_media_handle</code>, multipart clients may upload <code>header_media_file</code>. The API uploads it to S3, generates the WhatsApp media handle, and saves the public media URL.</p>
<pre><code>curl -X POST "<?php echo h($baseUrl); ?>/api/templates/create" \
  -H "Authorization: Bearer YOUR_BUSINESS_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "template_name": "order_update",
    "category": "UTILITY",
    "language": "en_US",
    "header_type": "TEXT",
    "header_text": "Order update",
    "body_text": "Hi {{1}}, your order {{2}} is {{3}}.",
    "body_samples": {
      "1": "Nisha",
      "2": "A10045",
      "3": "Shipped"
    },
    "footer_text": "Thank you",
    "buttons": [
      {
        "type": "URL",
        "text": "Track order",
        "value": "https://example.com/orders/{{1}}"
      }
    ]
  }'</code></pre>
            <p>Successful response:</p>
<pre><code>{
  "ok": true,
  "template": {
    "id": 88,
    "biz_id": 25,
    "template_id": "1234567890",
    "template_name": "order_update",
    "status": "PENDING",
    "category": "UTILITY"
  }
}</code></pre>
          </section>

          <section id="send" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Send WhatsApp Message</h2>
            <p><span class="method">POST</span> <code>/api/whatsapp/send</code></p>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                  <tr><td><code>kind</code></td><td>string</td><td>No</td><td><code>text</code>, <code>template</code>, <code>utility</code>, <code>marketing</code>, or <code>authentication</code>. Default is <code>text</code>.</td></tr>
                  <tr><td><code>to</code></td><td>string/array</td><td>Yes*</td><td>One phone number or many phone numbers. Also accepts <code>phone_numbers</code>, <code>recipients</code>, <code>contact_ids</code>, <code>subgroup_id</code>, or <code>subgroup_ids</code>.</td></tr>
                  <tr><td><code>message</code></td><td>string</td><td>For text</td><td>Text body for free-form WhatsApp text messages.</td></tr>
                  <tr><td><code>template_name</code></td><td>string</td><td>For templates</td><td>Approved WhatsApp template name saved in Arklytics Connect.</td></tr>
                  <tr><td><code>language</code></td><td>string</td><td>No</td><td>Template language, for example <code>en_US</code>. Defaults from the saved template.</td></tr>
                  <tr><td><code>parameters</code></td><td>array</td><td>No</td><td>Values for template body placeholders.</td></tr>
                  <tr><td><code>otp</code></td><td>string</td><td>For auth</td><td>OTP/code used by authentication templates.</td></tr>
                  <tr><td><code>components</code></td><td>array</td><td>No</td><td>Advanced WhatsApp template components.</td></tr>
                </tbody>
              </table>
            </div>
            <p class="small text-muted">*Provide at least one recipient source: phone numbers, contact IDs, group IDs, or subgroup IDs.</p>
<pre><code>{
  "kind": "utility",
  "template_name": "order_update",
  "language": "en_US",
  "subgroup_id": 18,
  "parameters": ["A10045", "Shipped"]
}</code></pre>
          </section>

          <section id="groups" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Groups & Subgroups</h2>
            <p><span class="method">GET</span> <code>/api/groups</code> lists all main groups and subgroups.</p>
            <p><span class="method">POST</span> <code>/api/groups</code> creates a main group. Add <code>parent_id</code> to create a subgroup under a main group.</p>
<pre><code>{
  "group_name": "Retail Leads"
}</code></pre>
<pre><code>{
  "group_name": "Hot Leads",
  "parent_id": 12
}</code></pre>
          </section>

          <section id="contacts" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Import Contacts</h2>
            <p><span class="method">POST</span> <code>/api/contacts/import</code></p>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                  <tr><td><code>subgroup_id</code></td><td>integer</td><td>Yes</td><td>Add imported contacts to an existing subgroup. Parent groups cannot hold contacts directly.</td></tr>
                  <tr><td><code>contacts</code></td><td>array</td><td>Yes</td><td>Contact rows. Also accepts <code>contact</code> or <code>rows</code>.</td></tr>
                  <tr><td><code>full_name</code></td><td>string</td><td>No</td><td>Also accepts <code>name</code> or <code>contact_name</code>.</td></tr>
                  <tr><td><code>phone_number</code></td><td>string</td><td>Yes</td><td>Also accepts <code>mobile_number</code> or <code>phone</code>.</td></tr>
                  <tr><td><code>email</code></td><td>string</td><td>No</td><td>Contact email.</td></tr>
                  <tr><td><code>lead_stage</code></td><td>string</td><td>No</td><td>Default: <code>lead</code>.</td></tr>
                  <tr><td><code>lead_status</code></td><td>string</td><td>No</td><td>Default: <code>new</code>.</td></tr>
                  <tr><td><code>source</code></td><td>string</td><td>No</td><td>Default: <code>API Import</code>.</td></tr>
                  <tr><td><code>whatsapp_opt_in</code></td><td>boolean</td><td>No</td><td>Whether the user opted in to WhatsApp messages.</td></tr>
                  <tr><td><code>next_follow_up_at</code></td><td>datetime</td><td>No</td><td>Any parseable date/time.</td></tr>
                  <tr><td><code>notes</code></td><td>string</td><td>No</td><td>Also accepts <code>crm_notes</code>.</td></tr>
                </tbody>
              </table>
            </div>
<pre><code>{
  "group_id": 12,
  "contacts": [
    {
      "full_name": "Nisha Patel",
      "phone_number": "+91990001004",
      "email": "nisha@example.com",
      "lead_status": "new",
      "source": "Website",
      "whatsapp_opt_in": true
    }
  ]
}</code></pre>
            <p class="mb-0">Contacts must be imported into a subgroup. Parent groups are only containers for subgroups.</p>
          </section>

          <section id="webhooks" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Webhooks</h2>
            <p>Use API webhooks when you want Arklytics Connect to POST inbound WhatsApp replies and delivery updates to your application.</p>
            <p><span class="method">GET</span> <code>/api/webhooks/config</code> returns the current webhook setup.</p>
            <p><span class="method">POST</span> <code>/api/webhooks/config</code> enables or updates the destination URL.</p>
<pre><code>curl -X POST "<?php echo h($baseUrl); ?>/api/webhooks/config" \
  -H "Authorization: Bearer YOUR_BUSINESS_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com/webhooks/arklytics","enabled":true}'</code></pre>
            <p>Successful setup response:</p>
<pre><code>{
  "ok": true,
  "biz_id": 25,
  "webhook": {
    "enabled": true,
    "url": "https://example.com/webhooks/arklytics",
    "secret": "whsec_..."
  }
}</code></pre>
            <p>Every webhook request is sent as JSON with these headers:</p>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Header</th><th>Description</th></tr></thead>
                <tbody>
                  <tr><td><code>X-Arklytics-Event</code></td><td>Event name, for example <code>message.received</code>.</td></tr>
                  <tr><td><code>X-Arklytics-Delivery</code></td><td>Unique delivery ID for idempotency.</td></tr>
                  <tr><td><code>X-Arklytics-Timestamp</code></td><td>Unix timestamp used for signature verification.</td></tr>
                  <tr><td><code>X-Arklytics-Signature</code></td><td><code>sha256=...</code> HMAC using <code>timestamp + "." + raw_body</code> and your webhook secret.</td></tr>
                </tbody>
              </table>
            </div>
            <p><code>message.received</code> payload:</p>
<pre><code>{
  "event": "message.received",
  "delivery_id": "whd_8d6f...",
  "api_version": "2026-07-01",
  "created_at": "2026-08-06T08:30:00+00:00",
  "biz_id": 25,
  "data": {
    "message_id": "wamid.HBg...",
    "from": "+919876543210",
    "to_phone_number_id": "1234567890",
    "whatsapp_business_account_id": "9876543210",
    "type": "text",
    "text": "I am interested",
    "timestamp": "2026-08-06T08:29:58+00:00",
    "contact": {
      "id": 44,
      "full_name": "Nisha Patel",
      "phone_number": "+919876543210"
    }
  },
  "raw": {
    "from": "919876543210",
    "id": "wamid.HBg...",
    "timestamp": "1786004998",
    "type": "text",
    "text": { "body": "I am interested" }
  }
}</code></pre>
            <p><code>message.status</code> payload:</p>
<pre><code>{
  "event": "message.status",
  "delivery_id": "whd_7b2a...",
  "api_version": "2026-07-01",
  "created_at": "2026-08-06T08:31:00+00:00",
  "biz_id": 25,
  "data": {
    "message_id": "wamid.HBg...",
    "recipient_id": "919876543210",
    "status": "delivered",
    "timestamp": "2026-08-06T08:30:55+00:00",
    "phone_number_id": "1234567890",
    "whatsapp_business_account_id": "9876543210",
    "conversation": null,
    "pricing": null,
    "errors": null
  },
  "raw": {
    "id": "wamid.HBg...",
    "recipient_id": "919876543210",
    "status": "delivered",
    "timestamp": "1786005055"
  }
}</code></pre>
            <p class="mb-0">Return any <code>2xx</code> status from your endpoint to mark delivery successful. Arklytics logs delivery failures but does not retry them yet.</p>
          </section>

          <section id="examples" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Language Examples</h2>
            <ul class="nav nav-pills mb-3" id="codeTabs" role="tablist">
              <?php foreach (['curl' => 'cURL', 'php' => 'PHP', 'python' => 'Python', 'javascript' => 'JavaScript', 'node' => 'Node.js', 'java' => 'Java', 'csharp' => 'C#', 'go' => 'Go'] as $id => $label): ?>
                <li class="nav-item" role="presentation">
                  <button class="nav-link <?php echo $id === 'curl' ? 'active' : ''; ?>" id="<?php echo h($id); ?>-tab" data-bs-toggle="pill" data-bs-target="#<?php echo h($id); ?>" type="button" role="tab"><?php echo h($label); ?></button>
                </li>
              <?php endforeach; ?>
            </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="curl" role="tabpanel">
<pre><code>curl -X POST "<?php echo h($baseUrl); ?>/api/whatsapp/send" \
  -H "Authorization: Bearer YOUR_BUSINESS_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"kind":"text","to":"+919876543210","message":"Hello from Arklytics Connect API"}'</code></pre>
              </div>
              <div class="tab-pane fade" id="php" role="tabpanel">
<pre><code>&lt;?php
$payload = [
  "kind" =&gt; "text",
  "to" =&gt; "+919876543210",
  "message" =&gt; "Hello from PHP"
];

$ch = curl_init("<?php echo h($baseUrl); ?>/api/whatsapp/send");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER =&gt; true,
  CURLOPT_POST =&gt; true,
  CURLOPT_HTTPHEADER =&gt; [
    "Authorization: Bearer YOUR_BUSINESS_API_KEY",
    "Content-Type: application/json"
  ],
  CURLOPT_POSTFIELDS =&gt; json_encode($payload)
]);

$response = curl_exec($ch);
curl_close($ch);
echo $response;</code></pre>
              </div>
              <div class="tab-pane fade" id="python" role="tabpanel">
<pre><code>import requests

url = "<?php echo h($baseUrl); ?>/api/whatsapp/send"
headers = {
    "Authorization": "Bearer YOUR_BUSINESS_API_KEY",
    "Content-Type": "application/json"
}
payload = {
    "kind": "text",
    "to": "+919876543210",
    "message": "Hello from Python"
}

response = requests.post(url, headers=headers, json=payload, timeout=30)
print(response.json())</code></pre>
              </div>
              <div class="tab-pane fade" id="javascript" role="tabpanel">
<pre><code>fetch("<?php echo h($baseUrl); ?>/api/whatsapp/send", {
  method: "POST",
  headers: {
    "Authorization": "Bearer YOUR_BUSINESS_API_KEY",
    "Content-Type": "application/json"
  },
  body: JSON.stringify({
    kind: "text",
    to: "+919876543210",
    message: "Hello from browser JavaScript"
  })
}).then((res) =&gt; res.json()).then(console.log);</code></pre>
              </div>
              <div class="tab-pane fade" id="node" role="tabpanel">
<pre><code>const response = await fetch("<?php echo h($baseUrl); ?>/api/contacts/import", {
  method: "POST",
  headers: {
    "Authorization": "Bearer YOUR_BUSINESS_API_KEY",
    "Content-Type": "application/json"
  },
  body: JSON.stringify({
    contacts: [{ full_name: "Asha Rao", phone_number: "+919876543210" }]
  })
});

console.log(await response.json());</code></pre>
              </div>
              <div class="tab-pane fade" id="java" role="tabpanel">
<pre><code>HttpClient client = HttpClient.newHttpClient();
String json = """
{"kind":"text","to":"+919876543210","message":"Hello from Java"}
""";

HttpRequest request = HttpRequest.newBuilder()
  .uri(URI.create("<?php echo h($baseUrl); ?>/api/whatsapp/send"))
  .header("Authorization", "Bearer YOUR_BUSINESS_API_KEY")
  .header("Content-Type", "application/json")
  .POST(HttpRequest.BodyPublishers.ofString(json))
  .build();

HttpResponse&lt;String&gt; response =
  client.send(request, HttpResponse.BodyHandlers.ofString());
System.out.println(response.body());</code></pre>
              </div>
              <div class="tab-pane fade" id="csharp" role="tabpanel">
<pre><code>using var client = new HttpClient();
client.DefaultRequestHeaders.Authorization =
    new AuthenticationHeaderValue("Bearer", "YOUR_BUSINESS_API_KEY");

var payload = new {
    kind = "text",
    to = "+919876543210",
    message = "Hello from C#"
};

var response = await client.PostAsJsonAsync(
    "<?php echo h($baseUrl); ?>/api/whatsapp/send",
    payload
);

Console.WriteLine(await response.Content.ReadAsStringAsync());</code></pre>
              </div>
              <div class="tab-pane fade" id="go" role="tabpanel">
<pre><code>payload := strings.NewReader(`{
  "kind": "text",
  "to": "+919876543210",
  "message": "Hello from Go"
}`)

req, _ := http.NewRequest("POST", "<?php echo h($baseUrl); ?>/api/whatsapp/send", payload)
req.Header.Set("Authorization", "Bearer YOUR_BUSINESS_API_KEY")
req.Header.Set("Content-Type", "application/json")

res, err := http.DefaultClient.Do(req)
if err != nil {
  panic(err)
}
defer res.Body.Close()
body, _ := io.ReadAll(res.Body)
fmt.Println(string(body))</code></pre>
              </div>
            </div>
          </section>

          <section id="responses" class="api-card p-4 mb-4">
            <h2 class="h4 fw-bold">Responses</h2>
            <p>Successful WhatsApp send:</p>
<pre><code>{
  "ok": true,
  "biz_id": 25,
  "kind": "text",
  "sent": 1,
  "failed": 0,
  "total": 1,
  "results": [
    {
      "to": "+919876543210",
      "status": "success",
      "message_id": "wamid.HBg...",
      "error": null
    }
  ]
}</code></pre>
            <p>Successful contact import:</p>
<pre><code>{
  "ok": true,
  "biz_id": 25,
  "group_id": 12,
  "created": 1,
  "updated": 0,
  "skipped": 0,
  "total": 1
}</code></pre>
          </section>

          <section id="errors" class="api-card p-4">
            <h2 class="h4 fw-bold">Common Errors</h2>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Status</th><th>Meaning</th><th>Fix</th></tr></thead>
                <tbody>
                  <tr><td><code>401</code></td><td>Missing or invalid API key.</td><td>Send <code>Authorization: Bearer YOUR_BUSINESS_API_KEY</code>.</td></tr>
                  <tr><td><code>404</code></td><td>Template or group was not found.</td><td>Check IDs and template names in the business workspace.</td></tr>
                  <tr><td><code>422</code></td><td>Validation/configuration issue.</td><td>Check WhatsApp credentials, recipient data, template fields, or message limits.</td></tr>
                  <tr><td><code>207</code></td><td>Partial send result.</td><td>Some recipients failed; inspect <code>results</code>.</td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>
    </main>

    <footer class="border-top bg-white">
      <div class="container py-4 d-flex flex-wrap justify-content-between gap-2">
        <span class="text-muted">Arklytics Connect API Documentation</span>
        <a href="<?php echo h($homeUrl); ?>" class="text-success fw-bold text-decoration-none">Back to Home</a>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
