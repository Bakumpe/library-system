<?php
// Step 2 of a password reset: the emailed code plus the new password.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_form.php';

if (!isset($_SESSION['reset_user_id'])) {
    redirect('auth/forgot_password.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/mailer.php';

    $user_id = (int) $_SESSION['reset_user_id'];
    $code = post_code();
    $new = post_password('new_password');

    $errors = new_password_errors($new, post_password('new_password_confirm'), 'new_password', 'new_password_confirm');
    if (!$errors) {
        // No such account: answer exactly like a wrong code, so nobody learns which accounts exist.
        $code_error = $user_id === 0 && strlen($code) === 6
            ? 'That code is wrong. Check the email and try again.'
            : check_code($pdo, $user_id, 'reset', $code);
        if ($code_error !== null) {
            $errors['code'] = $code_error;
        }
    }

    if (!$errors) {
        // Getting the code proves they own the email, so it counts as confirmed too.
        $pdo->prepare('UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE user_id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
        unset($_SESSION['reset_user_id']);
        flash('success', 'Your password is reset. Log in with the new one.');
        redirect('auth/login.php');
    }
    flash_now('error', $errors['code'] ?? 'Fix the highlighted fields to continue.');
}

$focus = array_key_first($errors) ?? 'code';

$page_title = 'Reset password';
$aside_note = 'Choose something new that you have not used here before.';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <?php require __DIR__ . '/../includes/auth_aside.php'; ?>
    <section class="auth__card" aria-labelledby="auth-title">
        <p class="auth__eyebrow">Password reset</p>
        <h1 class="auth__title" id="auth-title">Set a new password</h1>
        <p class="auth__lede">Type the code from the email, then your new password.</p>

        <form method="post" class="auth__form" data-busy-form>
            <?= csrf_field() ?>
            <?= code_field($errors, 'Code from the email', $focus === 'code') ?>
            <?= password_field($errors, 'new_password', 'New password', ['strength' => true, 'autofocus' => $focus === 'new_password']) ?>
            <?= password_field($errors, 'new_password_confirm', 'Type the new password again', ['match' => 'new_password', 'autofocus' => $focus === 'new_password_confirm']) ?>
            <button class="btn btn--block" type="submit" data-busy-label="Saving…">Reset password</button>
        </form>

        <p class="auth__switch">No email? Check spam, or <a href="<?= e(url('auth/forgot_password.php')) ?>">send a new code</a>.</p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
