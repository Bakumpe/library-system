<?php
// Step 2 of sign-up: type the code we emailed. Login sends people here too while their email is unconfirmed.
require_once __DIR__ . '/../includes/functions.php';

$user_id = (int) ($_SESSION['verify_user_id'] ?? 0);
if ($user_id === 0) {
    redirect('auth/login.php');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

$stmt = $pdo->prepare('SELECT user_id, email, full_name, role, email_verified_at FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    unset($_SESSION['verify_user_id']);
    redirect('auth/login.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (post_text('action') === 'resend') {
        $problem = send_code($pdo, $user, 'verify');
        flash($problem ? 'error' : 'success', $problem ?? 'We sent a new code to ' . $user['email'] . '.');
        redirect('auth/verify_email.php');
    }

    $error = check_code($pdo, $user_id, 'verify', preg_replace('/\D/', '', post_text('code')) ?? '');
    if ($error === null) {
        $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE user_id = ?')->execute([$user_id]);
        unset($_SESSION['verify_user_id']);
        log_in($user);
        flash('success', 'Email confirmed. Welcome, ' . $user['full_name'] . '.');
        redirect('dashboard/index.php');
    }
}

$page_title = 'Confirm your email';
require __DIR__ . '/../includes/header.php';
?>
<section class="slip">
    <h1 class="slip__title">Library</h1>
    <p class="slip__sub">Confirm your email</p>
    <p>We sent a 6-digit code to <strong><?= e($user['email']) ?></strong>. It works for <?= CODE_MINUTES ?> minutes.</p>

    <?php if ($error) : ?>
        <p class="flash flash--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label for="code">Code</label>
        <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus>
        <button class="btn" type="submit">Confirm</button>
    </form>

    <form method="post" class="slip__foot">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="resend">
        No email? Check spam, or <button class="btn btn--quiet" type="submit">send a new code</button>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
