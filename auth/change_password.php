<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $current = post_password('current_password');
    $new     = post_password('new_password');
    $confirm = post_password('new_password_confirm');

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();

    if ($hash === false || !password_verify($current, $hash)) {
        $errors[] = 'Your current password is wrong.';
    } elseif ($error = validate_new_password($new, $confirm)) {
        $errors[] = $error;
    } elseif (password_verify($new, $hash)) {
        $errors[] = 'Choose a password different from your current one.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        session_regenerate_id(true);
        flash('success', 'Your password is updated. Use the new one next time you log in.');
        redirect('dashboard/index.php');
    }
}

$page_title = 'Change password';
require __DIR__ . '/../includes/header.php';
?>
<h1>Change your password</h1>

<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>

<form method="post" class="form">
    <?= csrf_field() ?>
    <label for="current_password">Current password</label>
    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required autofocus>

    <label for="new_password">New password (at least 8 characters)</label>
    <input id="new_password" name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>

    <label for="new_password_confirm">Type the new password again</label>
    <input id="new_password_confirm" name="new_password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>

    <button class="btn" type="submit">Update password</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
