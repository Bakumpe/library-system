<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/category_form.php';

$errors = [];
$form = CATEGORY_BLANK;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = category_form_input();
    $errors = category_form_errors($form) ?: save_category($pdo, $form);
    if (!$errors) {
        flash('success', 'Category ' . $form['name'] . ' added.');
        redirect('categories/index.php');
    }
    flash_form_errors($errors, 'add the category');
}

$search = get_text('q');
$like = '%' . $search . '%';
$stmt = $pdo->prepare(
    'SELECT c.category_id, c.name, c.description, COUNT(b.book_id) AS titles
     FROM categories c
     LEFT JOIN books b ON b.category_id = c.category_id
     WHERE c.name LIKE ? OR c.description LIKE ?
     GROUP BY c.category_id, c.name, c.description
     ORDER BY c.name'
);
$stmt->execute([$like, $like]);
$categories = $stmt->fetchAll();

$page_title = 'Categories';
require __DIR__ . '/../includes/header.php';
?>
<h1>Categories</h1>

<form method="get" class="search" role="search">
    <label for="q">Search categories</label>
    <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name or description">
    <button class="btn btn--quiet" type="submit">Search</button>
</form>

<div class="table-scroll">
    <table>
        <thead><tr><th>Name</th><th>Description</th><th class="num">Titles</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $category) : ?>
            <tr>
                <td><?= e($category['name']) ?></td>
                <td><?= e($category['description'] ?? '') ?></td>
                <td class="num"><?= e($category['titles']) ?></td>
                <td class="row-actions">
                    <?= edit_link('categories', (int) $category['category_id']) ?>
                    <?= delete_link('categories', (int) $category['category_id']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$categories) : ?>
            <?= empty_row(4, 'No categories match that search.') ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<h2>Add a category</h2>
<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <?= category_fields($form, $errors) ?>
    <button class="btn" type="submit" data-busy-label="Saving…">Add category</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
