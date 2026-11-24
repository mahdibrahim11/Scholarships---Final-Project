# Iteration 6: Data Quality, Export, and Dashboards, where each item lives

Requirement IDs are mapped from the assignment bullets. Check them against your copy of the requirements document (open question 16).

| Assignment bullet | Requirement | Where | How to see it |
|---|---|---|---|
| Expired deadlines, stale verification, missing key fields, possible duplicates | FR-DQ-01, FR-DQ-02, FR-DQ-03 | `admin/quality.php`, `includes/quality.php` | Cards with counts; click one for the list (100 shown, full list in the CSV) |
| 20 more scholarships per student; combined CSV uploadable | (Iteration 3 rule) | `admin/coverage.php` (student tab), `tools/combine_files.php`, "Contributing student" column | Pick target 20/40/60. Combine files, upload the one CSV on the Import page |
| Coverage by state and national theme, gaps highlighted | FR-DQ-03 | `admin/coverage.php` | States (50 + DC) and 29 themes; "Gap" / "Thin" shown in words as well as color |
| CSV export, full or filtered, template column names | FR-DQ-03 | `admin/export.php`, `includes/export.php` | Filters change the count; header row = template names; BOM for Excel; formula-injection guard |
| Public Scholarship Facts page | FR-DASH-01 | `facts.php` | Summary cards, Chart.js charts, accessible number tables |
| Admin dashboard | FR-DASH-02 | `admin/dashboard.php` | Cards, charts, quick links to quality and coverage |
| Load next round, report issues back | | `admin/import/issues.php` | Groups problems by file and reason; downloadable CSV for the instructor |

## Decisions worth knowing
- Stale = last verified more than 180 days ago, or never. Thin coverage = fewer than 5 active scholarships.
- Duplicates are found among active rows by normalized URL, and by name + provider.
- National coverage excludes the `US` code from the state list; District of Columbia is included (migration 006).
- "20 per student" is reported, not enforced (open questions 10 and 19).
- Exported cells that start with = + - @ tab or CR get an apostrophe prefix so Excel does not run them.

## Tests
`php tests/quality_test.php` (125 checks), plus cleaner 107, import 23, admin 30, search 91. A browser check passed on desktop and phone width with no horizontal scroll and no JavaScript errors.
