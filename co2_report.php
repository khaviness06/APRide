<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
require_admin();

// CO2 is counted for rides that have already happened, per paying passenger.
$rides = db_all(
    "SELECT r.ride_id, r.destination, r.distance_km, r.date_time,
            COUNT(b.booking_id) AS passenger_count,
            GROUP_CONCAT(COALESCE(NULLIF(u.name, ''), u.TP_Number, 'Deleted account') ORDER BY b.booking_id SEPARATOR ', ') AS passengers
     FROM ride r
     JOIN booking b ON r.ride_id = b.ride_id AND b.status IN (" . PAID_STATUSES . ")
     LEFT JOIN customer c ON b.customer_id = c.customer_id
     LEFT JOIN user u ON c.user_id = u.user_id
     WHERE r.date_time <= ?
     GROUP BY r.ride_id
     ORDER BY r.date_time DESC",
    's',
    [now_sql()]
);

$totalCo2 = 0;
$totalPassengerKm = 0;
foreach ($rides as &$r) {
    $r['co2'] = co2_saved($r['distance_km'], $r['passenger_count']);
    $totalCo2 += $r['co2'];
    $totalPassengerKm += $r['distance_km'] * $r['passenger_count'];
}
unset($r);

page_header('CO₂ Report', ['fill' => true, 'role' => 'admin']);
?>
        <?php page_title('CO₂ Impact Report', 'CO₂ saved on completed rides = distance × ' . CO2_KG_PER_PASSENGER_KM . ' kg/km × passengers.'); ?>

            <div class="ap-stats">
                <div class="ap-stat">
                    <span class="ap-stat-label">Total CO₂ saved</span>
                    <span class="ap-stat-value text-green"><?= number_format($totalCo2, 2) ?> kg</span>
                </div>
                <div class="ap-stat">
                    <span class="ap-stat-label">Completed shared rides</span>
                    <span class="ap-stat-value"><?= count($rides) ?></span>
                </div>
                <div class="ap-stat">
                    <span class="ap-stat-label">Passenger-km shared</span>
                    <span class="ap-stat-value"><?= number_format($totalPassengerKm, 1) ?></span>
                </div>
                <div class="ap-stat">
                    <span class="ap-stat-label">Trees equivalent</span>
                    <span class="ap-stat-value"><?= number_format(trees_equivalent($totalCo2), 1) ?></span>
                    <span class="ap-stat-note">yearly absorption of one tree ≈ <?= TREE_KG_PER_YEAR ?> kg</span>
                </div>
            </div>

        <div class="glass-card">

            <div class="table-wrap">
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Ride</th>
                            <th>Route</th>
                            <th>Date & Time</th>
                            <th class="num">Distance</th>
                            <th>Passengers</th>
                            <th class="num">CO₂ Saved</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rides as $r): ?>
                        <tr>
                            <td class="nowrap">#RIDE-<?= (int)$r['ride_id'] ?></td>
                            <td><?= route_html($r['destination']) ?></td>
                            <td class="nowrap"><?= fmt_datetime($r['date_time']) ?></td>
                            <td class="num"><?= number_format($r['distance_km'], 1) ?> km</td>
                            <td><?= e($r['passengers']) ?> <span class="ap-muted">(<?= (int)$r['passenger_count'] ?>)</span></td>
                            <td class="num text-green"><strong><?= number_format($r['co2'], 2) ?> kg</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rides): ?>
                        <tr><td colspan="6" class="empty-state">No completed rides with passengers yet. CO₂ is saved when passengers share rides.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php page_footer(); ?>
