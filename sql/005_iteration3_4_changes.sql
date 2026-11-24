-- Iteration 3-4 schema change. Run this ONLY if you created the database before this file existed
-- (a fresh install from 001_schema.sql already has it). Safe to run twice.
-- Adds the 'discarded' batch status used by the import report's Discard button.
ALTER TABLE import_batches
  MODIFY status ENUM('staged','committed','rolled_back','discarded') NOT NULL DEFAULT 'staged';
