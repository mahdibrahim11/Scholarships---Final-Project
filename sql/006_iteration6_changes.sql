-- Iteration 6 schema/seed change. Run this ONLY if you created the database before this file existed
-- (a fresh install from 001 + 002 already has it). Safe to run twice.
-- The District of Columbia is accepted by the import cleaner and listed in the docs, but it was missing from `states`,
-- so a scholarship with state DC would have made a whole batch fail to commit (foreign key).
INSERT IGNORE INTO states (code, name) VALUES ('DC', 'District of Columbia');
