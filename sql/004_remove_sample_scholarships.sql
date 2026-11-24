-- Run BEFORE the real data load (Iteration 3). Deletes only the SAMPLE demo rows from 004_seed_sample_scholarships.sql.
-- scholarship_grades / _fields / _categories rows go with them (ON DELETE CASCADE).
DELETE FROM scholarships WHERE id BETWEEN 9001 AND 9999 AND name LIKE 'SAMPLE %';
DELETE FROM providers WHERE id = 9001 AND name_key = 'sample foundation (demo data)';
