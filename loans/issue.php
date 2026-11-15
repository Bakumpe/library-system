<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

const LOAN_DAYS = 14;

/**
 * Check the three loan rules inside the open transaction, locking the rows it reads.
 * Returns the message to show when a rule is broken, or null when the loan may go ahead.
 */
function issue_rule_error(PDO $pdo, int $member_id, int $book_id): ?string
{
    $member = fetch_row($pdo, 'SELECT status FROM members WHERE member_id = ? FOR UPDATE', [$member_id]);
    if ($member === null) {
        return 'That member could not be found.';
    }
    if ($member['status'] !== 'active') {
        return 'A suspended member cannot borrow books.';
    }

    $book = fetch_row($pdo, 'SELECT available_copies FROM books WHERE book_id = ? FOR UPDATE', [$book_id]);
    if ($book === null) {
        return 'That book could not be found.';
    }
    if ((int) $book['available_copies'] < 1) {
        return 'There are no available copies of that book.';
    }

    $open = fetch_row(
        $pdo,
        'SELECT loan_id FROM loans WHERE member_id = ? AND book_id = ? AND date_returned IS NULL LIMIT 1 FOR UPDATE',
        [$member_id, $book_id]
    );
    return $open !== null ? 'This member already has this book on loan.' : null;
}

$error = null;
$member_id = 0;
$book_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $member_id = post_id('member_id');
    $book_id = post_id('book_id');

    if ($member_id === 0) {
        $error = 'Choose a valid member.';
    } elseif ($book_id === 0) {
        $error = 'Choose a valid book.';
    } else {
        try {
            // One transaction: the loan is added and the copy count drops together, or neither happens.
            $pdo->beginTransaction();
            $error = issue_rule_error($pdo, $member_id, $book_id);

            if ($error === null) {
                $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?')->execute([$book_id]);
                $pdo->prepare(
                    'INSERT INTO loans (member_id, book_id, date_issued, due_date)
                     VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY))'
                )->execute([$member_id, $book_id, LOAN_DAYS]);
                $pdo->commit();

                flash('success', 'Book issued. It is due in ' . LOAN_DAYS . ' days.');
                redirect('loans/index.php');
            }
            $pdo->rollBack();
        } catch (PDOException $e) {
            rollback_if_open($pdo);
            if (!is_duplicate_error($e)) {
                throw $e;
            }
            // uq_loans_one_open_per_member_book caught a second open loan, for example a double click.
            $error = 'This member already has this book on loan.';
        }
    }
}

// Suspended members and books with no copies left show in the lists but cannot be picked.
$members = [];
$suspended = [];
foreach ($pdo->query('SELECT member_id, member_number, full_name, status FROM members ORDER BY status, full_name') as $member) {
    $id = (int) $member['member_id'];
    $members[$id] = $member['full_name'] . ' (' . $member['member_number'] . ($member['status'] === 'suspended' ? ', suspended' : '') . ')';
    if ($member['status'] === 'suspended') {
        $suspended[] = $id;
    }
}
$books = [];
$none_left = [];
foreach ($pdo->query('SELECT book_id, title, available_copies FROM books ORDER BY title') as $book) {
    $id = (int) $book['book_id'];
    $books[$id] = $book['title'] . ' (' . $book['available_copies'] . ' available)';
    if ((int) $book['available_copies'] < 1) {
        $none_left[] = $id;
    }
}
$due_date = fetch_row($pdo, 'SELECT DATE_ADD(CURDATE(), INTERVAL ? DAY) AS due', [LOAN_DAYS])['due'];

if ($error !== null) {
    flash_now('error', $error);
}

$page_title = 'Issue a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Issue a book</h1>
<p>Loans issued today are due on <strong><?= e($due_date) ?></strong> (<?= LOAN_DAYS ?> days after issue).</p>

<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <label for="member_id">Member</label>
    <select id="member_id" name="member_id" required>
        <?= select_options($members, $member_id ?: null, 'Choose a member', $suspended) ?>
    </select>

    <label for="book_id">Book</label>
    <select id="book_id" name="book_id" required>
        <?= select_options($books, $book_id ?: null, 'Choose a book', $none_left) ?>
    </select>

    <button class="btn" type="submit" data-busy-label="Issuing…">Issue book</button>
</form>
<p><a href="<?= e(url('loans/index.php')) ?>">Back to loans</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
