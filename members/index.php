<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/member_form.php';
require_once __DIR__ . '/../includes/reports.php';

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if (!isset(MEMBER_STATUSES[$status])) {
    $status = '';
}

// books_out counts each member's loans that are not returned yet. Each filter is skipped when it is empty.
$where = ' WHERE (? = \'\' OR m.full_name LIKE ? OR m.member_number LIKE ?)
             AND (? = \'\' OR m.status = ?)';
$like = '%' . $search . '%';
$params = [$search, $like, $like, $status, $status];
$select = 'SELECT m.member_id, m.member_number, m.full_name, m.email, m.phone, m.status, m.date_joined,
                  COUNT(l.loan_id) AS books_out
           FROM members m
           LEFT JOIN loans l ON l.member_id = m.member_id AND l.date_returned IS NULL'
    . $where . '
           GROUP BY m.member_id, m.member_number, m.full_name, m.email, m.phone, m.status, m.date_joined
           ORDER BY m.full_name';

if (wants_csv()) {
    $stmt = $pdo->prepare($select); // $select holds only ? placeholders.
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt as $member) {
        $rows[] = [
            $member['full_name'], $member['member_number'], $member['email'], $member['phone'],
            MEMBER_STATUSES[$member['status']], $member['date_joined'], $member['books_out'],
        ];
    }
    send_csv('members', ['Name', 'Member number', 'Email', 'Phone', 'Status', 'Joined', 'Books out'], $rows);
}

$pages = paginate(count_rows($pdo, ' FROM members m' . $where, $params));
$members = fetch_page($pdo, $select, $params, $pages);

$filtered = $search !== '' || $status !== '';
$back = query_link([]);

$page_title = 'Members';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Members</h1>
    <a class="btn" href="<?= e(url('members/create.php')) ?>">Add a member</a>
</div>

<form method="get" class="search search--filters" role="search">
    <div>
        <label for="q">Name</label>
        <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Name or member number">
    </div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status"><?= select_options(MEMBER_STATUSES, $status, 'All members') ?></select>
    </div>
    <div class="search__actions">
        <button class="btn btn--quiet" type="submit">Search</button>
        <?php if ($filtered) : ?>
            <a href="<?= e(url('members/index.php')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<div class="list-head">
    <p><?= $pages['total'] ?> <?= $pages['total'] === 1 ? 'member' : 'members' ?><?= $filtered ? ($pages['total'] === 1 ? ' matches' : ' match') . ' these filters' : '' ?>.</p>
    <?php if ($members) : ?>
        <?= csv_link() ?>
    <?php endif; ?>
</div>

<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Number</th>
                <th>Contact</th>
                <th>Status</th>
                <th class="num">Books out</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $member) : ?>
            <?php $active = $member['status'] === 'active'; ?>
            <tr>
                <td class="cell--wide"><strong><?= e($member['full_name']) ?></strong></td>
                <td><?= e($member['member_number']) ?></td>
                <td><?= e($member['email']) ?><br><small><?= e($member['phone']) ?></small></td>
                <td><span class="pill pill--<?= $active ? 'active' : 'suspended' ?>"><?= e(MEMBER_STATUSES[$member['status']]) ?></span></td>
                <td class="num"><?= e($member['books_out']) ?></td>
                <td class="row-actions">
                    <a href="<?= e(url('members/edit.php?id=' . (int) $member['member_id'])) ?>">Edit</a>
                    <form method="post" action="<?= e(url('members/status.php')) ?>"<?= $active ? ' data-confirm="' . e('Suspend ' . $member['full_name'] . '? They will not be able to borrow books until reactivated.') . '"' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $member['member_id'] ?>">
                        <input type="hidden" name="status" value="<?= $active ? 'suspended' : 'active' ?>">
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <button class="link-button" type="submit"><?= $active ? 'Suspend' : 'Reactivate' ?></button>
                    </form>
                    <?php if (is_admin()) : ?>
                        <a href="<?= e(url('members/delete.php?id=' . (int) $member['member_id'])) ?>">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$members) : ?>
            <tr><td colspan="6"><?= $filtered ? 'No members match these filters.' : 'No members yet. Add the first one.' ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= pagination_nav($pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
