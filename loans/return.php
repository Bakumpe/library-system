<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Record a return inside the open transaction.
 * Returns the message to show when it cannot be recorded, or null when it worked.
 */
function return_loan(PDO $pdo, int $loan_id): ?string
{
    $loan = fetch_row($pdo, 'SELECT book_id, date_returned FROM loans WHERE loan_id = ? FOR UPDATE', [$loan_id]);
    if ($loan === null) {
        return 'That loan could not be found.';
    }
    if ($loan['date_returned'] !== null) {
        return 'This loan has already been returned.';
    }

    $stmt = $pdo->prepare(
        'UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ? AND available_copies < total_copies'
    );
    $stmt->execute([(int) $loan['book_id']]);
    if ($stmt->rowCount() !== 1) {
        return 'The book copy count is already at its limit; the return was not recorded.';
    }

    $pdo->prepare('UPDATE loans SET date_returned = CURDATE() WHERE loan_id = ?')->execute([$loan_id]);
    return null;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $loan_id = post_id('loan_id');

    if ($loan_id === 0) {
        $error = 'Choose a valid loan to return.';
    } else {
        // One transaction: the loan is closed and the copy goes back on the shelf together, or neither happens.
        // If the database fails midway, PHP ends the request and MySQL undoes the open transaction.
        $pdo->beginTransaction();
        $error = return_loan($pdo, $loan_id);

        if ($error === null) {
            $pdo->commit();
            $book = fetch_row(
                $pdo,
                'SELECT b.title, b.available_copies, b.total_copies FROM loans l JOIN books b ON b.book_id = l.book_id WHERE l.loan_id = ?',
                [$loan_id]
            );
            flash('success', sprintf(
                'Book return recorded. "%s" now has %d of %d copies on the shelf.',
                $book['title'],
                $book['available_copies'],
                $book['total_copies']
            ));
            redirect('loans/index.php');
        }
        $pdo->rollBack();
    }
}

$loans = $pdo->query(
    'SELECT l.loan_id, l.date_issued, l.due_date, b.title, m.member_number, m.full_name
     FROM loans l
     JOIN books b ON b.book_id = l.book_id
     JOIN members m ON m.member_id = l.member_id
     WHERE l.date_returned IS NULL
     ORDER BY l.due_date, m.full_name, b.title'
)->fetchAll();

if ($error !== null) {
    flash_now('error', $error);
}

$page_title = 'Return a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Return a book</h1>

<?php if (!$loans) : ?>
    <p>There are no books on loan right now.</p>
<?php else : ?>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Member</th><th>Book</th><th>Issued</th><th>Due</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($loans as $loan) : ?>
                <tr>
                    <td><?= e($loan['full_name']) ?> (<?= e($loan['member_number']) ?>)</td>
                    <td><?= e($loan['title']) ?></td>
                    <td class="nowrap"><?= e($loan['date_issued']) ?></td>
                    <td class="nowrap"><?= e($loan['due_date']) ?></td>
                    <td>
                        <form method="post" data-busy-form data-confirm="<?= e('Record the return of ' . $loan['title'] . ' from ' . $loan['full_name'] . '?') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="loan_id" value="<?= (int) $loan['loan_id'] ?>">
                            <button class="btn" type="submit" data-busy-label="Saving…">Record return</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p><a href="<?= e(url('loans/index.php')) ?>">Back to loans</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
