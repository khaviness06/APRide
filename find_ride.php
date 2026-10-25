<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$customer = require_customer();

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$date = trim($_GET['date'] ?? '');
if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = '';
}
$searched = $from !== '' || $to !== '' || $date !== '';

function like_param($text)
{
    return '%' . addcslashes($text, '%_\\') . '%';
}

// Only upcoming rides with free seats from approved drivers.
$sql = "SELECT r.ride_id, r.destination, r.date_time, r.available_seats, r.price, r.distance_km,
               d.vehicle_type, u.name AS driver_name, u.TP_Number AS driver_tp,
               (SELECT b.status FROM booking b
                 WHERE b.ride_id = r.ride_id AND b.customer_id = ? AND b.status IN ('Pending', " . PAID_STATUSES . ")
                 LIMIT 1) AS my_status
        FROM ride r
        JOIN driver d ON r.driver_id = d.driver_id
        JOIN user u ON d.user_id = u.user_id
        WHERE d.status = 'Approved' AND r.available_seats > 0 AND r.date_time > ?";
$types = 'is';
$params = [$customer['customer_id'], now_sql()];

if ($from !== '') {
    // Pick-up is the part before the arrow.
    $sql .= " AND SUBSTRING_INDEX(r.destination, '→', 1) LIKE ?";
    $types .= 's';
    $params[] = like_param($from);
}
if ($to !== '') {
    // Drop-off is the part after the arrow (or the whole text for older rides).
    $sql .= " AND SUBSTRING_INDEX(r.destination, '→', -1) LIKE ?";
    $types .= 's';
    $params[] = like_param($to);
}
if ($date !== '') {
    $sql .= " AND DATE(r.date_time) = ?";
    $types .= 's';
    $params[] = $date;
}
$sql .= " ORDER BY r.date_time ASC";
$rides = db_all($sql, $types, $params);

page_header('Find Ride', ['role' => 'customer', 'fill' => true]);
?>
        <?php page_title('Where to?', 'Search upcoming rides. Leave fields empty to see everything available.'); ?>

        <div class="split-1-2 grow-row">
        <div class="glass-card">
            <h2>Search</h2>
            <form method="GET">
                <label class="ap-label" for="from">From</label>
                <input class="ap-input" type="text" id="from" name="from" list="locations" placeholder="Any pick-up point" value="<?= e($from) ?>">

                <label class="ap-label" for="to">To</label>
                <input class="ap-input" type="text" id="to" name="to" list="locations" placeholder="Any destination" value="<?= e($to) ?>">

                <datalist id="locations">
                    <?php foreach (array_keys(LOCATIONS) as $place): ?>
                        <option value="<?= e($place) ?>">
                    <?php endforeach; ?>
                </datalist>

                <label class="ap-label" for="date">Date</label>
                <input class="ap-input" type="date" id="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= e($date) ?>">

                <div class="form-actions">
                    <?php if ($searched): ?><a href="find_ride.php" class="btn btn-secondary">Clear</a><?php endif; ?>
                    <button type="submit" class="btn btn-primary">Search rides</button>
                </div>
            </form>

            <hr class="divider">
            <p class="sub-label" style="margin:0 0 10px">Popular destinations</p>
            <div class="quick-links">
                <?php foreach (array_keys(LOCATIONS) as $place): ?>
                    <a href="find_ride.php?to=<?= urlencode($place) ?>" class="chip<?= $to === $place ? ' active' : '' ?>"><?= e($place) ?></a>
                <?php endforeach; ?>
            </div>
            <p class="sub-label" style="margin:18px 0 10px">When</p>
            <div class="quick-links">
                <a href="find_ride.php?date=<?= date('Y-m-d') ?>" class="chip<?= $date === date('Y-m-d') ? ' active' : '' ?>">Today</a>
                <a href="find_ride.php?date=<?= date('Y-m-d', strtotime('+1 day')) ?>" class="chip<?= $date === date('Y-m-d', strtotime('+1 day')) ? ' active' : '' ?>">Tomorrow</a>
            </div>
        </div>

        <div class="glass-card">
            <div class="section-title">
                <h2><?= $searched ? 'Matching rides' : 'Available rides' ?></h2>
                <span class="ap-muted"><?= count($rides) ?> found</span>
            </div>

            <?php foreach ($rides as $r): ?>
                <div class="ride-card">
                    <div>
                        <div class="ride-route"><?= route_html($r['destination']) ?></div>
                        <div class="ride-meta">
                            <span>🗓 <?= fmt_datetime($r['date_time']) ?></span>
                            <span>🚗 <?= e($r['driver_name'] ?: $r['driver_tp']) ?> · <?= e($r['vehicle_type']) ?></span>
                            <span>💺 <?= (int)$r['available_seats'] ?> seat<?= $r['available_seats'] == 1 ? '' : 's' ?> left</span>
                            <span>📏 <?= number_format($r['distance_km'], 1) ?> km</span>
                        </div>
                    </div>
                    <div class="ride-side">
                        <span class="ride-price"><?= money($r['price']) ?></span>
                        <?php if ($r['my_status']): ?>
                            <?= booking_pill($r['my_status'], $r['date_time']) ?>
                        <?php else: ?>
                            <a href="ride_details.php?ride_id=<?= (int)$r['ride_id'] ?>" class="btn btn-primary btn-sm">View ride</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$rides): ?>
                <p class="empty-state">
                    <?= $searched ? 'No rides match your search. Try a different date or location.' : 'No upcoming rides right now. Check back soon!' ?>
                </p>
            <?php endif; ?>
        </div>
        </div>
<?php page_footer(); ?>
