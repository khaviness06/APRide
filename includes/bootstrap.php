<?php
// Shared setup for every APRide page: session, database, auth, CSRF and small helpers.

date_default_timezone_set('Asia/Kuala_Lumpur');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../db.php';

// ---------------------------------------------------------------------------
// Business rules (one place, so every page shows the same numbers)
// ---------------------------------------------------------------------------

const CO2_KG_PER_PASSENGER_KM = 0.2;  // CO2 saved per passenger for every km shared
const TREE_KG_PER_YEAR = 21;          // CO2 one mature tree absorbs in a year
const POINTS_PER_RIDE = 50;           // points a passenger earns per accepted ride
const PAID_STATUSES = "'Confirmed','Completed'"; // booking statuses that mean "accepted and paid"
const TOPUP_MIN = 1;
const TOPUP_MAX = 500;

// Fixed pick-up / drop-off points offered to drivers, with approximate map coordinates.
const LOCATIONS = [
    'APU Main Campus'          => [3.0553, 101.7006],
    'LRT Bukit Jalil'          => [3.0582, 101.6921],
    'Technology Park Malaysia' => [3.0499, 101.6942],
    'Pavilion Bukit Jalil'     => [3.0540, 101.6737],
    'KL Sentral'               => [3.1340, 101.6865],
    'Mid Valley Megamall'      => [3.1176, 101.6776],
];

// Most passengers each vehicle type can take.
const VEHICLE_SEATS = ['Sedan' => 4, 'Hatchback' => 4, 'SUV' => 6, 'MPV' => 7];

// ---------------------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------------------

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($amount)
{
    return 'RM ' . number_format((float)$amount, 2);
}

function fmt_datetime($dateTime)
{
    return date('d M Y, h:i A', strtotime($dateTime));
}

function now_sql()
{
    return date('Y-m-d H:i:s');
}

function is_past($dateTime)
{
    return strtotime($dateTime) <= time();
}

function co2_saved($km, $passengers = 1)
{
    return (float)$km * CO2_KG_PER_PASSENGER_KM * (int)$passengers;
}

function trees_equivalent($co2Kg)
{
    return $co2Kg / TREE_KG_PER_YEAR;
}

// Rides are stored as "Pick-up → Drop-off"; older rides only have a destination.
function split_route($destination)
{
    $parts = explode('→', (string)$destination, 2);
    if (count($parts) === 2) {
        return ['from' => trim($parts[0]), 'to' => trim($parts[1])];
    }
    return ['from' => null, 'to' => trim($parts[0])];
}

function route_html($destination)
{
    $r = split_route($destination);
    if ($r['from'] === null) {
        return e($r['to']);
    }
    return e($r['from']) . ' <span class="route-arrow" aria-label="to">→</span> ' . e($r['to']);
}

function pill($label, $tone)
{
    return '<span class="ap-pill ap-pill-' . e($tone) . '">' . e($label) . '</span>';
}

// Status shown to people for a booking, based on its status and whether the ride has happened.
function booking_pill($status, $rideDateTime)
{
    switch ($status) {
        case 'Pending':
            return pill('Awaiting driver', 'amber');
        case 'Confirmed':
        case 'Completed':
            return is_past($rideDateTime) ? pill('Completed', 'green') : pill('Confirmed', 'blue');
        case 'Rejected':
            return pill('Declined', 'red');
        case 'Cancelled':
            return pill('Cancelled', 'gray');
        default:
            return pill($status, 'gray');
    }
}

// Eco levels for drivers, based on eco points.
function eco_level($points)
{
    $levels = [[0, 'Eco-Starter'], [200, 'Eco-Explorer'], [500, 'Eco-Warrior'], [1000, 'Eco-Legend']];
    $current = $levels[0];
    $next = null;
    foreach ($levels as $i => $level) {
        if ($points >= $level[0]) {
            $current = $level;
            $next = $levels[$i + 1] ?? null;
        }
    }
    $progress = 100;
    if ($next) {
        $progress = ($points - $current[0]) / ($next[0] - $current[0]) * 100;
    }
    return [
        'name'      => $current[1],
        'next'      => $next ? $next[1] : null,
        'next_at'   => $next ? $next[0] : null,
        'remaining' => $next ? $next[0] - $points : 0,
        'progress'  => max(0, min(100, $progress)),
    ];
}

