<?php
// Step 2 of a password reset: the emailed code plus the new password.
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['reset_user_id'])) {
    redirect('auth/forgot_password.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/mailer.php';

    $user_id = (int) $_SESSION['reset_user_id'];
    $code    = preg_replace('/\D/', '', post_text('code')) ?? '';
    $new     = post_password('new_password');
    $confirm = post_password('new_password_confirm');

    if ($error = validate_new_password($new, $confirm)) {
        $errors[] = $error;
    } elseif ($user_id === 0) {
        // No such account. Answer like a wrong code.
        $errors[] = 'That code is wrong. Check the email and try again.';
    } elseif ($error = check_code($pdo, $user_id, 'reset', $code)) {
        $errors[] = $error;
    } else {
        // Getting the code proves they own the email, so it counts as confirmed too.
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE user_id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
        unset($_SESSION['reset_user_id']);
        flash('success', 'Your password is reset. Log in with the new one.');
        redirect('auth/login.php');
    }
}

$page_title = 'Reset password';
require __DIR__ . '/../includes/header.php';
?>
<section class="slip">
    <h1 class="slip__title">Library</h1>
    <p class="slip__sub">Set a new password</p>

    <?php foreach ($errors as $error) : ?>
        <p class="flash flash--error" role="alert"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label for="code">Code from the email</label>
        <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus>

        <label for="new_password">New password (at least 8 characters)</label>
        <input id="new_password" name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>

        <label for="new_password_confirm">Type the new password again</label>
        <input id="new_password_confirm" name="new_password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>

        <button class="btn" type="submit">Reset password</button>
    </form>
    <p class="slip__foot">No email? Check spam, or <a href="<?= e(url('auth/forgot_password.php')) ?>">send a new code</a>.</p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
