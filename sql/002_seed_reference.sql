-- Reference data. Categories and fields of study are a PROPOSED starting list:
-- compare with what the Data Science files actually contain and adjust (FR-SA-04).
SET NAMES utf8mb4;

INSERT INTO roles (id, name) VALUES (1,'student'),(2,'parent'),(3,'administrator'),(4,'super_admin'),(5,'visitor');

-- Seeded Super-admin (Iteration 7). Temporary password: Pathways!2026Temp. The account must change it at first sign-in.
-- There is no placeholder development user any more.
INSERT INTO users (id, role_id, email, password_hash, display_name, is_active, must_change_password) VALUES (1, 4, 'superadmin@pathways.local', '$2y$10$PPYGWrgpnPSer5rE5210n.7O9Xe0O33WIkNy5kv1sMxNG6W5biISq', 'Super Admin', 1, 1);

INSERT INTO states (code, name) VALUES ('US','National'),
('AL', 'Alabama'),
('AK', 'Alaska'),
('AZ', 'Arizona'),
('AR', 'Arkansas'),
('CA', 'California'),
('CO', 'Colorado'),
('CT', 'Connecticut'),
('DC', 'District of Columbia'),
('DE', 'Delaware'),
('FL', 'Florida'),
('GA', 'Georgia'),
('HI', 'Hawaii'),
('ID', 'Idaho'),
('IL', 'Illinois'),
('IN', 'Indiana'),
('IA', 'Iowa'),
('KS', 'Kansas'),
('KY', 'Kentucky'),
('LA', 'Louisiana'),
('ME', 'Maine'),
('MD', 'Maryland'),
('MA', 'Massachusetts'),
('MI', 'Michigan'),
('MN', 'Minnesota'),
('MS', 'Mississippi'),
('MO', 'Missouri'),
('MT', 'Montana'),
('NE', 'Nebraska'),
('NV', 'Nevada'),
('NH', 'New Hampshire'),
('NJ', 'New Jersey'),
('NM', 'New Mexico'),
('NY', 'New York'),
('NC', 'North Carolina'),
('ND', 'North Dakota'),
('OH', 'Ohio'),
('OK', 'Oklahoma'),
('OR', 'Oregon'),
('PA', 'Pennsylvania'),
('RI', 'Rhode Island'),
('SC', 'South Carolina'),
('SD', 'South Dakota'),
('TN', 'Tennessee'),
('TX', 'Texas'),
('UT', 'Utah'),
('VT', 'Vermont'),
('VA', 'Virginia'),
('WA', 'Washington'),
('WV', 'West Virginia'),
('WI', 'Wisconsin'),
('WY', 'Wyoming');

INSERT INTO grade_levels (grade, label, school_level) VALUES
(6, 'Grade 6', 'middle'),
(7, 'Grade 7', 'middle'),
(8, 'Grade 8', 'middle'),
(9, 'Grade 9', 'high'),
(10, 'Grade 10', 'high'),
(11, 'Grade 11', 'high'),
(12, 'Grade 12', 'high');

INSERT INTO provider_types (name) VALUES ('Foundation/nonprofit'),('Corporation'),('College/university'),('Government agency'),('Professional association'),('Community organization'),('Other');

INSERT INTO categories (name) VALUES ('Merit'),('Need-based'),('Community service'),('Leadership'),('STEM'),('Arts'),('Athletics'),('Essay/contest'),('Heritage or identity'),('Career and technical'),('Military-connected'),('First-generation'),('Disability-related'),('Faith-based'),('Other');

INSERT INTO fields_of_study (name) VALUES ('Any / undecided'),('Agriculture and environment'),('Arts, music, and design'),('Business and finance'),('Computer science and IT'),('Education and teaching'),('Engineering'),('Health and medicine'),('Humanities and social sciences'),('Law, public service, and government'),('Math'),('Science (general)'),('Skilled trades and technical'),('Writing, journalism, and communications');

-- The 29 national themes, numbered as in the Data Science assignment sheet.
INSERT INTO national_themes (id, name) VALUES
(1, 'Arts, music, and design'),
(2, 'Asian American and Pacific Islander heritage'),
(3, 'Black/African American heritage'),
(4, 'Business, finance, and accounting'),
(5, 'Community college, transfer, graduate, and adult/returning students'),
(6, 'Community service and volunteering'),
(7, 'Computer science, data science, and IT'),
(8, 'Corporate-sponsored programs'),
(9, 'Education and teaching'),
(10, 'Engineering'),
(11, 'Environment, agriculture, and natural resources'),
(12, 'First-generation college students'),
(13, 'Foster youth and independent students'),
(14, 'Health, nursing, and pre-med'),
(15, 'Hispanic/Latino heritage'),
(16, 'Humanities and social sciences'),
(17, 'Law, public service, and government'),
(18, 'Leadership and civic engagement'),
(19, 'LGBTQ+ students'),
(20, 'Military, veterans, and military families'),
(21, 'Native American and Indigenous'),
(22, 'Professional associations'),
(23, 'Religious and faith-based'),
(24, 'Rural and small-town students'),
(25, 'Skilled trades and technical programs'),
(26, 'STEM general (math, physics, chemistry, biology)'),
(27, 'Students with disabilities'),
(28, 'Women-focused'),
(29, 'Writing, journalism, and communications');
