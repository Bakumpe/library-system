# Database work notes (Lukwago Joseph Bakumpe)

## Done

- `database/library_db.sql`: the database itself now stops a member from having two open loans of the same book
  (see `open_loan_guard` in [database-design.md](database-design.md)). Returned loans don't block a new one.
- `database/queries.sql`: the four report queries.
- `database/erd.png`: six tables, including the users email and code columns.
- `docs/database-design.md`: the normalization write-up for the report.
- `.github/scripts/check-database.sh`: now checks that a second open loan is rejected.

## Why the loan rule is in the schema

A check in PHP alone can be skipped by another page, or beaten by two requests at the same moment. The UNIQUE key
can't. The PHP check still runs first so the user gets a clear message.

## Tested

Imported the schema into a scratch MariaDB 10.11 database and ran all four queries. A second open loan for the
same member and book was rejected; a second loan after the first was returned was allowed.

## Still worth doing

- Test edge cases: a loan due today, a book returned late, a category with no loans.
- When a table or key changes, update `erd.png` and `database-design.md` in the same pull request.

## Reloading the sample data

With Apache and MySQL running, run `composer dev:seed` in Git Bash and answer `y`. It wipes `library_db` and
loads the sample data again.
