# What is done and what is left

## Already in this starter (tested)
| Area | Status | Requirement IDs |
|---|---|---|
| Schema for all Section 6.2 tables (+ `student_assignments`), indexes, utf8mb4, FKs | Done, runs on MariaDB 10.11 | Section 6 |
| Seeds: 50 states + DC + National, grades 6-12, 29 national themes, 58 student assignments, five roles (student, parent, administrator, super_admin, visitor), placeholder dev user | Done | Iteration 2 |
| Hand-entered sample scholarships (`sql/004_seed_sample_scholarships.sql`) shown in the read-only list | Done | Iteration 2 |
| Shared layout, nav, footer, dev-mode banner, security headers | Done | FR-PUB-03 |
| Security helpers: `e()`, CSRF, flash, PDO connection, `require_role()`/`current_user_id()` stubs | Done | NFR-02 (start) |
| Home (quick search), About, FAQ, Contact (validated, stored, CSRF) | Done | FR-PUB-01/02 |
| Read-only list with state/grade/field filters, expired hidden, deadline sort, cards + table | Done (basic) | FR-SRCH-01/04/06/08 (partial) |
| Cleaning rules (text, states, scope, Yes/No, money, dates, grades, URL key, sponsor range) | Done, 92 tests | FR-IMP-04/05/06 |
| Row + file validation: Ready / Warning / Duplicate / Error, with reasons | Done | FR-IMP-05/06/07 |
| Staging, transactional commit, batch bookkeeping, rollback, audit log | Done, 23 DB tests | FR-IMP-03/08/09/10, FR-SA-05 (start) |
| Template download, CLI batch loader, 3 sample files with deliberate problems | Done | FR-IMP-01 |

## Your team still needs to build

**Iteration 1 (paper work, mostly started in `docs/`)**
- [ ] Review `docs/field_inventory.md` and `docs/user_stories.md`; edit them to be yours.
- [ ] Draw the 10 wireframes in `docs/wireframes_list.md` (mobile + desktop).
- [ ] Check `docs/erd.md` against the schema; export an image for the report.
- [ ] Everyone gets the app running locally. Create the GitHub repo and add the instructor.

**Iteration 3 (the big one) - BUILT in this version; remaining items are loading the real Data Science files and tuning rules**
- [ ] `admin/import/upload.php`: file input (`.csv` + `.xlsx`), type/size checks, SheetJS in the browser for `.xlsx` -> JSON -> POST (with CSRF header) to a PHP endpoint that calls `validate_file()` then `stage_batch()`.
- [ ] `admin/import/report.php?batch=N`: table of rows with status colors + reasons; buttons *Commit* and *Discard*.
- [ ] `admin/import/history.php`: list of batches, counts, uploader, *Roll back* button (POST + CSRF + confirm).
- [ ] Load the real Data Science files; tune rules where real data surprises you; write the cleaning-rules write-up.
- [ ] Check the "each student has 20 scholarships" requirement (see open questions).

**Iteration 4:** admin DataTable (`FR-ADM-05`), create/edit/archive form with many-to-many pickers, provider merge, verify action, audit entries for edits.
**Iteration 5:** extra filters (amount, deadline, category, renewable, essay, need), keyword search, AJAX results, detail page, search logging.
**Iteration 6: BUILT** (data quality, coverage, CSV export, Scholarship Facts, admin dashboard, combined files, import issues report). Left: load the real round-2 files and answer open questions 16-22.
**Iteration 7: BUILT** (sign-in, registration, role checks on every page, Super-admin users/reference data/audit, access-control matrix). Left: answer open questions 23-30; run the matrix again after any new page.
**Iteration 8: BUILT** (student and parent profiles, My Matches, save/un-save, status tracking, upcoming deadlines, parent dashboard, family isolation tests). Left: answer open questions 31-36; run `sql/008_iteration8_changes.sql` on an existing database.
**Iterations 9-10:** per the spec.

## Known gaps / things to decide
- `users` table is a draft until the instructor sends the official schema.
- Excel (`.xlsx`) parsing is not written. The spec suggests SheetJS in the browser. Until then, "Save as CSV (UTF-8)".
- Staged rows currently store provider *name*; the commit step finds or creates the provider by a normalized key. A provider-merge screen is Iteration 4.
- No CAPTCHA/rate limiting on Contact. Consider a honeypot field in Iteration 9.


## Iteration 5 (search): built
- Remaining for you: load the real class data through Import so search shows real scholarships, and confirm the open questions 13-15.
