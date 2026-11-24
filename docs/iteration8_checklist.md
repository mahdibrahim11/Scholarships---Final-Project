# Iteration 8: Student and Parent Features, where each item lives

Requirement IDs follow `docs/user_stories.md` (01 profile, 02 children, 03 matches, 04 save, 05 status, 06 deadlines). Check them against your copy of the requirements document (open question 31).

| Assignment bullet | Requirement | Where | How to see it |
|---|---|---|---|
| Student profile (grade, state, interests) | FR-USER-01 | `my/profile_edit.php`, `my/profiles.php`, `validate_profile_input()` | Sign in as a student, **My scholarships > My scholarship profile**. A student has exactly one profile |
| Parent manages multiple child profiles | FR-USER-02 | same pages, `my/dashboard.php`, `my/switch.php` | As a parent, **Add a child** up to 8 times; the "Showing" box switches children. Same name twice is refused |
| "My Matches" driven by the profile | FR-USER-03 | `my/matches.php`, `profile_matches()` | State plus national, grade, any chosen interest (or "any field"), deadline not passed. Each card says why it matches |
| Save / un-save, AJAX toggle from search results and detail pages | FR-USER-04 | `my/save.php`, `assets/js/save.js`, `save_button()` | Click Save on search results, the detail page or My Matches: no reload. With JavaScript off, **Sign in to save** and the profile pages still work |
| Application status tracking | FR-USER-05 | `my/saved.php`, `my/status.php` | Interested, In Progress, Submitted, Awarded, Not Awarded. Changing the box saves at once; the **Update** button is the no-JavaScript fallback |
| "Upcoming Deadlines" with due-soon highlighting | FR-USER-06 | `my/deadlines.php`, `due_class()`, `assets/css/site.css` | Red = 7 days or less, amber = 30 days or less. The words ("Due in 3 days") always appear, so colour is never the only signal |
| Parent dashboard: all children's saved scholarships and deadlines | FR-USER-02, 06 | `my/dashboard.php`, `my/deadlines.php?scope=all` | Parent account: **My scholarships > Family dashboard** |
| One user can never see or change another user's profiles or saved items | privacy; FR-AUTH-05 | `profile_owned()` (every page and action goes through it); `tests/family_test.php`; `tests/access_test.php` | See "How isolation works" below |

## How isolation works
- Every profile id that arrives (address, form, AJAX) is checked with `profile_owned($pdo, $id, $userId)`, which only returns a profile whose `owner_user_id` is the signed-in user. There is no other way to load a profile.
- Someone else's profile and a profile that does not exist give the **same** answer (404 page, or `{"error":"not_found"}` over AJAX), so nobody can discover which ids exist.
- Saved items, status changes, matches, deadlines and the dashboard all start from an owned profile. The dashboard lists `profiles_for_user(the signed-in user)` and nothing else.
- The "active child" kept in the session is re-checked against the database on every request, so a forged session value falls back to the user's own first profile.
- Every change needs the CSRF token (header `X-CSRF-Token` for AJAX). Administrators and visitors cannot use these pages.
- `tests/family_test.php` signs in as two different parents and two students and tries: other family's profile in `?profile=`, `?id=`, forms, delete, switch, AJAX save, un-save and status, odd ids (`abc`, `1 OR 1=1`, arrays, huge numbers), a forged session value. It then compares the database before and after: nothing changed.

## Decisions worth knowing
- Profiles hold only a nickname, grade, state and interests. No birth date, school, address or phone (privacy note in the user stories). A parent's children never get a login.
- Grades 6 to 12 only. A student can have one profile; a parent up to 8 (`PROFILE_MAX_PARENT`).
- Deleting a child (parents only) deletes their saved items too; deleting an account does the same (database cascade).
- Matches use the same filter code as public search (`search_where()`), so the two always agree on what "fits".
- "Upcoming" means saved items marked Interested or In Progress with a deadline from today on. Submitted, Awarded and Not Awarded drop off. Rolling and no-deadline items are listed separately.
- A scholarship that was archived after saving stays in the Saved list marked "No longer listed", and can be removed.
- Existing databases need `sql/008_iteration8_changes.sql` (status-changed time, one-name-per-owner rule).
