-- Production Seed Data for MKR College ERP
-- Institution: Matashri Kaushalya Ramdev Dr. Ganesh Ray Degree College (Sitamarhi, Bihar)
-- Affiliation: B.R.A. Bihar University (BRABU), Muzaffarpur

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Academic Sessions
INSERT INTO academic_sessions (id, name, start_year, end_year, is_current, created_at, updated_at) VALUES
('sess-2026-2030-curr', '2026-2030', 2026, 2030, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE is_current = 1, updated_at = NOW();

-- 2. Departments (12 Academic Departments)
INSERT INTO departments (id, name, code, faculty_type, head_of_dept, created_at) VALUES
('dept-hist', 'History', 'HIST', 'Social Science', 'Head of Department (History)', NOW()),
('dept-pols', 'Political Science', 'POLS', 'Social Science', 'Head of Department (Political Science)', NOW()),
('dept-econ', 'Economics', 'ECON', 'Social Science', 'Head of Department (Economics)', NOW()),
('dept-socl', 'Sociology', 'SOCL', 'Social Science', 'Head of Department (Sociology)', NOW()),
('dept-psyc', 'Psychology', 'PSYC', 'Social Science', 'Head of Department (Psychology)', NOW()),
('dept-geog', 'Geography', 'GEOG', 'Social Science', 'Head of Department (Geography)', NOW()),
('dept-hind', 'Hindi', 'HIND', 'Humanities', 'Head of Department (Hindi)', NOW()),
('dept-engl', 'English', 'ENGL', 'Humanities', 'Head of Department (English)', NOW()),
('dept-phil', 'Philosophy', 'PHIL', 'Humanities', 'Head of Department (Philosophy)', NOW()),
('dept-urdu', 'Urdu', 'URDU', 'Humanities', 'Head of Department (Urdu)', NOW()),
('dept-sans', 'Sanskrit', 'SANS', 'Humanities', 'Head of Department (Sanskrit)', NOW()),
('dept-comm', 'Commerce', 'COMM', 'Commerce', 'Head of Department (Commerce)', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), faculty_type = VALUES(faculty_type);

-- 3. Courses (3 Official UG Courses)
INSERT INTO courses (id, name, code, duration_year, total_semesters, department_id, created_at) VALUES
('crs-ba-ss', 'Bachelor of Arts (B.A) – Social Science', 'BA-SS', 4, 8, 'dept-pols', NOW()),
('crs-ba-hum', 'Bachelor of Arts (B.A) – Humanities', 'BA-HUM', 4, 8, 'dept-hind', NOW()),
('crs-bcom', 'Bachelor of Commerce (B.COM)', 'BCOM', 4, 8, 'dept-comm', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), duration_year = VALUES(duration_year), total_semesters = VALUES(total_semesters);

-- 4. Super Administrator Account (Password: Mkr@arvind98 | Bcrypt cost 12)
INSERT INTO users (id, email, password_hash, name, role, is_active, phone, created_at, updated_at) VALUES
('usr-super-admin-01', 'mkrcollege@gmail.com', '$2y$12$TAcqmrdKGUOPqDHTvumClOnnoxoVz8Y7tQm7SyQa7prAELZ66kbR6', 'MKR College Super Administrator', 'SUPER_ADMIN', 1, '+91-7597052444', NOW(), NOW())
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role), is_active = 1, updated_at = NOW();

-- 5. Official Notice Broadcast
INSERT INTO notices (id, title, content, category, is_published, published_at, created_at, updated_at) VALUES
('notc-2026-001', 'Welcome to MKR College ERP Portal - Academic Session 2026-2030 Admissions Open', 'Online admissions for 4-Year CBCS Undergraduate Programs (BA Social Science, BA Humanities, B.Com) for the Academic Session 2026-2030 are now open. Prospective students must register online and submit their CBCS subject preferences.', 'Admission', 1, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), is_published = 1, updated_at = NOW();

