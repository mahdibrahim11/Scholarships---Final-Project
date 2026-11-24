# Team plan (3 members)

## Ownership by area (rotate who leads each iteration)
| Area | Lead in iterations 1-4 | Owns |
|---|---|---|
| Data and schema | Member A | `sql/`, ERD, audit log, reference data, later: auth + user management |
| Import pipeline | Member B | `includes/import/`, upload + report + history pages, data-quality dashboard |
| Front end and search | Member C | layout, public pages, search, detail page, charts, accessibility |

Everyone writes tests for what they build. Everyone reviews a teammate's pull request every iteration.

## Weekly rhythm
- **Day 1:** pick tasks from `docs/NEXT_STEPS.md`; each task gets a requirement ID and an owner.
- **Mid-iteration:** 15-minute check-in. Anything blocked? Anything that will slip? Say so early.
- **Two days before the deadline:** merge freeze for new features. Test on the shared/deployed environment. Record the demo.
- **Deadline:** tag `iter-NN`, write the iteration report (planned / done by requirement ID / slipped and why / known bugs / next plan).

## Pull request checklist (paste into the PR description)
- [ ] Requirement ID(s): ______
- [ ] Prepared statements only; output escaped with `e()`; CSRF on forms
- [ ] Admin pages call `require_role()`
- [ ] Looks right at 375 / 768 / 1280 px; keyboard works; inputs have labels
- [ ] SQL scripts updated if the schema changed
- [ ] Tests added or updated; `php tests/cleaner_test.php` passes
- [ ] Reviewed by someone other than the author
