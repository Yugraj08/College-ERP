import os
import re
import sys

def verify_phase5():
    results = []

    expected_files = [
        "public/js/vendor/alpine.min.js",
        "public/js/app.js",
        "public/css/app.css",
        "public/assets/logo/mkr-college-emblem.webp",
        "public/assets/logo/mkr-dr-grd-college-logo.webp",
        "public/assets/erp-panel/image-1.png",
        "public/assets/erp-panel/image-2.webp",
        "views/layouts/main.php",
        "views/components/college_logo.php",
        "views/components/notice_ticker.php",
        "views/components/navbar.php",
        "views/components/footer.php",
        "views/pages/home.php",
        "views/pages/index.php",
        "src/Controllers/HomeController.php",
        "public/index.php",
        "tests/LinksAndAssetsTest.php",
    ]

    for f in expected_files:
        exists = os.path.isfile(f) and os.path.getsize(f) > 20
        results.append((f"File: {f}", "PASS" if exists else "FAIL", f"Size: {os.path.getsize(f) if exists else 0} bytes"))

    # Strict typing on PHP files
    for f in expected_files:
        if f.endswith(".php"):
            with open(f, "r", encoding="utf-8") as fp:
                code = fp.read()
                if "declare(strict_types=1);" in code:
                    results.append((f"Strict Typing: {os.path.basename(f)}", "PASS", "declare(strict_types=1); confirmed"))
                else:
                    results.append((f"Strict Typing: {os.path.basename(f)}", "FAIL", "Missing declare(strict_types=1);"))

    # Notice ticker requirement check
    with open("views/components/notice_ticker.php", "r", encoding="utf-8") as fp:
        ticker_code = fp.read()
    fallback_text = "UG Semester 1 (Batch 2026–2030) Online Admission & CBCS Subject Selection is active."
    dynamic_query = "FROM notices WHERE is_published = 1 ORDER BY published_at DESC LIMIT 1"
    has_fallback = fallback_text in ticker_code
    has_query = "SELECT" in ticker_code and "notices" in ticker_code and "is_published = 1" in ticker_code
    results.append(("Notice Ticker: Dynamic Query & Fallback", "PASS" if has_fallback and has_query else "FAIL", "Dynamic DB query with institutional fallback verified"))

    # Navbar helpline and Alpine.js drawer
    with open("views/components/navbar.php", "r", encoding="utf-8") as fp:
        nav_code = fp.read()
    has_alpine = "mobileOpen" in nav_code
    has_login_btn = "/login" in nav_code
    results.append(("Navbar: Alpine Drawer & ERP Login", "PASS" if has_alpine and has_login_btn else "FAIL", "Verified in navbar.php"))

    # Layout Alpine.js & CSRF check
    with open("views/layouts/main.php", "r", encoding="utf-8") as fp:
        layout_code = fp.read()
    has_alpine_script = "alpine.min.js" in layout_code
    has_csrf_meta = 'meta name="csrf-token"' in layout_code
    results.append(("Layout: Alpine.js Script & CSRF Meta", "PASS" if has_alpine_script and has_csrf_meta else "FAIL", "Verified in main.php"))

    # Home 3 entry modules
    with open("views/pages/home.php", "r", encoding="utf-8") as fp:
        home_code = fp.read()
    has_adm = "/admission" in home_code
    has_comp = "/complaint" in home_code
    has_erp = "/login" in home_code
    results.append(("Home Page: 3 Core Entry Modules", "PASS" if has_adm and has_comp and has_erp else "FAIL", "/admission, /complaint, and /login entry modules present"))

    print(f"{'Check':<46} {'Status':<8} {'Details'}")
    print("-" * 92)
    for check, status, details in results:
        print(f"{check:<46} {status:<8} {details}")

    return all(r[1] == "PASS" for r in results)

if __name__ == "__main__":
    success = verify_phase5()
    sys.exit(0 if success else 1)
