<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/author_form.php';

$errors = [];
$form = AUTHOR_BLANK;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = author_form_input();
    $errors = author_form_errors($form) ?: save_author($pdo, $form);
    if (!$errors) {
        flash('success', $form['name'] . ' added.');
        redirect('authors/index.php');
    }
    flash_form_errors($errors, 'add the author');
}

$search = get_text('q');
$like = '%' . $search . '%';
$stmt = $pdo->prepare(
    'SELECT a.author_id, a.name, a.country, COUNT(b.book_id) AS titles
     FROM authors a
     LEFT JOIN books b ON b.author_id = a.author_id
     WHERE a.name LIKE ? OR a.country LIKE ?
     GROUP BY a.author_id, a.name, a.country
     ORDER BY a.name'
);
$stmt->execute([$like, $like]);
$authors = $stmt->fetchAll();

$page_title = 'Authors';
require __DIR__ . '/../includes/header.php';
?>
<h1>Authors</h1>

<form method="get" class="search" role="search">
    <label for="q">Search authors</label>
    <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name or country">
    <button class="btn btn--quiet" type="submit">Search</button>
</form>

<div class="table-scroll">
    <table>
        <thead><tr><th>Name</th><th>Country</th><th class="num">Titles</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($authors as $author) : ?>
            <tr>
                <td><?= e($author['name']) ?></td>
                <td><?= e($author['country']) ?></td>
                <td class="num"><?= e($author['titles']) ?></td>
                <td class="row-actions">
                    <?= edit_link('authors', (int) $author['author_id']) ?>
                    <?= delete_link('authors', (int) $author['author_id']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$authors) : ?>
            <?= empty_row(4, 'No authors match that search.') ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<h2>Add an author</h2>
<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <?= author_fields($form, $errors) ?>
    <button class="btn" type="submit" data-busy-label="Saving…">Add author</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
