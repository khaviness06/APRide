<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$customer = require_customer();

$rideId = (int)($_GET['ride_id'] ?? 0);
[$ride, $problem] = bookable_ride($rideId, $customer['customer_id']);
if (!$ride || $problem) {
    flash('error', $problem);
    redirect($ride ? 'ride_details.php?ride_id=' . $rideId : 'find_ride.php');
}

$wallet = (float)$customer['wallet'];
$price = (float)$ride['price'];
$enough = $wallet >= $price;

page_header('Confirm Booking', ['role' => 'customer', 'active' => 'find_ride.php', 'fill' => true]);
?>
        <a href="ride_details.php?ride_id=<?= $rideId ?>" class="back-link">← Back to ride details</a>
        <?php page_title('Confirm your booking', 'Review the trip and how it will be paid.'); ?>

        <div class="split grow-row">
            <div class="glass-card">
                <h2>Trip summary</h2>
                <dl class="kv">
                    <div><dt>Route</dt><dd><?= route_html($ride['destination']) ?></dd></div>
                    <div><dt>Date & time</dt><dd><?= fmt_datetime($ride['date_time']) ?></dd></div>
                    <div><dt>Driver</dt><dd><?= e($ride['driver_name'] ?: $ride['driver_tp']) ?></dd></div>
                    <div><dt>Vehicle</dt><dd><?= e($ride['vehicle_type']) ?> · <?= e($ride['license_plate']) ?></dd></div>
                    <div><dt>Distance</dt><dd><?= number_format($ride['distance_km'], 1) ?> km</dd></div>
                    <div><dt>Fare</dt><dd class="text-green" style="font-size:18px"><?= money($price) ?></dd></div>
                </dl>
            </div>

            <div class="glass-card">
                <h2>Payment</h2>
                <dl class="kv">
                    <div><dt>Wallet balance</dt><dd><?= money($wallet) ?></dd></div>
                    <div><dt>After this ride</dt><dd class="<?= $enough ? '' : 'text-red' ?>"><?= money($wallet - $price) ?></dd></div>
                </dl>

                <?php if ($enough): ?>
                    <div class="ap-alert ap-alert-info" style="margin:18px 0">
                        Your request goes to the driver. <strong><?= money($price) ?></strong> is taken from your wallet only when they accept, and you earn <?= POINTS_PER_RIDE ?> reward points.
                    </div>
                    <form method="POST" action="booking_request.php" class="form-actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ride_id" value="<?= $rideId ?>">
                        <a href="find_ride.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Request seat · <?= money($price) ?></button>
                    </form>
                <?php else: ?>
                    <div class="ap-alert ap-alert-error" style="margin:18px 0">
                        You need <?= money($price - $wallet) ?> more in your wallet to book this ride.
                    </div>
                    <div class="form-actions">
                        <a href="find_ride.php" class="btn btn-secondary">Cancel</a>
                        <a href="topup.php?return=<?= urlencode('payment.php?ride_id=' . $rideId) ?>" class="btn btn-primary">Top up wallet</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php page_footer(); ?>
