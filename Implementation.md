# MKR College ERP: PHP 8.2+ • Alpine.js • MySQL Master Implementation Plan

This document establishes the authoritative architecture, relational database schema, Alpine.js reactive UI integration, Hostinger production deployment plan, and phase-by-phase agent prompt sequence to construct the **MKR College ERP System** from scratch.

---

## 1. System Vision & Language Switch Architecture

### The Transition
| Layer | Current Codebase | Target Architecture |
|---|---|---|
| **Server & Language** | Node.js v20+ / Next.js 15 (TypeScript) | **PHP 8.2+ (Strict Object-Oriented Typing with `declare(strict_types=1);`)** |
| **Client Reactivity** | React 18 Components | **HTML5 + Tailwind CSS + Alpine.js (v3.x ~15KB)** |
| **Database & ORM** | Prisma ORM with MySQL | **Raw MySQL 8.0 / MariaDB (InnoDB, utf8mb4) via PDO Prepared Statements** |
| **Routing & Dispatch** | Next.js App Router | **Lightweight PHP Front-Controller Pattern with Apache `.htaccess`** |
| **Authentication** | JWT in Next.js Cookies | **HttpOnly, Secure, SameSite='Lax' Native PHP Sessions / Signed Tokens** |
| **Hostinger Fit** | Standalone Node.js process via PM2 | **Native Apache / LiteSpeed Web Server, 0 Idle RAM, Instant Execution** |

### Why Alpine.js is the Ideal Choice
Alpine.js provides the reactive power of Vue/React directly inside server-rendered PHP templates without any Node.js build step or bundler overhead:
- **Zero Build Step**: Loaded via a single lightweight `<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>` or stored locally in `public/js/vendor/alpine.min.js`.
- **Declarative Modals & Tabs**: Modals (Fee review, status lookup, password reset) toggle with simple `x-data="{ open: false }"` and `x-show="open"`.
- **Live Computations**: CBCS subject cascading and Continuous Internal Assessment (CIA) 30-mark calculations update immediately in memory.
- **Micro-AJAX**: Uses browser-native `fetch()` to call clean PHP JSON endpoints (`/api/...`) for live search and background state persistence.

---

## 2. Directory Structure & 1:1 File Mapping

