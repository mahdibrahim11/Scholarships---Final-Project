Iteration 2 - Database Foundation and Site Skeleton

Files included:
1. 01_schema.sql
   - Creates the scholarship application database tables.
   - Includes users, roles, reference tables, scholarships, providers, junction tables, import tables, contact messages, applications, saved scholarships, and audit log.
   - Includes primary keys, foreign keys, unique keys, and indexes.
   - Includes nullable acting/audit user columns such as created_by_user_id, updated_by_user_id, deleted_by_user_id, and acting_user_id where appropriate.

2. 02_seed.sql
   - Inserts all 50 states plus National.
   - Inserts grade levels 6-12.
   - Inserts categories, fields of study, provider types, 29 national themes, five roles, and a placeholder development user.
   - Includes two sample providers and two sample scholarships to help prove the stack works end-to-end.

Suggested use:
Run 01_schema.sql first, then run 02_seed.sql.

Example:
mysql -u your_user -p your_database < 01_schema.sql
mysql -u your_user -p your_database < 02_seed.sql
