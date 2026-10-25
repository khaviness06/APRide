<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$driver = require_driver();

$displayName = $driver['name'] !== '' ? $driver['name'] : $driver['tp_number'];
$initials = '';
foreach (array_slice(preg_split('/\s+/', trim($displayName)), 0, 2) as $word) {
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}

// Activity summary: completed rides, passengers and earnings from accepted bookings.
$activity = db_one(
    "SELECT COUNT(DISTINCT CASE WHEN r.date_time <= ? THEN r.ride_id END) AS completed,
            COUNT(CASE WHEN r.date_time <= ? THEN b.booking_id END) AS passengers,
            COALESCE(SUM(CASE WHEN b.booking_id IS NOT NULL THEN r.price END), 0) AS earned,
            COUNT(DISTINCT CASE WHEN r.date_time > ? THEN r.ride_id END) AS upcoming
     FROM ride r
     LEFT JOIN booking b ON b.ride_id = r.ride_id AND b.status IN (" . PAID_STATUSES . ")
     WHERE r.driver_id = ?",
    'sssi',
    [now_sql(), now_sql(), now_sql(), $driver['driver_id']]
);

page_header('Profile', ['role' => 'driver', 'fill' => true]);
?>
        <?php page_title('Driver Profile', 'Contact the admin if any of these details need to change.'); ?>

        <div class="split-1-2 grow-row">
            <div class="glass-card identity">
                <div class="avatar" aria-hidden="true"><?= e($initials) ?></div>
                <h2><?= e($displayName) ?></h2>
                <span class="ap-muted"><?= e($driver['tp_number']) ?></span>
                <?= pill('Approved driver', 'green') ?>
            </div>

            <div class="glass-card">
                <h2>Account</h2>
                <dl class="kv">
                    <div><dt>Name</dt><dd><?= e($driver['name'] !== '' ? $driver['name'] : '—') ?></dd></div>
                    <div><dt>TP Number</dt><dd><?= e($driver['tp_number']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($driver['e_mail']) ?></dd></div>
                </dl>
                <h2 style="margin-top:22px">Vehicle</h2>
                <dl class="kv">
                    <div><dt>Vehicle type</dt><dd><?= e($driver['vehicle_type']) ?></dd></div>
                    <div><dt>License plate</dt><dd><?= e($driver['license_plate']) ?></dd></div>
                    <div><dt>Max passengers</dt><dd><?= (int)$driver['max_passengers'] ?></dd></div>
                </dl>
                <h2 style="margin-top:22px">Activity</h2>
                <div class="ap-stats mb-0">
                    <div class="ap-stat">
                        <span class="ap-stat-label">Completed rides</span>
                        <span class="ap-stat-value"><?= (int)$activity['completed'] ?></span>
                    </div>
                    <div class="ap-stat">
                        <span class="ap-stat-label">Upcoming rides</span>
                        <span class="ap-stat-value"><?= (int)$activity['upcoming'] ?></span>
                    </div>
                    <div class="ap-stat">
                        <span class="ap-stat-label">Passengers carried</span>
                        <span class="ap-stat-value"><?= (int)$activity['passengers'] ?></span>
                    </div>
                    <div class="ap-stat">
                        <span class="ap-stat-label">Total earned</span>
                        <span class="ap-stat-value text-green"><?= money($activity['earned']) ?></span>
                    </div>
                </div>
            </div>
        </div>
<?php page_footer(); ?>