```
MKR-erp/
├── .env.example                               # Hostinger MySQL Credentials & App Keys
├── .htaccess                                  # Apache/LiteSpeed URL Rewriter
├── AGENTS.md                                  # Coding Standards, Strict Typing & Architecture Rules
├── DEPLOYMENT.md                              # Hostinger Production Deployment Guide
├── composer.json                              # PSR-4 Autoloading ("App\\": "src/") & PHPUnit
├── phpunit.xml                                # PHPUnit 10 Configuration
├── tailwind.config.js                         # Tailwind Theme Config
│
├── database/                                  # Database DDL & Seed Files
│   ├── schema.sql                             # 16 InnoDB Tables (Mirrors prisma/schema.prisma)
│   └── seed.sql                               # Academic Session, Courses, Super Admin
│
├── public/                                    # Hostinger public_html Document Root
│   ├── index.php                              # Front-Controller & Central HTTP Dispatcher
│   ├── .htaccess                              # Public directory security & asset caching
│   ├── css/
│   │   └── app.css                            # Compiled Tailwind CSS + Print Stylesheet
│   ├── js/
│   │   ├── vendor/
│   │   │   └── alpine.min.js                  # Alpine.js Core (15KB static bundle)
│   │   └── app.js                             # Global helper utilities & CSRF handler
│   └── assets/                                # Static Media Assets (Mirrors public/assets/)
│       ├── logo/
│       │   ├── mkr-college-emblem.webp
│       │   └── mkr-dr-grd-college-logo.webp
│       └── erp-panel/
│           ├── image-1.png                    # Campus Building
│           └── image-2.webp                   # Award Ceremony
│
├── src/                                       # Core Backend Application (Mirrors src/lib/)
│   ├── Core/                                  # Base Foundation
│   │   ├── Database.php                       # Singleton PDO (Mirrors src/lib/prisma.ts)
│   │   ├── Request.php                        # HTTP Request Abstraction
│   │   ├── Response.php                       # JSON / HTML Response Dispatcher
│   │   ├── Router.php                         # Fast Parameterized Routing Engine
│   │   ├── Auth.php                           # Bcrypt Hasher & JWT (Mirrors src/lib/auth.ts)
│   │   ├── AuthServer.php                     # Session/Cookie Helper (Mirrors src/lib/auth-server.ts)
│   │   └── Validator.php                      # Strict Schema Validator (Mirrors Zod)
│   │
│   ├── Config/
│   │   └── CollegeConfig.php                  # Institutional Catalog (Mirrors src/lib/college-config.ts)
│   │
│   ├── Services/                              # Business Logic (Mirrors src/lib/services/)
│   │   ├── AcademicStructureService.php       # Mirrors src/lib/services/academic-structure.ts
│   │   ├── PromotionService.php               # Mirrors src/lib/services/promotion.ts
│   │   └── StudentDirectoryService.php        # Mirrors src/lib/services/student-directory.ts
│   │
│   └── Controllers/                           # Web & API Route Handlers (Mirrors src/app/api/)
│       ├── HomeController.php                 # Handles /
│       ├── AuthController.php                 # Handles /login & /api/auth/*
│       ├── AdmissionController.php            # Handles /admission & /api/admissions
│       ├── GrievanceController.php            # Handles /complaint & /api/grievances
│       ├── ContactController.php              # Handles /contact
│       ├── StudentController.php              # Handles /student & /api/payments/*
│       ├── FacultyController.php              # Handles /faculty & /api/faculty/*
│       └── Admin/                             # <--- DEDICATED ADMIN CONTROLLERS
│           ├── PrincipalController.php        # Handles /admin/principal
│           └── StudentDirectoryController.php # Handles /admin/students & /api/students
│
├── views/                                     # HTML5 + Alpine.js Templates (Mirrors src/app/ & src/components/)
│   ├── layouts/
│   │   ├── main.php                           # Base HTML5 Shell (Mirrors src/app/layout.tsx)
│   │   └── admin.php                          # Full-Screen Dashboard Layout
│   ├── components/                            # Reusable UI Blocks (Mirrors src/components/)
│   │   ├── navbar.php                         # Mirrors src/components/Navbar.tsx
│   │   ├── footer.php                         # Mirrors src/components/Footer.tsx
│   │   ├── college_logo.php                   # Mirrors src/components/CollegeLogo.tsx
│   │   └── notice_ticker.php                  # Mirrors src/components/NoticeTicker.tsx
│   └── pages/                                 # Exact Page Directory Mirroring
│       ├── index.php                          # Mirrors src/app/page.tsx (Home)
│       ├── login/
│       │   └── index.php                      # Mirrors src/app/login/page.tsx
│       ├── admission/
│       │   └── index.php                      # Mirrors src/app/admission/page.tsx
│       ├── complaint/
│       │   └── index.php                      # Mirrors src/app/complaint/page.tsx
│       ├── contact/
│       │   └── index.php                      # Mirrors src/app/contact/page.tsx
│       ├── student/
│       │   └── index.php                      # Mirrors src/app/student/page.tsx
│       ├── faculty/
│       │   └── index.php                      # Mirrors src/app/faculty/page.tsx
│       └── admin/                             # <--- EXACT ADMIN FOLDER MIRRORING
│           ├── principal/
│           │   └── index.php                  # Mirrors src/app/admin/principal/page.tsx
│           └── students/
│               └── index.php                  # Mirrors src/app/admin/students/page.tsx
│
└── tests/                                     # Automated PHPUnit Test Suite (Mirrors tests/)
    ├── BusinessLogicTest.php                  # Mirrors tests/business-logic.test.ts
    ├── LinksAndAssetsTest.php                 # Mirrors tests/links-and-assets.test.ts
    ├── ZeroDummyTest.php                      # Mirrors tests/zero-dummy.test.ts
    └── ApiContractTest.php                    # Mirrors tests/api-and-http.test.ts
---

## 3. Production MySQL Relational Schema (`database/schema.sql`)

```sql
-- Production Enterprise Database Schema for MKR College ERP
-- Matashri Kaushalya Ramdev Dr. Ganesh Ray Degree College (Sitamarhi, Bihar)
-- Affiliated with B.R.A. Bihar University (BRABU), Muzaffarpur

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

