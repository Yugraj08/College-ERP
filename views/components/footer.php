<?php
declare(strict_types=1);

use App\Config\CollegeConfig;
?>
<footer class="bg-[#001836] text-slate-300 border-t-4 border-[#800000]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="space-y-4 md:col-span-2">
                <div class="flex items-center gap-3">
                    <img src="/assets/logo/mkr-college-emblem.webp" 
                         alt="MKR College Emblem" 
                         class="h-14 w-14 object-contain"
                         onerror="this.src='/assets/logo/mkr-dr-grd-college-logo.webp';">
                    <div>
                        <h3 class="text-white font-extrabold text-base tracking-tight">
                            <?= CollegeConfig::INSTITUTION_NAME ?>
                        </h3>
                        <p class="text-xs text-[#D4AF37] font-semibold">
                            <?= CollegeConfig::AFFILIATED_UNIVERSITY ?>
                        </p>
                    </div>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed pr-6">
                    A premier degree institution dedicated to excellence in higher education under the 4-Year Choice Based Credit System (CBCS), providing quality undergraduate education across Social Science, Humanities, and Commerce streams.
                </p>
                <div class="text-xs text-slate-400">
                    <p><strong class="text-slate-200">Campus:</strong> <?= CollegeConfig::CAMPUS_LOCATION ?></p>
                    <p><strong class="text-slate-200">Email:</strong> <a href="mailto:<?= CollegeConfig::OFFICIAL_EMAIL ?>" class="text-[#D4AF37] hover:underline"><?= CollegeConfig::OFFICIAL_EMAIL ?></a></p>
                    <p><strong class="text-slate-200">Helpline:</strong> <a href="tel:<?= CollegeConfig::HELPLINE_PHONE ?>" class="text-[#D4AF37] hover:underline"><?= CollegeConfig::HELPLINE_PHONE ?></a></p>
                </div>
            </div>

            <div>
                <h4 class="text-white font-bold text-sm tracking-wider uppercase mb-4 border-b border-[#002B5C] pb-2">
                    Quick Links
                </h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="/" class="hover:text-[#D4AF37] transition-colors">Home Page</a></li>
                    <li><a href="/admission" class="hover:text-[#D4AF37] transition-colors">Online Admission (2026–2030)</a></li>
                    <li><a href="/complaint" class="hover:text-[#D4AF37] transition-colors">Student Grievance Redressal</a></li>
                    <li><a href="/contact" class="hover:text-[#D4AF37] transition-colors">Contact Administration</a></li>
                    <li><a href="https://brabu.net" target="_blank" rel="noopener noreferrer" class="hover:text-[#D4AF37] transition-colors">BRABU Official Portal &rarr;</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-white font-bold text-sm tracking-wider uppercase mb-4 border-b border-[#002B5C] pb-2">
                    ERP Portals
                </h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="/login?role=admin" class="hover:text-[#D4AF37] transition-colors">Principal & Admin Desk</a></li>
                    <li><a href="/login?role=faculty" class="hover:text-[#D4AF37] transition-colors">Faculty CIA & Attendance</a></li>
                    <li><a href="/login?role=student" class="hover:text-[#D4AF37] transition-colors">Student Self-Service Portal</a></li>
                    <li><a href="/admission?tab=status" class="hover:text-[#D4AF37] transition-colors">Track Admission Status</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 pt-6 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500">
            <p>&copy; <?= date('Y') ?> <?= CollegeConfig::INSTITUTION_NAME ?>. All rights reserved.</p>
            <p class="mt-2 sm:mt-0">MKR College ERP System &bull; PHP 8.2 &bull; Alpine.js &bull; LiteSpeed</p>
        </div>
    </div>
</footer>
