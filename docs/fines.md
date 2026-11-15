# Fines: worked out from the dates, not stored

**Decision:** there is no fines table. A loan's fine is

```
days late × UGX 500
days late = days from the due date to the return date (or to today while the book is still out), never below 0
```

The rate is `FINE_PER_DAY` in `includes/reports.php`. The SQL is `DAYS_LATE_SQL` in the same file:

```sql
GREATEST(DATEDIFF(COALESCE(l.date_returned, CURDATE()), l.due_date), 0)
```

## Why a calculation and not a table

1. **It can't drift.** The due date and the return date are already in `loans`. A stored fine would have to be
   updated every day a book is late, and one missed update would make it wrong. It would also break 3NF.
2. **Every page agrees.** The dashboard, the loans list and the CSV all use the same SQL.
3. **One place to change.** A new rate means changing `FINE_PER_DAY` only.

## What a table would add, and when to switch

Only if the library starts recording **payments** or **waived** fines, which the brief doesn't ask for. Then add
`fine_payments (payment_id, loan_id, amount, paid_on, user_id)` and work out what is owed as the fine minus the
payments. The fine itself still wouldn't be stored.

## Where it shows

| Page | What it shows |
|---|---|
| `dashboard/index.php` | Total fines owed now, and each overdue loan with its days late and fine. Export CSV. |
| `loans/index.php` | A Fine column: running for loans still out, final for books returned late. Export CSV. |
| `.github/scripts/check-rules.sh` | Checks the CSV fine is exactly days late × 500. |
