<?php
require_once 'includes/bootstrap.php';
require_once 'includes/layout.php';

// Already logged in? Go straight to the right dashboard.
if (current_user_id() && current_role()) {
    redirect(home_for_role(current_role()));
}

$oldTp = $_SESSION['old_tp'] ?? '';
unset($_SESSION['old_tp']);

page_header('Login', ['auth' => true]);
?>
        <div class="auth-split">
            <?php auth_hero(); ?>
            <div class="auth-form">
                <h1 class="auth-title">Welcome back</h1>
                <p class="auth-sub">Sign in to continue your eco‑friendly rides.</p>
                <form action="login_process.php" method="POST">
                    <?= csrf_field() ?>
                    <label class="ap-label" for="tp_number">TP Number</label>
                    <input class="ap-input" type="text" id="tp_number" name="tp_number" placeholder="TP012345"
                           value="<?= e($oldTp) ?>" autocomplete="username" required autofocus>
                    <label class="ap-label" for="password">Password</label>
                    <input class="ap-input" type="password" id="password" name="password" placeholder="••••••••"
                           autocomplete="current-password" required>
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </form>
                <p class="auth-foot">New here? <a href="signup.php" class="ap-link">Create an account</a></p>
            </div>
        </div>
<?php page_footer(); ?>
