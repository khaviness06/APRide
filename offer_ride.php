<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$driver = require_driver();
$maxSeats = (int)$driver['max_passengers'] > 0 ? (int)$driver['max_passengers'] : 4;

$old = ['pickup' => '', 'dropoff' => '', 'ride_date' => '', 'ride_time' => '', 'available_seats' => (string)$maxSeats, 'price' => '', 'distance_km' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $key => $default) {
        $old[$key] = trim((string)($_POST[$key] ?? $default));
    }

    if (!array_key_exists($old['pickup'], LOCATIONS)) {
        $errors['pickup'] = 'Choose a pick-up location.';
    }
    if (!array_key_exists($old['dropoff'], LOCATIONS)) {
        $errors['dropoff'] = 'Choose a drop-off location.';
    } elseif ($old['dropoff'] === $old['pickup']) {
        $errors['dropoff'] = 'Drop-off must be different from pick-up.';
    }

    $when = DateTime::createFromFormat('Y-m-d H:i', $old['ride_date'] . ' ' . $old['ride_time']);
    if (!$when || $when->format('Y-m-d H:i') !== $old['ride_date'] . ' ' . $old['ride_time']) {
        $errors['ride_date'] = 'Choose a valid date and time.';
    } elseif ($when->getTimestamp() <= time()) {
        $errors['ride_date'] = 'The ride must be in the future.';
    }

    $seats = (int)$old['available_seats'];
    if ($seats < 1 || $seats > $maxSeats) {
        $errors['available_seats'] = "Choose between 1 and $maxSeats seats.";
    }
    $price = filter_var($old['price'], FILTER_VALIDATE_FLOAT);
    if ($price === false || $price < 1 || $price > 500) {
        $errors['price'] = 'Enter a price between RM 1 and RM 500.';
    }
    $distance = filter_var($old['distance_km'], FILTER_VALIDATE_FLOAT);
    if ($distance === false || $distance < 0.1 || $distance > 500) {
        $errors['distance_km'] = 'Enter a distance between 0.1 and 500 km.';
    }

    if (!$errors) {
        db_exec(
            "INSERT INTO ride (driver_id, destination, date_time, available_seats, price, distance_km) VALUES (?, ?, ?, ?, ?, ?)",
            'issidd',
            [$driver['driver_id'], $old['pickup'] . ' → ' . $old['dropoff'], $when->format('Y-m-d H:i:00'), $seats, round($price, 2), round($distance, 1)]
        );
        flash('success', 'Ride published! Passengers can now request seats.');
        redirect('my_rides.php');
    }
}

function offer_error($errors, $key)
{
    return isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : '';
}

function location_options($selected)
{
    foreach (array_keys(LOCATIONS) as $place) {
        echo '<option' . ($place === $selected ? ' selected' : '') . '>' . e($place) . '</option>';
    }
}

