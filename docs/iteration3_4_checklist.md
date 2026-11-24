# Iterations 3 and 4: where each requirement lives

## Iteration 3 (bulk data load)
| Requirement | Where | Proof |
|---|---|---|
| FR-IMP-01 template download | `admin/import/template.php` | Link on the import page |
| FR-IMP-02 upload `.xlsx` and `.csv`, one or many, type and size checks | `admin/import/index.php`, `upload.php`, `includes/import/intake.php` | Upload `tests/sample_files/Young_Fuchee.csv` (fictional SAMPLE rows); try a `.txt` and a renamed fake `.xlsx` |
| FR-IMP-03 staging before commit | `import_staging_rows`, `includes/import/store.php` | Report shows rows while `scholarships` is unchanged |
| FR-IMP-04/05 cleaning rules | `includes/import/cleaner.php`, `docs/cleaning_rules.md` | `php tests/cleaner_test.php` (96 checks) |
| FR-IMP-06 duplicate detection by normalized URL | `normalize_url()`, `active_url_key` column | Cross-file duplicates in the sample files |
| FR-IMP-07 validation report | `admin/import/report.php` | Ready / Warning / Duplicate / Error with reasons and filter buttons |
| FR-IMP-08 commit in a transaction | `commit_batch()`, `admin/import/commit.php` | `php tests/import_db_test.php` (23 checks) |
| FR-IMP-09 rollback by batch | `rollback_batch()`, `admin/import/history.php` | Roll back a committed batch; double rollback is refused |
| FR-IMP-10 import history | `admin/import/history.php` | Lists every batch with counts, student and status |
| 20 per student | `admin/import/coverage.php` and the report header | Shows each of the 58 students against 20 |
| Test files | `tests/sample_files/Young_Fuchee.csv` | Fictional SAMPLE rows with deliberate problems (duplicate, missing URL, bad date). The three-file requirement is met by each teammate adding their own `LastName_FirstName.csv` here |

## Iteration 4 (admin CRUD)
| Requirement | Where | Proof |
|---|---|---|
| FR-ADM-05 list with DataTables and filters (state, student, batch, theme) | `admin/scholarships/index.php` | Filters, sort, search, paging |
| Bulk archive / mark verified (and restore) | `admin/scholarships/actions.php`, `scholarship_bulk_action()` | Select rows on different pages, then act |
| FR-ADM-02 create, view, edit, archive | `edit.php`, `view.php`, `actions.php` | Grades, categories and fields are many-to-many |
| FR-ADM-03 providers: list, edit, merge | `admin/providers/*` | Duplicate suggestions and a confirm page before merging |
| FR-ADM-04 validation on server and client | `validate_scholarship_input()` and the jQuery in `edit.php` | Same rules both sides; server is the authority |
| Mark as verified | `view.php` and bulk | Sets today's date and records who |
| FR-SA-05 audit log | `includes/audit.php`, `admin/audit.php` | create, edit, archive, restore, verify, provider.merge/edit, import.stage/commit/rollback/discard |
| Cleanup of Iteration 3 data | Archive, or roll back the batch | Rollback removes a whole file; archive hides single rows |

Tests: `php tests/scholarship_admin_test.php` (30 checks, empties local tables).
Database change for anyone who already built the database: run `sql/005_iteration3_4_changes.sql` once.
