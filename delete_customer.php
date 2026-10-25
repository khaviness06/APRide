<?php
require_once 'includes/bootstrap.php';
require_admin_api();

$userId = (int)($_POST['user_id'] ?? 0);
$customer = db_one("SELECT customer_id FROM customer WHERE user_id = ?", 'i', [$userId]);
if (!$customer) {
    json_reply(['ok' => false, 'error' => 'Customer not found. It may already have been deleted.'], 404);
}
if (db_one("SELECT admin_id FROM admin WHERE user_id = ?", 'i', [$userId])) {
    json_reply(['ok' => false, 'error' => 'Admin accounts cannot be deleted here.'], 400);
}

$customerId = (int)$customer['customer_id'];
db_transaction(function () use ($customerId, $userId) {
    // Give back the seats this customer held on upcoming rides.
    db_exec(
        "UPDATE ride r JOIN booking b ON b.ride_id = r.ride_id
         SET r.available_seats = r.available_seats + 1
         WHERE b.customer_id = ? AND b.status IN (" . PAID_STATUSES . ") AND r.date_time > ?",
        'is',
        [$customerId, now_sql()]
    );
    db_exec("DELETE FROM booking WHERE customer_id = ?", 'i', [$customerId]);
    db_exec("DELETE FROM points WHERE customer_id = ?", 'i', [$customerId]);
    db_exec("DELETE FROM redemption WHERE customer_id = ?", 'i', [$customerId]);
    db_exec("DELETE FROM customer WHERE customer_id = ?", 'i', [$customerId]);
    db_exec("DELETE FROM user WHERE user_id = ?", 'i', [$userId]);
});

json_reply(['ok' => true]);
