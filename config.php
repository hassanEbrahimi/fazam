<?php
/**
 * پیکربندی و اتصال به دیتابیس SQLite
 */

date_default_timezone_set('Asia/Tehran');

// هدرهای امنیتی (قبل از هر خروجی)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

define('DB_PATH', __DIR__ . '/data/share.db');
define('UPLOAD_DIR', __DIR__ . '/uploads');

/** پسوندهای ممنوع برای آپلود (اجرایی و اسکریپتی) */
define('BLOCKED_EXTENSIONS', [
    'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phar', 'phtml', 'phps', 'inc',
    'js', 'jsp', 'jspx', 'jsw', 'jsx', 'vbs', 'vbe', 'ws', 'wsf', 'wsc', 'wsh',
    'asp', 'aspx', 'asa', 'cer', 'cdx', 'htr', 'shtml', 'shtm', 'stm',
    'cgi', 'pl', 'py', 'pyc', 'pyo', 'rb', 'sh', 'bash', 'csh', 'ksh',
    'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif',
    'htaccess', 'htpasswd', 'ini',
]);
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($scriptDir === '/' ? '' : rtrim($scriptDir, '/')));
define('CODE_LENGTH', 4);
/** حداکثر طول متن به‌اشتراک‌گذاری‌شده (کاراکتر) */
define('MAX_TEXT_LENGTH', 200000);
/** حداکثر تعداد فایل در یک آپلود */
define('MAX_FILES_COUNT', 20);
/** حداکثر حجم هر فایل (بایت) — 25 مگابایت */
define('MAX_FILE_SIZE', 25 * 1024 * 1024);

// ایجاد پوشه‌ها
if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    $htaccess = UPLOAD_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "# جلوگیری از اجرای اسکریپت در پوشه آپلود\n<FilesMatch \"\\.(php|php[3-8]?|phtml|phar|phps|inc|pl|py|cgi|asp|aspx|jsp|js|vbs|htaccess)$\">\n    Require all denied\n</FilesMatch>\n");
    }
}

/**
 * بررسی مجاز بودن پسوند فایل برای آپلود
 */
function isExtensionAllowed($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === '') return true;
    return !in_array($ext, BLOCKED_EXTENSIONS, true);
}

/**
 * سانیتایز متن به‌اشتراک‌گذاری‌شده — حذف تگ‌ها و الگوهای مخرب (اسکریپت، PHP، جاوااسکریپت و غیره)
 */
function sanitizeSharedText($content) {
    if ($content === '') return '';
    // حذف تگ‌های PHP
    $content = preg_replace('/<\?php.*?\?>/is', '', $content);
    $content = preg_replace('/<\?.*?\?>/s', '', $content);
    // حذف تگ script و محتوا
    $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
    $content = preg_replace('/<script\b[^>]*\/?>/i', '', $content);
    // حذف javascript: و data: خطرناک در لینک‌ها
    $content = preg_replace('/\bjavascript\s*:/i', '', $content);
    $content = preg_replace('/\bdata\s*:\s*text\/html[^,\s]*/i', '', $content);
    $content = preg_replace('/\bdata\s*:\s*application\/x-(?:php|javascript)[^,\s]*/i', '', $content);
    // حذف vbscript:
    $content = preg_replace('/\bvbscript\s*:/i', '', $content);
    // حذف رویدادهای on* در تگ‌ها (مثل onclick=)
    $content = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);
    $content = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $content);
    // حذف تگ‌های iframe، object، embed
    $content = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $content);
    $content = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $content);
    $content = preg_replace('/<embed\b[^>]*\/?>/i', '', $content);
    return trim($content);
}

/**
 * تولید یا خواندن توکن CSRF برای فرم‌ها
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * اعتبارسنجی توکن CSRF
 */
function csrf_validate() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['_csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    die('خطا در اتصال به دیتابیس');
}

// ایجاد جدول در اولین بار
$pdo->exec("
    CREATE TABLE IF NOT EXISTS shares (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        type TEXT NOT NULL CHECK(type IN ('file', 'text')),
        file_path TEXT,
        content TEXT,
        expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

/**
 * حذف لینک‌های منقضی شده (هر بار لود برنامه)
 */
function cleanupExpired($pdo) {
    $stmt = $pdo->query("SELECT id, code, type, file_path FROM shares WHERE datetime(expires_at) < datetime('now')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['type'] === 'file' && $row['file_path']) {
            $dir = UPLOAD_DIR . '/' . $row['code'];
            if (is_dir($dir)) {
                array_map('unlink', glob($dir . '/*'));
                rmdir($dir);
            }
        }
        $pdo->prepare("DELETE FROM shares WHERE id = ?")->execute([$row['id']]);
    }
}

/**
 * تولید کد کوتاه یکتا
 */
// حروف بزرگ انگلیسی بدون I,O,J,L (قابل اشتباه با اعداد) + اعداد 2-9
function generateCode($pdo) {
    $chars = 'ABCDEFGHKMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $exists = $pdo->prepare("SELECT 1 FROM shares WHERE code = ?");
        $exists->execute([$code]);
    } while ($exists->fetch());
    return $code;
}

// پاکسازی لینک‌های منقضی در هر درخواست
cleanupExpired($pdo);

/** حداکثر تعداد اشتراک‌های اخیر در کوکی */
define('RECENT_SHARES_MAX', 10);
define('RECENT_SHARES_COOKIE', 'recent_shares');

/**
 * افزودن یک اشتراک به لیست کوکی آخرین اشتراک‌ها
 */
function addRecentShareCookie($code, $type) {
    $list = [];
    if (!empty($_COOKIE[RECENT_SHARES_COOKIE])) {
        $dec = json_decode($_COOKIE[RECENT_SHARES_COOKIE], true);
        if (is_array($dec)) {
            $list = $dec;
        }
    }
    array_unshift($list, ['code' => $code, 'type' => $type]);
    $list = array_slice($list, 0, RECENT_SHARES_MAX);
    $cookieOpts = [
        'expires' => time() + 30 * 24 * 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie(RECENT_SHARES_COOKIE, json_encode($list), $cookieOpts);
}

/**
 * خواندن لیست آخرین اشتراک‌ها از کوکی
 * @return array<array{code: string, type: string}>
 */
function getRecentSharesFromCookie() {
    if (empty($_COOKIE[RECENT_SHARES_COOKIE])) {
        return [];
    }
    $dec = json_decode($_COOKIE[RECENT_SHARES_COOKIE], true);
    return is_array($dec) ? $dec : [];
}
