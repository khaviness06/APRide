<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$customer = require_customer();

// Shared kilometres on rides this customer has completed.
$row = db_one(
    "SELECT COALESCE(SUM(r.distance_km), 0) AS km, COUNT(*) AS rides
     FROM booking b JOIN ride r ON b.ride_id = r.ride_id
     WHERE b.customer_id = ? AND b.status IN (" . PAID_STATUSES . ") AND r.date_time <= ?",
    'is',
    [$customer['customer_id'], now_sql()]
);
$km = (float)$row['km'];
$rides = (int)$row['rides'];
$co2 = co2_saved($km);

// The ring fills up towards the next milestone.
$milestone = 10;
foreach ([10, 25, 50, 100, 250, 500, 1000] as $m) {
    $milestone = $m;
    if ($co2 < $m) {
        break;
    }
}
$pct = min(100, $co2 / $milestone * 100);

page_header('CO₂ Stats', ['role' => 'customer', 'fill' => true]);
?>
        <?php page_title('Your Green Impact', "See how much CO₂ you've helped save by sharing rides."); ?>

        <div class="grid-2-1 grow-row">
            <div class="glass-card">
                <?= pill('Lifetime stats', 'green') ?>
                <div class="donut" style="--pct: <?= round($pct, 1) ?>" role="img"
                     aria-label="<?= number_format($co2, 1) ?> kg of CO2 saved, <?= round($pct) ?>% of the <?= $milestone ?> kg milestone">
                    <div class="donut-inner">
                        <div class="donut-value"><?= number_format($co2, 1) ?></div>
                        <div class="donut-unit">kg CO₂ saved</div>
                    </div>
                </div>
                <p class="ap-muted" style="text-align:center; margin-top:-8px">
                    <?= $co2 >= $milestone ? 'Amazing — you passed ' . $milestone . ' kg!' : number_format($milestone - $co2, 1) . ' kg to your next milestone (' . $milestone . ' kg)' ?>
                </p>

                <div class="list-row"><span class="ap-muted">Completed shared rides</span><strong><?= $rides ?></strong></div>
                <div class="list-row"><span class="ap-muted">Total shared distance</span><strong><?= number_format($km, 1) ?> km</strong></div>
                <div class="list-row"><span class="ap-muted">Trees' yearly absorption*</span><strong><?= number_format(trees_equivalent($co2), 2) ?></strong></div>
                <div class="list-row"><span class="ap-muted">CO₂ saved per km</span><strong><?= number_format(CO2_KG_PER_PASSENGER_KM, 2) ?> kg/km</strong></div>
                <p class="ap-muted small mb-0" style="margin-top:14px">*One mature tree absorbs about <?= TREE_KG_PER_YEAR ?> kg CO₂ per year.</p>
            </div>

            <div class="glass-card">
                <h2>Keep going green</h2>
                <ul class="tips">
                    <li>Share rides to campus instead of driving alone.</li>
                    <li>Choose drivers that already match your usual route.</li>
                    <li>Invite friends to carpool and multiply the impact.</li>
                    <li>Every shared ride also earns you <?= POINTS_PER_RIDE ?> reward points.</li>
                </ul>
                <a href="find_ride.php" class="btn btn-primary btn-block" style="margin-top:18px">Find a ride</a>
            </div>
        </div>
<?php page_footer(); ?>
