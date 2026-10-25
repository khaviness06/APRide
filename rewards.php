<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
$customer = require_customer();
$customerId = (int)$customer['customer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reward_id'])) {
    csrf_check();
    // The cost always comes from the database, never from the form.
    $reward = db_one("SELECT reward_id, product_title, points_required FROM reward WHERE reward_id = ?", 'i', [(int)$_POST['reward_id']]);
    if (!$reward) {
        flash('error', 'That reward is no longer available.');
        redirect('rewards.php');
    }
    $cost = (int)$reward['points_required'];
    $redeemed = db_transaction(function () use ($customerId, $cost, $reward) {
        $deducted = db_exec(
            "UPDATE customer SET points_balance = points_balance - ? WHERE customer_id = ? AND points_balance >= ?",
            'iii',
            [$cost, $customerId, $cost]
        );
        if (!$deducted) {
            return false;
        }
        db_exec(
            "INSERT INTO redemption (customer_id, reward_id, date_redeemed) VALUES (?, ?, ?)",
            'iis',
            [$customerId, $reward['reward_id'], now_sql()]
        );
        return true;
    });
    if ($redeemed) {
        flash('success', 'Redeemed: ' . $reward['product_title'] . '. Enjoy!');
    } else {
        flash('error', "You don't have enough points for " . $reward['product_title'] . '.');
    }
    redirect('rewards.php');
}

$points = (int)$customer['points_balance'];
$rewards = db_all("SELECT reward_id, product_title, description, points_required FROM reward ORDER BY points_required");
$earned = db_all(
    "SELECT p.points_earned, r.destination
     FROM points p LEFT JOIN ride r ON p.ride_id = r.ride_id
     WHERE p.customer_id = ? ORDER BY p.points_id DESC LIMIT 5",
    'i',
    [$customerId]
);
$redemptions = db_all(
    "SELECT rd.date_redeemed, rw.product_title, rw.points_required
     FROM redemption rd LEFT JOIN reward rw ON rd.reward_id = rw.reward_id
     WHERE rd.customer_id = ? ORDER BY rd.date_redeemed DESC LIMIT 5",
    'i',
    [$customerId]
);

$nextReward = null;
foreach ($rewards as $r) {
    if ($points < (int)$r['points_required']) {
        $nextReward = $r;
        break;
    }
}

page_header('Rewards', ['role' => 'customer', 'fill' => true]);
?>
        <?php page_title('Rewards', 'Earn ' . POINTS_PER_RIDE . ' points for every ride a driver accepts, then redeem them here.'); ?>

        <div class="ap-stats cols-3">
            <div class="ap-stat">
                <span class="ap-stat-label">Points balance</span>
                <span class="ap-stat-value text-green"><?= number_format($points) ?> pts</span>
            </div>
            <div class="ap-stat">
                <span class="ap-stat-label">Membership</span>
                <span class="ap-stat-value"><?= e($customer['membership_status'] ?: 'Silver') ?></span>
            </div>
            <div class="ap-stat">
                <span class="ap-stat-label">Next reward</span>
                <span class="ap-stat-value"><?= $nextReward ? e($nextReward['product_title']) : 'All unlocked 🎉' ?></span>
                <?php if ($nextReward): ?>
                    <span class="ap-stat-note"><?= number_format((int)$nextReward['points_required'] - $points) ?> more points needed</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-2-1 grow-row">
            <div class="glass-card">
                <div class="section-title">
                    <h2>Available rewards</h2>
                </div>
                <?php foreach ($rewards as $r):
                    $need = (int)$r['points_required'];
                    $canRedeem = $points >= $need;
                    ?>
                    <div class="reward-card">
                        <div style="flex:1; min-width:200px">
                            <div class="ride-route"><?= e($r['product_title']) ?></div>
                            <?php if (!empty($r['description'])): ?>
                                <div class="ap-muted"><?= e($r['description']) ?></div>
                            <?php endif; ?>
                            <div class="progress"><span style="width: <?= min(100, $need ? $points / $need * 100 : 100) ?>%"></span></div>
                            <div class="ap-muted small"><?= number_format(min($points, $need)) ?> / <?= number_format($need) ?> pts</div>
                        </div>
                        <form method="POST" class="inline-form"
                              onsubmit="return confirm('Redeem <?= e(addslashes($r['product_title'])) ?> for <?= $need ?> points?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="reward_id" value="<?= (int)$r['reward_id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" <?= $canRedeem ? '' : 'disabled' ?>>
                                <?= $canRedeem ? 'Redeem · ' . number_format($need) . ' pts' : 'Need ' . number_format($need - $points) . ' more pts' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (!$rewards): ?>
                    <p class="empty-state">No rewards available right now.</p>
                <?php endif; ?>
            </div>

            <div class="col">
                <div class="glass-card">
                    <h2>Points earned</h2>
                    <?php foreach ($earned as $p): ?>
                        <div class="list-row">
                            <span class="ap-muted"><?= $p['destination'] !== null ? route_html($p['destination']) : 'Ride (removed)' ?></span>
                            <strong class="text-green nowrap">+<?= (int)$p['points_earned'] ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$earned): ?>
                        <p class="ap-muted mb-0">No points yet. Book a ride to start earning!</p>
                    <?php endif; ?>
                </div>
                <div class="glass-card grow">
                    <h2>Redeemed</h2>
                    <?php foreach ($redemptions as $rd): ?>
                        <div class="list-row">
                            <span>
                                <?= e($rd['product_title'] ?? 'Reward (removed)') ?>
                                <span class="ap-muted small" style="display:block"><?= date('d M Y', strtotime($rd['date_redeemed'])) ?></span>
                            </span>
                            <strong class="text-amber nowrap">−<?= (int)$rd['points_required'] ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$redemptions): ?>
                        <p class="ap-muted mb-0">Nothing redeemed yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php page_footer(); ?>
