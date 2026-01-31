# Simple Share — File & Text Sharing

A minimal, self-hosted PHP application to share files or text via short links. Upload files or paste text, get a 4-character code, and share. Links expire automatically after the chosen duration.

**اشتراک‌گذاری ساده** — فایل یا متن را آپلود کنید، لینک کوتاه بگیرید.

---

## Features

- **Share files** — Upload one or multiple files (up to 20 files, 25 MB each), get a short link
- **Share text** — Paste text (up to 200,000 characters), get a short link
- **Expiring links** — Choose expiry: 10 min, 1h, 6h, 1 day, or 5 days 
- **Short codes** — 4-character alphanumeric codes (e.g. `r/AB12`)
- **Pretty URL** — Optional rewrite: `yoursite.com/r/AB12` → view page
- **Recent shares** — Last shared links stored in a cookie (client-side)
- **RTL / Persian UI** — Interface in Persian (Fa) with RTL layout
- **Security** — CSRF protection, input validation, safe file types, security headers

---

## Requirements

- **PHP** 7.3+ (with PDO SQLite, `mbstring`, `session`, `json`)
- **Web server** — Apache (with `mod_rewrite`) or nginx (see below)

---

## Installation

1. **Clone or download** the project into your web root (e.g. `htdocs/simple-share` or a subdomain).

2. **Web server**
   - **Apache:** Ensure `mod_rewrite` is enabled. The repo includes an `.htaccess` for clean URLs.
   - **nginx:** Add a rewrite rule so `/r/<code>` goes to `v.php?c=<code>`:
     ```nginx
     location /r/ {
         rewrite ^/r/([A-Za-z0-9]+)$ /v.php?c=$1 last;
     }
     ```

3. **Permissions**  
   The app will create `data/` (SQLite DB) and `uploads/` (uploaded files) on first run. Ensure the web server can write to the project directory, or create them manually:
   ```bash
   mkdir -p data uploads
   chmod 755 data uploads
   ```

4. **Open in browser**  
   Visit `http://localhost/simple-share` (or your domain). No installer — it works out of the box.

---

## Usage

1. **Home** — Choose “Share file” or “Share text”.
2. **Share file** — Select file(s), set expiry, submit. Copy the short link (e.g. `https://yoursite.com/r/XY99`).
3. **Share text** — Paste text, set expiry, submit. Copy the short link.
4. **View** — Open the link or enter the 4-character code on the home page under “View shared file/text”.

Links and files are removed automatically after the chosen expiry time.

---

## Configuration

Edit `config.php` to change:

| Constant            | Default   | Description                    |
|---------------------|-----------|--------------------------------|
| `DB_PATH`           | `data/share.db` | SQLite database path    |
| `UPLOAD_DIR`        | `uploads`       | Upload directory        |
| `CODE_LENGTH`       | 4               | Short code length       |
| `MAX_TEXT_LENGTH`   | 200000          | Max characters for text|
| `MAX_FILES_COUNT`   | 20              | Max files per upload   |
| `MAX_FILE_SIZE`     | 25 MB            | Max size per file      |
| `BLOCKED_EXTENSIONS`| (list in file)  | Blocked file types     |

`BASE_URL` is set automatically from the current request.

---

## Project structure

```
├── index.php        # Home & router
├── share-file.php   # File upload form & handler
├── share-text.php   # Text share form & handler
├── v.php            # View / download by code
├── config.php       # Config, DB, helpers, security
├── .htaccess        # Rewrite /r/<code> → v.php?c=<code>
├── README.md
├── assets/
│   ├── style.css
│   └── app.js
├── data/            # Created at runtime (SQLite DB)
└── uploads/         # Created at runtime (uploaded files)
```

---

## Security

- **CSRF** — Forms use a session-based CSRF token.
- **Input** — `page` and `code` are whitelisted/validated; text is sanitized (no scripts).
- **Files** — Extension whitelist, size/count limits, safe filenames; `.htaccess` in upload dir blocks script execution.
- **Download** — Path traversal prevented; download filename validated against the share’s file list.
- **Headers** — `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`.
- **Cookies** — Recent-shares cookie uses `HttpOnly`, `SameSite=Lax`, and `Secure` when on HTTPS.

---

## License

MIT (or your choice). Use and modify freely.

---

## Contributing

1. Fork the repo.  
2. Create a branch, make changes, then open a Pull Request.

If you share this project on GitHub, a link to the repo in the README is appreciated.
