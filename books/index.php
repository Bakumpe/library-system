<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$search = trim((string) ($_GET['q'] ?? ''));
$authorFilter = (int) ($_GET['author_id'] ?? 0);
$categoryFilter = (int) ($_GET['category_id'] ?? 0);

$authorStmt = $pdo->query('SELECT author_id, name FROM authors ORDER BY name');
$authors = $authorStmt->fetchAll();

$categoryStmt = $pdo->query('SELECT category_id, name FROM categories ORDER BY name');
$categories = $categoryStmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT b.book_id, b.isbn, b.title, a.name AS author_name, c.name AS category_name,
            b.publisher, b.year_published, b.total_copies, b.available_copies,
            COALESCE((SELECT COUNT(*)
                     FROM loans l
                     WHERE l.book_id = b.book_id AND l.date_returned IS NULL), 0) AS copies_out
     FROM books b
     JOIN authors a ON a.author_id = b.author_id
     JOIN categories c ON c.category_id = b.category_id
     WHERE (? = "" OR b.title LIKE ? OR a.name LIKE ?)
       AND (? = 0 OR b.author_id = ?)
       AND (? = 0 OR b.category_id = ?)
     ORDER BY b.title'
);

$like = '%' . $search . '%';
$stmt->execute([
    $search,
    $like,
    $like,
    $authorFilter,
    $authorFilter,
    $categoryFilter,
    $categoryFilter,
]);
$books = $stmt->fetchAll();

$page_title = 'Books';
require __DIR__ . '/../includes/header.php';
?>
<h1>Books</h1>

<div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; flex-wrap:wrap;">
    <a class="btn" href="<?= e(url('books/create.php')) ?>">Add book</a>
</div>

<form method="get" class="search" role="search">
    <label for="q">Search books</label>
    <input id="q" name="q" value="<?= e($search) ?>" placeholder="Title or author">

    <label for="author_id">Author</label>
    <select id="author_id" name="author_id">
        <option value="0">All authors</option>
        <?php foreach ($authors as $author) : ?>
            <option value="<?= e((string) $author['author_id']) ?>"<?= (string) $author['author_id'] === (string) $authorFilter ? ' selected' : '' ?>>
                <?= e($author['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id">
        <option value="0">All categories</option>
        <?php foreach ($categories as $category) : ?>
            <option value="<?= e((string) $category['category_id']) ?>"<?= (string) $category['category_id'] === (string) $categoryFilter ? ' selected' : '' ?>>
                <?= e($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="btn btn--quiet" type="submit">Apply filters</button>
</form>

<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>ISBN</th>
                <th>Title</th>
                <th>Author</th>
                <th>Category</th>
                <th class="num">Copies</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($books as $book) : ?>
            <tr>
                <td><?= e($book['isbn']) ?></td>
                <td>
                    <strong><?= e($book['title']) ?></strong><br>
                    <small><?= e($book['publisher']) ?> · <?= e((string) $book['year_published']) ?></small>
                </td>
                <td><?= e($book['author_name']) ?></td>
                <td><?= e($book['category_name']) ?></td>
                <td class="num"><?= e((string) $book['available_copies']) ?> / <?= e((string) $book['total_copies']) ?></td>
                <td>
                    <?php
                    $copiesOut = (int) $book['copies_out'];
                    $status = $copiesOut > 0 ? $copiesOut . ' out' : 'On shelf';
                    $class = $copiesOut > 0 ? 'pill pill--out' : 'pill pill--active';
                    ?>
                    <span class="<?= e($class) ?>"><?= e($status) ?></span>
                </td>
                <td>
                    <a class="btn btn--quiet" href="<?= e(url('books/edit.php?id=' . (int) $book['book_id'])) ?>">Edit</a>
                    <a class="btn btn--danger" href="<?= e(url('books/delete.php?id=' . (int) $book['book_id'])) ?>">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$books) : ?>
            <tr><td colspan="7">No books match that search.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
