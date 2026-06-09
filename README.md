# iForum

**A modern forum that installs like a classic PHP blog.**

iForum is a lightweight community forum for teams, makers, clubs, products, and small communities that want a clean discussion space without a heavy deployment stack. Upload a zip, open the browser installer, fill in your MySQL database, and your forum is live.

[Download the PHP installer zip](https://github.com/AndyXeCM/iForum/raw/main/packages/iforum-php-template.zip) · [Release v1.0.0](https://github.com/AndyXeCM/iForum/releases/tag/v1.0.0) · [iOS template](https://github.com/AndyXeCM/iForum-iOS)

## Why iForum

Most modern forum products feel polished, but many expect a VPS, Composer, queues, containers, or a managed hosting workflow. iForum keeps the good parts: a calm interface, discussion categories, accounts, admin controls, and a JSON API, while staying friendly to ordinary PHP hosting.

## Highlights

- **Typecho-style installer**: upload `iforum-php-template.zip`, open `install.php`, fill in database and admin details.
- **Shared-hosting friendly**: PHP 8.1+, PDO MySQL/MariaDB, no Composer, no Node build, no background worker.
- **Modern community UI**: product-style landing area, category filters, thread cards, reply view, auth screens, and admin settings.
- **API-first backend**: `api.php` powers the included SwiftUI iOS template.
- **Safe installation flow**: writes `app/config.php`, creates `app/installed.lock`, and protects internal folders with `.htaccess`.
- **Clean starter content**: neutral categories and product-ready copy, with no institution-specific customization.

## What You Get

| Area | Included |
| --- | --- |
| Forum | Categories, threads, replies, views, pinned-ready schema |
| Accounts | Register, login, logout, password hashing |
| Admin | Site copy, tagline, category name, color, and order |
| Installer | Database connection, table creation, admin bootstrap |
| API | Site, categories, thread list, thread detail, login, register, publish, reply |
| Packaging | One-command zip builder and downloadable installer package |

## Install

### Ordinary PHP Hosting

1. Download [`packages/iforum-php-template.zip`](https://github.com/AndyXeCM/iForum/raw/main/packages/iforum-php-template.zip).
2. Upload it to your website directory and unzip it.
3. Visit `https://your-domain.com/install.php`.
4. Enter MySQL/MariaDB details and create the first admin account.
5. Open `index.php` and start configuring your community.

### Rebuild the Zip

```bash
bash scripts/make-zip.sh iforum-php-template.zip
```

The generated package excludes installed runtime files such as `app/config.php`, `app/installed.lock`, and uploaded files.

## Local Development

```bash
php -S 127.0.0.1:8080
```

Then open:

```text
http://127.0.0.1:8080/install.php
```

## API Preview

```text
GET  api.php?action=site
GET  api.php?action=categories
GET  api.php?action=threads
GET  api.php?action=thread&id=1
POST api.php?action=login
POST api.php?action=register
POST api.php?action=thread
POST api.php?action=reply
```

Authenticated API calls use:

```text
Authorization: Bearer <token>
```

## iOS Companion

The separate [iForum-iOS](https://github.com/AndyXeCM/iForum-iOS) project is a SwiftUI client template for this API. Deploy iForum, then point the iOS app to:

```swift
static let baseURLString = "https://your-domain.com/api.php"
```

## Server Requirements

- PHP 8.1 or newer
- `pdo_mysql`
- MySQL 5.7+ or MariaDB 10.4+
- `app/` writable during installation
- HTTPS recommended for production

## License

MIT

