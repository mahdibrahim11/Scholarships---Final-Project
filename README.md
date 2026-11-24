# Pathways to Scholarships (`scholarships`)

ICS 325 final project, Fall 2026. A PHP 8 / MySQL / Bootstrap 5 web app that helps parents and students find
scholarships by **state, grade (6-12), and field of study**, and that **batch-loads, cleans, de-duplicates, and
exports** the Data Science class's scholarship files.

Requirements: see the course spec (`ics325_scholarships_requirements.md`). Data Science references are in `docs/reference/`.

## Quick start (XAMPP / MAMP / WAMP)

1. Put this folder in `htdocs/scholarships` (so the site is `http://localhost/scholarships/`).
2. In phpMyAdmin (or the `mysql` CLI) create a database:
   `CREATE DATABASE scholarships CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
3. Import, in order: `sql/001_schema.sql`, `sql/002_seed_reference.sql`, `sql/003_seed_assignments.sql`, `sql/004_seed_sample_scholarships.sql` (5 fake demo scholarships so the list page shows something; delete them with `sql/004_remove_sample_scholarships.sql` before the real data load in Iteration 3).
4. Copy `config/config.sample.php` to `config/config.php` and set your DB user/password. **Never commit `config.php`.**
5. Open `http://localhost/scholarships/`. Load the sample data (optional):
   `php tools/load_csv.php tests/sample_files/*.csv --commit`

Needs PHP 8.1+ with `pdo_mysql` and `mbstring`.

## Run the tests

```
php tests/cleaner_test.php        # 107 checks on the cleaning rules (no database needed)
php tests/import_db_test.php      # 23 checks: staging, commit, duplicates, rollback (LOCAL database only: it empties tables)
```

## Folder layout

```
config/            config.sample.php (copy to config.php; git-ignored)
sql/               versioned schema + seed scripts
includes/          shared PHP: bootstrap, db, security (escape/CSRF), auth stubs, header/footer, audit
my/                student and parent pages (profiles, matches, saved, deadlines, dashboard, AJAX save/status)
includes/import/   cleaner.php (rules), pipeline.php (row + file validation), store.php (stage/commit/rollback)
admin/             admin pages (all must call require_role())
assets/css/        site.css (design tokens at the top)
tests/             unit + DB tests, sample import files with deliberate problems
tools/             load_csv.php (CLI batch loader), gen_seed.py (regenerates the 58-student seed)
docs/              field inventory, user stories, ERD, plans, open questions
```

## Rules every page follows (spec: Definition of Done)

- Queries: **prepared statements only** (`db()->prepare(...)`). Never put a variable inside SQL text, except an `(int)` cast.
- Output: wrap anything from a user or the database in `e(...)`.
- Forms that change data: `<?= csrf_field() ?>` in the form and `require_csrf();` in the handler.
- Admin pages: `require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);` at the top (a no-op stub until Iteration 7).
- Acting user: `current_user_id()`, never a hard-coded number.
- Check the page at 375px, 768px, and 1280px wide.

## Git workflow

- `main` is always demoable. Nobody commits directly to it.
- Branch per task: `iter03/import-upload-page`, `iter04/provider-merge`.
- Open a pull request; **a teammate other than the author reviews and merges.**
- Commit messages start with the requirement ID: `FR-IMP-06: flag duplicate URLs across files`.
- At the end of each iteration: tag `iter-01`, `iter-02`, ... and submit the iteration report.


## Iterations 3 and 4 (import and admin)
- If your database already exists, run `sql/005_iteration3_4_changes.sql` once.
- Import: `Admin > Import data` (upload one or many `.csv`/`.xlsx`), review the report, Commit or Discard; `Import history` rolls back a batch; `Student coverage` shows progress to 20 each.
- Manage: `Admin > Manage scholarships` (DataTables, filters, bulk actions), `Providers` (edit, merge), `Audit log`.
- Rules and requirement map: `docs/cleaning_rules.md`, `docs/iteration3_4_checklist.md`.
- Tests: `php tests/cleaner_test.php`, `php tests/import_db_test.php`, `php tests/scholarship_admin_test.php` (the last two empty the local tables).
- DataTables loads from a CDN, so the list pages need internet access.