-- 16. Audit Logs
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
```

---

## 4. Alpine.js Interactive UI Components Specification

Here is how Alpine.js is integrated directly into the PHP views:

### A. 7-Second Campus Slideshow (`views/pages/login.php`)
```html
<div x-data="{ 
    current: 0, 
    slides: ['/assets/erp-panel/image-1.png', '/assets/erp-panel/image-2.webp'],
    init() {
        setInterval(() => { this.current = (this.current + 1) % this.slides.length; }, 7000);
    }
}" class="relative h-full w-full bg-slate-950 overflow-hidden">
    <template x-for="(slide, index) in slides" :key="index">
        <img :src="slide" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-1000"
             :class="current === index ? 'opacity-100 z-10' : 'opacity-0 z-0'">
    </template>
</div>
```

### B. CBCS Dynamic Subject Cascading & Fee Review Modal (`views/pages/admission.php`)
```html
<div x-data="{
    faculty: 'Social Science',
    courseFee: 2800,
    feeModalOpen: false,
    subjects: {
        'Social Science': { fee: 2800, mjc: ['HISTORY', 'POLITICAL SCIENCE', 'ECONOMICS'], mic: ['POLITICAL SCIENCE', 'HISTORY'] },
        'Humanities': { fee: 2800, mjc: ['HINDI', 'ENGLISH', 'URDU', 'PHILOSOPHY'], mic: ['ENGLISH', 'HINDI'] },
        'Commerce': { fee: 3200, mjc: ['ACCOUNTING & FINANCE', 'MARKETING'], mic: ['BUSINESS MANAGEMENT', 'ECONOMICS'] }
    },
    updateFaculty() {
        this.courseFee = this.subjects[this.faculty].fee;
    }
}">
    <!-- Faculty Select -->
    <select x-model="faculty" @change="updateFaculty()" name="faculty" class="form-select">
        <option value="Social Science">Social Science (₹2,800)</option>
        <option value="Humanities">Humanities (₹2,800)</option>
        <option value="Commerce">Commerce (₹3,200)</option>
    </select>

    <!-- MJC Subject Dropdown (Auto-updates without reload) -->
    <select name="mjcSubject" class="form-select">
        <template x-for="subj in subjects[faculty].mjc" :key="subj">
            <option :value="subj" x-text="subj"></option>
        </template>
    </select>

    <!-- Fee Review Popup Modal -->
    <button type="button" @click="feeModalOpen = true" class="btn-primary">Review Fee & Apply</button>

    <div x-show="feeModalOpen" x-cloak class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl max-w-md w-full shadow-2xl" @click.away="feeModalOpen = false">
            <h3 class="text-lg font-bold text-[#800000]">Semester 1 Fee Summary</h3>
            <p class="text-sm text-slate-600 mt-2">Program Total: <span class="font-bold text-slate-900" x-text="'₹' + courseFee"></span></p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="feeModalOpen = false" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-confirm">Confirm & Submit Application</button>
            </div>
        </div>
    </div>
</div>
```

### C. Live Debounced Student Search (`views/pages/admin/students.php`)
```html
<div x-data="{
    search: '',
    students: [],
    loading: false,
    timer: null,
    fetchStudents() {
        clearTimeout(this.timer);
        this.timer = setTimeout(async () => {
            this.loading = true;
            const res = await fetch(`/api/students?search=${encodeURIComponent(this.search)}`);
            const data = await res.json();
            this.students = data.data || [];
            this.loading = false;
        }, 300);
    }
}" x-init="fetchStudents()">
    <input type="text" x-model="search" @input="fetchStudents()" placeholder="Search roll no, phone, name..." class="search-input">
    
    <div x-show="loading" class="text-sm text-slate-500 py-2">Searching live directory...</div>

    <table class="w-full">
        <tbody>
            <template x-for="s in students" :key="s.id">
                <tr>
                    <td x-text="s.collegeRollNo || s.applicationNo"></td>
                    <td x-text="s.fullName"></td>
                    <td x-text="s.courseCode"></td>
                    <td x-text="'Sem ' + s.currentSemester"></td>
                </tr>
            </template>
        </tbody>
    </table>
