# Iteration 5: Scholarship Search (core feature), where each item lives

The requirement IDs (FR-SRCH-01 to 08, FR-PUB-01) are matched here by the bullet list in the assignment. Check the numbers against your copy of the requirements document and fix any that differ.

| Assignment bullet | Where | How to see it |
|---|---|---|
| Search page with the three mandatory filters (state, grade, field of study) | `search.php`, `includes/search.php` | Choose any of the three; results change |
| Additional filters | "More filters" on `search.php` | Category, school level, national theme, award of at least, deadline window, my GPA, no essay, no financial need, no recommendation, renewable |
| Keyword search | Keyword box | Every word must match the name, provider, residency, requirements, a field or a category. `%` and `_` are searched literally |
| Sorting | "Sort by" | Deadline (soonest first, default), award, name, recently verified |
| Pagination | Pager under the results | 20 per page, stable order, "Showing 21 to 40 of 70" |
| AJAX filtering with jQuery | `assets/js/search.js`, `search_results.php` | Change a filter: no page reload, the address bar updates, Back and Forward work. With JavaScript off the same form still works as a normal GET form |
| Hide expired by default | `search_where()` | A checkbox includes past deadlines. Rolling and no-deadline scholarships stay visible |
| Detail page with official URL and verification date | `scholarship.php?id=N` | Click any result. Archived scholarships return "not found" |
| Responsive results | `search_render_results()` | Cards below 992 px wide (phones, tablets), table above. No sideways scrolling on a phone |
| Quick search on Home connected to search | `index.php` posts to `search.php` | State, grade and field carry over into the filters |
| Anonymous search logging | `search_log()`, table `search_log` | Stores state, grade and field only. No keyword, no user, no IP |

## Decisions worth knowing
- A state search shows that state **plus national** scholarships. "National only" shows just national ones.
- Choosing a field also shows scholarships **open to any field** (tagged "Any / undecided", or with no field listed), because those are valid for every field.
- Grades are strict. A scholarship with no grades listed does not appear in a grade search (the import warns about these).
- "No essay", "no financial need" and "no recommendation" only match scholarships confirmed **No**, never "Unknown".
- Notes, the contributing student and the import batch are never shown on public pages.
- A new search is logged only when state, grade or field changes (or the page first loads with them). Typing a keyword, sorting and paging are not logged.

## Trying it before the real data is in
`php tools/demo_data.php add` adds 72 fictional scholarships (names start with "DEMO:") spread across states, grades, fields and deadlines, including expired, rolling and no-deadline rows. `php tools/demo_data.php remove` deletes exactly those rows. Remove them before you hand in or demo with real data.

## Tests
- `php tests/search_test.php` (91 checks; empties the scholarship tables, so use the local database only)
- Earlier suites still pass: `cleaner_test.php` (96), `import_db_test.php` (23), `scholarship_admin_test.php` (30)

## Search statistics (for the later statistics iteration)
```sql
SELECT state_code, COUNT(*) AS searches FROM search_log WHERE state_code IS NOT NULL GROUP BY state_code ORDER BY searches DESC;
SELECT grade, COUNT(*) FROM search_log WHERE grade IS NOT NULL GROUP BY grade ORDER BY grade;
SELECT f.name, COUNT(*) AS searches FROM search_log l JOIN fields_of_study f ON f.id = l.field_id GROUP BY f.name ORDER BY searches DESC;
```
