<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';

if (current_user_id() && current_role()) {
    redirect(home_for_role(current_role()));
}

$old = [
    'tp_number' => '', 'name' => '', 'email' => '', 'role' => '',
    'vehicle_type' => '', 'license_plate' => '', 'max_passengers' => '4',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $key => $default) {
        $old[$key] = trim((string)($_POST[$key] ?? $default));
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9]{3,20}$/', $old['tp_number'])) {
        $errors['tp_number'] = 'Use 3–20 letters or numbers, e.g. TP012345.';
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 100) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Use at least 6 characters.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if (!in_array($old['role'], ['Customer', 'Driver'], true)) {
        $errors['role'] = 'Please choose an account type.';
    }

    $plate = strtoupper(preg_replace('/\s+/', ' ', $old['license_plate']));
    $maxPassengers = (int)$old['max_passengers'];
    if ($old['role'] === 'Driver') {
        if (!array_key_exists($old['vehicle_type'], VEHICLE_SEATS)) {
            $errors['vehicle_type'] = 'Please choose your vehicle type.';
        } elseif ($maxPassengers < 1 || $maxPassengers > VEHICLE_SEATS[$old['vehicle_type']]) {
            $errors['max_passengers'] = 'A ' . $old['vehicle_type'] . ' can take 1–' . VEHICLE_SEATS[$old['vehicle_type']] . ' passengers.';
        }
        if (!preg_match('/^[A-Z0-9 -]{2,20}$/', $plate)) {
            $errors['license_plate'] = 'Please enter a valid license plate.';
        } elseif (db_one("SELECT driver_id FROM driver WHERE license_plate = ?", 's', [$plate])) {
            $errors['license_plate'] = 'This license plate is already registered.';
        }
    }

    if (!isset($errors['tp_number']) && db_one("SELECT user_id FROM user WHERE TP_Number = ?", 's', [$old['tp_number']])) {
        $errors['tp_number'] = 'An account with this TP number already exists. Try logging in instead.';
    }
    if (!isset($errors['email']) && db_one("SELECT user_id FROM user WHERE e_mail = ?", 's', [$old['email']])) {
        $errors['email'] = 'This email is already registered.';
    }

    if (!$errors) {
        try {
            db_transaction(function () use ($old, $password, $plate, $maxPassengers) {
                db_exec(
                    "INSERT INTO user (TP_Number, password, e_mail, name, role) VALUES (?, ?, ?, ?, ?)",
                    'sssss',
                    [$old['tp_number'], password_hash($password, PASSWORD_DEFAULT), $old['email'], $old['name'], $old['role']]
                );
                global $conn;
                $userId = $conn->insert_id;
                if ($old['role'] === 'Customer') {
                    db_exec(
                        "INSERT INTO customer (user_id, points_balance, membership_status, wallet) VALUES (?, 0, 'Silver', 0)",
                        'i',
                        [$userId]
                    );
                } else {
                    db_exec(
                        "INSERT INTO driver (user_id, status, vehicle_type, license_plate, max_passengers) VALUES (?, 'Pending', ?, ?, ?)",
                        'issi',
                        [$userId, $old['vehicle_type'], $plate, $maxPassengers]
                    );
                }
            });
            flash('success', $old['role'] === 'Driver'
                ? 'Driver account created! An admin will review your details — you can log in once approved.'
                : 'Account created! Please log in.');
            $_SESSION['old_tp'] = $old['tp_number'];
            redirect('login.php');
        } catch (mysqli_sql_exception $ex) {
            $errors['form'] = 'Something went wrong while creating your account. Please try again.';
        }
    }
}

function field_error($errors, $key)
{
    return isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : '';
}

function error_class($errors, $key)
{
    return isset($errors[$key]) ? ' has-error' : '';
}

