<?php
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
}

$login = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/mailer.php';

    // The field is called username, but an email works too. Usernames cannot contain @.
    $login = post_text('username');
    $password = post_password('password');

    $stmt = $pdo->prepare(
        'SELECT user_id, password_hash, email, email_verified_at, full_name, role FROM users WHERE username = ? OR email = ?'
    );
    $stmt->execute([strtolower($login), strtolower($login)]);
    $account = $stmt->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        if ($account['email_verified_at'] === null) {
            // Right password, but the email is not confirmed yet: send a code and ask for it.
            $_SESSION['verify_user_id'] = (int) $account['user_id'];
            $problem = send_code($pdo, $account, 'verify');
            flash($problem ? 'error' : 'success', $problem ?? 'Confirm your email first. We sent a code to ' . $account['email'] . '.');
            redirect('auth/verify_email.php');
        }
        log_in($account);
        flash('success', 'Welcome back, ' . $account['full_name'] . '.');
        redirect('dashboard/index.php');
    }

    $error = 'That username and password do not match.';
}

$page_title = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<section class="slip">
    <h1 class="slip__title">Library</h1>
    <p class="slip__sub">Staff sign-in</p>

    <?php if ($error) : ?>
        <p class="flash flash--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label for="username">Username or email</label>
        <input id="username" name="username" value="<?= e($login) ?>" autocomplete="username" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button class="btn" type="submit">Log in</button>
    </form>
    <p class="slip__foot"><a href="<?= e(url('auth/forgot_password.php')) ?>">Forgot your password?</a></p>
    <p class="slip__foot">No account yet? <a href="<?= e(url('auth/signup.php')) ?>">Sign up</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
