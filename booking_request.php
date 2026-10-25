<?php
require_once 'includes/bootstrap.php';
$customer = require_customer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('find_ride.php');
}
csrf_check();

$rideId = (int)($_POST['ride_id'] ?? 0);
[$ride, $problem] = bookable_ride($rideId, $customer['customer_id']);
if ($problem) {
    flash('error', $problem);
    redirect($ride ? 'ride_details.php?ride_id=' . $rideId : 'find_ride.php');
}
if ((float)$customer['wallet'] < (float)$ride['price']) {
    flash('error', 'Your wallet balance is too low for this ride. Please top up first.');
    redirect('payment.php?ride_id=' . $rideId);
}

// The driver must accept before the fare is charged (see manage_ride.php).
db_exec(
    "INSERT INTO booking (ride_id, customer_id, status) VALUES (?, ?, 'Pending')",
    'ii',
    [$rideId, $customer['customer_id']]
);

flash('success', 'Request sent! The driver will review it — you will only be charged once they accept.');
redirect('customer.php');
