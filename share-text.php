<?php
require_once __DIR__ . '/config.php';

$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'درخواست نامعتبر. لطفاً دوباره تلاش کنید.';
    } else {
    $expiry = $_POST['expiry'] ?? '1d';
    $validExpiry = ['10m' => 10, '1h' => 60, '6h' => 360, '1d' => 1440, '5d' => 7200];
    $minutes = $validExpiry[$expiry] ?? 1440;
    $content = trim((string) ($_POST['content'] ?? ''));
    if (mb_strlen($content) > MAX_TEXT_LENGTH) {
        $content = mb_substr($content, 0, MAX_TEXT_LENGTH);
    }
    $content = sanitizeSharedText($content);

    if ($content === '') {
        $error = 'لطفاً متنی وارد کنید (محتوای مخرب مثل اسکریپت یا کد اجرایی مجاز نیست).';
    } else {
        $code = generateCode($pdo);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
        $createdAt = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO shares (code, type, content, expires_at, created_at) VALUES (?, 'text', ?, ?, ?)");
        $stmt->execute([$code, $content, $expiresAt, $createdAt]);
        $result = ['code' => $code, 'type' => 'text'];
        addRecentShareCookie($code, 'text');
    }
    }
}

$expiryMinutes = [
    '10m' => '۱۰ دقیقه',
    '1h' => '۱ ساعت',
    '6h' => '۶ ساعت',
    '1d' => '۱ روز',
    '5d' => '۵ روز',
  
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اشتراک متن</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app">
        <header class="header small">
            <a href="index.php" class="back">← بازگشت</a>
            <h1>اشتراک متن</h1>
        </header>

        <main class="main form-page">
            <?php if ($result): ?>
                <div class="result-box">
                    <p class="result-label">لینک کوتاه شما:</p>
                    <div class="link-row">
                        <input type="text" id="shortLink" readonly value="<?= htmlspecialchars(BASE_URL . '/r/' . $result['code']) ?>">
                        <button type="button" class="btn btn-copy" data-copy="#shortLink">کپی لینک</button>
                    </div>
                    <p class="result-hint">این لینک تا زمان انتخاب‌شده معتبر است.</p>
                    <p class="result-hint result-code-hint">یا کد <strong><?= htmlspecialchars($result['code']) ?></strong> را در صفحهٔ اصلی، در بخش «مشاهده فایل و متن آپلود شده»، وارد کنید.</p>
                </div>
            <?php else: ?>
                <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
                <form class="share-form" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <label class="field">
                        <span>متن</span>
                        <textarea name="content" rows="6" maxlength="<?= (int) MAX_TEXT_LENGTH ?>" placeholder="متن خود را اینجا بنویسید..." required></textarea>
                    </label>
                    <label class="field">
                        <span>مدت اعتبار لینک</span>
                        <select name="expiry">
                            <?php foreach ($expiryMinutes as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $val === '1d' ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary">دریافت لینک کوتاه</button>
                </form>
            <?php endif; ?>
        </main>
        <footer class="footer">
            <p class="footer-oss">این برنامه اوپن‌سورس و رایگان است. می‌توانید در مشارکت کد کمک کنید یا آن را روی هاست خود بالا بیاورید. <a href="https://github.com/hassanEbrahimi/fazam" target="_blank" rel="noopener noreferrer">GitHub</a></p>
        </footer>
    </div>
    <script src="assets/app.js"></script>
</body>
</html>
