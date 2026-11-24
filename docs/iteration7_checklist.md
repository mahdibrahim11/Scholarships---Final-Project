# Iteration 7: Authentication, RBAC, and User Management, where each item lives

Requirement IDs are mapped from the assignment bullets. Check them against your copy of the requirements document (open question 23).

| Assignment bullet | Requirement | Where | How to see it |
|---|---|---|---|
| Registration for Student and Parent, client and server validation | FR-AUTH-01 | `register.php`, `assets/js/auth.js`, `validate_account_input()` | Try an empty form, a weak password, a mismatch. Changing the role field to `administrator` in the browser tools is refused |
| Login/logout, sessions, `password_hash()`/`password_verify()`, session id regeneration, inactivity timeout | FR-AUTH-02, 03, 04 | `login.php`, `logout.php`, `includes/auth.php` | New session id at login; idle 30 minutes (`session_timeout`) and you are signed out with a message; logout is a POST with a CSRF token |
| `require_role()` and `current_user_id()` for real; every import, admin and data quality page calls `require_role()` | FR-AUTH-05 | `includes/auth.php`; static check in `tests/auth_test.php` | Open any `/admin/...` address while signed out: redirect to login, then back to the page |
| Role-based menu; development banner removed | FR-AUTH-06 | `includes/header.php` | Menu differs for visitor, student, parent, administrator, Super-admin |
| Seeded Super-admin; placeholder user's records reassigned | FR-SA-03 | `sql/002` (fresh), `sql/007` (existing), `tools/create_superadmin.php` | Audit log and scholarships now name a real account; the placeholder is inactive |
| Super-admin user management: create any role, change roles, activate/deactivate, reset passwords, protect last Super-admin | FR-SA-01, 02, 03 | `admin/users/`, `includes/users.php` | Try deactivating or demoting the only Super-admin: refused. You also cannot change your own role |
| Reference data screens | FR-SA-04 | `admin/reference/`, `includes/reference.php` | Add, rename, delete an unused entry. Entries in use, and "US" and "Any / undecided", cannot be deleted |
| Audit log viewer with filters | FR-SA-05 | `admin/audit.php` | Filter by user, action, record type, dates, text; 50 per page |
| Profile page and change password | FR-AUTH-07 | `profile.php`, `change_password.php` | Name edit; password change needs the current password and ends your other sessions |
| Access-control test matrix, including admin URLs while logged out | all | `tests/access_test.php`, `docs/access_control_matrix.md` | `php tests/access_test.php` rewrites the matrix from the live site |

## Decisions worth knowing
- Role, active flag and "must change password" are read from the database on every request. A demotion or deactivation applies on the very next click; a password reset ends that person's other sessions (`session_version`).
- Unknown email and wrong password give the same message. A password check always runs, so the timing does not reveal registered emails.
- A temporary password (new user, reset, seeded account) is shown to the Super-admin once, never saved or logged, and must be replaced at the next sign-in.
- Visitors are redirected to login; signed-in users with the wrong role get a 403 page (or JSON 401/403 for AJAX).
- Audit viewer is now Super-admin only (open question 28).
- Command-line tools and tests are not web requests, so `require_role()` lets them through and `current_user_id()` is the first active Super-admin.

## Tests
`php tests/auth_test.php` (73), `php tests/access_test.php` (316), plus the earlier suites: cleaner 107, import 23, admin 30, search 91, quality 125. A browser check passed on desktop and phone width (registration, forced password change, users, reference data, audit filters) with no JavaScript errors.
