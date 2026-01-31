(function () {
    'use strict';

    // کپی لینک
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sel = this.getAttribute('data-copy');
            var el = document.querySelector(sel);
            if (!el) return;
            el.select();
            el.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                var text = btn.textContent;
                btn.textContent = 'کپی شد!';
                btn.classList.add('copied');
                setTimeout(function () {
                    btn.textContent = text;
                    btn.classList.remove('copied');
                }, 2000);
            } catch (e) {
                navigator.clipboard && navigator.clipboard.writeText(el.value).then(function () {
                    btn.textContent = 'کپی شد!';
                    btn.classList.add('copied');
                    setTimeout(function () {
                        btn.textContent = 'کپی لینک';
                        btn.classList.remove('copied');
                    }, 2000);
                });
            }
        });
    });

    // ناحیه آپلود فایل — کلیک، درگ و نمایش نام‌ها
    var fileInput = document.getElementById('fileInput');
    var fileNames = document.getElementById('fileNames');
    var fileUploadZone = document.getElementById('fileUploadZone');
    if (fileInput && fileNames && fileUploadZone) {
        function updateFileNames() {
            var names = Array.from(fileInput.files).map(function (f) { return f.name; });
            fileNames.textContent = names.length ? names.join('، ') : '';
            fileUploadZone.classList.toggle('has-files', names.length > 0);
            if (fileInput.required) fileInput.setAttribute('required', 'required');
        }
        fileInput.addEventListener('change', updateFileNames);

        fileUploadZone.addEventListener('click', function (e) {
            if (e.target !== fileInput) {
                e.preventDefault();
                fileInput.click();
            }
        });
        fileUploadZone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput.click();
            }
        });

        ['dragenter', 'dragover'].forEach(function (ev) {
            fileUploadZone.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                fileUploadZone.classList.add('drag-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            fileUploadZone.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                fileUploadZone.classList.remove('drag-over');
                if (ev === 'drop' && e.dataTransfer.files.length) {
                    var dt = new DataTransfer();
                    for (var i = 0; i < e.dataTransfer.files.length; i++) {
                        dt.items.add(e.dataTransfer.files[i]);
                    }
                    fileInput.files = dt.files;
                    updateFileNames();
                }
            });
        });
    }
})();
