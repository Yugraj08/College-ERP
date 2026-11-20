<?php
declare(strict_types=1);

use App\Config\CollegeConfig;

$pageTitle = $title ?? 'Contact College Administration | ' . CollegeConfig::INSTITUTION_NAME;
$token = $csrfToken ?? '';
?>
<div x-data="{
    submitting: false,
    errorMessage: '',
    successResult: null,

    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',

    async submitInquiry() {
        this.submitting = true;
        this.errorMessage = '';

        try {
            const res = await fetch('/api/contact/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': '<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>'
                },
                body: JSON.stringify({
                    _csrf: '<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>',
                    name: this.name,
                    email: this.email,
                    phone: this.phone,
                    subject: this.subject,
                    message: this.message,
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                this.successResult = data;
                this.name = '';
                this.email = '';
                this.phone = '';
                this.subject = '';
                this.message = '';
            } else {
                this.errorMessage = data.error || 'Failed to submit inquiry.';
            }
        } catch (e) {
            this.errorMessage = 'Connection error. Please try again.';
        } finally {
            this.submitting = false;
        }
    }
}" class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <!-- Header Banner -->
    <div class="bg-[#800000] text-white rounded-2xl p-6 sm:p-8 mb-8 shadow-xl border border-[#D4AF37]/40">
        <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-[#D4AF37] text-xs font-semibold mb-2 border border-[#D4AF37]/30">
            Administrative Secretariat &bull; Punaura Dham, Sitamarhi
        </span>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Contact Administration & Campus Helpdesk</h1>
        <p class="text-sm text-slate-200 mt-2 max-w-2xl leading-relaxed">
            Reach out to the Principal Secretariat, Admissions Office, or Technical ERP Support team.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Contact Information Cards -->
        <div class="space-y-4">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="w-10 h-10 rounded-full bg-red-100 text-[#800000] flex items-center justify-center mb-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Campus Location</h3>
                <p class="text-xs text-slate-600 leading-relaxed"><?= CollegeConfig::CAMPUS_LOCATION ?></p>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="w-10 h-10 rounded-full bg-blue-100 text-[#002B5C] flex items-center justify-center mb-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Helpline Phone</h3>
                <p class="text-xs text-slate-600 mb-2">Available 9:00 AM – 5:00 PM IST</p>
                <a href="tel:+917597052444" class="text-sm font-bold text-[#800000] hover:underline"><?= CollegeConfig::HELPLINE_PHONE ?></a>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="w-10 h-10 rounded-full bg-amber-100 text-[#D4AF37] flex items-center justify-center mb-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 mb-1">Official Email</h3>
                <p class="text-xs text-slate-600 mb-2">Institutional inquiries & official circulars</p>
                <a href="mailto:<?= CollegeConfig::OFFICIAL_EMAIL ?>" class="text-sm font-bold text-[#002B5C] hover:underline"><?= CollegeConfig::OFFICIAL_EMAIL ?></a>
            </div>
        </div>

        <!-- Inquiry Form -->
        <div class="lg:col-span-2 bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
            <h2 class="text-base font-extrabold text-[#800000] uppercase tracking-wider mb-2">Send an Inquiry to College Office</h2>
            <p class="text-xs text-slate-500 mb-6">Fill in the form below and an administrative officer will contact you within 24–48 hours.</p>

            <div x-show="successResult" class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs">
                <p class="font-bold text-sm mb-1" x-text="successResult.message"></p>
                <p>Tracking Reference: <strong class="font-mono text-emerald-800" x-text="successResult.reference"></strong></p>
            </div>

            <div x-show="errorMessage" class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold" x-text="errorMessage"></div>

            <form @submit.prevent="submitInquiry()" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Your Name *</label>
                        <input type="text" x-model="name" required placeholder="Full Name" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Address *</label>
                        <input type="email" x-model="email" required placeholder="name@example.com" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Contact Phone Number *</label>
                        <input type="tel" x-model="phone" required placeholder="10-digit mobile" pattern="[6-9][0-9]{9}" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Subject *</label>
                        <input type="text" x-model="subject" required placeholder="Subject of inquiry" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Your Message *</label>
                    <textarea x-model="message" required rows="4" placeholder="How can our administration assist you?" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm"></textarea>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" :disabled="submitting" class="btn-primary py-2.5 px-6 text-xs font-bold">
                        <span x-text="submitting ? 'Submitting...' : 'Send Inquiry Message &rarr;'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
