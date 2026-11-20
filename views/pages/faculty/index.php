<?php
declare(strict_types=1);

use App\Config\CollegeConfig;

$pageTitle = 'Faculty Academic Register & CIA Assessment | ' . CollegeConfig::INSTITUTION_NAME;
$token = $csrfToken ?? '';
$fac = $facultyProfile ?? [];
?>
<script>
function facultyPortal() {
    return {
        activeTab: 'attendance',
        loading: false,
        toastMessage: '',
        toastError: '',

        // Attendance Tab
        selectedSubject: <?= json_encode(!empty($subjects[0]['id']) ? (string)$subjects[0]['id'] : '') ?>,
        selectedSemester: <?= !empty($subjects[0]['semester']) ? (int)$subjects[0]['semester'] : 1 ?>,
        sessionDate: <?= json_encode(date('Y-m-d')) ?>,
        timeSlot: '10:00 AM - 11:00 AM',
        roomNo: 'LT-01',
        topicCovered: '',
        students: [],
        studentsLoaded: false,

        // CIA Tab
        ciaSubject: <?= json_encode(!empty($subjects[0]['id']) ? (string)$subjects[0]['id'] : '') ?>,
        ciaSemester: <?= !empty($subjects[0]['semester']) ? (int)$subjects[0]['semester'] : 1 ?>,
        ciaStudents: [],
        ciaLoaded: false,

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

        async loadStudentsForAttendance() {
            if (!this.selectedSubject) return;
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    subject_id: this.selectedSubject,
                    semester: this.selectedSemester
                });
                const res = await fetch('/api/faculty/students?' + params.toString());
                const data = await res.json();
                if (res.ok && data.success) {
                    this.students = (data.students || []).map(s => ({
                        ...s,
                        status: 'PRESENT',
                        remarks: ''
                    }));
                    this.studentsLoaded = true;
                } else {
                    this.showToast(data.error || 'Failed to load students', true);
                }
            } catch (e) {
                this.showToast('Network error loading students', true);
            } finally {
                this.loading = false;
            }
        },

        markAll(status) {
            this.students.forEach(s => s.status = status);
        },

        toggleStatus(s) {
            s.status = s.status === 'PRESENT' ? 'ABSENT' : 'PRESENT';
        },

        async submitAttendance() {
            if (this.students.length === 0) {
                this.showToast('No students loaded to register attendance.', true);
                return;
            }
            this.loading = true;
            try {
                const payload = {
                    _csrf: <?= json_encode($token) ?>,
                    subject_id: this.selectedSubject,
                    semester: parseInt(this.selectedSemester),
                    session_date: this.sessionDate,
                    time_slot: this.timeSlot,
                    room_no: this.roomNo,
                    topic_covered: this.topicCovered,
                    records: this.students.map(s => ({
                        student_id: s.student_id,
                        status: s.status,
                        remarks: s.remarks
                    }))
                };

                const res = await fetch('/api/faculty/attendance/save', {
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
                    this.showToast(data.message);
                    this.topicCovered = '';
                    setTimeout(() => location.reload(), 1200);
                } else {
                    this.showToast(data.error || 'Failed to register attendance', true);
                }
            } catch (e) {
                this.showToast('Network error saving attendance', true);
            } finally {
                this.loading = false;
            }
        },

        async loadStudentsForCia() {
            if (!this.ciaSubject) return;
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    subject_id: this.ciaSubject,
                    semester: this.ciaSemester
                });
                const res = await fetch('/api/faculty/students?' + params.toString());
                const data = await res.json();
                if (res.ok && data.success) {
                    this.ciaStudents = (data.students || []).map(s => ({
                        ...s,
                        mid_term_exam: parseFloat(s.cia?.mid_term_exam || 0),
                        assignment: parseFloat(s.cia?.assignment || 0),
                        attendance_score: parseFloat(s.cia?.attendance_score || 0),
                        total_cia_score: parseFloat(s.cia?.total_cia_score || 0),
                        saved: false
                    }));
                    this.ciaLoaded = true;
                } else {
                    this.showToast(data.error || 'Failed to load CIA roster', true);
                }
            } catch (e) {
                this.showToast('Network error loading CIA roster', true);
            } finally {
                this.loading = false;
            }
        },

        calculateCiaSum(s) {
            const m = Math.min(15, Math.max(0, parseFloat(s.mid_term_exam) || 0));
            const a = Math.min(10, Math.max(0, parseFloat(s.assignment) || 0));
            const att = Math.min(5, Math.max(0, parseFloat(s.attendance_score) || 0));
            s.mid_term_exam = m;
            s.assignment = a;
            s.attendance_score = att;
            s.total_cia_score = Math.round((m + a + att) * 100) / 100;
        },

        async saveStudentCia(s) {
            try {
                const res = await fetch('/api/faculty/cia/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        subject_id: this.ciaSubject,
                        student_id: s.student_id,
                        semester: parseInt(this.ciaSemester),
                        mid_term_exam: s.mid_term_exam,
                        assignment: s.assignment,
                        attendance_score: s.attendance_score
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    s.saved = true;
                    this.showToast(`Saved ${s.full_name}: ${s.total_cia_score}/30`);
                    setTimeout(() => s.saved = false, 3000);
                } else {
                    this.showToast(data.error || 'Failed to save CIA score', true);
                }
            } catch (e) {
                this.showToast('Network error saving CIA score', true);
            }
        }
    };
}
</script>
<div x-data="facultyPortal()" class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">

    <!-- Toast Notifications -->
    <div x-show="toastMessage" x-cloak class="p-4 bg-emerald-600 text-white rounded-xl shadow-lg text-sm font-semibold flex items-center justify-between">
        <span x-text="toastMessage"></span>
        <button @click="toastMessage = ''" class="ml-4 text-white">&times;</button>
    </div>
    <div x-show="toastError" x-cloak class="p-4 bg-red-600 text-white rounded-xl shadow-lg text-sm font-semibold flex items-center justify-between">
        <span x-text="toastError"></span>
        <button @click="toastError = ''" class="ml-4 text-white">&times;</button>
    </div>

    <!-- Faculty Profile Header -->
    <div class="bg-gradient-to-r from-[#800000] to-[#002B5C] text-white rounded-2xl p-6 sm:p-8 shadow-md border border-[#D4AF37]/30 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-[#D4AF37] text-xs font-semibold mb-2 border border-[#D4AF37]/30">
                Staff Code: <?= htmlspecialchars((string)($fac['employee_code'] ?? 'FACULTY'), ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars((string)($fac['department_name'] ?? 'Academic Department'), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                <?= htmlspecialchars((string)($fac['full_name'] ?? $user['full_name'] ?? 'Faculty Member'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-200 mt-1 max-w-2xl leading-relaxed">
                <?= htmlspecialchars((string)($fac['designation'] ?? 'Lecturer'), ENT_QUOTES, 'UTF-8') ?> &bull; BRABU CBCS Continuous Internal Assessment & Daily Lecture Attendance Registry
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/logout" class="px-4 py-2 bg-white/10 hover:bg-white/20 border border-white/20 text-white rounded-xl text-xs font-bold transition-colors">
                Logout
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        <button type="button" @click="activeTab = 'attendance'"
                :class="activeTab === 'attendance' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            Daily Attendance Register
        </button>
        <button type="button" @click="activeTab = 'cia'"
                :class="activeTab === 'cia' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            BRABU 30-Mark CIA Register
        </button>
        <button type="button" @click="activeTab = 'history'"
                :class="activeTab === 'history' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            Lecture Session History
        </button>
    </div>

    <!-- TAB 1: ATTENDANCE REGISTER -->
    <div x-show="activeTab === 'attendance'" class="space-y-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Subject / Paper *</label>
                <select x-model="selectedSubject" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                    <?php foreach (($subjects ?? []) as $sub): ?>
                        <option value="<?= htmlspecialchars((string)$sub['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string)$sub['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$sub['code'], ENT_QUOTES, 'UTF-8') ?> - Sem <?= (int)$sub['semester'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Semester *</label>
                <select x-model.number="selectedSemester" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>">Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" @click="loadStudentsForAttendance()" :disabled="loading" class="btn-primary w-full py-2.5 text-xs font-bold shadow-md">
                    <span x-text="loading ? 'Loading...' : 'Load Student Roster &rarr;'"></span>
                </button>
            </div>
        </div>

        <div x-show="studentsLoaded" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full sm:w-auto">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Lecture Date</label>
                        <input type="date" x-model="sessionDate" class="p-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Time Slot</label>
                        <input type="text" x-model="timeSlot" class="p-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Room No</label>
                        <input type="text" x-model="roomNo" class="p-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold">
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="markAll('PRESENT')" class="px-3 py-1.5 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-lg text-xs font-bold">
                        Mark All Present
                    </button>
                    <button type="button" @click="markAll('ABSENT')" class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-800 rounded-lg text-xs font-bold">
                        Mark All Absent
                    </button>
                </div>
            </div>

            <div class="p-4 bg-slate-50 border-b border-slate-200">
                <input type="text" x-model="topicCovered" placeholder="Lecture Topic Covered (e.g. CBCS Unit 3: Mauryan Administration)..." class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs">
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Roll No</th>
                            <th class="py-3 px-4">Student Name</th>
                            <th class="py-3 px-4">Registration Code</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="s in students" :key="s.student_id">
                            <tr class="hover:bg-slate-50">
                                <td class="py-3 px-4 font-mono font-bold text-[#800000]" x-text="s.college_roll_no || 'PENDING'"></td>
                                <td class="py-3 px-4 font-bold text-slate-900" x-text="s.full_name"></td>
                                <td class="py-3 px-4 font-mono text-slate-600" x-text="s.registration_code"></td>
                                <td class="py-3 px-4 text-center">
                                    <button type="button" @click="toggleStatus(s)"
                                            :class="s.status === 'PRESENT' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'"
                                            class="px-4 py-1.5 rounded-full font-bold text-xs shadow-sm transition-all">
                                        <span x-text="s.status"></span>
                                    </button>
                                </td>
                                <td class="py-3 px-4">
                                    <input type="text" x-model="s.remarks" placeholder="Optional notes" class="w-full p-1.5 bg-slate-50 border border-slate-200 rounded text-[11px]">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="p-6 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <span class="text-xs text-slate-500 font-semibold" x-text="'Total Enrolled Students: ' + students.length"></span>
                <button type="button" @click="submitAttendance()" :disabled="loading" class="btn-primary py-2.5 px-6 text-xs font-bold shadow-lg">
                    <span x-text="loading ? 'Saving...' : 'Register Lecture Attendance & Save Records'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- TAB 2: BRABU 30-MARK CIA REGISTER -->
    <div x-show="activeTab === 'cia'" x-cloak class="space-y-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Subject / Paper *</label>
                <select x-model="ciaSubject" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                    <?php foreach (($subjects ?? []) as $sub): ?>
                        <option value="<?= htmlspecialchars((string)$sub['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string)$sub['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$sub['code'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Semester *</label>
                <select x-model.number="ciaSemester" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>">Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" @click="loadStudentsForCia()" :disabled="loading" class="btn-navy w-full py-2.5 text-xs font-bold shadow-md">
                    <span x-text="loading ? 'Loading...' : 'Load CIA Marks Register &rarr;'"></span>
                </button>
            </div>
        </div>

        <div class="bg-amber-50 border border-amber-200 p-4 rounded-xl text-xs text-amber-900 leading-relaxed">
            <strong>BRABU Statutory CBCS Continuous Internal Assessment (CIA) Regulations:</strong>
            Total CIA score is <strong>30 Marks</strong> distributed as:
            Mid-term Written Exam (Max 15) + Seminar / Assignment / Project (Max 10) + Class Attendance (Max 5).
        </div>

        <div x-show="ciaLoaded" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Roll No</th>
                            <th class="py-3 px-4">Student Name</th>
                            <th class="py-3 px-4 text-center">Mid-Term (15)</th>
                            <th class="py-3 px-4 text-center">Assignment (10)</th>
                            <th class="py-3 px-4 text-center">Attendance (5)</th>
                            <th class="py-3 px-4 text-center">Total (30)</th>
                            <th class="py-3 px-4 text-right">Save</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="s in ciaStudents" :key="s.student_id">
                            <tr class="hover:bg-slate-50">
                                <td class="py-3 px-4 font-mono font-bold text-[#800000]" x-text="s.college_roll_no || 'PENDING'"></td>
                                <td class="py-3 px-4 font-bold text-slate-900" x-text="s.full_name"></td>
                                <td class="py-3 px-4 text-center">
                                    <input type="number" step="0.5" min="0" max="15" x-model.number="s.mid_term_exam" @input="calculateCiaSum(s)"
                                           class="w-20 p-1.5 bg-slate-50 border border-slate-300 rounded-lg text-center font-bold text-xs">
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <input type="number" step="0.5" min="0" max="10" x-model.number="s.assignment" @input="calculateCiaSum(s)"
                                           class="w-20 p-1.5 bg-slate-50 border border-slate-300 rounded-lg text-center font-bold text-xs">
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <input type="number" step="0.5" min="0" max="5" x-model.number="s.attendance_score" @input="calculateCiaSum(s)"
                                           class="w-20 p-1.5 bg-slate-50 border border-slate-300 rounded-lg text-center font-bold text-xs">
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-mono font-extrabold"
                                          :class="s.total_cia_score >= 12 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                          x-text="s.total_cia_score + ' / 30'"></span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button type="button" @click="saveStudentCia(s)"
                                            :class="s.saved ? 'bg-emerald-600 text-white' : 'bg-slate-800 hover:bg-slate-900 text-white'"
                                            class="px-3 py-1 rounded-lg text-xs font-bold transition-all shadow-sm">
                                        <span x-text="s.saved ? 'Saved ✓' : 'Save'"></span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: ATTENDANCE HISTORY -->
    <div x-show="activeTab === 'history'" x-cloak class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">Conducted Lecture Sessions Log</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Subject</th>
                        <th class="py-3 px-4">Time & Room</th>
                        <th class="py-3 px-4">Topic Covered</th>
                        <th class="py-3 px-4 text-center">Present</th>
                        <th class="py-3 px-4 text-center">Absent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach (($recentSessions ?? []) as $ses): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars((string)$ses['session_date'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3 px-4 font-semibold text-[#800000]"><?= htmlspecialchars((string)$ses['subject_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars((string)$ses['time_slot'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)($ses['room_no'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>)</td>
                            <td class="py-3 px-4 text-slate-700 max-w-xs truncate"><?= htmlspecialchars((string)($ses['topic_covered'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3 px-4 text-center font-bold text-emerald-700"><?= (int)$ses['total_present'] ?></td>
                            <td class="py-3 px-4 text-center font-bold text-red-700"><?= (int)$ses['total_absent'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
