<?php
declare(strict_types=1);

use App\Config\CollegeConfig;
use App\Core\Auth;
use App\Core\AuthServer;

$pageTitle = isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' | Administrative Console' : 'ERP Administrative Portal | ' . CollegeConfig::INSTITUTION_NAME;
$csrf = AuthServer::csrfToken();
$currentUser = Auth::user();
$userName = htmlspecialchars((string)($currentUser['full_name'] ?? $currentUser['email'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
$userRole = htmlspecialchars((string)($currentUser['role'] ?? 'ADMIN'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= $pageTitle ?></title>

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
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased flex flex-col"
      x-data="{ sidebarOpen: false }">

    <!-- Top Admin Header Bar -->
    <header class="bg-[#800000] border-b border-[#5C0000] text-white shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button type="button"
                        @click="sidebarOpen = !sidebarOpen"
                        class="md:hidden p-2 rounded-lg text-slate-200 hover:text-white hover:bg-black/20 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <a href="/admin/principal" class="flex items-center gap-3">
                    <img src="/assets/logo/mkr-college-emblem.webp"
                         alt="College Emblem"
                         class="w-10 h-10 rounded-full bg-white p-0.5 border border-[#D4AF37]">
                    <div class="hidden sm:block">
                        <span class="block text-xs font-semibold text-[#D4AF37] uppercase tracking-wider">Administrative Console</span>
                        <h1 class="text-sm font-bold text-white truncate max-w-xs md:max-w-md"><?= CollegeConfig::INSTITUTION_NAME ?></h1>
                    </div>
                </a>
            </div>

            <!-- Profile & Quick Action Menu -->
            <div class="flex items-center gap-3">
                <div class="hidden md:flex flex-col text-right">
                    <span class="text-xs font-bold text-white"><?= $userName ?></span>
                    <span class="text-[10px] text-[#D4AF37] font-semibold"><?= $userRole ?></span>
                </div>
                <a href="/" class="text-xs text-slate-200 hover:text-white px-2.5 py-1.5 rounded-lg bg-black/20 hover:bg-black/40 border border-white/10 transition-colors">
                    Public Site
                </a>
                <a href="/logout" class="text-xs font-bold text-white bg-[#5C0000] hover:bg-black/40 border border-[#D4AF37]/50 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Content Slot -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
        <?= $content ?? '' ?>
    </main>

    <!-- Admin Footer -->
    <footer class="bg-[#001836] border-t border-slate-700 text-slate-400 text-xs py-4 px-4 text-center">
        &copy; <?= date('Y') ?> <?= CollegeConfig::INSTITUTION_NAME ?> &bull; BRABU CBCS ERP Engine
    </footer>
</body>
</html>
