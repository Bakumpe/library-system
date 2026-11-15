<?php

// The add and edit author forms share this.

const AUTHOR_BLANK = ['name' => '', 'country' => ''];

/** @return array<string, string> */
function author_form_input(): array
{
    return ['name' => post_text('name'), 'country' => post_text('country')];
}

/**
 * @param array<string, string> $form
 * @return array<string, string>
 */
function author_form_errors(array $form): array
{
    return array_filter([
        'name'    => validate_required($form['name'], 'Name', 120),
        'country' => validate_required($form['country'], 'Country', 80),
    ]);
}

/** Save the author (insert when $author_id is 0). Returns the field errors, empty when it saved. */
function save_author(PDO $pdo, array $form, int $author_id = 0): array
{
    try {
        if ($author_id === 0) {
            $pdo->prepare('INSERT INTO authors (name, country) VALUES (?, ?)')->execute([$form['name'], $form['country']]);
        } else {
            $pdo->prepare('UPDATE authors SET name = ?, country = ? WHERE author_id = ?')
                ->execute([$form['name'], $form['country'], $author_id]);
        }
        return [];
    } catch (PDOException $e) {
        if (!is_duplicate_error($e)) {
            throw $e;
        }
        return ['name' => 'That author from that country is already on the list.'];
    }
}

/**
 * @param array<string, string> $form
 * @param array<string, string> $errors
 */
function author_fields(array $form, array $errors): string
{
    ob_start();
    ?>
    <label for="name">Name</label>
    <input id="name" name="name" value="<?= e($form['name']) ?>" maxlength="120" required<?= field_attrs($errors, 'name') ?>>
    <?= field_error($errors, 'name') ?>

    <label for="country">Country</label>
    <input id="country" name="country" value="<?= e($form['country']) ?>" maxlength="80" required<?= field_attrs($errors, 'country') ?>>
    <?= field_error($errors, 'country') ?>
    <?php
    return (string) ob_get_clean();
}