page_header('Sign Up', ['auth' => true]);
?>
        <div class="auth-split">
            <?php auth_hero(); ?>
            <div class="auth-form">
            <h1 class="auth-title">Join APRide</h1>
            <p class="auth-sub">Share rides around campus and earn rewards.</p>

            <?php if (isset($errors['form'])): ?>
                <div class="ap-alert ap-alert-error"><?= e($errors['form']) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div>
                        <label class="ap-label" for="tp_number">TP Number</label>
                        <input class="ap-input<?= error_class($errors, 'tp_number') ?>" type="text" id="tp_number" name="tp_number"
                               placeholder="TP012345" value="<?= e($old['tp_number']) ?>" autocomplete="username" required>
                        <?= field_error($errors, 'tp_number') ?>
                    </div>
                    <div>
                        <label class="ap-label" for="name">Full Name</label>
                        <input class="ap-input<?= error_class($errors, 'name') ?>" type="text" id="name" name="name"
                               placeholder="Your name" value="<?= e($old['name']) ?>" autocomplete="name" required>
                        <?= field_error($errors, 'name') ?>
                    </div>
                </div>

                <label class="ap-label" for="email">Email</label>
                <input class="ap-input<?= error_class($errors, 'email') ?>" type="email" id="email" name="email"
                       placeholder="Student/Staff email" value="<?= e($old['email']) ?>" autocomplete="email" required>
                <?= field_error($errors, 'email') ?>

                <div class="form-row">
                    <div>
                        <label class="ap-label" for="password">Password</label>
                        <input class="ap-input<?= error_class($errors, 'password') ?>" type="password" id="password" name="password"
                               placeholder="At least 6 characters" autocomplete="new-password" minlength="6" required>
                        <?= field_error($errors, 'password') ?>
                    </div>
                    <div>
                        <label class="ap-label" for="password_confirm">Confirm Password</label>
                        <input class="ap-input<?= error_class($errors, 'password_confirm') ?>" type="password" id="password_confirm"
                               name="password_confirm" placeholder="Repeat password" autocomplete="new-password" required>
                        <?= field_error($errors, 'password_confirm') ?>
                    </div>
                </div>

                <label class="ap-label" for="roleSelect">Account type</label>
                <select class="ap-select<?= error_class($errors, 'role') ?>" name="role" id="roleSelect" required>
                    <option value="" disabled <?= $old['role'] === '' ? 'selected' : '' ?>>Select your role</option>
                    <option value="Customer" <?= $old['role'] === 'Customer' ? 'selected' : '' ?>>Customer (Passenger)</option>
                    <option value="Driver" <?= $old['role'] === 'Driver' ? 'selected' : '' ?>>Driver (Car Owner)</option>
                </select>
                <?= field_error($errors, 'role') ?>

                <div id="driver-fields" <?= $old['role'] === 'Driver' ? '' : 'hidden' ?>>
                    <p class="form-section-title">Driver details</p>

                    <label class="ap-label" for="vehicle_type">Vehicle Type</label>
                    <select class="ap-select<?= error_class($errors, 'vehicle_type') ?>" name="vehicle_type" id="vehicle_type">
                        <option value="" disabled <?= $old['vehicle_type'] === '' ? 'selected' : '' ?>>Select vehicle type</option>
                        <?php foreach (VEHICLE_SEATS as $type => $seats): ?>
                            <option value="<?= e($type) ?>" data-seats="<?= $seats ?>" <?= $old['vehicle_type'] === $type ? 'selected' : '' ?>>
                                <?= e($type) ?> (up to <?= $seats ?> passengers)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'vehicle_type') ?>

                    <div class="form-row">
                        <div>
                            <label class="ap-label" for="license_plate">License Plate</label>
                            <input class="ap-input<?= error_class($errors, 'license_plate') ?>" type="text" name="license_plate"
                                   id="license_plate" placeholder="e.g. VAB 1234" value="<?= e($old['license_plate']) ?>">
                            <?= field_error($errors, 'license_plate') ?>
                        </div>
                        <div>
                            <label class="ap-label" for="max_passengers">Max Passengers</label>
                            <input class="ap-input<?= error_class($errors, 'max_passengers') ?>" type="number" name="max_passengers"
                                   id="max_passengers" min="1" max="7" value="<?= e($old['max_passengers']) ?>">
                            <?= field_error($errors, 'max_passengers') ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </form>
            <p class="auth-foot">Already have an account? <a href="login.php" class="ap-link">Back to Login</a></p>
            </div>
        </div>
<?php
page_footer(<<<'HTML'
    <script>
        (function () {
            var role = document.getElementById('roleSelect');
            var section = document.getElementById('driver-fields');
            var vehicle = document.getElementById('vehicle_type');
            var plate = document.getElementById('license_plate');
            var maxPax = document.getElementById('max_passengers');

            function syncRole() {
                var isDriver = role.value === 'Driver';
                section.hidden = !isDriver;
                [vehicle, plate, maxPax].forEach(function (el) { el.required = isDriver; });
            }
            function syncSeats() {
                var opt = vehicle.options[vehicle.selectedIndex];
                if (opt && opt.dataset.seats) {
                    maxPax.max = opt.dataset.seats;
                    if (+maxPax.value > +opt.dataset.seats) maxPax.value = opt.dataset.seats;
                }
            }
            role.addEventListener('change', syncRole);
            vehicle.addEventListener('change', syncSeats);
            syncRole();
            syncSeats();
        })();
    </script>
HTML
);
