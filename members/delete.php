<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/delete_page.php';

// books_out: loans not returned yet. past_loans: returned ones, which must keep the member.
$view = delete_page($pdo, [
    'back'    => 'members/index.php',
    'missing' => 'That member is not on the list.',
    'lock'    => 'SELECT member_id FROM members WHERE member_id = ? FOR UPDATE',
    'load'    => 'SELECT m.full_name, m.member_number,
                         SUM(l.loan_id IS NOT NULL AND l.date_returned IS NULL) AS books_out,
                         SUM(l.date_returned IS NOT NULL) AS past_loans
                  FROM members m
                  LEFT JOIN loans l ON l.member_id = m.member_id
                  WHERE m.member_id = ?
                  GROUP BY m.member_id, m.full_name, m.member_number',
    'delete'  => 'DELETE FROM members WHERE member_id = ?',
    'blocked' => function (array $member): ?string {
        $out = (int) $member['books_out'];
        if ($out > 0) {
            return $member['full_name'] . ' cannot be deleted: they still hold ' . plural($out, 'book', 'books')
                . '. Return ' . ($out === 1 ? 'it' : 'them') . ' first.';
        }
        if ((int) $member['past_loans'] > 0) {
            return $member['full_name'] . ' cannot be deleted: they appear in past loans, and those records must keep them. '
                . 'Suspend the member instead.';
        }
        return null;
    },
    'done'    => fn (array $member): string => $member['full_name'] . ' deleted.',
    'in_use'  => 'That member cannot be deleted because loans refer to them.',
]);

$page_title = 'Delete a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a member</h1>
<?= delete_confirmation($view, 'member', $view['row']['full_name'], $view['row']['member_number']) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
