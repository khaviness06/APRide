<?php
require_once 'includes/bootstrap.php';
require_admin_api();

$userId = (int)($_POST['user_id'] ?? 0);
$driver = db_one("SELECT driver_id FROM driver WHERE user_id = ?", 'i', [$userId]);
if (!$driver) {
    json_reply(['ok' => false, 'error' => 'Driver not found. It may already have been deleted.'], 404);
}
if (db_one("SELECT admin_id FROM admin WHERE user_id = ?", 'i', [$userId])) {
    json_reply(['ok' => false, 'error' => 'Admin accounts cannot be deleted here.'], 400);
}

$driverId = (int)$driver['driver_id'];
$refunded = db_transaction(function () use ($driverId, $userId) {
    $refunded = 0;
    $rideIds = array_column(db_all("SELECT ride_id FROM ride WHERE driver_id = ?", 'i', [$driverId]), 'ride_id');
    foreach ($rideIds as $rideId) {
        // Passengers on upcoming rides get their money back before the rides are removed.
        $refunded += cancel_ride_bookings($rideId);
        db_exec("DELETE FROM points WHERE ride_id = ?", 'i', [$rideId]);
        db_exec("DELETE FROM booking WHERE ride_id = ?", 'i', [$rideId]);
    }
    db_exec("DELETE FROM ride WHERE driver_id = ?", 'i', [$driverId]);
    db_exec("DELETE FROM driver WHERE driver_id = ?", 'i', [$driverId]);
    db_exec("DELETE FROM user WHERE user_id = ?", 'i', [$userId]);
    return $refunded;
});

json_reply(['ok' => true, 'refunded' => $refunded]);