</div>
```

### D. BRABU 30-Mark CIA Real-Time Calculator (`views/pages/faculty/index.php`)
```html
<tr x-data="{
    mid: 0,
    assign: 0,
    att: 0,
    get total() { 
        return (parseFloat(this.mid || 0) + parseFloat(this.assign || 0) + parseFloat(this.att || 0)).toFixed(2); 
    }
}">
    <td><input type="number" min="0" max="15" x-model.number="mid" class="w-16"> /15</td>
    <td><input type="number" min="0" max="10" x-model.number="assign" class="w-16"> /10</td>
    <td><input type="number" min="0" max="5" x-model.number="att" class="w-16"> /5</td>
    <td class="font-bold text-[#800000]" x-text="total + ' /30'"></td>
</tr>
```

---

## 5. Phase-by-Phase Implementation Prompts for Agents

---

### Phase 1: Environment, Directory Structure, Standards & Deployment Docs
```markdown
Phase 1: Project Scaffolding & Standards Setup
1. Initialize the directory structure for `mkr-college-erp-php` according to the master plan.
2. Create `composer.json` with PSR-4 autoloading (`"App\\": "src/"`), PHP >= 8.2, vlucas/phpdotenv, and PHPUnit 10.
3. Write `AGENTS.md` enforcing:
   - 100% strict typing: every PHP file must begin with `declare(strict_types=1);`.
   - Hostinger LiteSpeed/Apache compatibility with `.htaccess` rewrites targeting `public/index.php`.
   - Zero Dummy Data Policy: all metrics and tables must execute real SQL queries.
4. Write `DEPLOYMENT.md` detailing step-by-step instructions for Hostinger cPanel/hPanel, MySQL database setup, phpMyAdmin migration import, and folder mapping.
5. Create `.env.example` with `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_ENV`, and `APP_SECRET`.
6. Implement `public/.htaccess` and root `.htaccess` to securely route requests to `public/index.php` and forbid direct access to `src/`, `views/`, and `database/`.
```

---

### Phase 2: MySQL DDL Schema & Institutional Seed Data
```markdown
Phase 2: Database Schema & Seeds
1. Create `database/schema.sql` implementing all 16 relational InnoDB tables with utf8mb4_unicode_ci collation, foreign keys, compound indexes, and audit logging as defined in Section 3 of the master blueprint.
2. Create `database/seed.sql` containing:
   - Current Academic Session `2026-2030` (`is_current = 1`).
   - 12 Academic Departments (History, Political Science, Economics, Commerce, Hindi, English, Philosophy, Sociology, Psychology, Geography, Urdu, Sanskrit).
   - 3 Official UG Courses matching mkrcollege.com:
     * `BA-SS`: Bachelor's of Arts (BA) – Social Science (Total Fee: ₹2,800)
     * `BA-HUM`: Bachelor's of Arts (B.A) – Humanities (Total Fee: ₹2,800)
     * `BCOM`: Bachelor's of Commerce (B.COM) (Total Fee: ₹3,200)
   - Singleton Super Admin account: email `mkrcollege@gmail.com`, password hash generated via bcrypt with cost 12 (`Mkr@arvind98`), role `SUPER_ADMIN`.
   - Initial published notice in `notices` table.
```

---

### Phase 3: Core Foundation, Database Singleton & Security
```markdown
Phase 3: Core Foundation & Security
1. Create `src/Core/Database.php`:
   - Singleton pattern returning a configured `PDO` connection.
   - Enforce `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, and `PDO::ATTR_EMULATE_PREPARES => false`.
2. Create `src/Core/Request.php` & `src/Core/Response.php`:
   - Strongly-typed HTTP request handling for `$_GET`, `$_POST`, raw JSON bodies, and file uploads.
   - JSON response helper with HTTP status codes and HTML view renderer helper.
3. Create `src/Core/Router.php`:
   - Lightweight regex-based router supporting `GET`, `POST`, `PATCH`, `DELETE` with route parameters and middleware support.
4. Create `src/Core/Auth.php`:
   - Bcrypt hashing using `password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12])` and `password_verify()`.
   - Secure HttpOnly, SameSite='Lax', secure cookie session manager issuing signed tokens.
   - RBAC check function `Auth::requireRole(array $allowedRoles)`.
5. Create `src/Core/Validator.php`:
   - Schema validation engine supporting required fields, emails, phone regex (`/^[6-9]\d{9}$/`), min/max lengths, and enum values, returning structured human-readable error bags.
```

---

