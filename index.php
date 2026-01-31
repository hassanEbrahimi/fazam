<?php
require_once __DIR__ . '/config.php';

$allowedPages = ['home', 'share-file', 'share-text'];
$page = isset($_GET['page']) && in_array($_GET['page'], $allowedPages, true) ? $_GET['page'] : 'home';
$recentShares = ($page === 'home') ? getRecentSharesFromCookie() : [];

if ($page === 'share-file') {
    include __DIR__ . '/share-file.php';
    exit;
}
if ($page === 'share-text') {
    include __DIR__ . '/share-text.php';
    exit;
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اشتراک‌گذاری فایل و متن</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app">
        <header class="header">
            <h1>اشتراک‌گذاری ساده</h1>
            <p>فایل یا متن خود را به اشتراک بگذارید و لینک کوتاه دریافت کنید</p>
        </header>

        <main class="main">
            <div class="cards">
                <a href="?page=share-file" class="card card-file">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="18" x2="12" y2="12"/>
                            <line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                    </div>
                    <h2>اشتراک فایل</h2>
                    <p>یک یا چند فایل انتخاب کنید و لینک کوتاه بگیرید</p>
                </a>

                <a href="?page=share-text" class="card card-text">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="4" y1="6" x2="20" y2="6"/>
                            <line x1="4" y1="12" x2="16" y2="12"/>
                            <line x1="4" y1="18" x2="12" y2="18"/>
                        </svg>
                    </div>
                    <h2>اشتراک متن</h2>
                    <p>متن خود را وارد کنید و لینک کوتاه دریافت کنید</p>
                </a>
            </div>

            <div class="view-code-box">
                <p class="view-code-label">مشاهده فایل و متن آپلود شده</p>
                <form class="view-code-form" action="/v.php" method="get">
                    <input style="direction: ltr;" type="text" name="c" maxlength="4" placeholder="کد ۴ کاراکتری" pattern="[A-Za-z0-9]{4}" title="۴ کاراکتر حرف یا عدد" required autocomplete="off">
                    <button type="submit" class="btn btn-primary">مشاهده</button>
                </form>
            </div>

            <?php if (!empty($recentShares)): ?>
            <section class="recent-shares">
                <p class="recent-shares-title">آخرین اشتراک‌گذاری‌های شما</p>
                <ul class="recent-shares-list">
                    <?php foreach ($recentShares as $item):
                        $code = isset($item['code']) ? $item['code'] : '';
                        $type = isset($item['type']) ? $item['type'] : 'file';
                        if ($code === '') continue;
                        $label = $type === 'text' ? 'متن' : 'فایل';
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars(BASE_URL) ?>/v.php?c=<?= htmlspecialchars(urlencode($code)) ?>">
                            <span class="recent-shares-code"><?= htmlspecialchars($code) ?></span>
                            <span class="recent-shares-type">(<?= htmlspecialchars($label) ?>)</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </main>

        <footer class="footer">
            <p>لینک‌ها پس از مدت انتخاب‌شده به‌طور خودکار حذف می‌شوند</p>
            <p class="footer-oss">این برنامه اوپن‌سورس و رایگان است. می‌توانید در مشارکت کد کمک کنید یا آن را روی هاست خود بالا بیاورید. <a href="https://github.com/hassanEbrahimi/fazam" target="_blank" rel="noopener noreferrer">GitHub</a></p>
        </footer>
    </div>
</body>
</html>
