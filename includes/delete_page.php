<?php

// The four delete pages (books, members, authors, categories) share this. Only admins may delete, and a record
// that something still needs stays. The check and the delete run in one locked transaction, so a loan or a book
// added at the same moment cannot slip in between. The foreign keys (ON DELETE RESTRICT) back this up.

/**
 * Run a delete page. When its form is sent, this deletes the record or refuses, then goes back to the list.
 * When the page opens, it returns what the confirmation screen shows.
 *
 * $page holds:
 *   back     the list to return to, like 'books/index.php'
 *   missing  the message when no record has that id
 *   lock     SELECT ... FOR UPDATE that locks the record by its id
 *   load     SELECT that reads the record by its id, with the counts that can block the delete
 *   delete   DELETE of the record by its id
 *   blocked  fn (array $row): ?string, why the record cannot be deleted, or null when it can
 *   done     fn (array $row): string, the message once it is deleted
 *   in_use   the message when a foreign key refuses the delete (something was added since the check)
 * Every SQL statement takes the id as its only ? placeholder.
 *
 * @return array{id: int, row: array, reason: ?string, back: string}
 */
function delete_page(PDO $pdo, array $page): array
{
    require_admin($page['back']);
    $id = require_id($page['back'], $page['missing']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        try {
            $pdo->beginTransaction();
            $pdo->prepare($page['lock'])->execute([$id]);
            $row = fetch_row($pdo, $page['load'], [$id]);
            $reason = $row === null ? $page['missing'] : $page['blocked']($row);

            if ($reason !== null) {
                $pdo->rollBack();
                flash('error', $reason);
            } else {
                $pdo->prepare($page['delete'])->execute([$id]);
                $pdo->commit();
                flash('success', $page['done']($row));
            }
        } catch (PDOException $e) {
            rollback_if_open($pdo);
            if (!is_in_use_error($e)) {
                throw $e;
            }
            flash('error', $page['in_use']);
        }
        redirect($page['back']);
    }

    $row = fetch_row($pdo, $page['load'], [$id]);
    if ($row === null) {
        flash('error', $page['missing']);
        redirect($page['back']);
    }
    return ['id' => $id, 'row' => $row, 'reason' => $page['blocked']($row), 'back' => $page['back']];
}

/**
 * The body of a delete page: why the record must stay, or the question and the Delete button.
 * $noun names the record ('book'); $name and $detail describe it ('Things Fall Apart', 'ISBN 9780385474542').
 *
 * @param array{id: int, row: array, reason: ?string, back: string} $view
 */
function delete_confirmation(array $view, string $noun, string $name, string $detail = ''): string
{
    $list = url($view['back']);
    if ($view['reason'] !== null) {
        return '<p class="flash flash--error" role="alert">' . e($view['reason']) . '</p>'
            . '<p><a href="' . e($list) . '">Back to ' . e(basename(dirname($view['back']))) . '</a></p>';
    }

    $question = 'Delete ' . $name . '? This cannot be undone.';
    return '<p>Delete <strong>' . e($name) . '</strong>' . ($detail !== '' ? ' (' . e($detail) . ')' : '') . '? This cannot be undone.</p>'
        . '<form method="post" class="form" data-busy-form data-confirm="' . e($question) . '">'
        . csrf_field()
        . '<input type="hidden" name="id" value="' . $view['id'] . '">'
        . '<div class="form__actions">'
        . '<button class="btn btn--danger" type="submit" data-busy-label="Deleting…">Delete ' . e($noun) . '</button>'
        . '<a href="' . e($list) . '">Cancel</a>'
        . '</div></form>';
}