### Phase 4: Institutional Configuration, Identity Formatters & Academic Services
```markdown
Phase 4: Domain Catalog & Academic Services
1. Create `src/Config/CollegeConfig.php`:
   - Typed constants for `COURSE_CATALOG` (`BA-SS`, `BA-HUM`, `BCOM`) with fee breakdowns.
   - 6 CBCS Subject catalogs (`FACULTY_SUBJECTS`) for Social Science, Humanities, and Commerce with `MJC`, `MIC`, `MDC`, `AEC`, `SEC`, and `VAC`.
   - Pure functions:
     * `formatApplicationNo(int $year, int $serial): string` -> `MKR-2026-00001`
     * `formatRegistrationCode(int $year, int $serial): string` -> `MKR-REG-2026-00001`
     * `formatCollegeRollNo(int $year, string $prefix, int $serial): string` -> `26/BASS/0001`
     * `evaluateRegistrationWindow(?array $window, int $targetSemester, ?DateTime $now): array`
     * `computeOverdueRetention(?string $markedForDeletionAt, int $graceDays = 30): array`
2. Create `src/Services/AcademicStructureService.php`:
   - Collision-safe sequential generators for Application Numbers, Registration Codes, and Stream Roll Numbers querying the database inside transactions.
   - CBCS subject enrollment persistence into `subjects` and `student_subject_enrollments`.
```

---

### Phase 5: UI Shell, Alpine.js Integration, Homepage & Assets
```markdown
Phase 5: Public UI Shell & Landing Page
1. Configure Tailwind CSS with the official MKR College palette:
   - Maroon: `#800000`, Maroon Border: `#5C0000`, Deep Navy: `#002B5C`, Navy Dark: `#001836`, Accent Gold: `#D4AF37`.
2. Add `public/js/vendor/alpine.min.js` and include `<script defer src="/js/vendor/alpine.min.js"></script>` in `views/layouts/main.php`.
3. Create reusable components in `views/components/`:
   - `navbar.php`: Top maroon header with BRABU affiliation text and helpline `+91-7597052444`. Responsive single-line desktop navigation and mobile dropdown drawer powered by Alpine.js (`x-data="{ mobileOpen: false }"`). Direct "ERP Login" button.
   - `footer.php`: Deep navy campus footer with college address (Punaura Dham, Sitamarhi), quick ERP links, and copyright.
   - `college_logo.php`: Logo rendering with blend-mode transparency.
   - `notice_ticker.php`: Real-time announcement ticker fetching active notices from the database.
4. Create `views/pages/home.php`:
   - Centered college emblem and official title.
   - 3 clean entry modules:
     1. Online Admission Form (Session 2026–2030) -> `/admission`
     2. Complain Form (Grievance Redressal) -> `/complaint`
     3. ERP Module (Principal • Faculty • Student) -> `/login`
5. Place official high-resolution images in `public/assets/` (`mkr-college-emblem.webp`, `image-1.png`, `image-2.webp`).
```

---

### Phase 6: Centralized Split-Screen Login & Authentication Flow
```markdown
Phase 6: Centralized Role-Based Login System
1. Create `views/pages/login.php`:
   - Exact 100vh height full-page split-screen layout without vertical body scrolling.
   - Left side (65% width): Pure campus slideshow automatically cycling between `/assets/erp-panel/image-1.png` and `image-2.webp` every 7 seconds via Alpine.js (`x-data="{ current: 0 }"`).
   - Right side (35% width): Role-based login card with 3 radio tabs:
     * "Super Admin" (placeholder: `mkrcollege@gmail.com`)
     * "Faculty" (placeholder: Staff Code / Email / Mobile)
     * "Student" (placeholder: Roll No / Reg Code / App No with DOB `YYYY-MM-DD` or Phone as password).
   - Show/Hide Password toggle and Forgot Password toast using Alpine.js.
2. Implement `src/Controllers/AuthController.php`:
   - Validate credentials against `users`, `faculty_profiles`, and `students` tables.
   - Fallback check for bootstrap Super Admin (`mkrcollege@gmail.com` / `Mkr@arvind98`).
   - Issue secure HttpOnly session cookies and redirect to the appropriate portal:
     * Super Admin -> `/admin/principal`
     * Faculty -> `/faculty`
     * Student -> `/student`
   - Handle `/logout` clearing session state.
