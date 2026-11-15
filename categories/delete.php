<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/delete_page.php';

$in_use = 'This category cannot be deleted while books are filed under it.';

$view = delete_page($pdo, [
    'back'    => 'categories/index.php',
    'missing' => 'That category is not on the list.',
    'lock'    => 'SELECT category_id FROM categories WHERE category_id = ? FOR UPDATE',
    'load'    => 'SELECT c.name, COUNT(b.book_id) AS titles
                  FROM categories c
                  LEFT JOIN books b ON b.category_id = c.category_id
                  WHERE c.category_id = ?
                  GROUP BY c.category_id, c.name',
    'delete'  => 'DELETE FROM categories WHERE category_id = ?',
    'blocked' => fn (array $category): ?string => (int) $category['titles'] > 0
        ? $in_use . ' ' . $category['name'] . ' has ' . plural((int) $category['titles'], 'book', 'books') . '.'
        : null,
    'done'    => fn (array $category): string => 'Category ' . $category['name'] . ' deleted.',
    'in_use'  => $in_use,
]);

$page_title = 'Delete a category';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a category</h1>
<?= delete_confirmation($view, 'category', $view['row']['name']) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
