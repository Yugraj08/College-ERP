<?php
declare(strict_types=1);

use App\Config\CollegeConfig;

$pageTitle = 'Principal Control Suite | ' . CollegeConfig::INSTITUTION_NAME;
$token = $csrfToken ?? '';
?>
<script>
function principalConsole() {
    return {
        activeTab: 'scrutiny',
        loading: false,
        toastMessage: '',
        toastError: '',

        // Scrutiny Queue
        pendingList: <?= json_encode($pendingAdmissions ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>,
        scrutinyModal: false,
        selectedApplicant: null,
        scrutinyAction: 'APPROVE',
        scrutinyRemarks: '',

        // Registration Windows
        windowsList: <?= json_encode($windows ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>,
        winCourseId: <?= json_encode(!empty($courses[0]['id']) ? (string)$courses[0]['id'] : '') ?>,
        winSemester: 1,
        winSessionId: <?= json_encode(!empty($sessions[0]['id']) ? (string)$sessions[0]['id'] : '') ?>,
        winStartDate: <?= json_encode(date('Y-m-d')) ?>,
        winEndDate: <?= json_encode(date('Y-m-d', strtotime('+30 days'))) ?>,
        winGraceDays: 30,
        winIsActive: 1,

        // Faculty Management
        facultyList: <?= json_encode($facultyList ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>,
        facModalOpen: false,
        facName: '',
        facEmail: '',
        facPhone: '',
        facDeptId: <?= json_encode(!empty($departments[0]['id']) ? (string)$departments[0]['id'] : '') ?>,
        facDesignation: 'Assistant Professor',
        facPassword: '',
        pwdModalOpen: false,
        pwdFacultyId: '',
        pwdNewValue: '',

        // Notice Broadcaster
        noticeList: <?= json_encode($notices ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>,
        noticeTitle: '',
        noticeCategory: 'General',
        noticeContent: '',

        // Grievances
        grievanceList: <?= json_encode($grievances ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>,
        grvModalOpen: false,
        selectedTicket: null,
        grvStatus: 'RESOLVED',
        grvRemarks: '',

        showToast(msg, isErr = false) {
            if (isErr) {
                this.toastError = msg;
                this.toastMessage = '';
            } else {
                this.toastMessage = msg;
                this.toastError = '';
            }
            setTimeout(() => { this.toastMessage = ''; this.toastError = ''; }, 5000);
        },

        openScrutiny(app, action) {
            this.selectedApplicant = app;
            this.scrutinyAction = action;
            this.scrutinyRemarks = action === 'APPROVE' ? 'Academic eligibility verified according to BRABU CBCS norms.' : 'Discrepancy in submitted documents or incomplete verification.';
            this.scrutinyModal = true;
        },

        async confirmScrutiny() {
            if (!this.selectedApplicant) return;
            this.loading = true;

            try {
                const res = await fetch('/api/admin/admissions/scrutinize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        student_id: this.selectedApplicant.id,
                        action: this.scrutinyAction,
                        remarks: this.scrutinyRemarks
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.pendingList = this.pendingList.filter(s => s.id !== this.selectedApplicant.id);
                    this.scrutinyModal = false;
                    this.showToast(data.message);
                } else {
                    this.showToast(data.error || 'Scrutiny update failed', true);
                }
            } catch (e) {
                this.showToast('Network error during scrutiny update', true);
            } finally {
                this.loading = false;
            }
        },

        async submitWindow() {
            this.loading = true;
            try {
                const res = await fetch('/api/admin/windows/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        course_id: this.winCourseId,
                        semester: this.winSemester,
                        academic_session_id: this.winSessionId,
                        start_date: this.winStartDate,
                        end_date: this.winEndDate,
                        grace_days: this.winGraceDays,
                        is_active: this.winIsActive
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showToast(data.error || 'Failed to save window', true);
                }
            } catch (e) {
                this.showToast('Network error saving registration window', true);
            } finally {
                this.loading = false;
            }
        },

        async submitFaculty() {
            this.loading = true;
            try {
                const res = await fetch('/api/admin/faculty/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        full_name: this.facName,
                        email: this.facEmail,
                        phone: this.facPhone,
                        department_id: this.facDeptId,
                        designation: this.facDesignation,
                        password: this.facPassword
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.facModalOpen = false;
                    this.facName = '';
                    this.facEmail = '';
                    this.facPhone = '';
                    this.facPassword = '';
                    this.showToast(data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showToast(data.error || 'Failed to create faculty member', true);
                }
            } catch (e) {
                this.showToast('Network error creating faculty', true);
            } finally {
                this.loading = false;
            }
        },

        async toggleFaculty(fac) {
            try {
                const res = await fetch('/api/admin/faculty/toggle-status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        faculty_id: fac.id
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    fac.is_active = data.isActive ? 1 : 0;
                    this.showToast(data.message);
                } else {
                    this.showToast(data.error || 'Failed to toggle status', true);
                }
            } catch (e) {
                this.showToast('Network error toggling faculty status', true);
            }
        },

        openResetPwd(fac) {
            this.pwdFacultyId = fac.id;
            this.pwdNewValue = '';
            this.pwdModalOpen = true;
        },

        async submitResetPwd() {
            if (!this.pwdNewValue || this.pwdNewValue.length < 6) {
                this.showToast('Password must be at least 6 characters', true);
                return;
            }
            try {
                const res = await fetch('/api/admin/faculty/reset-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        faculty_id: this.pwdFacultyId,
                        new_password: this.pwdNewValue
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.pwdModalOpen = false;
                    this.showToast(data.message);
                } else {
                    this.showToast(data.error || 'Failed to reset password', true);
                }
            } catch (e) {
                this.showToast('Network error resetting password', true);
            }
        },

        async submitNotice() {
            if (!this.noticeTitle.trim() || !this.noticeContent.trim()) {
                this.showToast('Please provide both notice title and announcement content.', true);
                return;
            }
            this.loading = true;
            try {
                const res = await fetch('/api/admin/notices/broadcast', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        title: this.noticeTitle,
                        category: this.noticeCategory,
                        content: this.noticeContent
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.noticeTitle = '';
                    this.noticeContent = '';
                    this.showToast(data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showToast(data.error || 'Notice broadcast failed', true);
                }
            } catch (e) {
                this.showToast('Network error broadcasting notice', true);
            } finally {
                this.loading = false;
            }
        },

        async deleteNotice(notice) {
            if (!confirm('Are you sure you want to remove announcement: "' + notice.title + '"? It will be removed immediately from the public site and notice ticker.')) {
                return;
            }
            this.loading = true;
            try {
                const res = await fetch('/api/admin/notices/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        notice_id: notice.id
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.noticeList = this.noticeList.filter(n => n.id !== notice.id);
                    this.showToast(data.message);
                } else {
                    this.showToast(data.error || 'Failed to remove notice', true);
                }
            } catch (e) {
                this.showToast('Network error removing notice', true);
            } finally {
                this.loading = false;
            }
        },

        openGrievance(ticket) {
            this.selectedTicket = ticket;
            this.grvStatus = 'RESOLVED';
            this.grvRemarks = '';
            this.grvModalOpen = true;
        },

        async submitGrievanceResolution() {
            if (!this.selectedTicket) return;
            this.loading = true;
            try {
                const res = await fetch('/api/admin/grievances/resolve', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        ticket_no: this.selectedTicket.ticket_no,
                        status: this.grvStatus,
                        admin_remarks: this.grvRemarks
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.grievanceList = this.grievanceList.filter(t => t.ticket_no !== this.selectedTicket.ticket_no);
                    this.grvModalOpen = false;
                    this.showToast(data.message);
                } else {
                    this.showToast(data.error || 'Failed to update grievance', true);
                }
            } catch (e) {
                this.showToast('Network error resolving grievance', true);
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
<div x-data="principalConsole()" class="space-y-6">

    <!-- Toast Notifications -->
    <div x-show="toastMessage" x-cloak class="p-4 bg-emerald-500 text-white rounded-xl shadow-lg text-sm font-semibold flex items-center justify-between">
        <span x-text="toastMessage"></span>
        <button @click="toastMessage = ''" class="ml-4 text-white hover:text-slate-200">&times;</button>
    </div>
    <div x-show="toastError" x-cloak class="p-4 bg-red-600 text-white rounded-xl shadow-lg text-sm font-semibold flex items-center justify-between">
        <span x-text="toastError"></span>
        <button @click="toastError = ''" class="ml-4 text-white hover:text-slate-200">&times;</button>
    </div>

    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-[#800000] to-[#5C0000] text-white rounded-2xl p-6 sm:p-8 shadow-md border border-[#D4AF37]/30 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-[#D4AF37] text-xs font-semibold mb-2 border border-[#D4AF37]/30">
                Principal Secretariat &bull; Administrative Console
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Institutional Governance & Control Suite</h1>
            <p class="text-xs sm:text-sm text-slate-200 mt-1 max-w-2xl leading-relaxed">
                Live oversight of CBCS admissions scrutiny, registration limits, faculty management, financial collections, and student grievances.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/admin/students" class="btn-navy py-2.5 px-4 text-xs font-bold shadow-md">
                View Student Directory &rarr;
            </a>
        </div>
    </div>

    <!-- Live Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Pending Scrutiny</span>
            <span class="text-2xl font-extrabold text-[#800000]" x-text="pendingList.length"></span>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Admitted Students</span>
            <span class="text-2xl font-extrabold text-emerald-700"><?= (int)($stats['admittedStudents'] ?? 0) ?></span>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Active Faculty</span>
            <span class="text-2xl font-extrabold text-slate-800"><?= (int)($stats['activeFaculty'] ?? 0) ?></span>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Today's Fee</span>
            <span class="text-xl font-extrabold text-emerald-800">₹<?= number_format((float)($stats['todayCollection'] ?? 0), 2) ?></span>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Collection</span>
            <span class="text-xl font-extrabold text-slate-900">₹<?= number_format((float)($stats['totalCollection'] ?? 0), 2) ?></span>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Open Tickets</span>
            <span class="text-2xl font-extrabold text-amber-600" x-text="grievanceList.length"></span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        <button type="button" @click="activeTab = 'scrutiny'"
                :class="activeTab === 'scrutiny' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm">
            <span>Admissions Scrutiny</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white/20" x-text="pendingList.length"></span>
        </button>
        <button type="button" @click="activeTab = 'windows'"
                :class="activeTab === 'windows' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            Registration Windows
        </button>
        <button type="button" @click="activeTab = 'faculty'"
                :class="activeTab === 'faculty' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            Faculty Directory
        </button>
        <button type="button" @click="activeTab = 'notices'"
                :class="activeTab === 'notices' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            Notice Broadcast
        </button>
        <button type="button" @click="activeTab = 'grievances'"
                :class="activeTab === 'grievances' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm">
            <span>Student Helpdesk</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white/20" x-text="grievanceList.length"></span>
        </button>
    </div>

    <!-- TAB 1: ADMISSIONS SCRUTINY QUEUE -->
    <div x-show="activeTab === 'scrutiny'" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Admission Applications Scrutiny Queue</h2>
                <p class="text-xs text-slate-500">Review fresh applicants, verify CBCS subject eligibility, and approve to generate stream roll numbers.</p>
            </div>
            <span class="text-xs font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-lg" x-text="pendingList.length + ' Pending Applications'"></span>
        </div>

        <template x-if="pendingList.length === 0">
            <div class="p-12 text-center text-slate-500">
                <svg class="w-12 h-12 mx-auto text-emerald-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-base font-bold text-slate-800">Scrutiny Queue Clear</h3>
                <p class="text-xs text-slate-500 mt-1">All incoming admission applications have been processed and enrolled.</p>
            </div>
        </template>

        <template x-if="pendingList.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Application No</th>
                            <th class="py-3 px-4">Applicant</th>
                            <th class="py-3 px-4">Course</th>
                            <th class="py-3 px-4">Contact</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Applied At</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="app in pendingList" :key="app.id">
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 font-mono font-bold text-[#800000]" x-text="app.application_no"></td>
                                <td class="py-3.5 px-4 font-semibold text-slate-900" x-text="app.full_name"></td>
                                <td class="py-3.5 px-4 font-medium text-slate-700" x-text="app.course_name"></td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <div x-text="app.phone"></div>
                                    <div class="text-[10px] text-slate-400" x-text="app.email"></div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700" x-text="app.category"></span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 text-[11px]" x-text="app.created_at"></td>
                                <td class="py-3.5 px-4 text-right space-x-2">
                                    <button type="button" @click="openScrutiny(app, 'APPROVE')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-colors">
                                        Approve
                                    </button>
                                    <button type="button" @click="openScrutiny(app, 'REJECT')" class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-xs font-bold transition-colors">
                                        Reject
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <!-- TAB 2: REGISTRATION WINDOWS -->
    <div x-show="activeTab === 'windows'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-sm font-extrabold text-[#800000] uppercase tracking-wider mb-2">Configure Window</h2>
            <p class="text-xs text-slate-500 mb-4">Set statutory admission and promotion timelines per semester.</p>

            <form @submit.prevent="submitWindow()" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Target Course *</label>
                    <select x-model="winCourseId" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                        <?php foreach (($courses ?? []) as $c): ?>
                            <option value="<?= htmlspecialchars((string)$c['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$c['code'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Semester *</label>
                    <select x-model.number="winSemester" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>">Semester <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Academic Session *</label>
                    <select x-model="winSessionId" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                        <?php foreach (($sessions ?? []) as $s): ?>
                            <option value="<?= htmlspecialchars((string)$s['id'], ENT_QUOTES, 'UTF-8') ?>">
                                Session <?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Start Date</label>
                        <input type="date" x-model="winStartDate" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">End Date</label>
                        <input type="date" x-model="winEndDate" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Grace Days</label>
                        <input type="number" x-model.number="winGraceDays" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                        <select x-model.number="winIsActive" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" :disabled="loading" class="btn-primary w-full py-2.5 text-xs font-bold">
                    Save Window Limits
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-sm font-extrabold text-slate-900">Configured Registration Windows</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Course / Semester</th>
                            <th class="py-3 px-4">Session</th>
                            <th class="py-3 px-4">Timeline</th>
                            <th class="py-3 px-4">Grace</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="w in windowsList" :key="w.id">
                            <tr class="hover:bg-slate-50">
                                <td class="py-3 px-4 font-semibold text-slate-800" x-text="w.course_name + ' (Sem ' + w.semester + ')'"></td>
                                <td class="py-3 px-4 text-slate-600" x-text="w.session_name"></td>
                                <td class="py-3 px-4 text-slate-600 text-[11px]" x-text="w.start_date.substring(0,10) + ' to ' + w.end_date.substring(0,10)"></td>
                                <td class="py-3 px-4 font-bold text-slate-700" x-text="w.grace_days + ' days'"></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                          :class="w.is_active == 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                                          x-text="w.is_active == 1 ? 'ACTIVE' : 'INACTIVE'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: FACULTY MANAGEMENT -->
    <div x-show="activeTab === 'faculty'" x-cloak class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Faculty & Academic Staff</h2>
                <p class="text-xs text-slate-500">Manage teaching faculty credentials, designations, and portal access permissions.</p>
            </div>
            <button type="button" @click="facModalOpen = true" class="btn-primary py-2.5 px-4 text-xs font-bold shadow-md">
                + Add Faculty Account
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Staff Code</th>
                        <th class="py-3 px-4">Faculty Name</th>
                        <th class="py-3 px-4">Designation</th>
                        <th class="py-3 px-4">Department</th>
                        <th class="py-3 px-4">Contact</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="f in facultyList" :key="f.id">
                        <tr class="hover:bg-slate-50">
                            <td class="py-3.5 px-4 font-mono font-bold text-[#800000]" x-text="f.employee_code"></td>
                            <td class="py-3.5 px-4 font-bold text-slate-900" x-text="f.full_name"></td>
                            <td class="py-3.5 px-4 text-slate-700" x-text="f.designation"></td>
                            <td class="py-3.5 px-4 font-semibold text-slate-800" x-text="f.department_name"></td>
                            <td class="py-3.5 px-4 text-slate-600">
                                <div x-text="f.phone"></div>
                                <div class="text-[10px] text-slate-400" x-text="f.email"></div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                      :class="f.is_active == 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                                      x-text="f.is_active == 1 ? 'Active' : 'Disabled'"></span>
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-2">
                                <button type="button" @click="toggleFaculty(f)" class="text-xs text-slate-600 hover:text-slate-900 underline">
                                    <span x-text="f.is_active == 1 ? 'Disable' : 'Enable'"></span>
                                </button>
                                <button type="button" @click="openResetPwd(f)" class="text-xs text-[#800000] hover:underline font-bold">
                                    Reset Password
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 4: NOTICE BROADCASTER -->
    <div x-show="activeTab === 'notices'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-sm font-extrabold text-[#800000] uppercase tracking-wider mb-2">Publish Announcement</h2>
            <p class="text-xs text-slate-500 mb-4">Broadcast official notices to public ticker and student dashboard.</p>

            <form @submit.prevent="submitNotice()" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Notice Headline *</label>
                    <input type="text" x-model="noticeTitle" placeholder="e.g. UG Semester 1 CBCS Registration Notice" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Category *</label>
                    <select x-model="noticeCategory" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                        <option value="Admission">Admission</option>
                        <option value="Academic">Academic</option>
                        <option value="Exam">Examination</option>
                        <option value="General">General Notice</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Full Announcement Details *</label>
                    <textarea x-model="noticeContent" rows="4" placeholder="Enter complete statutory announcement text..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
                </div>
                <button type="submit" :disabled="loading" class="btn-primary w-full py-2.5 text-xs font-bold">
                    Broadcast to Ticker & Portal
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-sm font-extrabold text-slate-900">Institutional Announcements Log</h3>
            </div>
            <div class="divide-y divide-slate-100 p-6 space-y-4">
                <template x-for="n in noticeList" :key="n.id">
                    <div class="pt-3 first:pt-0">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#800000]/10 text-[#800000]" x-text="n.category"></span>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-slate-400" x-text="n.published_at"></span>
                                <button type="button" @click="deleteNotice(n)" class="text-[11px] text-red-600 hover:text-white hover:bg-red-600 px-2 py-0.5 rounded border border-red-200 transition-colors flex items-center gap-1 font-bold">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Remove
                                </button>
                            </div>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900 mb-1" x-text="n.title"></h4>
                        <p class="text-xs text-slate-600 leading-relaxed" x-text="n.content"></p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- TAB 5: STUDENT GRIEVANCE HELPDESK -->
    <div x-show="activeTab === 'grievances'" x-cloak class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Student & Public Grievance Resolution Desk</h2>
                <p class="text-xs text-slate-500">Address student grievances, fee disputes, and public inquiries.</p>
            </div>
            <span class="text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1 rounded-lg" x-text="grievanceList.length + ' Pending Inquiries'"></span>
        </div>

        <template x-if="grievanceList.length === 0">
            <div class="p-12 text-center text-slate-500">
                <svg class="w-12 h-12 mx-auto text-emerald-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <h3 class="text-base font-bold text-slate-800">No Pending Grievances</h3>
                <p class="text-xs text-slate-500 mt-1">All grievance tickets have been resolved or closed.</p>
            </div>
        </template>

        <template x-if="grievanceList.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Ticket No</th>
                            <th class="py-3 px-4">Complainant</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Subject</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Received</th>
                            <th class="py-3 px-4 text-right">Resolve</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="t in grievanceList" :key="t.id">
                            <tr class="hover:bg-slate-50">
                                <td class="py-3.5 px-4 font-mono font-bold text-[#800000]" x-text="t.ticket_no"></td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900" x-text="t.name"></div>
                                    <div class="text-[10px] text-slate-400" x-text="t.phone + ' • ' + t.submitted_by"></div>
                                </td>
                                <td class="py-3.5 px-4 font-medium text-slate-700" x-text="t.category"></td>
                                <td class="py-3.5 px-4 text-slate-800 max-w-xs truncate" x-text="t.subject"></td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                          :class="t.status === 'PENDING' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'"
                                          x-text="t.status"></span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 text-[11px]" x-text="t.created_at"></td>
                                <td class="py-3.5 px-4 text-right">
                                    <button type="button" @click="openGrievance(t)" class="btn-navy py-1.5 px-3 text-xs font-bold">
                                        Respond &rarr;
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <!-- Scrutiny Confirmation Modal -->
    <div x-show="scrutinyModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div @click.away="scrutinyModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-extrabold text-slate-900">
                <span x-text="scrutinyAction === 'APPROVE' ? 'Approve Admission Application' : 'Reject Admission Application'"></span>
            </h3>
            <template x-if="selectedApplicant">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs space-y-1">
                    <div>Applicant: <strong class="text-slate-900" x-text="selectedApplicant.full_name"></strong></div>
                    <div>App No: <strong class="font-mono text-[#800000]" x-text="selectedApplicant.application_no"></strong></div>
                    <div>Course: <span x-text="selectedApplicant.course_name"></span></div>
                    <div x-show="scrutinyAction === 'APPROVE'" class="text-emerald-700 font-semibold pt-1">
                        &bull; Approving will automatically assign sequential stream roll number (e.g. 26/BASS/XXXX).
                    </div>
                </div>
            </template>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Administrative Scrutiny Remarks</label>
                <textarea x-model="scrutinyRemarks" rows="3" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="scrutinyModal = false" class="px-4 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="button" @click="confirmScrutiny()" :disabled="loading"
                        :class="scrutinyAction === 'APPROVE' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
                        class="px-5 py-2 text-white rounded-xl text-xs font-bold shadow-md">
                    <span x-text="loading ? 'Processing...' : (scrutinyAction === 'APPROVE' ? 'Confirm Approval' : 'Confirm Rejection')"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Create Faculty Modal -->
    <div x-show="facModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div @click.away="facModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-extrabold text-slate-900">Add Teaching Faculty Member</h3>
            <form @submit.prevent="submitFaculty()" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Full Name *</label>
                        <input type="text" x-model="facName" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Designation *</label>
                        <input type="text" x-model="facDesignation" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Address *</label>
                        <input type="email" x-model="facEmail" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number *</label>
                        <input type="text" x-model="facPhone" required placeholder="10 digits" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Department *</label>
                    <select x-model="facDeptId" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                        <?php foreach (($departments ?? []) as $d): ?>
                            <option value="<?= htmlspecialchars((string)$d['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string)$d['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$d['faculty_type'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Initial Login Password *</label>
                    <input type="password" x-model="facPassword" required placeholder="Min 6 characters" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono">
                </div>
                <div class="flex justify-end gap-3 pt-3">
                    <button type="button" @click="facModalOpen = false" class="px-4 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" :disabled="loading" class="btn-primary py-2 px-5 text-xs font-bold">
                        <span x-text="loading ? 'Creating...' : 'Create Faculty Account'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div x-show="pwdModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div @click.away="pwdModalOpen = false" class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-extrabold text-slate-900">Reset Faculty Password</h3>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">New Password (Min 6 chars)</label>
                <input type="password" x-model="pwdNewValue" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="pwdModalOpen = false" class="px-4 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                <button type="button" @click="submitResetPwd()" class="btn-primary py-2 px-4 text-xs font-bold">Update Password</button>
            </div>
        </div>
    </div>

    <!-- Grievance Response Modal -->
    <div x-show="grvModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div @click.away="grvModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-extrabold text-slate-900">Respond to Grievance Ticket</h3>
            <template x-if="selectedTicket">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs space-y-1">
                    <div>Ticket: <strong class="font-mono text-[#800000]" x-text="selectedTicket.ticket_no"></strong></div>
                    <div>From: <span x-text="selectedTicket.name + ' (' + selectedTicket.phone + ')'"></span></div>
                    <div>Subject: <strong x-text="selectedTicket.subject"></strong></div>
                    <div class="text-slate-600 pt-1" x-text="selectedTicket.description"></div>
                </div>
            </template>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Resolution Status</label>
                <select x-model="grvStatus" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                    <option value="RESOLVED">RESOLVED (Grievance Addressed)</option>
                    <option value="IN_REVIEW">IN_REVIEW (Under Further Scrutiny)</option>
                    <option value="REJECTED">REJECTED (Invalid / Inadmissible)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Official Administrative Remarks *</label>
                <textarea x-model="grvRemarks" rows="3" placeholder="State official resolution action taken..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="grvModalOpen = false" class="px-4 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                <button type="button" @click="submitGrievanceResolution()" :disabled="loading" class="btn-primary py-2 px-5 text-xs font-bold">
                    <span x-text="loading ? 'Updating...' : 'Submit Resolution'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
