<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/category_form.php';

$category_id = require_id('categories/index.php', 'Choose a category to edit.');
$category = fetch_row($pdo, 'SELECT name, description FROM categories WHERE category_id = ?', [$category_id]);
if ($category === null) {
    flash('error', 'That category is not on the list.');
    redirect('categories/index.php');
}

$errors = [];
$form = form_values(CATEGORY_BLANK, $category);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = category_form_input();
    $errors = category_form_errors($form) ?: save_category($pdo, $form, $category_id);
    if (!$errors) {
        flash('success', 'Category ' . $form['name'] . ' saved.');
        redirect('categories/index.php');
    }
    flash_form_errors($errors, 'save the category');
}

$page_title = 'Edit a category';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit a category</h1>

<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $category_id ?>">
    <?= category_fields($form, $errors) ?>
    <div class="form__actions">
        <button class="btn" type="submit" data-busy-label="Saving…">Save changes</button>
        <a href="<?= e(url('categories/index.php')) ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
