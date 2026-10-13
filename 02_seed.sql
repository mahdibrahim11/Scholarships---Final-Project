-- Iteration 2: Seed scripts for Scholarship Finder
-- Target DB: MySQL/MariaDB

START TRANSACTION;

-- Five roles
INSERT INTO roles (role_key, role_name, description) VALUES
('admin', 'Administrator', 'Full system access and user management.'),
('scholarship_manager', 'Scholarship Manager', 'Can create, edit, import, and publish scholarships.'),
('reviewer', 'Reviewer', 'Can review scholarship data and validation reports.'),
('provider', 'Provider', 'Represents a scholarship provider organization.'),
('student', 'Student', 'Can search, save, and match with scholarships.')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), description = VALUES(description);

-- Placeholder development user. Replace the password hash before production.
INSERT INTO users (role_id, email, password_hash, first_name, last_name, is_active)
SELECT role_id, 'dev@example.com', '$2y$10$replace-this-development-password-hash', 'Development', 'User', 1
FROM roles
WHERE role_key = 'admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), first_name = VALUES(first_name), last_name = VALUES(last_name), is_active = VALUES(is_active);

-- All 50 states plus National
INSERT INTO states (state_code, state_name, sort_order) VALUES
('NA', 'National', 0),
('AL', 'Alabama', 1),
('AK', 'Alaska', 2),
('AZ', 'Arizona', 3),
('AR', 'Arkansas', 4),
('CA', 'California', 5),
('CO', 'Colorado', 6),
('CT', 'Connecticut', 7),
('DE', 'Delaware', 8),
('FL', 'Florida', 9),
('GA', 'Georgia', 10),
('HI', 'Hawaii', 11),
('ID', 'Idaho', 12),
('IL', 'Illinois', 13),
('IN', 'Indiana', 14),
('IA', 'Iowa', 15),
('KS', 'Kansas', 16),
('KY', 'Kentucky', 17),
('LA', 'Louisiana', 18),
('ME', 'Maine', 19),
('MD', 'Maryland', 20),
('MA', 'Massachusetts', 21),
('MI', 'Michigan', 22),
('MN', 'Minnesota', 23),
('MS', 'Mississippi', 24),
('MO', 'Missouri', 25),
('MT', 'Montana', 26),
('NE', 'Nebraska', 27),
('NV', 'Nevada', 28),
('NH', 'New Hampshire', 29),
('NJ', 'New Jersey', 30),
('NM', 'New Mexico', 31),
('NY', 'New York', 32),
('NC', 'North Carolina', 33),
('ND', 'North Dakota', 34),
('OH', 'Ohio', 35),
('OK', 'Oklahoma', 36),
('OR', 'Oregon', 37),
('PA', 'Pennsylvania', 38),
('RI', 'Rhode Island', 39),
('SC', 'South Carolina', 40),
('SD', 'South Dakota', 41),
('TN', 'Tennessee', 42),
('TX', 'Texas', 43),
('UT', 'Utah', 44),
('VT', 'Vermont', 45),
('VA', 'Virginia', 46),
('WA', 'Washington', 47),
('WV', 'West Virginia', 48),
('WI', 'Wisconsin', 49),
('WY', 'Wyoming', 50)
ON DUPLICATE KEY UPDATE state_name = VALUES(state_name), sort_order = VALUES(sort_order);

-- Grade levels 6-12
INSERT INTO grade_levels (grade_key, grade_label, sort_order) VALUES
('6', 'Grade 6', 6),
('7', 'Grade 7', 7),
('8', 'Grade 8', 8),
('9', 'Grade 9', 9),
('10', 'Grade 10', 10),
('11', 'Grade 11', 11),
('12', 'Grade 12', 12)
ON DUPLICATE KEY UPDATE grade_label = VALUES(grade_label), sort_order = VALUES(sort_order);

