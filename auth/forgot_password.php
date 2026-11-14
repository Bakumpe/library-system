<?php
// Step 1 of a password reset: email a code to the account's address.
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('auth/change_password.php');
}

$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/mailer.php';

    $login = strtolower(post_text('username'));
    $stmt = $pdo->prepare('SELECT user_id, email, full_name FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    // Say the same thing whether or not the account exists, so nobody can test which ones do.
    $_SESSION['reset_user_id'] = $user ? (int) $user['user_id'] : 0;
    $problem = $user ? send_code($pdo, $user, 'reset') : null;
    if ($problem) {
        error_log('Reset code not sent for user ' . $user['user_id'] . ': ' . $problem);
    }
    flash('success', 'If that account exists, we sent a 6-digit code to its email. It works for ' . CODE_MINUTES . ' minutes.');
    redirect('auth/reset_password.php');
}

$page_title = 'Forgot password';
require __DIR__ . '/../includes/header.php';
?>
<section class="slip">
    <h1 class="slip__title">Library</h1>
    <p class="slip__sub">Forgot your password?</p>
    <p>Enter your username or email. We will email you a code to set a new password.</p>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label for="username">Username or email</label>
        <input id="username" name="username" value="<?= e($login) ?>" autocomplete="username" required autofocus>
        <button class="btn" type="submit">Send the code</button>
    </form>
    <p class="slip__foot"><a href="<?= e(url('auth/login.php')) ?>">Back to log in</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
