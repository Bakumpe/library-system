<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_form.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $current = post_password('current_password');
    $new     = post_password('new_password');

    $user = fetch_row($pdo, 'SELECT password_hash FROM users WHERE user_id = ?', [$_SESSION['user_id']]);
    $hash = $user['password_hash'] ?? '';

    if (!password_verify($current, $hash)) {
        $errors['current_password'] = 'Your current password is wrong.';
    }
    $errors += new_password_errors($new, post_password('new_password_confirm'), 'new_password', 'new_password_confirm');
    if (!isset($errors['new_password']) && password_verify($new, $hash)) {
        $errors['new_password'] = 'Choose a password different from your current one.';
    }

    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        session_regenerate_id(true);
        flash('success', 'Your password is updated. Use the new one next time you log in.');
        redirect('dashboard/index.php');
    }
    flash_form_errors($errors, 'continue');
}

$focus = array_key_first($errors) ?? 'current_password';

$page_title = 'Change password';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth__card auth__card--solo" aria-labelledby="auth-title">
    <p class="auth__eyebrow">Your account</p>
    <h1 class="auth__title" id="auth-title">Change your password</h1>
    <p class="auth__lede">You need your current password to set a new one.</p>

    <form method="post" class="auth__form" data-busy-form>
        <?= csrf_field() ?>
        <?= password_field($errors, 'current_password', 'Current password', ['autofocus' => $focus === 'current_password']) ?>
        <?= password_field($errors, 'new_password', 'New password', ['strength' => true, 'autofocus' => $focus === 'new_password']) ?>
        <?= password_field($errors, 'new_password_confirm', 'Type the new password again', ['match' => 'new_password', 'autofocus' => $focus === 'new_password_confirm']) ?>
        <button class="btn btn--block" type="submit" data-busy-label="Saving…">Update password</button>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
