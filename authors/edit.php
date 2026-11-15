<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/author_form.php';

$author_id = require_id('authors/index.php', 'Choose an author to edit.');
$author = fetch_row($pdo, 'SELECT name, country FROM authors WHERE author_id = ?', [$author_id]);
if ($author === null) {
    flash('error', 'That author is not on the list.');
    redirect('authors/index.php');
}

$errors = [];
$form = form_values(AUTHOR_BLANK, $author);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = author_form_input();
    $errors = author_form_errors($form) ?: save_author($pdo, $form, $author_id);
    if (!$errors) {
        flash('success', $form['name'] . ' saved.');
        redirect('authors/index.php');
    }
    flash_form_errors($errors, 'save the author');
}

$page_title = 'Edit an author';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit an author</h1>

<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $author_id ?>">
    <?= author_fields($form, $errors) ?>
    <div class="form__actions">
        <button class="btn" type="submit" data-busy-label="Saving…">Save changes</button>
        <a href="<?= e(url('authors/index.php')) ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
