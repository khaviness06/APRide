<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';

// Admins see every ride; drivers see their own rides plus eco stats.
if (current_user_id() && current_role() === 'admin') {
    $isAdmin = true;
    require_admin();
} else {
    $isAdmin = false;
    $driver = require_driver();
}

$sql = "SELECT r.ride_id, r.destination, r.date_time, r.distance_km, r.price,
               COUNT(b.booking_id) AS pax_count,
               u.TP_Number AS driver_tp, u.name AS driver_name
        FROM ride r
        LEFT JOIN booking b ON r.ride_id = b.ride_id AND b.status IN (" . PAID_STATUSES . ")
        LEFT JOIN driver d ON r.driver_id = d.driver_id
        LEFT JOIN user u ON d.user_id = u.user_id";
if ($isAdmin) {
    $rides = db_all("$sql GROUP BY r.ride_id ORDER BY r.date_time DESC");
} else {
    $rides = db_all("$sql WHERE r.driver_id = ? GROUP BY r.ride_id ORDER BY r.date_time DESC", 'i', [$driver['driver_id']]);
}

$totalPoints = 0;
$totalCo2 = 0;
foreach ($rides as &$ride) {
    $ride['done'] = is_past($ride['date_time']);
    $pax = (int)$ride['pax_count'];
    // Drivers earn eco points only for completed rides that carried passengers.
    $ride['points'] = ($ride['done'] && $pax > 0) ? 10 + $pax * 10 : 0;
    $ride['co2'] = $ride['done'] ? co2_saved($ride['distance_km'], $pax) : 0;
    $totalPoints += $ride['points'];
    $totalCo2 += $ride['co2'];
}
unset($ride);

function ride_status_pill($ride)
{
    if (!$ride['done']) {
        return pill('Upcoming', 'blue');
    }
    return $ride['pax_count'] > 0 ? pill('Completed', 'green') : pill('No passengers', 'gray');
}

page_header('Ride History', ['fill' => true, 'role' => $isAdmin ? 'admin' : 'driver']);
?>
<?php if ($isAdmin): ?>
        <?php page_title('Ride History', 'All rides offered by drivers, newest first.'); ?>
        <div class="glass-card">
<?php else: ?>
        <?php
        page_title('Eco Performance', 'Your impact from sharing rides. Each completed ride earns 10 eco points + 10 per passenger.');
        $level = eco_level($totalPoints);
        ?>
        <div class="ap-stats">
            <div class="ap-stat">
                <span class="ap-stat-label">Eco level</span>
                <span class="ap-stat-value text-green"><?= e($level['name']) ?></span>
                <div class="progress"><span style="width: <?= $level['progress'] ?>%"></span></div>
                <span class="ap-stat-note">
                    <?= $level['next'] ? number_format($level['remaining']) . ' pts to ' . e($level['next']) : "Top level reached 🌿" ?>
                </span>
            </div>
            <div class="ap-stat">
                <span class="ap-stat-label">Eco points</span>
                <span class="ap-stat-value"><?= number_format($totalPoints) ?></span>
                <span class="ap-stat-note">from completed rides</span>
            </div>
            <div class="ap-stat">
                <span class="ap-stat-label">Carbon reduction</span>
                <span class="ap-stat-value text-blue"><?= number_format($totalCo2, 1) ?> kg</span>
                <span class="ap-stat-note">CO₂ saved</span>
            </div>
            <div class="ap-stat">
                <span class="ap-stat-label">Environmental impact</span>
                <span class="ap-stat-value text-amber"><?= number_format(trees_equivalent($totalCo2), 1) ?> trees</span>
                <span class="ap-stat-note">of yearly absorption</span>
            </div>
        </div>

        <div class="glass-card">
            <h2>Ride History</h2>
<?php endif; ?>

            <div class="table-wrap">
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <?php if ($isAdmin): ?><th>Driver</th><?php endif; ?>
                            <th>Route</th>
                            <th class="num">Passengers</th>
                            <?php if (!$isAdmin): ?><th class="num">Points</th><?php endif; ?>
                            <th class="num">CO₂ Saved</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rides as $ride): ?>
                        <tr>
                            <td class="nowrap"><?= fmt_datetime($ride['date_time']) ?></td>
                            <?php if ($isAdmin): ?>
                                <td><?= e($ride['driver_name'] ?: ($ride['driver_tp'] ?? 'Deleted driver')) ?></td>
                            <?php endif; ?>
                            <td><?= route_html($ride['destination']) ?></td>
                            <td class="num"><?= (int)$ride['pax_count'] ?></td>
                            <?php if (!$isAdmin): ?>
                                <td class="num text-green"><?= $ride['points'] ? '+' . $ride['points'] : '—' ?></td>
                            <?php endif; ?>
                            <td class="num"><?= $ride['done'] ? number_format($ride['co2'], 1) . ' kg' : '—' ?></td>
                            <td><?= ride_status_pill($ride) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rides): ?>
                        <tr><td colspan="6" class="empty-state">No rides yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php page_footer(); ?>
