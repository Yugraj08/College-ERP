<?php
declare(strict_types=1);

use App\Config\CollegeConfig;

$pageTitle = $title ?? 'Grievance Redressal & Student Helpdesk | ' . CollegeConfig::INSTITUTION_NAME;
$token = $csrfToken ?? '';
$cats = $categories ?? [
    'Academic & Curriculum',
    'Examination & Results',
    'Fee, Accounts & Scholarship',
    'CBCS Subject Allocation',
    'Library & Laboratory Facilities',
    'Administrative & Office Services',
    'Anti-Ragging / Student Welfare',
    'General Institutional Inquiry',
];
?>
<div x-data="{
    activeTab: 'lodge',
    submitting: false,
    errorMessage: '',
    successResult: null,

    // Form inputs
    submittedBy: 'STUDENT',
    name: '',
    email: '',
    phone: '',
    studentId: '',
    category: 'Academic & Curriculum',
    subject: '',
    description: '',

    // Tracking
    trackTicketNo: '',
    trackingLoading: false,
    trackResult: null,
    trackError: '',

    async submitGrievance() {
        this.submitting = true;
        this.errorMessage = '';

        try {
            const payload = {
                _csrf: '<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>',
                submitted_by: this.submittedBy,
                name: this.name,
                email: this.email,
                phone: this.phone,
                student_identifier: this.studentId,
                category: this.category,
                subject: this.subject,
                description: this.description,
            };

            const res = await fetch('/api/grievances/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': '<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (res.ok && data.success) {
                this.successResult = data;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                this.errorMessage = data.error || 'Failed to submit grievance.';
            }
        } catch (e) {
            this.errorMessage = 'Network connection error. Please try again.';
        } finally {
            this.submitting = false;
        }
    },

    async trackGrievance() {
        if (!this.trackTicketNo.trim()) {
            this.trackError = 'Please provide your Grievance Ticket Number.';
            return;
        }

        this.trackingLoading = true;
        this.trackError = '';
        this.trackResult = null;

        try {
            const res = await fetch('/api/grievances/track', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ ticket_no: this.trackTicketNo.trim() })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                this.trackResult = data.ticket;
            } else {
                this.trackError = data.error || 'Ticket not found in college records.';
            }
        } catch (e) {
            this.trackError = 'Connection error. Please try again.';
        } finally {
            this.trackingLoading = false;
        }
    }
}" class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <!-- Header Banner -->
    <div class="bg-[#002B5C] text-white rounded-2xl p-6 sm:p-8 mb-8 shadow-xl border border-[#D4AF37]/40 relative overflow-hidden">
        <div class="relative z-10">
            <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-[#D4AF37] text-xs font-semibold mb-2 border border-[#D4AF37]/30">
                Institutional Grievance Redressal Cell &bull; Statutory UGC & BRABU Regulations
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Student & Public Grievance Helpdesk</h1>
            <p class="text-sm text-slate-200 mt-2 max-w-2xl leading-relaxed">
                Submit complaints, inquiries, or feedback directly to the Principal's Secretariat. Every ticket receives an immutable tracking code and formal administrative review.
            </p>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-slate-200 mb-8 space-x-4">
        <button type="button"
                @click="activeTab = 'lodge'"
                class="py-3 px-4 font-bold text-sm border-b-2 transition-all flex items-center gap-2"
                :class="activeTab === 'lodge' ? 'border-[#800000] text-[#800000]' : 'border-transparent text-slate-500 hover:text-slate-800'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            1. Lodge New Grievance
        </button>

        <button type="button"
                @click="activeTab = 'track'"
                class="py-3 px-4 font-bold text-sm border-b-2 transition-all flex items-center gap-2"
                :class="activeTab === 'track' ? 'border-[#800000] text-[#800000]' : 'border-transparent text-slate-500 hover:text-slate-800'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            2. Track Grievance Status
        </button>
    </div>

    <!-- TAB 1: LODGE GRIEVANCE -->
    <div x-show="activeTab === 'lodge'" x-cloak>
        <!-- Success Card -->
        <template x-if="successResult">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 mb-8 text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Ticket Registered Successfully</span>
                <h2 class="text-2xl font-extrabold text-slate-900 mt-1 mb-2">Tracking Code: <span class="text-[#800000] font-mono" x-text="successResult.ticketNo"></span></h2>
                <p class="text-sm text-slate-600 max-w-lg mx-auto mb-6">
                    Your grievance has been lodged in the institutional registry. Please save your ticket number to track responses and administrative resolutions.
                </p>
                <div class="flex justify-center gap-4">
                    <button type="button" @click="activeTab = 'track'; trackTicketNo = successResult.ticketNo; trackGrievance()" class="btn-primary">
                        Track Ticket Now &rarr;
                    </button>
                    <button type="button" @click="successResult = null; subject = ''; description = ''" class="btn-cancel">
                        Submit Another
                    </button>
                </div>
            </div>
        </template>

        <!-- Form -->
        <div x-show="!successResult" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <div x-show="errorMessage" class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl text-xs font-bold" x-text="errorMessage"></div>

            <form @submit.prevent="submitGrievance()" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">I am a *</label>
                        <select x-model="submittedBy" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                            <option value="STUDENT">Enrolled Student</option>
                            <option value="PARENT">Parent / Guardian</option>
                            <option value="PUBLIC">Alumnus / General Public</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Grievance Category *</label>
                        <select x-model="category" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                            <?php foreach ($cats as $c): ?>
                                <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Full Name *</label>
                        <input type="text" x-model="name" required placeholder="Your full name" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Email Address *</label>
                        <input type="email" x-model="email" required placeholder="contact@example.com" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Mobile Phone Number *</label>
                        <input type="tel" x-model="phone" required placeholder="10-digit mobile" pattern="[6-9][0-9]{9}" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Student Roll / Application No (Optional)</label>
                        <input type="text" x-model="studentId" placeholder="e.g. 26/BASS/0001 or MKR-2026-..." class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Subject / Headline *</label>
                    <input type="text" x-model="subject" required placeholder="Brief summary of your grievance or issue" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Detailed Description of the Grievance *</label>
                    <textarea x-model="description" required rows="5" placeholder="Provide complete facts, relevant dates, semester, and specific assistance requested..." class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm"></textarea>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-200">
                    <button type="submit" :disabled="submitting" class="btn-primary py-3 px-8 text-sm font-bold shadow-lg">
                        <span x-text="submitting ? 'Registering Ticket...' : 'Lodge Grievance Ticket &rarr;'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 2: TRACK GRIEVANCE -->
    <div x-show="activeTab === 'track'" x-cloak class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
        <h2 class="text-base font-extrabold text-[#800000] uppercase tracking-wider mb-2">Live Grievance Tracker</h2>
        <p class="text-xs text-slate-500 mb-6">Enter your Ticket Number to see latest scrutiny status and official remarks.</p>

        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <input type="text" x-model="trackTicketNo" placeholder="Ticket Number (e.g. TKT-2026-00001)" class="flex-1 p-3 bg-slate-50 border border-slate-300 rounded-xl font-mono text-sm uppercase">
            <button type="button" @click="trackGrievance()" :disabled="trackingLoading" class="btn-navy py-3 px-6 text-sm font-bold">
                <span x-text="trackingLoading ? 'Searching...' : 'Track Ticket'"></span>
            </button>
        </div>

        <div x-show="trackError" class="p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl text-xs font-semibold mb-6" x-text="trackError"></div>

        <template x-if="trackResult">
            <div class="p-6 bg-slate-50 border border-slate-200 rounded-xl space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-200 pb-4">
                    <div>
                        <span class="text-xs text-slate-500 uppercase">Ticket Reference</span>
                        <h3 class="text-lg font-mono font-bold text-[#800000]" x-text="trackResult.ticketNo"></h3>
                    </div>
                    <div>
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold"
                              :class="{
                                  'bg-amber-100 text-amber-800': trackResult.status === 'PENDING',
                                  'bg-blue-100 text-blue-800': trackResult.status === 'IN_REVIEW',
                                  'bg-emerald-100 text-emerald-800': trackResult.status === 'RESOLVED',
                                  'bg-red-100 text-red-800': trackResult.status === 'REJECTED'
                              }"
                              x-text="'Status: ' + trackResult.status"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-slate-500">Applicant:</span>
                        <strong class="text-slate-800 ml-1" x-text="trackResult.name"></strong>
                    </div>
                    <div>
                        <span class="text-slate-500">Category:</span>
                        <strong class="text-slate-800 ml-1" x-text="trackResult.category"></strong>
                    </div>
                    <div>
                        <span class="text-slate-500">Submitted At:</span>
                        <span class="text-slate-700 ml-1" x-text="trackResult.submittedAt"></span>
                    </div>
                    <div x-show="trackResult.resolvedAt">
                        <span class="text-slate-500">Resolved At:</span>
                        <span class="text-slate-700 ml-1" x-text="trackResult.resolvedAt"></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-200">
                    <span class="block text-xs font-bold text-slate-700 mb-1">Subject</span>
                    <p class="text-xs text-slate-900 font-semibold" x-text="trackResult.subject"></p>
                </div>

                <div>
                    <span class="block text-xs font-bold text-slate-700 mb-1">Description</span>
                    <p class="text-xs text-slate-600 leading-relaxed bg-white p-3 rounded-lg border border-slate-200" x-text="trackResult.description"></p>
                </div>

                <div x-show="trackResult.adminRemarks" class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <span class="block text-xs font-bold text-emerald-900 mb-1">Principal / Administrator Action Remarks:</span>
                    <p class="text-xs text-emerald-800" x-text="trackResult.adminRemarks"></p>
                </div>
            </div>
        </template>
    </div>
</div>
