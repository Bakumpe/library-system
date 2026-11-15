<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/delete_page.php';

$in_use = 'This author cannot be deleted while books are assigned to them.';

$view = delete_page($pdo, [
    'back'    => 'authors/index.php',
    'missing' => 'That author is not on the list.',
    'lock'    => 'SELECT author_id FROM authors WHERE author_id = ? FOR UPDATE',
    'load'    => 'SELECT a.name, a.country, COUNT(b.book_id) AS titles
                  FROM authors a
                  LEFT JOIN books b ON b.author_id = a.author_id
                  WHERE a.author_id = ?
                  GROUP BY a.author_id, a.name, a.country',
    'delete'  => 'DELETE FROM authors WHERE author_id = ?',
    'blocked' => fn (array $author): ?string => (int) $author['titles'] > 0
        ? $in_use . ' ' . $author['name'] . ' has ' . plural((int) $author['titles'], 'book', 'books') . '.'
        : null,
    'done'    => fn (array $author): string => $author['name'] . ' deleted.',
    'in_use'  => $in_use,
]);

$page_title = 'Delete an author';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete an author</h1>
<?= delete_confirmation($view, 'author', $view['row']['name'], $view['row']['country']) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
