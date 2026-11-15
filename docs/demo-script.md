# Demo running order

Every workstream shows its "Demo proof" from `docs/team.html`, in build order. Each member presents their own part
and must be able to explain its code. The times add up to about 20 minutes. Trim them once the lecturer confirms the
demo length.

## Before the demo (5 minutes, not in the timing)

1. Start Apache and MySQL, then run `composer dev:seed` to load the sample data.
2. Run `composer code:check:full`. Every line should say PASS, including "31 rules held".
3. Open three browser tabs: the app logged out, phpMyAdmin on `library_db`, and DevTools set to a 390px phone.
4. Keep these at hand:
   - Logins: `admin` / `admin123` and `librarian` / `library123`
   - Suspended members: Peter Kato and Rita Achan
   - A book with no copies left: *Weep Not, Child*
   - A member holding a book: Grace Atim

## Running order

| # | Time | Who | Workstream | Show this |
|---|---|---|---|---|
| 1 | 1 min | Antonia Nyakato | Project management | The task board, the seven stages, and this running order. |
| 2 | 2 min | Lukwago Joseph Bakumpe | Database design | Import `database/library_db.sql` into an empty server. Walk through `erd.png` and 3NF. Run each JOIN and aggregate in `queries.sql` and explain the result. Insert a second open loan of the same book for the same member in SQL: `uq_loans_one_open_per_member_book` refuses it. |
| 3 | 2 min | Maway Abili Etienne | Login and security | Open `/books/index.php` while logged out: you are sent to log in. Log in with a wrong password: it fails. Show a `$2y$` hash in `users`. Log in as `librarian`: no Delete links, and the role shows in the header. Log in as `admin`. |
| 4 | 2 min | Atyang Sarah | Authors and categories | Add a category, then pick it on a new book. Try to delete Chinua Achebe: refused because he has books. |
| 5 | 2 min | Mugalu Kevin | Books | Add, search, edit and delete a book. Try to delete *Things Fall Apart*: refused, 2 copies are out. Try a duplicate ISBN. |
| 6 | 2 min | Anai Achiek Wel Alith | Members | Add, search, edit and delete a member. Suspend and reactivate one. Add a member with `GRACE.ATIM@example.org`: duplicate email. Delete Grace Atim: refused, she holds a book. |
| 7 | 2 min | Unguech Unuech William Mawut | Issuing loans | Issue a book and point at the due date, 14 days ahead. Break each rule: a suspended member (Peter Kato), no copies left (*Weep Not, Child*), and the same book twice. |
| 8 | 2 min | Lwoto Josiah Jared | Returns and history | Return a book: the toast shows the copy count go up. Press Back and return it again: refused. Filter the loans list to Overdue. |
| 9 | 2 min | Ibrahim Ali Artan | Dashboard and reports | Note the dashboard numbers, issue or return a book, and show them change. Explain fines (`docs/fines.md`). Export the overdue CSV and open it in Excel. Show pagination on the loans list. |
| 10 | 2 min | Ali Henry Fred Alimo | Layout and rule testing | Phone width in DevTools: every table becomes cards. A delete prompt. Walk through `docs/rule-checklist.md`, then run `.github/scripts/check-rules.sh` live. |
| 11 | 1 min | Antonia Nyakato | Wrap-up | GitHub: one pull request per workstream, commits from every member, and the checks passing. |

## If something goes wrong

- **A page shows a database error:** run `composer dev:seed` and reload.
- **No network for the email code:** with `APP_ENV=local` the code appears on screen.
- **The demo runs long:** cut the "break each rule" steps to one rule each. `check-rules.sh` proves the rest in seconds.
