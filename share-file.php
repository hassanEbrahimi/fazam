<?php
require_once __DIR__ . '/config.php';

$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'درخواست نامعتبر. لطفاً دوباره تلاش کنید.';
    } else {
    $expiry = $_POST['expiry'] ?? '1d';
    $validExpiry = ['10m' => 10, '1h' => 60, '6h' => 360, '1d' => 1440, '5d' => 7200, '30d' => 43200];
    $minutes = $validExpiry[$expiry] ?? 1440;

    $count = isset($_FILES['files']['name']) ? count($_FILES['files']['name']) : 0;
    if ($count > MAX_FILES_COUNT) {
        $error = 'حداکثر ' . MAX_FILES_COUNT . ' فایل مجاز است.';
    } elseif (empty($_FILES['files']['name'][0]) || $_FILES['files']['error'][0] === UPLOAD_ERR_NO_FILE) {
        $error = 'لطفاً حداقل یک فایل انتخاب کنید.';
    } else {
        // بررسی پسوند و حجم فایل‌ها قبل از آپلود
        $blocked = [];
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                if (isset($_FILES['files']['size'][$i]) && $_FILES['files']['size'][$i] > MAX_FILE_SIZE) {
                    $error = 'حجم هر فایل حداکثر ' . round(MAX_FILE_SIZE / (1024 * 1024)) . ' مگابایت مجاز است.';
                    break;
                }
                $name = basename($_FILES['files']['name'][$i]);
                if (!isExtensionAllowed($name)) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $blocked[$ext] = true;
                }
            }
        }
        if ($error !== '') {
            // خطا از حجم یا تعداد قبلاً ست شده
        } elseif (!empty($blocked)) {
            $blockedList = implode(', ', array_keys($blocked));
            $error = 'نوع فایل مجاز نیست. پسوندهای ممنوع: ' . htmlspecialchars($blockedList) . ' (مثل php، js، exe و سایر فایل‌های اجرایی/اسکریپتی).';
        } else {
            $code = generateCode($pdo);
            $targetDir = UPLOAD_DIR . '/' . $code;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
                $htaccess = $targetDir . '/.htaccess';
                file_put_contents($htaccess, "# جلوگیری از اجرای اسکریپت\n<FilesMatch \"\\.(php|php[3-8]?|phtml|phar|phps|inc|pl|py|cgi|asp|aspx|jsp|js|vbs|htaccess)$\">\n    Require all denied\n</FilesMatch>\n");
            }

            $files = [];
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $name = basename($_FILES['files']['name'][$i]);
                    if (!isExtensionAllowed($name)) continue; // دوباره چک
                    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                    $target = $targetDir . '/' . $safe;
                    if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
                        $files[] = $safe;
                    }
                }
            }

            if (empty($files)) {
                $error = 'آپلود فایل با خطا مواجه شد.';
                if (is_dir($targetDir)) {
                    array_map('unlink', glob($targetDir . '/*'));
                    rmdir($targetDir);
                }
            } else {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
            $filePath = json_encode($files);
            $createdAt = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO shares (code, type, file_path, expires_at, created_at) VALUES (?, 'file', ?, ?, ?)");
            $stmt->execute([$code, $filePath, $expiresAt, $createdAt]);
            $result = ['code' => $code, 'type' => 'file'];
            addRecentShareCookie($code, 'file');
            }
        }
    }
    }
}

$expiryMinutes = [
    '10m' => '۱۰ دقیقه',
    '1h' => '۱ ساعت',
    '6h' => '۶ ساعت',
    '1d' => '۱ روز',
    '5d' => '۵ روز',
    '30d' => '۳۰ روز',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اشتراک فایل</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app">
        <header class="header small">
            <a href="index.php" class="back">← بازگشت</a>
            <h1>اشتراک فایل</h1>
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
                <form class="share-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <label class="field field-file">
                        <span>فایل‌ها (یک یا چند فایل)</span>
                        <div class="file-upload-zone" id="fileUploadZone" role="button" tabindex="0">
                            <input type="file" name="files[]" multiple required accept="*/*" class="file-input" id="fileInput">
                            <span class="file-upload-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            </span>
                            <span class="file-upload-text">فایل‌ها را اینجا بکشید یا کلیک کنید</span>
                            <span class="file-upload-hint">یک یا چند فایل انتخاب کنید</span>
                        </div>
                        <span class="file-names" id="fileNames"></span>
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
    </div>
    <script src="assets/app.js"></script>
</body>
</html>
