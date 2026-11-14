<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$form = [
    'isbn' => '',
    'title' => '',
    'author_id' => '',
    'category_id' => '',
    'publisher' => '',
    'year_published' => '',
    'total_copies' => '1',
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
    $form['total_copies'] = trim((string) ($_POST['total_copies'] ?? '1'));

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
        $totalCopies = (int) $form['total_copies'];
        $availableCopies = $totalCopies;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO books (isbn, title, author_id, category_id, publisher, year_published, total_copies, available_copies)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $form['isbn'],
                $form['title'],
                (int) $form['author_id'],
                (int) $form['category_id'],
                $form['publisher'],
                (int) $form['year_published'],
                $totalCopies,
                $availableCopies,
            ]);

            flash('success', 'Book added successfully.');
            redirect('books/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors[] = 'That ISBN is already in the system.';
            } else {
                error_log($e->getMessage());
                $errors[] = 'The book could not be saved. Please try again.';
            }
        }
    }
}

$page_title = 'Add a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Add a book</h1>

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
        <option value="">Choose an author</option>
        <?php foreach ($authors as $author) : ?>
            <option value="<?= e((string) $author['author_id']) ?>"<?= (string) $author['author_id'] === (string) $form['author_id'] ? ' selected' : '' ?>>
                <?= e($author['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required>
        <option value="">Choose a category</option>
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

    <button class="btn" type="submit">Add book</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
