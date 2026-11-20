<?php
declare(strict_types=1);

$logoSize = $size ?? 'md';
$withText = $showText ?? true;

$dimensionClasses = match ($logoSize) {
    'sm' => 'h-10 w-10',
    'lg' => 'h-24 w-24',
    'xl' => 'h-32 w-32',
    default => 'h-16 w-16',
};
?>
<div class="flex items-center gap-3">
    <a href="/" class="flex-shrink-0 transition-transform duration-200 hover:scale-105">
        <img src="/assets/logo/mkr-college-emblem.webp" 
             alt="Matashri Kaushalya Ramdev Dr. Ganesh Ray Degree College Emblem" 
             class="<?= $dimensionClasses ?> object-contain drop-shadow-sm"
             loading="eager"
             onerror="this.src='/assets/logo/mkr-dr-grd-college-logo.webp';">
    </a>
    <?php if ($withText): ?>
        <div class="flex flex-col">
            <span class="text-xs uppercase tracking-widest text-[#D4AF37] font-semibold">Affiliated with BRABU, Muzaffarpur</span>
            <span class="text-sm md:text-base font-extrabold text-white tracking-tight leading-snug">
                M.K.R. Dr. Ganesh Ray Degree College
            </span>
            <span class="text-[11px] text-slate-300">Punaura Dham, Sitamarhi, Bihar</span>
        </div>
    <?php endif; ?>
</div>
