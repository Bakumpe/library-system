<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/reports.php';

$statuses = ['out' => 'Out', 'returned' => 'Returned', 'overdue' => 'Overdue'];
$status = is_string($_GET['status'] ?? null) && isset($statuses[$_GET['status']]) ? $_GET['status'] : '';
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';

$conditions = [];
$params = [];
if ($status === 'out') {
    $conditions[] = 'l.date_returned IS NULL AND l.due_date >= CURDATE()';
} elseif ($status === 'returned') {
    $conditions[] = 'l.date_returned IS NOT NULL';
} elseif ($status === 'overdue') {
    $conditions[] = 'l.date_returned IS NULL AND l.due_date < CURDATE()';
}
if ($search !== '') {
    $conditions[] = '(m.full_name LIKE ? OR m.member_number LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$from = ' FROM loans l
          JOIN books b ON b.book_id = l.book_id
          JOIN members m ON m.member_id = l.member_id'
    . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '');

// Open loans first, the most urgent due date at the top.
$select = 'SELECT l.loan_id, l.date_issued, l.due_date, l.date_returned, ' . days_late_sql() . ' AS days_late,
                  l.date_returned IS NULL AND l.due_date < CURDATE() AS is_overdue,
                  b.title, m.member_number, m.full_name'
    . $from . ' ORDER BY l.date_returned IS NULL DESC, l.due_date, m.full_name, b.title';

/** Out, Returned or Overdue for one loan row. */
function loan_status(array $loan): string
{
    if ($loan['date_returned'] !== null) {
        return 'returned';
    }
    return $loan['is_overdue'] ? 'overdue' : 'out';
}

if (wants_csv()) {
    $stmt = $pdo->prepare($select); // $select holds only ? placeholders.
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt as $loan) {
        $rows[] = [
            $loan['full_name'], $loan['member_number'], $loan['title'], $loan['date_issued'], $loan['due_date'],
            $loan['date_returned'] ?? '', ucfirst(loan_status($loan)), $loan['days_late'], fine_for((int) $loan['days_late']),
        ];
    }
    send_csv('loans', ['Member', 'Member number', 'Book', 'Issued', 'Due', 'Returned', 'Status', 'Days late', 'Fine (UGX)'], $rows);
}

$pages = paginate(count_rows($pdo, $from, $params));
$loans = fetch_page($pdo, $select, $params, $pages);

$filtered = $search !== '' || $status !== '';
$pills = ['out' => 'pill--out', 'returned' => 'pill--returned', 'overdue' => 'pill--late'];

$page_title = 'Loans';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Loans</h1>
    <div class="page-head__actions">
        <a class="btn" href="<?= e(url('loans/issue.php')) ?>">Issue a book</a>
        <a class="btn btn--quiet" href="<?= e(url('loans/return.php')) ?>">Return a book</a>
    </div>
</div>

<form method="get" class="search search--filters" role="search">
    <div>
        <label for="search">Member</label>
        <input id="search" name="search" value="<?= e($search) ?>" type="search" placeholder="Name or member number">
    </div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status"><?= select_options($statuses, $status, 'All loans') ?></select>
    </div>
    <div class="search__actions">
        <button class="btn btn--quiet" type="submit">Search</button>
        <?php if ($filtered) : ?>
            <a href="<?= e(url('loans/index.php')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<div class="list-head">
    <p><?= $pages['total'] ?> <?= $pages['total'] === 1 ? 'loan' : 'loans' ?><?= $filtered ? ' match these filters' : '' ?>.
        Late books cost <?= e(money(FINE_PER_DAY)) ?> a day.</p>
    <?php if ($loans) : ?>
        <?= csv_link() ?>
    <?php endif; ?>
</div>

<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Member</th>
                <th>Book</th>
                <th>Issued</th>
                <th>Due</th>
                <th>Returned</th>
                <th>Status</th>
                <th class="num">Fine</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($loans as $loan) : ?>
            <?php $state = loan_status($loan);
            $fine = fine_for((int) $loan['days_late']); ?>
            <tr>
                <td class="cell--wide"><strong><?= e($loan['full_name']) ?></strong><br><small><?= e($loan['member_number']) ?></small></td>
                <td><?= e($loan['title']) ?></td>
                <td class="nowrap"><?= e($loan['date_issued']) ?></td>
                <td class="nowrap"><?= e($loan['due_date']) ?></td>
                <td class="nowrap"><?= e($loan['date_returned'] ?? '-') ?></td>
                <td><span class="pill <?= $pills[$state] ?>"><?= e($statuses[$state]) ?></span></td>
                <td class="num"><?= $fine > 0 ? e(money($fine)) . '<br><small>' . e($loan['days_late']) . ' days late</small>' : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$loans) : ?>
            <tr><td colspan="7"><?= $filtered ? 'No loans match these filters.' : 'No loans yet. Issue the first book.' ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= pagination_nav($pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
