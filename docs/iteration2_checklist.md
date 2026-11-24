# Iteration 2 checklist: Database Foundation and Site Skeleton

Requirements: FR-PUB-02, FR-PUB-03, Section 6, NFR-02 (start). Tag the repo `iter-02` when everything is merged to `main`.

| Deliverable | Where | Done? |
|---|---|---|
| Schema script, all 20 Section 6.2 tables (22 with `student_assignments` and `national_themes`), keys, indexes, nullable acting-user columns | `sql/001_schema.sql` | [ ] loaded on my machine |
| Seeds: 50 states + DC + National, grades 6-12, categories, fields, provider types, 29 themes, 5 roles, dev user | `sql/002_seed_reference.sql` | [ ] row counts checked |
| Hand-entered sample scholarships | `sql/004_seed_sample_scholarships.sql` | [ ] list page shows them |
| Shared includes: config (git-ignored), PDO, header, nav, footer, dev banner | `config/`, `includes/` | [ ] banner visible on every page |
| Security helpers: `e()`, CSRF, `require_role()`, `current_user_id()` | `includes/security.php`, `includes/auth.php` | [ ] |
| About, FAQ, Contact (saves to DB, server-side validation, CSRF) | `about.php`, `faq.php`, `contact.php` | [ ] sent a test message |
| Basic read-only scholarship list | `scholarships.php` | [ ] filters work |

## Quick check on your machine (about 5 minutes)
1. Start Apache and MySQL, create the `scholarships` database, import `001`, `002`, `003`, `004` in that order.
2. Copy `config/config.sample.php` to `config/config.php` (`git status` must NOT list it).
3. Open the Home page: you should see the "Development mode" banner at the top and the navigation bar.
4. Open Find scholarships: 4 SAMPLE rows (the Texas one is past its deadline, so it is hidden). Tick "Include past deadlines": 5 rows.
5. Contact: send a message; confirm the row in the `contact_messages` table (phpMyAdmin). Try an empty form and a bad email to see the errors.
6. `php tests/cleaner_test.php` should print `0 failed`.

Note: the Wisconsin sample's deadline is 2026-12-01, so after that date it also moves behind "Include past deadlines".

## Row counts to expect after the seeds
roles 5, states 51, grade_levels 7, national_themes 29, categories 15, fields_of_study 14, provider_types 7, users 1 (dev user).

## Watch out
- `php tests/import_db_test.php` EMPTIES the scholarships and providers tables, including the sample rows. Re-run `004_seed_sample_scholarships.sql` afterwards.
- Do not put this site on a public URL without HTTP Basic Auth until login arrives in Iteration 7.
- `users` is a working draft until the instructor sends the official users + registrations schema.

## Git steps
```
git checkout -b iter02/db-and-skeleton
git add sql includes config/config.sample.php *.php docs README.md
git commit -m "FR-PUB-02, FR-PUB-03: schema, seeds, shared layout, public pages, sample list"
git push -u origin iter02/db-and-skeleton
# open a pull request; a teammate reviews and merges; then:
git tag iter-02 && git push origin iter-02
```