```

---

### Phase 7: Online Admissions, CBCS Progression & Grievance Redressal
```markdown
Phase 7: Admission Engine, Progression & Public Helpdesk
1. Create `views/pages/admission.php` with Alpine.js:
   - Fresh Admission Form: Personal info, parent details, BRABU 6-tier CBCS subject picker with dynamic cascading dropdowns, dynamic fee breakdown calculation modal, and printable application summary sheet with QR/Roll placeholders.
   - Semester 2–8 Progression Mode: Student lookup by Registration Code or Roll Number, registration window validity check, and fee clearance screen.
   - Status Check Modal: Query by Application Number & Date of Birth via Alpine.js `fetch()`.
2. Create `src/Controllers/AdmissionController.php`:
   - `action=FRESH_ADMISSION`: Validate with `Validator`, insert student record with status `APPLIED`, record initial fee invoice, and enroll CBCS subjects.
   - `action=PROGRESSION_REGISTER`: Verify previous semester completion and registration window status.
3. Create `views/pages/complaint.php` & `src/Controllers/GrievanceController.php`:
   - Lodge Grievance form generating tracking code `TKT-2026-XXXXX`.
   - Live Status Tracker tab searching by ticket number and displaying administrator remarks via Alpine.js.
4. Create `views/pages/contact.php`: Helpdesk inquiry form dispatching tickets to college office.
```

---

### Phase 8: Management Portals (Super Admin, Faculty & Student Portals)
```markdown
Phase 8: Role-Specific Management Portals & Transactional Engines
1. Implement `src/Services/PromotionService.php`:
   - ACID PDO transaction executing: idempotency check on `transaction_ref`, sequential semester check, registration window validation, `fee_payments` record creation (`REC-YYYY-S...`), `student_semester_records` update, student `current_semester` advancement, and `promotion_histories` logging.
2. Implement `src/Services/StudentDirectoryService.php`:
   - Fast paginated SQL queries with multi-filter parameters (search term, course, semester, status, category).
3. Create `views/pages/admin/principal.php` & `src/Controllers/AdminController.php`:
   - Scrutiny queue for pending admissions: "Approve" (auto-assigns stream roll number `26/BASS/XXXX`) or "Reject" via Alpine.js in-page updates.
   - Registration Window Manager: Set start/end dates and grace periods per course and semester.
   - Faculty Manager: Create faculty accounts, auto-generate employee codes, toggle status, and reset passwords in modals.
   - Financial Collection Metrics: Aggregated cards for today's collection, total cleared, and pending reconciliations.
   - Official Notice Broadcaster: Post alerts displayed on the public notice ticker.
   - Grievance Resolver: Review complaints and submit administrative responses.
4. Create `views/pages/admin/students.php`: Paginated directory table with Alpine.js debounced live search.
5. Create `views/pages/faculty/index.php` & `src/Controllers/FacultyController.php`:
   - Daily Lecture Attendance Register: 1-click toggling between Present/Absent pills and "Mark All Present" button.
   - BRABU 30-Mark CIA Register: Real-time sum calculation of Mid-term Exam (max 15), Assignment (max 10), and Attendance (max 5) with automatic validation.
6. Create `views/pages/student/index.php` & `src/Controllers/StudentController.php`:
   - Student dashboard showing attendance percentage, CIA marks, fee invoice ledger, and printable fee receipts (`window.print()`).
   - One-click online payment and progression trigger to advance to the next semester.
```

---

### Phase 9: Automated Test Suite & Hostinger Verification
```markdown
Phase 9: Comprehensive Automated Test Suite (PHPUnit)
1. Write `tests/BusinessLogicTest.php`:
   - Validate canonical program fees (BA-SS: ₹2,800, BA-HUM: ₹2,800, BCOM: ₹3,200).
   - Verify roll number, application number, and registration code formatting.
   - Test registration window evaluation (OPEN, NOT_STARTED, CLOSED).
   - Test 30-day overdue grace retention calculations.
   - Test password hashing and verification.
2. Write `tests/LinksAndAssetsTest.php`:
   - Crawl all internal links and ensure every view and asset referenced (`public/assets/...`) exists on disk.
3. Write `tests/ZeroDummyTest.php`:
   - Scrape view files and controller classes to ensure zero hardcoded fake arrays, dummy student names, or mock counters exist.
