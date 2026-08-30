<?php
include '../db_conn.php';

$biz_id = Auth::requireLogin();
$db = Database::connect();
PaymentSupport::ensureTables($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    if ($action === 'create_order') {
        header('Content-Type: application/json; charset=utf-8');
        $packageKey = strtolower(trim((string) ($_POST['package_key'] ?? 'starter')));
        $result = PaymentSupport::createRazorpayOrder($db, (int) $biz_id, $packageKey);
        if (!$result['ok']) {
            http_response_code(422);
        }
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'verify_payment') {
        header('Content-Type: application/json; charset=utf-8');
        $result = PaymentSupport::verifyAndActivate($db, (int) $biz_id, $_POST);
        if (!$result['ok']) {
            http_response_code(422);
        }
        echo json_encode($result + ['redirect_url' => app_url('business')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$status = PaymentSupport::businessPackageStatus($db, (int) $biz_id);
$packages = PaymentSupport::packages($db);
$razorpayKeyId = PaymentSupport::razorpayKeyId();

include 'header.php';
?>

<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <h1>Payments</h1>
                <p>Choose a package to activate WhatsApp messages for your business.</p>
            </div>

            <?php if (!($status['active'] ?? false)): ?>
                <div class="alert alert-warning">
                    <?php echo h((string) ($status['reason'] ?? 'Payment is required to continue.')); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    Your current package is active. You can renew or upgrade anytime.
                </div>
            <?php endif; ?>

            <?php if ($razorpayKeyId === ''): ?>
                <div class="alert alert-danger">
                    Razorpay key is missing. Add <code>RAZORPAY_KEY_ID</code> and <code>RAZORPAY_KEY_SECRET</code> in <code>.env</code>.
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <?php foreach ($packages as $key => $package): ?>
                    <?php
                        $marketingLimit = (int) $package['marketing_limit'];
                        $utilityLimit = (int) $package['utility_limit'];
                        $totalLimit = PaymentSupport::packageTotalMessages($package);
                    ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="wg-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="text-muted small">Package</div>
                                    <h4 class="mb-1"><?php echo h((string) $package['label']); ?></h4>
                                </div>
                                <span class="badge bg-success">INR <?php echo h(number_format((float) $package['price'], 2)); ?></span>
                            </div>
                            <div class="mt-3">
                                <div class="fw-bold fs-5"><?php echo h(number_format($totalLimit)); ?> all messages</div>
                                <div class="text-muted small mt-1"><?php echo h(number_format($marketingLimit)); ?> marketing messages</div>
                                <div class="text-muted small"><?php echo h(number_format($utilityLimit)); ?> utility messages</div>
                                <div class="text-muted small mt-2">Valid for <?php echo h((string) $package['days']); ?> days</div>
                            </div>
                            <button
                                type="button"
                                class="btn btn-success w-100 mt-4 js-pay-package"
                                data-package-key="<?php echo h((string) $key); ?>"
                                <?php echo $razorpayKeyId === '' ? 'disabled' : ''; ?>
                            >
                                <i class="bi bi-credit-card me-1"></i> Pay and Activate
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<form id="paymentMetaForm" class="d-none">
    <?php echo Security::csrfField(); ?>
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.querySelectorAll('.js-pay-package').forEach(function (button) {
    button.addEventListener('click', async function () {
        const token = document.querySelector('#paymentMetaForm input[name="_csrf_token"]')?.value || '';
        const packageKey = button.dataset.packageKey || 'starter';
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Starting payment';

        try {
            const createData = new FormData();
            createData.append('_csrf_token', token);
            createData.append('action', 'create_order');
            createData.append('package_key', packageKey);

            const orderResponse = await fetch('<?php echo h(app_url('business/payments')); ?>', {
                method: 'POST',
                body: createData,
            });
            const order = await orderResponse.json();
            if (!order.ok) {
                throw new Error(order.error || 'Unable to start payment.');
            }

            const checkout = new Razorpay({
                key: order.key_id,
                amount: order.amount,
                currency: order.currency || 'INR',
                name: 'Arklytics Connect',
                description: (order.package?.label || 'Package') + ' package',
                order_id: order.order_id,
                handler: async function (response) {
                    const verifyData = new FormData();
                    verifyData.append('_csrf_token', token);
                    verifyData.append('action', 'verify_payment');
                    verifyData.append('razorpay_order_id', response.razorpay_order_id || '');
                    verifyData.append('razorpay_payment_id', response.razorpay_payment_id || '');
                    verifyData.append('razorpay_signature', response.razorpay_signature || '');

                    const verifyResponse = await fetch('<?php echo h(app_url('business/payments')); ?>', {
                        method: 'POST',
                        body: verifyData,
                    });
                    const verified = await verifyResponse.json();
                    if (!verified.ok) {
                        throw new Error(verified.error || 'Payment verification failed.');
                    }

                    window.location.href = verified.redirect_url || '<?php echo h(app_url('business')); ?>';
                },
                theme: { color: '#0f8f63' },
            });
            checkout.open();
        } catch (error) {
            alert(error.message || 'Payment could not be started.');
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-credit-card me-1"></i> Pay and Activate';
        }
    });
});
</script>

<?php include 'footer.php'; ?>
