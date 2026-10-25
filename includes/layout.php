<?php
// Shared page chrome: <head>, top navigation, flash messages and footer.

function nav_items($role)
{
    switch ($role) {
        case 'admin':
            return [
                'admin.php'              => 'Dashboard',
                'admin_approval.php'     => 'Requests',
                'customer_list.php'      => 'Customers',
                'view_drivers.php'       => 'Drivers',
                'ride_history.php'       => 'Ride History',
                'transaction_report.php' => 'Bookings & Payments',
                'co2_report.php'         => 'CO₂ Report',
            ];
        case 'driver':
            return [
                'driver_dashboard.php' => 'Dashboard',
                'offer_ride.php'       => 'Offer Ride',
                'my_rides.php'         => 'My Rides',
                'ride_history.php'     => 'Ride History',
                'earnings.php'         => 'Earnings',
                'profile.php'          => 'Profile',
            ];
        case 'customer':
            return [
                'customer.php'  => 'Home',
                'find_ride.php' => 'Find Ride',
                'topup.php'     => 'Top-up Wallet',
                'co2_stats.php' => 'CO₂ Stats',
                'rewards.php'   => 'Rewards',
            ];
        default:
            return [];
    }
}

// <head> extras for admin pages that call the JSON endpoints.
function admin_assets()
{
    return '<meta name="csrf-token" content="' . e(csrf_token()) . '">' . "\n    "
        . '<script src="admin.js?v=2"></script>';
}

function role_label($role)
{
    $labels = ['admin' => 'Admin Control Center', 'driver' => 'Driver', 'customer' => 'Passenger'];
    return $labels[$role] ?? 'Smart campus carpool';
}

// Options: role, active (nav file to highlight), auth (true for login/signup),
// fill (true for dashboards that stretch to the screen height), head (extra <head> HTML).
function page_header($title, array $options = [])
{
    $role = $options['role'] ?? null;
    $active = $options['active'] ?? basename($_SERVER['SCRIPT_NAME']);
    $isAuthPage = !empty($options['auth']);
    $mainClass = $isAuthPage ? 'auth-shell' : (empty($options['fill']) ? 'shell' : 'shell shell-fill');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | APRide</title>
    <link rel="icon" href="logo.png">
    <link rel="stylesheet" href="app.css?v=3">
    <?= $options['head'] ?? '' ?>
</head>
<body>
<?php if (!$isAuthPage): ?>
    <header class="ap-nav">
        <div class="ap-nav-inner">
            <a class="ap-nav-left" href="<?= e(home_for_role($role)) ?>">
                <img src="logo.png" alt="" class="ap-logo">
                <span>
                    <span class="ap-brand">APRide</span>
                    <span class="ap-nav-sub"><?= e(role_label($role)) ?></span>
                </span>
            </a>
            <nav class="ap-nav-links" id="ap-nav-links" aria-label="Main">
                <?php foreach (nav_items($role) as $href => $label): ?>
                    <?php if ($href === $active): ?>
                        <a href="<?= e($href) ?>" class="is-active" aria-current="page"><?= e($label) ?></a>
                    <?php else: ?>
                        <a href="<?= e($href) ?>"><?= e($label) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="logout.php" class="nav-logout-mobile">Logout</a>
            </nav>
            <div class="ap-nav-right">
                <a href="logout.php" class="ap-nav-logout">Logout</a>
                <button class="ap-nav-toggle" type="button" aria-expanded="false" aria-controls="ap-nav-links">
                    <span aria-hidden="true">☰</span><span class="sr-only">Menu</span>
                </button>
            </div>
        </div>
    </header>
<?php endif; ?>
    <main class="<?= $mainClass ?>">
<?php foreach (take_flashes() as $f): ?>
        <div class="ap-alert ap-alert-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function page_footer($extraHtml = '')
{
    ?>
    </main>
    <script>
        document.querySelectorAll('.ap-nav-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var open = document.getElementById('ap-nav-links').classList.toggle('open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    </script>
    <?= $extraHtml ?>
</body>
</html>
<?php
}

// Left-hand brand panel on the login and sign-up pages.
function auth_hero()
{
    ?>
            <div class="auth-hero">
                <img src="logo.png" alt="APRide logo" class="ap-logo">
                <div>
                    <span class="ap-brand">APRide</span>
                    <h2>Smart campus carpool</h2>
                </div>
                <p>Share rides around APU, split the cost and cut your carbon footprint.</p>
                <ul class="auth-features">
                    <li><span aria-hidden="true">🚗</span>Find or offer rides between campus, LRT and the city</li>
                    <li><span aria-hidden="true">💳</span>Pay from your wallet only when the driver accepts</li>
                    <li><span aria-hidden="true">🌱</span>Track the CO₂ you save and earn reward points</li>
                </ul>
            </div>
<?php
}

// One dashboard tile. Every tile uses the same structure so they all line up.
function tile($href, $icon, $title, $subtitle, $metric = null, $metricLabel = '', $extraClass = '')
{
    ?>
                <a class="ap-tile <?= e($extraClass) ?>" href="<?= e($href) ?>">
                    <span class="tile-icon" aria-hidden="true"><?= $icon ?></span>
                    <span class="ap-tile-title"><?= e($title) ?></span>
                    <span class="ap-tile-sub"><?= e($subtitle) ?></span>
                    <span class="tile-bottom">
                        <span class="tile-metric"><?php if ($metric !== null): ?><?= e($metric) ?><small><?= e($metricLabel) ?></small><?php endif; ?></span>
                        <span class="tile-foot">Open →</span>
                    </span>
                </a>
<?php
}

// Small page heading with optional subtitle and action buttons on the right.
function page_title($title, $subtitle = '', $actionsHtml = '')
{
    ?>
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <?php if ($subtitle !== ''): ?><p class="ap-muted"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($actionsHtml !== ''): ?><div class="page-actions"><?= $actionsHtml ?></div><?php endif; ?>
    </div>
<?php
}
