<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/book_form.php';

$authors = author_choices($pdo);
$categories = category_choices($pdo);

$errors = [];
$form = BOOK_BLANK;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = book_form_input();
    $errors = book_form_errors($form, $authors, $categories);

    if (!$errors) {
        try {
            // A new book has every copy on the shelf.
            $copies = (int) $form['total_copies'];
            $stmt = $pdo->prepare(
                'INSERT INTO books (isbn, title, author_id, category_id, publisher, year_published, total_copies, available_copies)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([...book_values($form), $copies, $copies]);

            flash('success', '"' . $form['title'] . '" added to the catalogue.');
            redirect('books/index.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                throw $e;
            }
            $errors['isbn'] = 'Another book already has that ISBN.';
        }
    }
    flash_form_errors($errors, 'save the book');
}

$page_title = 'Add a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Add a book</h1>

<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <?= book_fields($form, $errors, $authors, $categories) ?>
    <div class="form__actions">
        <button class="btn" type="submit" data-busy-label="Saving…">Add book</button>
        <a href="<?= e(url('books/index.php')) ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
