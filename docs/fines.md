# Fines: worked out from the dates, not stored

**Decision:** there is no fines table. A loan's fine is

```
days late × UGX 500
days late = days from the due date to the return date (or to today while the book is still out), never below 0
```

The rate is `FINE_PER_DAY` in `includes/reports.php`. The SQL is `days_late_sql()` in the same file:

```sql
GREATEST(DATEDIFF(COALESCE(l.date_returned, CURDATE()), l.due_date), 0)
```

## Why a calculation and not a table

1. **It cannot go wrong.** Every fact the fine needs, the due date and the return date, is already in `loans`.
   A stored fine would repeat those facts and would have to be updated every night while a book is late.
   If that job missed a day, the table would disagree with the dates. Storing a value that can be worked out
   from other columns breaks third normal form for the same reason.
2. **It is always current.** The dashboard, the loans list and the CSV all call the same function, so they show
   the same amount on the same day.
3. **It changes in one place.** If the library changes the rate, only `FINE_PER_DAY` changes, and every loan,
   past and present, uses the new rate.

## What a table would add, and when to switch

A table would be needed to record **payments**, or a fine that a librarian **waives**. Neither is in the brief.
If they are added later, the table should be `fine_payments (payment_id, loan_id, amount, paid_on, user_id)`, and
the amount owed would still be calculated: the fine from the dates minus the payments. The fine itself would still
not be stored.

## Where it shows

| Page | What it shows |
|---|---|
| `dashboard/index.php` | Total fines owed now, and each overdue loan with its days late and fine. Export CSV. |
| `loans/index.php` | A Fine column: running for loans still out, final for books returned late. Export CSV. |
| `.github/scripts/check-rules.sh` | Checks the CSV fine is exactly days late × 500. |
