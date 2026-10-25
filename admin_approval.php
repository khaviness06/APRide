<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';
require_admin();

// Every driver who is not approved yet needs admin attention (pending, rejected or an unknown status).
$drivers = db_all(
    "SELECT d.driver_id, d.vehicle_type, d.license_plate, d.max_passengers, d.status,
            u.user_id, u.TP_Number AS tp_number, u.name, u.e_mail
     FROM driver d JOIN user u ON d.user_id = u.user_id
     WHERE d.status <> 'Approved'
     ORDER BY (d.status = 'Rejected'), d.driver_id"
);

$counts = ['pending' => 0, 'rejected' => 0];
foreach ($drivers as &$d) {
    $d['bucket'] = strcasecmp($d['status'], 'Rejected') === 0 ? 'rejected' : 'pending';
    $counts[$d['bucket']]++;
}
unset($d);

page_header('Driver Requests', ['fill' => true, 'role' => 'admin', 'head' => admin_assets()]);
?>
        <?php page_title('Driver Requests', 'New driver sign-ups waiting for your decision. Approved drivers move to the Drivers page.'); ?>

        <div class="glass-card">
            <div id="page-message" hidden></div>

            <div class="chip-group" role="group" aria-label="Filter requests">
                <button type="button" class="chip active" data-filter="all">All <span data-count="all"><?= count($drivers) ?></span></button>
                <button type="button" class="chip" data-filter="pending">Pending <span data-count="pending"><?= $counts['pending'] ?></span></button>
                <button type="button" class="chip" data-filter="rejected">Rejected <span data-count="rejected"><?= $counts['rejected'] ?></span></button>
            </div>

            <div class="table-wrap">
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>TP Number</th>
                            <th>Email</th>
                            <th>Vehicle</th>
                            <th>Plate</th>
                            <th>Status</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($drivers as $d): ?>
                        <tr id="driver-<?= (int)$d['driver_id'] ?>" data-bucket="<?= $d['bucket'] ?>">
                            <td><strong><?= e($d['name'] !== '' ? $d['name'] : $d['tp_number']) ?></strong><div class="ap-muted small">#DRV-<?= (int)$d['driver_id'] ?></div></td>
                            <td><?= e($d['tp_number']) ?></td>
                            <td><a class="ap-link" href="mailto:<?= e($d['e_mail']) ?>"><?= e($d['e_mail']) ?></a></td>
                            <td><?= e($d['vehicle_type']) ?> · <?= (int)$d['max_passengers'] ?> seats</td>
                            <td class="nowrap"><strong><?= e($d['license_plate']) ?></strong></td>
                            <td class="status-cell"><?= $d['bucket'] === 'rejected' ? pill('Rejected', 'red') : pill('Pending', 'amber') ?></td>
                            <td class="actions">
                                <div class="btn-row" style="justify-content:flex-end">
                                    <button type="button" class="btn btn-primary btn-sm" data-action="approve" data-id="<?= (int)$d['driver_id'] ?>">Approve</button>
                                    <button type="button" class="btn btn-warning btn-sm" data-action="reject" data-id="<?= (int)$d['driver_id'] ?>" <?= $d['bucket'] === 'rejected' ? 'disabled' : '' ?>>Reject</button>
                                    <button type="button" class="btn btn-danger btn-sm" data-action="delete" data-id="<?= (int)$d['driver_id'] ?>" data-user="<?= (int)$d['user_id'] ?>">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="empty-row" <?= $drivers ? 'hidden' : '' ?>>
                            <td colspan="7" class="empty-state">No driver requests at the moment. 🎉</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
<?php
page_footer(<<<'HTML'
    <script>
        (function () {
            var filter = 'all';

            function rows() { return document.querySelectorAll('tbody tr[data-bucket]'); }

            function refresh() {
                var counts = { all: 0, pending: 0, rejected: 0 }, visible = 0;
                rows().forEach(function (row) {
                    counts.all++;
                    counts[row.dataset.bucket]++;
                    var show = filter === 'all' || row.dataset.bucket === filter;
                    row.hidden = !show;
                    if (show) visible++;
                });
                Object.keys(counts).forEach(function (k) {
                    document.querySelector('[data-count="' + k + '"]').textContent = counts[k];
                });
                document.querySelector('.empty-row').hidden = visible > 0;
            }

            document.querySelectorAll('.chip[data-filter]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    document.querySelectorAll('.chip[data-filter]').forEach(function (c) { c.classList.remove('active'); });
                    chip.classList.add('active');
                    filter = chip.dataset.filter;
                    refresh();
                });
            });

            document.querySelector('tbody').addEventListener('click', function (event) {
                var btn = event.target.closest('button[data-action]');
                if (!btn) return;
                var row = document.getElementById('driver-' + btn.dataset.id);
                var name = row.querySelector('strong').textContent;
                var action = btn.dataset.action;
                var request;

                if (action === 'delete') {
                    if (!confirm('Permanently delete ' + name + "'s account? This cannot be undone.")) return;
                    request = adminPost('delete_driver.php', { user_id: btn.dataset.user });
                } else {
                    request = adminPost('update_driver_status.php', {
                        driver_id: btn.dataset.id,
                        status: action === 'approve' ? 'Approved' : 'Rejected'
                    });
                }

                btn.disabled = true;
                request.then(function () {
                    if (action === 'reject') {
                        row.dataset.bucket = 'rejected';
                        row.querySelector('.status-cell').innerHTML = '<span class="ap-pill ap-pill-red">Rejected</span>';
                        showPageMessage(name + ' was rejected. They can no longer log in as a driver.');
                    } else {
                        row.remove();
                        showPageMessage(action === 'approve'
                            ? name + ' is approved and can now offer rides.'
                            : name + "'s account was deleted.");
                    }
                    refresh();
                }).catch(function (err) {
                    btn.disabled = false;
                    showPageMessage(err.message, 'error');
                });
            });
        })();
    </script>
HTML
);