-- 6. Semester 1 Registration Windows (Session 2026-2030)
INSERT INTO registration_windows (id, academic_session_id, course_id, semester, start_date, end_date, grace_days, is_active, created_at, updated_at) VALUES
('win-2026-ba-ss-s1', 'sess-2026-2030-curr', 'crs-ba-ss', 1, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 30, 1, NOW(), NOW()),
('win-2026-ba-hum-s1', 'sess-2026-2030-curr', 'crs-ba-hum', 1, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 30, 1, NOW(), NOW()),
('win-2026-bcom-s1', 'sess-2026-2030-curr', 'crs-bcom', 1, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 30, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW();

-- 7. CBCS Subjects for Semester 1 (BRABU Statutory 20-Credit Scheme)
-- Social Science Subjects (MJC: 6, MIC: 4, MDC: 4, AEC: 2, SEC: 2, VAC: 2)
INSERT INTO subjects (id, code, name, semester, credits, category, course_id, department_id, created_at) VALUES
('subj-ss-mjc-hist', 'BA-SS-S1-MJC-HISTORY', 'Major (MJC): History of India (Earliest to 300 CE)', 1, 6, 'MJC', 'crs-ba-ss', 'dept-hist', NOW()),
('subj-ss-mjc-pols', 'BA-SS-S1-MJC-POLITICAL-SCIENCE', 'Major (MJC): Political Theory & Institutions', 1, 6, 'MJC', 'crs-ba-ss', 'dept-pols', NOW()),
('subj-ss-mjc-econ', 'BA-SS-S1-MJC-ECONOMICS', 'Major (MJC): Microeconomic Analysis', 1, 6, 'MJC', 'crs-ba-ss', 'dept-econ', NOW()),
('subj-ss-mic-pols', 'BA-SS-S1-MIC-POLITICAL-SCIENCE', 'Minor (MIC): Indian Government & Politics', 1, 4, 'MIC', 'crs-ba-ss', 'dept-pols', NOW()),
('subj-ss-mic-hist', 'BA-SS-S1-MIC-HISTORY', 'Minor (MIC): Social Formations in World History', 1, 4, 'MIC', 'crs-ba-ss', 'dept-hist', NOW()),
('subj-ss-mdc-01',   'BA-SS-S1-MDC-SOCIOLOGY', 'Multidisciplinary (MDC): Foundations of Society & Economy', 1, 4, 'MDC', 'crs-ba-ss', 'dept-socl', NOW()),
('subj-ss-aec-01',   'BA-SS-S1-AEC-HINDI', 'Ability Enhancement (AEC): Modern Indian Language (Hindi/English)', 1, 2, 'AEC', 'crs-ba-ss', 'dept-hind', NOW()),
('subj-ss-sec-01',   'BA-SS-S1-SEC-COMMUNICATION', 'Skill Enhancement (SEC): Communication & Digital Media', 1, 2, 'SEC', 'crs-ba-ss', 'dept-engl', NOW()),
('subj-ss-vac-01',   'BA-SS-S1-VAC-ENVIRONMENTAL', 'Value Added Course (VAC): Environmental Studies & Sustainability', 1, 2, 'VAC', 'crs-ba-ss', 'dept-geog', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), credits = VALUES(credits);

-- Humanities Subjects (MJC: 6, MIC: 4, MDC: 4, AEC: 2, SEC: 2, VAC: 2)
INSERT INTO subjects (id, code, name, semester, credits, category, course_id, department_id, created_at) VALUES
('subj-hum-mjc-hind', 'BA-HUM-S1-MJC-HINDI', 'Major (MJC): Hindi Sahitya Ka Itihas', 1, 6, 'MJC', 'crs-ba-hum', 'dept-hind', NOW()),
('subj-hum-mjc-engl', 'BA-HUM-S1-MJC-ENGLISH', 'Major (MJC): English Literature (14th to 17th Century)', 1, 6, 'MJC', 'crs-ba-hum', 'dept-engl', NOW()),
('subj-hum-mjc-urdu', 'BA-HUM-S1-MJC-URDU', 'Major (MJC): Classical Urdu Ghazal', 1, 6, 'MJC', 'crs-ba-hum', 'dept-urdu', NOW()),
('subj-hum-mjc-phil', 'BA-HUM-S1-MJC-PHILOSOPHY', 'Major (MJC): Indian & Western Epistemology', 1, 6, 'MJC', 'crs-ba-hum', 'dept-phil', NOW()),
('subj-hum-mic-engl', 'BA-HUM-S1-MIC-ENGLISH', 'Minor (MIC): Literary Appreciation & Composition', 1, 4, 'MIC', 'crs-ba-hum', 'dept-engl', NOW()),
('subj-hum-mic-hind', 'BA-HUM-S1-MIC-HINDI', 'Minor (MIC): Kavyashastra Evam Bhashavigyan', 1, 4, 'MIC', 'crs-ba-hum', 'dept-hind', NOW()),
('subj-hum-mdc-01',   'BA-HUM-S1-MDC-HISTORY', 'Multidisciplinary (MDC): Cultural Heritage of India', 1, 4, 'MDC', 'crs-ba-hum', 'dept-hist', NOW()),
('subj-hum-aec-01',   'BA-HUM-S1-AEC-HINDI', 'Ability Enhancement (AEC): Modern Indian Language', 1, 2, 'AEC', 'crs-ba-hum', 'dept-hind', NOW()),
('subj-hum-sec-01',   'BA-HUM-S1-SEC-CREATIVE-WRITING', 'Skill Enhancement (SEC): Creative Writing & Translation', 1, 2, 'SEC', 'crs-ba-hum', 'dept-engl', NOW()),
('subj-hum-vac-01',   'BA-HUM-S1-VAC-HUMAN-VALUES', 'Value Added Course (VAC): Human Values & Vedic Ethics', 1, 2, 'VAC', 'crs-ba-hum', 'dept-phil', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), credits = VALUES(credits);

-- Commerce Subjects (MJC: 6, MIC: 4, MDC: 4, AEC: 2, SEC: 2, VAC: 2)
INSERT INTO subjects (id, code, name, semester, credits, category, course_id, department_id, created_at) VALUES
('subj-com-mjc-acc', 'BCOM-S1-MJC-ACCOUNTING-FINANCE', 'Major (MJC): Financial Accounting & Reporting', 1, 6, 'MJC', 'crs-bcom', 'dept-comm', NOW()),
('subj-com-mjc-mkt', 'BCOM-S1-MJC-MARKETING', 'Major (MJC): Principles of Marketing Management', 1, 6, 'MJC', 'crs-bcom', 'dept-comm', NOW()),
('subj-com-mic-bm',  'BCOM-S1-MIC-BUSINESS-MANAGEMENT', 'Minor (MIC): Business Organization & Management', 1, 4, 'MIC', 'crs-bcom', 'dept-comm', NOW()),
('subj-com-mic-econ','BCOM-S1-MIC-BUSINESS-ECONOMICS', 'Minor (MIC): Business Economics', 1, 4, 'MIC', 'crs-bcom', 'dept-econ', NOW()),
('subj-com-mdc-01',  'BCOM-S1-MDC-BANKING', 'Multidisciplinary (MDC): Banking Operations & Financial Markets', 1, 4, 'MDC', 'crs-bcom', 'dept-comm', NOW()),
('subj-com-aec-01',  'BCOM-S1-AEC-COMMUNICATION', 'Ability Enhancement (AEC): Business Communication', 1, 2, 'AEC', 'crs-bcom', 'dept-comm', NOW()),
('subj-com-sec-01',  'BCOM-S1-SEC-DIGITAL-ACCOUNTING', 'Skill Enhancement (SEC): Computerized Accounting (Tally & GST)', 1, 2, 'SEC', 'crs-bcom', 'dept-comm', NOW()),
('subj-com-vac-01',  'BCOM-S1-VAC-FINANCIAL-LITERACY', 'Value Added Course (VAC): Financial Literacy & Investor Awareness', 1, 2, 'VAC', 'crs-bcom', 'dept-comm', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), credits = VALUES(credits);

SET FOREIGN_KEY_CHECKS = 1;
