<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$customer = require_customer();
$customerId = (int)$customer['customer_id'];

// Cancel a request that the driver has not answered yet.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    csrf_check();
    $changed = db_exec(
        "UPDATE booking SET status = 'Cancelled' WHERE booking_id = ? AND customer_id = ? AND status = 'Pending'",
        'ii',
        [(int)$_POST['booking_id'], $customerId]
    );
    flash($changed ? 'success' : 'error', $changed ? 'Your ride request was cancelled.' : 'That request can no longer be cancelled.');
    redirect('customer.php');
}

$bookings = db_all(
    "SELECT b.booking_id, b.status, r.ride_id, r.destination, r.date_time, r.price,
            u.name AS driver_name, u.TP_Number AS driver_tp
     FROM booking b
     JOIN ride r ON b.ride_id = r.ride_id
     LEFT JOIN driver d ON r.driver_id = d.driver_id
     LEFT JOIN user u ON d.user_id = u.user_id
     WHERE b.customer_id = ?
     ORDER BY r.date_time DESC",
    'i',
    [$customerId]
);

$upcoming = [];
$history = [];
foreach ($bookings as $b) {
    $active = in_array($b['status'], ['Pending', 'Confirmed', 'Completed'], true);
    if ($active && !is_past($b['date_time'])) {
        $upcoming[] = $b;
    } else {
        $history[] = $b;
    }
}
$upcoming = array_reverse($upcoming); // soonest first

$co2 = co2_saved((float)db_value(
    "SELECT COALESCE(SUM(r.distance_km), 0) FROM booking b JOIN ride r ON b.ride_id = r.ride_id
     WHERE b.customer_id = ? AND b.status IN (" . PAID_STATUSES . ") AND r.date_time <= ?",
    'is',
    [$customerId, now_sql()]
));
$availableRides = (int)db_value(
    "SELECT COUNT(*) FROM ride r JOIN driver d ON r.driver_id = d.driver_id
     WHERE d.status = 'Approved' AND r.available_seats > 0 AND r.date_time > ?",
    's',
    [now_sql()]
);

function booking_list($rows, $withActions)
{
    foreach ($rows as $b): ?>
                <div class="ride-card">
                    <div style="min-width:0">
                        <div class="ride-route"><?= route_html($b['destination']) ?></div>
                        <div class="ride-meta">
                            <span>🗓 <?= fmt_datetime($b['date_time']) ?></span>
                            <span>🚗 <?= e($b['driver_name'] ?: ($b['driver_tp'] ?? 'Driver removed')) ?></span>
                        </div>
                    </div>
                    <div class="ride-side">
                        <span class="ride-price"><?= money($b['price']) ?></span>
                        <?= booking_pill($b['status'], $b['date_time']) ?>
                        <?php if ($withActions && $b['status'] === 'Pending'): ?>
                            <form method="POST" class="inline-form" onsubmit="return confirm('Cancel this ride request?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                                <button type="submit" name="cancel_booking" class="btn btn-secondary btn-sm">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
    <?php endforeach;
}

$firstName = $customer['name'] !== '' ? explode(' ', $customer['name'])[0] : $customer['tp_number'];

page_header('Home', ['role' => 'customer', 'fill' => true]);
?>
        <?php page_title('Hi, ' . $firstName . ' 👋', 'Your smart hub for daily rides and rewards.',
            '<a href="find_ride.php" class="btn btn-primary">Book a ride</a>'); ?>

        <div class="tile-grid cols-4 no-grow">
            <?php
            tile('find_ride.php', '🔍', 'Find Ride', 'Search and book eco-friendly carpools.', (string)$availableRides, 'rides available');
            tile('topup.php', '💳', 'Wallet', 'Top up for seamless ride payments.', money($customer['wallet']), 'balance');
            tile('co2_stats.php', '🌱', 'CO₂ Impact', "Track how much carbon you've saved.", number_format($co2, 1) . ' kg', 'CO₂ saved');
            tile('rewards.php', '🎁', 'Rewards', 'Redeem points for exclusive perks.', number_format((int)$customer['points_balance']) . ' pts', ($customer['membership_status'] ?: 'Silver') . ' member');
            ?>
        </div>

        <div class="split grow-row">
            <div class="glass-card">
                <div class="section-title">
                    <h2>Upcoming & requests</h2>
                    <span class="ap-muted"><?= count($upcoming) ?></span>
                </div>
                <?php if ($upcoming): ?>
                    <?php booking_list($upcoming, true); ?>
                    <p class="ap-muted small mb-0" style="margin-top:12px">Your wallet is charged when the driver accepts your request.</p>
                <?php else: ?>
                    <p class="empty-state">No upcoming rides. <a href="find_ride.php" class="ap-link">Find one now</a>.</p>
                <?php endif; ?>
            </div>

            <div class="glass-card">
                <div class="section-title">
                    <h2>History</h2>
                    <span class="ap-muted"><?= count($history) ?></span>
                </div>
                <?php if ($history): ?>
                    <?php booking_list($history, false); ?>
                <?php else: ?>
                    <p class="empty-state">Your past rides will appear here.</p>
                <?php endif; ?>
            </div>
        </div>
<?php page_footer(); ?>
