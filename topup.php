<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$customer = require_customer();

// Only allow returning to a booking page after topping up.
$return = (string)($_GET['return'] ?? $_POST['return'] ?? '');
if (!preg_match('/^payment\.php\?ride_id=\d+$/', $return)) {
    $return = '';
}

$errors = [];
$old = ['amount' => '', 'card' => '', 'expiry' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['amount'] = trim((string)($_POST['amount'] ?? ''));
    $old['card'] = trim((string)($_POST['card_number'] ?? ''));
    $old['expiry'] = trim((string)($_POST['expiry'] ?? ''));
    $cvv = trim((string)($_POST['cvv'] ?? ''));

    $amount = filter_var($old['amount'], FILTER_VALIDATE_FLOAT);
    if ($amount === false || $amount < TOPUP_MIN || $amount > TOPUP_MAX || round($amount, 2) != $amount) {
        $errors['amount'] = 'Enter an amount between RM ' . TOPUP_MIN . ' and RM ' . TOPUP_MAX . ' (up to 2 decimals).';
    }
    if (!preg_match('/^\d{13,19}$/', preg_replace('/[\s-]/', '', $old['card']))) {
        $errors['card'] = 'Enter a valid card number.';
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $old['expiry'], $m)) {
        $errors['expiry'] = 'Use MM/YY.';
    } elseif (mktime(0, 0, 0, (int)$m[1] + 1, 1, 2000 + (int)$m[2]) <= time()) {
        $errors['expiry'] = 'This card has expired.';
    }
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        $errors['cvv'] = 'Enter the 3–4 digit code.';
    }

    if (!$errors) {
        // Demo payment: card details are only validated, never stored or charged.
        db_exec("UPDATE customer SET wallet = wallet + ? WHERE customer_id = ?", 'di', [$amount, $customer['customer_id']]);
        flash('success', money($amount) . ' added to your wallet.');
        redirect($return !== '' ? $return : 'customer.php');
    }
}

function topup_error($errors, $key)
{
    return isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : '';
}

// Last fares taken from the wallet (accepted bookings).
$charges = db_all(
    "SELECT r.destination, r.date_time, r.price
     FROM booking b JOIN ride r ON b.ride_id = r.ride_id
     WHERE b.customer_id = ? AND b.status IN (" . PAID_STATUSES . ")
     ORDER BY b.booking_id DESC LIMIT 3",
    'i',
    [$customer['customer_id']]
);

page_header('Top-up Wallet', ['role' => 'customer', 'fill' => true]);
?>
        <?php page_title('Top-up Wallet', 'Add funds for seamless ride payments.'); ?>

        <div class="split-1-2 grow-row">
            <div class="glass-card">
                <h2>Your wallet</h2>
                <div class="ap-stat" style="margin-bottom:20px">
                    <span class="ap-stat-label">Current balance</span>
                    <span class="ap-stat-value text-green" style="font-size:36px"><?= money($customer['wallet']) ?></span>
                </div>
                <h2>How it works</h2>
                <ul class="tips">
                    <li>Top up any amount from RM <?= TOPUP_MIN ?> to RM <?= TOPUP_MAX ?>.</li>
                    <li>Request a seat on any ride — you're only charged when the driver accepts.</li>
                    <li>If a driver cancels an upcoming ride, the fare comes straight back to your wallet.</li>
                    <li>Every accepted ride also earns you <?= POINTS_PER_RIDE ?> reward points.</li>
                </ul>

                <h2 style="margin-top:22px">Recent charges</h2>
                <?php foreach ($charges as $c): ?>
                    <div class="list-row">
                        <span style="min-width:0">
                            <?= route_html($c['destination']) ?>
                            <span class="ap-muted small" style="display:block"><?= fmt_datetime($c['date_time']) ?></span>
                        </span>
                        <strong class="text-amber nowrap">−<?= money($c['price']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if (!$charges): ?>
                    <p class="ap-muted mb-0">No rides charged yet.</p>
                <?php endif; ?>
            </div>

            <div class="glass-card">
                <h2>Reload</h2>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return" value="<?= e($return) ?>">

                    <label class="ap-label" for="amount">Reload amount (RM)</label>
                    <div class="chip-group" role="group" aria-label="Quick amounts">
                        <?php foreach ([10, 20, 50, 100] as $quick): ?>
                            <button type="button" class="chip" data-amount="<?= $quick ?>">RM <?= $quick ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input class="ap-input<?= isset($errors['amount']) ? ' has-error' : '' ?>" type="number" id="amount" name="amount"
                           placeholder="e.g. 50" min="<?= TOPUP_MIN ?>" max="<?= TOPUP_MAX ?>" step="0.01" value="<?= e($old['amount']) ?>" required>
                    <?= topup_error($errors, 'amount') ?>

                    <label class="ap-label" for="card_number">Card number</label>
                    <input class="ap-input<?= isset($errors['card']) ? ' has-error' : '' ?>" type="text" id="card_number" name="card_number"
                           placeholder="1234 5678 9012 3456" inputmode="numeric" autocomplete="cc-number"
                           pattern="[0-9 \-]{13,23}" maxlength="23" value="<?= e($old['card']) ?>" required>
                    <?= topup_error($errors, 'card') ?>

                    <div class="form-row">
                        <div>
                            <label class="ap-label" for="expiry">Expiry (MM/YY)</label>
                            <input class="ap-input<?= isset($errors['expiry']) ? ' has-error' : '' ?>" type="text" id="expiry" name="expiry"
                                   placeholder="MM/YY" inputmode="numeric" autocomplete="cc-exp" pattern="(0[1-9]|1[0-2])/\d{2}" maxlength="5"
                                   value="<?= e($old['expiry']) ?>" required>
                            <?= topup_error($errors, 'expiry') ?>
                        </div>
                        <div>
                            <label class="ap-label" for="cvv">CVV</label>
                            <input class="ap-input<?= isset($errors['cvv']) ? ' has-error' : '' ?>" type="password" id="cvv" name="cvv"
                                   placeholder="123" inputmode="numeric" autocomplete="cc-csc" pattern="\d{3,4}" maxlength="4" required>
                            <?= topup_error($errors, 'cvv') ?>
                        </div>
                    </div>

                    <p class="ap-muted small">🔒 Demo payment — card details are checked for format only and are never stored.</p>
                    <div class="form-actions">
                        <a href="<?= e($return !== '' ? $return : 'customer.php') ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Confirm reload</button>
                    </div>
                </form>
            </div>
        </div>
<?php
page_footer(<<<'HTML'
    <script>
        document.querySelectorAll('.chip[data-amount]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                document.getElementById('amount').value = chip.dataset.amount;
                document.querySelectorAll('.chip[data-amount]').forEach(function (c) { c.classList.toggle('active', c === chip); });
            });
        });
        document.getElementById('expiry').addEventListener('input', function (e) {
            var digits = e.target.value.replace(/\D/g, '').slice(0, 4);
            e.target.value = digits.length > 2 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits;
        });
    </script>
HTML
);
