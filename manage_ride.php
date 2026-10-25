<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$driver = require_driver();
$driverId = (int)$driver['driver_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $rideId = (int)($_POST['ride_id'] ?? 0);

    try {
        if (isset($_POST['accept_booking'])) {
            $message = accept_booking($bookingId, $driverId);
        } elseif (isset($_POST['reject_booking'])) {
            $changed = db_exec(
                "UPDATE booking b JOIN ride r ON b.ride_id = r.ride_id
                 SET b.status = 'Rejected'
                 WHERE b.booking_id = ? AND r.driver_id = ? AND b.status = 'Pending'",
                'ii',
                [$bookingId, $driverId]
            );
            if (!$changed) {
                throw new UserFacingError('That request is no longer pending.');
            }
            $message = 'Request declined. The passenger was not charged.';
        } elseif (isset($_POST['delete_ride'])) {
            $message = delete_ride($rideId, $driverId);
        } else {
            throw new UserFacingError('Unknown action.');
        }
        flash('success', $message);
    } catch (UserFacingError $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('my_rides.php');
}

// Accepting charges the passenger, takes a seat and awards their points — all or nothing.
function accept_booking($bookingId, $driverId)
{
    return db_transaction(function () use ($bookingId, $driverId) {
        $b = db_one(
            "SELECT b.booking_id, b.customer_id, r.ride_id, r.price, r.date_time, c.wallet,
                    COALESCE(NULLIF(u.name, ''), u.TP_Number) AS passenger
             FROM booking b
             JOIN ride r ON b.ride_id = r.ride_id
             JOIN customer c ON b.customer_id = c.customer_id
             JOIN user u ON c.user_id = u.user_id
             WHERE b.booking_id = ? AND r.driver_id = ? AND b.status = 'Pending'
             FOR UPDATE",
            'ii',
            [$bookingId, $driverId]
        );
        if (!$b) {
            throw new UserFacingError('That request is no longer pending.');
        }
        if (is_past($b['date_time'])) {
            throw new UserFacingError('This ride has already departed.');
        }
        if ((float)$b['wallet'] < (float)$b['price']) {
            throw new UserFacingError($b['passenger'] . " doesn't have enough wallet balance yet. Ask them to top up, or decline the request.");
        }
        if (!db_exec("UPDATE ride SET available_seats = available_seats - 1 WHERE ride_id = ? AND available_seats > 0", 'i', [$b['ride_id']])) {
            throw new UserFacingError('This ride is already full.');
        }
        if (!db_exec("UPDATE customer SET wallet = wallet - ? WHERE customer_id = ? AND wallet >= ?", 'did', [$b['price'], $b['customer_id'], $b['price']])) {
            throw new UserFacingError($b['passenger'] . " doesn't have enough wallet balance.");
        }
        db_exec("UPDATE booking SET status = 'Confirmed' WHERE booking_id = ?", 'i', [$bookingId]);
        db_exec(
            "INSERT INTO points (customer_id, ride_id, points_earned, redeemed) VALUES (?, ?, ?, 0)",
            'iii',
            [$b['customer_id'], $b['ride_id'], POINTS_PER_RIDE]
        );
        db_exec("UPDATE customer SET points_balance = points_balance + ? WHERE customer_id = ?", 'ii', [POINTS_PER_RIDE, $b['customer_id']]);
        return 'Accepted ' . $b['passenger'] . '. ' . money($b['price']) . ' was charged to their wallet.';
    });
}

// Upcoming rides can always be cancelled (paid passengers are refunded).
// Past rides are kept as history once they carried passengers.
function delete_ride($rideId, $driverId)
{
    return db_transaction(function () use ($rideId, $driverId) {
        $ride = db_one("SELECT ride_id, date_time FROM ride WHERE ride_id = ? AND driver_id = ? FOR UPDATE", 'ii', [$rideId, $driverId]);
        if (!$ride) {
            throw new UserFacingError('Ride not found.');
        }
        $paid = (int)db_value("SELECT COUNT(*) FROM booking WHERE ride_id = ? AND status IN (" . PAID_STATUSES . ")", 'i', [$rideId]);
        if (is_past($ride['date_time']) && $paid > 0) {
            throw new UserFacingError('Completed rides with passengers stay in your history and cannot be deleted.');
        }
        $refunded = cancel_ride_bookings($rideId);
        db_exec("DELETE FROM points WHERE ride_id = ?", 'i', [$rideId]);
        db_exec("DELETE FROM booking WHERE ride_id = ?", 'i', [$rideId]);
        db_exec("DELETE FROM ride WHERE ride_id = ?", 'i', [$rideId]);
        return $refunded
            ? "Ride cancelled. $refunded passenger" . ($refunded > 1 ? 's were' : ' was') . ' refunded.'
            : 'Ride removed.';
    });
}

$rides = db_all("SELECT * FROM ride WHERE driver_id = ? ORDER BY date_time ASC", 'i', [$driverId]);
$bookingRows = db_all(
    "SELECT b.booking_id, b.ride_id, b.status, u.TP_Number AS tp_number, u.name, c.wallet
     FROM booking b
     JOIN ride r ON b.ride_id = r.ride_id
     JOIN customer c ON b.customer_id = c.customer_id
     JOIN user u ON c.user_id = u.user_id
     WHERE r.driver_id = ? AND b.status IN ('Pending', " . PAID_STATUSES . ")
     ORDER BY b.booking_id",
    'i',
    [$driverId]
);
$bookingsByRide = [];
foreach ($bookingRows as $b) {
    $bookingsByRide[$b['ride_id']][] = $b;
}

$upcoming = array_values(array_filter($rides, function ($r) { return !is_past($r['date_time']); }));
$past = array_reverse(array_values(array_filter($rides, function ($r) { return is_past($r['date_time']); })));

function hidden_fields($fields)
{
    $html = csrf_field();
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
    return $html;
}

page_header('My Rides', ['role' => 'driver', 'active' => 'my_rides.php', 'fill' => true]);
?>
        <?php page_title('My Rides', 'Accept passengers and manage your upcoming trips.',
            '<a href="offer_ride.php" class="btn btn-primary">+ Offer a ride</a>'); ?>

        <div class="split-2-1 grow-row">
        <div class="glass-card">
            <div class="section-title">
                <h2>Upcoming rides</h2>
                <span class="ap-muted"><?= count($upcoming) ?></span>
            </div>
            <?php foreach ($upcoming as $r):
                $bookings = $bookingsByRide[$r['ride_id']] ?? [];
                $confirmed = array_filter($bookings, function ($b) { return $b['status'] !== 'Pending'; });
                $pending = array_filter($bookings, function ($b) { return $b['status'] === 'Pending'; });
                $full = (int)$r['available_seats'] <= 0;
                ?>
                <div class="ride-block">
                    <div class="ride-block-head">
                        <div>
                            <div class="ride-route"><?= route_html($r['destination']) ?></div>
                            <div class="ride-meta">
                                <span>🗓 <?= fmt_datetime($r['date_time']) ?></span>
                                <span>📏 <?= number_format($r['distance_km'], 1) ?> km</span>
                                <span><?= $full ? pill('Full', 'gray') : pill((int)$r['available_seats'] . ' seat' . ($r['available_seats'] == 1 ? '' : 's') . ' left', 'blue') ?></span>
                            </div>
                        </div>
                        <div class="ride-side">
                            <span class="ride-price"><?= money($r['price']) ?></span>
                            <form method="POST" class="inline-form"
                                  onsubmit="return confirm('Cancel this ride?<?= $confirmed ? ' Confirmed passengers will be refunded.' : '' ?>');">
                                <?= hidden_fields(['ride_id' => $r['ride_id']]) ?>
                                <button type="submit" name="delete_ride" class="btn btn-danger btn-sm">Cancel ride</button>
                            </form>
                        </div>
                    </div>

                    <?php if ($confirmed): ?>
                        <p class="sub-label">Passengers (<?= count($confirmed) ?>)</p>
                        <?php foreach ($confirmed as $b): ?>
                            <div class="person-row">
                                <span><?= e($b['name'] ?: $b['tp_number']) ?> <span class="ap-muted">· <?= e($b['tp_number']) ?></span></span>
                                <?= pill('Confirmed', 'green') ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($pending): ?>
                        <p class="sub-label">Requests (<?= count($pending) ?>)</p>
                        <?php foreach ($pending as $b):
                            $lowBalance = (float)$b['wallet'] < (float)$r['price'];
                            ?>
                            <div class="person-row">
                                <span>
                                    <?= e($b['name'] ?: $b['tp_number']) ?> <span class="ap-muted">· <?= e($b['tp_number']) ?></span>
                                    <?php if ($lowBalance): ?><span class="ap-muted small text-amber" style="display:block">Wallet balance too low right now</span><?php endif; ?>
                                </span>
                                <div class="btn-row">
                                    <form method="POST" class="inline-form">
                                        <?= hidden_fields(['booking_id' => $b['booking_id']]) ?>
                                        <button type="submit" name="accept_booking" class="btn btn-primary btn-sm" <?= ($full || $lowBalance) ? 'disabled' : '' ?>>Accept</button>
                                    </form>
                                    <form method="POST" class="inline-form">
                                        <?= hidden_fields(['booking_id' => $b['booking_id']]) ?>
                                        <button type="submit" name="reject_booking" class="btn btn-secondary btn-sm">Decline</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!$bookings): ?>
                        <p class="ap-muted small mb-0" style="margin-top:12px">No requests yet.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?>
                <p class="empty-state">No upcoming rides. <a href="offer_ride.php" class="ap-link">Offer one now</a>.</p>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <div class="section-title">
                <h2>Past rides</h2>
                <a href="ride_history.php" class="ap-link small">Full history →</a>
            </div>
            <?php foreach ($past as $r):
                $paidCount = count(array_filter($bookingsByRide[$r['ride_id']] ?? [], function ($b) { return $b['status'] !== 'Pending'; }));
                ?>
                <div class="list-row">
                    <span style="min-width:0">
                        <span class="ride-route" style="font-size:14px"><?= route_html($r['destination']) ?></span>
                        <span class="ap-muted small" style="display:block"><?= fmt_datetime($r['date_time']) ?> · <?= $paidCount ?> passenger<?= $paidCount == 1 ? '' : 's' ?> · <?= money($r['price']) ?></span>
                    </span>
                    <?php if ($paidCount === 0): ?>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Remove this ride?');">
                            <?= hidden_fields(['ride_id' => $r['ride_id']]) ?>
                            <button type="submit" name="delete_ride" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    <?php else: ?>
                        <?= pill('Completed', 'green') ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$past): ?>
                <p class="empty-state">Your completed rides will appear here.</p>
            <?php endif; ?>
        </div>
        </div>
<?php page_footer(); ?>
