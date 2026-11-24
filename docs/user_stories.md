# User stories (starter set: edit these to match your team's thinking)

Format: *As a ___, I want ___ so that ___.*  Requirement IDs in brackets.

## Visitor (min. 3)
1. As a visitor, I want to search by state, grade, and field without making an account, so that I can see if the site is useful. [FR-SRCH-01]
2. As a visitor, I want each scholarship to link to the official page and show when it was last verified, so that I can trust it. [FR-SRCH-07, NFR-07]
3. As a visitor, I want the site to work on my phone, so that I can search from anywhere. [NFR-01, FR-SRCH-08]
4. As a visitor, I want to register as a student or parent, so that I can save scholarships. [FR-AUTH-01]

## Student (min. 5)
1. As a student, I want to enter my grade, state, and interests once, so that I see matching scholarships automatically. [FR-USER-01, FR-USER-03]
2. As a student, I want to save scholarships, so that I can come back to them. [FR-USER-04]
3. As a student, I want to mark where I am (Interested, In Progress, Submitted, Awarded), so that I do not lose track. [FR-USER-05]
4. As a student, I want to see what is due in the next 30, 60, or 90 days, so that I do not miss a deadline. [FR-USER-06]
5. As a student, I want to change my password and profile, so that my account stays current. [FR-AUTH-06]

## Parent (min. 5)
1. As a parent, I want to create a profile for each child, so that each gets their own matches. [FR-USER-02]
2. As a parent, I want to manage my child without the child needing a login, so that a 6th grader is not asked for personal data. [Privacy note]
3. As a parent, I want to see all my children's saved scholarships and deadlines on one page, so that I can plan the year. [Iteration 8]
4. As a parent, I want to filter by essay required and financial need, so that I can find ones my family can do. [FR-SRCH-02]
5. As a parent, I want to be sure no one else can see my child's profile, so that my child's information stays private. [FR-AUTH-05]

## Administrator (min. 5)
1. As an administrator, I want to upload several student files at once, so that the dataset grows quickly. [FR-IMP-02]
2. As an administrator, I want to see every row marked Ready, Warning, Duplicate, or Error with a reason, so that I can decide what to commit. [FR-IMP-07]
3. As an administrator, I want to roll back a whole bad batch, so that one mistake does not pollute the dataset. [FR-IMP-10]
4. As an administrator, I want to edit, verify, and archive scholarships, so that the data stays correct. [FR-ADM-01, FR-ADM-03]
5. As an administrator, I want to merge two spellings of the same provider, so that provider lists are clean. [Iteration 4]
6. As an administrator, I want to export the merged dataset in the template columns, so that the Data Science class can analyze it. [FR-DQ-02]

## Super-admin (min. 3)
1. As a super-admin, I want to create administrators and change roles, so that the right people have the right access. [FR-SA-01, FR-SA-02]
2. As a super-admin, I want the system to stop me from removing the last super-admin, so that nobody is locked out. [FR-SA-02]
3. As a super-admin, I want to filter the audit log by user and action, so that I can see who changed what. [FR-SA-05]
4. As a super-admin, I want to manage states, categories, and fields of study, so that reference lists stay accurate. [FR-SA-04]
