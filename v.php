<?php
/**
 * مشاهده / دریافت لینک کوتاه (config شامل cleanup است)
 */
require_once __DIR__ . '/config.php';

$codeRaw = isset($_GET['c']) ? trim((string) $_GET['c']) : '';
$code = preg_replace('/[^A-Za-z0-9]/', '', $codeRaw);
$code = strtoupper(mb_substr($code, 0, CODE_LENGTH));
if ($code === '' || mb_strlen($code) !== CODE_LENGTH) {
    header('Location: /index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT type, file_path, content, created_at FROM shares WHERE code = ? AND datetime(expires_at) >= datetime('now')");
$stmt->execute([$code]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>لینک نامعتبر</title><link rel="stylesheet" href="/assets/style.css"><style>.box{min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:2rem;} a{color:var(--primary);}</style></head><body><div class="box"><div><h1>لینک منقضی یا نامعتبر است</h1><p><a href="/index.php">بازگشت به صفحه اصلی</a></p></div></div></body></html>';
    exit;
}

if ($row['type'] === 'text') {
    header('Content-Type: text/html; charset=utf-8');
    $createdAt = isset($row['created_at']) ? $row['created_at'] : '';
    $createdFormatted = $createdAt ? date('Y/m/d H:i', strtotime($createdAt)) : '';
    $contentRaw = $row['content'];
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>متن به اشتراک‌گذاری‌شده</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .view-text-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem; padding: 0.75rem 1rem; background: var(--surface2); border-radius: var(--radius); }
        .view-text-meta { font-size: 0.9rem; color: var(--text-muted); }
        .view-text-meta strong { color: var(--text); }
    </style>
</head>
<body>
    <div class="app">
        <div class="view-text-box">
            <div class="view-text-top">
                <?php if ($createdFormatted): ?>
                <span class="view-text-meta"><strong>زمان به اشتراک‌گذاری:</strong> <?= htmlspecialchars($createdFormatted) ?></span>
                <?php endif; ?>
                <button type="button" class="btn btn-primary" id="copyTextBtn">کپی متن</button>
            </div>
            <pre class="view-text" id="viewTextContent"><?= htmlspecialchars($contentRaw) ?></pre>
            <a href="/index.php" class="btn btn-secondary">بازگشت به صفحه اصلی</a>
        </div>
    </div>
    <script>
        (function() {
            var btn = document.getElementById('copyTextBtn');
            var pre = document.getElementById('viewTextContent');
            if (!btn || !pre) return;
            btn.addEventListener('click', function() {
                var text = pre.innerText || pre.textContent || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        var old = btn.textContent;
                        btn.textContent = 'کپی شد!';
                        btn.disabled = true;
                        setTimeout(function() { btn.textContent = old; btn.disabled = false; }, 1500);
                    }).catch(function() { fallback(); });
                } else { fallback(); }
                function fallback() {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed'; ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        document.execCommand('copy');
                        btn.textContent = 'کپی شد!';
                        btn.disabled = true;
                        setTimeout(function() { btn.textContent = 'کپی متن'; btn.disabled = false; }, 1500);
                    } catch (e) {}
                    document.body.removeChild(ta);
                }
            });
        })();
    </script>
</body>
</html>
    <?php
    exit;
}

// نوع فایل
$files = json_decode($row['file_path'], true);
if (!is_array($files)) {
    $files = [];
}
$dir = UPLOAD_DIR . '/' . $code;

// اگر یک فایل است و درخواست مستقیم دانلود است
$download = isset($_GET['f']) ? (string) $_GET['f'] : null;
$safeFileName = $download !== '' ? preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($download)) : '';
if ($safeFileName !== '' && in_array($safeFileName, $files, true)) {
    $path = $dir . '/' . $safeFileName;
    $realPath = realpath($path);
    $realDir = realpath($dir);
    $dirLen = strlen($realDir);
    $insideDir = $realPath !== false && $realDir !== false && strpos($realPath, $realDir) === 0
        && ($dirLen === strlen($realPath) || in_array($realPath[$dirLen], ['/', '\\'], true));
    if ($insideDir && is_file($path)) {
        $mime = function_exists('mime_content_type') ? @mime_content_type($path) : null;
        header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
        $dispName = str_replace(['"', "\r", "\n"], ['%22', '', ''], $safeFileName);
        header('Content-Disposition: attachment; filename="' . $dispName . '"');
        readfile($path);
        exit;
    }
}

// لیست فایل‌ها
header('Content-Type: text/html; charset=utf-8');
$createdAt = isset($row['created_at']) ? $row['created_at'] : '';
$createdFormatted = $createdAt ? date('Y/m/d H:i', strtotime($createdAt)) : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دانلود فایل‌ها</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .view-files-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem; padding: 0.75rem 1rem; background: var(--surface2); border-radius: var(--radius); }
        .view-files-meta { font-size: 0.9rem; color: var(--text-muted); }
        .view-files-meta strong { color: var(--text); }
    </style>
</head>
<body>
    <div class="app">
        <div class="view-files-box">
            <?php if ($createdFormatted): ?>
            <div class="view-files-top">
                <span class="view-files-meta"><strong>زمان به اشتراک‌گذاری:</strong> <?= htmlspecialchars($createdFormatted) ?></span>
            </div>
            <?php endif; ?>
            <h2>فایل‌های به اشتراک‌گذاری‌شده</h2>
            <ul class="file-list">
                <?php foreach ($files as $f): $f = is_string($f) ? $f : ''; if ($f === '') continue; ?>
                    <li><a href="/v.php?c=<?= urlencode($code) ?>&f=<?= urlencode($f) ?>"><?= htmlspecialchars($f) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <a href="/index.php" class="btn btn-secondary">بازگشت به صفحه اصلی</a>
        </div>
    </div>
</body>
</html>
