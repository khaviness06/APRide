<?php
require_once 'includes/bootstrap.php';
require_admin_api();

$driverId = (int)($_POST['driver_id'] ?? 0);
$status = (string)($_POST['status'] ?? '');

if ($driverId <= 0 || !in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
    json_reply(['ok' => false, 'error' => 'Invalid request.'], 400);
}

$driver = db_one("SELECT driver_id FROM driver WHERE driver_id = ?", 'i', [$driverId]);
if (!$driver) {
    json_reply(['ok' => false, 'error' => 'Driver not found. It may have been deleted.'], 404);
}

db_exec("UPDATE driver SET status = ? WHERE driver_id = ?", 'si', [$status, $driverId]);
json_reply(['ok' => true, 'status' => $status]);
