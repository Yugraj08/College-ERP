<?php
declare(strict_types=1);

use App\Core\Auth;

$currentUser = Auth::user();
?>
<header x-data="{ mobileOpen: false }" class="bg-[#800000] text-white shadow-md relative z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <?php
            $size = 'md';
            $showText = true;
            require __DIR__ . '/college_logo.php';
            ?>

            <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
                <a href="/" class="text-white hover:text-[#D4AF37] transition-colors py-1">Home</a>
                <a href="/admission" class="text-slate-100 hover:text-[#D4AF37] transition-colors py-1">Online Admission</a>
                <a href="/complaint" class="text-slate-100 hover:text-[#D4AF37] transition-colors py-1">Grievance Portal</a>
                <a href="/contact" class="text-slate-100 hover:text-[#D4AF37] transition-colors py-1">Contact</a>

                <?php if ($currentUser): ?>
                    <div class="flex items-center gap-3 pl-2 border-l border-[#5C0000]">
                        <span class="text-xs text-[#D4AF37]">
                            <?= htmlspecialchars((string)($currentUser['name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <a href="/login" class="bg-[#002B5C] hover:bg-[#001836] text-white text-xs px-3.5 py-2 rounded-lg font-semibold shadow transition-all">
                            Portal
                        </a>
                    </div>
                <?php else: ?>
                    <a href="/login" class="bg-[#D4AF37] hover:bg-[#c49f2e] text-[#001836] font-bold px-4 py-2 rounded-lg shadow transition-transform hover:scale-105 inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        ERP Login
                    </a>
                <?php endif; ?>
            </nav>

            <div class="flex md:hidden items-center gap-2">
                <a href="/login" class="bg-[#D4AF37] text-[#001836] text-xs font-bold px-3 py-1.5 rounded shadow">
                    Login
                </a>
                <button @click="mobileOpen = !mobileOpen" type="button" class="p-2 rounded-md text-slate-200 hover:text-white hover:bg-[#5C0000] focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="mobileOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="mobileOpen" x-cloak class="md:hidden bg-[#5C0000] border-t border-[#800000] px-4 pt-2 pb-4 space-y-1">
        <a href="/" class="block px-3 py-2 rounded-md text-base font-medium text-white hover:bg-[#800000]">Home</a>
        <a href="/admission" class="block px-3 py-2 rounded-md text-base font-medium text-slate-100 hover:bg-[#800000]">Online Admission</a>
        <a href="/complaint" class="block px-3 py-2 rounded-md text-base font-medium text-slate-100 hover:bg-[#800000]">Grievance Portal</a>
        <a href="/contact" class="block px-3 py-2 rounded-md text-base font-medium text-slate-100 hover:bg-[#800000]">Contact</a>
        <a href="/login" class="block px-3 py-2 rounded-md text-base font-bold text-[#D4AF37] hover:bg-[#800000]">ERP Portal Login</a>
    </div>
</header>