-- Scholarship categories
INSERT INTO categories (category_name, description) VALUES
('Need-Based', 'Scholarships based on financial need.'),
('Merit-Based', 'Scholarships based on academic or personal achievement.'),
('STEM', 'Science, technology, engineering, and math scholarships.'),
('Healthcare', 'Scholarships for healthcare-related interests.'),
('Business', 'Scholarships for business-related interests.'),
('Arts', 'Scholarships for arts, music, design, and creative work.'),
('Athletics', 'Scholarships connected to sports or athletic participation.'),
('Community Service', 'Scholarships based on service and volunteering.'),
('Leadership', 'Scholarships based on leadership experience.'),
('First Generation', 'Scholarships for first-generation college students.'),
('Military', 'Scholarships for veterans, military members, and families.'),
('Local', 'Scholarships connected to a specific local area.'),
('National', 'Scholarships open nationally.'),
('Essay Required', 'Scholarships that require an essay.'),
('Career and Technical', 'Scholarships for trades and career technical education.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Fields of study
INSERT INTO fields_of_study (field_name, description) VALUES
('General', 'Open to many majors or no specific field required.'),
('Computer Science', 'Computer science and software development.'),
('Information Technology', 'IT, networking, systems, and support.'),
('Cybersecurity', 'Cybersecurity and information security.'),
('Engineering', 'Engineering fields.'),
('Mathematics', 'Mathematics and statistics.'),
('Nursing', 'Nursing and patient care.'),
('Healthcare', 'Healthcare and allied health.'),
('Business Administration', 'Business management and administration.'),
('Accounting', 'Accounting.'),
('Finance', 'Finance.'),
('Education', 'Teaching and education.'),
('Social Work', 'Social work and human services.'),
('Criminal Justice', 'Criminal justice and law enforcement.'),
('Arts and Design', 'Art, design, and creative fields.'),
('Music', 'Music and performance.'),
('Communications', 'Communications and media.'),
('Environmental Science', 'Environmental science and sustainability.'),
('Agriculture', 'Agriculture and food systems.'),
('Skilled Trades', 'Skilled trades and technical careers.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Provider types
INSERT INTO provider_types (provider_type_name, description) VALUES
('College or University', 'Scholarship offered by a college or university.'),
('Foundation', 'Scholarship offered by a private or public foundation.'),
('Corporation', 'Scholarship offered by a company or business.'),
('Government Agency', 'Scholarship offered by a government office or program.'),
('Nonprofit Organization', 'Scholarship offered by a nonprofit organization.'),
('Community Organization', 'Scholarship offered by a local or community group.'),
('Professional Association', 'Scholarship offered by an industry or career association.'),
('Religious Organization', 'Scholarship offered by a faith-based organization.'),
('Individual Donor', 'Scholarship funded by an individual donor.'),
('Other', 'Other scholarship provider type.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 29 national themes
INSERT INTO national_themes (theme_name, description) VALUES
('Need-Based', 'Financial need is a major eligibility factor.'),
('Merit-Based', 'Academic or achievement merit is a major eligibility factor.'),
('STEM', 'Science, technology, engineering, and mathematics.'),
('Computer Science', 'Computer science, software, and computing careers.'),
('Healthcare', 'Healthcare careers and healthcare education.'),
('Nursing', 'Nursing education and nursing careers.'),
('Business', 'Business-related education and careers.'),
('Accounting', 'Accounting education and careers.'),
('Finance', 'Finance education and careers.'),
('Education and Teaching', 'Education and teaching careers.'),
('Arts and Design', 'Creative arts and design.'),
('Music and Performing Arts', 'Music, theater, and performing arts.'),
('Athletics', 'Sports, athletics, and student athletes.'),
('Community Service', 'Volunteer work and service.'),
('Leadership', 'Leadership experience and potential.'),
('First-Generation Students', 'Students who are first in their family to attend college.'),
('Low-Income Students', 'Students from low-income households.'),
('Underrepresented Students', 'Students from underrepresented backgrounds.'),
('Women in STEM', 'Support for women pursuing STEM fields.'),
('Military and Veterans', 'Military members, veterans, and families.'),
('Students with Disabilities', 'Students with disabilities.'),
('Rural Students', 'Students from rural communities.'),
('Immigrant and Refugee Students', 'Students who are immigrants or refugees.'),
('Single Parent Students', 'Students who are single parents.'),
('Foster Youth', 'Students with foster care experience.'),
('Public Service', 'Public service, government, and civic careers.'),
('Environment and Sustainability', 'Environmental and sustainability interests.'),
('Career and Technical Education', 'Trades and career technical education.'),
('Essay Contest', 'Essay-based scholarship or contest.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Basic providers and hand-entered scholarships for testing the stack end-to-end.
INSERT INTO providers (provider_type_id, provider_name, website_url, contact_email, created_by_user_id)
SELECT pt.provider_type_id, 'Metro Scholarship Foundation', 'https://example.com/metro-scholarship', 'scholarships@example.com', u.user_id
FROM provider_types pt
JOIN users u ON u.email = 'dev@example.com'
WHERE pt.provider_type_name = 'Foundation'
ON DUPLICATE KEY UPDATE website_url = VALUES(website_url), contact_email = VALUES(contact_email);

INSERT INTO providers (provider_type_id, provider_name, website_url, contact_email, created_by_user_id)
SELECT pt.provider_type_id, 'Community Future Fund', 'https://example.com/community-future', 'contact@example.com', u.user_id
FROM provider_types pt
JOIN users u ON u.email = 'dev@example.com'
WHERE pt.provider_type_name = 'Community Organization'
ON DUPLICATE KEY UPDATE website_url = VALUES(website_url), contact_email = VALUES(contact_email);

INSERT INTO scholarships (provider_id, title, description, eligibility_summary, award_amount_min, award_amount_max, is_renewable, application_url, deadline_date, status, created_by_user_id)
SELECT p.provider_id, 'Metro Future Leaders Scholarship',
       'A sample scholarship for students with leadership and community service experience.',
       'Open to grades 11-12. Leadership or community service recommended.',
       500.00, 1500.00, 0, 'https://example.com/apply/metro-future-leaders', '2027-03-15', 'published', u.user_id
FROM providers p
JOIN users u ON u.email = 'dev@example.com'
WHERE p.provider_name = 'Metro Scholarship Foundation'
ON DUPLICATE KEY UPDATE description = VALUES(description), status = VALUES(status);

INSERT INTO scholarships (provider_id, title, description, eligibility_summary, award_amount_min, award_amount_max, is_renewable, application_url, deadline_date, status, created_by_user_id)
SELECT p.provider_id, 'Community STEM Starter Award',
       'A sample scholarship for students interested in STEM, computer science, or engineering.',
       'Open to grades 9-12. Interest in STEM required.',
       250.00, 1000.00, 0, 'https://example.com/apply/community-stem', '2027-04-01', 'published', u.user_id
FROM providers p
JOIN users u ON u.email = 'dev@example.com'
WHERE p.provider_name = 'Community Future Fund'
ON DUPLICATE KEY UPDATE description = VALUES(description), status = VALUES(status);

COMMIT;