4. Write `tests/ApiContractTest.php`:
   - Execute mock HTTP requests against admission lookup, student query, and grievance APIs.
5. Run full test suite: `./vendor/bin/phpunit`.
```

---

## 6. Hostinger Production Deployment Guide (`DEPLOYMENT.md`)

```markdown
# Hostinger Production Deployment Guide for MKR College ERP (PHP + MySQL)

## 1. Prerequisites in Hostinger hPanel
- Any Hostinger Web Hosting Plan (Single, Premium, Business, Cloud, or VPS).
- PHP Version set to **8.2** or **8.3** (hPanel -> Advanced -> PHP Configuration).
- Extensions enabled: `pdo_mysql`, `mbstring`, `json`, `openssl`, `curl`.

---

## 2. Database Creation & Migration
1. Go to **Databases** -> **MySQL Databases** in hPanel.
2. Create a database:
   - **Database Name**: e.g. `u123456789_mkr_erp`
   - **Username**: e.g. `u123456789_erpuser`
   - **Password**: Create a strong password.
3. Open **phpMyAdmin** for this database.
4. Click **Import** and select `database/schema.sql`. Click "Go".
5. Click **Import** and select `database/seed.sql`. Click "Go".

---

## 3. File Deployment Structure
Hostinger serves public traffic from `public_html/`. To keep backend logic secure and unexposed:

```
/home/u123456789/
├── erp_core/                                  <-- PRIVATE (Outside public_html)
│   ├── src/
│   ├── views/
│   ├── database/
│   ├── vendor/
│   ├── composer.json
│   └── .env                                   <-- Real credentials here
│
└── public_html/                               <-- PUBLIC (Web Root)
    ├── index.php                              <-- Points to ../erp_core/public/index.php
    ├── .htaccess
    ├── css/
    ├── js/
    └── assets/
```

*Tip*: If deploying into a single root, configure the Document Root in hPanel to point to `public`.

---

## 4. Environment Variables (`.env`)
Create `.env` in the project root:
```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_mkr_erp
DB_USER=u123456789_erpuser
DB_PASS=YourStrongPasswordHere
APP_ENV=production
APP_SECRET=a_random_32_character_string_for_security
```

---

## 5. LiteSpeed Web Server Optimization (`.htaccess`)
Ensure `public/.htaccess` includes:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L,QSA]
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/webp "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
```

---

## 6. Default Administrative Access
- **URL**: `https://yourdomain.com/login`
- **Role**: Select **Super Admin**
- **Username**: `mkrcollege@gmail.com`
- **Password**: `Mkr@arvind98`
```

---

## 7. Development Guidelines (`AGENTS.md`)

```markdown
# ERP Project - Agent Architecture & Development Guidelines (PHP + Alpine.js + MySQL)

## 1. System Vision & Architecture
- **Domain**: Enterprise Resource Planning (ERP) for MKR College (BRABU).
- **Target Deployment**: Hostinger Web Hosting (LiteSpeed / Apache) with PHP 8.2+ and MySQL.
- **Database**: MySQL (utf8mb4, strict mode, InnoDB ACID transactions, foreign keys).
- **Client Reactivity**: Lightweight Alpine.js (v3.x) for modals, tabs, auto-math, and search.
- **Core Principles**:
  - Modular Monorepo: Separate Core, Config, Services, Controllers, Views, and Tests.
  - Auditability: Financial transactions, status transitions, and marks entries must log to `audit_logs` with timestamps.
  - Role-Based Access Control: Granular enforcement (SUPER_ADMIN, FACULTY, STUDENT) on all controllers.

## 2. Code Quality & Standards
- **Type Safety**: 100% strict typing. Every PHP file must begin with `declare(strict_types=1);`.
- **Validation**: Strict validation on all inputs before SQL execution.
- **Security**: Never concatenate raw strings in SQL. Always use PDO prepared statements. Passwords hashed via Bcrypt (cost 12).
- **Zero Dummy Data**: Every dashboard metric and table must fetch live database data.

## 3. Workflow for Agents
1. Create data models and run migration scripts first.
2. Implement services and validation logic.
3. Build responsive server-rendered views with Alpine.js reactive components.
4. Verify with PHPUnit tests before committing.
```
