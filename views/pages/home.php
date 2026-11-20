<?php
declare(strict_types=1);

use App\Config\CollegeConfig;
?>
<section class="relative bg-gradient-to-b from-[#800000] via-[#5C0000] to-[#001836] text-white py-16 md:py-24 overflow-hidden shadow-xl">
    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#D4AF37_1px,transparent_1px)] [background-size:16px_16px]"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <div class="flex justify-center mb-6">
            <div class="p-3 bg-white/10 rounded-full backdrop-blur-sm border border-white/20 shadow-2xl transition-transform duration-300 hover:scale-105">
                <img src="/assets/logo/mkr-college-emblem.webp" 
                     alt="MKR College Emblem" 
                     class="h-28 w-28 md:h-36 md:w-36 object-contain"
                     onerror="this.src='/assets/logo/mkr-dr-grd-college-logo.webp';">
            </div>
        </div>

        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#D4AF37]/20 border border-[#D4AF37]/40 text-[#D4AF37] text-xs font-semibold uppercase tracking-wider mb-4">
            <span class="w-2 h-2 rounded-full bg-[#D4AF37]"></span>
            <?= CollegeConfig::AFFILIATED_UNIVERSITY ?>
        </div>

        <h1 class="text-2xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-tight">
            <?= CollegeConfig::INSTITUTION_NAME ?>
        </h1>
        <p class="mt-2 text-sm sm:text-base md:text-lg text-slate-200 font-medium tracking-wide">
            Punaura Dham, Sitamarhi, Bihar (843302)
        </p>
        <p class="mt-4 max-w-2xl mx-auto text-xs sm:text-sm text-slate-300">
            Official Enterprise Resource Planning (ERP) Portal &bull; Choice Based Credit System (CBCS) Undergraduate Programs
        </p>

        <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto text-left">
            <!-- 1. Admission Module Card -->
            <div class="bg-white rounded-2xl p-6 shadow-xl text-slate-800 border-t-4 border-[#800000] transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="p-3 rounded-xl bg-red-50 text-[#800000]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </span>
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Session 2026–2030
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Online Admission Form</h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-4">
                        Fresh admissions for 4-Year CBCS Degree programs (B.A. Social Science, B.A. Humanities, and B.Com) and Semester 2–8 progression registration.
                    </p>
                </div>
                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <a href="/admission" class="btn-primary w-full text-center text-xs">
                        Apply for Admission &rarr;
                    </a>
                </div>
            </div>

            <!-- 2. Grievance Redressal Card -->
            <div class="bg-white rounded-2xl p-6 shadow-xl text-slate-800 border-t-4 border-[#002B5C] transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="p-3 rounded-xl bg-blue-50 text-[#002B5C]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 0A9 9 0 013 12a9 9 0 0115.364-6.364zm0 0l-3.536 3.536M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                            Helpdesk
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Grievance Redressal</h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-4">
                        Submit student complaints, administrative inquiries, or fee verification issues with instant tracking code (TKT-YYYY-XXXXX).
                    </p>
                </div>
                <div class="pt-4 border-t border-slate-100">
                    <a href="/complaint" class="btn-navy w-full text-center text-xs">
                        Lodge Grievance &rarr;
                    </a>
                </div>
            </div>

            <!-- 3. Centralized ERP Portal Card -->
            <div class="bg-white rounded-2xl p-6 shadow-xl text-slate-800 border-t-4 border-[#D4AF37] transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="p-3 rounded-xl bg-amber-50 text-[#800000]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                            Role-Based Desk
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Centralized ERP Login</h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-4">
                        Unified login portal for Principal/Super Admin, Faculty members (Attendance & CIA Marks), and Students (Receipts & Progression).
                    </p>
                </div>
                <div class="pt-4 border-t border-slate-100">
                    <a href="/login" class="w-full inline-flex items-center justify-center gap-2 bg-[#001836] hover:bg-[#800000] text-[#D4AF37] hover:text-white font-bold py-2.5 px-4 rounded-lg text-xs transition-colors shadow">
                        Access ERP Portal &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Official Academic Programs Section -->
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <span class="text-xs uppercase font-bold tracking-widest text-[#800000]">Undergraduate Curriculum</span>
            <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 mt-1">BRABU 4-Year CBCS Degree Programs</h2>
            <p class="text-sm text-slate-600 mt-2">
                Statutory Choice Based Credit System offering major specialization, minor electives, multidisciplinary foundation, and vocational skill enhancements.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach (CollegeConfig::COURSE_CATALOG as $code => $course): ?>
                <div class="border border-slate-200 rounded-2xl p-6 bg-slate-50/50 hover:bg-white hover:border-[#800000] hover:shadow-lg transition-all duration-200">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-bold text-[#800000] bg-red-50 border border-red-100 px-3 py-1 rounded-full uppercase">
                            <?= htmlspecialchars($course['facultyType'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="text-xs font-semibold text-slate-500">
                            <?= $course['durationYears'] ?> Yrs (<?= $course['totalSemesters'] ?> Sems)
                        </span>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 mb-2">
                        <?= htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <div class="my-4 p-4 rounded-xl bg-white border border-slate-100 shadow-sm space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Semester 1 Fee:</span>
                            <span class="font-bold text-slate-900">₹<?= number_format($course['totalFee'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Admission Component:</span>
                            <span>₹<?= number_format($course['admissionFee'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Tuition Component:</span>
                            <span>₹<?= number_format($course['tuitionFee'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Exam Component:</span>
                            <span>₹<?= number_format($course['examFee'], 2) ?></span>
                        </div>
                    </div>

                    <a href="/admission?course=<?= urlencode($code) ?>" class="btn-outline w-full text-center text-xs mt-2">
                        Enroll in <?= htmlspecialchars($course['rollPrefix'], ENT_QUOTES, 'UTF-8') ?> &rarr;
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
