# ERP Project - Agent Architecture & Development Guidelines (PHP 8.2+ • Alpine.js • MySQL)

This document establishes the mandatory architectural rules, coding standards, security directives, and workflow patterns for all developers and AI agents implementing the **MKR College ERP System**.

---

## 1. System Vision & Architecture

- **Institution**: Matashri Kaushalya Ramdev Dr. Ganesh Ray Degree College (Sitamarhi, Bihar).
- **University Affiliation**: B.R.A. Bihar University (BRABU), Muzaffarpur.
- **Server Environment**: Native Apache / LiteSpeed Web Server on Hostinger Web Hosting.
- **Backend Stack**: PHP 8.2+ with strict object-oriented typing (`declare(strict_types=1);`).
- **Client Reactivity**: Lightweight Alpine.js (v3.x ~15KB) embedded in server-rendered PHP templates.
- **Database Engine**: MySQL 8.0 / MariaDB with InnoDB engine, utf8mb4 collation, foreign keys, and ACID transactions.
- **Routing**: Single front-controller pattern routed through `public/index.php`.
- **Zero Idle Overhead**: No persistent Node.js or PM2 daemon required. Fast cold starts and instant execution on shared and cloud hosting.

---

## 2. Core Architectural Principles

### A. 100% Strict Typing
Every single PHP file in `src/`, `tests/`, and helper scripts **must start with**:
```php
<?php
declare(strict_types=1);
```
All class properties, method parameters, and return types must be explicitly typed (`string`, `int`, `float`, `bool`, `array`, `?string`, `void`, etc.).

### B. Hostinger LiteSpeed / Apache Compatibility
- The root `.htaccess` securely delegates all web traffic to `public/index.php`.
- The `public/.htaccess` routes non-file/non-directory requests to `index.php` with `[L,QSA]`.
- Direct web access to `src/`, `views/`, `database/`, `vendor/`, and `.env` files is strictly forbidden via rewrite rules.
- Public web root corresponds to Hostinger's `public_html/`.

### C. Zero Dummy Data Policy (Strict Enforcement)
- **Zero hardcoded mocks**: Under no circumstances should views or controllers display mock student counts, hardcoded recent payments, or simulated test arrays in production flows.
- Every metric, table row, badge, ticker notice, and report must query live database records via PDO prepared statements.
- When tables are empty, clean and informative empty-state UI components must be rendered.

### D. Separation of Concerns
1. **Core (`src/Core/`)**: Reusable foundational infrastructure (Database singleton, Request, Response, Router, Auth, Validator, Logger).
2. **Config (`src/Config/`)**: Institutional domain constants, CBCS subject catalogs, fee tables, and pure formatting utilities.
3. **Services (`src/Services/`)**: ACID business logic, sequential number generators, admission pipelines, and semester progression.
4. **Controllers (`src/Controllers/`)**: HTTP endpoints, input sanitization, service invocation, and view rendering.
5. **Views (`views/`)**: HTML5 semantic layouts and components with Alpine.js reactive bindings (`x-data`, `x-model`, `x-show`).
6. **Tests (`tests/`)**: Automated PHPUnit verification covering domain logic, routes, and link integrity.

---

## 3. Database & Transactional Integrity

- **Prepared Statements**: Raw string interpolation or concatenation in SQL queries is strictly banned. Always bind parameters via PDO:
  ```php
  $stmt = $db->prepare('SELECT * FROM students WHERE application_no = :app_no LIMIT 1');
  $stmt->execute(['app_no' => $applicationNo]);
  ```
- **ACID Transactions**: Multi-table updates (e.g. admission approval, semester fee payment + student level advancement + audit logging) must be wrapped inside `try { $pdo->beginTransaction(); ... $pdo->commit(); } catch (Throwable $e) { $pdo->rollBack(); throw $e; }`.
- **Audit Logging**: Any change in student status, financial records, marks entry, or credential modification must write an entry to `audit_logs`.

---

## 4. Security Directives

1. **Password Hashing**: Passwords must always be hashed with native `password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12])` and verified with `password_verify()`.
2. **Session Security**: Session cookies must be issued with `HttpOnly`, `Secure` (in HTTPS/production), and `SameSite='Lax'`. Session fixation attacks must be prevented with `session_regenerate_id(true)`.
3. **Role-Based Access Control (RBAC)**: Controllers must invoke `Auth::requireRole(['SUPER_ADMIN'])` or similar before performing privileged operations.
4. **Input Validation**: All incoming requests must be parsed through `Validator::validate($request->all(), $rules)`.
5. **Cross-Site Scripting (XSS)**: All server-rendered strings printed in views must be escaped with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` or rendered via Alpine's `:text` binding.

---

## 5. Alpine.js Client Reactivity Conventions

- Alpine.js is used for:
  - Multi-slide campus hero slideshows with 7-second timers.
  - Cascading CBCS subject dropdowns (`Social Science` -> `History`, `Political Science`, etc.).
  - Live computation of BRABU 30-mark CIA scores (`Mid (15) + Assignment (10) + Attendance (5)`).
  - Modal dialogues (Fee review, student status check, password reset).
  - Debounced live search with micro-AJAX `fetch()`.
- Use `x-cloak` alongside CSS `[x-cloak] { display: none !important; }` to prevent flash of unstyled content (FOUC).

---

## 6. Code Quality & Zero-Noise Policy

1. **No Unwanted / Noisy Comments**:
   - Never add obvious, redundant comments (e.g., `// loop through students`, `// return response`, `// end of if`).
   - Code must be clean, elegant, concise, and self-documenting.
   - Use only essential PHPDoc type annotations (`/** @param ... */`) and vital institutional business logic rules (e.g., BRABU CBCS calculation formulas).

2. **100% Strict Type Safety**:
   - Every PHP file starts with `declare(strict_types=1);`.
   - Every method, function, and property must have explicit scalar types and explicit return types.
   - Alpine.js reactive components must use explicit checks and clean validation.

3. **Strict Input Validation**:
   - Never trust input. Validate all incoming request payloads against strict validation schemas before executing any database query or business logic.
   - Reject malformed data immediately with structured validation errors.

4. **Graceful Error Handling & Structured Logging**:
   - Catch exceptions gracefully with human-readable, user-friendly error messages (never expose raw SQL error traces or stack traces to the user).
   - Log critical errors with structured context (timestamp, user ID, action, message) using `App\Core\Logger`.

5. **Critical Workflow Testing**:
   - Maintain unit and integration tests covering all critical business workflows (fee invoice generation, sequential semester progression, seat/roll formatting, and date window evaluations).

---

## 7. Workflow for Agents

1. Before implementing any major module, create an architectural plan and confirm specifications.
2. Build data models and migrations first.
3. Implement backend services and API validation.
4. Construct responsive, accessible UI views with loading and empty states.
5. Verify build, tests, and runtime integrity before committing changes.
