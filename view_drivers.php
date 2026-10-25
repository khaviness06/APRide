<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
require_admin();

$drivers = db_all(
    "SELECT d.driver_id, d.vehicle_type, d.license_plate, d.max_passengers,
            u.user_id, u.TP_Number AS tp_number, u.name, u.e_mail,
            (SELECT COUNT(*) FROM ride r WHERE r.driver_id = d.driver_id) AS rides
     FROM driver d JOIN user u ON d.user_id = u.user_id
     WHERE d.status = 'Approved'
     ORDER BY d.driver_id"
);

page_header('Drivers', ['fill' => true, 'role' => 'admin', 'head' => admin_assets()]);
?>
        <?php page_title('Approved Drivers', 'Drivers who can offer rides. New or rejected applications are under Requests.'); ?>

        <div class="glass-card">
            <div id="page-message" hidden></div>

            <div class="table-wrap">
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>TP Number</th>
                            <th>Email</th>
                            <th>Vehicle</th>
                            <th>Plate</th>
                            <th class="num">Rides offered</th>
                            <th>Status</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($drivers as $d): ?>
                        <tr id="driver-<?= (int)$d['driver_id'] ?>">
                            <td><strong><?= e($d['name'] !== '' ? $d['name'] : $d['tp_number']) ?></strong><div class="ap-muted small">#DRV-<?= (int)$d['driver_id'] ?></div></td>
                            <td><?= e($d['tp_number']) ?></td>
                            <td><a class="ap-link" href="mailto:<?= e($d['e_mail']) ?>"><?= e($d['e_mail']) ?></a></td>
                            <td><?= e($d['vehicle_type']) ?> · <?= (int)$d['max_passengers'] ?> seats</td>
                            <td class="nowrap"><strong><?= e($d['license_plate']) ?></strong></td>
                            <td class="num"><?= (int)$d['rides'] ?></td>
                            <td><?= pill('Approved', 'green') ?></td>
                            <td class="actions">
                                <div class="btn-row" style="justify-content:flex-end">
                                    <button type="button" class="btn btn-warning btn-sm" data-action="revoke" data-id="<?= (int)$d['driver_id'] ?>"
                                            data-name="<?= e($d['name'] !== '' ? $d['name'] : $d['tp_number']) ?>">Revoke</button>
                                    <button type="button" class="btn btn-danger btn-sm" data-action="delete" data-id="<?= (int)$d['driver_id'] ?>"
                                            data-user="<?= (int)$d['user_id'] ?>" data-name="<?= e($d['name'] !== '' ? $d['name'] : $d['tp_number']) ?>">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$drivers): ?>
                        <tr><td colspan="8" class="empty-state">No approved drivers yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php
page_footer(<<<'HTML'
    <script>
        document.querySelector('tbody').addEventListener('click', function (event) {
            var btn = event.target.closest('button[data-action]');
            if (!btn) return;
            var name = btn.dataset.name, request;
            if (btn.dataset.action === 'delete') {
                if (!confirm('Permanently delete ' + name + '? Their rides are removed and passengers on upcoming rides are refunded.')) return;
                request = adminPost('delete_driver.php', { user_id: btn.dataset.user });
            } else {
                if (!confirm('Revoke approval for ' + name + '? They will not be able to log in as a driver until approved again.')) return;
                request = adminPost('update_driver_status.php', { driver_id: btn.dataset.id, status: 'Rejected' });
            }
            btn.disabled = true;
            request.then(function () {
                document.getElementById('driver-' + btn.dataset.id).remove();
                showPageMessage(btn.dataset.action === 'delete'
                    ? name + ' was deleted.'
                    : name + "'s approval was revoked. They now appear under Requests.");
            }).catch(function (err) {
                btn.disabled = false;
                showPageMessage(err.message, 'error');
            });
        });
    </script>
HTML
);