// ---------------------------------------------------------------------------
// Database helpers (always prepared statements)
// ---------------------------------------------------------------------------

function db_run($sql, $types = '', array $params = [])
{
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function db_all($sql, $types = '', array $params = [])
{
    $result = db_run($sql, $types, $params)->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function db_one($sql, $types = '', array $params = [])
{
    $rows = db_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function db_value($sql, $types = '', array $params = [])
{
    $row = db_one($sql, $types, $params);
    return $row ? reset($row) : null;
}

// Runs an INSERT/UPDATE/DELETE and returns the number of affected rows.
function db_exec($sql, $types = '', array $params = [])
{
    return db_run($sql, $types, $params)->affected_rows;
}

// A problem whose message is safe to show to the user (unlike raw database errors).
class UserFacingError extends Exception
{
}

// Runs $work inside a transaction, rolling back if anything throws.
function db_transaction(callable $work)
{
    global $conn;
    $conn->begin_transaction();
    try {
        $result = $work();
        $conn->commit();
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

// ---------------------------------------------------------------------------
// Flash messages and redirects
// ---------------------------------------------------------------------------

function redirect($url)
{
    header('Location: ' . $url);
    exit();
}

function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes()
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---------------------------------------------------------------------------
// CSRF protection for every form and AJAX request that changes data
// ---------------------------------------------------------------------------

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid()
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function csrf_check()
{
    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'login.php');
    }
}

// ---------------------------------------------------------------------------
// Authentication and roles
// ---------------------------------------------------------------------------

function current_user_id()
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

// The role of the logged-in user, worked out from the admin/driver/customer tables.
function current_role()
{
    static $cache = [];
    $uid = current_user_id();
    if (!$uid) {
        return null;
    }
    if (!array_key_exists($uid, $cache)) {
        $cache[$uid] = role_for_user($uid);
    }
    return $cache[$uid];
}

function role_for_user($uid)
{
    if (db_one("SELECT admin_id FROM admin WHERE user_id = ?", 'i', [$uid])) {
        return 'admin';
    }
    if (db_one("SELECT driver_id FROM driver WHERE user_id = ?", 'i', [$uid])) {
        return 'driver';
    }
    if (db_one("SELECT customer_id FROM customer WHERE user_id = ?", 'i', [$uid])) {
        return 'customer';
    }
    return null;
}

function home_for_role($role)
{
    switch ($role) {
        case 'admin':
            return 'admin.php';
        case 'driver':
            return 'driver_dashboard.php';
        case 'customer':
            return 'customer.php';
        default:
            return 'login.php';
    }
}

function end_session_with($type, $message)
{
    $_SESSION = [];
    session_regenerate_id(true);
    flash($type, $message);
    redirect('login.php');
}

// Sends visitors who are not logged in as $role to the right place.
function require_role($role)
{
    if (!current_user_id()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
    $actual = current_role();
    if ($actual === null) {
        end_session_with('error', 'Your account no longer has access. Please contact the admin.');
    }
    if ($actual !== $role) {
        flash('error', "That page isn't available for your account.");
        redirect(home_for_role($actual));
    }
}

function require_admin()
{
    require_role('admin');
}

// Returns the logged-in customer's profile.
function require_customer()
{
    require_role('customer');
    return db_one(
        "SELECT c.customer_id, c.wallet, c.points_balance, c.membership_status,
                u.user_id, u.TP_Number AS tp_number, u.name, u.e_mail
         FROM customer c JOIN user u ON u.user_id = c.user_id
         WHERE c.user_id = ?",
        'i',
        [current_user_id()]
    );
}

// Returns the logged-in driver's profile. Drivers who are not approved are logged out.
function require_driver()
{
    require_role('driver');
    $driver = db_one(
        "SELECT d.driver_id, d.vehicle_type, d.license_plate, d.max_passengers, d.status,
                u.user_id, u.TP_Number AS tp_number, u.name, u.e_mail
         FROM driver d JOIN user u ON u.user_id = d.user_id
         WHERE d.user_id = ?",
        'i',
        [current_user_id()]
    );
    if (strcasecmp($driver['status'], 'Approved') !== 0) {
        end_session_with('error', 'Your driver account is not approved. Please wait for an admin to review it.');
    }
    return $driver;
}

// For AJAX endpoints: answers with JSON instead of redirecting.
function require_admin_api()
{
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit(json_encode(['ok' => false, 'error' => 'Method not allowed']));
    }
    if (!current_user_id() || current_role() !== 'admin') {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Admins only. Please log in again.']));
    }
    if (!csrf_valid()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Session expired. Please refresh the page.']));
    }
}

function json_reply(array $data, $status = 200)
{
    http_response_code($status);
    exit(json_encode($data));
}

// ---------------------------------------------------------------------------
// Booking rules shared by drivers and admins
// ---------------------------------------------------------------------------

// Loads a ride for a customer. Returns [ride, reason it can't be booked (null when it can)].
function bookable_ride($rideId, $customerId)
{
    $ride = db_one(
        "SELECT r.ride_id, r.destination, r.date_time, r.available_seats, r.price, r.distance_km,
                d.vehicle_type, d.license_plate, u.name AS driver_name, u.TP_Number AS driver_tp,
                (SELECT b.status FROM booking b
                  WHERE b.ride_id = r.ride_id AND b.customer_id = ? AND b.status IN ('Pending', " . PAID_STATUSES . ")
                  LIMIT 1) AS my_status
         FROM ride r
         JOIN driver d ON r.driver_id = d.driver_id
         JOIN user u ON d.user_id = u.user_id
         WHERE r.ride_id = ? AND d.status = 'Approved'",
        'ii',
        [$customerId, $rideId]
    );
    if (!$ride) {
        return [null, 'This ride is no longer available.'];
    }
    if (is_past($ride['date_time'])) {
        return [$ride, 'This ride has already departed.'];
    }
    if ($ride['my_status'] === 'Pending') {
        return [$ride, "You've already requested this ride. The driver will respond soon."];
    }
    if ($ride['my_status']) {
        return [$ride, "You're already booked on this ride."];
    }
    if ((int)$ride['available_seats'] <= 0) {
        return [$ride, 'This ride is full.'];
    }
    return [$ride, null];
}

// Cancels the active bookings on an upcoming ride: paid passengers get their fare back
// and lose the points that ride earned them. Past rides are left untouched.
function cancel_ride_bookings($rideId)
{
    $ride = db_one("SELECT price, date_time FROM ride WHERE ride_id = ?", 'i', [$rideId]);
    if (!$ride || is_past($ride['date_time'])) {
        return 0;
    }
    $paid = db_all(
        "SELECT booking_id, customer_id FROM booking WHERE ride_id = ? AND status IN (" . PAID_STATUSES . ")",
        'i',
        [$rideId]
    );
    foreach ($paid as $booking) {
        refund_booking($booking['customer_id'], $rideId, $ride['price']);
    }
    db_exec(
        "UPDATE booking SET status = 'Cancelled' WHERE ride_id = ? AND status IN ('Pending', " . PAID_STATUSES . ")",
        'i',
        [$rideId]
    );
    return count($paid);
}

function refund_booking($customerId, $rideId, $price)
{
    $earned = (int)db_value(
        "SELECT COALESCE(SUM(points_earned), 0) FROM points WHERE customer_id = ? AND ride_id = ?",
        'ii',
        [$customerId, $rideId]
    );
    db_exec(
        "UPDATE customer SET wallet = wallet + ?, points_balance = GREATEST(points_balance - ?, 0) WHERE customer_id = ?",
        'dii',
        [$price, $earned, $customerId]
    );
    db_exec("DELETE FROM points WHERE customer_id = ? AND ride_id = ?", 'ii', [$customerId, $rideId]);
}
