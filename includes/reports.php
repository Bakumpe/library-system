<?php

// Fines, pagination and CSV export, shared by the dashboard and the list pages.

// Fines are worked out from the dates, not stored: see docs/fines.md for why.
const FINE_PER_DAY = 500; // Uganda shillings for each day a book is late.
const PER_PAGE = 20;

/**
 * SQL for the days a loan is late: up to today while it is out, up to the return date once it is back.
 * Never below zero. Use it with the loans table aliased as l.
 */
function days_late_sql(): string
{
    return 'GREATEST(DATEDIFF(COALESCE(l.date_returned, CURDATE()), l.due_date), 0)';
}

/** The fine for a number of late days. */
function fine_for(int $days_late): int
{
    return max(0, $days_late) * FINE_PER_DAY;
}

/** An amount of money as the library shows it, like "UGX 3,500". */
function money(int $amount): string
{
    return 'UGX ' . number_format($amount);
}

/**
 * Which slice of a list to show. Reads ?page= and keeps it between 1 and the last page.
 *
 * @return array{page: int, pages: int, total: int, offset: int, limit: int}
 */
function paginate(int $total, int $per_page = PER_PAGE): array
{
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($pages, max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)));

    return ['page' => $page, 'pages' => $pages, 'total' => $total, 'offset' => ($page - 1) * $per_page, 'limit' => $per_page];
}

/** How many rows a FROM ... WHERE ... part matches. $from holds only ? placeholders, never values. */
function count_rows(PDO $pdo, string $from, array $params): int
{
    $sql = 'SELECT COUNT(*)' . $from;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * One page of a query's rows. LIMIT and OFFSET are bound as whole numbers after the other values.
 *
 * @param array{page: int, pages: int, total: int, offset: int, limit: int} $pages
 * @return list<array<string, mixed>>
 */
function fetch_page(PDO $pdo, string $select, array $params, array $pages): array
{
    $sql = $select . ' LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($sql);
    foreach (array_values($params) as $i => $value) {
        $stmt->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(count($params) + 1, $pages['limit'], PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $pages['offset'], PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** The current page's address with some query values changed, keeping the filters. */
function query_link(array $changes): string
{
    $query = array_filter(array_merge($_GET, $changes), fn ($value) => $value !== null && $value !== '');
    return '?' . http_build_query($query);
}

/**
 * Previous and next links with "21 to 40 of 57". Prints nothing when everything fits on one page.
 *
 * @param array{page: int, pages: int, total: int, offset: int, limit: int} $p
 */
function pagination_nav(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $first = $p['offset'] + 1;
    $last = min($p['total'], $p['offset'] + $p['limit']);

    $html = '<nav class="pager" aria-label="Pages">';
    $html .= $p['page'] > 1
        ? '<a href="' . e(query_link(['page' => $p['page'] - 1])) . '" rel="prev">Previous</a>'
        : '<span aria-disabled="true">Previous</span>';
    $html .= '<span>' . $first . ' to ' . $last . ' of ' . $p['total'] . '</span>';
    $html .= $p['page'] < $p['pages']
        ? '<a href="' . e(query_link(['page' => $p['page'] + 1])) . '" rel="next">Next</a>'
        : '<span aria-disabled="true">Next</span>';
    return $html . '</nav>';
}

/** True when the list was asked for as a CSV file (?export=csv). */
function wants_csv(): bool
{
    return ($_GET['export'] ?? '') === 'csv';
}

/** A link that downloads the current list, with the same filters, as a CSV file. */
function csv_link(string $label = 'Export CSV'): string
{
    return '<a class="btn btn--quiet" href="' . e(query_link(['export' => 'csv', 'page' => null])) . '" download>' . e($label) . '</a>';
}

/**
 * Send rows as a CSV download and stop. Excel opens it directly; the BOM keeps names like "Okot p'Bitek" readable.
 *
 * @param list<string> $header
 * @param iterable<array<int|string, mixed>> $rows
 */
function send_csv(string $name, array $header, iterable $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_safe', array_values($row)), ',', '"', '');
    }
    fclose($out);
    exit;
}

/**
 * Stop a spreadsheet from running a cell as a formula. A value starting with = + - or @ gets a leading quote,
 * unless it is just a number or phone number like +256700555001.
 */
function csv_safe(mixed $value): string
{
    $value = (string) $value;
    if (preg_match('/^[=+\-@\t\r]/', $value) && !preg_match('/^[+-]?[\d ().-]+$/', $value)) {
        return "'" . $value;
    }
    return $value;
}
