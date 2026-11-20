<?php
declare(strict_types=1);

use App\Config\CollegeConfig;
use App\Core\AuthServer;

$pageTitle = isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' | ' . CollegeConfig::INSTITUTION_NAME : CollegeConfig::INSTITUTION_NAME . ' (BRABU)';
$csrf = AuthServer::csrfToken();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= $pageTitle ?></title>

    <link rel="icon" type="image/webp" href="/assets/logo/mkr-college-emblem.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN for instant styling without Node build dependency -->
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

    <!-- Custom CSS (design tokens, marquee, print stylesheet) -->
    <link rel="stylesheet" href="/css/app.css">

    <!-- Alpine.js (Core 15KB reactive bundle) -->
    <script defer src="/js/vendor/alpine.min.js"></script>

    <!-- Global App Helpers & CSRF -->
    <script defer src="/js/app.js"></script>
</head>
<body class="flex flex-col min-h-screen font-sans text-slate-800 antialiased bg-slate-50">
    <?php require dirname(__DIR__) . '/components/navbar.php'; ?>
    <?php require dirname(__DIR__) . '/components/notice_ticker.php'; ?>

    <main class="flex-grow">
        <?= $content ?? '' ?>
    </main>

    <?php require dirname(__DIR__) . '/components/footer.php'; ?>
</body>
</html>
