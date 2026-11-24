-- =====================================================================
-- Iteration 2: a few HAND-ENTERED scholarships to prove the stack works end to end
-- (database -> PHP -> list page). These are FAKE demo rows: names start with "SAMPLE",
-- links use example.org, ids start at 9001. Run AFTER 001 and 002.
-- Remove them before the real data load with sql/004_remove_sample_scholarships.sql.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO providers (id, name, name_key, provider_type_id, website)
VALUES (9001, 'Sample Foundation (demo data)', 'sample foundation (demo data)', 1, 'https://example.org');

INSERT INTO scholarships
 (id, name, provider_id, official_url, url_key, state_code, scope, national_theme_id, school_level,
  award_min, award_max, num_awards, renewable, open_date, deadline, is_rolling, min_gpa,
  essay_required, other_requirements, application_method, verification_date, notes, status, created_by, updated_by)
VALUES
 (9001, 'SAMPLE National STEM Essay Award', 9001, 'https://example.org/sample/stem-essay', 'example.org/sample/stem-essay',
  'US', 'national', 26, 'high', 500.00, 1000.00, 10, 'No', '2026-09-01', '2027-03-15', 0, 3.00,
  'Yes', 'Fake demo row.', 'Online', '2026-10-01', 'Demo data for Iteration 2.', 'active', 1, 1),
 (9002, 'SAMPLE Minnesota Young Coders Grant', 9001, 'https://example.org/sample/mn-coders', 'example.org/sample/mn-coders',
  'MN', 'state_local', NULL, 'middle', 250.00, 250.00, 20, 'No', NULL, '2027-01-31', 0, NULL,
  'No', 'Fake demo row.', 'Online', '2026-10-01', 'Demo data for Iteration 2.', 'active', 1, 1),
 (9003, 'SAMPLE Rolling Community Service Award', 9001, 'https://example.org/sample/service', 'example.org/sample/service',
  'US', 'national', 6, 'both', NULL, 500.00, NULL, 'Unknown', NULL, NULL, 1, NULL,
  'Unknown', 'Fake demo row.', 'Online', '2026-10-01', 'Rolling deadline example.', 'active', 1, 1),
 (9004, 'SAMPLE Wisconsin Writers Contest', 9001, 'https://example.org/sample/wi-writers', 'example.org/sample/wi-writers',
  'WI', 'state_local', 29, 'high', 100.00, 750.00, 5, 'No', '2026-09-01', '2026-12-01', 0, NULL,
  'Yes', 'Fake demo row.', 'Mail', '2026-10-01', 'Deadline soon example.', 'active', 1, 1),
 (9005, 'SAMPLE Texas Arts Showcase Scholarship', 9001, 'https://example.org/sample/tx-arts', 'example.org/sample/tx-arts',
  'TX', 'state_local', 1, 'high', 1000.00, 1000.00, 2, 'Yes', '2026-03-01', '2026-09-01', 0, NULL,
  'No', 'Fake demo row.', 'Online', '2026-10-01', 'Past deadline: hidden unless "Include past deadlines" is checked.', 'active', 1, 1);

INSERT INTO scholarship_grades (scholarship_id, grade) VALUES
 (9001,9),(9001,10),(9001,11),(9001,12),
 (9002,6),(9002,7),(9002,8),
 (9003,6),(9003,7),(9003,8),(9003,9),(9003,10),(9003,11),(9003,12),
 (9004,9),(9004,10),(9004,11),(9004,12),
 (9005,10),(9005,11),(9005,12);

INSERT INTO scholarship_fields (scholarship_id, field_id)
SELECT 9001, id FROM fields_of_study WHERE name IN ('Engineering','Math','Science (general)')
UNION ALL SELECT 9002, id FROM fields_of_study WHERE name = 'Computer science and IT'
UNION ALL SELECT 9004, id FROM fields_of_study WHERE name LIKE 'Writing%'
UNION ALL SELECT 9005, id FROM fields_of_study WHERE name LIKE 'Arts%';

INSERT INTO scholarship_categories (scholarship_id, category_id)
SELECT 9001, id FROM categories WHERE name IN ('STEM','Essay/contest')
UNION ALL SELECT 9003, id FROM categories WHERE name = 'Community service'
UNION ALL SELECT 9004, id FROM categories WHERE name = 'Essay/contest'
UNION ALL SELECT 9005, id FROM categories WHERE name = 'Arts';
