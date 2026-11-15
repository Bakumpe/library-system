<?php

// The add and edit category forms share this.

const CATEGORY_BLANK = ['name' => '', 'description' => ''];

/** @return array<string, string> */
function category_form_input(): array
{
    return ['name' => post_text('name'), 'description' => post_text('description')];
}

/**
 * @param array<string, string> $form
 * @return array<string, string>
 */
function category_form_errors(array $form): array
{
    return array_filter([
        'name'        => validate_required($form['name'], 'Name', 80),
        'description' => mb_strlen($form['description']) > 255 ? 'Description must be 255 characters or fewer.' : null,
    ]);
}

/** Save the category (insert when $category_id is 0). Returns the field errors, empty when it saved. */
function save_category(PDO $pdo, array $form, int $category_id = 0): array
{
    $description = $form['description'] !== '' ? $form['description'] : null;
    try {
        if ($category_id === 0) {
            $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)')->execute([$form['name'], $description]);
        } else {
            $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE category_id = ?')
                ->execute([$form['name'], $description, $category_id]);
        }
        return [];
    } catch (PDOException $e) {
        if (!is_duplicate_error($e)) {
            throw $e;
        }
        return ['name' => 'Another category already has that name.'];
    }
}

/**
 * @param array<string, string> $form
 * @param array<string, string> $errors
 */
function category_fields(array $form, array $errors): string
{
    ob_start();
    ?>
    <label for="name">Name</label>
    <input id="name" name="name" value="<?= e($form['name']) ?>" maxlength="80" required<?= field_attrs($errors, 'name') ?>>
    <?= field_error($errors, 'name') ?>

    <label for="description">Description (optional)</label>
    <input id="description" name="description" value="<?= e($form['description']) ?>" maxlength="255"<?= field_attrs($errors, 'description') ?>>
    <?= field_error($errors, 'description') ?>
    <?php
    return (string) ob_get_clean();
}
