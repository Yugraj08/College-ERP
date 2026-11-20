<?php
declare(strict_types=1);

use App\Config\CollegeConfig;

$pageTitle = $title ?? 'Online Admission & Semester Progression | ' . CollegeConfig::INSTITUTION_NAME;
$token = $csrfToken ?? '';
$courseCatalog = $courses ?? CollegeConfig::COURSE_CATALOG;
?>
<script>
function admissionForm() {
    return {
        activeTab: 'fresh',
        faculty: 'Social Science',
        courseCode: 'BA-SS',
        courseCatalog: <?= json_encode($courseCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>,
        facultyCatalog: <?= json_encode($facultySubjects ?? CollegeConfig::FACULTY_SUBJECTS, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>,
        courseFee: 2800,
        feeModalOpen: false,
        paymentMethod: 'ONLINE_GATEWAY',
        utrNumber: '',
        journalNo: '',
        submitting: false,
        applicationResult: null,
        errorMessage: '',

        // Form inputs
        fullName: '',
        fatherName: '',
        motherName: '',
        dateOfBirth: '',
        gender: 'Male',
        category: 'General',
        phone: '',
        email: '',
        address: '',
        mjcSubject: '',
        micSubject: '',
        mdcSubject: '',
        aecSubject: '',
        secSubject: '',
        vacSubject: '',

        // Status Lookup
        searchAppNo: '',
        searchDob: '',
        lookupLoading: false,
        lookupResult: null,
        lookupError: '',

        // Progression
        progIdentifier: '',
        progSemester: 2,
        progPaymentMethod: 'ONLINE_GATEWAY',
        progUtr: '',
        progJournal: '',
        progLoading: false,
        progResult: null,
        progError: '',

        init() {
            this.updateCourse();
        },

        getSubjectList(category) {
            if (!this.facultyCatalog || !this.facultyCatalog[this.faculty]) return [];
            const cat = this.facultyCatalog[this.faculty];
            const keyUpper = category.toUpperCase();
            const keyLower = category.toLowerCase();
            return cat[keyUpper] || cat[keyLower] || [];
        },

        updateCourse() {
            const c = this.courseCatalog[this.courseCode];
            if (c) {
                this.faculty = c.facultyType;
                this.courseFee = c.totalFee;
                const mjcList = this.getSubjectList('mjc');
                const micList = this.getSubjectList('mic');
                const mdcList = this.getSubjectList('mdc');
                const aecList = this.getSubjectList('aec');
                const secList = this.getSubjectList('sec');
                const vacList = this.getSubjectList('vac');

                this.mjcSubject = mjcList[0] || '';
                this.micSubject = micList[0] || '';
                this.mdcSubject = mdcList[0] || '';
                this.aecSubject = aecList[0] || '';
                this.secSubject = secList[0] || '';
                this.vacSubject = vacList[0] || '';
            }
        },

        async submitFreshApplication() {
            this.submitting = true;
            this.errorMessage = '';

            try {
                const payload = {
                    _csrf: <?= json_encode($token) ?>,
                    full_name: this.fullName,
                    father_name: this.fatherName,
                    mother_name: this.motherName,
                    date_of_birth: this.dateOfBirth,
                    gender: this.gender,
                    category: this.category,
                    phone: this.phone,
                    email: this.email,
                    address: this.address,
                    course_code: this.courseCode,
                    mjc_subject: this.mjcSubject,
                    mic_subject: this.micSubject,
                    mdc_subject: this.mdcSubject,
                    aec_subject: this.aecSubject,
                    sec_subject: this.secSubject,
                    vac_subject: this.vacSubject,
                    payment_method: this.paymentMethod,
                    utr_number: this.utrNumber,
                    journal_no: this.journalNo,
                };

                const res = await fetch('/api/admissions/apply', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.applicationResult = data;
                    this.feeModalOpen = false;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    this.errorMessage = data.error || 'Failed to submit application.';
                }
            } catch (e) {
                this.errorMessage = 'Network connection error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        async performStatusLookup() {
            if (!this.searchAppNo.trim()) {
                this.lookupError = 'Please enter your Application or Registration Number.';
                return;
            }

            this.lookupLoading = true;
            this.lookupError = '';
            this.lookupResult = null;

            try {
                const res = await fetch('/api/admissions/status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        application_no: this.searchAppNo.trim(),
                        dob_or_phone: this.searchDob.trim()
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.lookupResult = data;
                } else {
                    this.lookupError = data.error || 'Record not found.';
                }
            } catch (e) {
                this.lookupError = 'Unable to connect to college directory.';
            } finally {
                this.lookupLoading = false;
            }
        },

        async submitProgression() {
            if (!this.progIdentifier.trim()) {
                this.progError = 'Please enter your Registration Code or Roll Number.';
                return;
            }

            this.progLoading = true;
            this.progError = '';
            this.progResult = null;

            try {
                const res = await fetch('/api/admissions/progression', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        identifier: this.progIdentifier.trim(),
                        target_semester: parseInt(this.progSemester),
                        payment_method: this.progPaymentMethod,
                        utr_number: this.progUtr,
                        journal_no: this.progJournal,
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.progResult = data;
                } else {
                    this.progError = data.error || 'Progression registration failed.';
                }
            } catch (e) {
                this.progError = 'Network connection error during progression.';
            } finally {
                this.progLoading = false;
            }
        }
    };
}
</script>
<div x-data="admissionForm()" class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <!-- Header Banner -->
    <div class="bg-[#800000] text-white rounded-2xl p-6 sm:p-8 mb-8 shadow-xl border border-[#D4AF37]/40 relative overflow-hidden">
        <div class="relative z-10">
            <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-[#D4AF37] text-xs font-semibold mb-2 border border-[#D4AF37]/30">
                Academic Session 2026–2030 &bull; BRABU CBCS UG 4-Year System
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Online Admission & Academic Progression</h1>
            <p class="text-sm text-slate-200 mt-2 max-w-2xl leading-relaxed">
                Apply for fresh undergraduate admission with statutory BRABU CBCS 6-tier subject allocation, or advance to subsequent semesters.
            </p>
        </div>
        <div class="absolute -right-8 -bottom-8 w-48 h-48 bg-[#D4AF37]/10 rounded-full blur-2xl pointer-events-none"></div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-slate-200 mb-8 space-x-2 sm:space-x-4">
        <button type="button"
                @click="activeTab = 'fresh'"
                class="py-3 px-4 font-bold text-sm border-b-2 transition-all flex items-center gap-2"
                :class="activeTab === 'fresh' ? 'border-[#800000] text-[#800000]' : 'border-transparent text-slate-500 hover:text-slate-800'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            1. Fresh UG Admission (Sem 1)
        </button>

        <button type="button"
                @click="activeTab = 'progression'"
                class="py-3 px-4 font-bold text-sm border-b-2 transition-all flex items-center gap-2"
                :class="activeTab === 'progression' ? 'border-[#800000] text-[#800000]' : 'border-transparent text-slate-500 hover:text-slate-800'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            2. Semester 2–8 Progression
        </button>

        <button type="button"
                @click="activeTab = 'status'"
                class="py-3 px-4 font-bold text-sm border-b-2 transition-all flex items-center gap-2"
                :class="activeTab === 'status' ? 'border-[#800000] text-[#800000]' : 'border-transparent text-slate-500 hover:text-slate-800'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            3. Check Application Status
        </button>
    </div>

    <!-- TAB 1: FRESH UG ADMISSION FORM -->
    <div x-show="activeTab === 'fresh'" x-cloak>
        <!-- Success Slip Screen (When Application Completed) -->
        <template x-if="applicationResult">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-6 sm:p-10 mb-8 print:p-0 print:border-none print:shadow-none">
                <div class="border-b-2 border-[#800000] pb-6 mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <img src="/assets/logo/mkr-college-emblem.webp" alt="Emblem" class="w-16 h-16 rounded-full border border-[#D4AF37] p-1">
                        <div>
                            <span class="text-xs font-bold text-[#800000] uppercase tracking-wider">Official Provisional Admission Slip</span>
                            <h2 class="text-xl font-extrabold text-slate-900"><?= CollegeConfig::INSTITUTION_NAME ?></h2>
                            <p class="text-xs text-slate-500"><?= CollegeConfig::CAMPUS_LOCATION ?> &bull; <?= CollegeConfig::AFFILIATED_UNIVERSITY ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-800 font-bold rounded-lg text-xs">
                            Status: <span x-text="applicationResult.paymentStatus"></span>
                        </span>
                        <p class="text-xs text-slate-400 mt-1" x-text="'Applied: ' + applicationResult.appliedAt"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 p-6 rounded-xl border border-slate-200 mb-6">
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase">Application Number</span>
                        <span class="text-lg font-mono font-extrabold text-[#800000]" x-text="applicationResult.applicationNo"></span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase">Registration Code</span>
                        <span class="text-lg font-mono font-extrabold text-slate-900" x-text="applicationResult.registrationCode"></span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase">Fee Receipt Number</span>
                        <span class="text-base font-mono font-bold text-slate-800" x-text="applicationResult.receiptNo"></span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase">Program / Course</span>
                        <span class="text-base font-bold text-slate-800" x-text="applicationResult.courseName"></span>
                    </div>
                </div>

                <!-- Enrolled CBCS Subjects Table -->
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-[#800000] uppercase tracking-wider mb-3">Enrolled BRABU CBCS Subjects (Semester 1 - 20 Statutory Credits)</h3>
                    <div class="overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-[#001836] text-white">
                                <tr>
                                    <th class="py-2.5 px-4 font-semibold">Tier</th>
                                    <th class="py-2.5 px-4 font-semibold">Subject Code</th>
                                    <th class="py-2.5 px-4 font-semibold">Subject Title</th>
                                    <th class="py-2.5 px-4 font-semibold text-center">Credits</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <template x-for="s in applicationResult.subjects" :key="s.subject_code">
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-2.5 px-4 font-bold text-[#800000]" x-text="s.category"></td>
                                        <td class="py-2.5 px-4 font-mono font-medium text-slate-700" x-text="s.subject_code"></td>
                                        <td class="py-2.5 px-4 text-slate-900 font-semibold" x-text="s.subject_name"></td>
                                        <td class="py-2.5 px-4 font-bold text-slate-800 text-center" x-text="s.credits"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-200 print:hidden">
                    <button type="button" @click="window.print()" class="btn-primary w-full sm:w-auto">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print Application Slip
                    </button>
                    <button type="button" @click="applicationResult = null" class="text-xs text-slate-600 hover:text-slate-900 underline">
                        Submit Another Application
                    </button>
                </div>
            </div>
        </template>

        <!-- Fresh Admission Input Form -->
        <div x-show="!applicationResult">
            <!-- Error Banner -->
            <div x-show="errorMessage" class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center justify-between">
                <span x-text="errorMessage"></span>
                <button type="button" @click="errorMessage = ''" class="font-bold">&times;</button>
            </div>

            <form @submit.prevent="feeModalOpen = true" class="space-y-8">
                <!-- Section 1: Academic Course Selection -->
                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-base font-extrabold text-[#800000] uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-[#800000] text-white flex items-center justify-center text-xs">1</span>
                        Course & Faculty Selection
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Select Undergraduate Program *</label>
                            <select x-model="courseCode" @change="updateCourse()" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl font-semibold text-slate-800 focus:ring-2 focus:ring-[#800000]">
                                <template x-for="(c, code) in courseCatalog" :key="code">
                                    <option :value="code" x-text="c.name + ' (' + c.code + ')'"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Faculty Stream</label>
                            <input type="text" :value="faculty" disabled class="w-full p-3 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 font-bold">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Personal Information -->
                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-base font-extrabold text-[#800000] uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-[#800000] text-white flex items-center justify-center text-xs">2</span>
                        Student & Guardian Information
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Full Name (as per 10th Certificate) *</label>
                            <input type="text" x-model="fullName" required placeholder="e.g. Aarav Kumar" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Father's Name *</label>
                            <input type="text" x-model="fatherName" required placeholder="e.g. Ramesh Kumar" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Mother's Name *</label>
                            <input type="text" x-model="motherName" required placeholder="e.g. Sunita Devi" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Date of Birth *</label>
                            <input type="date" x-model="dateOfBirth" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Gender *</label>
                            <select x-model="gender" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Transgender">Transgender</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Social Category *</label>
                            <select x-model="category" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                                <option value="General">General (UR)</option>
                                <option value="OBC">OBC / BC</option>
                                <option value="EBC">EBC</option>
                                <option value="SC">SC</option>
                                <option value="ST">ST</option>
                                <option value="EWS">EWS</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Mobile Phone (10 digits) *</label>
                            <input type="tel" x-model="phone" required placeholder="e.g. 9876543210" pattern="[6-9][0-9]{9}" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Email Address *</label>
                            <input type="email" x-model="email" required placeholder="e.g. student@gmail.com" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Residential Address *</label>
                            <input type="text" x-model="address" required placeholder="Village, Post, Dist, PIN" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#800000]">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Statutory 6-Tier BRABU CBCS Subject Picker -->
                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-extrabold text-[#800000] uppercase tracking-wider flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-[#800000] text-white flex items-center justify-center text-xs">3</span>
                            BRABU CBCS 6-Tier Subject Selection
                        </h2>
                        <span class="text-xs bg-[#D4AF37]/20 text-[#800000] font-bold px-3 py-1 rounded-full border border-[#D4AF37]/40">
                            Statutory Semester 1: 20 Credits
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Major Core (MJC) -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-bold text-slate-800">1. Major Course (MJC) *</label>
                                <span class="text-[10px] font-bold text-white bg-[#800000] px-2 py-0.5 rounded">6 Credits</span>
                            </div>
                            <select x-model="mjcSubject" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#800000]">
                                <option value="">-- Select Major Subject (MJC) --</option>
                                <template x-for="s in getSubjectList('MJC')" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Minor Core (MIC) -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-bold text-slate-800">2. Minor Course (MIC) *</label>
                                <span class="text-[10px] font-bold text-white bg-[#002B5C] px-2 py-0.5 rounded">4 Credits</span>
                            </div>
                            <select x-model="micSubject" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#800000]">
                                <option value="">-- Select Minor Subject (MIC) --</option>
                                <template x-for="s in getSubjectList('MIC')" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Multidisciplinary (MDC) -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-bold text-slate-800">3. Multidisciplinary (MDC) *</label>
                                <span class="text-[10px] font-bold text-white bg-[#002B5C] px-2 py-0.5 rounded">4 Credits</span>
                            </div>
                            <select x-model="mdcSubject" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#800000]">
                                <option value="">-- Select Multidisciplinary (MDC) --</option>
                                <template x-for="s in getSubjectList('MDC')" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Ability Enhancement (AEC) -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-bold text-slate-800">4. Ability Enhancement (AEC) *</label>
                                <span class="text-[10px] font-bold text-slate-700 bg-slate-200 px-2 py-0.5 rounded">2 Credits</span>
                            </div>
                            <select x-model="aecSubject" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#800000]">
                                <option value="">-- Select Ability Enhancement (AEC) --</option>
                                <template x-for="s in getSubjectList('AEC')" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Skill Enhancement (SEC) -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-bold text-slate-800">5. Skill Enhancement (SEC) *</label>
                                <span class="text-[10px] font-bold text-slate-700 bg-slate-200 px-2 py-0.5 rounded">2 Credits</span>
                            </div>
                            <select x-model="secSubject" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#800000]">
                                <option value="">-- Select Skill Enhancement (SEC) --</option>
                                <template x-for="s in getSubjectList('SEC')" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Value Added Course (VAC) -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-bold text-slate-800">6. Value Added Course (VAC) *</label>
                                <span class="text-[10px] font-bold text-slate-700 bg-slate-200 px-2 py-0.5 rounded">2 Credits</span>
                            </div>
                            <select x-model="vacSubject" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#800000]">
                                <option value="">-- Select Value Added Course (VAC) --</option>
                                <template x-for="s in getSubjectList('VAC')" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Submit Button / Review Fee -->
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary py-3 px-8 text-sm font-bold shadow-lg">
                        Review Fee & Proceed to Payment &rarr;
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 2: SEMESTER 2-8 PROGRESSION -->
    <div x-show="activeTab === 'progression'" x-cloak class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
        <h2 class="text-base font-extrabold text-[#800000] uppercase tracking-wider mb-2">Semester Progression & Registration</h2>
        <p class="text-xs text-slate-500 mb-6">Enter your College Roll Number or Registration Code to advance to the subsequent semester.</p>

        <div x-show="progError" class="mb-4 p-3 bg-red-50 text-red-700 border border-red-200 rounded-xl text-xs font-semibold" x-text="progError"></div>

        <template x-if="progResult">
            <div class="p-6 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 mb-6">
                <h3 class="font-bold text-base mb-1" x-text="progResult.message"></h3>
                <p class="text-xs">Receipt Number: <strong class="font-mono text-emerald-800" x-text="progResult.receiptNo"></strong></p>
                <p class="text-xs">Student: <strong x-text="progResult.student.fullName + ' (' + progResult.student.collegeRollNo + ')'"></strong></p>
                <p class="text-xs">Registered Semester: <strong x-text="'Semester ' + progResult.targetSemester"></strong></p>
            </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Registration Code or Roll No *</label>
                <input type="text" x-model="progIdentifier" placeholder="e.g. 26/BASS/0001 or MKR-REG-..." class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Target Progression Semester *</label>
                <select x-model.number="progSemester" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                    <option value="2">Semester 2 (₹2,800 / ₹3,200)</option>
                    <option value="3">Semester 3 (₹2,800 / ₹3,200)</option>
                    <option value="4">Semester 4 (₹2,800 / ₹3,200)</option>
                    <option value="5">Semester 5 (₹2,800 / ₹3,200)</option>
                    <option value="6">Semester 6 (₹2,800 / ₹3,200)</option>
                    <option value="7">Semester 7 (₹2,800 / ₹3,200)</option>
                    <option value="8">Semester 8 (₹2,800 / ₹3,200)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Payment Method *</label>
                <select x-model="progPaymentMethod" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                    <option value="ONLINE_GATEWAY">Instant Online Gateway (Simulator / Razorpay)</option>
                    <option value="UPI">Direct UPI Transfer (12-Digit UTR)</option>
                    <option value="BANK_CHALLAN">Bank Challan (SBI Punaura)</option>
                </select>
            </div>
        </div>

        <div x-show="progPaymentMethod === 'UPI'" class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-xl">
            <label class="block text-xs font-bold text-slate-700 mb-1">Enter 12-digit UPI UTR Reference Number</label>
            <input type="text" x-model="progUtr" placeholder="e.g. 423589123456" class="w-full sm:w-1/2 p-2.5 bg-white border border-slate-300 rounded-lg text-sm font-mono">
        </div>

        <button type="button" @click="submitProgression()" :disabled="progLoading" class="btn-primary">
            <span x-text="progLoading ? 'Processing Progression...' : 'Confirm Semester Promotion & Clear Fee'"></span>
        </button>
    </div>

    <!-- TAB 3: CHECK APPLICATION STATUS -->
    <div x-show="activeTab === 'status'" x-cloak class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
        <h2 class="text-base font-extrabold text-[#800000] uppercase tracking-wider mb-2">Application Tracking & Fee Ledger</h2>
        <p class="text-xs text-slate-500 mb-6">Track your admission scrutiny status, roll assignment, and view itemized fee receipts.</p>

        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <input type="text" x-model="searchAppNo" placeholder="Application No (e.g. MKR-2026-00001)" class="flex-1 p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            <input type="text" x-model="searchDob" placeholder="DOB (YYYY-MM-DD) or Mobile" class="sm:w-64 p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            <button type="button" @click="performStatusLookup()" :disabled="lookupLoading" class="btn-navy py-3 px-6 text-sm font-bold">
                <span x-text="lookupLoading ? 'Searching...' : 'Check Status'"></span>
            </button>
        </div>

        <div x-show="lookupError" class="p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl text-xs font-semibold mb-6" x-text="lookupError"></div>

        <template x-if="lookupResult">
            <div class="space-y-6">
                <!-- Profile Status Card -->
                <div class="p-6 bg-slate-50 border border-slate-200 rounded-xl grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <span class="block text-xs text-slate-500 uppercase">Student Name</span>
                        <strong class="text-sm text-slate-900" x-text="lookupResult.student.fullName"></strong>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 uppercase">Current Status</span>
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold"
                              :class="lookupResult.student.status === 'ADMITTED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                              x-text="lookupResult.student.status"></span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 uppercase">College Roll Number</span>
                        <strong class="text-sm font-mono text-[#800000]" x-text="lookupResult.student.collegeRollNo || 'Pending Scrutiny'"></strong>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 uppercase">Course</span>
                        <strong class="text-sm text-slate-800" x-text="lookupResult.student.courseName"></strong>
                    </div>
                </div>

                <!-- Subjects -->
                <div>
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Registered CBCS Subjects</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2">
                        <template x-for="s in lookupResult.subjects" :key="s.subject_code">
                            <div class="p-3 bg-white border border-slate-200 rounded-lg text-center shadow-sm">
                                <span class="block text-[10px] font-bold text-[#800000]" x-text="s.category"></span>
                                <span class="block text-xs font-bold text-slate-900 truncate" x-text="s.subject_name"></span>
                                <span class="text-[10px] text-slate-500" x-text="s.credits + ' Credits'"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Fee Receipts -->
                <div>
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Fee Receipts</h3>
                    <div class="overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-slate-700 font-bold">
                                <tr>
                                    <th class="py-2.5 px-4">Receipt No</th>
                                    <th class="py-2.5 px-4">Semester</th>
                                    <th class="py-2.5 px-4">Amount</th>
                                    <th class="py-2.5 px-4">Method</th>
                                    <th class="py-2.5 px-4">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <template x-for="p in lookupResult.payments" :key="p.receipt_no">
                                    <tr>
                                        <td class="py-2.5 px-4 font-mono font-bold text-[#800000]" x-text="p.receipt_no"></td>
                                        <td class="py-2.5 px-4" x-text="'Semester ' + p.target_semester"></td>
                                        <td class="py-2.5 px-4 font-bold" x-text="'₹' + parseFloat(p.amount).toFixed(2)"></td>
                                        <td class="py-2.5 px-4" x-text="p.payment_method"></td>
                                        <td class="py-2.5 px-4">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                  :class="p.status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                                  x-text="p.status"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- DYNAMIC FEE REVIEW & PAYMENT MODAL -->
    <div x-show="feeModalOpen"
         x-cloak
         class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50"
         @keydown.escape.window="feeModalOpen = false">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200"
             @click.away="feeModalOpen = false">

            <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
                <div>
                    <span class="text-xs font-bold text-[#800000] uppercase tracking-wider">Fee Summary & Checkout</span>
                    <h3 class="text-lg font-bold text-slate-900" x-text="courseCatalog[courseCode]?.name"></h3>
                </div>
                <button type="button" @click="feeModalOpen = false" class="text-slate-400 hover:text-slate-700 text-lg">&times;</button>
            </div>

            <!-- Fee Breakdown -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600">
                    <span>Tuition Fee (Semester 1):</span>
                    <span class="font-semibold" x-text="'₹' + (courseCode === 'BCOM' ? '2,200.00' : '1,800.00')"></span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Admission Fee:</span>
                    <span class="font-semibold">₹500.00</span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Examination Fee:</span>
                    <span class="font-semibold">₹500.00</span>
                </div>
                <div class="pt-2 border-t border-slate-200 flex justify-between text-sm font-bold text-slate-900">
                    <span>Total Amount Payable:</span>
                    <span class="text-[#800000]" x-text="'₹' + courseFee.toFixed(2)"></span>
                </div>
            </div>

            <!-- Payment Driver Selection -->
            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Select Payment Method *</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                           :class="paymentMethod === 'ONLINE_GATEWAY' ? 'border-[#800000] bg-red-50/50' : 'border-slate-200 hover:bg-slate-50'">
                        <input type="radio" name="pay_method" value="ONLINE_GATEWAY" x-model="paymentMethod" class="text-[#800000] focus:ring-[#800000]">
                        <div>
                            <span class="block text-xs font-bold text-slate-800">Instant Online Gateway</span>
                            <span class="block text-[11px] text-slate-500">Automated instant verification (Seamless Simulator / Razorpay)</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                           :class="paymentMethod === 'UPI' ? 'border-[#800000] bg-red-50/50' : 'border-slate-200 hover:bg-slate-50'">
                        <input type="radio" name="pay_method" value="UPI" x-model="paymentMethod" class="text-[#800000] focus:ring-[#800000]">
                        <div>
                            <span class="block text-xs font-bold text-slate-800">Direct UPI Transfer</span>
                            <span class="block text-[11px] text-slate-500">Transfer to <code class="font-bold text-[#800000]">mkrcollege@sbi</code> and enter 12-digit UTR</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                           :class="paymentMethod === 'BANK_CHALLAN' ? 'border-[#800000] bg-red-50/50' : 'border-slate-200 hover:bg-slate-50'">
                        <input type="radio" name="pay_method" value="BANK_CHALLAN" x-model="paymentMethod" class="text-[#800000] focus:ring-[#800000]">
                        <div>
                            <span class="block text-xs font-bold text-slate-800">Offline Bank Challan</span>
                            <span class="block text-[11px] text-slate-500">Generate bank challan for SBI Punaura Dham Branch</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Conditional Payment Field -->
            <div x-show="paymentMethod === 'UPI'" class="mb-6">
                <label class="block text-xs font-bold text-slate-700 mb-1">Enter 12-digit UPI UTR Number *</label>
                <input type="text" x-model="utrNumber" placeholder="e.g. 423589123456" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm font-mono">
            </div>

            <div x-show="paymentMethod === 'BANK_CHALLAN'" class="mb-6">
                <label class="block text-xs font-bold text-slate-700 mb-1">Bank Deposit Scroll / Journal No *</label>
                <input type="text" x-model="journalNo" placeholder="e.g. SBI-PUN-098234" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm font-mono">
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                <button type="button" @click="feeModalOpen = false" class="py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 border border-slate-300">
                    Cancel
                </button>
                <button type="button" @click="submitFreshApplication()" :disabled="submitting" class="btn-primary py-2.5 px-6 text-xs font-bold">
                    <span x-text="submitting ? 'Processing Payment...' : 'Confirm & Complete Admission'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