## Iteration 5 (public search)
- Public pages: `search.php` (filters, keyword, sort, pages, results update without reloading), `scholarship.php?id=N` (detail), Home quick search. The old `scholarships.php` forwards to `search.php`.
- Needs nothing new in the database. `search_log` already exists in `001_schema.sql`.
- Try it with fictional data: `php tools/demo_data.php add` (and `remove`). Real data comes from the Import page.
- Test: `php tests/search_test.php` (empties the local scholarship tables).
- The search pages load jQuery and Bootstrap from CDNs, so be online.

## Iteration 6 (data quality, export, dashboards)
- **Existing database:** run `sql/006_iteration6_changes.sql` once (adds District of Columbia to `states`). In phpMyAdmin, select the `scholarships` database first.
- Admin pages: `Dashboard`, `Data quality` (expired, stale, missing fields, possible duplicates), `Coverage report` (by state, national theme and student, gaps highlighted), `Export CSV` (full or filtered, template column names), `Import issues report`.
- Public page: `facts.php` (Scholarship Facts: summary cards and Chart.js charts, each with a "Show the numbers" table).
- Combined file: `php tools/combine_files.php out.csv file1.csv file2.xlsx ...` writes one CSV with a "Contributing student" column that the Import page reads per row.
- Thresholds are constants at the top of `includes/quality.php` (stale 180 days, thin below 5).
- Test: `php tests/quality_test.php` (empties the local scholarship tables; reload demo data with `php tools/demo_data.php add`).
- Charts load Chart.js from a CDN, so be online.

## Iteration 7 (sign-in, roles, user management)
- **Existing database:** run `sql/007_iteration7_changes.sql` once (phpMyAdmin: click the `scholarships` database first, then the SQL tab). It adds `users.session_version` and `login_attempts`, creates the seeded Super-admin, moves everything the placeholder development user did to that Super-admin, and switches the placeholder off. Safe to run twice. A fresh install from `001` + `002` already has all of it.
- **First sign-in:** `superadmin@pathways.local` with the temporary password `Pathways!2026Temp`. You must choose a new password straight away. If every Super-admin is locked out: `php tools/create_superadmin.php you@example.org "Your Name"`.
- **Config:** the `dev_user_id` setting is gone. `session_timeout` (seconds, default 1800) is the inactivity limit. Set `env` to `production` on any public server.
- **Pages:** `register.php` (Student or Parent), `login.php`, `logout.php` (POST), `profile.php`, `change_password.php`. Super-admin only: `admin/users/` (create, change role, activate/deactivate, reset password), `admin/reference/` (states, provider types, categories, fields, national themes), `admin/audit.php` (filters).
- **How access works:** every page calls `require_role([...])` at the top. Not signed in: redirect to the login page, then back. Wrong role: 403. The role is read from the database on every request, so a role change or deactivation applies on the next click.
- **Tests:** `php tests/auth_test.php` (73 checks), `php tests/access_test.php` (316 checks; starts its own web server on port 8098 and rewrites `docs/access_control_matrix.md`). Both create and remove their own `@unit.invalid` / `@matrix.invalid` users.
- Rules, requirement map and the matrix: `docs/iteration7_checklist.md`, `docs/access_control_matrix.md`.

## Iteration 8 (student and parent features)
- **Existing database:** run `sql/008_iteration8_changes.sql` once (phpMyAdmin: click the `scholarships` database first, then the SQL tab). It adds `saved_scholarships.status_updated_at` and the one-name-per-owner rule on profiles. Safe to run twice. A fresh install from `001` + `002` already has it.
- **Pages (Student and Parent only):** `my/profile_edit.php`, `my/profiles.php`, `my/matches.php`, `my/saved.php`, `my/deadlines.php`, `my/dashboard.php` (parents), plus the AJAX endpoints `my/save.php`, `my/status.php` and the form endpoint `my/switch.php`. A new student lands on My Matches and a new parent on the Family dashboard.
- **Save buttons** appear on search results, the detail page and My Matches (`assets/js/save.js`). Visitors see "Sign in to save"; administrators see none.
- **Privacy rule:** a profile id from the address or an AJAX call is only used after `profile_owned()` confirms it belongs to the signed-in user. Someone else's profile looks exactly like one that does not exist.
- **Tests:** `php tests/family_test.php` (101 checks; own web server on port 8097; creates and removes `@fam.invalid` users and `FAMTEST` scholarships). `php tests/access_test.php` now has 366 checks and covers the new pages.
- Rules, requirement map and how isolation works: `docs/iteration8_checklist.md`.

