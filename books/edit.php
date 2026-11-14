<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$bookId = (int) ($_GET['id'] ?? 0);

if ($bookId <= 0) {
    flash('error', 'Please choose a book to edit.');
    redirect('books/index.php');
}

$stmt = $pdo->prepare(
    'SELECT b.*, a.name AS author_name, c.name AS category_name,
            (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.book_id AND l.date_returned IS NULL) AS copies_out
     FROM books b
     JOIN authors a ON a.author_id = b.author_id
     JOIN categories c ON c.category_id = b.category_id
     WHERE b.book_id = ?'
);
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    flash('error', 'The book you tried to edit was not found.');
    redirect('books/index.php');
}

$errors = [];
$form = [
    'isbn' => $book['isbn'],
    'title' => $book['title'],
    'author_id' => (string) $book['author_id'],
    'category_id' => (string) $book['category_id'],
    'publisher' => $book['publisher'],
    'year_published' => (string) $book['year_published'],
    'total_copies' => (string) $book['total_copies'],
];

$authorStmt = $pdo->query('SELECT author_id, name FROM authors ORDER BY name');
$authors = $authorStmt->fetchAll();

$categoryStmt = $pdo->query('SELECT category_id, name FROM categories ORDER BY name');
$categories = $categoryStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $form['isbn'] = strtoupper(trim((string) ($_POST['isbn'] ?? '')));
    $form['title'] = post_text('title');
    $form['author_id'] = (string) ($_POST['author_id'] ?? '');
    $form['category_id'] = (string) ($_POST['category_id'] ?? '');
    $form['publisher'] = post_text('publisher');
    $form['year_published'] = trim((string) ($_POST['year_published'] ?? ''));
    $form['total_copies'] = trim((string) ($_POST['total_copies'] ?? ''));
    $form['isbn'] = preg_replace('/[^0-9Xx]/', '', $form['isbn']) ?: '';

    $errors = array_filter([
        validate_required($form['isbn'], 'ISBN', 17),
        validate_required($form['title'], 'Title', 200),
        validate_required($form['publisher'], 'Publisher', 120),
        ($form['author_id'] === '' ? 'Choose an author.' : null),
        ($form['category_id'] === '' ? 'Choose a category.' : null),
        (($form['year_published'] === '' || !ctype_digit($form['year_published']) || (int) $form['year_published'] < 1400 || (int) $form['year_published'] > 2100)
            ? 'Year published must be a four-digit year between 1400 and 2100.' : null),
        (($form['total_copies'] === '' || !ctype_digit($form['total_copies']) || (int) $form['total_copies'] < 1)
            ? 'Total copies must be a whole number of at least 1.' : null),
    ]);

    $currentlyOut = (int) $book['copies_out'];
    $proposedTotal = (int) $form['total_copies'];
    if (!$errors && $proposedTotal < $currentlyOut) {
        $errors[] = "Total copies cannot be less than the $currentlyOut copy/copies currently out on loan.";
    }

    if (!$errors && !preg_match('/^(?:97[89]\d{10,12}|\d{10,13})$/', $form['isbn'])) {
        $errors[] = 'ISBN must be a valid 10- or 13-digit ISBN.';
    }

    if (!$errors) {
        $authorId = (int) $form['author_id'];
        $categoryId = (int) $form['category_id'];

        $authorCheck = $pdo->prepare('SELECT 1 FROM authors WHERE author_id = ?');
        $authorCheck->execute([$authorId]);
        $categoryCheck = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = ?');
        $categoryCheck->execute([$categoryId]);

        if (!$authorCheck->fetch()) {
            $errors[] = 'The selected author does not exist.';
        }
        if (!$categoryCheck->fetch()) {
            $errors[] = 'The selected category does not exist.';
        }
    }

    if (!$errors) {
        $availableCopies = max(0, $proposedTotal - $currentlyOut);

        try {
            $stmt = $pdo->prepare(
                'UPDATE books
                 SET isbn = ?, title = ?, author_id = ?, category_id = ?, publisher = ?, year_published = ?, total_copies = ?, available_copies = ?
                 WHERE book_id = ?'
            );
            $stmt->execute([
                $form['isbn'],
                $form['title'],
                (int) $form['author_id'],
                (int) $form['category_id'],
                $form['publisher'],
                (int) $form['year_published'],
                $proposedTotal,
                $availableCopies,
                $bookId,
            ]);

            flash('success', 'Book updated successfully.');
            redirect('books/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors[] = 'Another book already uses that ISBN.';
            } else {
                error_log($e->getMessage());
                $errors[] = 'The book could not be updated. Please try again.';
            }
        }
    }
}

$page_title = 'Edit a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit a book</h1>

<a class="btn btn--quiet" href="<?= e(url('books/index.php')) ?>">Back to books</a>

<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>

<form method="post" class="form">
    <?= csrf_field() ?>

    <label for="isbn">ISBN</label>
    <input id="isbn" name="isbn" value="<?= e($form['isbn']) ?>" maxlength="17" required>

    <label for="title">Title</label>
    <input id="title" name="title" value="<?= e($form['title']) ?>" maxlength="200" required>

    <label for="author_id">Author</label>
    <select id="author_id" name="author_id" required>
        <?php foreach ($authors as $author) : ?>
            <option value="<?= e((string) $author['author_id']) ?>"<?= (string) $author['author_id'] === (string) $form['author_id'] ? ' selected' : '' ?>>
                <?= e($author['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required>
        <?php foreach ($categories as $category) : ?>
            <option value="<?= e((string) $category['category_id']) ?>"<?= (string) $category['category_id'] === (string) $form['category_id'] ? ' selected' : '' ?>>
                <?= e($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="publisher">Publisher</label>
    <input id="publisher" name="publisher" value="<?= e($form['publisher']) ?>" maxlength="120" required>

    <label for="year_published">Year published</label>
    <input id="year_published" name="year_published" type="number" min="1400" max="2100" value="<?= e($form['year_published']) ?>" required>

    <label for="total_copies">Total copies</label>
    <input id="total_copies" name="total_copies" type="number" min="1" value="<?= e($form['total_copies']) ?>" required>

    <p><strong>Currently out on loan:</strong> <?= e((string) $book['copies_out']) ?></p>

    <button class="btn" type="submit">Save changes</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
