<?php
declare(strict_types=1);

use App\Config\CollegeConfig;

$pageTitle = 'Student Portal & Academic Dashboard | ' . CollegeConfig::INSTITUTION_NAME;
$token = $csrfToken ?? '';
$st = $student ?? [];
?>
<script>
function studentPortal() {
    return {
        activeTab: 'academics',
        loading: false,
        toastMessage: '',
        toastError: '',

        // Progression Payment
        paymentMethod: 'ONLINE_GATEWAY',
        utrNumber: '',
        journalNo: '',
        progLoading: false,
        progResult: null,

        // Printable Receipt Modal
        receiptModal: false,
        selectedReceipt: null,
        receiptLoading: false,

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

        async submitProgressionPayment() {
            this.progLoading = true;
            try {
                const res = await fetch('/api/student/pay-progression', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': <?= json_encode($token) ?>
                    },
                    body: JSON.stringify({
                        _csrf: <?= json_encode($token) ?>,
                        target_semester: <?= (int)($nextSemester ?? 2) ?>,
                        payment_method: this.paymentMethod,
                        utr_number: this.utrNumber,
                        journal_no: this.journalNo
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.progResult = data;
                    this.showToast(data.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    this.showToast(data.error || 'Payment progression failed', true);
                }
            } catch (e) {
                this.showToast('Network error during progression payment', true);
            } finally {
                this.progLoading = false;
            }
        },

        async openReceipt(receiptNo) {
            this.receiptLoading = true;
            this.receiptModal = true;
            try {
                const res = await fetch('/api/student/receipt?receipt_no=' + encodeURIComponent(receiptNo));
                const data = await res.json();
                if (res.ok && data.success) {
                    this.selectedReceipt = data.receipt;
                } else {
                    this.showToast('Receipt record not found', true);
                    this.receiptModal = false;
                }
            } catch (e) {
                this.showToast('Network error loading receipt', true);
                this.receiptModal = false;
            } finally {
                this.receiptLoading = false;
            }
        }
    };
}
</script>
<div x-data="studentPortal()" class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">

    <!-- Toast Notifications -->
    <div x-show="toastMessage" x-cloak class="p-4 bg-emerald-600 text-white rounded-xl shadow-lg text-sm font-semibold flex items-center justify-between">
        <span x-text="toastMessage"></span>
        <button @click="toastMessage = ''" class="ml-4 text-white">&times;</button>
    </div>
    <div x-show="toastError" x-cloak class="p-4 bg-red-600 text-white rounded-xl shadow-lg text-sm font-semibold flex items-center justify-between">
        <span x-text="toastError"></span>
        <button @click="toastError = ''" class="ml-4 text-white">&times;</button>
    </div>

    <!-- Student Header Banner -->
    <div class="bg-gradient-to-r from-[#800000] via-[#5C0000] to-[#001836] text-white rounded-2xl p-6 sm:p-8 shadow-md border border-[#D4AF37]/30 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-[#D4AF37] text-xs font-semibold mb-2 border border-[#D4AF37]/30">
                Roll No: <?= htmlspecialchars((string)($st['college_roll_no'] ?? 'PENDING'), ENT_QUOTES, 'UTF-8') ?> &bull; Session <?= htmlspecialchars((string)($st['session_name'] ?? '2026-2030'), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                <?= htmlspecialchars((string)($st['full_name'] ?? $user['full_name'] ?? 'Student'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-200 mt-1 max-w-2xl leading-relaxed">
                <?= htmlspecialchars((string)($st['course_name'] ?? 'Undergraduate Degree'), ENT_QUOTES, 'UTF-8') ?> &bull; Semester <?= (int)($st['current_semester'] ?? 1) ?> &bull; Reg Code: <span class="font-mono text-[#D4AF37]"><?= htmlspecialchars((string)($st['registration_code'] ?? 'PENDING'), ENT_QUOTES, 'UTF-8') ?></span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                Status: <?= htmlspecialchars((string)($st['status'] ?? 'ADMITTED'), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <a href="/logout" class="px-4 py-2 bg-white/10 hover:bg-white/20 border border-white/20 text-white rounded-xl text-xs font-bold transition-colors">
                Logout
            </a>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Attendance %</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-extrabold <?= ($attendance['percentage'] ?? 100) >= 75 ? 'text-emerald-700' : 'text-amber-600' ?>">
                    <?= number_format((float)($attendance['percentage'] ?? 100), 1) ?>%
                </span>
                <span class="text-[10px] text-slate-400">
                    (<?= (int)($attendance['attendedLectures'] ?? 0) ?>/<?= (int)($attendance['totalLectures'] ?? 0) ?>)
                </span>
            </div>
            <span class="text-[10px] font-semibold <?= ($attendance['percentage'] ?? 100) >= 75 ? 'text-emerald-600' : 'text-amber-600' ?>">
                <?= ($attendance['percentage'] ?? 100) >= 75 ? 'BRABU Exam Eligible' : 'Shortage Warning (<75%)' ?>
            </span>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Current Standing</span>
            <span class="text-2xl font-extrabold text-[#800000] mt-1 block">Semester <?= (int)($st['current_semester'] ?? 1) ?></span>
            <span class="text-[10px] text-slate-400 font-semibold">Four-Year Degree Course (FYUGP)</span>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Enrolled Credits</span>
            <?php
            $totalCreds = 0;
            foreach (($enrolledSubjects ?? []) as $sub) {
                $totalCreds += (int)($sub['credits'] ?? 0);
            }
            ?>
            <span class="text-2xl font-extrabold text-slate-900 mt-1 block"><?= $totalCreds ?: 20 ?> Credits</span>
            <span class="text-[10px] text-emerald-600 font-semibold">BRABU CBCS Statutory Total</span>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Payments Cleared</span>
            <span class="text-2xl font-extrabold text-emerald-700 mt-1 block"><?= count($feeLedger ?? []) ?> Receipts</span>
            <span class="text-[10px] text-slate-400 font-semibold">Itemized Ledger Cleared</span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        <button type="button" @click="activeTab = 'academics'"
                :class="activeTab === 'academics' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            CBCS Subjects & CIA Scores
        </button>
        <button type="button" @click="activeTab = 'fees'"
                :class="activeTab === 'fees' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            Fee Ledger & Receipts
        </button>
        <button type="button" @click="activeTab = 'progression'"
                :class="activeTab === 'progression' ? 'bg-[#800000] text-white' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm">
            Semester Progression
        </button>
    </div>

    <!-- TAB 1: CBCS SUBJECTS & CIA PERFORMANCE -->
    <div x-show="activeTab === 'academics'" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">Enrolled CBCS Subjects & Internal Assessment (Semester <?= (int)($st['current_semester'] ?? 1) ?>)</h2>
            <p class="text-xs text-slate-500">Continuous Internal Assessment (CIA) marks awarded out of 30 statutory marks.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Tier</th>
                        <th class="py-3 px-4">Subject Code</th>
                        <th class="py-3 px-4">Subject Title</th>
                        <th class="py-3 px-4 text-center">Credits</th>
                        <th class="py-3 px-4 text-center">Mid-Term (15)</th>
                        <th class="py-3 px-4 text-center">Assignment (10)</th>
                        <th class="py-3 px-4 text-center">Attendance (5)</th>
                        <th class="py-3 px-4 text-center">CIA Score (30)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php
                    $ciaMap = [];
                    foreach (($ciaScores ?? []) as $c) {
                        $ciaMap[$c['subject_id'] ?? ''] = $c;
                    }
                    foreach (($enrolledSubjects ?? []) as $s):
                        $cRow = $ciaMap[$s['subject_id'] ?? ''] ?? null;
                    ?>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3.5 px-4 font-bold text-[#800000]"><?= htmlspecialchars((string)$s['category'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3.5 px-4 font-mono font-medium text-slate-700"><?= htmlspecialchars((string)$s['subject_code'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars((string)$s['subject_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-800"><?= (int)$s['credits'] ?></td>
                            <td class="py-3.5 px-4 text-center text-slate-700"><?= $cRow ? number_format((float)$cRow['mid_term_exam'], 1) : '-' ?></td>
                            <td class="py-3.5 px-4 text-center text-slate-700"><?= $cRow ? number_format((float)$cRow['assignment'], 1) : '-' ?></td>
                            <td class="py-3.5 px-4 text-center text-slate-700"><?= $cRow ? number_format((float)$cRow['attendance_score'], 1) : '-' ?></td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($cRow): ?>
                                    <span class="px-2.5 py-1 rounded-full font-bold font-mono text-[11px] <?= (float)$cRow['total_cia_score'] >= 12 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                        <?= number_format((float)$cRow['total_cia_score'], 1) ?> / 30
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 text-[11px]">Evaluation Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: FEE LEDGER & PRINTABLE RECEIPTS -->
    <div x-show="activeTab === 'fees'" x-cloak class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">Official Institutional Fee Ledger</h2>
            <p class="text-xs text-slate-500">All authenticated fee receipts issued by the Accounts Secretariat.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Receipt Number</th>
                        <th class="py-3 px-4">Description</th>
                        <th class="py-3 px-4">Semester</th>
                        <th class="py-3 px-4">Amount</th>
                        <th class="py-3 px-4">Method</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Paid On</th>
                        <th class="py-3 px-4 text-right">Receipt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach (($feeLedger ?? []) as $f): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3.5 px-4 font-mono font-bold text-[#800000]"><?= htmlspecialchars((string)$f['receipt_no'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900"><?= htmlspecialchars((string)$f['title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3.5 px-4 font-medium text-slate-700">Semester <?= (int)$f['target_semester'] ?></td>
                            <td class="py-3.5 px-4 font-bold text-slate-900">₹<?= number_format((float)$f['amount'], 2) ?></td>
                            <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars((string)$f['payment_method'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $f['status'] === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                    <?= htmlspecialchars((string)$f['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500 text-[11px]"><?= htmlspecialchars((string)($f['paid_at'] ?? $f['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-3.5 px-4 text-right">
                                <button type="button" @click="openReceipt('<?= htmlspecialchars((string)$f['receipt_no'], ENT_QUOTES, 'UTF-8') ?>')" class="btn-navy py-1.5 px-3 text-xs font-bold">
                                    View Slip &rarr;
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 3: SEMESTER PROGRESSION -->
    <div x-show="activeTab === 'progression'" x-cloak class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
        <div>
            <h2 class="text-base font-extrabold text-slate-900">Next Semester Academic Progression</h2>
            <p class="text-xs text-slate-500">Advance to your next statutory semester, register subjects, and clear the university term fee.</p>
        </div>

        <?php if ($nextSemester === null): ?>
            <div class="p-6 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 text-xs">
                You are currently in Semester 8, which is the final semester of the Four-Year Undergraduate Program (FYUGP).
            </div>
        <?php elseif ($isNextSemPaid): ?>
            <div class="p-6 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 text-xs flex items-center gap-3">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h3 class="font-bold text-sm">Semester <?= $nextSemester ?> Registration & Fees Cleared</h3>
                    <p class="text-slate-600 mt-0.5">Your fee has been received and verified. You are enrolled for Semester <?= $nextSemester ?>.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-slate-50 p-6 rounded-xl border border-slate-200 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <span class="block text-xs font-bold text-slate-500 uppercase">Target Semester</span>
                    <span class="text-xl font-extrabold text-[#800000]">Semester <?= $nextSemester ?></span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-500 uppercase">Statutory Fee</span>
                    <span class="text-xl font-extrabold text-slate-900">₹<?= number_format((float)($nextSemesterFee ?? 2800), 2) ?></span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-500 uppercase">Current Standing</span>
                    <span class="text-xl font-bold text-emerald-700">Eligible to Progress</span>
                </div>
            </div>

            <div class="space-y-4 pt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Choose Payment Method *</label>
                    <select x-model="paymentMethod" class="w-full sm:w-1/2 p-3 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                        <option value="ONLINE_GATEWAY">Instant Online Gateway (Simulator / Razorpay)</option>
                        <option value="UPI">UPI Direct Transfer (12-Digit UTR)</option>
                        <option value="BANK_CHALLAN">Bank Challan (SBI Punaura Branch)</option>
                    </select>
                </div>

                <div x-show="paymentMethod === 'UPI'" class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                    <span class="text-xs text-slate-600 block">Transfer to VPA: <strong class="font-mono text-[#800000]"><?= CollegeConfig::INSTITUTION_CODE ?> (mkrcollege@sbi)</strong></span>
                    <input type="text" x-model="utrNumber" placeholder="Enter 12-digit UPI UTR Number" class="w-full sm:w-1/2 p-2 bg-white border border-slate-300 rounded-lg text-xs font-mono">
                </div>

                <button type="button" @click="submitProgressionPayment()" :disabled="progLoading" class="btn-primary py-3 px-8 text-xs font-bold shadow-lg">
                    <span x-text="progLoading ? 'Processing...' : 'Pay ₹<?= number_format((float)($nextSemesterFee ?? 2800), 2) ?> & Advance to Semester <?= $nextSemester ?>'"></span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Printable Receipt Modal -->
    <div x-show="receiptModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div @click.away="receiptModal = false" class="bg-white rounded-2xl max-w-xl w-full p-6 sm:p-8 shadow-2xl space-y-6 print:p-0 print:shadow-none print:border-none">
            <template x-if="selectedReceipt">
                <div>
                    <!-- Header -->
                    <div class="border-b-2 border-[#800000] pb-4 mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img src="/assets/logo/mkr-college-emblem.webp" alt="Emblem" class="w-12 h-12 rounded-full border border-[#D4AF37] p-0.5">
                            <div>
                                <span class="text-[10px] font-bold text-[#800000] uppercase tracking-wider">Official Fee Receipt</span>
                                <h3 class="text-sm font-extrabold text-slate-900"><?= CollegeConfig::INSTITUTION_NAME ?></h3>
                                <p class="text-[10px] text-slate-500"><?= CollegeConfig::CAMPUS_LOCATION ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="font-mono text-xs font-extrabold text-[#800000]" x-text="selectedReceipt.receipt_no"></span>
                            <div class="text-[10px] text-slate-400" x-text="selectedReceipt.paid_at || selectedReceipt.created_at"></div>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-4 rounded-xl border border-slate-200 mb-4">
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase">Student Name</span>
                            <strong class="text-slate-900" x-text="selectedReceipt.full_name"></strong>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase">College Roll No</span>
                            <strong class="font-mono text-[#800000]" x-text="selectedReceipt.college_roll_no || 'PENDING'"></strong>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase">Course / Semester</span>
                            <span class="font-semibold text-slate-800" x-text="selectedReceipt.course_code + ' - Semester ' + selectedReceipt.target_semester"></span>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase">Payment Method</span>
                            <span class="font-medium" x-text="selectedReceipt.payment_method"></span>
                        </div>
                    </div>

                    <!-- Payment Amount -->
                    <div class="border border-slate-200 rounded-xl p-4 mb-4 flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-700" x-text="selectedReceipt.title"></span>
                        <span class="text-lg font-extrabold text-emerald-800 font-mono" x-text="'₹' + parseFloat(selectedReceipt.amount).toFixed(2)"></span>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 print:hidden">
                        <button type="button" @click="receiptModal = false" class="px-4 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-600">Close</button>
                        <button type="button" @click="window.print()" class="btn-primary py-2 px-5 text-xs font-bold flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Receipt
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
