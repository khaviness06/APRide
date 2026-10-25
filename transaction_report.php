<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
require_admin();

$bookings = db_all(
    "SELECT b.booking_id, b.status, r.destination, r.date_time, r.price,
            cu.TP_Number AS customer_tp, cu.name AS customer_name,
            du.TP_Number AS driver_tp, du.name AS driver_name
     FROM booking b
     JOIN ride r ON b.ride_id = r.ride_id
     LEFT JOIN customer c ON b.customer_id = c.customer_id
     LEFT JOIN user cu ON c.user_id = cu.user_id
     LEFT JOIN driver d ON r.driver_id = d.driver_id
     LEFT JOIN user du ON d.user_id = du.user_id
     ORDER BY r.date_time DESC, b.booking_id DESC"
);

$revenue = 0;
$paidCount = 0;
$pendingCount = 0;
foreach ($bookings as $b) {
    if (in_array($b['status'], ['Confirmed', 'Completed'], true)) {
        $revenue += $b['price'];
        $paidCount++;
    } elseif ($b['status'] === 'Pending') {
        $pendingCount++;
    }
}

function payment_pill($status)
{
    switch ($status) {
        case 'Confirmed':
        case 'Completed':
            return pill('Paid', 'green');
        case 'Pending':
            return pill('Awaiting driver', 'amber');
        case 'Cancelled':
            return pill('Cancelled', 'gray');
        case 'Rejected':
            return pill('Declined · not charged', 'red');
        default:
            return pill($status, 'gray');
    }
}

function person($name, $tp)
{
    if ($tp === null) {
        return '<span class="ap-muted">Deleted account</span>';
    }
    return '<strong>' . e($name !== '' && $name !== null ? $name : $tp) . '</strong><div class="ap-muted small">' . e($tp) . '</div>';
}

page_header('Bookings & Payments', ['fill' => true, 'role' => 'admin']);
?>
        <?php page_title('Bookings & Payments', 'Every booking request and what was charged.'); ?>

            <div class="ap-stats cols-3">
                <div class="ap-stat">
                    <span class="ap-stat-label">Total revenue</span>
                    <span class="ap-stat-value text-green"><?= money($revenue) ?></span>
                </div>
                <div class="ap-stat">
                    <span class="ap-stat-label">Paid bookings</span>
                    <span class="ap-stat-value"><?= $paidCount ?></span>
                </div>
                <div class="ap-stat">
                    <span class="ap-stat-label">Awaiting driver</span>
                    <span class="ap-stat-value <?= $pendingCount ? 'text-amber' : '' ?>"><?= $pendingCount ?></span>
                </div>
            </div>

        <div class="glass-card">

            <div class="table-wrap">
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Passenger</th>
                            <th>Driver</th>
                            <th>Route</th>
                            <th>Ride date</th>
                            <th class="num">Amount</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td class="nowrap">#BKN-<?= (int)$b['booking_id'] ?></td>
                            <td><?= person($b['customer_name'], $b['customer_tp']) ?></td>
                            <td><?= person($b['driver_name'], $b['driver_tp']) ?></td>
                            <td><?= route_html($b['destination']) ?></td>
                            <td class="nowrap"><?= fmt_datetime($b['date_time']) ?></td>
                            <td class="num"><?= money($b['price']) ?></td>
                            <td><?= payment_pill($b['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$bookings): ?>
                        <tr><td colspan="7" class="empty-state">No bookings yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php page_footer(); ?>
