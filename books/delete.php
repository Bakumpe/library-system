<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$bookId = (int) ($_REQUEST['id'] ?? 0);

if ($bookId <= 0) {
    flash('error', 'Please choose a book to delete.');
    redirect('books/index.php');
}

$stmt = $pdo->prepare(
    'SELECT b.book_id, b.title, b.isbn,
            (SELECT COUNT(*)
             FROM loans l
             WHERE l.book_id = b.book_id AND l.date_returned IS NULL) AS copies_out
     FROM books b
     WHERE b.book_id = ?'
);
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    flash('error', 'The book you tried to delete was not found.');
    redirect('books/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ((int) $book['copies_out'] > 0) {
        flash('error', 'This book cannot be deleted because ' . (int) $book['copies_out'] . ' copy/copies are still out on loan.');
        redirect('books/index.php');
    }

    try {
        $deleteStmt = $pdo->prepare('DELETE FROM books WHERE book_id = ?');
        $deleteStmt->execute([$bookId]);
        flash('success', 'Book deleted successfully.');
        redirect('books/index.php');
    } catch (PDOException $e) {
        error_log($e->getMessage());
        flash('error', 'The book could not be deleted right now. Please try again.');
        redirect('books/index.php');
    }
}

$page_title = 'Delete a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a book</h1>

<?php if ((int) $book['copies_out'] > 0) : ?>
    <p class="flash flash--error" role="alert">
        <?= e($book['title']) ?> cannot be deleted because <?= e((string) $book['copies_out']) ?> copy/copies are still out on loan.
    </p>
    <a class="btn btn--quiet" href="<?= e(url('books/index.php')) ?>">Back to books</a>
<?php else : ?>
    <p>Are you sure you want to delete <strong><?= e($book['title']) ?></strong> (ISBN: <?= e($book['isbn']) ?>)?</p>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((string) $bookId) ?>">
        <button class="btn btn--danger" type="submit">Delete book</button>
        <a class="btn btn--quiet" href="<?= e(url('books/index.php')) ?>">Cancel</a>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
