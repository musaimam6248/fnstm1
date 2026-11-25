<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user && $user['role'] === 'admin') {
        redirect('admin/dashboard.php');
    }
    redirect('department/dashboard.php');
}

if (is_post()) {
    $username = normalize_text($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        set_flash('error', 'Username and password are required.');
        redirect('login.php');
    }

    if (!login_user($username, $password)) {
        record_activity_event('Failed sign-in attempt for ' . mb_substr($username, 0, 120));
        set_flash('error', 'Invalid login details.');
        redirect('login.php');
    }

    $user = current_user();
    set_flash('success', 'Welcome back, ' . ($user['username'] ?? 'User') . '!');
    if ($user && $user['role'] === 'admin') {
        redirect('admin/dashboard.php');
    }
    redirect('department/dashboard.php');
}

render_header('Login');
?>
<div class="login-backdrop" aria-hidden="true"></div>
<section class="login-stage fade-rise">
    <div class="login-dialog" role="dialog" aria-labelledby="login-title" aria-modal="true">
        <header class="login-dialog-header">
            <img src="<?= e(app_url('assets/fnph_logo.png')) ?>" alt="FNPH" class="login-logo">
            <div><h1 id="login-title">FNPH Kaduna Staff Portal</h1><p>Federal Neuro-Psychiatric Hospital</p></div>
            <a class="login-close" href="<?= e(app_url('index.php')) ?>" aria-label="Close sign in">&times;</a>
        </header>
        <nav class="login-tabs" aria-label="Account actions">
            <a class="is-active" href="<?= e(app_url('login.php')) ?>" aria-current="page">Sign In</a>
            <a href="<?= e(app_url('register_department.php')) ?>">Register Dept</a>
            <a href="<?= e(app_url('forgot_password.php')) ?>">Forgot Password</a>
        </nav>
        <div class="login-dialog-body">
            <?php render_flash_messages(); ?>
            <form method="post" action="<?= e(app_url('login.php')) ?>" class="vstack gap-3">
                <div><label for="username" class="form-label">Username</label><input type="text" class="form-control" id="username" name="username" autocomplete="username" placeholder="e.g. admin or nursing" required></div>
                <div><div class="d-flex justify-content-between align-items-center"><label for="password" class="form-label mb-1">Password</label><a class="small" href="<?= e(app_url('forgot_password.php')) ?>">Forgot HOD password?</a></div><input type="password" class="form-control" id="password" name="password" autocomplete="current-password" placeholder="Enter account password" required></div>
                <button type="submit" class="btn btn-primary w-100">Sign In to Portal</button>
            </form>
            <p class="login-help">Use your administrator or department account to continue.</p>
        </div>
    </div>
</section>
<?php
render_footer();
