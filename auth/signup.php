<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_form.php';
redirect_if_logged_in();

$full_name = '';
$username = '';
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/mailer.php';

    $full_name = post_text('full_name');
    $username  = strtolower(post_text('username'));
    $email     = strtolower(post_text('email'));
    $password  = post_password('password');

    $errors = array_filter([
        'full_name' => validate_required($full_name, 'Full name', 120),
        'username'  => validate_username($username),
        'email'     => validate_required($email, 'Email', 190) ?? validate_email($email),
    ]) + new_password_errors($password, post_password('password_confirm'), 'password', 'password_confirm');

    // Check for taken names up front, so every problem shows at once. The UNIQUE keys still catch a race.
    $stmt = $pdo->prepare('SELECT username = ? AS username_taken, email = ? AS email_taken FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email, $username, $email]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['username_taken'] && !isset($errors['username'])) {
            $errors['username'] = 'That username is taken. Choose another.';
        }
        if ($row['email_taken'] && !isset($errors['email'])) {
            $errors['email'] = 'That email already has an account. Log in, or reset your password.';
        }
    }

    if (!$errors) {
        try {
            // Everyone who signs up is a normal user. Make someone admin in the database.
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, full_name, role) VALUES (?, ?, ?, ?, 'librarian')");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $full_name]);
            $user = ['user_id' => (int) $pdo->lastInsertId(), 'email' => $email, 'full_name' => $full_name];

            $_SESSION['verify_user_id'] = $user['user_id'];
            $sent = send_code($pdo, $user, 'verify');
            flash($sent['type'], 'Account created. ' . $sent['message']);
            redirect('auth/verify_email.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                throw $e;
            }
            $errors['username'] = 'That username or email was just taken. Try again.';
        }
    }

    flash_form_errors($errors, 'continue');
}

$first_error = array_key_first($errors) ?? 'full_name';

$page_title = 'Sign up';
$aside_note = 'Create your staff account in a minute. We only email you a code to confirm it is you.';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <?php require __DIR__ . '/../includes/auth_aside.php'; ?>
    <section class="auth__card" aria-labelledby="auth-title">
        <p class="auth__eyebrow">New account</p>
        <h1 class="auth__title" id="auth-title">Create your account</h1>
        <p class="auth__lede">You will confirm your email with a 6-digit code next.</p>

        <form method="post" class="auth__form" data-busy-form>
            <?= csrf_field() ?>
            <div class="field">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" value="<?= e($full_name) ?>" maxlength="120" autocomplete="name"
                    required<?= $first_error === 'full_name' ? ' autofocus' : '' ?><?= field_attrs($errors, 'full_name') ?>>
                <?= field_error($errors, 'full_name') ?>
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" value="<?= e($username) ?>" maxlength="40" pattern="[a-zA-Z0-9._\-]{3,40}"
                    autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="username-hint"
                    required<?= $first_error === 'username' ? ' autofocus' : '' ?><?= field_attrs($errors, 'username') ?>>
                <p class="field__hint" id="username-hint">3 to 40 letters, numbers, dots, dashes or underscores.</p>
                <?= field_error($errors, 'username') ?>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="190" autocomplete="email"
                    required<?= $first_error === 'email' ? ' autofocus' : '' ?><?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>

            <?= password_field($errors, 'password', 'Password', ['strength' => true, 'autofocus' => $first_error === 'password']) ?>
            <?= password_field($errors, 'password_confirm', 'Type the password again', ['match' => 'password', 'autofocus' => $first_error === 'password_confirm']) ?>

            <button class="btn btn--block" type="submit" data-busy-label="Creating your account…">Create account</button>
        </form>

        <p class="auth__switch">Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
