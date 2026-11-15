<?php

// Suspend or reactivate a member from the list. POST only: it changes data.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/member_form.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('members/index.php');
}
require_csrf();

$member_id = post_id('id');
$status = post_text('status');
$filters = post_text('back'); // The list's ?query, so the user lands on the same page and filters.
$back = 'members/index.php' . (str_starts_with($filters, '?') ? $filters : '');

if ($member_id === 0 || !isset(MEMBER_STATUSES[$status])) {
    flash('error', 'Choose a member and a status.');
    redirect('members/index.php');
}

$pdo->prepare('UPDATE members SET status = ? WHERE member_id = ?')->execute([$status, $member_id]);
$member = fetch_row($pdo, 'SELECT full_name FROM members WHERE member_id = ?', [$member_id]);
$full_name = $member['full_name'] ?? null;

if ($full_name === null) {
    flash('error', 'That member is not on the list.');
} elseif ($status === 'suspended') {
    flash('success', $full_name . ' is suspended and cannot borrow books until reactivated.');
} else {
    flash('success', $full_name . ' is active again and can borrow books.');
}
redirect($back);
