-- Production Enterprise Database Schema for MKR College ERP
-- Institution: Matashri Kaushalya Ramdev Dr. Ganesh Ray Degree College (Sitamarhi, Bihar)
-- Affiliation: B.R.A. Bihar University (BRABU), Muzaffarpur
-- Collation: utf8mb4_unicode_ci | Engine: InnoDB

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, notices, grievance_tickets, continuous_internal_assessments,
attendance_records, attendance_sessions, promotion_histories, fee_payments,
registration_windows, student_semester_records, student_subject_enrollments,
faculty_profiles, students, subjects, courses, departments, academic_sessions, users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users & RBAC
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(191) NOT NULL,
    role ENUM('SUPER_ADMIN', 'PRINCIPAL', 'FACULTY', 'TEACHER', 'ACCOUNTANT', 'CLERK', 'STUDENT', 'PARENT') NOT NULL DEFAULT 'STUDENT',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    phone VARCHAR(32) UNIQUE,
    last_login_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Academic Sessions
CREATE TABLE academic_sessions (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(64) NOT NULL UNIQUE,
    start_year INT NOT NULL,
    end_year INT NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Departments
CREATE TABLE departments (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(128) NOT NULL UNIQUE,
    code VARCHAR(32) NOT NULL UNIQUE,
    faculty_type ENUM('Social Science', 'Humanities', 'Commerce', 'Science') NOT NULL,
    head_of_dept VARCHAR(128),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dept_faculty (faculty_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Courses
CREATE TABLE courses (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    code VARCHAR(32) NOT NULL UNIQUE,
    duration_year INT NOT NULL DEFAULT 4,
    total_semesters INT NOT NULL DEFAULT 8,
    department_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    INDEX idx_course_dept (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Subjects (CBCS Categories)
CREATE TABLE subjects (
    id VARCHAR(36) PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(191) NOT NULL,
    semester INT NOT NULL,
    credits INT NOT NULL DEFAULT 4,
    category ENUM('MJC', 'MIC', 'MDC', 'AEC', 'SEC', 'VAC') NOT NULL,
    course_id VARCHAR(36) NOT NULL,
    department_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    INDEX idx_subject_course_sem (course_id, semester, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Students
CREATE TABLE students (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) UNIQUE,
    application_no VARCHAR(64) NOT NULL UNIQUE,
    registration_code VARCHAR(64) UNIQUE,
    college_roll_no VARCHAR(64) UNIQUE,
    university_reg_no VARCHAR(64) UNIQUE,
    university_app_no VARCHAR(64),
    is_overdue TINYINT(1) NOT NULL DEFAULT 0,
    marked_for_deletion_at DATETIME,
    full_name VARCHAR(191) NOT NULL,
    first_name VARCHAR(64),
    middle_name VARCHAR(64),
    last_name VARCHAR(64),
    father_name VARCHAR(191) NOT NULL,
    father_phone VARCHAR(32),
    mother_name VARCHAR(191) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Transgender') NOT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'General',
    religion VARCHAR(64) DEFAULT 'Hinduism',
    marital_status VARCHAR(32) DEFAULT 'Unmarried',
    caste VARCHAR(64),
    aadhar_no VARCHAR(32),
    apaar_no VARCHAR(32),
    phone VARCHAR(32) NOT NULL,
    alternate_phone VARCHAR(32),
    email VARCHAR(191) NOT NULL,
    address TEXT NOT NULL,
    current_address TEXT,
    photo_url VARCHAR(255),
    signature_url VARCHAR(255),
    session_id VARCHAR(36) NOT NULL,
    course_id VARCHAR(36) NOT NULL,
    current_semester INT NOT NULL DEFAULT 1,
    status ENUM('APPLIED', 'DOCUMENT_VERIFIED', 'ADMITTED', 'PROMOTED', 'DETAINED', 'SUSPENDED', 'PASSED_OUT', 'REJECTED') NOT NULL DEFAULT 'APPLIED',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    INDEX idx_student_search (application_no, registration_code, college_roll_no, phone),
    INDEX idx_student_session_status (session_id, course_id, current_semester, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Student Semester Progression Records
CREATE TABLE student_semester_records (
    id VARCHAR(36) PRIMARY KEY,
    student_id VARCHAR(36) NOT NULL,
    semester INT NOT NULL,
    academic_session_id VARCHAR(36) NOT NULL,
    status ENUM('ENROLLED', 'FEES_PAID', 'SUBJECTS_CONFIRMED', 'EXAM_FORM_SUBMITTED', 'SEMESTER_COMPLETED', 'DETAINED') NOT NULL DEFAULT 'ENROLLED',
    is_fee_cleared TINYINT(1) NOT NULL DEFAULT 0,
    is_subject_locked TINYINT(1) NOT NULL DEFAULT 0,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    sgpa DECIMAL(4, 2),
    remarks VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_semester (student_id, semester),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Registration Windows (Time Limits & 30-Day Grace)
CREATE TABLE registration_windows (
    id VARCHAR(36) PRIMARY KEY,
    academic_session_id VARCHAR(36) NOT NULL,
    course_id VARCHAR(36) NOT NULL,
    semester INT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    grace_days INT NOT NULL DEFAULT 30,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_window (course_id, semester, academic_session_id),
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Faculty Profiles
CREATE TABLE faculty_profiles (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) UNIQUE,
    employee_code VARCHAR(64) NOT NULL UNIQUE,
    full_name VARCHAR(191) NOT NULL,
    designation VARCHAR(64) NOT NULL,
    department_id VARCHAR(36) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(32) NOT NULL UNIQUE,
    qualification VARCHAR(128),
    specialization VARCHAR(128),
    joining_date DATE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Fee Payments (Immutable Financial Ledger)
CREATE TABLE fee_payments (
    id VARCHAR(36) PRIMARY KEY,
    receipt_no VARCHAR(64) NOT NULL UNIQUE,
    student_id VARCHAR(36) NOT NULL,
    target_semester INT NOT NULL,
    payment_type ENUM('ADMISSION_CUM_SEM1', 'SEMESTER_PROMOTION', 'EXAMINATION_FEE', 'MIGRATION_FEE', 'MISCELLANEOUS') NOT NULL DEFAULT 'SEMESTER_PROMOTION',
    title VARCHAR(191) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('CASH', 'UPI', 'ONLINE_GATEWAY', 'BANK_CHALLAN') NOT NULL DEFAULT 'ONLINE_GATEWAY',
    status ENUM('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
    transaction_ref VARCHAR(128) UNIQUE,
    gateway_order_id VARCHAR(128) UNIQUE,
    gateway_signature TEXT,
    is_promotion_triggered TINYINT(1) NOT NULL DEFAULT 0,
    paid_at DATETIME,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    INDEX idx_fee_payment_search (student_id, target_semester, status),
    INDEX idx_fee_status_date (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Promotion History
CREATE TABLE promotion_histories (
    id VARCHAR(36) PRIMARY KEY,
    student_id VARCHAR(36) NOT NULL,
    from_semester INT NOT NULL,
    to_semester INT NOT NULL,
    academic_session_id VARCHAR(36) NOT NULL,
    trigger_payment_id VARCHAR(36) UNIQUE,
    promoted_by VARCHAR(64) NOT NULL DEFAULT 'SYSTEM_AUTO_PAYMENT',
    remarks TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE RESTRICT,
    FOREIGN KEY (trigger_payment_id) REFERENCES fee_payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Attendance Sessions & Records
CREATE TABLE attendance_sessions (
    id VARCHAR(36) PRIMARY KEY,
    faculty_id VARCHAR(36) NOT NULL,
    subject_id VARCHAR(36) NOT NULL,
    academic_session_id VARCHAR(36) NOT NULL,
    semester INT NOT NULL,
    session_date DATE NOT NULL,
    time_slot VARCHAR(64) NOT NULL,
    room_no VARCHAR(32),
    topic_covered TEXT,
    total_present INT NOT NULL DEFAULT 0,
    total_absent INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty_profiles(id) ON DELETE RESTRICT,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_records (
    id VARCHAR(36) PRIMARY KEY,
    session_id VARCHAR(36) NOT NULL,
    student_id VARCHAR(36) NOT NULL,
    faculty_id VARCHAR(36) NOT NULL,
    subject_id VARCHAR(36) NOT NULL,
    semester INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('PRESENT', 'ABSENT', 'LEAVE', 'EXCUSED') NOT NULL DEFAULT 'PRESENT',
    remarks VARCHAR(128),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance (session_id, student_id),
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_att_student (student_id, subject_id, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Continuous Internal Assessment (30 Marks BRABU Breakdown)
CREATE TABLE continuous_internal_assessments (
    id VARCHAR(36) PRIMARY KEY,
    faculty_id VARCHAR(36) NOT NULL,
    subject_id VARCHAR(36) NOT NULL,
    student_id VARCHAR(36) NOT NULL,
    semester INT NOT NULL,
    mid_term_exam DECIMAL(5, 2) NOT NULL DEFAULT 0.00, -- Max 15
    assignment DECIMAL(5, 2) NOT NULL DEFAULT 0.00,    -- Max 10
    attendance_score DECIMAL(5, 2) NOT NULL DEFAULT 0.00, -- Max 5
    total_cia_score DECIMAL(5, 2) NOT NULL DEFAULT 0.00, -- Max 30
    is_finalized TINYINT(1) NOT NULL DEFAULT 0,
    evaluation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cia (student_id, subject_id, semester),
    FOREIGN KEY (faculty_id) REFERENCES faculty_profiles(id) ON DELETE RESTRICT,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Grievance Tickets
CREATE TABLE grievance_tickets (
    id VARCHAR(36) PRIMARY KEY,
    ticket_no VARCHAR(64) NOT NULL UNIQUE,
    student_id VARCHAR(36),
    submitted_by ENUM('STUDENT', 'PARENT', 'PUBLIC') NOT NULL DEFAULT 'STUDENT',
    name VARCHAR(191) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(32) NOT NULL,
    category VARCHAR(64) NOT NULL,
    subject VARCHAR(191) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('PENDING', 'IN_REVIEW', 'RESOLVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    admin_remarks TEXT,
    resolved_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_grievance_status (status, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Notices
CREATE TABLE notices (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('Academic', 'Admission', 'Exam', 'General') NOT NULL DEFAULT 'General',
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    attachment VARCHAR(255),
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notice_published (is_published, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Student CBCS Subject Enrollments
CREATE TABLE student_subject_enrollments (
    id VARCHAR(36) PRIMARY KEY,
    student_id VARCHAR(36) NOT NULL,
    subject_id VARCHAR(36) NOT NULL,
    semester INT NOT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_subject_sem (student_id, subject_id, semester),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    INDEX idx_enrollment_lookup (student_id, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Audit Logs
CREATE TABLE audit_logs (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36),
    action VARCHAR(64) NOT NULL,
    entity VARCHAR(64) NOT NULL,
    entity_id VARCHAR(64),
    old_values JSON,
    new_values JSON,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id, timestamp),
    INDEX idx_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
