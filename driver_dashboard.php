<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$driver = require_driver();
$driverId = (int)$driver['driver_id'];
$now = now_sql();

// One row per ride with its paying passengers, so distances are not double-counted.
$rides = db_all(
    "SELECT r.ride_id, r.distance_km, r.date_time, r.price,
            (SELECT COUNT(*) FROM booking b WHERE b.ride_id = r.ride_id AND b.status IN (" . PAID_STATUSES . ")) AS pax
     FROM ride r WHERE r.driver_id = ?",
    'i',
    [$driverId]
);

$completed = 0;
$passengers = 0;
$sharedKm = 0;
$co2 = 0;
$upcoming = 0;
$ecoPoints = 0;
$earned = 0;
foreach ($rides as $r) {
    $earned += $r['price'] * $r['pax'];
    if (!is_past($r['date_time'])) {
        $upcoming++;
        continue;
    }
    $completed++;
    $passengers += $r['pax'];
    if ($r['pax'] > 0) {
        $sharedKm += $r['distance_km'];
        $ecoPoints += 10 + $r['pax'] * 10;
    }
    $co2 += co2_saved($r['distance_km'], $r['pax']);
}

$pendingRequests = (int)db_value(
    "SELECT COUNT(*) FROM booking b JOIN ride r ON b.ride_id = r.ride_id
     WHERE r.driver_id = ? AND b.status = 'Pending' AND r.date_time > ?",
    'is',
    [$driverId, $now]
);

$firstName = $driver['name'] !== '' ? explode(' ', $driver['name'])[0] : $driver['tp_number'];

page_header('Driver Dashboard', ['role' => 'driver', 'fill' => true]);
?>
        <?php if ($pendingRequests): ?>
            <div class="ap-alert ap-alert-warning">
                You have <?= $pendingRequests ?> passenger request<?= $pendingRequests > 1 ? 's' : '' ?> waiting. <a href="my_rides.php">Review now →</a>
            </div>
        <?php endif; ?>

        <?php page_title('Hi, ' . $firstName . ' 👋', 'An at-a-glance overview of your trips, passengers and CO₂ savings.'); ?>

        <div class="ap-stats">
            <div class="ap-stat">
                <span class="ap-stat-label">Completed rides</span>
                <span class="ap-stat-value"><?= $completed ?></span>
                <span class="ap-stat-note"><?= $upcoming ?> upcoming</span>
            </div>
            <div class="ap-stat">
                <span class="ap-stat-label">Passengers carried</span>
                <span class="ap-stat-value"><?= $passengers ?></span>
                <span class="ap-stat-note">on completed rides</span>
            </div>
            <div class="ap-stat">
                <span class="ap-stat-label">Shared distance</span>
                <span class="ap-stat-value"><?= number_format($sharedKm, 1) ?> km</span>
                <span class="ap-stat-note">with passengers on board</span>
            </div>
            <div class="ap-stat">
                <span class="ap-stat-label">CO₂ saved</span>
                <span class="ap-stat-value text-green"><?= number_format($co2, 1) ?> kg</span>
                <span class="ap-stat-note"><?= number_format(trees_equivalent($co2), 1) ?> trees' yearly absorption</span>
            </div>
        </div>

        <div class="tile-grid">
            <?php
            tile('offer_ride.php', '🚗', 'Offer New Ride', 'Publish a new trip for students to join. Pick your route, time, seats and price.', null, '', 'tile-feature');
            tile('my_rides.php', '📋', 'My Rides', 'Accept passengers and manage upcoming trips.',
                $pendingRequests ? $pendingRequests . ' new' : (string)$upcoming, $pendingRequests ? 'requests waiting' : 'upcoming rides');
            tile('ride_history.php', '🌱', 'Ride History', 'Review completed journeys and eco stats.', eco_level($ecoPoints)['name'], number_format($ecoPoints) . ' eco points');
            tile('earnings.php', '💰', 'Earnings', "See how much you've earned from shared rides.", money($earned), 'total earned');
            tile('profile.php', '👤', 'Profile', 'View your driver and vehicle details.', $driver['license_plate'], $driver['vehicle_type']);
            ?>
        </div>
<?php page_footer(); ?>
