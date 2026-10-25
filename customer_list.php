<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
require_admin();

$customers = db_all(
    "SELECT c.customer_id, c.points_balance, c.wallet, c.membership_status,
            u.user_id, u.TP_Number AS tp_number, u.name, u.e_mail,
            (SELECT COUNT(*) FROM booking b WHERE b.customer_id = c.customer_id AND b.status IN (" . PAID_STATUSES . ")) AS rides
     FROM customer c JOIN user u ON c.user_id = u.user_id
     ORDER BY c.customer_id"
);

page_header('Customers', ['fill' => true, 'role' => 'admin', 'head' => admin_assets()]);
?>
        <?php page_title('Registered Customers', count($customers) . ' passengers registered.'); ?>

        <div class="glass-card">
            <div id="page-message" hidden></div>

            <div class="table-wrap">
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>TP Number</th>
                            <th>Email</th>
                            <th>Membership</th>
                            <th class="num">Rides</th>
                            <th class="num">Wallet</th>
                            <th class="num">Points</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($customers as $c): ?>
                        <tr id="customer-<?= (int)$c['user_id'] ?>">
                            <td><strong><?= e($c['name'] !== '' ? $c['name'] : $c['tp_number']) ?></strong><div class="ap-muted small">#CUST-<?= (int)$c['customer_id'] ?></div></td>
                            <td><?= e($c['tp_number']) ?></td>
                            <td>
                                <?php if ($c['e_mail']): ?>
                                    <a href="mailto:<?= e($c['e_mail']) ?>" class="ap-link"><?= e($c['e_mail']) ?></a>
                                <?php else: ?>
                                    <span class="ap-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= pill($c['membership_status'] ?: 'Silver', $c['membership_status'] === 'Gold' ? 'amber' : 'gray') ?></td>
                            <td class="num"><?= (int)$c['rides'] ?></td>
                            <td class="num"><?= money($c['wallet']) ?></td>
                            <td class="num"><?= number_format((int)$c['points_balance']) ?> pts</td>
                            <td class="actions">
                                <button type="button" class="btn btn-danger btn-sm" data-user="<?= (int)$c['user_id'] ?>"
                                        data-name="<?= e($c['name'] !== '' ? $c['name'] : $c['tp_number']) ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$customers): ?>
                        <tr><td colspan="8" class="empty-state">No customers found in the system.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php
page_footer(<<<'HTML'
    <script>
        document.querySelector('tbody').addEventListener('click', function (event) {
            var btn = event.target.closest('button[data-user]');
            if (!btn) return;
            var name = btn.dataset.name;
            if (!confirm('Permanently delete ' + name + '? Their bookings, points and rewards history will also be removed.')) return;
            btn.disabled = true;
            adminPost('delete_customer.php', { user_id: btn.dataset.user })
                .then(function () {
                    document.getElementById('customer-' + btn.dataset.user).remove();
                    showPageMessage(name + ' was deleted.');
                })
                .catch(function (err) {
                    btn.disabled = false;
                    showPageMessage(err.message, 'error');
                });
        });
    </script>
HTML
);
