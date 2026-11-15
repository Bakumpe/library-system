<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_form.php';
redirect_if_logged_in();

$login = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $login = strtolower(post_text('username')); // A username or an email.
    $password = post_password('password');

    $errors = array_filter([
        'username' => $login === '' ? 'Enter your username or email.' : null,
        'password' => $password === '' ? 'Enter your password.' : null,
    ]);

    if ($errors) {
        flash_now('error', 'Fill in both fields to log in.');
    } else {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/mailer.php';

        $account = find_user($pdo, $login);
        if ($account !== null && password_verify($password, $account['password_hash'])) {
            if ($account['email_verified_at'] === null) {
                // Right password, but the email is not confirmed yet: send a code and ask for it.
                $_SESSION['verify_user_id'] = (int) $account['user_id'];
                $sent = send_code($pdo, $account, 'verify');
                flash('info', 'Confirm your email to finish signing in.');
                flash($sent['type'], $sent['message']);
                redirect('auth/verify_email.php');
            }
            log_in($account);
            flash('success', 'Welcome back, ' . $account['full_name'] . '.');
            redirect('dashboard/index.php');
        }

        // Same message for an unknown account and a wrong password, so nobody can test which accounts exist.
        $errors['password'] = 'Those details do not match an account.';
        flash_now('error', 'Those details do not match an account. Check your username or email, and your password.');
    }
}

$focus = isset($errors['password']) && !isset($errors['username']) ? 'password' : 'username';

$page_title = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <?php require __DIR__ . '/../includes/auth_aside.php'; ?>
    <section class="auth__card" aria-labelledby="auth-title">
        <p class="auth__eyebrow">Staff sign-in</p>
        <h1 class="auth__title" id="auth-title">Welcome back</h1>
        <p class="auth__lede">Log in with your username or your email address.</p>

        <form method="post" class="auth__form" data-busy-form>
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username or email</label>
                <input id="username" name="username" value="<?= e($login) ?>" autocomplete="username"
                    autocapitalize="none" spellcheck="false" required<?= $focus === 'username' ? ' autofocus' : '' ?><?= field_attrs($errors, 'username') ?>>
                <?= field_error($errors, 'username') ?>
            </div>
            <?= password_field($errors, 'password', 'Password', [
                'autofocus' => $focus === 'password',
                'link'      => '<a class="field__link" href="' . e(url('auth/forgot_password.php')) . '">Forgot password?</a>',
            ]) ?>
            <button class="btn btn--block" type="submit" data-busy-label="Logging in…">Log in</button>
        </form>

        <p class="auth__switch">New here? <a href="<?= e(url('auth/signup.php')) ?>">Create an account</a></p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
