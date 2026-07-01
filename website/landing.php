<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$logoUrl = app_url('website/uploads/connect-logo.png');
$heroImageUrl = app_url('master/uploads/slider1.jpg');
$loginUrl = app_url('business/login');
$signupUrl = app_url('business/signup');
$businessUrl = app_url('business');
$privacyUrl = app_url('privacy-policy');
$termsUrl = app_url('terms-conditions');
$crmPrivacyUrl = app_url('crm-privacy');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Arklytics Connect brings WhatsApp messaging, CRM, follow-ups, and business workflows into one fast workspace.">
    <title>Arklytics Connect | WhatsApp CRM for Growing Businesses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
      :root {
        --ac-ink: #142126;
        --ac-muted: #5f6f74;
        --ac-line: #dce8e7;
        --ac-green: #12825c;
        --ac-green-dark: #0b5f45;
        --ac-mint: #eaf7f0;
        --ac-sky: #dff3fa;
        --ac-sun: #f7d77e;
        --ac-coral: #e77b67;
        --ac-paper: #fbfdfb;
        --ac-navy: #0e2430;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        color: var(--ac-ink);
        background: var(--ac-paper);
        font-family: Manrope, "Segoe UI", sans-serif;
      }

      a {
        color: inherit;
      }

      .ac-nav {
        position: sticky;
        top: 0;
        z-index: 50;
        background: rgba(251, 253, 251, 0.94);
        border-bottom: 1px solid rgba(20, 33, 38, 0.08);
        backdrop-filter: blur(16px);
      }

      .ac-brand img {
        height: 48px;
        max-width: 220px;
        object-fit: contain;
      }

      .ac-nav-link {
        color: var(--ac-muted);
        font-weight: 700;
        text-decoration: none;
      }

      .ac-nav-link:hover {
        color: var(--ac-green-dark);
      }

      .ac-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 46px;
        padding: 12px 18px;
        border-radius: 8px;
        font-weight: 800;
        text-decoration: none;
        transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
      }

      .ac-btn:hover {
        transform: translateY(-1px);
      }

      .ac-btn-primary {
        color: #fff;
        background: var(--ac-green);
        box-shadow: 0 14px 28px rgba(18, 130, 92, 0.22);
      }

      .ac-btn-primary:hover {
        color: #fff;
        background: var(--ac-green-dark);
      }

      .ac-btn-light {
        color: var(--ac-ink);
        background: #fff;
        border: 1px solid var(--ac-line);
      }

      .ac-hero {
        min-height: calc(100vh - 72px);
        display: flex;
        align-items: stretch;
        background:
          linear-gradient(90deg, rgba(251, 253, 251, 0.96) 0%, rgba(251, 253, 251, 0.9) 47%, rgba(14, 36, 48, 0.42) 100%),
          url("<?php echo h($heroImageUrl); ?>") center right / cover no-repeat;
        border-bottom: 1px solid var(--ac-line);
      }

      .ac-hero-inner {
        width: 100%;
        display: grid;
        grid-template-columns: minmax(0, 1.03fr) minmax(320px, 0.72fr);
        align-items: center;
        gap: 36px;
        padding: 68px 0 42px;
      }

      .ac-kicker {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 8px 12px;
        border: 1px solid rgba(18, 130, 92, 0.2);
        border-radius: 999px;
        background: rgba(234, 247, 240, 0.86);
        color: var(--ac-green-dark);
        font-size: 0.84rem;
        font-weight: 800;
      }

      .ac-hero h1,
      .ac-section-title h2 {
        font-family: "Space Grotesk", Manrope, sans-serif;
        letter-spacing: 0;
      }

      .ac-hero h1 {
        max-width: 780px;
        margin: 20px 0 18px;
        font-size: clamp(2.6rem, 6vw, 5.8rem);
        line-height: 0.96;
        font-weight: 700;
      }

      .ac-hero-text {
        max-width: 680px;
        color: var(--ac-muted);
        font-size: 1.13rem;
        line-height: 1.75;
      }

      .ac-action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 30px;
      }

      .ac-trust-row {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        max-width: 720px;
        margin-top: 34px;
      }

      .ac-trust-item {
        min-height: 82px;
        padding: 15px;
        border: 1px solid rgba(20, 33, 38, 0.08);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.84);
      }

      .ac-trust-item strong {
        display: block;
        font-size: 1.3rem;
      }

      .ac-trust-item span {
        color: var(--ac-muted);
        font-size: 0.88rem;
        font-weight: 700;
      }

      .ac-live-panel {
        align-self: end;
        background: rgba(14, 36, 48, 0.92);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.16);
        border-radius: 8px;
        box-shadow: 0 24px 70px rgba(14, 36, 48, 0.28);
        overflow: hidden;
      }

      .ac-live-head,
      .ac-live-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
      }

      .ac-live-head {
        padding: 17px 18px;
        background: rgba(255, 255, 255, 0.08);
      }

      .ac-live-head strong {
        font-family: "Space Grotesk", Manrope, sans-serif;
        font-size: 1rem;
      }

      .ac-pulse {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #4fe7a0;
        box-shadow: 0 0 0 8px rgba(79, 231, 160, 0.13);
      }

      .ac-live-body {
        padding: 10px 18px 18px;
      }

      .ac-live-row {
        padding: 13px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      }

      .ac-live-row:last-child {
        border-bottom: 0;
      }

      .ac-live-row small {
        color: rgba(255, 255, 255, 0.68);
        font-weight: 700;
      }

      .ac-status {
        padding: 5px 9px;
        border-radius: 999px;
        background: rgba(79, 231, 160, 0.14);
        color: #9af2c8;
        font-size: 0.78rem;
        font-weight: 800;
      }

      .ac-section {
        padding: 84px 0;
      }

      .ac-section.alt {
        background:
          linear-gradient(180deg, #ffffff 0%, #f3f8f7 100%);
        border-top: 1px solid var(--ac-line);
        border-bottom: 1px solid var(--ac-line);
      }

      .ac-section-title {
        max-width: 780px;
        margin-bottom: 32px;
      }

      .ac-section-title h2 {
        margin-bottom: 12px;
        font-size: clamp(2rem, 4vw, 3.3rem);
        font-weight: 700;
      }

      .ac-section-title p {
        color: var(--ac-muted);
        font-size: 1.05rem;
        line-height: 1.75;
      }

      .ac-slider {
        border: 1px solid var(--ac-line);
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 18px 50px rgba(20, 33, 38, 0.08);
      }

      .ac-slide {
        min-height: 420px;
        display: grid;
        grid-template-columns: minmax(0, 0.92fr) minmax(320px, 1.08fr);
      }

      .ac-slide-copy {
        padding: clamp(28px, 5vw, 54px);
        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .ac-slide-copy h3 {
        font-family: "Space Grotesk", Manrope, sans-serif;
        font-size: clamp(1.8rem, 3vw, 3rem);
        font-weight: 700;
      }

      .ac-slide-copy p {
        color: var(--ac-muted);
        line-height: 1.75;
      }

      .ac-slide-visual {
        position: relative;
        min-height: 360px;
        background:
          linear-gradient(135deg, rgba(18, 130, 92, 0.8), rgba(14, 36, 48, 0.82)),
          url("<?php echo h($heroImageUrl); ?>") center / cover no-repeat;
      }

      .ac-phone {
        position: absolute;
        right: clamp(22px, 8vw, 72px);
        top: 50%;
        width: min(285px, 70%);
        transform: translateY(-50%);
        border-radius: 30px;
        background: #f7fbf8;
        border: 8px solid #102832;
        box-shadow: 0 22px 50px rgba(0, 0, 0, 0.24);
        overflow: hidden;
      }

      .ac-phone-top {
        padding: 13px 16px;
        background: #0f6e50;
        color: #fff;
        font-weight: 800;
      }

      .ac-chat {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 18px 14px 22px;
      }

      .ac-bubble {
        max-width: 88%;
        padding: 10px 12px;
        border-radius: 14px;
        color: #193238;
        background: #fff;
        box-shadow: 0 8px 18px rgba(14, 36, 48, 0.08);
        font-size: 0.86rem;
        font-weight: 700;
      }

      .ac-bubble.out {
        margin-left: auto;
        color: #063425;
        background: #d9f7e7;
      }

      .carousel-control-prev,
      .carousel-control-next {
        width: 52px;
      }

      .carousel-indicators [data-bs-target] {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background-color: var(--ac-green);
      }

      .ac-feature-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
      }

      .ac-feature {
        min-height: 228px;
        padding: 24px;
        border: 1px solid var(--ac-line);
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 14px 36px rgba(20, 33, 38, 0.06);
      }

      .ac-feature i,
      .ac-industry i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        margin-bottom: 18px;
        border-radius: 8px;
        color: var(--ac-green-dark);
        background: var(--ac-mint);
        font-size: 1.35rem;
      }

      .ac-feature h3,
      .ac-industry h3 {
        font-size: 1.08rem;
        font-weight: 800;
      }

      .ac-feature p,
      .ac-industry p {
        margin-bottom: 0;
        color: var(--ac-muted);
        line-height: 1.65;
        font-size: 0.95rem;
      }

      .ac-industry-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
      }

      .ac-industry {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 18px;
        padding: 24px;
        border: 1px solid rgba(20, 33, 38, 0.08);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.82);
      }

      .ac-industry i {
        margin-bottom: 0;
      }

      .ac-workflow {
        display: grid;
        grid-template-columns: minmax(0, 0.78fr) minmax(320px, 1.22fr);
        gap: 26px;
        align-items: stretch;
      }

      .ac-workflow-list {
        display: grid;
        gap: 12px;
      }

      .ac-step {
        display: grid;
        grid-template-columns: 48px minmax(0, 1fr);
        gap: 14px;
        padding: 18px;
        border: 1px solid var(--ac-line);
        border-radius: 8px;
        background: #fff;
      }

      .ac-step span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 8px;
        color: #fff;
        background: var(--ac-green);
        font-weight: 800;
      }

      .ac-step h3 {
        margin: 0 0 5px;
        font-size: 1rem;
        font-weight: 800;
      }

      .ac-step p {
        margin: 0;
        color: var(--ac-muted);
        line-height: 1.55;
      }

      .ac-dashboard {
        padding: 22px;
        border-radius: 8px;
        background: var(--ac-navy);
        color: #fff;
        box-shadow: 0 20px 58px rgba(14, 36, 48, 0.18);
      }

      .ac-dashboard-bar {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        padding-bottom: 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
      }

      .ac-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 16px;
      }

      .ac-metric {
        min-height: 104px;
        padding: 16px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.08);
      }

      .ac-metric strong {
        display: block;
        font-size: 1.6rem;
      }

      .ac-metric span {
        color: rgba(255, 255, 255, 0.68);
        font-size: 0.86rem;
        font-weight: 700;
      }

      .ac-message-list {
        display: grid;
        gap: 10px;
        margin-top: 16px;
      }

      .ac-message-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.08);
      }

      .ac-message-item small {
        color: rgba(255, 255, 255, 0.62);
        font-weight: 700;
      }

      .ac-cta {
        color: #fff;
        background:
          linear-gradient(135deg, rgba(14, 36, 48, 0.96), rgba(18, 130, 92, 0.9)),
          url("<?php echo h($heroImageUrl); ?>") center / cover no-repeat;
      }

      .ac-cta-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
      }

      .ac-cta h2 {
        font-family: "Space Grotesk", Manrope, sans-serif;
        font-size: clamp(2rem, 4vw, 3.5rem);
        font-weight: 700;
      }

      .ac-cta p {
        max-width: 650px;
        color: rgba(255, 255, 255, 0.78);
        line-height: 1.75;
      }

      .ac-footer {
        padding: 42px 0;
        color: rgba(255, 255, 255, 0.78);
        background: #091a22;
      }

      .ac-footer a {
        color: rgba(255, 255, 255, 0.82);
        text-decoration: none;
      }

      .ac-footer a:hover {
        color: #fff;
      }

      @media (max-width: 991px) {
        .ac-hero {
          min-height: auto;
          background:
            linear-gradient(180deg, rgba(251, 253, 251, 0.96), rgba(251, 253, 251, 0.91)),
            url("<?php echo h($heroImageUrl); ?>") center / cover no-repeat;
        }

        .ac-hero-inner,
        .ac-slide,
        .ac-workflow,
        .ac-cta-inner {
          grid-template-columns: 1fr;
        }

        .ac-cta-inner {
          display: grid;
        }

        .ac-feature-grid,
        .ac-industry-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 767px) {
        .ac-nav-actions {
          width: 100%;
          margin-top: 12px;
        }

        .ac-nav-actions .ac-btn {
          flex: 1 1 0;
          min-width: 140px;
        }

        .ac-hero-inner {
          padding: 42px 0 28px;
        }

        .ac-trust-row,
        .ac-feature-grid,
        .ac-industry-grid,
        .ac-dashboard-grid {
          grid-template-columns: 1fr;
        }

        .ac-section {
          padding: 58px 0;
        }

        .ac-slide {
          min-height: 0;
        }

        .ac-slide-visual {
          min-height: 320px;
        }
      }
    </style>
  </head>
  <body>
    <nav class="ac-nav">
      <div class="container py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <a class="ac-brand" href="<?php echo h(app_url('')); ?>" aria-label="Arklytics Connect home">
            <img src="<?php echo h($logoUrl); ?>" alt="Arklytics Connect">
          </a>
          <div class="d-none d-lg-flex align-items-center gap-4">
            <a class="ac-nav-link" href="#platform">Platform</a>
            <a class="ac-nav-link" href="#industries">Industries</a>
            <a class="ac-nav-link" href="#workflow">Workflow</a>
          </div>
          <div class="ac-nav-actions d-flex flex-wrap gap-2">
            <a class="ac-btn ac-btn-light" href="<?php echo h($loginUrl); ?>"><i class="bi bi-box-arrow-in-right"></i> Login to CRM</a>
            <a class="ac-btn ac-btn-primary" href="<?php echo h($signupUrl); ?>"><i class="bi bi-whatsapp"></i> Connect Business</a>
          </div>
        </div>
      </div>
    </nav>

    <main>
      <section class="ac-hero">
        <div class="container ac-hero-inner">
          <div>
            <span class="ac-kicker"><i class="bi bi-lightning-charge-fill"></i> WhatsApp CRM for fast-moving teams</span>
            <h1>Arklytics Connect</h1>
            <p class="ac-hero-text">Bring customer conversations, leads, follow-ups, templates, campaigns, and reporting into one clean CRM built around WhatsApp. Built for SaaS teams, stores, service businesses, hospitals, and local operators who need quick user interactions without losing context.</p>
            <div class="ac-action-row">
              <a class="ac-btn ac-btn-primary" href="<?php echo h($signupUrl); ?>"><i class="bi bi-building-add"></i> Connect Business</a>
              <a class="ac-btn ac-btn-light" href="<?php echo h($loginUrl); ?>"><i class="bi bi-kanban"></i> Login to CRM</a>
            </div>
            <div class="ac-trust-row">
              <div class="ac-trust-item">
                <strong>1 inbox</strong>
                <span>CRM, contacts, templates</span>
              </div>
              <div class="ac-trust-item">
                <strong>Fast</strong>
                <span>Lead updates in seconds</span>
              </div>
              <div class="ac-trust-item">
                <strong>Ready</strong>
                <span>For campaigns and follow-ups</span>
              </div>
            </div>
          </div>

          <aside class="ac-live-panel" aria-label="Live CRM preview">
            <div class="ac-live-head">
              <strong>Today in Connect CRM</strong>
              <span class="ac-pulse" aria-hidden="true"></span>
            </div>
            <div class="ac-live-body">
              <div class="ac-live-row">
                <div>
                  <strong>New WhatsApp lead</strong><br>
                  <small>Store inquiry assigned to sales</small>
                </div>
                <span class="ac-status">Live</span>
              </div>
              <div class="ac-live-row">
                <div>
                  <strong>Follow-up due</strong><br>
                  <small>Hospital appointment reminder</small>
                </div>
                <span class="ac-status">Now</span>
              </div>
              <div class="ac-live-row">
                <div>
                  <strong>Template sent</strong><br>
                  <small>SaaS onboarding checklist</small>
                </div>
                <span class="ac-status">Sent</span>
              </div>
            </div>
          </aside>
        </div>
      </section>

      <section class="ac-section" id="platform">
        <div class="container">
          <div class="ac-section-title">
            <h2>A practical CRM around real conversations</h2>
            <p>Arklytics Connect keeps daily customer work close to WhatsApp, so your team can capture leads, group contacts, send approved templates, and move opportunities forward from one workspace.</p>
          </div>

          <div id="connectSlider" class="carousel slide ac-slider" data-bs-ride="carousel" data-bs-interval="4300">
            <div class="carousel-indicators">
              <button type="button" data-bs-target="#connectSlider" data-bs-slide-to="0" class="active" aria-current="true" aria-label="CRM workspace"></button>
              <button type="button" data-bs-target="#connectSlider" data-bs-slide-to="1" aria-label="WhatsApp campaigns"></button>
              <button type="button" data-bs-target="#connectSlider" data-bs-slide-to="2" aria-label="Reports and follow-ups"></button>
            </div>
            <div class="carousel-inner">
              <div class="carousel-item active">
                <div class="ac-slide">
                  <div class="ac-slide-copy">
                    <span class="ac-kicker">CRM workspace</span>
                    <h3>Know every customer, stage, and next action.</h3>
                    <p>Track contacts, lead status, notes, source, due follow-ups, and team activity without switching between spreadsheets and chat apps.</p>
                  </div>
                  <div class="ac-slide-visual">
                    <div class="ac-phone">
                      <div class="ac-phone-top">Arklytics CRM</div>
                      <div class="ac-chat">
                        <div class="ac-bubble">New lead from WhatsApp: interested in premium plan.</div>
                        <div class="ac-bubble out">Assigned to Asha. Follow-up set for 4:30 PM.</div>
                        <div class="ac-bubble">Status moved to Qualified.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="carousel-item">
                <div class="ac-slide">
                  <div class="ac-slide-copy">
                    <span class="ac-kicker">WhatsApp campaigns</span>
                    <h3>Send approved templates to the right audience.</h3>
                    <p>Create customer groups, upload contacts, attach media, and launch marketing, utility, or authentication messages with proper delivery logs.</p>
                  </div>
                  <div class="ac-slide-visual">
                    <div class="ac-phone">
                      <div class="ac-phone-top">Campaign Ready</div>
                      <div class="ac-chat">
                        <div class="ac-bubble">Hi Priya, your order is ready for pickup.</div>
                        <div class="ac-bubble out">Button: View invoice</div>
                        <div class="ac-bubble">Delivered to 284 contacts.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="carousel-item">
                <div class="ac-slide">
                  <div class="ac-slide-copy">
                    <span class="ac-kicker">Follow-up engine</span>
                    <h3>Move faster when the next step is obvious.</h3>
                    <p>Use due follow-up views, lead reports, message history, and package usage visibility to keep work moving during busy business hours.</p>
                  </div>
                  <div class="ac-slide-visual">
                    <div class="ac-phone">
                      <div class="ac-phone-top">Reports</div>
                      <div class="ac-chat">
                        <div class="ac-bubble">18 hot leads need a reply today.</div>
                        <div class="ac-bubble out">Sequence: Day 2 reminder queued.</div>
                        <div class="ac-bubble">7 leads marked won this week.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#connectSlider" data-bs-slide="prev" aria-label="Previous slide">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#connectSlider" data-bs-slide="next" aria-label="Next slide">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
            </button>
          </div>
        </div>
      </section>

      <section class="ac-section alt">
        <div class="container">
          <div class="ac-section-title">
            <h2>Everything your team needs to respond quickly</h2>
            <p>Designed for practical teams who need speed, accountability, and clean customer records, not another complicated dashboard.</p>
          </div>
          <div class="ac-feature-grid">
            <article class="ac-feature">
              <i class="bi bi-person-lines-fill"></i>
              <h3>Lead CRM</h3>
              <p>Capture contacts, stages, notes, sources, statuses, and next follow-up dates in one place.</p>
            </article>
            <article class="ac-feature">
              <i class="bi bi-whatsapp"></i>
              <h3>WhatsApp templates</h3>
              <p>Create and send business-approved messages with media, buttons, and sample placeholders.</p>
            </article>
            <article class="ac-feature">
              <i class="bi bi-diagram-3"></i>
              <h3>Sequences</h3>
              <p>Plan structured follow-ups for onboarding, reminders, renewals, reactivation, and offers.</p>
            </article>
            <article class="ac-feature">
              <i class="bi bi-bar-chart-line"></i>
              <h3>Reports</h3>
              <p>See sent messages, delivery outcomes, due work, won leads, lost leads, and package usage.</p>
            </article>
          </div>
        </div>
      </section>

      <section class="ac-section" id="industries">
        <div class="container">
          <div class="ac-section-title">
            <h2>Built for teams where every reply matters</h2>
            <p>Arklytics Connect fits everyday operations across service, sales, support, and appointment-driven businesses.</p>
          </div>
          <div class="ac-industry-grid">
            <article class="ac-industry">
              <i class="bi bi-cloud-check"></i>
              <div>
                <h3>SaaS and service teams</h3>
                <p>Qualify inbound demos, send onboarding templates, trigger renewal reminders, and track support conversations.</p>
              </div>
            </article>
            <article class="ac-industry">
              <i class="bi bi-shop"></i>
              <div>
                <h3>Stores and retailers</h3>
                <p>Manage product inquiries, order updates, customer groups, offers, and repeat purchase campaigns.</p>
              </div>
            </article>
            <article class="ac-industry">
              <i class="bi bi-briefcase"></i>
              <div>
                <h3>Local businesses</h3>
                <p>Keep all customer requests, follow-ups, quotes, and booking reminders organized for the whole team.</p>
              </div>
            </article>
            <article class="ac-industry">
              <i class="bi bi-hospital"></i>
              <div>
                <h3>Hospitals and clinics</h3>
                <p>Send appointment reminders, patient instructions, reports updates, and follow-up prompts with clean logs.</p>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section class="ac-section alt" id="workflow">
        <div class="container">
          <div class="ac-workflow">
            <div>
              <div class="ac-section-title">
                <h2>From first message to won customer</h2>
                <p>A faster workflow for teams who live in WhatsApp but still need CRM discipline.</p>
              </div>
              <div class="ac-workflow-list">
                <div class="ac-step">
                  <span>1</span>
                  <div>
                    <h3>Connect business</h3>
                    <p>Set up the business workspace and connect WhatsApp credentials.</p>
                  </div>
                </div>
                <div class="ac-step">
                  <span>2</span>
                  <div>
                    <h3>Add leads</h3>
                    <p>Create contacts manually or import lists into groups for campaigns.</p>
                  </div>
                </div>
                <div class="ac-step">
                  <span>3</span>
                  <div>
                    <h3>Send and follow up</h3>
                    <p>Use templates, sequences, and reports to keep every interaction moving.</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="ac-dashboard">
              <div class="ac-dashboard-bar">
                <div>
                  <strong>CRM command center</strong><br>
                  <small class="text-white-50">Fast interaction view</small>
                </div>
                <span class="ac-status">Active</span>
              </div>
              <div class="ac-dashboard-grid">
                <div class="ac-metric">
                  <strong>284</strong>
                  <span>Messages sent</span>
                </div>
                <div class="ac-metric">
                  <strong>42</strong>
                  <span>Open leads</span>
                </div>
                <div class="ac-metric">
                  <strong>18</strong>
                  <span>Due follow-ups</span>
                </div>
              </div>
              <div class="ac-message-list">
                <div class="ac-message-item">
                  <div>
                    <strong>Demo request</strong><br>
                    <small>SaaS prospect moved to Qualified</small>
                  </div>
                  <i class="bi bi-arrow-up-right-circle"></i>
                </div>
                <div class="ac-message-item">
                  <div>
                    <strong>Appointment reminder</strong><br>
                    <small>Clinic template delivered</small>
                  </div>
                  <i class="bi bi-check2-circle"></i>
                </div>
                <div class="ac-message-item">
                  <div>
                    <strong>Store campaign</strong><br>
                    <small>Offer sent to repeat customers</small>
                  </div>
                  <i class="bi bi-send-check"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="ac-section ac-cta">
        <div class="container ac-cta-inner">
          <div>
            <h2>Open the workspace your team will actually use.</h2>
            <p>Connect WhatsApp, organize contacts, send templates, and manage customer follow-ups from a CRM built for daily business work.</p>
          </div>
          <div class="ac-action-row m-0">
            <a class="ac-btn ac-btn-primary" href="<?php echo h($signupUrl); ?>"><i class="bi bi-building-add"></i> Connect Business</a>
            <a class="ac-btn ac-btn-light" href="<?php echo h($businessUrl); ?>"><i class="bi bi-kanban"></i> Open CRM</a>
          </div>
        </div>
      </section>
    </main>

    <footer class="ac-footer">
      <div class="container">
        <div class="d-flex flex-wrap justify-content-between gap-4">
          <div>
            <img src="<?php echo h($logoUrl); ?>" alt="Arklytics Connect" style="height: 44px; max-width: 220px; object-fit: contain; filter: brightness(0) invert(1);">
            <p class="mt-3 mb-0" style="max-width: 480px;">WhatsApp CRM for customer communication, campaigns, lead tracking, and follow-up discipline.</p>
          </div>
          <div class="d-flex flex-wrap gap-4 align-items-start">
            <a href="<?php echo h($signupUrl); ?>">Connect Business</a>
            <a href="<?php echo h($loginUrl); ?>">Login to CRM</a>
            <a href="<?php echo h($privacyUrl); ?>">Privacy</a>
            <a href="<?php echo h($termsUrl); ?>">Terms</a>
            <a href="<?php echo h($crmPrivacyUrl); ?>">CRM Privacy</a>
          </div>
        </div>
        <div class="pt-4 mt-4 border-top" style="border-color: rgba(255, 255, 255, 0.12) !important;">
          <small>Copyright <?php echo h(date('Y')); ?> Arklytics Connect. All rights reserved.</small>
        </div>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
  </body>
</html>
