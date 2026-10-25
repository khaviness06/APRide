<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$customer = require_customer();

[$ride, $problem] = bookable_ride((int)($_GET['ride_id'] ?? 0), $customer['customer_id']);
if (!$ride) {
    flash('error', $problem);
    redirect('find_ride.php');
}

$route = split_route($ride['destination']);
$fromPoint = LOCATIONS[$route['from']] ?? null;
$toPoint = LOCATIONS[$route['to']] ?? null;
$hasMap = $fromPoint || $toPoint;

$leaflet = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">';

page_header('Ride Details', ['role' => 'customer', 'active' => 'find_ride.php', 'fill' => true, 'head' => $hasMap ? $leaflet : '']);
?>
        <a href="find_ride.php" class="back-link">← Back to rides</a>
        <?php page_title('Ride details', 'Check the trip before you request a seat.'); ?>

        <div class="<?= $hasMap ? 'split' : '' ?> grow-row">
            <div class="glass-card">
                <h2>Trip</h2>
                <dl class="kv">
                    <div><dt>Route</dt><dd><?= route_html($ride['destination']) ?></dd></div>
                    <div><dt>Date & time</dt><dd><?= fmt_datetime($ride['date_time']) ?></dd></div>
                    <div><dt>Driver</dt><dd><?= e($ride['driver_name'] ?: $ride['driver_tp']) ?> <span class="ap-muted">(<?= e($ride['driver_tp']) ?>)</span></dd></div>
                    <div><dt>Vehicle</dt><dd><?= e($ride['vehicle_type']) ?> · <?= e($ride['license_plate']) ?></dd></div>
                    <div><dt>Distance</dt><dd><?= number_format($ride['distance_km'], 1) ?> km</dd></div>
                    <div><dt>Seats left</dt><dd><?= (int)$ride['available_seats'] ?></dd></div>
                    <div><dt>Price</dt><dd class="text-green" style="font-size:18px"><?= money($ride['price']) ?></dd></div>
                    <div><dt>Your CO₂ saving</dt><dd><?= number_format(co2_saved($ride['distance_km']), 2) ?> kg</dd></div>
                </dl>

                <?php if ($problem): ?>
                    <div class="ap-alert ap-alert-info" style="margin:18px 0 0"><?= e($problem) ?> <a href="customer.php">View my bookings</a></div>
                <?php else: ?>
                    <div class="form-actions" style="margin-top:20px">
                        <a href="find_ride.php" class="btn btn-secondary">Back</a>
                        <a href="payment.php?ride_id=<?= (int)$ride['ride_id'] ?>" class="btn btn-primary">Continue to booking</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($hasMap): ?>
                <div class="glass-card map-card">
                    <h2>Route map</h2>
                    <div id="map" role="img" aria-label="Map of the pick-up and drop-off points"></div>
                    <p class="ap-muted small mb-0" style="margin-top:10px">Pins show the approximate pick-up and drop-off points.</p>
                </div>
            <?php endif; ?>
        </div>
<?php
$mapScript = '';
if ($hasMap) {
    $points = [];
    if ($fromPoint) {
        $points[] = ['lat' => $fromPoint[0], 'lng' => $fromPoint[1], 'label' => 'Pick-up: ' . $route['from']];
    }
    if ($toPoint) {
        $points[] = ['lat' => $toPoint[0], 'lng' => $toPoint[1], 'label' => 'Drop-off: ' . $route['to']];
    }
    $mapScript = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            var points = ' . json_encode($points, JSON_HEX_TAG | JSON_HEX_AMP) . ';
            var map = L.map("map");
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "© OpenStreetMap" }).addTo(map);
            var latLngs = points.map(function (p) {
                L.marker([p.lat, p.lng]).addTo(map).bindTooltip(p.label, { permanent: true, direction: "top", offset: [-15, -12] });
                return [p.lat, p.lng];
            });
            if (latLngs.length > 1) {
                L.polyline(latLngs, { color: "#22c55e", weight: 4, dashArray: "6 8" }).addTo(map);
                map.fitBounds(latLngs, { padding: [60, 60] });
            } else {
                map.setView(latLngs[0], 15);
            }
        })();
    </script>';
}
page_footer($mapScript);