page_header('Offer Ride', ['role' => 'driver', 'fill' => true, 'head' =>
    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">']);
?>
        <?php page_title('🚗 Offer a Ride', 'Publish a new trip for students to join.'); ?>

        <div class="split-2-1 grow-row">
            <div class="glass-card flex-card">
                <h2>Trip details</h2>
                <form method="POST" id="offer-form">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div>
                            <label class="ap-label" for="pickup">Pick-up location</label>
                            <select class="ap-select<?= isset($errors['pickup']) ? ' has-error' : '' ?>" name="pickup" id="pickup" required>
                                <option value="">Select pick-up</option>
                                <?php location_options($old['pickup']); ?>
                            </select>
                            <?= offer_error($errors, 'pickup') ?>
                        </div>
                        <div>
                            <label class="ap-label" for="dropoff">Drop-off location</label>
                            <select class="ap-select<?= isset($errors['dropoff']) ? ' has-error' : '' ?>" name="dropoff" id="dropoff" required>
                                <option value="">Select drop-off</option>
                                <?php location_options($old['dropoff']); ?>
                            </select>
                            <?= offer_error($errors, 'dropoff') ?>
                        </div>
                    </div>

                    <div class="form-row form-row-3">
                        <div>
                            <label class="ap-label" for="ride_date">Date</label>
                            <input class="ap-input<?= isset($errors['ride_date']) ? ' has-error' : '' ?>" type="date" name="ride_date" id="ride_date"
                                   min="<?= date('Y-m-d') ?>" value="<?= e($old['ride_date']) ?>" required>
                            <?= offer_error($errors, 'ride_date') ?>
                        </div>
                        <div>
                            <label class="ap-label" for="ride_time">Time</label>
                            <input class="ap-input" type="time" name="ride_time" id="ride_time" value="<?= e($old['ride_time']) ?>" required>
                        </div>
                        <div>
                            <label class="ap-label" for="distance_km">Distance (km)</label>
                            <input class="ap-input<?= isset($errors['distance_km']) ? ' has-error' : '' ?>" type="number" step="0.1" min="0.1" max="500"
                                   name="distance_km" id="distance_km" value="<?= e($old['distance_km']) ?>" required>
                            <?= offer_error($errors, 'distance_km') ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label class="ap-label" for="available_seats">Seats offered (max <?= $maxSeats ?>)</label>
                            <input class="ap-input<?= isset($errors['available_seats']) ? ' has-error' : '' ?>" type="number" name="available_seats"
                                   id="available_seats" min="1" max="<?= $maxSeats ?>" value="<?= e($old['available_seats']) ?>" required>
                            <?= offer_error($errors, 'available_seats') ?>
                        </div>
                        <div>
                            <label class="ap-label" for="price">Price per person (RM)</label>
                            <input class="ap-input<?= isset($errors['price']) ? ' has-error' : '' ?>" type="number" step="0.01" min="1" max="500"
                                   name="price" id="price" value="<?= e($old['price']) ?>" required>
                            <?= offer_error($errors, 'price') ?>
                        </div>
                    </div>

                    <span class="ap-label">Route preview</span>
                    <div id="route-map" role="img" aria-label="Map of the chosen pick-up and drop-off points"></div>

                    <div class="form-actions">
                        <a href="driver_dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">🚀 Publish ride</button>
                    </div>
                </form>
            </div>

            <div class="glass-card">
                <h2>Summary</h2>
                <div class="ap-stat" style="margin-bottom:14px">
                    <span class="ap-stat-label">Your vehicle</span>
                    <span class="ap-stat-value" style="font-size:20px"><?= e($driver['vehicle_type']) ?> · <?= e($driver['license_plate']) ?></span>
                    <span class="ap-stat-note">up to <?= $maxSeats ?> passengers</span>
                </div>
                <div class="ap-stat" style="margin-bottom:14px">
                    <span class="ap-stat-label">If every seat is filled</span>
                    <span class="ap-stat-value text-green" style="font-size:20px">RM <span id="earn">0.00</span></span>
                    <span class="ap-stat-note">you earn, and save about <strong><span id="co2">0.00</span> kg</strong> of CO₂</span>
                </div>
                <ul class="tips">
                    <li>Passengers request seats; you accept or decline them under My Rides.</li>
                    <li>Fares are charged to passengers' wallets when you accept.</li>
                    <li>Cancelling an upcoming ride refunds everyone automatically.</li>
                </ul>
            </div>
        </div>
<?php
page_footer('
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            var places = ' . json_encode(LOCATIONS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
            var map = L.map("route-map", { scrollWheelZoom: false }).setView([3.08, 101.69], 11);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "© OpenStreetMap" }).addTo(map);
            var layer = L.layerGroup().addTo(map);
            var pickup = document.getElementById("pickup");
            var dropoff = document.getElementById("dropoff");

            // Redraw the pins and line whenever the driver changes a location.
            function draw() {
                layer.clearLayers();
                var points = [];
                [[pickup, "Pick-up"], [dropoff, "Drop-off"]].forEach(function (pair) {
                    var spot = places[pair[0].value];
                    if (spot) {
                        L.marker(spot).addTo(layer).bindTooltip(pair[1] + ": " + pair[0].value, { permanent: true, direction: "top", offset: [-15, -12] });
                        points.push(spot);
                    }
                });
                if (points.length === 2) {
                    L.polyline(points, { color: "#22c55e", weight: 4, dashArray: "6 8" }).addTo(layer);
                    map.fitBounds(points, { padding: [50, 50] });
                } else if (points.length === 1) {
                    map.setView(points[0], 14);
                }
            }
            pickup.addEventListener("change", draw);
            dropoff.addEventListener("change", draw);
            draw();
        })();
    </script>
    <script>
        (function () {
            var CO2_PER_KM = ' . CO2_KG_PER_PASSENGER_KM . ';
            var seats = document.getElementById("available_seats");
            var distance = document.getElementById("distance_km");
            var pickup = document.getElementById("pickup");
            var dropoff = document.getElementById("dropoff");
            var price = document.getElementById("price");
            var date = document.getElementById("ride_date");
            var time = document.getElementById("ride_time");

            function updateCo2() {
                var kg = (parseFloat(distance.value) || 0) * CO2_PER_KM * (parseInt(seats.value, 10) || 0);
                document.getElementById("co2").textContent = kg.toFixed(2);
                var earn = (parseFloat(price.value) || 0) * (parseInt(seats.value, 10) || 0);
                document.getElementById("earn").textContent = earn.toFixed(2);
            }
            // Same pick-up and drop-off is not allowed: clear the other one.
            pickup.addEventListener("change", function () { if (dropoff.value === pickup.value) dropoff.value = ""; });
            dropoff.addEventListener("change", function () { if (pickup.value === dropoff.value) pickup.value = ""; });

            function checkFuture() {
                time.setCustomValidity("");
                if (date.value && time.value && new Date(date.value + "T" + time.value) <= new Date()) {
                    time.setCustomValidity("Pick a time in the future.");
                }
            }
            date.addEventListener("change", checkFuture);
            time.addEventListener("change", checkFuture);
            seats.addEventListener("input", updateCo2);
            distance.addEventListener("input", updateCo2);
            price.addEventListener("input", updateCo2);
            updateCo2();
        })();
    </script>
');
