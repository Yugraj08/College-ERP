<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Logger;

$fallbackNotice = 'UG Semester 1 (Batch 2026–2030) Online Admission & CBCS Subject Selection is active.';
$activeNotice = null;

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT id, title, published_at 
        FROM notices 
        WHERE is_published = 1 
        ORDER BY published_at DESC 
        LIMIT 1
    ');
    $stmt->execute();
    $activeNotice = $stmt->fetch();
} catch (Throwable $e) {
    Logger::warning('Failed to fetch active notice ticker: ' . $e->getMessage());
}

$noticeTitle = $activeNotice['title'] ?? $fallbackNotice;
?>
<div class="bg-[#001836] border-y border-[#5C0000] text-slate-100 py-2 px-4 shadow-inner">
    <div class="max-w-7xl mx-auto flex items-center gap-3">
        <div class="flex-shrink-0 flex items-center gap-2 bg-[#800000] text-[#D4AF37] px-3 py-1 rounded-md text-xs font-bold uppercase tracking-wider shadow">
            <span class="w-2 h-2 rounded-full bg-[#D4AF37] animate-ping"></span>
            Notice
        </div>
        <div class="overflow-hidden flex-1 relative h-6 flex items-center">
            <div class="animate-marquee font-medium text-xs md:text-sm text-slate-200">
                <?= htmlspecialchars($noticeTitle, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <div class="hidden sm:flex items-center gap-1 text-[11px] text-slate-400 flex-shrink-0">
            <span>Helpline:</span>
            <a href="tel:+917597052444" class="text-[#D4AF37] font-semibold hover:underline">+91-7597052444</a>
        </div>
    </div>
</div>
