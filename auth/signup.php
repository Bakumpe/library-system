<?php
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
}

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
    $confirm   = post_password('password_confirm');

    $errors = array_filter([
        validate_required($full_name, 'Full name', 120),
        validate_username($username),
        validate_required($email, 'Email', 190) ?? validate_email($email),
        validate_new_password($password, $confirm),
    ]);

    if (!$errors) {
        try {
            // Everyone who signs up is a normal user. Make someone admin in the database.
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, full_name, role) VALUES (?, ?, ?, ?, 'librarian')");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $full_name]);
            $user = ['user_id' => (int) $pdo->lastInsertId(), 'email' => $email, 'full_name' => $full_name];

            $_SESSION['verify_user_id'] = $user['user_id'];
            $problem = send_code($pdo, $user, 'verify');
            flash($problem ? 'error' : 'success', $problem ?? "Account created. We sent a 6-digit code to $email.");
            redirect('auth/verify_email.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                error_log('Sign-up failed: ' . $e->getMessage());
                throw $e;
            }
            $errors[] = str_contains((string) ($e->errorInfo[2] ?? ''), 'uq_users_email')
                ? 'That email already has an account. Log in, or use "Forgot your password?".'
                : 'That username is taken. Choose another.';
        }
    }
}

$page_title = 'Sign up';
require __DIR__ . '/../includes/header.php';
?>
<section class="slip">
    <h1 class="slip__title">Library</h1>
    <p class="slip__sub">Create a staff account</p>

    <?php foreach ($errors as $error) : ?>
        <p class="flash flash--error" role="alert"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($full_name) ?>" maxlength="120" autocomplete="name" required autofocus>

        <label for="username">Username</label>
        <input id="username" name="username" value="<?= e($username) ?>" maxlength="40" pattern="[a-z0-9._\-]{3,40}" autocomplete="username" required>

        <label for="email">Email (we send a code to confirm it)</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="190" autocomplete="email" required>

        <label for="password">Password (at least 8 characters)</label>
        <input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>

        <label for="password_confirm">Type the password again</label>
        <input id="password_confirm" name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>

        <button class="btn" type="submit">Sign up</button>
    </form>
    <p class="slip__foot">Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
