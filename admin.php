<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
require_admin();

$now = now_sql();
$pendingDrivers = (int)db_value("SELECT COUNT(*) FROM driver WHERE status <> 'Approved' AND status <> 'Rejected'");
$customers = (int)db_value("SELECT COUNT(*) FROM customer");
$approvedDrivers = (int)db_value("SELECT COUNT(*) FROM driver WHERE status = 'Approved'");
$upcomingRides = (int)db_value("SELECT COUNT(*) FROM ride WHERE date_time > ?", 's', [$now]);
$revenue = (float)db_value(
    "SELECT COALESCE(SUM(r.price), 0) FROM booking b JOIN ride r ON b.ride_id = r.ride_id WHERE b.status IN (" . PAID_STATUSES . ")"
);
$passengerKm = (float)db_value(
    "SELECT COALESCE(SUM(r.distance_km), 0) FROM booking b JOIN ride r ON b.ride_id = r.ride_id
     WHERE b.status IN (" . PAID_STATUSES . ") AND r.date_time <= ?",
    's',
    [$now]
);

page_header('Admin Dashboard', ['role' => 'admin', 'fill' => true]);
?>
        <?php page_title('Welcome, Administrator', 'Select a report or management task to view.'); ?>

        <div class="tile-grid">
            <?php
            tile('admin_approval.php', '📩', 'Driver Requests', 'Approve or reject new driver sign-ups.', (string)$pendingDrivers, 'waiting for review');
            tile('customer_list.php', '👥', 'Customers', 'Manage registered passengers.', (string)$customers, 'registered');
            tile('view_drivers.php', '🚙', 'Drivers', 'View and manage approved drivers.', (string)$approvedDrivers, 'approved');
            tile('ride_history.php', '🚗', 'Ride History', 'Monitor trips and usage across all drivers.', (string)$upcomingRides, 'upcoming rides');
            tile('transaction_report.php', '💰', 'Bookings & Payments', 'Review revenue and transactions.', money($revenue), 'total revenue');
            tile('co2_report.php', '📊', 'CO₂ Report', 'See the emissions saved by carpooling.', number_format(co2_saved($passengerKm), 1) . ' kg', 'CO₂ saved');
            ?>
        </div>
<?php page_footer(); ?>
