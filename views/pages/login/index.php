<?php
declare(strict_types=1);

use App\Config\CollegeConfig;

$pageTitle = $title ?? 'ERP Portal Login | ' . CollegeConfig::INSTITUTION_NAME;
$currentRole = $defaultRole ?? 'STUDENT';
$token = $csrfToken ?? '';
$error = $flashError ?? '';
$success = $flashSuccess ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#001836]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/webp" href="/assets/logo/mkr-college-emblem.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        college: {
                            maroon: '#800000',
                            'maroon-dark': '#5C0000',
                            navy: '#002B5C',
                            'navy-dark': '#001836',
                            gold: '#D4AF37',
                            'gold-light': '#F5E6BE'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="/js/vendor/alpine.min.js"></script>
    <script defer src="/js/app.js"></script>
</head>
<body class="h-screen max-h-screen overflow-hidden bg-[#001836] font-sans text-slate-100 antialiased select-none"
      x-data="{
          activeRole: '<?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?>',
          showPassword: false,
          forgotModalOpen: false,
          toastOpen: false,
          toastMessage: '',
          submitting: false,
          remember: false,
          identifier: '',
          password: '',
          errorMessage: '<?= addslashes($error) ?>',
          successMessage: '<?= addslashes($success) ?>',
          get identifierLabel() {
              if (this.activeRole === 'SUPER_ADMIN') return 'Administrative Email';
              if (this.activeRole === 'FACULTY') return 'Staff Code / Email / Mobile';
              return 'Roll No / Reg Code / Application No';
          },
          get identifierPlaceholder() {
              if (this.activeRole === 'SUPER_ADMIN') return 'mkrcollege@gmail.com';
              if (this.activeRole === 'FACULTY') return 'Staff Code / Email / Mobile';
              return 'Roll No / Reg Code / App No';
          },
          get passwordHint() {
              if (this.activeRole === 'STUDENT') return 'Default password is your Date of Birth (YYYY-MM-DD) or Mobile Number';
              if (this.activeRole === 'FACULTY') return 'Default password is your registered Mobile or Staff Code';
              return 'Enter master security password';
          },
          showToast(msg) {
              this.toastMessage = msg;
              this.toastOpen = true;
              setTimeout(() => { this.toastOpen = false; }, 5000);
          },
          async handleLogin() {
              if (!this.identifier.trim() || !this.password.trim()) {
                  this.errorMessage = 'Please provide both your identifier and password.';
                  return;
              }

              this.submitting = true;
              this.errorMessage = '';
              this.successMessage = '';

              try {
                  const res = await fetch('/api/auth/login', {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/json',
                          'Accept': 'application/json',
                          'X-CSRF-Token': '<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>'
                      },
                      body: JSON.stringify({
                          role: this.activeRole,
                          identifier: this.identifier.trim(),
                          password: this.password.trim(),
                          remember: this.remember,
                          _csrf: '<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>'
                      })
                  });

                  const data = await res.json();

                  if (res.ok && data.success) {
                      this.successMessage = 'Authentication successful! Redirecting...';
                      const redirectUrl = data.redirect || '/';
                      setTimeout(() => {
                          window.location.href = redirectUrl;
                      }, 400);
                  } else {
                      this.errorMessage = data.error || 'Authentication failed. Please verify credentials.';
                      this.showToast(this.errorMessage);
                  }
              } catch (err) {
                  this.errorMessage = 'Network or server error occurred. Please try again.';
                  this.showToast(this.errorMessage);
              } finally {
                  this.submitting = false;
              }
          }
      }">

    <div class="h-full w-full flex flex-col md:flex-row">
        <!-- Left Side: 65% Auto-rotating 7-Second Slideshow -->
        <div class="hidden md:flex md:w-[60%] lg:w-[65%] h-full relative overflow-hidden bg-slate-950 flex-col justify-between"
             x-data="{
                 current: 0,
                 slides: [
                     { src: '/assets/erp-panel/image-1.png', label: 'Main Academic Campus & Administrative Secretariat' },
                     { src: '/assets/erp-panel/image-2.webp', label: 'BRABU UG CBCS Learning Centers & Digital Lecture Halls' }
                 ],
                 timer: null,
                 init() {
                     this.timer = setInterval(() => {
                         this.current = (this.current + 1) % this.slides.length;
                     }, 7000);
                 }
             }">

            <!-- Slides Images -->
            <div class="absolute inset-0 z-0">
                <template x-for="(slide, index) in slides" :key="index">
                    <img :src="slide.src"
                         :alt="slide.label"
                         class="absolute inset-0 w-full h-full object-cover transition-all duration-1000 ease-in-out"
                         :class="current === index ? 'opacity-100 scale-100 z-10' : 'opacity-0 scale-105 z-0'">
                </template>
            </div>

            <!-- Gradient Filter -->
            <div class="absolute inset-0 bg-gradient-to-t from-[#001836] via-[#001836]/60 to-black/40 z-20 pointer-events-none"></div>

            <!-- Campus Branding Header -->
            <div class="relative z-30 p-8 lg:p-12 flex items-center justify-between">
                <a href="/" class="flex items-center gap-4 group">
                    <img src="/assets/logo/mkr-college-emblem.webp"
                         alt="MKR College Emblem"
                         class="w-14 h-14 rounded-full border-2 border-[#D4AF37] bg-white p-1 shadow-lg group-hover:scale-105 transition-transform duration-300">
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-[#D4AF37]">Affiliated with BRABU Muzaffarpur</span>
                        <h2 class="text-lg lg:text-xl font-extrabold text-white leading-tight drop-shadow">
                            <?= CollegeConfig::INSTITUTION_NAME ?>
                        </h2>
                        <span class="text-xs text-slate-300">Punaura Dham, Sitamarhi, Bihar (843302)</span>
                    </div>
                </a>

                <a href="/" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-black/40 hover:bg-black/60 border border-slate-600/60 text-xs text-slate-200 backdrop-blur transition-colors">
                    <svg class="w-3.5 h-3.5 text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Website Home
                </a>
            </div>

            <!-- Slide Caption & Highlights -->
            <div class="relative z-30 p-8 lg:p-12 space-y-4">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#800000]/80 border border-[#D4AF37]/50 text-[#D4AF37] text-xs font-semibold backdrop-blur shadow">
                    <span class="w-2 h-2 rounded-full bg-[#D4AF37] animate-pulse"></span>
                    BRABU CBCS UG 4-Year Semester System (2026–2030)
                </div>

                <div class="max-w-xl">
                    <h1 class="text-2xl lg:text-3xl font-extrabold text-white drop-shadow-md leading-tight">
                        Integrated Academic & Examination ERP Management System
                    </h1>
                    <p class="text-sm text-slate-300 mt-2 line-clamp-2"
                       x-text="slides[current].label"></p>
                </div>

                <!-- Slide Navigation Dots -->
                <div class="flex items-center gap-2 pt-2">
                    <template x-for="(slide, index) in slides" :key="index">
                        <button type="button"
                                @click="current = index"
                                class="h-2 rounded-full transition-all duration-300"
                                :class="current === index ? 'w-8 bg-[#D4AF37]' : 'w-2 bg-white/40 hover:bg-white/70'"></button>
                    </template>
                    <span class="text-xs text-slate-400 font-mono ml-2" x-text="'0' + (current + 1) + ' / 0' + slides.length"></span>
                </div>
            </div>
        </div>

        <!-- Right Side: 35% Role-Based Centralized Login Portal -->
        <div class="w-full md:w-[40%] lg:w-[35%] h-full flex flex-col justify-between overflow-y-auto bg-[#001836] border-l border-[#5C0000]/60 p-6 sm:p-8 lg:p-10 shadow-2xl z-30">
            <div>
                <!-- Mobile Campus Header Bar -->
                <div class="md:hidden flex items-center justify-between pb-6 mb-4 border-b border-slate-700/60">
                    <a href="/" class="flex items-center gap-3">
                        <img src="/assets/logo/mkr-college-emblem.webp" alt="Logo" class="w-10 h-10 rounded-full border border-[#D4AF37] bg-white p-0.5">
                        <div class="text-left">
                            <h2 class="text-sm font-bold text-white leading-tight">MKR College</h2>
                            <p class="text-[10px] text-[#D4AF37]">Sitamarhi, Bihar (BRABU)</p>
                        </div>
                    </a>
                    <a href="/" class="text-xs text-slate-400 hover:text-white flex items-center gap-1">
                        ← Home
                    </a>
                </div>

                <!-- Card Title -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 text-[#D4AF37] text-xs font-semibold tracking-wider uppercase mb-1">
                        <svg class="w-4 h-4 text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Secure Authentication
                    </div>
                    <h3 class="text-2xl font-extrabold text-white tracking-tight">Institutional ERP Access</h3>
                    <p class="text-xs text-slate-400 mt-1">Select your designated portal role to log in</p>
                </div>

                <!-- Role Selection 3-Radio Selector -->
                <div class="grid grid-cols-3 gap-1.5 p-1 bg-black/40 border border-slate-700/60 rounded-xl mb-6 shadow-inner">
                    <!-- Super Admin Radio -->
                    <label class="flex flex-col items-center justify-center py-2.5 px-2 rounded-lg text-xs font-semibold cursor-pointer transition-all duration-200"
                           :class="activeRole === 'SUPER_ADMIN' ? 'bg-[#800000] text-white shadow-md border border-[#D4AF37]/50' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'">
                        <input type="radio" name="role_selector" value="SUPER_ADMIN" x-model="activeRole" class="sr-only">
                        <svg class="w-4 h-4 mb-1" :class="activeRole === 'SUPER_ADMIN' ? 'text-[#D4AF37]' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span>Super Admin</span>
                    </label>

                    <!-- Faculty Radio -->
                    <label class="flex flex-col items-center justify-center py-2.5 px-2 rounded-lg text-xs font-semibold cursor-pointer transition-all duration-200"
                           :class="activeRole === 'FACULTY' ? 'bg-[#800000] text-white shadow-md border border-[#D4AF37]/50' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'">
                        <input type="radio" name="role_selector" value="FACULTY" x-model="activeRole" class="sr-only">
                        <svg class="w-4 h-4 mb-1" :class="activeRole === 'FACULTY' ? 'text-[#D4AF37]' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                        </svg>
                        <span>Faculty</span>
                    </label>

                    <!-- Student Radio -->
                    <label class="flex flex-col items-center justify-center py-2.5 px-2 rounded-lg text-xs font-semibold cursor-pointer transition-all duration-200"
                           :class="activeRole === 'STUDENT' ? 'bg-[#800000] text-white shadow-md border border-[#D4AF37]/50' : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'">
                        <input type="radio" name="role_selector" value="STUDENT" x-model="activeRole" class="sr-only">
                        <svg class="w-4 h-4 mb-1" :class="activeRole === 'STUDENT' ? 'text-[#D4AF37]' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Student</span>
                    </label>
                </div>

                <!-- Inline Reactive Error Message (Without Page Reload) -->
                <div x-show="errorMessage"
                     x-cloak
                     class="mb-5 p-3 rounded-xl bg-red-950/80 border border-red-700/80 text-red-200 text-xs flex items-start gap-2.5 shadow">
                    <svg class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="flex-1 font-medium leading-relaxed" x-text="errorMessage"></div>
                    <button type="button" @click="errorMessage = ''" class="text-red-400 hover:text-red-200 text-xs">✕</button>
                </div>

                <!-- Inline Success Message -->
                <div x-show="successMessage"
                     x-cloak
                     class="mb-5 p-3 rounded-xl bg-emerald-950/80 border border-emerald-700/80 text-emerald-200 text-xs flex items-start gap-2.5 shadow">
                    <svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <div class="flex-1 font-medium leading-relaxed" x-text="successMessage"></div>
                </div>

                <!-- Alpine.js AJAX Login Form -->
                <form action="/login" method="POST" @submit.prevent="handleLogin()" class="space-y-4">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="role" :value="activeRole">
                    <!-- Dynamic Identifier Input -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5" x-text="identifierLabel"></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input type="text"
                                   x-model="identifier"
                                   :placeholder="identifierPlaceholder"
                                   required
                                   autocomplete="username"
                                   class="w-full pl-10 pr-3 py-2.5 bg-black/40 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#D4AF37] focus:ring-1 focus:ring-[#D4AF37] transition-colors">
                        </div>
                    </div>

                    <!-- Password Input with Show/Hide Toggle -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Password</label>
                            <button type="button"
                                    @click="forgotModalOpen = true"
                                    class="text-xs text-[#D4AF37] hover:underline focus:outline-none">
                                Forgot password?
                            </button>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input :type="showPassword ? 'text' : 'password'"
                                   x-model="password"
                                   placeholder="••••••••••••"
                                   required
                                   autocomplete="current-password"
                                   class="w-full pl-10 pr-10 py-2.5 bg-black/40 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#D4AF37] focus:ring-1 focus:ring-[#D4AF37] transition-colors">
                            <button type="button"
                                    @click="showPassword = !showPassword"
                                    tabindex="-1"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-200 focus:outline-none">
                                <!-- Eye Open Icon -->
                                <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <!-- Eye Off Icon -->
                                <svg x-show="showPassword" x-cloak class="w-4 h-4 text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1.5" x-text="passwordHint"></p>
                    </div>

                    <!-- Remember Me Option -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   x-model="remember"
                                   value="1"
                                   class="w-4 h-4 rounded bg-black/40 border-slate-700 text-[#800000] focus:ring-[#D4AF37] focus:ring-offset-0">
                            <span class="text-xs text-slate-300">Keep me signed in for 30 days</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit"
                                :disabled="submitting"
                                class="w-full py-3 px-4 rounded-xl bg-[#800000] hover:bg-[#5C0000] text-white font-bold text-sm tracking-wide shadow-lg border border-[#D4AF37]/50 hover:border-[#D4AF37] transition-all flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]">
                            <svg x-show="submitting" x-cloak class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span x-text="submitting ? 'Verifying Credentials...' : 'Sign In to ' + (activeRole === 'SUPER_ADMIN' ? 'Admin Portal' : (activeRole === 'FACULTY' ? 'Faculty Portal' : 'Student Portal'))"></span>
                            <svg x-show="!submitting" class="w-4 h-4 text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </div>
                </form>

                <!-- Alternate Direct Links -->
                <div class="mt-6 pt-5 border-t border-slate-700/60 grid grid-cols-2 gap-2 text-center text-xs">
                    <a href="/admission" class="py-2 px-3 rounded-lg bg-black/20 hover:bg-black/40 border border-slate-700/50 text-slate-300 hover:text-white transition-colors">
                        New Admission Form
                    </a>
                    <a href="/complaint" class="py-2 px-3 rounded-lg bg-black/20 hover:bg-black/40 border border-slate-700/50 text-slate-300 hover:text-white transition-colors">
                        Lodge Grievance
                    </a>
                </div>
            </div>

            <!-- Card Bottom Institutional Footer -->
            <div class="pt-6 border-t border-slate-700/60 text-center space-y-2">
                <div class="text-[11px] text-slate-400">
                    Need technical assistance?
                    <a href="tel:+917597052444" class="text-[#D4AF37] font-semibold hover:underline">+91-7597052444</a>
                </div>
                <div class="text-[10px] text-slate-500">
                    &copy; <?= date('Y') ?> <?= CollegeConfig::INSTITUTION_NAME ?>. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Toast Notification -->
    <div x-show="toastOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 right-6 z-50 max-w-sm w-full bg-[#800000] border border-[#D4AF37] text-white p-4 rounded-xl shadow-2xl flex items-start gap-3">
        <svg class="w-5 h-5 text-[#D4AF37] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="flex-1 text-xs leading-relaxed" x-text="toastMessage"></div>
        <button type="button" @click="toastOpen = false" class="text-white/70 hover:text-white text-xs">✕</button>
    </div>

    <!-- Alpine.js Forgot Password Help Modal -->
    <div x-show="forgotModalOpen"
         x-cloak
         class="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4 z-50"
         @keydown.escape.window="forgotModalOpen = false">
        <div class="bg-[#001836] border border-[#5C0000] text-slate-100 rounded-2xl max-w-md w-full p-6 shadow-2xl relative"
             @click.away="forgotModalOpen = false">
            <button type="button"
                    @click="forgotModalOpen = false"
                    class="absolute top-4 right-4 text-slate-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-[#800000] border border-[#D4AF37] flex items-center justify-center text-[#D4AF37] flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-base font-bold text-white">Credential Recovery Assistance</h4>
                    <span class="text-xs text-[#D4AF37]" x-text="'Role: ' + activeRole"></span>
                </div>
            </div>

            <div class="text-xs text-slate-300 space-y-3 leading-relaxed bg-black/30 p-4 rounded-xl border border-slate-700/60">
                <template x-if="activeRole === 'SUPER_ADMIN'">
                    <p>
                        Super Admin credentials are cryptographic and managed directly by the College Principal Secretariat.
                        Contact the Principal directly via <a href="mailto:mkrcollege@gmail.com" class="text-[#D4AF37] underline">mkrcollege@gmail.com</a>.
                    </p>
                </template>
                <template x-if="activeRole === 'FACULTY'">
                    <p>
                        Faculty accounts can be re-initialized by the Principal through the Administrative Control Console.
                        You may also use your registered Mobile Number or Staff Code as your default security password.
                    </p>
                </template>
                <template x-if="activeRole === 'STUDENT'">
                    <div>
                        <p class="font-semibold text-white mb-1">Student Default Password:</p>
                        <p>Your password is your registered <strong>Date of Birth</strong> in <code class="text-[#D4AF37]">YYYY-MM-DD</code> format (e.g. 2005-08-15) or your registered 10-digit mobile number.</p>
                        <p class="mt-2 text-slate-400">If your records require correction, lodge a ticket through the <a href="/complaint" class="text-[#D4AF37] underline">Helpdesk Redressal Form</a>.</p>
                    </div>
                </template>
            </div>

            <div class="mt-5 flex items-center justify-between">
                <a href="tel:+917597052444" class="text-xs text-[#D4AF37] hover:underline flex items-center gap-1 font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    Call Helpline: +91-7597052444
                </a>
                <button type="button"
                        @click="forgotModalOpen = false"
                        class="px-4 py-2 rounded-lg bg-[#800000] hover:bg-[#5C0000] text-xs font-bold text-white transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</body>
</html>
