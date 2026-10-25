<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$driver = require_driver();

$rides = db_all(
    "SELECT r.ride_id, r.destination, r.date_time, r.price, COUNT(b.booking_id) AS pax, SUM(r.price) AS earned
     FROM ride r
     JOIN booking b ON b.ride_id = r.ride_id AND b.status IN (" . PAID_STATUSES . ")
     WHERE r.driver_id = ?
     GROUP BY r.ride_id
     ORDER BY r.date_time DESC",
    'i',
    [$driver['driver_id']]
);

$earnedCompleted = 0;
$earnedUpcoming = 0;
$trips = 0;
foreach ($rides as $r) {
    $trips += $r['pax'];
    if (is_past($r['date_time'])) {
        $earnedCompleted += $r['earned'];
    } else {
        $earnedUpcoming += $r['earned'];
    }
}

page_header('Earnings', ['fill' => true, 'role' => 'driver']);
?>
        <?php page_title('Earnings', 'Fares from passengers you accepted.'); ?>

            <div class="ap-stats">
                <div class="ap-stat">
                    <span class="ap-stat-label">Total earned</span>
                    <span class="ap-stat-value text-green"><?= money($earnedCompleted + $earnedUpcoming) ?></span>
                </div>
                <div class="ap-stat">
                    <span class="ap-stat-label">From completed rides</span>
                    <span class="ap-stat-value"><?= money($earnedCompleted) ?></span>
                </div>
                <div class="ap-stat">
                    <span class="ap-stat-label">Booked on upcoming rides</span>
                    <span class="ap-stat-value"><?= money($earnedUpcoming) ?></span>
                </div>
                <div class="ap-stat">
                    <span class="ap-stat-label">Paid seats</span>
                    <span class="ap-stat-value"><?= (int)$trips ?></span>
                </div>
            </div>

        <div class="glass-card">

            <h2>By ride</h2>
            <div class="table-wrap">
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Route</th>
                            <th class="num">Fare</th>
                            <th class="num">Passengers</th>
                            <th class="num">Earned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rides as $r): ?>
                        <tr>
                            <td class="nowrap"><?= fmt_datetime($r['date_time']) ?></td>
                            <td><?= route_html($r['destination']) ?></td>
                            <td class="num"><?= money($r['price']) ?></td>
                            <td class="num"><?= (int)$r['pax'] ?></td>
                            <td class="num text-green"><strong><?= money($r['earned']) ?></strong></td>
                            <td><?= is_past($r['date_time']) ? pill('Completed', 'green') : pill('Upcoming', 'blue') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rides): ?>
                        <tr><td colspan="6" class="empty-state">No paid rides yet. Accept passenger requests under My Rides to start earning.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php page_footer(); ?>
