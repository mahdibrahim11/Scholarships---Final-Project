# Questions to ask the instructor

1. **Users/registrations schema** (TODO 1 in the spec): when will it arrive? `users` in `001_schema.sql` is a placeholder.
2. **"Each student has 20 scholarships"** appears in Iteration 3 and Iteration 6. Is that a data-collection target we should only *report on*, or a rule the import must *enforce* (reject a file with fewer than 20)? The Data Science spec says "approximately 10 per state and 2-3 national", which is different. Which number is right?
3. **National code:** we store National as `US` in `states`. The spec says "National". OK to keep the `US` code with the label "National"?
4. **District of Columbia:** not one of the 50 states but likely to appear in data. We included `DC`. Keep?
5. **URL normalization:** the spec says lowercase host, no trailing slash, no tracking parameters. We also ignore `http` vs `https`, a leading `www.`, `#fragment`, and query-parameter order. Acceptable?
6. **National theme:** the template has no theme column. We set a national row's theme from the uploading student's assignment (from the `LastName_FirstName` file name). Is that the intended way?
7. **Regional scholarships** (several states) are stored as National with the state list copied into "Residency requirement". Is that enough, or do you want a many-to-many `scholarship_states` table?
8. **Aggregator URLs** (Fastweb, Bold.org, ...) produce a *Warning*, not an *Error*. Should they be errors?
9. **Security review** (TODO 2, anveshi.ai/trailhead): what do we need to prepare, and when around FP 7-8?
10. **20 per student (decision taken in Iteration 3):** the import *reports* each student's progress toward 20 but does not reject files with fewer. Is that acceptable?
11. **Archive vs delete:** Iteration 4 archives scholarships (kept in the database, hidden from public search) instead of deleting them. Is a hard delete ever required?
12. **Audit attribution:** until sign-in exists, audit rows use the placeholder dev user. We will switch to the real user in Iteration 7.
13. **Field of study filter:** a scholarship open to any field (tagged "Any / undecided" or with no field listed) is shown for every field choice. Is that the intended meaning, or should a field search show only scholarships tagged with that exact field?
14. **State filter:** choosing a state also shows national scholarships. OK, or should "state only" be a choice?
15. **Search log:** we count a new search when state, grade or field changes (not for keyword, sort or paging). Is that how the statistics iteration wants searches counted?
16. **Requirement IDs:** we mapped FR-DQ-01 (expired/stale), FR-DQ-02 (missing key fields), FR-DQ-03 (duplicates / coverage), FR-DASH-01 (public Scholarship Facts) and FR-DASH-02 (admin dashboard) by the assignment bullets. Please confirm the numbers against the requirements document.
17. **"Stale" verification:** we use 180 days (or never verified). Is that the right threshold?
18. **"Thin" coverage:** a state or theme with fewer than 5 active scholarships is flagged as a gap. Is 5 the right bar, given the Data Science target of about 10 per state and 2-3 national?
19. **Per-student target:** the dashboard shows progress toward 20 (selectable 20/40/60) but does not enforce it. Does "20 more" mean 20 new rows per student on top of Iteration 3, or 20 in total?
20. **Combined file:** `tools/combine_files.php` merges class files into one CSV with a "Contributing student" column that the import reads per row. Is this the format the instructor expects for "the combined CSV"?
21. **Reporting back:** the Import issues report downloads as CSV. Should it go to the instructor by email, a shared folder, or another way?
22. **Round 2 files:** when will the next Data Science files arrive, and will the column names still match the template exactly?
23. **Requirement IDs for Iteration 7:** we mapped FR-AUTH-01 registration, 02 login/logout, 03 password hashing, 04 sessions (regeneration, timeout), 05 server-side role checks, 06 role-based menu, 07 profile and change password; FR-SA-01 create users, 02 change roles and activate/deactivate, 03 reset passwords and protect the last Super-admin, 04 reference data, 05 audit log viewer. Please confirm against the requirements document.
24. **Password rule:** 10 to 72 characters with a letter and a number, plus a short list of common passwords. Is that strong enough, or should it follow a stricter rule?
25. **Inactivity timeout:** 30 minutes. Is that the right number?
26. **Password reset for people who forgot it:** we have no "forgot password" email, because the project has no mail server. A Super-admin resets it and shows a temporary password once. Is that acceptable for this course?
27. **Email verification:** registration signs the person in without confirming their email. Is that acceptable, or must we verify?
28. **Audit log access:** we made the audit viewer Super-admin only (in Iteration 4 administrators could open it). Should administrators see it?
29. **Sign-in lockout:** 5 wrong passwords for one email (or 30 from one address) block sign-in for 15 minutes. OK?
30. **Seeded account:** the Super-admin's temporary password is written in the setup files and the README. That is fine for a class project but must change before real use. OK?
31. **Requirement IDs for Iteration 8:** we mapped FR-USER-01 student profile, 02 parent child profiles, 03 My Matches, 04 save/un-save, 05 application status, 06 upcoming deadlines (the parent dashboard counts toward 02 and 06; the "no one else can see it" rule is tested under privacy / FR-AUTH-05), following docs/user_stories.md. Please confirm against the requirements document.
32. **Profile limits:** a student has one profile; a parent can have up to 8 children. Is that right?
33. **Grades:** profiles allow grades 6 to 12 only. Should grade 5 or "college" be allowed?
34. **Matching:** a scholarship matches when its state is the profile's state or national, the grade is covered, at least one interest matches (or it is open to any field), and the deadline has not passed. Should GPA or essay preferences also be part of a profile?
35. **Upcoming deadlines:** only items marked Interested or In Progress count as "upcoming". Is that the intended meaning? "Due soon" is 7 days or less (red) and 30 days or less (amber): OK?
36. **Student account and child profile:** a student's profile is filled in by the student. Should a student under 13 be blocked from registering without a parent?

