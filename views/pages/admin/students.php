<?php
declare(strict_types=1);

use App\Config\CollegeConfig;

$pageTitle = 'Student Directory & Relational Registry | ' . CollegeConfig::INSTITUTION_NAME;
$token = $csrfToken ?? '';
?>
<script>
function studentDirectory() {
    return {
        search: '',
        courseCode: '',
        semester: '',
        status: '',
        category: '',
        page: 1,
        perPage: 15,
        students: [],
        pagination: { total: 0, page: 1, perPage: 15, totalPages: 1 },
        loading: false,

        // Detail Modal
        detailModal: false,
        selectedStudent: null,
        detailLoading: false,

        init() {
            this.fetchStudents();
            this.$watch('search', () => { this.page = 1; this.fetchStudents(); });
            this.$watch('courseCode', () => { this.page = 1; this.fetchStudents(); });
            this.$watch('semester', () => { this.page = 1; this.fetchStudents(); });
            this.$watch('status', () => { this.page = 1; this.fetchStudents(); });
            this.$watch('category', () => { this.page = 1; this.fetchStudents(); });
        },

        async fetchStudents() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    search: this.search,
                    course_code: this.courseCode,
                    semester: this.semester,
                    status: this.status,
                    category: this.category,
                    page: this.page,
                    per_page: this.perPage
                });

                const res = await fetch('/api/admin/students?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.students = data.students || [];
                    this.pagination = data.pagination || { total: 0, page: 1, perPage: 15, totalPages: 1 };
                }
            } catch (e) {
                console.error('Failed to load students', e);
            } finally {
                this.loading = false;
            }
        },

        goToPage(p) {
            if (p < 1 || p > this.pagination.totalPages) return;
            this.page = p;
            this.fetchStudents();
        },

        getExportUrl() {
            const params = new URLSearchParams({
                search: this.search,
                course_code: this.courseCode,
                semester: this.semester,
                status: this.status,
                category: this.category
            });
            return '/api/admin/students/export?' + params.toString();
        },

        viewDetails(student) {
            this.selectedStudent = student;
            this.detailModal = true;
        }
    };
}
</script>
<div x-data="studentDirectory()" class="space-y-6">

    <!-- Header Banner -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="/admin/principal" class="text-xs text-[#800000] hover:underline font-bold">&larr; Return to Principal Console</a>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-1">Student Academic Registry</h1>
            <p class="text-xs text-slate-500 mt-1">Multi-filter directory of all admitted, applied, and active students with live enrollment status.</p>
        </div>
        <div class="flex items-center gap-3">
            <a :href="getExportUrl()" class="btn-navy py-2.5 px-4 text-xs font-bold shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export CSV
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
        <div class="md:col-span-2">
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Debounced Search</label>
            <input type="text" x-model.debounce.400ms="search" placeholder="Search by name, roll, app no, mobile, email..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Course</label>
            <select x-model="courseCode" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                <option value="">All Courses</option>
                <?php foreach (($courses ?? []) as $c): ?>
                    <option value="<?= htmlspecialchars((string)$c['code'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string)$c['code'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Semester</label>
            <select x-model="semester" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                <option value="">All Semesters</option>
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <option value="<?= $i ?>">Semester <?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Status</label>
            <select x-model="status" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                <option value="">All Statuses</option>
                <option value="ADMITTED">ADMITTED</option>
                <option value="APPLIED">APPLIED</option>
                <option value="REJECTED">REJECTED</option>
                <option value="INACTIVE">INACTIVE</option>
            </select>
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700" x-text="'Total Records: ' + pagination.total"></span>
            <span x-show="loading" class="text-xs text-[#800000] font-bold animate-pulse">Loading directory...</span>
        </div>

        <template x-if="students.length === 0 && !loading">
            <div class="p-12 text-center text-slate-500">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <h3 class="text-base font-bold text-slate-800">No Students Found</h3>
                <p class="text-xs text-slate-500 mt-1">Try adjusting your search keywords or filter criteria.</p>
            </div>
        </template>

        <template x-if="students.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Roll No</th>
                            <th class="py-3 px-4">Student Name</th>
                            <th class="py-3 px-4">Application No</th>
                            <th class="py-3 px-4">Course / Sem</th>
                            <th class="py-3 px-4">Contact</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="s in students" :key="s.id">
                            <tr class="hover:bg-slate-50">
                                <td class="py-3.5 px-4 font-mono font-bold text-[#800000]" x-text="s.college_roll_no || 'PENDING'"></td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900" x-text="s.full_name"></div>
                                    <div class="text-[10px] text-slate-400" x-text="'Father: ' + s.father_name"></div>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-slate-600" x-text="s.application_no"></td>
                                <td class="py-3.5 px-4 font-medium text-slate-800" x-text="s.course_code + ' (Sem ' + s.current_semester + ')'"></td>
                                <td class="py-3.5 px-4 text-slate-600">
                                    <div x-text="s.phone"></div>
                                    <div class="text-[10px] text-slate-400 truncate max-w-[150px]" x-text="s.email"></div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700" x-text="s.category + ' (' + s.gender + ')'"></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                          :class="{
                                              'bg-emerald-100 text-emerald-800': s.status === 'ADMITTED',
                                              'bg-amber-100 text-amber-800': s.status === 'APPLIED',
                                              'bg-red-100 text-red-800': s.status === 'REJECTED'
                                          }"
                                          x-text="s.status"></span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <button type="button" @click="viewDetails(s)" class="text-xs text-[#800000] hover:underline font-bold">
                                        View &rarr;
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Pagination Controls -->
        <div x-show="pagination.totalPages > 1" class="p-4 border-t border-slate-200 flex items-center justify-between text-xs">
            <span class="text-slate-500" x-text="'Page ' + pagination.page + ' of ' + pagination.totalPages"></span>
            <div class="flex items-center gap-2">
                <button type="button" @click="goToPage(pagination.page - 1)" :disabled="pagination.page <= 1" class="px-3 py-1.5 border border-slate-300 rounded-lg font-bold disabled:opacity-40">Previous</button>
                <button type="button" @click="goToPage(pagination.page + 1)" :disabled="pagination.page >= pagination.totalPages" class="px-3 py-1.5 border border-slate-300 rounded-lg font-bold disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>

    <!-- Student Detail Modal -->
    <div x-show="detailModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div @click.away="detailModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-base font-extrabold text-slate-900">Student Profile Summary</h3>
                <button type="button" @click="detailModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>
            <template x-if="selectedStudent">
                <div class="space-y-3 text-xs">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-1.5">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Student Name:</span>
                            <strong class="text-slate-900" x-text="selectedStudent.full_name"></strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">College Roll No:</span>
                            <strong class="font-mono text-[#800000]" x-text="selectedStudent.college_roll_no || 'PENDING'"></strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Application Number:</span>
                            <span class="font-mono font-bold" x-text="selectedStudent.application_no"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Registration Code:</span>
                            <span class="font-mono text-slate-700" x-text="selectedStudent.registration_code || 'PENDING'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Course & Semester:</span>
                            <span class="font-semibold text-slate-800" x-text="selectedStudent.course_name + ' (Sem ' + selectedStudent.current_semester + ')'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Status:</span>
                            <span class="font-bold text-emerald-800" x-text="selectedStudent.status"></span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div>Father's Name: <strong x-text="selectedStudent.father_name"></strong></div>
                        <div>Mother's Name: <strong x-text="selectedStudent.mother_name"></strong></div>
                        <div>Date of Birth: <span x-text="selectedStudent.date_of_birth"></span></div>
                        <div>Mobile: <span x-text="selectedStudent.phone"></span></div>
                        <div>Email: <span x-text="selectedStudent.email"></span></div>
                        <div>Address: <span x-text="selectedStudent.address"></span></div>
                    </div>
                </div>
            </template>
            <div class="flex justify-end pt-2">
                <button type="button" @click="detailModal = false" class="btn-navy py-2 px-5 text-xs font-bold">Close Profile</button>
            </div>
        </div>
    </div>
</div>
