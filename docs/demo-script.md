# Demo running order (10 minutes)

The brief asks for about 10 minutes: log in, show create, read, update and delete on books, members and loans,
then try to break each business rule on purpose. Questions about the code come after. Every member presents the
part they own and must be able to explain any of the code.

## Before the demo (not in the 10 minutes)

1. Start Apache and MySQL, then run `composer dev:seed` to load the sample data.
2. Run `composer code:check:full`. Every line should say PASS.
3. Open two browser tabs: the app logged out, and phpMyAdmin on `library_db`. Have DevTools ready at 390px.
4. Keep these at hand:
   - Logins: `admin` / `admin123` and `librarian` / `library123`
   - Suspended member: Peter Kato
   - A book with no copies left: *Weep Not, Child*
   - A member holding a book: Grace Atim
   - A book with 2 copies out: *Things Fall Apart*

## Running order

| # | Time | Who | Show this |
|---|---|---|---|
| 1 | 0:30 | Antonia Nyakato | Introduce the group and the system. |
| 2 | 1:00 | Maway Abili Etienne | Open `/books/index.php` while logged out: redirected to log in. Log in with a wrong password: it fails. Log in as `admin`. Show a `$2y$` hash in phpMyAdmin. |
| 3 | 1:00 | Lukwago Joseph Bakumpe | `erd.png`: six tables, keys, relationships. Run one JOIN and the loans-per-category query from `queries.sql`. |
| 4 | 1:30 | Mugalu Kevin | Books CRUD: add a book (pick the category Atyang Sarah just added), search and filter, edit it, delete it after the prompt. |
| 5 | 1:30 | Anai Achiek Wel Alith | Members CRUD: add, search, edit, suspend, delete after the prompt. |
| 6 | 1:30 | Unguech Unuech William Mawut, Lwoto Josiah Jared | Loans: issue a book (due in 14 days, copies go down), filter the list, return it (copies go back up). |
| 7 | 2:00 | Ali Henry Fred Alimo | Break each rule (list below), then switch to phone width. |
| 8 | 0:30 | Ibrahim Ali Artan | Dashboard: the numbers changed after the loan, the overdue fines, and an exported CSV. |
| 9 | 0:30 | Atyang Sarah | Add the category used in step 4. Try to delete an author who has books: refused. |

Step 9 is short. Atyang Sarah can do it just before step 4 so Kevin can pick the new category.

### The rules to break (step 7)

| Try | Expected |
|---|---|
| Issue *Weep Not, Child* | "There are no available copies of that book." |
| Issue a book to Peter Kato | "A suspended member cannot borrow books." |
| Issue the same book to the same member twice | "This member already has this book on loan." |
| Return the same loan twice (Back, then submit) | "This loan has already been returned." |
| Delete *Things Fall Apart* | Refused: copies are still out on loan |
| Delete Grace Atim | Refused: she still holds a book |

If time is short, run `bash .github/scripts/check-rules.sh` instead. It tries all 31 rules in a few seconds.

## If something goes wrong

- **A page shows a database error:** run `composer dev:seed` and reload.
- **No network for the email code:** with `APP_ENV=local` the code appears on screen.
