# ERD (matches `sql/001_schema.sql`)

Paste the block into https://mermaid.live to export an image for the iteration report.

```mermaid
erDiagram
  roles ||--o{ users : has
  users ||--o{ student_profiles : owns
  users ||--o{ audit_log : performs
  users ||--o{ import_batches : uploads
  student_profiles ||--o{ profile_interests : has
  student_profiles ||--o{ saved_scholarships : saves
  fields_of_study ||--o{ profile_interests : chosen_in
  fields_of_study ||--o{ scholarship_fields : tags
  states ||--o{ student_profiles : lives_in
  states ||--o{ scholarships : located_in
  grade_levels ||--o{ student_profiles : in_grade
  grade_levels ||--o{ scholarship_grades : eligible
  provider_types ||--o{ providers : classifies
  providers ||--o{ scholarships : offers
  national_themes ||--o{ scholarships : themed
  national_themes ||--o{ student_assignments : assigned
  states ||--o{ student_assignments : assigned
  student_assignments ||--o{ scholarships : contributed
  student_assignments ||--o{ import_batches : source
  import_batches ||--o{ scholarships : created
  import_batches ||--o{ import_staging_rows : stages
  scholarships ||--o{ scholarship_grades : has
  scholarships ||--o{ scholarship_categories : has
  scholarships ||--o{ scholarship_fields : has
  categories ||--o{ scholarship_categories : tags
  scholarships ||--o{ saved_scholarships : saved_as
```

Standalone tables: `contact_messages`, `search_log`.

Design choices to defend in your presentation:
- `scholarships.active_url_key` is a generated column that is NULL for archived rows, so MySQL's UNIQUE index enforces "unique among active records" (MySQL has no partial unique index).
- `school_level` is stored but always derived from `scholarship_grades` when saving, so the two cannot disagree.
- `student_assignments` lets the import check theme and sponsor-letter range, and fills in the national theme.
