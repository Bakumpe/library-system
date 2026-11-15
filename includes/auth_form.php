<?php

// The sign-in pages share this: finding an account, and the password and code boxes.

/** The account with this username or email, or null. Usernames cannot contain @, so the two never clash. */
function find_user(PDO $pdo, string $login): ?array
{
    return fetch_row(
        $pdo,
        'SELECT user_id, password_hash, email, email_verified_at, full_name, role FROM users WHERE username = ? OR email = ?',
        [$login, $login]
    );
}

/** The emailed code as typed, digits only ("123 456" becomes "123456"). */
function post_code(): string
{
    return preg_replace('/\D/', '', post_text('code')) ?? '';
}

/**
 * A password box with its Show button and error line.
 * $options: autofocus (bool), strength (bool, a new password with the strength bar),
 * match (the id of the password this one repeats), link (HTML beside the label, like "Forgot password?").
 */
function password_field(array $errors, string $name, string $label, array $options = []): string
{
    $new = !empty($options['strength']) || isset($options['match']);
    $attrs = $new ? ' minlength="8" maxlength="72" autocomplete="new-password"' : ' autocomplete="current-password"';
    if (!empty($options['strength'])) {
        $attrs .= ' data-strength="' . e($name) . '-strength"';
    }
    if (isset($options['match'])) {
        $attrs .= ' data-match="' . e($options['match']) . '"';
    }
    if (!empty($options['autofocus'])) {
        $attrs .= ' autofocus';
    }

    $label_html = '<label for="' . e($name) . '">' . e($label) . '</label>';
    if (isset($options['link'])) {
        $label_html = '<div class="field__row">' . $label_html . $options['link'] . '</div>';
    }

    return '<div class="field">' . $label_html
        . '<div class="field__password">'
        . '<input id="' . e($name) . '" name="' . e($name) . '" type="password" required' . $attrs . field_attrs($errors, $name) . '>'
        . '<button type="button" class="field__reveal" data-reveal="' . e($name) . '" aria-pressed="false">Show</button>'
        . '</div>'
        . (!empty($options['strength'])
            ? '<div class="strength" id="' . e($name) . '-strength" aria-live="polite"><span class="strength__bar"></span>'
                . '<span class="strength__label">At least 8 characters</span></div>'
            : '')
        . field_error($errors, $name)
        . '</div>';
}

/** The box for the 6-digit code from the email. */
function code_field(array $errors, string $label, bool $autofocus = true): string
{
    return '<div class="field">'
        . '<label for="code">' . e($label) . '</label>'
        . '<input id="code" name="code" class="field__code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7"'
        . ' placeholder="000 000" autocomplete="one-time-code" required' . ($autofocus ? ' autofocus' : '') . field_attrs($errors, 'code') . '>'
        . field_error($errors, 'code')
        . '</div>';
}
